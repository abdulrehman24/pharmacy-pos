import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import {
    TrendingUp,
    AlertTriangle,
    Clock,
    DollarSign,
    CreditCard,
    ArrowDownRight,
    ArrowUpRight,
    ShieldAlert,
    Pill,
    ShoppingCart,
    Wallet,
    Flame,
    Boxes,
    FileSpreadsheet,
    Users,
    Building2,
    BarChart3,
    Sparkles,
    ArrowRight,
    MapPin,
    Activity
} from 'lucide-react';

interface Props {
    stats: {
        gross_sales_paisa: number;
        discount_paisa: number;
        tax_paisa: number;
        returns_paisa: number;
        net_sales_paisa: number;
        gross_margin_paisa: number;
        cash_collections_paisa: number;
        card_collections_paisa: number;
        digital_collections_paisa: number;
        credit_sales_paisa: number;
        customer_receivables_paisa: number;
        supplier_payables_paisa: number;
        invoices_count: number;
    };
    lowStock: Array<{
        id: number;
        code: string;
        brand_name: string;
        generic_name?: string;
        strength?: string;
        base_unit: string;
        reorder_level: number;
        current_stock: number;
        rack_shelf?: string;
    }>;
    expiringBatches: Array<{
        id: number;
        medicine_name: string;
        batch_number: string;
        expiry_date: string;
        is_expired: boolean;
        remaining_base_qty: number;
        base_unit: string;
    }>;
    fastMovers: Array<{
        medicine_name: string;
        total_units: number;
        total_revenue_paisa: number;
    }>;
    currentShift: any;
}

