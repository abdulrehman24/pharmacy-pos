import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { BarChart3, Download, Calendar, DollarSign, TrendingUp, AlertTriangle, Boxes } from 'lucide-react';

interface Props {
    filters: { start_date: string; end_date: string };
    salesSummary: {
        gross_sales_paisa: number;
        discount_paisa: number;
        tax_paisa: number;
        returns_paisa: number;
        net_sales_paisa: number;
        cogs_paisa: number;
        gross_margin_paisa: number;
        invoices_count: number;
        payment_methods: Record<string, number>;
    };
    itemSales: Array<{
        medicine_name: string;
        total_qty: number;
        total_revenue_paisa: number;
    }>;
    inventorySummary: {
        total_units: number;
        valuation_paisa: number;
        expiry_at_risk_units: number;
        expiry_at_risk_cost_paisa: number;
    };
}

function pkr(paisa: number): string {
    return 'PKR ' + (paisa / 100).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function ReportsIndex({ filters, salesSummary, itemSales, inventorySummary }: Props) {
    const [startDate, setStartDate] = useState(filters.start_date);
    const [endDate, setEndDate] = useState(filters.end_date);

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('reports.index'), { start_date: startDate, end_date: endDate }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 w-full">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                            <BarChart3 className="h-6 w-6 text-emerald-400" />
                            Financial & Inventory Analytics Reports
                        </h1>
                        <p className="text-sm text-slate-400">Reconciled sales, true lot COGS margins, stock valuation, and audit schedules</p>
                    </div>

                    <div className="flex gap-2">
                        <a
                            href={route('reports.export-sales', { start_date: startDate, end_date: endDate })}
                            className="flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 px-3.5 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-700 transition"
                        >
                            <Download className="h-4 w-4 text-emerald-400" />
                            <span>Export Sales CSV</span>
                        </a>

                        <a
                            href={route('reports.export-stock')}
                            className="flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 px-3.5 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-700 transition"
                        >
                            <Download className="h-4 w-4 text-blue-400" />
                            <span>Export Stock CSV</span>
                        </a>
                    </div>
                </div>
            }
        >
            <Head title="Reports & Financials" />

            {/* Date Range Filter Bar */}
            <div className="mb-6 rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
                <form onSubmit={handleFilter} className="flex flex-col sm:flex-row items-end gap-3">
                    <div className="flex-1">
                        <label className="block text-xs font-semibold text-slate-400 mb-1">Start Date</label>
                        <input
                            type="date"
                            value={startDate}
                            onChange={(e) => setStartDate(e.target.value)}
                            className="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                        />
                    </div>
                    <div className="flex-1">
                        <label className="block text-xs font-semibold text-slate-400 mb-1">End Date</label>
                        <input
                            type="date"
                            value={endDate}
                            onChange={(e) => setEndDate(e.target.value)}
                            className="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                        />
                    </div>
                    <button
                        type="submit"
                        className="rounded-xl bg-emerald-600 px-6 py-2 text-sm font-bold text-white hover:bg-emerald-500 shadow"
                    >
                        Apply Date Range
                    </button>
                </form>
            </div>

            {/* Reconciled Profitability & Revenue Summary */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
                    <span className="text-xs font-semibold uppercase text-slate-400">Net Sales Revenue</span>
                    <div className="text-2xl font-bold text-white mt-2">{pkr(salesSummary.net_sales_paisa)}</div>
                    <div className="text-xs text-slate-400 mt-1">
                        Gross: {pkr(salesSummary.gross_sales_paisa)} • {salesSummary.invoices_count} bills
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
                    <span className="text-xs font-semibold uppercase text-slate-400">Cost of Goods Sold (COGS)</span>
                    <div className="text-2xl font-bold text-slate-300 mt-2">{pkr(salesSummary.cogs_paisa)}</div>
                    <div className="text-xs text-slate-400 mt-1">Based on historical lot cost layers</div>
                </div>

                <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
                    <span className="text-xs font-semibold uppercase text-slate-400">Reconciled Gross Margin</span>
                    <div className="text-2xl font-black text-emerald-400 mt-2">{pkr(salesSummary.gross_margin_paisa)}</div>
                    <div className="text-xs text-slate-400 mt-1">
                        Margin: {salesSummary.net_sales_paisa > 0 ? ((salesSummary.gross_margin_paisa / salesSummary.net_sales_paisa) * 100).toFixed(1) : 0}%
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
                    <span className="text-xs font-semibold uppercase text-slate-400">Customer Returns Refunded</span>
                    <div className="text-2xl font-bold text-rose-400 mt-2">{pkr(salesSummary.returns_paisa)}</div>
                    <div className="text-xs text-slate-400 mt-1">Subtracted from gross sales</div>
                </div>
            </div>

            {/* Inventory Valuation & Expiry Risk Cards */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="font-semibold text-white flex items-center gap-2">
                            <Boxes className="h-5 w-5 text-blue-400" />
                            <span>Stock on Hand & Cost Valuation</span>
                        </h2>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="rounded-xl border border-slate-800 bg-slate-950 p-4">
                            <div className="text-xs text-slate-400">Physical Stock Units</div>
                            <div className="text-2xl font-bold text-slate-100 mt-1">{inventorySummary.total_units} units</div>
                        </div>
                        <div className="rounded-xl border border-slate-800 bg-slate-950 p-4">
                            <div className="text-xs text-slate-400">Total Stock Cost Value</div>
                            <div className="text-2xl font-bold text-emerald-400 mt-1">{pkr(inventorySummary.valuation_paisa)}</div>
                        </div>
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="font-semibold text-white flex items-center gap-2">
                            <AlertTriangle className="h-5 w-5 text-amber-400" />
                            <span>Expiry Risk (Expiring within 90 days)</span>
                        </h2>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="rounded-xl border border-slate-800 bg-slate-950 p-4">
                            <div className="text-xs text-slate-400">Units at Risk</div>
                            <div className="text-2xl font-bold text-amber-300 mt-1">{inventorySummary.expiry_at_risk_units} units</div>
                        </div>
                        <div className="rounded-xl border border-slate-800 bg-slate-950 p-4">
                            <div className="text-xs text-slate-400">Cost Value at Risk</div>
                            <div className="text-2xl font-bold text-rose-400 mt-1">{pkr(inventorySummary.expiry_at_risk_cost_paisa)}</div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Top Revenue Medicines Table */}
            <div className="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden shadow-sm">
                <div className="p-4 border-b border-slate-800">
                    <h2 className="font-semibold text-white">Top Medicines by Sales Volume for Period</h2>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-slate-900 border-b border-slate-800 text-slate-400 font-semibold uppercase">
                            <tr>
                                <th className="py-3 px-4">Medicine Description</th>
                                <th className="py-3 px-4 text-center">Units Sold</th>
                                <th className="py-3 px-4 text-right">Revenue Generated</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800/60">
                            {itemSales.length === 0 ? (
                                <tr><td colSpan={3} className="py-6 text-center text-slate-500">No sales recorded during this range.</td></tr>
                            ) : (
                                itemSales.map((item, idx) => (
                                    <tr key={idx}>
                                        <td className="py-3 px-4 font-bold text-slate-200">{item.medicine_name}</td>
                                        <td className="py-3 px-4 text-center font-bold text-slate-300">{item.total_qty}</td>
                                        <td className="py-3 px-4 text-right font-extrabold text-emerald-400">{pkr(item.total_revenue_paisa)}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
