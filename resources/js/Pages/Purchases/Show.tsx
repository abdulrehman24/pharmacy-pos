import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Truck, ArrowLeft, Building2 } from 'lucide-react';

interface Props {
    purchase: any;
}

function pkr(paisa: number): string {
    return 'PKR ' + (paisa / 100).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function PurchasesShow({ purchase }: Props) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-4">
                    <Link
                        href={route('purchases.index')}
                        className="rounded-xl bg-slate-800 p-2 text-slate-400 hover:text-white"
                    >
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                            <Truck className="h-6 w-6 text-emerald-400" />
                            Purchase Invoice {purchase.invoice_number}
                        </h1>
                        <p className="text-sm text-slate-400">
                            Supplier: {purchase.supplier?.name} • Bill Date: {purchase.invoice_date}
                        </p>
                    </div>
                </div>
            }
        >
            <Head title={`Purchase ${purchase.invoice_number}`} />

            <div className="space-y-6">
                {/* Summary Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
                        <div className="text-xs text-slate-400">Supplier Bill #</div>
                        <div className="text-base font-bold text-slate-100 font-mono mt-1">
                            {purchase.supplier_invoice_number || 'N/A'}
                        </div>
                    </div>
                    <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
                        <div className="text-xs text-slate-400">Total Invoice Amount</div>
                        <div className="text-base font-bold text-slate-100 mt-1">{pkr(purchase.total_paisa)}</div>
                    </div>
                    <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
                        <div className="text-xs text-slate-400">Paid Amount</div>
                        <div className="text-base font-bold text-emerald-400 mt-1">{pkr(purchase.paid_paisa)}</div>
                    </div>
                    <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
                        <div className="text-xs text-slate-400">Remaining Payable</div>
                        <div className="text-base font-bold text-amber-400 mt-1">
                            {pkr(Math.max(0, purchase.total_paisa - purchase.paid_paisa))}
                        </div>
                    </div>
                </div>

                {/* Lines Table */}
                <div className="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden">
                    <div className="p-4 border-b border-slate-800">
                        <h2 className="font-semibold text-white">Received Medicine Batches</h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-900 border-b border-slate-800 text-slate-400 font-semibold uppercase">
                                <tr>
                                    <th className="py-3 px-4">Medicine</th>
                                    <th className="py-3 px-4">Batch Number</th>
                                    <th className="py-3 px-4">Expiry Date</th>
                                    <th className="py-3 px-4 text-center">Packs</th>
                                    <th className="py-3 px-4 text-center">Bonus Units</th>
                                    <th className="py-3 px-4 text-center">Total Base Units</th>
                                    <th className="py-3 px-4 text-right">Pack Cost</th>
                                    <th className="py-3 px-4 text-right">Effective Unit Cost</th>
                                    <th className="py-3 px-4 text-right">Line Total</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-800/60">
                                {purchase.lines.map((l: any) => (
                                    <tr key={l.id}>
                                        <td className="py-3 px-4 font-medium text-slate-200">
                                            {l.medicine?.brand_name} {l.medicine?.strength}
                                        </td>
                                        <td className="py-3 px-4 font-mono text-slate-300">{l.batch_number}</td>
                                        <td className="py-3 px-4 text-slate-300">{l.expiry_date}</td>
                                        <td className="py-3 px-4 text-center font-bold text-slate-200">{l.package_quantity}</td>
                                        <td className="py-3 px-4 text-center text-teal-400 font-medium">{l.bonus_base_quantity}</td>
                                        <td className="py-3 px-4 text-center font-bold text-slate-100">{l.total_base_quantity}</td>
                                        <td className="py-3 px-4 text-right text-slate-300">{pkr(l.package_cost_paisa)}</td>
                                        <td className="py-3 px-4 text-right text-emerald-400 font-mono">
                                            PKR {(l.unit_cost_paisa / 100).toFixed(4)}
                                        </td>
                                        <td className="py-3 px-4 text-right font-bold text-slate-100">{pkr(l.total_cost_paisa)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
