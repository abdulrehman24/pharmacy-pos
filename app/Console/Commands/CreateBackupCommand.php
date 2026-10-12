<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class CreateBackupCommand extends Command
{
    protected $signature = 'app:backup';

    protected $description = 'Generate a complete authoritative system backup containing database and attachments';

    public function handle(BackupService $backupService): int
    {
        $this->info('Starting automated system backup...');

        try {
            $backupRun = $backupService->createBackup(null);
            $this->info("Backup created successfully: {$backupRun->file_name} ({$backupRun->file_size_bytes} bytes)");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Backup creation failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
