import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Pill, ArrowLeft, Boxes, History, Edit, Archive } from 'lucide-react';

interface Props {
    medicine: any;
    total_stock_base: number;
    recentMovements: any[];
}

function pkr(paisa: number): string {
    return 'PKR ' + (paisa / 100).toFixed(2);
}

export default function MedicinesShow({ medicine, total_stock_base, recentMovements }: Props) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <Link
                            href={route('medicines.index')}
                            className="rounded-xl bg-slate-800 p-2 text-slate-400 hover:text-white"
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                                <Pill className="h-6 w-6 text-emerald-400" />
                                {medicine.brand_name} {medicine.strength}
                            </h1>
                            <p className="text-sm text-slate-400">
                                {medicine.generic_name} • Code: {medicine.code}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('medicines.edit', medicine.id)}
                            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-700 hover:text-white transition"
                        >
                            <Edit className="h-3.5 w-3.5" />
                            <span>Edit Profile</span>
                        </Link>
                        <Link
                            href={route('medicines.toggle-status', medicine.id)}
                            method="post"
                            as="button"
                            className={`inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-semibold transition ${
                                medicine.is_active
                                    ? 'border border-rose-500/30 bg-rose-500/10 text-rose-300 hover:bg-rose-500/20'
                                    : 'border border-emerald-500/30 bg-emerald-500/10 text-emerald-300 hover:bg-emerald-500/20'
                            }`}
                        >
                            <Archive className="h-3.5 w-3.5" />
                            <span>{medicine.is_active ? 'Archive Medicine' : 'Activate Medicine'}</span>
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title={medicine.brand_name} />

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Details Column */}
                <div className="space-y-6">
                    <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-5 space-y-3 text-sm">
                        <h2 className="font-semibold text-white border-b border-slate-800 pb-2">Medicine Profile</h2>
                        <div className="flex justify-between text-slate-400">
                            <span>Form:</span>
                            <span className="text-slate-200 font-medium">{medicine.dosage_form}</span>
                        </div>
                        <div className="flex justify-between text-slate-400">
                            <span>Base Unit:</span>
                            <span className="text-slate-200 font-medium">{medicine.base_unit}</span>
                        </div>
                        <div className="flex justify-between text-slate-400">
                            <span>Category:</span>
                            <span className="text-slate-200 font-medium">{medicine.category?.name || '—'}</span>
                        </div>
                        <div className="flex justify-between text-slate-400">
                            <span>Manufacturer:</span>
                            <span className="text-slate-200 font-medium">{medicine.manufacturer?.name || '—'}</span>
                        </div>
                        <div className="flex justify-between text-slate-400">
                            <span>Storage Shelf:</span>
                            <span className="text-slate-200 font-medium">{medicine.rack_shelf || '—'}</span>
                        </div>
                        <div className="flex justify-between text-slate-400">
                            <span>Reorder Level:</span>
                            <span className="text-slate-200 font-medium">{medicine.reorder_level} {medicine.base_unit}</span>
                        </div>
                        <div className="flex justify-between text-slate-400 pt-2 border-t border-slate-800">
                            <span>Total Sellable Stock:</span>
                            <span className="text-lg font-bold text-emerald-400">{total_stock_base} {medicine.base_unit}</span>
                        </div>
                    </div>

                    {/* Packaging Units */}
                    <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-5 space-y-3">
                        <h2 className="font-semibold text-white border-b border-slate-800 pb-2">Packaging Formats</h2>
                        <div className="space-y-2">
                            {medicine.packages.map((pkg: any) => (
                                <div key={pkg.id} className="rounded-xl border border-slate-800 bg-slate-950 p-3 text-xs flex justify-between items-center">
                                    <div>
                                        <div className="font-bold text-slate-200">{pkg.name}</div>
                                        <div className="text-slate-400">
                                            1 {pkg.name} = {pkg.base_quantity} {medicine.base_unit}(s)
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <div className="font-bold text-emerald-400">{pkr(pkg.default_sell_price_paisa)}</div>
                                        {pkg.is_default_sale && (
                                            <span className="text-[10px] text-teal-400 font-semibold">Default Unit</span>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Batches and Movement Ledger (Col 2) */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Batches List */}
                    <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
                        <h2 className="font-semibold text-white flex items-center gap-2 mb-3">
                            <Boxes className="h-4 w-4 text-emerald-400" />
                            <span>Active Batches (FEFO Tracking)</span>
                        </h2>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="border-b border-slate-800 text-slate-400 uppercase">
                                    <tr>
                                        <th className="pb-2">Batch No</th>
                                        <th className="pb-2">Expiry Date</th>
                                        <th className="pb-2">Status</th>
                                        <th className="pb-2 text-right">Remaining Stock</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-800/60">
                                    {medicine.batches.map((batch: any) => {
                                        const remaining = batch.stock_lots?.reduce((sum: number, l: any) => sum + l.remaining_base_qty, 0) || 0;
                                        return (
                                            <tr key={batch.id}>
                                                <td className="py-2.5 font-mono text-slate-200">{batch.batch_number}</td>
                                                <td className="py-2.5 text-slate-300">{batch.expiry_date}</td>
                                                <td className="py-2.5">
                                                    <span className="rounded bg-emerald-500/10 px-2 py-0.5 text-emerald-400 font-medium">
                                                        {batch.status}
                                                    </span>
                                                </td>
                                                <td className="py-2.5 text-right font-bold text-slate-100">
                                                    {remaining} {medicine.base_unit}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Movement Ledger */}
                    <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
                        <h2 className="font-semibold text-white flex items-center gap-2 mb-3">
                            <History className="h-4 w-4 text-blue-400" />
                            <span>Recent Stock Movements</span>
                        </h2>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="border-b border-slate-800 text-slate-400 uppercase">
                                    <tr>
                                        <th className="pb-2">Timestamp</th>
                                        <th className="pb-2">Type</th>
                                        <th className="pb-2 text-right">Quantity</th>
                                        <th className="pb-2">Notes</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-800/60">
                                    {recentMovements.map((m) => (
                                        <tr key={m.id}>
                                            <td className="py-2.5 text-slate-400 font-mono">
                                                {new Date(m.created_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' })}
                                            </td>
                                            <td className="py-2.5 capitalize font-medium text-slate-200">{m.movement_type}</td>
                                            <td className={`py-2.5 text-right font-bold ${m.quantity_base_unit > 0 ? 'text-emerald-400' : 'text-rose-400'}`}>
                                                {m.quantity_base_unit > 0 ? `+${m.quantity_base_unit}` : m.quantity_base_unit}
                                            </td>
                                            <td className="py-2.5 text-slate-400">{m.notes || '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
