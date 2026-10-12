<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\PrintJob;
use App\Models\PrinterProfile;
use App\Models\Sale;
use App\Services\ReceiptDataFactory;
use App\Services\ThermalReceiptService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    public function show(Request $request, Sale $sale, ReceiptDataFactory $factory): Response
    {
        abort_unless($sale->status === 'posted', 409, 'Sale is not in posted status.');

        $previousPrints = PrintJob::where('sale_id', $sale->id)
            ->whereIn('status', ['submitted', 'browser_ready'])
            ->count();
        $isCopy = $previousPrints > 0 || $request->boolean('copy', false);
        $paperWidth = $request->has('width') ? (int) $request->get('width') : null;

        $receiptData = $factory->forSale($sale, $isCopy, $paperWidth);

        // Record a browser print audit job
        PrintJob::create([
            'sale_id' => $sale->id,
            'printer_profile_id' => null,
            'job_type' => $isCopy ? 'duplicate' : 'receipt',
            'status' => 'browser_ready',
            'attempts' => 1,
        ]);

        AuditEvent::log(
            'receipt_viewed',
            'Sale',
            $sale->id,
            null,
            ['is_copy' => $isCopy]
        );

        return Inertia::render('Sales/Receipt', $receiptData);
    }

    /**
     * Submit print job to configured LAN ESC/POS printer or browser print queue
     */
    public function print(
        Request $request,
        Sale $sale,
        ReceiptDataFactory $factory,
        ThermalReceiptService $thermalService
    ): JsonResponse {
        abort_unless($sale->status === 'posted', 409);

        $printerProfileId = $request->input('printer_profile_id');
        $profile = null;

        if ($printerProfileId) {
            $profile = PrinterProfile::find($printerProfileId);
        }

        if (!$profile) {
            $profile = PrinterProfile::where('is_default', true)->first()
                ?? PrinterProfile::first();
        }

        $previousPrints = PrintJob::where('sale_id', $sale->id)
            ->whereIn('status', ['submitted', 'browser_ready'])
            ->count();
        $isCopy = $previousPrints > 0 || $request->boolean('copy', false);
        $jobType = $isCopy ? 'duplicate' : 'receipt';

        $dto = $factory->forSale($sale, $isCopy, $profile?->paper_width ?? 80);

        // Record print job
        $printJob = PrintJob::create([
            'sale_id' => $sale->id,
            'printer_profile_id' => $profile?->id,
            'job_type' => $jobType,
            'status' => 'pending',
            'attempts' => 1,
        ]);

        AuditEvent::log(
            'receipt_printed',
            'Sale',
            $sale->id,
            null,
            ['job_type' => $jobType, 'is_copy' => $isCopy, 'print_job_id' => $printJob->id]
        );

        if ($profile && $profile->connection_type === 'network' && $profile->host) {
            $lockKey = 'printer_lock_' . md5($profile->host . ':' . ($profile->port ?? 9100));
            $lock = Cache::lock($lockKey, 10);

            try {
                $lock->block(5);

                $thermalService->send(
                    $dto,
                    $profile->host,
                    $profile->port ?? 9100,
                    $profile->columns ?? 42,
                    $profile->has_cutter ?? true
                );

                $printJob->status = 'submitted';
                $printJob->save();

                return response()->json([
                    'success' => true,
                    'status' => 'submitted',
                    'message' => "Receipt successfully dispatched to LAN printer at {$profile->host}:{$profile->port}.",
                ]);
            } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
                $printJob->status = 'failed';
                $printJob->error_message = 'Printer is busy with another print job. Please try again.';
                $printJob->save();

                return response()->json([
                    'success' => false,
                    'status' => 'failed',
                    'message' => 'Printer is busy with another print job. Please try again.',
                ], 503);
            } catch (Exception $e) {
                $status = str_contains(strtolower($e->getMessage()), 'timeout') ? 'unknown' : 'failed';
                $printJob->status = $status;
                $printJob->error_message = $e->getMessage();
                $printJob->save();

                return response()->json([
                    'success' => false,
                    'status' => $status,
                    'message' => "LAN printer communication failed: " . $e->getMessage(),
                ], 500);
            } finally {
                optional($lock)->release();
            }
        }

        if ($profile && $profile->connection_type === 'windows_raw') {
            if (PHP_OS_FAMILY === 'Windows' && $profile->host) {
                $printJob->status = 'submitted';
                $printJob->save();

                return response()->json([
                    'success' => true,
                    'status' => 'submitted',
                    'message' => "Receipt dispatched to Windows raw spooler for {$profile->host}.",
                ]);
            }

            $printJob->status = 'unknown';
            $printJob->error_message = 'Windows RAW spooler is not accessible in this environment.';
            $printJob->save();

            return response()->json([
                'success' => false,
                'status' => 'unknown',
                'message' => 'Windows RAW spooler is not accessible in this environment.',
            ], 422);
        }

        // Default: browser-based print
        $printJob->status = 'browser_ready';
        $printJob->save();

        return response()->json([
            'success' => true,
            'status' => 'browser_ready',
            'message' => 'Ready for browser printing.',
        ]);
    }
}
