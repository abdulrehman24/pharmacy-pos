import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { FileSignature, Plus, Search, CheckCircle, Paperclip, X } from 'lucide-react';

interface Prescription {
    id: number;
    prescription_number: string;
    patient_name: string;
    patient_phone?: string;
    doctor_name: string;
    prescription_date: string;
    notes?: string;
    status: string;
    verified_at?: string;
    customer?: { name: string };
    verifier?: { name: string };
    attachments?: Array<{ id: number; file_name: string; file_path: string }>;
}

interface Props {
    prescriptions: {
        data: Prescription[];
        links: any[];
        total: number;
    };
    customers: Array<{ id: number; name: string }>;
    filters: { search?: string; status?: string };
}

export default function PrescriptionsIndex({ prescriptions, customers, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [createModal, setCreateModal] = useState(false);
    const [verifyModal, setVerifyModal] = useState<Prescription | null>(null);

    const { data, setData, post, processing, reset } = useForm({
        patient_name: '',
        patient_phone: '',
        doctor_name: '',
        prescription_date: new Date().toISOString().split('T')[0],
        customer_id: '',
        notes: '',
        attachment: null as File | null,
    });

    const verifyForm = useForm({
        instructions: 'Dispensed as prescribed by doctor. Dosage instructions explained to patient.',
    });

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('prescriptions.index'), { search }, { preserveState: true });
    };

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('prescriptions.store'), {
            onSuccess: () => {
                setCreateModal(false);
                reset();
            },
        });
    };

    const submitVerify = (e: React.FormEvent) => {
        e.preventDefault();
        if (!verifyModal) return;
        verifyForm.post(route('prescriptions.verify', verifyModal.id), {
            onSuccess: () => {
                setVerifyModal(null);
                verifyForm.reset();
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 w-full">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                            <FileSignature className="h-6 w-6 text-emerald-400" />
                            Prescription Records & Dispensing Review
                        </h1>
                        <p className="text-sm text-slate-400">Prescription scans, clinical recordkeeping, and pharmacist verification</p>
                    </div>
                    <button
                        onClick={() => setCreateModal(true)}
                        className="flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-emerald-600/30 hover:bg-emerald-500 transition"
                    >
                        <Plus className="h-4 w-4" />
                        <span>Record Prescription</span>
                    </button>
                </div>
            }
        >
            <Head title="Prescriptions" />

            {/* Filter Bar */}
            <div className="mb-6 rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
                <form onSubmit={handleSearch} className="flex gap-3">
                    <div className="flex-1 relative">
                        <Search className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by patient name, doctor, or Rx number..."
                            className="w-full rounded-xl border border-slate-700 bg-slate-950 pl-10 pr-4 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emerald-500"
                        />
                    </div>
                    <button
                        type="submit"
                        className="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-700"
                    >
                        Search
                    </button>
                </form>
            </div>

            {/* Prescriptions Table */}
            <div className="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-slate-900 border-b border-slate-800 text-slate-400 font-semibold uppercase tracking-wider">
                            <tr>
                                <th className="py-3 px-4">Rx Number</th>
                                <th className="py-3 px-4">Patient Name</th>
                                <th className="py-3 px-4">Prescribing Doctor</th>
                                <th className="py-3 px-4">Date</th>
                                <th className="py-3 px-4 text-center">Status</th>
                                <th className="py-3 px-4">Verified By</th>
                                <th className="py-3 px-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800/60">
                            {prescriptions.data.map((rx) => (
                                <tr key={rx.id} className="hover:bg-slate-800/30">
                                    <td className="py-3 px-4 font-mono font-bold text-slate-200">{rx.prescription_number}</td>
                                    <td className="py-3 px-4">
                                        <div className="font-bold text-slate-100 text-sm">{rx.patient_name}</div>
                                        <div className="text-[11px] text-slate-500">{rx.patient_phone || 'No phone'}</div>
                                    </td>
                                    <td className="py-3 px-4 text-slate-200 font-medium">{rx.doctor_name}</td>
                                    <td className="py-3 px-4 text-slate-400">{rx.prescription_date}</td>
                                    <td className="py-3 px-4 text-center">
                                        <span className={`inline-block px-2 py-0.5 rounded text-[11px] font-semibold capitalize ${
                                            rx.status === 'dispensed'
                                                ? 'bg-emerald-500/20 text-emerald-400'
                                                : 'bg-amber-500/20 text-amber-300'
                                        }`}>
                                            {rx.status}
                                        </span>
                                    </td>
                                    <td className="py-3 px-4 text-slate-300">
                                        {rx.verifier?.name ? (
                                            <div>
                                                <div className="font-medium text-emerald-400">{rx.verifier.name}</div>
                                                <div className="text-[10px] text-slate-500">Verified</div>
                                            </div>
                                        ) : (
                                            <span className="text-slate-500">Pending Review</span>
                                        )}
                                    </td>
                                    <td className="py-3 px-4 text-center">
                                        {rx.status === 'pending' && (
                                            <button
                                                onClick={() => setVerifyModal(rx)}
                                                className="inline-flex items-center gap-1 rounded-lg bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 px-3 py-1 text-xs font-semibold hover:bg-emerald-600/30 transition"
                                            >
                                                <CheckCircle className="h-3.5 w-3.5" />
                                                <span>Pharmacist Review</span>
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Create Rx Modal */}
            {createModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
                    <div className="w-full max-w-lg rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-800">
                            <h3 className="text-base font-bold text-white flex items-center gap-2">
                                <FileSignature className="h-5 w-5 text-emerald-400" />
                                Record Patient Prescription
                            </h3>
                            <button onClick={() => setCreateModal(false)} className="rounded p-1 text-slate-400 hover:text-white">
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={submitCreate} className="space-y-4 my-4">
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-400 mb-1">Patient Name *</label>
                                    <input
                                        type="text"
                                        value={data.patient_name}
                                        onChange={(e) => setData('patient_name', e.target.value)}
                                        className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-400 mb-1">Patient Phone</label>
                                    <input
                                        type="text"
                                        value={data.patient_phone}
                                        onChange={(e) => setData('patient_phone', e.target.value)}
                                        placeholder="0300-XXXXXXX"
                                        className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-400 mb-1">Doctor Name *</label>
                                    <input
                                        type="text"
                                        value={data.doctor_name}
                                        onChange={(e) => setData('doctor_name', e.target.value)}
                                        placeholder="Dr. ..."
                                        className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-400 mb-1">Prescription Date *</label>
                                    <input
                                        type="date"
                                        value={data.prescription_date}
                                        onChange={(e) => setData('prescription_date', e.target.value)}
                                        className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                        required
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Prescription Notes / Instructions</label>
                                <textarea
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    placeholder="Doctor's notes, dosage instructions..."
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                    rows={2}
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1 flex items-center gap-1">
                                    <Paperclip className="h-3.5 w-3.5" /> Attach Prescription Scan / Photo
                                </label>
                                <input
                                    type="file"
                                    accept="image/*,application/pdf"
                                    onChange={(e) => setData('attachment', e.target.files ? e.target.files[0] : null)}
                                    className="w-full text-xs text-slate-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-slate-200 hover:file:bg-slate-700"
                                />
                            </div>

                            <div className="flex gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setCreateModal(false)}
                                    className="flex-1 rounded-xl border border-slate-700 py-2.5 text-xs font-semibold text-slate-300 hover:bg-slate-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex-1 rounded-xl bg-emerald-600 py-2.5 text-xs font-bold text-white hover:bg-emerald-500 shadow"
                                >
                                    Save Prescription Record
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Review / Verify Modal */}
            {verifyModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
                    <div className="w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-800">
                            <div>
                                <h3 className="text-base font-bold text-white">Pharmacist Clinical Verification</h3>
                                <p className="text-xs text-slate-400">Patient: {verifyModal.patient_name} • Dr: {verifyModal.doctor_name}</p>
                            </div>
                            <button onClick={() => setVerifyModal(null)} className="rounded p-1 text-slate-400 hover:text-white">
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={submitVerify} className="space-y-4 my-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">
                                    Dispensing Review & Clinical Instructions
                                </label>
                                <textarea
                                    value={verifyForm.data.instructions}
                                    onChange={(e) => verifyForm.setData('instructions', e.target.value)}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                    rows={3}
                                    required
                                />
                            </div>

                            <div className="flex gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setVerifyModal(null)}
                                    className="flex-1 rounded-xl border border-slate-700 py-2.5 text-xs font-semibold text-slate-300 hover:bg-slate-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={verifyForm.processing}
                                    className="flex-1 rounded-xl bg-emerald-600 py-2.5 text-xs font-bold text-white hover:bg-emerald-500 shadow"
                                >
                                    Confirm Dispensing
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
