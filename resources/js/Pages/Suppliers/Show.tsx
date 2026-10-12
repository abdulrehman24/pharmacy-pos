import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Building2, ArrowLeft, DollarSign, Truck, X } from 'lucide-react';

interface Props {
    supplier: any;
    purchases: any[];
    payments: any[];
}

function pkr(paisa: number): string {
    return 'PKR ' + (paisa / 100).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function SuppliersShow({ supplier, purchases, payments }: Props) {
    const [payModal, setPayModal] = useState(false);

    const { data, setData, post, processing, reset } = useForm({
        amount_paisa: supplier.balance_paisa > 0 ? supplier.balance_paisa : 500000,
        payment_method: 'bank_transfer',
        reference: '',
        notes: 'Distributor payment',
    });

    const submitPayment = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('suppliers.payment', supplier.id), {
            onSuccess: () => {
                setPayModal(false);
                reset();
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between w-full">
                    <div className="flex items-center gap-4">
                        <Link
                            href={route('suppliers.index')}
                            className="rounded-xl bg-slate-800 p-2 text-slate-400 hover:text-white"
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                                <Building2 className="h-6 w-6 text-emerald-400" />
                                {supplier.name}
                            </h1>
                            <p className="text-sm text-slate-400">
                                Code: {supplier.code} • Contact: {supplier.contact_person || 'N/A'} • Phone: {supplier.phone || 'N/A'}
                            </p>
                        </div>
                    </div>
                    <button
                        onClick={() => setPayModal(true)}
                        className="flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-emerald-600/30 hover:bg-emerald-500 transition"
                    >
                        <DollarSign className="h-4 w-4" />
                        <span>Pay Supplier</span>
                    </button>
                </div>
            }
        >
            <Head title={`Supplier - ${supplier.name}`} />

            <div className="space-y-6">
                {/* Account Balances Card */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
                        <div className="text-xs text-slate-400">NTN / Tax Number</div>
                        <div className="text-base font-bold text-slate-100 font-mono mt-1">{supplier.tax_number || 'N/A'}</div>
                    </div>
                    <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
                        <div className="text-xs text-slate-400">Outstanding Payable to Supplier</div>
                        <div className={`text-2xl font-black mt-1 ${supplier.balance_paisa > 0 ? 'text-rose-400' : 'text-emerald-400'}`}>
                            {pkr(supplier.balance_paisa)}
                        </div>
                    </div>
                    <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
                        <div className="text-xs text-slate-400">Office / Warehouse Address</div>
                        <div className="text-sm font-medium text-slate-300 mt-1">{supplier.address || 'Not specified'}</div>
                    </div>
                </div>

                {/* Ledger Columns */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Purchases Invoices */}
                    <div className="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden shadow-sm">
                        <div className="p-4 border-b border-slate-800">
                            <h2 className="font-semibold text-white flex items-center gap-2">
                                <Truck className="h-4 w-4 text-emerald-400" />
                                <span>Recent Purchase Invoices</span>
                            </h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="bg-slate-900 text-slate-400 uppercase">
                                    <tr>
                                        <th className="py-2.5 px-3">Pur #</th>
                                        <th className="py-2.5 px-3">Date</th>
                                        <th className="py-2.5 px-3 text-right">Invoice Total</th>
                                        <th className="py-2.5 px-3 text-right">Paid</th>
                                        <th className="py-2.5 px-3 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-800">
                                    {purchases.map((p) => (
                                        <tr key={p.id}>
                                            <td className="py-2.5 px-3 font-mono text-slate-200">{p.invoice_number}</td>
                                            <td className="py-2.5 px-3 text-slate-400">{p.invoice_date}</td>
                                            <td className="py-2.5 px-3 text-right font-bold text-slate-100">{pkr(p.total_paisa)}</td>
                                            <td className="py-2.5 px-3 text-right text-emerald-400 font-medium">{pkr(p.paid_paisa)}</td>
                                            <td className="py-2.5 px-3 text-center">
                                                <Link href={route('purchases.show', p.id)} className="text-emerald-400 hover:underline">
                                                    View
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Payments Made */}
                    <div className="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden shadow-sm">
                        <div className="p-4 border-b border-slate-800">
                            <h2 className="font-semibold text-white flex items-center gap-2">
                                <DollarSign className="h-4 w-4 text-teal-400" />
                                <span>Payments Disbursed to Supplier</span>
                            </h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="bg-slate-900 text-slate-400 uppercase">
                                    <tr>
                                        <th className="py-2.5 px-3">Date</th>
                                        <th className="py-2.5 px-3">Method</th>
                                        <th className="py-2.5 px-3 text-right">Amount Paid</th>
                                        <th className="py-2.5 px-3">Reference / Notes</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-800">
                                    {payments.length === 0 ? (
                                        <tr><td colSpan={4} className="py-6 text-center text-slate-500">No payment records yet.</td></tr>
                                    ) : (
                                        payments.map((p) => (
                                            <tr key={p.id}>
                                                <td className="py-2.5 px-3 text-slate-400 font-mono">
                                                    {new Date(p.created_at).toLocaleDateString()}
                                                </td>
                                                <td className="py-2.5 px-3 capitalize text-slate-300">{p.payment_method}</td>
                                                <td className="py-2.5 px-3 text-right font-bold text-emerald-400">{pkr(p.amount_paisa)}</td>
                                                <td className="py-2.5 px-3 text-slate-400">{p.notes || p.reference || '—'}</td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {/* Payment Modal */}
            {payModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
                    <div className="w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-800">
                            <h3 className="text-base font-bold text-white flex items-center gap-2">
                                <DollarSign className="h-5 w-5 text-emerald-400" />
                                Disburse Supplier Payment
                            </h3>
                            <button onClick={() => setPayModal(false)} className="rounded p-1 text-slate-400 hover:text-white">
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={submitPayment} className="space-y-4 my-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Payment Amount (PKR) *</label>
                                <input
                                    type="number"
                                    min="1"
                                    step="1"
                                    value={data.amount_paisa / 100}
                                    onChange={(e) => setData('amount_paisa', Math.round(Number(e.target.value) * 100))}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 font-bold"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Disbursement Method</label>
                                <select
                                    value={data.payment_method}
                                    onChange={(e) => setData('payment_method', e.target.value)}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                >
                                    <option value="bank_transfer">Bank Transfer (Online/Cheque)</option>
                                    <option value="cash">Cash from Register</option>
                                    <option value="cheque">Bearer Cheque</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Cheque / Transaction Ref</label>
                                <input
                                    type="text"
                                    value={data.reference}
                                    onChange={(e) => setData('reference', e.target.value)}
                                    placeholder="e.g. Chq #098124 or IBFT-771"
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                />
                            </div>

                            <div className="flex gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setPayModal(false)}
                                    className="flex-1 rounded-xl border border-slate-700 py-2.5 text-xs font-semibold text-slate-300 hover:bg-slate-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex-1 rounded-xl bg-emerald-600 py-2.5 text-xs font-bold text-white hover:bg-emerald-500 shadow"
                                >
                                    Confirm Payment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
