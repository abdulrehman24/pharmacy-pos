import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { Truck, Plus, Trash2, ArrowLeft, Save, AlertCircle } from 'lucide-react';

interface Medicine {
    id: number;
    brand_name: string;
    generic_name?: string;
    strength?: string;
    base_unit: string;
    packages: Array<{ id: number; name: string; base_quantity: number; default_purchase_price_paisa: number }>;
}

interface Props {
    suppliers: Array<{ id: number; name: string; code: string }>;
    medicines: Medicine[];
}

function pkr(paisa: number): string {
    return 'PKR ' + (paisa / 100).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function PurchasesCreate({ suppliers, medicines }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        supplier_id: suppliers[0]?.id || '',
        supplier_invoice_number: '',
        invoice_date: new Date().toISOString().split('T')[0],
        payment_method: 'cash',
        paid_paisa: 0,
        discount_paisa: 0,
        tax_paisa: 0,
        notes: '',
        lines: [
            {
                medicine_id: medicines[0]?.id || 1,
                medicine_package_id: medicines[0]?.packages[0]?.id || '',
                batch_number: '',
                expiry_date: '',
                package_quantity: 1,
                bonus_base_quantity: 0,
                package_cost_paisa: medicines[0]?.packages[0]?.default_purchase_price_paisa || 1000,
            },
        ],
    });

    const addLine = () => {
        const med = medicines[0];
        setData('lines', [
            ...data.lines,
            {
                medicine_id: med?.id || 1,
                medicine_package_id: med?.packages[0]?.id || '',
                batch_number: '',
                expiry_date: '',
                package_quantity: 1,
                bonus_base_quantity: 0,
                package_cost_paisa: med?.packages[0]?.default_purchase_price_paisa || 1000,
            },
        ]);
    };

    const removeLine = (idx: number) => {
        if (data.lines.length === 1) return;
        const updated = [...data.lines];
        updated.splice(idx, 1);
        setData('lines', updated);
    };

    const updateLine = (idx: number, field: string, val: any) => {
        const updated = [...data.lines];
        (updated[idx] as any)[field] = val;

        // If medicine changed, auto-select first package
        if (field === 'medicine_id') {
            const med = medicines.find((m) => m.id === Number(val));
            if (med && med.packages.length > 0) {
                updated[idx].medicine_package_id = med.packages[0].id;
                updated[idx].package_cost_paisa = med.packages[0].default_purchase_price_paisa || 1000;
            }
        }

        setData('lines', updated);
    };

    // Calculate subtotal and grand total
    const totals = useMemo(() => {
        let subtotal = 0;
        for (const line of data.lines) {
            subtotal += (line.package_quantity || 0) * (line.package_cost_paisa || 0);
        }
        const total = Math.max(0, subtotal - data.discount_paisa + data.tax_paisa);
        return { subtotal, total };
    }, [data.lines, data.discount_paisa, data.tax_paisa]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('purchases.store'));
    };

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
                            Receive Purchase Invoice
                        </h1>
                        <p className="text-sm text-slate-400">Post goods receipt, update supplier liability, and register new batches</p>
                    </div>
                </div>
            }
        >
            <Head title="Receive Purchase" />

            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Header Information Card */}
                <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 space-y-4">
                    <h2 className="text-base font-semibold text-white border-b border-slate-800 pb-2">
                        Supplier Bill Header
                    </h2>

                    <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div>
                            <label className="block text-xs font-semibold text-slate-400 mb-1">Supplier *</label>
                            <select
                                value={data.supplier_id}
                                onChange={(e) => setData('supplier_id', Number(e.target.value))}
                                className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                                required
                            >
                                {suppliers.map((s) => (
                                    <option key={s.id} value={s.id}>{s.name} ({s.code})</option>
                                ))}
                            </select>
                            {errors.supplier_id && <div className="text-xs text-rose-400 mt-1">{errors.supplier_id}</div>}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-400 mb-1">Supplier Invoice #</label>
                            <input
                                type="text"
                                value={data.supplier_invoice_number}
                                onChange={(e) => setData('supplier_invoice_number', e.target.value)}
                                placeholder="e.g. INV-9812"
                                className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-400 mb-1">Invoice Date *</label>
                            <input
                                type="date"
                                value={data.invoice_date}
                                onChange={(e) => setData('invoice_date', e.target.value)}
                                className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                                required
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-400 mb-1">Payment Method</label>
                            <select
                                value={data.payment_method}
                                onChange={(e) => setData('payment_method', e.target.value)}
                                className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                            >
                                <option value="cash">Cash Paid Now</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="credit">On Account / Payable</option>
                            </select>
                        </div>
                    </div>
                </div>

                {/* Purchase Line Items */}
                <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 space-y-4">
                    <div className="flex items-center justify-between border-b border-slate-800 pb-2">
                        <div>
                            <h2 className="text-base font-semibold text-white">Received Items & Batch Costing</h2>
                            <p className="text-xs text-slate-400">Capture batch number, expiry date, bonus units, and purchase cost</p>
                        </div>
                        <button
                            type="button"
                            onClick={addLine}
                            className="flex items-center gap-1.5 rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-slate-700"
                        >
                            <Plus className="h-3.5 w-3.5" />
                            <span>Add Medicine Line</span>
                        </button>
                    </div>

                    <div className="space-y-3">
                        {data.lines.map((line, idx) => {
                            const selectedMed = medicines.find((m) => m.id === Number(line.medicine_id)) || medicines[0];
                            const selectedPkg = selectedMed?.packages.find((p) => p.id === Number(line.medicine_package_id));
                            const multiplier = selectedPkg?.base_quantity || 1;
                            const totalBaseQty = (line.package_quantity * multiplier) + (Number(line.bonus_base_quantity) || 0);
                            const totalLineCost = line.package_quantity * line.package_cost_paisa;
                            const effectiveUnitCost = totalBaseQty > 0 ? (totalLineCost / totalBaseQty) : 0;

                            return (
                                <div key={idx} className="rounded-xl border border-slate-800 bg-slate-950 p-4 space-y-3">
                                    <div className="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                                        {/* Medicine */}
                                        <div className="sm:col-span-4">
                                            <label className="block text-[11px] font-semibold text-slate-400 mb-1">Medicine *</label>
                                            <select
                                                value={line.medicine_id}
                                                onChange={(e) => updateLine(idx, 'medicine_id', Number(e.target.value))}
                                                className="w-full rounded-lg bg-slate-900 border border-slate-700 px-2.5 py-1.5 text-xs text-slate-100"
                                                required
                                            >
                                                {medicines.map((m) => (
                                                    <option key={m.id} value={m.id}>
                                                        {m.brand_name} {m.strength} ({m.generic_name})
                                                    </option>
                                                ))}
                                            </select>
                                        </div>

                                        {/* Packaging Unit */}
                                        <div className="sm:col-span-2">
                                            <label className="block text-[11px] font-semibold text-slate-400 mb-1">Unit</label>
                                            <select
                                                value={line.medicine_package_id}
                                                onChange={(e) => updateLine(idx, 'medicine_package_id', Number(e.target.value))}
                                                className="w-full rounded-lg bg-slate-900 border border-slate-700 px-2.5 py-1.5 text-xs text-slate-100"
                                            >
                                                {selectedMed?.packages.map((pkg) => (
                                                    <option key={pkg.id} value={pkg.id}>
                                                        {pkg.name} ({pkg.base_quantity} {selectedMed.base_unit})
                                                    </option>
                                                ))}
                                            </select>
                                        </div>

                                        {/* Batch Number */}
                                        <div className="sm:col-span-2">
                                            <label className="block text-[11px] font-semibold text-slate-400 mb-1">Batch # *</label>
                                            <input
                                                type="text"
                                                value={line.batch_number}
                                                onChange={(e) => updateLine(idx, 'batch_number', e.target.value)}
                                                placeholder="e.g. B-AUG-102"
                                                className="w-full rounded-lg bg-slate-900 border border-slate-700 px-2.5 py-1.5 text-xs text-slate-100"
                                                required
                                            />
                                        </div>

                                        {/* Expiry Date */}
                                        <div className="sm:col-span-2">
                                            <label className="block text-[11px] font-semibold text-slate-400 mb-1">Expiry Date *</label>
                                            <input
                                                type="date"
                                                value={line.expiry_date}
                                                onChange={(e) => updateLine(idx, 'expiry_date', e.target.value)}
                                                className="w-full rounded-lg bg-slate-900 border border-slate-700 px-2.5 py-1.5 text-xs text-slate-100"
                                                required
                                            />
                                        </div>

                                        {/* Delete Action */}
                                        <div className="sm:col-span-2 flex justify-end pb-1">
                                            <button
                                                type="button"
                                                onClick={() => removeLine(idx)}
                                                disabled={data.lines.length === 1}
                                                className="rounded p-1 text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 disabled:opacity-20"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 sm:grid-cols-4 gap-3 pt-2 border-t border-slate-800/60">
                                        <div>
                                            <label className="block text-[11px] font-semibold text-slate-400 mb-1">
                                                Qty (Packs) *
                                            </label>
                                            <input
                                                type="number"
                                                min="1"
                                                value={line.package_quantity}
                                                onChange={(e) => updateLine(idx, 'package_quantity', Number(e.target.value))}
                                                className="w-full rounded-lg bg-slate-900 border border-slate-700 px-2.5 py-1.5 text-xs text-slate-100"
                                                required
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-[11px] font-semibold text-slate-400 mb-1">
                                                Bonus Free ({selectedMed?.base_unit})
                                            </label>
                                            <input
                                                type="number"
                                                min="0"
                                                value={line.bonus_base_quantity}
                                                onChange={(e) => updateLine(idx, 'bonus_base_quantity', Number(e.target.value))}
                                                placeholder="0"
                                                className="w-full rounded-lg bg-slate-900 border border-slate-700 px-2.5 py-1.5 text-xs text-slate-100"
                                            />
                                        </div>

                                        <div>
                                            <label className="block text-[11px] font-semibold text-slate-400 mb-1">
                                                Pack Cost (PKR) *
                                            </label>
                                            <input
                                                type="number"
                                                step="0.01"
                                                value={line.package_cost_paisa / 100}
                                                onChange={(e) => updateLine(idx, 'package_cost_paisa', Math.round(Number(e.target.value) * 100))}
                                                className="w-full rounded-lg bg-slate-900 border border-slate-700 px-2.5 py-1.5 text-xs text-slate-100"
                                                required
                                            />
                                        </div>

                                        <div className="text-right flex flex-col justify-end">
                                            <div className="text-[11px] text-slate-400">Total: <strong className="text-slate-200">{pkr(totalLineCost)}</strong></div>
                                            <div className="text-[10px] text-emerald-400 font-medium">
                                                Unit Cost: {pkr(effectiveUnitCost)} / {selectedMed?.base_unit} ({totalBaseQty} units)
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Totals & Payment Summary Card */}
                <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 flex flex-col sm:flex-row justify-between gap-6">
                    <div className="flex-1 max-w-sm space-y-3">
                        <div>
                            <label className="block text-xs font-semibold text-slate-400 mb-1">Bill Discount (PKR)</label>
                            <input
                                type="number"
                                min="0"
                                step="1"
                                value={data.discount_paisa / 100}
                                onChange={(e) => setData('discount_paisa', Math.round(Number(e.target.value) * 100))}
                                className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-1.5 text-xs text-slate-100"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-400 mb-1">Paid Now (PKR)</label>
                            <input
                                type="number"
                                min="0"
                                step="1"
                                value={data.paid_paisa / 100}
                                onChange={(e) => setData('paid_paisa', Math.round(Number(e.target.value) * 100))}
                                className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-1.5 text-xs text-slate-100"
                            />
                        </div>
                    </div>

                    <div className="w-72 space-y-2 text-sm border-t sm:border-t-0 sm:border-l border-slate-800 sm:pl-6 pt-4 sm:pt-0">
                        <div className="flex justify-between text-slate-400">
                            <span>Subtotal:</span>
                            <span className="font-semibold text-slate-200">{pkr(totals.subtotal)}</span>
                        </div>
                        <div className="flex justify-between text-slate-400">
                            <span>Discount:</span>
                            <span className="font-semibold text-rose-400">- {pkr(data.discount_paisa)}</span>
                        </div>
                        <div className="pt-2 border-t border-slate-800 flex justify-between font-bold text-base text-slate-100">
                            <span>Grand Total:</span>
                            <span className="text-emerald-400 font-extrabold">{pkr(totals.total)}</span>
                        </div>
                        <div className="flex justify-between text-xs text-slate-400">
                            <span>Balance Due:</span>
                            <span className="font-bold text-amber-400">{pkr(Math.max(0, totals.total - data.paid_paisa))}</span>
                        </div>
                    </div>
                </div>

                {/* Submit Actions */}
                <div className="flex justify-end gap-3">
                    <Link
                        href={route('purchases.index')}
                        className="rounded-xl border border-slate-700 px-5 py-2.5 text-sm font-semibold text-slate-300 hover:bg-slate-800"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        disabled={processing}
                        className="flex items-center gap-2 rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-bold text-white shadow-lg shadow-emerald-600/30 hover:bg-emerald-500 disabled:opacity-50"
                    >
                        <Save className="h-4 w-4" />
                        <span>Post Purchase & Update Stock</span>
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
