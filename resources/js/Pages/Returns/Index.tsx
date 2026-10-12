import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import { RotateCcw, Search, CheckCircle, AlertCircle, ArrowRight } from 'lucide-react';

interface Props {
    customerReturns: {
        data: any[];
        links: any[];
        total: number;
    };
    supplierReturns: any[];
}

function pkr(paisa: number): string {
    return 'PKR ' + (paisa / 100).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function ReturnsIndex({ customerReturns, supplierReturns }: Props) {
    const [searchInvoice, setSearchInvoice] = useState('');
    const [foundInvoice, setFoundInvoice] = useState<any>(null);
    const [invoiceLines, setInvoiceLines] = useState<any[]>([]);
    const [searchLoading, setSearchLoading] = useState(false);
    const [searchError, setSearchError] = useState<string | null>(null);

    // Return Form
    const { data, setData, post, processing, reset } = useForm({
        sale_id: 0,
        refund_method: 'cash',
        reason: 'Customer return request',
        items: [] as Array<{
            sale_line_id: number;
            quantity: number;
            disposition: 'restock' | 'quarantine' | 'write_off';
        }>,
    });

    const handleSearchInvoice = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!searchInvoice.trim()) return;

        setSearchLoading(true);
        setSearchError(null);
        setFoundInvoice(null);
        setInvoiceLines([]);

        try {
            const res = await axios.get(route('returns.search-invoice'), {
                params: { invoice_number: searchInvoice.trim() },
            });
            setFoundInvoice(res.data.sale);
            setInvoiceLines(res.data.lines);
            setData({
                sale_id: res.data.sale.id,
                refund_method: 'cash',
                reason: 'Customer return request',
                items: res.data.lines.map((l: any) => ({
                    sale_line_id: l.id,
                    quantity: 0,
                    disposition: 'restock',
                })),
            });
        } catch (err: any) {
            setSearchError(err.response?.data?.error || 'Invoice not found or ineligible for return.');
        } finally {
            setSearchLoading(false);
        }
    };

    const updateItemQty = (lineId: number, qty: number) => {
        const updated = data.items.map((item) =>
            item.sale_line_id === lineId ? { ...item, quantity: qty } : item
        );
        setData('items', updated);
    };

    const updateItemDisposition = (lineId: number, disp: any) => {
        const updated = data.items.map((item) =>
            item.sale_line_id === lineId ? { ...item, disposition: disp } : item
        );
        setData('items', updated);
    };

    const submitReturn = (e: React.FormEvent) => {
        e.preventDefault();
        const activeItems = data.items.filter((i) => i.quantity > 0);
        if (activeItems.length === 0) {
            alert('Please specify at least one return quantity.');
            return;
        }

        post(route('returns.customer.store'), {
            onSuccess: () => {
                setFoundInvoice(null);
                setInvoiceLines([]);
                setSearchInvoice('');
                reset();
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <RotateCcw className="h-6 w-6 text-emerald-400" />
                        Customer & Supplier Returns
                    </h1>
                    <p className="text-sm text-slate-400">Strict invoice return limit validation, disposition controls, and refund posting</p>
                </div>
            }
        >
            <Head title="Returns & Losses" />

            {/* Invoice Lookup Form */}
            <div className="mb-6 rounded-2xl border border-slate-800 bg-slate-900/60 p-6 space-y-4">
                <h2 className="text-base font-semibold text-white">Find Original Invoice for Customer Return</h2>
                <form onSubmit={handleSearchInvoice} className="flex gap-3 max-w-xl">
                    <input
                        type="text"
                        value={searchInvoice}
                        onChange={(e) => setSearchInvoice(e.target.value)}
                        placeholder="Enter full invoice number (e.g. INV-20261011-XXXX)..."
                        className="flex-1 rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emerald-500"
                        required
                    />
                    <button
                        type="submit"
                        disabled={searchLoading}
                        className="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-bold text-white hover:bg-emerald-500 transition disabled:opacity-50"
                    >
                        {searchLoading ? 'Searching...' : 'Locate Invoice'}
                    </button>
                </form>

                {searchError && (
                    <div className="text-xs text-rose-400 flex items-center gap-1.5 mt-2">
                        <AlertCircle className="h-4 w-4" />
                        <span>{searchError}</span>
                    </div>
                )}
            </div>

            {/* Found Invoice Return Process Box */}
            {foundInvoice && (
                <div className="mb-6 rounded-2xl border border-emerald-500/30 bg-slate-900/90 p-6 space-y-4 shadow-xl">
                    <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-slate-800 pb-3">
                        <div>
                            <h3 className="text-lg font-bold text-white">Invoice #{foundInvoice.invoice_number}</h3>
                            <div className="text-xs text-slate-400">
                                Date: {foundInvoice.created_at} • Total: {pkr(foundInvoice.total_paisa)} • Customer: {foundInvoice.customer?.name || 'Walk-in'}
                            </div>
                        </div>
                    </div>

                    <form onSubmit={submitReturn} className="space-y-4">
                        <div className="overflow-x-auto rounded-xl border border-slate-800 bg-slate-950">
                            <table className="w-full text-left text-xs">
                                <thead className="bg-slate-900 text-slate-400 uppercase">
                                    <tr>
                                        <th className="py-2.5 px-3">Item Description</th>
                                        <th className="py-2.5 px-3 text-center">Sold Qty</th>
                                        <th className="py-2.5 px-3 text-center">Already Returned</th>
                                        <th className="py-2.5 px-3 text-center">Max Returnable</th>
                                        <th className="py-2.5 px-3 text-right">Unit Price</th>
                                        <th className="py-2.5 px-3">Return Qty</th>
                                        <th className="py-2.5 px-3">Stock Disposition</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-800">
                                    {invoiceLines.map((line) => {
                                        const formItem = data.items.find((i) => i.sale_line_id === line.id);
                                        return (
                                            <tr key={line.id}>
                                                <td className="py-2.5 px-3 font-medium text-slate-200">
                                                    {line.medicine_name} <span className="text-slate-500">({line.package_name})</span>
                                                </td>
                                                <td className="py-2.5 px-3 text-center text-slate-300">{line.sold_quantity}</td>
                                                <td className="py-2.5 px-3 text-center text-rose-400 font-semibold">{line.already_returned}</td>
                                                <td className="py-2.5 px-3 text-center font-bold text-emerald-400">{line.returnable_quantity}</td>
                                                <td className="py-2.5 px-3 text-right text-slate-300">{pkr(line.unit_price_paisa)}</td>
                                                <td className="py-2.5 px-3">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max={line.returnable_quantity}
                                                        value={formItem?.quantity || 0}
                                                        onChange={(e) => updateItemQty(line.id, Math.min(line.returnable_quantity, Math.max(0, Number(e.target.value))))}
                                                        className="w-20 rounded-lg bg-slate-900 border border-slate-700 px-2 py-1 text-xs text-white text-center font-bold"
                                                    />
                                                </td>
                                                <td className="py-2.5 px-3">
                                                    <select
                                                        value={formItem?.disposition || 'restock'}
                                                        onChange={(e) => updateItemDisposition(line.id, e.target.value)}
                                                        className="rounded-lg bg-slate-900 border border-slate-700 px-2 py-1 text-xs text-slate-200"
                                                    >
                                                        <option value="restock">Restock to Sellable</option>
                                                        <option value="quarantine">Quarantine Inspection</option>
                                                        <option value="write_off">Write-Off (Damaged)</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Refund Method</label>
                                <select
                                    value={data.refund_method}
                                    onChange={(e) => setData('refund_method', e.target.value)}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                >
                                    <option value="cash">Cash Refund Paid to Customer</option>
                                    <option value="credit_adjustment">Deduct from Customer Credit Khata</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Return Reason *</label>
                                <input
                                    type="text"
                                    value={data.reason}
                                    onChange={(e) => setData('reason', e.target.value)}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                    required
                                />
                            </div>
                        </div>

                        <div className="flex justify-end gap-3 pt-2">
                            <button
                                type="button"
                                onClick={() => setFoundInvoice(null)}
                                className="rounded-xl border border-slate-700 px-4 py-2 text-xs font-semibold text-slate-300"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="flex items-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2 text-xs font-bold text-white hover:bg-emerald-500 shadow"
                            >
                                <CheckCircle className="h-4 w-4" />
                                <span>Confirm Customer Return</span>
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Recent Returns History */}
            <div className="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden shadow-sm">
                <div className="p-4 border-b border-slate-800">
                    <h2 className="font-semibold text-white">Recent Customer Returns Register</h2>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-slate-900 border-b border-slate-800 text-slate-400 font-semibold uppercase">
                            <tr>
                                <th className="py-3 px-4">Return #</th>
                                <th className="py-3 px-4">Original Invoice</th>
                                <th className="py-3 px-4">Customer</th>
                                <th className="py-3 px-4">Cashier</th>
                                <th className="py-3 px-4 text-right">Refund Total</th>
                                <th className="py-3 px-4">Method</th>
                                <th className="py-3 px-4">Reason</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800/60">
                            {customerReturns.data.map((r) => (
                                <tr key={r.id}>
                                    <td className="py-3 px-4 font-mono font-bold text-slate-200">{r.return_number}</td>
                                    <td className="py-3 px-4 font-mono text-slate-300">{r.sale?.invoice_number}</td>
                                    <td className="py-3 px-4 text-slate-300">{r.customer?.name || 'Walk-in'}</td>
                                    <td className="py-3 px-4 text-slate-400">{r.cashier?.name}</td>
                                    <td className="py-3 px-4 text-right font-bold text-rose-400">{pkr(r.total_refund_paisa)}</td>
                                    <td className="py-3 px-4 capitalize text-slate-300">{r.refund_method.replace('_', ' ')}</td>
                                    <td className="py-3 px-4 text-slate-400">{r.reason}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
