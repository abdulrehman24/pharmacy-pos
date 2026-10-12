import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { Boxes, Plus, CheckCircle, Clock, AlertCircle } from 'lucide-react';

interface Props {
    counts: {
        data: any[];
        links: any[];
        total: number;
    };
    locations: any[];
    medicines: any[];
}

export default function InventoryCounts({ counts, locations, medicines }: Props) {
    const [isCreating, setIsCreating] = useState(false);

    // Form for new count
    const { data, setData, post, processing, reset } = useForm({
        location_id: locations[0]?.id || 1,
        notes: '',
        lines: [
            {
                medicine_id: medicines[0]?.id || 1,
                batch_id: medicines[0]?.batches[0]?.id || 1,
                stock_lot_id: medicines[0]?.batches[0]?.stock_lots[0]?.id || 1,
                system_quantity: medicines[0]?.batches[0]?.stock_lots[0]?.remaining_base_qty || 0,
                counted_quantity: medicines[0]?.batches[0]?.stock_lots[0]?.remaining_base_qty || 0,
                reason: 'Routine physical audit',
            },
        ],
    });

    const addLine = () => {
        const med = medicines[0];
        const batch = med?.batches[0];
        const lot = batch?.stock_lots[0];

        setData('lines', [
            ...data.lines,
            {
                medicine_id: med?.id || 1,
                batch_id: batch?.id || 1,
                stock_lot_id: lot?.id || 1,
                system_quantity: lot?.remaining_base_qty || 0,
                counted_quantity: lot?.remaining_base_qty || 0,
                reason: 'Routine physical audit',
            },
        ]);
    };

    const updateLineMed = (idx: number, medId: number) => {
        const med = medicines.find((m) => m.id === medId);
        const batch = med?.batches[0];
        const lot = batch?.stock_lots[0];

        const updated = [...data.lines];
        updated[idx] = {
            ...updated[idx],
            medicine_id: medId,
            batch_id: batch?.id || 1,
            stock_lot_id: lot?.id || 1,
            system_quantity: lot?.remaining_base_qty || 0,
            counted_quantity: lot?.remaining_base_qty || 0,
        };
        setData('lines', updated);
    };

    const submitCount = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('inventory.counts.store'), {
            onSuccess: () => {
                setIsCreating(false);
                reset();
            },
        });
    };

    const approveCount = (id: number) => {
        if (confirm('Approve this stock count and post variance adjustments to inventory?')) {
            router.post(route('inventory.counts.approve', id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 w-full">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                            <Boxes className="h-6 w-6 text-emerald-400" />
                            Physical Stock Counts & Audits
                        </h1>
                        <p className="text-sm text-slate-400">Conduct physical inventory counts and approve variance adjustments</p>
                    </div>
                    <button
                        onClick={() => setIsCreating(!isCreating)}
                        className="flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-emerald-600/30 hover:bg-emerald-500 transition"
                    >
                        <Plus className="h-4 w-4" />
                        <span>{isCreating ? 'Close Form' : 'New Stock Count'}</span>
                    </button>
                </div>
            }
        >
            <Head title="Stock Counts" />

            {/* Create Count Form */}
            {isCreating && (
                <div className="mb-6 rounded-2xl border border-slate-800 bg-slate-900/80 p-6 space-y-4">
                    <h2 className="text-base font-semibold text-white border-b border-slate-800 pb-2">
                        Record Physical Count
                    </h2>

                    <form onSubmit={submitCount} className="space-y-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Audit Location</label>
                                <select
                                    value={data.location_id}
                                    onChange={(e) => setData('location_id', Number(e.target.value))}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                >
                                    {locations.map((loc) => (
                                        <option key={loc.id} value={loc.id}>{loc.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Audit Notes</label>
                                <input
                                    type="text"
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    placeholder="e.g. Month-end shelf audit..."
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                />
                            </div>
                        </div>

                        {/* Count lines */}
                        <div className="space-y-3">
                            {data.lines.map((line, idx) => {
                                const variance = line.counted_quantity - line.system_quantity;
                                return (
                                    <div key={idx} className="rounded-xl border border-slate-800 bg-slate-950 p-3 grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
                                        <div className="sm:col-span-2">
                                            <label className="block text-[11px] font-semibold text-slate-400 mb-1">Medicine</label>
                                            <select
                                                value={line.medicine_id}
                                                onChange={(e) => updateLineMed(idx, Number(e.target.value))}
                                                className="w-full rounded-lg bg-slate-900 border border-slate-700 px-2.5 py-1.5 text-xs text-slate-100"
                                            >
                                                {medicines.map((m) => (
                                                    <option key={m.id} value={m.id}>{m.brand_name} {m.strength}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div>
                                            <label className="block text-[11px] font-semibold text-slate-400 mb-1">System Qty</label>
                                            <div className="rounded-lg bg-slate-900 border border-slate-800 px-2.5 py-1.5 text-xs text-slate-400 font-bold">
                                                {line.system_quantity}
                                            </div>
                                        </div>
                                        <div>
                                            <label className="block text-[11px] font-semibold text-slate-400 mb-1">Counted Qty</label>
                                            <input
                                                type="number"
                                                min="0"
                                                value={line.counted_quantity}
                                                onChange={(e) => {
                                                    const updated = [...data.lines];
                                                    updated[idx].counted_quantity = Number(e.target.value);
                                                    setData('lines', updated);
                                                }}
                                                className="w-full rounded-lg bg-slate-900 border border-slate-700 px-2.5 py-1.5 text-xs text-slate-100 font-bold"
                                            />
                                        </div>
                                        <div className="text-right pb-1">
                                            <span className={`text-xs font-bold ${variance === 0 ? 'text-slate-400' : variance > 0 ? 'text-emerald-400' : 'text-rose-400'}`}>
                                                Variance: {variance > 0 ? `+${variance}` : variance}
                                            </span>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        <div className="flex justify-between pt-2">
                            <button
                                type="button"
                                onClick={addLine}
                                className="text-xs text-emerald-400 hover:underline"
                            >
                                + Add another item to audit
                            </button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-xl bg-emerald-600 px-5 py-2 text-xs font-bold text-white hover:bg-emerald-500 shadow"
                            >
                                Save Draft Count
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Counts History */}
            <div className="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-slate-900 border-b border-slate-800 text-slate-400 font-semibold uppercase">
                            <tr>
                                <th className="py-3 px-4">Count Number</th>
                                <th className="py-3 px-4">Location</th>
                                <th className="py-3 px-4">Conducted By</th>
                                <th className="py-3 px-4">Status</th>
                                <th className="py-3 px-4">Approved By</th>
                                <th className="py-3 px-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800/60">
                            {counts.data.map((c) => (
                                <tr key={c.id} className="hover:bg-slate-800/30">
                                    <td className="py-3 px-4 font-mono font-bold text-slate-200">{c.count_number}</td>
                                    <td className="py-3 px-4 text-slate-300">{c.location?.name}</td>
                                    <td className="py-3 px-4 text-slate-300">{c.conductor?.name}</td>
                                    <td className="py-3 px-4">
                                        <span className={`inline-block px-2 py-0.5 rounded text-[11px] font-semibold capitalize ${
                                            c.status === 'approved'
                                                ? 'bg-emerald-500/20 text-emerald-400'
                                                : 'bg-amber-500/20 text-amber-300'
                                        }`}>
                                            {c.status}
                                        </span>
                                    </td>
                                    <td className="py-3 px-4 text-slate-400">{c.approver?.name || 'Pending'}</td>
                                    <td className="py-3 px-4 text-center">
                                        {c.status === 'draft' && (
                                            <button
                                                onClick={() => approveCount(c.id)}
                                                className="inline-flex items-center gap-1 rounded-lg bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 px-3 py-1 text-xs font-semibold hover:bg-emerald-600/30 transition"
                                            >
                                                <CheckCircle className="h-3.5 w-3.5" />
                                                <span>Approve Adjustment</span>
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
