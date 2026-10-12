import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Truck, Plus, Search, Eye } from 'lucide-react';

interface Purchase {
    id: number;
    invoice_number: string;
    supplier_invoice_number?: string;
    invoice_date: string;
    subtotal_paisa: number;
    discount_paisa: number;
    tax_paisa: number;
    total_paisa: number;
    paid_paisa: number;
    payment_method: string;
    status: string;
    supplier: { name: string; code: string };
    user: { name: string };
    lines_count?: number;
    lines?: any[];
}

interface Props {
    purchases: {
        data: Purchase[];
        links: any[];
        total: number;
    };
    suppliers: Array<{ id: number; name: string }>;
    filters: { search?: string; supplier_id?: string };
}

function pkr(paisa: number): string {
    return 'PKR ' + (paisa / 100).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function PurchasesIndex({ purchases, suppliers, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [supplierId, setSupplierId] = useState(filters.supplier_id || '');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('purchases.index'), { search, supplier_id: supplierId }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 w-full">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                            <Truck className="h-6 w-6 text-emerald-400" />
                            Purchases & Stock Receiving
                        </h1>
                        <p className="text-sm text-slate-400">Supplier invoices, batch cost layers, and bonus stock intake</p>
                    </div>
                    <Link
                        href={route('purchases.create')}
                        className="flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-emerald-600/30 hover:bg-emerald-500 transition"
                    >
                        <Plus className="h-4 w-4" />
                        <span>Receive New Purchase</span>
                    </Link>
                </div>
            }
        >
            <Head title="Purchases & Receiving" />

            {/* Filter Bar */}
            <div className="mb-6 rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
                <form onSubmit={handleSearch} className="flex flex-col sm:flex-row gap-3">
                    <div className="flex-1 relative">
                        <Search className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search purchase number or supplier invoice..."
                            className="w-full rounded-xl border border-slate-700 bg-slate-950 pl-10 pr-4 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emerald-500"
                        />
                    </div>
                    <select
                        value={supplierId}
                        onChange={(e) => setSupplierId(e.target.value)}
                        className="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                    >
                        <option value="">All Suppliers</option>
                        {suppliers.map((s) => (
                            <option key={s.id} value={s.id}>{s.name}</option>
                        ))}
                    </select>
                    <button
                        type="submit"
                        className="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-700"
                    >
                        Filter
                    </button>
                </form>
            </div>

            {/* Purchases Table */}
            <div className="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-slate-900 border-b border-slate-800 text-slate-400 font-semibold uppercase tracking-wider">
                            <tr>
                                <th className="py-3 px-4">Internal Pur #</th>
                                <th className="py-3 px-4">Supplier Bill #</th>
                                <th className="py-3 px-4">Supplier</th>
                                <th className="py-3 px-4">Date</th>
                                <th className="py-3 px-4 text-right">Invoice Total</th>
                                <th className="py-3 px-4 text-right">Paid Amount</th>
                                <th className="py-3 px-4 text-center">Status</th>
                                <th className="py-3 px-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800/60">
                            {purchases.data.map((p) => (
                                <tr key={p.id} className="hover:bg-slate-800/30">
                                    <td className="py-3 px-4 font-mono font-bold text-slate-200">{p.invoice_number}</td>
                                    <td className="py-3 px-4 font-mono text-slate-400">{p.supplier_invoice_number || '—'}</td>
                                    <td className="py-3 px-4 font-medium text-slate-100">{p.supplier.name}</td>
                                    <td className="py-3 px-4 text-slate-400">{p.invoice_date}</td>
                                    <td className="py-3 px-4 text-right font-bold text-slate-100">{pkr(p.total_paisa)}</td>
                                    <td className="py-3 px-4 text-right font-medium text-emerald-400">{pkr(p.paid_paisa)}</td>
                                    <td className="py-3 px-4 text-center">
                                        <span className="rounded bg-emerald-500/10 px-2 py-0.5 text-emerald-400 font-medium capitalize">
                                            {p.status}
                                        </span>
                                    </td>
                                    <td className="py-3 px-4 text-center">
                                        <Link
                                            href={route('purchases.show', p.id)}
                                            className="inline-flex items-center gap-1 rounded-lg bg-slate-800 px-2.5 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-700 hover:text-white"
                                        >
                                            <Eye className="h-3.5 w-3.5" />
                                            <span>Details</span>
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                <div className="p-4 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
                    <div>Showing {purchases.data.length} of {purchases.total} purchases</div>
                    <div className="flex gap-1">
                        {purchases.links.map((link, idx) => (
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
