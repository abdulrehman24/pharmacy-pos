<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\BackupRun;
use App\Models\PrescriptionAttachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupService
{
    /**
     * Create an authoritative complete backup containing database and attachments.
     */
    public function createBackup(?int $userId = null): BackupRun
    {
        $backupDir = storage_path('app/backups');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $uniqueSuffix = bin2hex(random_bytes(4));
        $fileName = 'backup_' . date('Y-m-d_His') . '_' . $uniqueSuffix . '.zip';
        $filePath = $backupDir . '/' . $fileName;

        try {
            $sqlContent = $this->dumpDatabase();

            $zip = new ZipArchive();
            if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException("Unable to create zip file at {$filePath}");
            }

            $manifestFiles = [];

            // 1. Add SQL dump
            $zip->addFromString('database.sql', $sqlContent);
            $manifestFiles['database.sql'] = [
                'sha256' => hash('sha256', $sqlContent),
                'size' => strlen($sqlContent),
            ];

            // 2. Add Prescription attachments
            $attachments = PrescriptionAttachment::all();
            foreach ($attachments as $attachment) {
                if (Storage::disk('local')->exists($attachment->file_path)) {
                    $content = Storage::disk('local')->get($attachment->file_path);
                    $zipPath = 'attachments/' . ltrim($attachment->file_path, '/');
                    $zip->addFromString($zipPath, $content);
                    $manifestFiles[$zipPath] = [
                        'sha256' => hash('sha256', $content),
                        'size' => strlen($content),
                    ];
                }
            }

            // Also check for any loose prescription files in storage/app/prescriptions
            $allStorageFiles = Storage::disk('local')->allFiles('prescriptions');
            foreach ($allStorageFiles as $sFile) {
                $zipPath = 'attachments/' . ltrim($sFile, '/');
                if (!isset($manifestFiles[$zipPath])) {
                    $content = Storage::disk('local')->get($sFile);
                    $zip->addFromString($zipPath, $content);
                    $manifestFiles[$zipPath] = [
                        'sha256' => hash('sha256', $content),
                        'size' => strlen($content),
                    ];
                }
            }

            // 3. Add Manifest
            $manifest = [
                'version' => 1,
                'app_name' => config('app.name', 'Pakistan Pharmacy POS'),
                'created_at' => now()->toIso8601String(),
                'connection' => config('database.default'),
                'total_files' => count($manifestFiles),
                'files' => $manifestFiles,
            ];

            $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $zip->addFromString('manifest.json', $manifestJson);

            $zip->close();

            $fileSize = file_exists($filePath) ? filesize($filePath) : 0;

            $backupRun = BackupRun::create([
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_size_bytes' => $fileSize,
                'status' => 'completed',
                'error_message' => null,
                'initiated_by' => $userId,
            ]);

            AuditEvent::log(
                'backup_created',
                'BackupRun',
                $backupRun->id,
                null,
                ['file_name' => $fileName, 'size_bytes' => $fileSize]
            );

            return $backupRun;
        } catch (\Throwable $e) {
            $backupRun = BackupRun::create([
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_size_bytes' => file_exists($filePath) ? filesize($filePath) : 0,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'initiated_by' => $userId,
            ]);

            AuditEvent::log(
                'backup_failed',
                'BackupRun',
                $backupRun->id,
                null,
                ['error' => $e->getMessage()]
            );

            throw $e;
        }
    }

    /**
     * Restore from a verified backup archive.
     */
    public function restoreBackup(string|BackupRun $backup, ?int $userId = null, bool $createSafetyBackup = true): array
    {
        $backupModel = null;
        if ($backup instanceof BackupRun) {
            $backupModel = $backup;
            $filePath = $backup->file_path;
        } else {
            $filePath = $backup;
            $backupModel = BackupRun::where('file_path', $filePath)->first();
        }

        if (!file_exists($filePath)) {
            throw new \RuntimeException("Backup file not found at: {$filePath}");
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException("Invalid or corrupted zip archive: {$filePath}");
        }

        $manifestContent = $zip->getFromName('manifest.json');
        if (!$manifestContent) {
            $zip->close();
            throw new \RuntimeException("Backup archive is missing manifest.json");
        }

        $manifest = json_decode($manifestContent, true);
        if (!$manifest || !isset($manifest['files'])) {
            $zip->close();
            throw new \RuntimeException("Malformed manifest.json in backup archive");
        }

        // Verify checksums of all files
        foreach ($manifest['files'] as $archivePath => $meta) {
            $content = $zip->getFromName($archivePath);
            if ($content === false) {
                $zip->close();
                throw new \RuntimeException("Manifest file {$archivePath} is missing in archive.");
            }
            $hash = hash('sha256', $content);
            if ($hash !== $meta['sha256']) {
                $zip->close();
                throw new \RuntimeException("Checksum mismatch for {$archivePath}. Expected {$meta['sha256']} but got {$hash}.");
            }
        }

        // Create safety pre-restore backup if requested
        $safetyBackup = null;
        if ($createSafetyBackup) {
            try {
                $safetyBackup = $this->createBackup($userId);
            } catch (\Throwable $e) {
                // If pre-restore fails, continue or warn
            }
        }

        // Restore SQL
        $sqlContent = $zip->getFromName('database.sql');
        $this->restoreDatabase($sqlContent);

        // Restore Attachments
        $restoredFiles = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, 'attachments/')) {
                $storageRelativePath = substr($name, strlen('attachments/'));
                $fileData = $zip->getFromIndex($i);
                Storage::disk('local')->put($storageRelativePath, $fileData);
                $restoredFiles++;
            }
        }

        $zip->close();

        AuditEvent::log(
            'backup_restored',
            $backupModel ? 'BackupRun' : null,
            $backupModel?->id,
            null,
            [
                'file_name' => basename($filePath),
                'safety_backup_id' => $safetyBackup?->id,
                'restored_attachments' => $restoredFiles,
            ]
        );

        return [
            'status' => 'completed',
            'restored_attachments' => $restoredFiles,
            'safety_backup_id' => $safetyBackup?->id,
        ];
    }

    /**
     * Dump the database safely using argument arrays and isolated environments.
     */
    protected function dumpDatabase(): string
    {
        $connection = config('database.default');

        if ($connection === 'mysql') {
            $dbName = config('database.connections.mysql.database');
            $dbUser = config('database.connections.mysql.username');
            $dbPass = config('database.connections.mysql.password');
            $dbHost = config('database.connections.mysql.host');
            $dbPort = config('database.connections.mysql.port', 3306);

            $args = [
                'mysqldump',
                '--host=' . $dbHost,
                '--port=' . $dbPort,
                '--user=' . $dbUser,
                '--single-transaction',
                '--quick',
                '--skip-lock-tables',
                '--set-gtid-purged=OFF',
                '--no-tablespaces',
                $dbName,
            ];

            $env = [];
            if (!empty($dbPass)) {
                $env['MYSQL_PWD'] = (string) $dbPass;
            }

            $process = new Process($args, null, $env);
            $process->setTimeout(300);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException('mysqldump failed: ' . $process->getErrorOutput());
            }

            return $process->getOutput();
        }

        // SQLite dump logic (e.g. for testing / local sqlite)
        if ($connection === 'sqlite') {
            $tables = DB::select("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            $lines = [];
            $lines[] = "PRAGMA foreign_keys = OFF;";

            foreach ($tables as $t) {
                if ($t->name === 'migrations') {
                    continue;
                }
                $lines[] = "DELETE FROM `{$t->name}`;";

                $rows = DB::table($t->name)->get();
                foreach ($rows as $row) {
                    $rowArray = (array) $row;
                    if (!empty($rowArray)) {
                        $cols = implode('`, `', array_keys($rowArray));
                        $vals = implode(', ', array_map(function ($val) {
                            if (is_null($val)) {
                                return 'NULL';
                            }
                            return "'" . str_replace("'", "''", (string)$val) . "'";
                        }, array_values($rowArray)));
                        $lines[] = "INSERT INTO `{$t->name}` (`{$cols}`) VALUES ({$vals});";
                    }
                }
            }

            $lines[] = "PRAGMA foreign_keys = ON;";
            return implode("\n", $lines);
        }

        throw new \RuntimeException("Unsupported database driver for backup: {$connection}");
    }

    /**
     * Restore SQL dump into database.
     */
    protected function restoreDatabase(string $sqlContent): void
    {
        $connection = config('database.default');

        if ($connection === 'mysql') {
            $dbName = config('database.connections.mysql.database');
            $dbUser = config('database.connections.mysql.username');
            $dbPass = config('database.connections.mysql.password');
            $dbHost = config('database.connections.mysql.host');
            $dbPort = config('database.connections.mysql.port', 3306);

            $args = [
                'mysql',
                '--host=' . $dbHost,
                '--port=' . $dbPort,
                '--user=' . $dbUser,
                $dbName,
            ];

            $env = [];
            if (!empty($dbPass)) {
                $env['MYSQL_PWD'] = (string) $dbPass;
            }

            $process = new Process($args, null, $env, $sqlContent);
            $process->setTimeout(300);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException('mysql restore failed: ' . $process->getErrorOutput());
            }
            return;
        }

        if ($connection === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
            DB::unprepared($sqlContent);
            DB::statement('PRAGMA foreign_keys = ON;');
            return;
        }

        throw new \RuntimeException("Unsupported database driver for restore: {$connection}");
    }
}