function pkr(paisa: number): string {
    return 'PKR ' + (paisa / 100).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function Dashboard({ stats, lowStock, expiringBatches, fastMovers, currentShift }: Props) {
    const totalCollectionsPaisa =
        stats.cash_collections_paisa +
        stats.card_collections_paisa +
        stats.digital_collections_paisa +
        stats.credit_sales_paisa;

    const cashPct = totalCollectionsPaisa > 0 ? Math.round((stats.cash_collections_paisa / totalCollectionsPaisa) * 100) : 0;
    const cardPct = totalCollectionsPaisa > 0 ? Math.round((stats.card_collections_paisa / totalCollectionsPaisa) * 100) : 0;
    const digitalPct = totalCollectionsPaisa > 0 ? Math.round((stats.digital_collections_paisa / totalCollectionsPaisa) * 100) : 0;
    const creditPct = totalCollectionsPaisa > 0 ? Math.round((stats.credit_sales_paisa / totalCollectionsPaisa) * 100) : 0;

    const marginPct = stats.net_sales_paisa > 0
        ? ((stats.gross_margin_paisa / stats.net_sales_paisa) * 100).toFixed(1)
        : '0.0';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 w-full">
                    <div>
                        <h1 className="text-2xl font-black tracking-tight text-white flex items-center gap-2">
                            <span>Operations Command Center</span>
                            <span className="text-xs px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-medium">
                                Live POS Reconciled
                            </span>
                        </h1>
                        <p className="text-xs text-slate-400 mt-0.5">
                            Real-time sales, collections, stock audits, cashier shifts, and FEFO expiry tracking
                        </p>
                    </div>
                    <div className="flex items-center gap-2.5">
                        <Link
                            href={route('pos.index')}
                            className="flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-2.5 text-xs font-bold text-white shadow-lg shadow-emerald-600/25 hover:from-emerald-500 hover:to-teal-500 transition active:scale-95"
                        >
                            <ShoppingCart className="h-4 w-4" />
                            <span>Launch POS Counter (F1)</span>
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Pharmacy Operations Dashboard" />

            {/* Shift Status Hero Banner */}
            {!currentShift ? (
                <div className="mb-6 rounded-2xl border border-amber-500/40 bg-amber-500/10 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-lg backdrop-blur-sm">
                    <div className="flex items-center gap-3.5">
                        <div className="rounded-xl bg-amber-500/20 p-2.5 text-amber-400 border border-amber-500/30">
                            <AlertTriangle className="h-6 w-6" />
                        </div>
                        <div>
                            <div className="font-bold text-base text-amber-200">No Active Cashier Shift Open</div>
                            <div className="text-xs text-amber-300/80 mt-0.5">
                                Shift float must be initialized with opening cash before billing walk-in customers.
                            </div>
                        </div>
                    </div>
                    <Link
                        href={route('shifts.index')}
                        className="rounded-xl bg-amber-500 px-4 py-2 text-xs font-bold text-slate-950 hover:bg-amber-400 shadow transition self-start sm:self-auto"
                    >
                        Start Shift Float Now
                    </Link>
                </div>
            ) : (
                <div className="mb-6 rounded-2xl border border-emerald-500/40 bg-gradient-to-r from-slate-900/90 to-emerald-950/40 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-lg backdrop-blur-sm glow-emerald">
                    <div className="flex items-center gap-3.5">
                        <div className="rounded-xl bg-emerald-500/20 p-2.5 text-emerald-400 border border-emerald-500/30">
                            <Clock className="h-6 w-6 animate-pulse" />
                        </div>
                        <div>
                            <div className="font-bold text-base text-emerald-200 flex items-center gap-2">
                                <span>Active Shift: {currentShift.counter?.name ?? 'Counter 1'}</span>
                                <span className="text-[11px] px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-medium">
                                    Cashier: {currentShift.user?.name ?? 'Cashier'}
                                </span>
                            </div>
                            <div className="text-xs text-slate-400 mt-1 flex items-center gap-3">
                                <span>Opening Float: <strong className="text-slate-200">{pkr(currentShift.opening_float_paisa)}</strong></span>
                                <span>•</span>
                                <span>Opened at {new Date(currentShift.opened_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link
                            href={route('shifts.index')}
                            className="rounded-xl border border-slate-700 bg-slate-800/80 px-3.5 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-700 hover:text-white transition"
                        >
                            Shift Float Details
                        </Link>
                        <Link
                            href={route('pos.index')}
                            className="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-500 shadow-lg shadow-emerald-600/30 transition flex items-center gap-1.5"
                        >
                            <span>Open POS Register</span>
                            <ArrowRight className="h-3.5 w-3.5" />
                        </Link>
                    </div>
                </div>
            )}

            {/* Top 4 KPI Metrics Grid */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                {/* Net Sales */}
                <div className="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 shadow-lg relative overflow-hidden group hover:border-slate-700 transition">
                    <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 to-teal-400" />
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Today's Net Sales</span>
                        <div className="rounded-xl bg-emerald-500/10 p-2 text-emerald-400 border border-emerald-500/20">
                            <TrendingUp className="h-4 w-4" />
                        </div>
                    </div>
                    <div className="mt-3 text-2xl sm:text-3xl font-black text-white tracking-tight">{pkr(stats.net_sales_paisa)}</div>
                    <div className="mt-2 flex items-center text-xs text-slate-400 gap-2">
                        <span className="font-medium text-slate-300">{stats.invoices_count} invoices</span>
                        <span>•</span>
                        <span>Gross: {pkr(stats.gross_sales_paisa)}</span>
                    </div>
                </div>

                {/* Estimated Gross Margin */}
                <div className="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 shadow-lg relative overflow-hidden group hover:border-slate-700 transition">
                    <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-teal-500 to-cyan-400" />
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Est. Gross Margin</span>
                        <div className="rounded-xl bg-teal-500/10 p-2 text-teal-400 border border-teal-500/20">
                            <DollarSign className="h-4 w-4" />
                        </div>
                    </div>
                    <div className="mt-3 text-2xl sm:text-3xl font-black text-teal-300 tracking-tight">{pkr(stats.gross_margin_paisa)}</div>
                    <div className="mt-2 flex items-center justify-between text-xs text-slate-400">
                        <span>Net Sales less lot COGS</span>
                        <span className="px-1.5 py-0.5 rounded bg-teal-500/10 text-teal-300 border border-teal-500/20 font-bold font-mono">
                            {marginPct}% Margin
                        </span>
                    </div>
                </div>

                {/* Customer Khata Balance */}
                <div className="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 shadow-lg relative overflow-hidden group hover:border-slate-700 transition">
                    <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-indigo-500 to-purple-400" />
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Customer Receivables</span>
                        <div className="rounded-xl bg-indigo-500/10 p-2 text-indigo-400 border border-indigo-500/20">
                            <ArrowDownRight className="h-4 w-4" />
                        </div>
                    </div>
                    <div className="mt-3 text-2xl sm:text-3xl font-black text-indigo-300 tracking-tight">{pkr(stats.customer_receivables_paisa)}</div>
                    <div className="mt-2 flex items-center justify-between text-xs text-slate-400">
                        <span>Customer Khata dues</span>
                        <Link href={route('customers.index')} className="text-indigo-400 hover:underline font-semibold">
                            Ledgers →
                        </Link>
                    </div>
                </div>

                {/* Supplier Payable Balance */}
                <div className="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 shadow-lg relative overflow-hidden group hover:border-slate-700 transition">
                    <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-rose-500 to-amber-500" />
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Supplier Payables</span>
                        <div className="rounded-xl bg-rose-500/10 p-2 text-rose-400 border border-rose-500/20">
                            <ArrowUpRight className="h-4 w-4" />
                        </div>
                    </div>
                    <div className="mt-3 text-2xl sm:text-3xl font-black text-rose-300 tracking-tight">{pkr(stats.supplier_payables_paisa)}</div>
                    <div className="mt-2 flex items-center justify-between text-xs text-slate-400">
                        <span>Distributor invoices</span>
                        <Link href={route('suppliers.index')} className="text-rose-400 hover:underline font-semibold">
                            Suppliers →
                        </Link>
                    </div>
                </div>
            </div>

            {/* Collections Split with Visual Proportional Progress Bar */}
            <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-5 mb-6 shadow-lg">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                    <div>
                        <h2 className="text-sm font-bold uppercase tracking-wider text-slate-300 flex items-center gap-2">
                            <span>Today's Collections Split</span>
                            <span className="text-xs text-slate-500 font-normal">({pkr(totalCollectionsPaisa)} total)</span>
                        </h2>
                    </div>
                </div>

                {/* Proportional Multi-Segment Bar */}
                <div className="w-full bg-slate-950 rounded-full h-3 flex overflow-hidden p-0.5 border border-slate-800 mb-4">
                    {cashPct > 0 && (
                        <div
                            className="bg-emerald-500 h-full rounded-l-full transition-all"
                            style={{ width: `${cashPct}%` }}
                            title={`Cash: ${cashPct}%`}
                        />
                    )}
                    {cardPct > 0 && (
                        <div
                            className="bg-blue-500 h-full transition-all"
                            style={{ width: `${cardPct}%` }}
                            title={`Card: ${cardPct}%`}
                        />
                    )}
                    {digitalPct > 0 && (
                        <div
                            className="bg-amber-500 h-full transition-all"
                            style={{ width: `${digitalPct}%` }}
                            title={`JazzCash / Easypaisa: ${digitalPct}%`}
                        />
                    )}
                    {creditPct > 0 && (
                        <div
                            className="bg-indigo-500 h-full rounded-r-full transition-all"
                            style={{ width: `${creditPct}%` }}
                            title={`Khata Credit: ${creditPct}%`}
                        />
                    )}
                </div>

                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div className="rounded-xl border border-slate-800 bg-slate-950/80 p-3.5">
                        <div className="text-xs text-slate-400 flex items-center justify-between">
                            <span className="flex items-center gap-1.5"><Wallet className="h-3.5 w-3.5 text-emerald-400" /> Cash Drawer</span>
                            <span className="font-bold text-emerald-400 font-mono text-[11px]">{cashPct}%</span>
                        </div>
                        <div className="text-base font-bold text-slate-100 mt-1.5">{pkr(stats.cash_collections_paisa)}</div>
                    </div>

                    <div className="rounded-xl border border-slate-800 bg-slate-950/80 p-3.5">
                        <div className="text-xs text-slate-400 flex items-center justify-between">
                            <span className="flex items-center gap-1.5"><CreditCard className="h-3.5 w-3.5 text-blue-400" /> Card Terminal</span>
                            <span className="font-bold text-blue-400 font-mono text-[11px]">{cardPct}%</span>
                        </div>
                        <div className="text-base font-bold text-slate-100 mt-1.5">{pkr(stats.card_collections_paisa)}</div>
                    </div>

                    <div className="rounded-xl border border-slate-800 bg-slate-950/80 p-3.5">
                        <div className="text-xs text-slate-400 flex items-center justify-between">
                            <span className="flex items-center gap-1.5"><DollarSign className="h-3.5 w-3.5 text-amber-400" /> JazzCash / Easypaisa</span>
                            <span className="font-bold text-amber-400 font-mono text-[11px]">{digitalPct}%</span>
                        </div>
                        <div className="text-base font-bold text-slate-100 mt-1.5">{pkr(stats.digital_collections_paisa)}</div>
                    </div>

                    <div className="rounded-xl border border-slate-800 bg-slate-950/80 p-3.5">
                        <div className="text-xs text-slate-400 flex items-center justify-between">
                            <span className="flex items-center gap-1.5"><ArrowDownRight className="h-3.5 w-3.5 text-indigo-400" /> Khata / Credit</span>
                            <span className="font-bold text-indigo-400 font-mono text-[11px]">{creditPct}%</span>
                        </div>
                        <div className="text-base font-bold text-slate-100 mt-1.5">{pkr(stats.credit_sales_paisa)}</div>
                    </div>
                </div>
            </div>

            {/* Quick Action Navigation Grid */}
            <div className="mb-6">
                <h2 className="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Quick Operations Launchpad</h2>
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    {[
                        { title: 'POS Billing', desc: 'Scan & Bill (F1)', href: route('pos.index'), icon: ShoppingCart, color: 'text-emerald-400 bg-emerald-500/10' },
                        { title: 'Medicines', desc: '+ Add New Item', href: route('medicines.create'), icon: Pill, color: 'text-teal-400 bg-teal-500/10' },
                        { title: 'Receive PO', desc: 'Distributor Intake', href: route('purchases.create'), icon: Boxes, color: 'text-cyan-400 bg-cyan-500/10' },
                        { title: 'Customer Khata', desc: 'Credit Ledgers', href: route('customers.index'), icon: Users, color: 'text-indigo-400 bg-indigo-500/10' },
                        { title: 'Stock Audits', desc: 'Physical Counts', href: route('inventory.counts'), icon: Activity, color: 'text-amber-400 bg-amber-500/10' },
                        { title: 'Daily Reports', desc: 'Margins & Sales', href: route('reports.index'), icon: BarChart3, color: 'text-purple-400 bg-purple-500/10' },
                    ].map((item, idx) => (
                        <Link
                            key={idx}
                            href={item.href}
                            className="rounded-xl border border-slate-800 bg-slate-900/60 p-3 hover:bg-slate-800/80 hover:border-slate-700 transition group flex flex-col justify-between"
                        >
                            <div className={`w-8 h-8 rounded-lg ${item.color} flex items-center justify-center mb-2`}>
                                <item.icon className="h-4 w-4" />
                            </div>
                            <div>
                                <div className="text-xs font-bold text-slate-200 group-hover:text-white transition-colors">{item.title}</div>
                                <div className="text-[11px] text-slate-400">{item.desc}</div>
                            </div>
                        </Link>
                    ))}
                </div>
            </div>

            {/* Inventory Alerts Grid: Low Stock, FEFO Batches & Fast Movers */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Low Stock Alerts */}
                <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-5 shadow-lg flex flex-col">
                    <div className="flex items-center justify-between mb-4">
                        <div className="flex items-center gap-2">
                            <ShieldAlert className="h-5 w-5 text-amber-400" />
                            <h2 className="font-bold text-sm text-white">Low Stock Reorders</h2>
                        </div>
                        <Link href={route('medicines.index')} className="text-xs text-emerald-400 hover:underline">
                            View All
                        </Link>
                    </div>

                    {lowStock.length === 0 ? (
                        <div className="py-10 text-center text-xs text-slate-500 flex-1 flex items-center justify-center">
                            All medicines have healthy stock levels.
                        </div>
                    ) : (
                        <div className="overflow-x-auto flex-1">
                            <table className="w-full text-left text-xs">
                                <thead>
                                    <tr className="border-b border-slate-800 text-slate-400 font-semibold">
                                        <th className="pb-2">Medicine</th>
                                        <th className="pb-2">Shelf</th>
                                        <th className="pb-2 text-right">Available</th>
                                        <th className="pb-2 text-right">Order</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-800/60">
                                    {lowStock.slice(0, 7).map((item) => (
                                        <tr key={item.id} className="hover:bg-slate-800/30">
                                            <td className="py-2.5">
                                                <div className="font-semibold text-slate-200">{item.brand_name} {item.strength}</div>
                                                <div className="text-[11px] text-slate-500 truncate max-w-[120px]">{item.generic_name}</div>
                                            </td>
                                            <td className="py-2.5 text-slate-400 font-mono text-[11px]">{item.rack_shelf ?? '—'}</td>
                                            <td className="py-2.5 text-right font-bold text-rose-400">
                                                {item.current_stock} <span className="text-[10px] text-slate-500 font-normal">{item.base_unit}</span>
                                            </td>
                                            <td className="py-2.5 text-right">
                                                <Link
                                                    href={route('purchases.create')}
                                                    className="inline-block rounded px-2 py-0.5 bg-slate-800 text-emerald-400 hover:bg-emerald-600 hover:text-white transition text-[11px] font-semibold"
                                                >
                                                    + PO
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Expiring Medicines Batches (FEFO) */}
                <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-5 shadow-lg flex flex-col">
                    <div className="flex items-center justify-between mb-4">
                        <div className="flex items-center gap-2">
                            <AlertTriangle className="h-5 w-5 text-rose-400" />
                            <h2 className="font-bold text-sm text-white">FEFO Expiry Tracking</h2>
                        </div>
                        <Link href={route('inventory.index')} className="text-xs text-emerald-400 hover:underline">
                            Batches View
                        </Link>
                    </div>

                    {expiringBatches.length === 0 ? (
                        <div className="py-10 text-center text-xs text-slate-500 flex-1 flex items-center justify-center">
                            No batches expiring within 90 days.
                        </div>
                    ) : (
                        <div className="overflow-x-auto flex-1">
                            <table className="w-full text-left text-xs">
                                <thead>
                                    <tr className="border-b border-slate-800 text-slate-400 font-semibold">
                                        <th className="pb-2">Medicine / Batch</th>
                                        <th className="pb-2">Expiry</th>
                                        <th className="pb-2 text-right">Stock</th>
                                        <th className="pb-2 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-800/60">
                                    {expiringBatches.slice(0, 7).map((batch) => (
                                        <tr key={batch.id} className="hover:bg-slate-800/30">
                                            <td className="py-2.5">
                                                <div className="font-semibold text-slate-200">{batch.medicine_name}</div>
                                                <div className="font-mono text-[11px] text-slate-500">{batch.batch_number}</div>
                                            </td>
                                            <td className="py-2.5">
                                                <span className={`inline-block px-2 py-0.5 rounded text-[10px] font-bold ${
                                                    batch.is_expired
                                                        ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30'
                                                        : 'bg-amber-500/20 text-amber-300 border border-amber-500/30'
                                                }`}>
                                                    {batch.expiry_date}
                                                </span>
                                            </td>
                                            <td className="py-2.5 text-right font-medium text-slate-200">
                                                {batch.remaining_base_qty} {batch.base_unit}
                                            </td>
                                            <td className="py-2.5 text-right">
                                                <Link
                                                    href={route('inventory.index', { search: batch.batch_number })}
                                                    className="text-xs text-indigo-400 hover:underline"
                                                >
                                                    Manage
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Top Fast-Moving Medicines */}
                <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-5 shadow-lg flex flex-col">
                    <div className="flex items-center justify-between mb-4">
                        <div className="flex items-center gap-2">
                            <Flame className="h-5 w-5 text-amber-400" />
                            <h2 className="font-bold text-sm text-white">Top Fast-Movers (Demand)</h2>
                        </div>
                        <Link href={route('reports.index')} className="text-xs text-emerald-400 hover:underline">
                            Sales Trend
                        </Link>
                    </div>

                    {!fastMovers || fastMovers.length === 0 ? (
                        <div className="py-10 text-center text-xs text-slate-500 flex-1 flex items-center justify-center">
                            Sales activity will establish fast-moving catalogue.
                        </div>
                    ) : (
                        <div className="overflow-x-auto flex-1">
                            <table className="w-full text-left text-xs">
                                <thead>
                                    <tr className="border-b border-slate-800 text-slate-400 font-semibold">
                                        <th className="pb-2">Rank & Medicine</th>
                                        <th className="pb-2 text-right">Units Sold</th>
                                        <th className="pb-2 text-right">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-800/60">
                                    {fastMovers.slice(0, 7).map((item, idx) => (
                                        <tr key={idx} className="hover:bg-slate-800/30">
                                            <td className="py-2.5">
                                                <div className="flex items-center gap-2">
                                                    <span className={`w-5 h-5 rounded-full flex items-center justify-center font-bold text-[10px] ${
                                                        idx === 0
                                                            ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30'
                                                            : 'bg-slate-800 text-slate-400'
                                                    }`}>
                                                        {idx + 1}
                                                    </span>
                                                    <span className="font-semibold text-slate-200">{item.medicine_name}</span>
                                                </div>
                                            </td>
                                            <td className="py-2.5 text-right font-bold text-emerald-400">
                                                {item.total_units}
                                            </td>
                                            <td className="py-2.5 text-right text-slate-300 font-medium">
                                                {pkr(item.total_revenue_paisa)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
