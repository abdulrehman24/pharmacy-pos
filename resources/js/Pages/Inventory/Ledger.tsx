import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { FileSpreadsheet, Filter } from 'lucide-react';

interface Movement {
    id: number;
    movement_type: string;
    reference_type?: string;
    quantity_base_unit: number;
    cost_per_base_unit: number;
    notes?: string;
    created_at: string;
    medicine: { brand_name: string; strength?: string; base_unit: string; code: string };
    batch?: { batch_number: string };
    user?: { name: string };
    location?: { name: string };
}

interface Props {
    movements: {
        data: Movement[];
        links: any[];
        total: number;
    };
    medicines: Array<{ id: number; brand_name: string; code: string }>;
    filters: { medicine_id?: string; movement_type?: string };
}

function pkr(rupees: number): string {
    return 'PKR ' + Number(rupees).toFixed(2);
}

export default function InventoryLedger({ movements, medicines, filters }: Props) {
    const [medId, setMedId] = useState(filters.medicine_id || '');
    const [type, setType] = useState(filters.movement_type || '');

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('inventory.ledger'), { medicine_id: medId, movement_type: type }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <FileSpreadsheet className="h-6 w-6 text-emerald-400" />
                        Stock Movement Audit Ledger
                    </h1>
                    <p className="text-sm text-slate-400">Complete immutable record of all inbound and outbound medicine transactions</p>
                </div>
            }
        >
            <Head title="Stock Movement Ledger" />

            {/* Filter Bar */}
            <div className="mb-6 rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
                <form onSubmit={handleFilter} className="flex flex-col sm:flex-row gap-3">
                    <select
                        value={medId}
                        onChange={(e) => setMedId(e.target.value)}
                        className="flex-1 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                    >
                        <option value="">All Medicines</option>
                        {medicines.map((m) => (
                            <option key={m.id} value={m.id}>{m.brand_name} ({m.code})</option>
                        ))}
                    </select>

                    <select
                        value={type}
                        onChange={(e) => setType(e.target.value)}
                        className="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                    >
                        <option value="">All Movement Types</option>
                        <option value="opening">Opening Stock</option>
                        <option value="purchase">Purchase Intake</option>
                        <option value="sale">Counter Sale</option>
                        <option value="customer_return">Customer Return</option>
                        <option value="supplier_return">Supplier Return</option>
                        <option value="adjustment">Stock Count Adjustment</option>
                        <option value="write_off">Write-Off (Loss)</option>
                    </select>

                    <button
                        type="submit"
                        className="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-700"
                    >
                        Filter Ledger
                    </button>
                </form>
            </div>

            {/* Ledger Table */}
            <div className="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-slate-900 border-b border-slate-800 text-slate-400 font-semibold uppercase tracking-wider">
                            <tr>
                                <th className="py-3 px-4">Timestamp (PKT)</th>
                                <th className="py-3 px-4">Movement Type</th>
                                <th className="py-3 px-4">Medicine</th>
                                <th className="py-3 px-4">Batch</th>
                                <th className="py-3 px-4 text-right">Quantity Delta</th>
                                <th className="py-3 px-4 text-right">Unit Cost</th>
                                <th className="py-3 px-4">Staff / User</th>
                                <th className="py-3 px-4">Audit Notes</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800/60">
                            {movements.data.map((m) => (
                                <tr key={m.id} className="hover:bg-slate-800/30">
                                    <td className="py-3 px-4 font-mono text-slate-400">
                                        {new Date(m.created_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' })}
                                    </td>
                                    <td className="py-3 px-4">
                                        <span className={`inline-block px-2 py-0.5 rounded text-[11px] font-semibold capitalize ${
                                            m.movement_type === 'sale'
                                                ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20'
                                                : m.movement_type === 'purchase'
                                                ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                                : m.movement_type === 'customer_return'
                                                ? 'bg-teal-500/10 text-teal-300 border border-teal-500/20'
                                                : m.movement_type === 'write_off'
                                                ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20'
                                                : 'bg-slate-800 text-slate-300'
                                        }`}>
                                            {m.movement_type.replace('_', ' ')}
                                        </span>
                                    </td>
                                    <td className="py-3 px-4 font-bold text-slate-200">
                                        {m.medicine.brand_name} {m.medicine.strength}
                                    </td>
                                    <td className="py-3 px-4 font-mono text-slate-400">{m.batch?.batch_number || '—'}</td>
                                    <td className={`py-3 px-4 text-right font-extrabold text-sm ${
                                        m.quantity_base_unit > 0 ? 'text-emerald-400' : 'text-rose-400'
                                    }`}>
                                        {m.quantity_base_unit > 0 ? `+${m.quantity_base_unit}` : m.quantity_base_unit}
                                        <span className="text-xs font-normal text-slate-500 ml-1">{m.medicine.base_unit}</span>
                                    </td>
                                    <td className="py-3 px-4 text-right text-slate-300">{pkr(m.cost_per_base_unit)}</td>
                                    <td className="py-3 px-4 text-slate-300">{m.user?.name || 'System'}</td>
                                    <td className="py-3 px-4 text-slate-400 text-[11px]">{m.notes || '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                <div className="p-4 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
                    <div>Showing {movements.data.length} of {movements.total} recorded movements</div>
                    <div className="flex gap-1">
                        {movements.links.map((link, idx) => (
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
        </AuthenticatedLayout>
    );
}
