import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Boxes, Search, AlertTriangle, ShieldCheck, Flame, X } from 'lucide-react';

interface BatchItem {
    id: number;
    medicine_id: number;
    medicine_name: string;
    base_unit: string;
    batch_number: string;
    expiry_date: string;
    is_expired: boolean;
    days_to_expiry: number;
    status: string;
    remaining_base_qty: number;
    unit_cost_paisa: number;
}

interface Props {
    batches: {
        data: BatchItem[];
        links: any[];
        total: number;
    };
    filters: { search?: string; status?: string };
}

function pkr(paisa: number): string {
    return 'PKR ' + (paisa / 100).toFixed(2);
}

export default function InventoryIndex({ batches, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [writeOffModal, setWriteOffModal] = useState<BatchItem | null>(null);

    const { data, setData, post, processing, reset } = useForm({
        stock_lot_id: 1,
        quantity: 1,
        type: 'expired',
        reason: '',
    });

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('inventory.index'), { search, status: statusFilter }, { preserveState: true });
    };

    const submitWriteOff = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('inventory.write-off'), {
            onSuccess: () => {
                setWriteOffModal(null);
                reset();
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <Boxes className="h-6 w-6 text-emerald-400" />
                        Batch Inventory & FEFO Expiry Management
                    </h1>
                    <p className="text-sm text-slate-400">First-Expiry First-Out tracking, quarantine statuses, and damage write-offs</p>
                </div>
            }
        >
            <Head title="Batches & Inventory" />

            {/* Filter Bar */}
            <div className="mb-6 rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
                <form onSubmit={handleSearch} className="flex flex-col sm:flex-row gap-3">
                    <div className="flex-1 relative">
                        <Search className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by batch number or medicine name..."
                            className="w-full rounded-xl border border-slate-700 bg-slate-950 pl-10 pr-4 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emerald-500"
                        />
                    </div>
                    <select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        className="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                    >
                        <option value="">All Statuses</option>
                        <option value="sellable">Sellable</option>
                        <option value="quarantine">Quarantine</option>
                        <option value="recalled">Recalled</option>
                        <option value="expired">Expired</option>
                    </select>
                    <button
                        type="submit"
                        className="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-700"
                    >
                        Filter
                    </button>
                </form>
            </div>

            {/* Batches Table */}
            <div className="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-slate-900 border-b border-slate-800 text-slate-400 font-semibold uppercase tracking-wider">
                            <tr>
                                <th className="py-3 px-4">Medicine Name</th>
                                <th className="py-3 px-4">Batch Number</th>
                                <th className="py-3 px-4">Expiry Date</th>
                                <th className="py-3 px-4">Days Remaining</th>
                                <th className="py-3 px-4">Status</th>
                                <th className="py-3 px-4 text-right">Available Units</th>
                                <th className="py-3 px-4 text-right">Unit Cost</th>
                                <th className="py-3 px-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800/60">
                            {batches.data.map((b) => (
                                <tr key={b.id} className="hover:bg-slate-800/30">
                                    <td className="py-3 px-4 font-bold text-sm text-slate-100">{b.medicine_name}</td>
                                    <td className="py-3 px-4 font-mono font-medium text-slate-300">{b.batch_number}</td>
                                    <td className="py-3 px-4 text-slate-200">{b.expiry_date}</td>
                                    <td className="py-3 px-4">
                                        <span className={`inline-block px-2 py-0.5 rounded text-[11px] font-semibold ${
                                            b.is_expired
                                                ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30'
                                                : b.days_to_expiry <= 90
                                                ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30'
                                                : 'bg-emerald-500/10 text-emerald-400'
                                        }`}>
                                            {b.is_expired ? 'Expired' : `${b.days_to_expiry} days left`}
                                        </span>
                                    </td>
                                    <td className="py-3 px-4">
                                        <span className="capitalize text-slate-300 font-medium">{b.status}</span>
                                    </td>
                                    <td className="py-3 px-4 text-right font-bold text-sm text-slate-100">
                                        {b.remaining_base_qty} <span className="text-xs text-slate-500">{b.base_unit}</span>
                                    </td>
                                    <td className="py-3 px-4 text-right text-slate-300 font-medium">{pkr(b.unit_cost_paisa)}</td>
                                    <td className="py-3 px-4 text-center">
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setWriteOffModal(b);
                                                setData({
                                                    stock_lot_id: b.id, // approximate lot match
                                                    quantity: 1,
                                                    type: b.is_expired ? 'expired' : 'damaged',
                                                    reason: b.is_expired ? 'Expired medicine write-off' : 'Damaged / loss',
                                                });
                                            }}
                                            className="inline-flex items-center gap-1 rounded-lg bg-rose-500/10 border border-rose-500/20 px-2.5 py-1 text-xs font-semibold text-rose-400 hover:bg-rose-500/20 transition"
                                        >
                                            <Flame className="h-3 w-3" />
                                            <span>Write-off</span>
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                <div className="p-4 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
                    <div>Showing {batches.data.length} of {batches.total} batches</div>
                    <div className="flex gap-1">
                        {batches.links.map((link, idx) => (
                            <Link
                                key={idx}
                                href={link.url || '#'}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                                className={`px-2.5 py-1 rounded ${
                                    link.active
                                        ? 'bg-emerald-600 text-white font-bold'
                                        : link.url
                                        ? 'text-slate-400 hover:bg-slate-800'
                                        : 'text-slate-600 cursor-not-allowed'
                                }`}
                            />
                        ))}
                    </div>
                </div>
            </div>

            {/* Write-Off Modal */}
            {writeOffModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
                    <div className="w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-800">
                            <div>
                                <h3 className="text-base font-bold text-white flex items-center gap-2">
                                    <Flame className="h-5 w-5 text-rose-400" />
                                    Write Off Stock Loss
                                </h3>
                                <p className="text-xs text-slate-400">{writeOffModal.medicine_name} • Batch {writeOffModal.batch_number}</p>
                            </div>
                            <button onClick={() => setWriteOffModal(null)} className="rounded p-1 text-slate-400 hover:text-white">
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={submitWriteOff} className="space-y-4 my-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">
                                    Units to Write Off ({writeOffModal.base_unit}) * (Available: {writeOffModal.remaining_base_qty})
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    max={writeOffModal.remaining_base_qty}
                                    value={data.quantity}
                                    onChange={(e) => setData('quantity', Number(e.target.value))}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Loss Reason Category</label>
                                <select
                                    value={data.type}
                                    onChange={(e) => setData('type', e.target.value)}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                >
                                    <option value="expired">Expired Medicine</option>
                                    <option value="damaged">Damaged / Broken Packaging</option>
                                    <option value="lost">Lost / Discrepancy</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Reason / Disposal Notes *</label>
                                <textarea
                                    value={data.reason}
                                    onChange={(e) => setData('reason', e.target.value)}
                                    placeholder="Explain condition and disposal method..."
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                    rows={3}
                                    required
                                />
                            </div>

                            <div className="flex gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setWriteOffModal(null)}
                                    className="flex-1 rounded-xl border border-slate-700 py-2.5 text-xs font-semibold text-slate-300 hover:bg-slate-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex-1 rounded-xl bg-rose-600 py-2.5 text-xs font-bold text-white hover:bg-rose-500 shadow"
                                >
                                    Confirm Write-Off
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
