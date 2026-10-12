<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DispensingRecord;
use App\Models\Prescription;
use App\Models\PrescriptionAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PrescriptionController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Prescription::with(['customer', 'verifier', 'attachments', 'dispensingRecords.sale'])
            ->latest('prescription_date');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('patient_name', 'LIKE', "%{$search}%")
                  ->orWhere('doctor_name', 'LIKE', "%{$search}%")
                  ->orWhere('prescription_number', 'LIKE', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $prescriptions = $query->paginate(15)->withQueryString();
        $customers = Customer::where('is_active', true)->select('id', 'name', 'phone')->get();

        return Inertia::render('Prescriptions/Index', [
            'prescriptions' => $prescriptions,
            'customers' => $customers,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_phone' => ['nullable', 'string', 'max:30'],
            'doctor_name' => ['required', 'string', 'max:255'],
            'prescription_date' => ['required', 'date'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        $datePrefix = now()->format('Ymd');
        $prescriptionNumber = 'RX-' . $datePrefix . '-' . strtoupper(bin2hex(random_bytes(3)));

        $rx = Prescription::create([
            'prescription_number' => $prescriptionNumber,
            'customer_id' => $validated['customer_id'] ?? null,
            'patient_name' => $validated['patient_name'],
            'patient_phone' => $validated['patient_phone'] ?? null,
            'doctor_name' => $validated['doctor_name'],
            'prescription_date' => $validated['prescription_date'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('prescriptions', 'local');

            PrescriptionAttachment::create([
                'prescription_id' => $rx->id,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'file_size' => $file->getSize(),
            ]);
        }

        return redirect()->route('prescriptions.index')
            ->with('success', "Prescription {$prescriptionNumber} recorded.");
    }

    /**
     * Pharmacist verification of prescription
     */
    public function verify(Request $request, Prescription $prescription): RedirectResponse
    {
        $user = $request->user();

        if (!$user->isPharmacist()) {
            return back()->withErrors(['auth' => 'Only a licensed pharmacist or manager can verify prescriptions.']);
        }

        $prescription->status = 'dispensed';
        $prescription->verified_by = $user->id;
        $prescription->verified_at = now();
        $prescription->save();

        if ($request->filled('instructions')) {
            DispensingRecord::create([
                'prescription_id' => $prescription->id,
                'pharmacist_id' => $user->id,
                'instructions' => $request->input('instructions'),
            ]);
        }

        return back()->with('success', "Prescription {$prescription->prescription_number} verified and approved.");
    }
}
