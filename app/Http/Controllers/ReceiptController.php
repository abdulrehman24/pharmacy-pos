<?php

namespace App\Http\Controllers;

use App\Models\PrintJob;
use App\Models\PrinterProfile;
use App\Models\Sale;
use App\Services\ReceiptDataFactory;
use App\Services\ThermalReceiptService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    public function show(Request $request, Sale $sale, ReceiptDataFactory $factory): Response
    {
        abort_unless($sale->status === 'posted', 409, 'Sale is not in posted status.');

        $isCopy = $request->boolean('copy', false);
        $paperWidth = $request->has('width') ? (int) $request->get('width') : null;

        $receiptData = $factory->forSale($sale, $isCopy, $paperWidth);

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

        $isCopy = $request->boolean('copy', false);
        $dto = $factory->forSale($sale, $isCopy, $profile?->paper_width ?? 80);

        // Record print job
        $printJob = PrintJob::create([
            'sale_id' => $sale->id,
            'printer_profile_id' => $profile?->id,
            'job_type' => $isCopy ? 'duplicate' : 'receipt',
            'status' => 'pending',
            'attempts' => 1,
        ]);

        if ($profile && $profile->connection_type === 'network' && $profile->host) {
            try {
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
            } catch (Exception $e) {
                $printJob->status = 'failed';
                $printJob->error_message = $e->getMessage();
                $printJob->save();

                return response()->json([
                    'success' => false,
                    'status' => 'failed',
                    'message' => "LAN printer communication failed: " . $e->getMessage(),
                ], 500);
            }
        }

        // Default: browser-based print
        $printJob->status = 'submitted';
        $printJob->save();

        return response()->json([
            'success' => true,
            'status' => 'browser_ready',
            'message' => 'Ready for browser printing.',
        ]);
    }
}
