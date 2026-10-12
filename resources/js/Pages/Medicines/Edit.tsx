import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { Pill, ArrowLeft, Save } from 'lucide-react';

interface Medicine {
    id: number;
    code: string;
    brand_name: string;
    generic_name?: string;
    strength?: string;
    dosage_form: string;
    category_id?: number;
    manufacturer_id?: number;
    base_unit: string;
    rack_shelf?: string;
    reorder_level: number;
    prescription_required: boolean;
    is_narcotic: boolean;
    is_active: boolean;
    notes?: string;
}

interface Props {
    medicine: Medicine;
    categories: Array<{ id: number; name: string }>;
    manufacturers: Array<{ id: number; name: string }>;
}

export default function MedicinesEdit({ medicine, categories, manufacturers }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        brand_name: medicine.brand_name || '',
        generic_name: medicine.generic_name || '',
        strength: medicine.strength || '',
        dosage_form: medicine.dosage_form || 'Tablet',
        category_id: medicine.category_id || '',
        manufacturer_id: medicine.manufacturer_id || '',
        base_unit: medicine.base_unit || 'Tablet',
        rack_shelf: medicine.rack_shelf || '',
        reorder_level: medicine.reorder_level ?? 0,
        prescription_required: Boolean(medicine.prescription_required),
        is_narcotic: Boolean(medicine.is_narcotic),
        notes: medicine.notes || '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('medicines.update', medicine.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-4">
                    <Link
                        href={route('medicines.show', medicine.id)}
                        className="rounded-xl bg-slate-800 p-2 text-slate-400 hover:text-white"
                    >
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                            <Pill className="h-6 w-6 text-emerald-400" />
                            Edit: {medicine.brand_name} {medicine.strength}
                        </h1>
                        <p className="text-sm text-slate-400">
                            Code: <span className="font-mono text-slate-300">{medicine.code}</span>
                        </p>
                    </div>
                </div>
            }
        >
            <Head title={`Edit ${medicine.brand_name}`} />

            <form onSubmit={submit} className="max-w-4xl space-y-6">
                <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 space-y-5">
                    <h2 className="text-base font-semibold text-white border-b border-slate-800 pb-3">
                        Medicine Profile & Identification
                    </h2>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-semibold text-slate-300 mb-1">
                                Brand Name *
                            </label>
                            <input
                                type="text"
                                value={data.brand_name}
                                onChange={(e) => setData('brand_name', e.target.value)}
                                className="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                                required
                            />
                            {errors.brand_name && (
                                <p className="text-xs text-rose-400 mt-1">{errors.brand_name}</p>
                            )}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-300 mb-1">
                                Generic Name / Formula
                            </label>
                            <input
                                type="text"
                                value={data.generic_name}
                                onChange={(e) => setData('generic_name', e.target.value)}
                                className="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                            />
                            {errors.generic_name && (
                                <p className="text-xs text-rose-400 mt-1">{errors.generic_name}</p>
                            )}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-300 mb-1">
                                Strength (e.g. 500mg, 10mg/5ml)
                            </label>
                            <input
                                type="text"
                                value={data.strength}
                                onChange={(e) => setData('strength', e.target.value)}
                                className="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                            />
                            {errors.strength && (
                                <p className="text-xs text-rose-400 mt-1">{errors.strength}</p>
                            )}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-300 mb-1">
                                Dosage Form *
                            </label>
                            <input
                                type="text"
                                value={data.dosage_form}
                                onChange={(e) => setData('dosage_form', e.target.value)}
                                className="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                                required
                            />
                            {errors.dosage_form && (
                                <p className="text-xs text-rose-400 mt-1">{errors.dosage_form}</p>
                            )}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-300 mb-1">
                                Category
                            </label>
                            <select
                                value={data.category_id}
                                onChange={(e) => setData('category_id', e.target.value ? Number(e.target.value) : '')}
                                className="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                            >
                                <option value="">Select Category...</option>
                                {categories.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-300 mb-1">
                                Manufacturer
                            </label>
                            <select
                                value={data.manufacturer_id}
                                onChange={(e) => setData('manufacturer_id', e.target.value ? Number(e.target.value) : '')}
                                className="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                            >
                                <option value="">Select Manufacturer...</option>
                                {manufacturers.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-300 mb-1">
                                Base Unit (Tablet, ml, vial) *
                            </label>
                            <input
                                type="text"
                                value={data.base_unit}
                                onChange={(e) => setData('base_unit', e.target.value)}
                                className="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                                required
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-300 mb-1">
                                Shelf / Rack Location
                            </label>
                            <input
                                type="text"
                                value={data.rack_shelf}
                                onChange={(e) => setData('rack_shelf', e.target.value)}
                                className="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-300 mb-1">
                                Reorder Alert Level (Base Units) *
                            </label>
                            <input
                                type="number"
                                min="0"
                                value={data.reorder_level}
                                onChange={(e) => setData('reorder_level', Number(e.target.value))}
                                className="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                                required
                            />
                        </div>
                    </div>

                    <div className="flex gap-6 pt-2">
                        <label className="inline-flex items-center gap-2 cursor-pointer text-sm text-slate-300">
                            <input
                                type="checkbox"
                                checked={data.prescription_required}
                                onChange={(e) => setData('prescription_required', e.target.checked)}
                                className="rounded border-slate-700 bg-slate-950 text-emerald-500 focus:ring-emerald-500 h-4 w-4"
                            />
                            <span>Prescription Required (Rx)</span>
                        </label>

                        <label className="inline-flex items-center gap-2 cursor-pointer text-sm text-slate-300">
                            <input
                                type="checkbox"
                                checked={data.is_narcotic}
                                onChange={(e) => setData('is_narcotic', e.target.checked)}
                                className="rounded border-slate-700 bg-slate-950 text-rose-500 focus:ring-rose-500 h-4 w-4"
                            />
                            <span>Narcotic / Controlled Substance</span>
                        </label>
                    </div>

                    <div>
                        <label className="block text-xs font-semibold text-slate-300 mb-1">
                            Notes / Instructions
                        </label>
                        <textarea
                            rows={3}
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            className="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
                        />
                    </div>
                </div>

                <div className="flex items-center justify-end gap-3">
                    <Link
                        href={route('medicines.show', medicine.id)}
                        className="rounded-xl border border-slate-700 px-4 py-2.5 text-xs font-semibold text-slate-300 hover:bg-slate-800"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-emerald-500 transition shadow disabled:opacity-50"
                    >
                        <Save className="h-4 w-4" />
                        <span>Update Medicine</span>
                    </button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
