import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import { Pill, Plus, Trash2, ArrowLeft, Save, X } from 'lucide-react';

interface Props {
    categories: Array<{ id: number; name: string }>;
    manufacturers: Array<{ id: number; name: string }>;
}

export default function MedicinesCreate({ categories, manufacturers }: Props) {
    const [categoryList, setCategoryList] = useState(categories);
    const [manufacturerList, setManufacturerList] = useState(manufacturers);

    // Category modal state
    const [showCategoryModal, setShowCategoryModal] = useState(false);
    const [categoryName, setCategoryName] = useState('');
    const [categoryDescription, setCategoryDescription] = useState('');
    const [categorySubmitting, setCategorySubmitting] = useState(false);
    const [categoryError, setCategoryError] = useState('');

    // Manufacturer modal state
    const [showManufacturerModal, setShowManufacturerModal] = useState(false);
    const [manufacturerName, setManufacturerName] = useState('');
    const [manufacturerContact, setManufacturerContact] = useState('');
    const [manufacturerSubmitting, setManufacturerSubmitting] = useState(false);
    const [manufacturerError, setManufacturerError] = useState('');

    const { data, setData, post, processing, errors } = useForm({
        code: 'MED-' + Math.floor(1000 + Math.random() * 9000),
        brand_name: '',
        generic_name: '',
        strength: '',
        dosage_form: 'Tablet',
        category_id: categories[0]?.id || '',
        manufacturer_id: manufacturers[0]?.id || '',
        base_unit: 'Tablet',
        rack_shelf: 'A-01',
        reorder_level: 50,
        prescription_required: false,
        notes: '',
        packages: [
            {
                name: 'Unit / Piece',
                base_quantity: 1,
                default_sell_price_paisa: 500,
                default_purchase_price_paisa: 400,
                barcode: '',
                is_default_sale: true,
            },
        ],
    });

    const handleCreateCategory = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!categoryName.trim()) {
            setCategoryError('Category name is required.');
            return;
        }
        setCategorySubmitting(true);
        setCategoryError('');
        try {
            const res = await axios.post(route('categories.quick-store'), {
                name: categoryName.trim(),
                description: categoryDescription.trim() || undefined,
            });
            const newCat = res.data.category;
            setCategoryList((prev) => [...prev, newCat]);
            setData('category_id', newCat.id);
            setCategoryName('');
            setCategoryDescription('');
            setShowCategoryModal(false);
        } catch (err: any) {
            setCategoryError(
                err.response?.data?.errors?.name?.[0] ||
                err.response?.data?.message ||
                'Failed to create category.'
            );
        } finally {
            setCategorySubmitting(false);
        }
    };

    const handleCreateManufacturer = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!manufacturerName.trim()) {
            setManufacturerError('Manufacturer name is required.');
            return;
        }
        setManufacturerSubmitting(true);
        setManufacturerError('');
        try {
            const res = await axios.post(route('manufacturers.quick-store'), {
                name: manufacturerName.trim(),
                contact_info: manufacturerContact.trim() || undefined,
            });
            const newMfg = res.data.manufacturer;
            setManufacturerList((prev) => [...prev, newMfg]);
            setData('manufacturer_id', newMfg.id);
            setManufacturerName('');
            setManufacturerContact('');
            setShowManufacturerModal(false);
        } catch (err: any) {
            setManufacturerError(
                err.response?.data?.errors?.name?.[0] ||
                err.response?.data?.message ||
                'Failed to create manufacturer.'
            );
        } finally {
            setManufacturerSubmitting(false);
        }
    };

    const handleDeleteCategory = async (id: number, name: string) => {
        if (!confirm(`Are you sure you want to delete category "${name}"?`)) return;
        setCategoryError('');
        try {
            await axios.delete(`/categories/${id}`);
            const updated = categoryList.filter((c) => c.id !== id);
            setCategoryList(updated);
            if (data.category_id === id) {
                setData('category_id', updated[0]?.id || '');
            }
        } catch (err: any) {
            setCategoryError(
                err.response?.data?.message || 'Failed to delete category.'
            );
        }
    };

    const handleDeleteManufacturer = async (id: number, name: string) => {
        if (!confirm(`Are you sure you want to delete manufacturer "${name}"?`)) return;
        setManufacturerError('');
        try {
            await axios.delete(`/manufacturers/${id}`);
            const updated = manufacturerList.filter((m) => m.id !== id);
            setManufacturerList(updated);
            if (data.manufacturer_id === id) {
                setData('manufacturer_id', updated[0]?.id || '');
            }
        } catch (err: any) {
            setManufacturerError(
                err.response?.data?.message || 'Failed to delete manufacturer.'
            );
        }
    };

    const addPackage = () => {
        setData('packages', [
            ...data.packages,
            {
                name: 'Strip of 10',
                base_quantity: 10,
                default_sell_price_paisa: 5000,
                default_purchase_price_paisa: 4000,
                barcode: '',
                is_default_sale: false,
            },
        ]);
    };

    const removePackage = (idx: number) => {
        if (data.packages.length === 1) return;
        const updated = [...data.packages];
        updated.splice(idx, 1);
        setData('packages', updated);
    };

    const updatePackageField = (idx: number, field: string, value: any) => {
        const updated = [...data.packages];
        (updated[idx] as any)[field] = value;
        setData('packages', updated);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('medicines.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-4">
                    <Link
                        href={route('medicines.index')}
                        className="rounded-xl bg-slate-800 p-2 text-slate-400 hover:text-white"
                    >
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white">Register New Medicine</h1>
                        <p className="text-sm text-slate-400">Add medicine specifications, dosages, and packaging conversion rates</p>
                    </div>
                </div>
            }
        >
            <Head title="Add Medicine" />

            <form onSubmit={handleSubmit} className="max-w-4xl space-y-6">
                {/* Basic Details Card */}
                <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 space-y-4">
                    <h2 className="text-base font-semibold text-white border-b border-slate-800 pb-2">
                        Medicine Specifications
                    </h2>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label className="block text-xs font-semibold text-slate-400 mb-1">Item Code</label>
                            <input
                                type="text"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value)}
                                className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500 font-mono"
                                required
                            />
                            {errors.code && <div className="text-xs text-rose-400 mt-1">{errors.code}</div>}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-400 mb-1">Brand Name</label>
                            <input
                                type="text"
                                value={data.brand_name}
                                onChange={(e) => setData('brand_name', e.target.value)}
                                placeholder="e.g. Panadol 500mg"
                                className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500 font-semibold"
                                required
                            />
                            {errors.brand_name && <div className="text-xs text-rose-400 mt-1">{errors.brand_name}</div>}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-400 mb-1">Strength / Dosage</label>
                            <input
                                type="text"
                                value={data.strength}
                                onChange={(e) => setData('strength', e.target.value)}
                                placeholder="e.g. 500mg, 10mg/5ml"
                                className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label className="block text-xs font-semibold text-slate-400 mb-1">Generic Ingredient</label>
                            <input
                                type="text"
                                value={data.generic_name}
                                onChange={(e) => setData('generic_name', e.target.value)}
                                placeholder="e.g. Paracetamol"
                                className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-400 mb-1">Dosage Form</label>
                            <select
                                value={data.dosage_form}
                                onChange={(e) => setData('dosage_form', e.target.value)}
                                className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                            >
                                {['Tablet', 'Capsule', 'Syrup', 'Suspension', 'Injection', 'Cream', 'Ointment', 'Eye Drops', 'Inhaler', 'Sachet'].map((f) => (
                                    <option key={f} value={f}>{f}</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-400 mb-1">Base Unit</label>
                            <select
                                value={data.base_unit}
                                onChange={(e) => setData('base_unit', e.target.value)}
                                className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                            >
                                {['Tablet', 'Capsule', 'Bottle', 'Vial', 'Tube', 'Piece', 'ml', 'gm'].map((u) => (
                                    <option key={u} value={u}>{u}</option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div>
                            <div className="flex items-center justify-between mb-1">
                                <label className="text-xs font-semibold text-slate-400">Category</label>
                                <button
                                    type="button"
                                    onClick={() => { setCategoryError(''); setShowCategoryModal(true); }}
                                    className="inline-flex items-center gap-0.5 text-[11px] font-semibold text-emerald-400 hover:text-emerald-300 transition-colors"
                                    title="Add new category"
                                >
                                    <Plus className="h-3 w-3" />
                                    Add
                                </button>
                            </div>
                            <select
                                value={data.category_id}
                                onChange={(e) => setData('category_id', e.target.value)}
                                className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                            >
                                {categoryList.map((c) => (
                                    <option key={c.id} value={c.id}>{c.name}</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <div className="flex items-center justify-between mb-1">
                                <label className="text-xs font-semibold text-slate-400">Manufacturer</label>
                                <button
                                    type="button"
                                    onClick={() => { setManufacturerError(''); setShowManufacturerModal(true); }}
                                    className="inline-flex items-center gap-0.5 text-[11px] font-semibold text-emerald-400 hover:text-emerald-300 transition-colors"
                                    title="Add new manufacturer"
                                >
                                    <Plus className="h-3 w-3" />
                                    Add
                                </button>
                            </div>
                            <select
                                value={data.manufacturer_id}
                                onChange={(e) => setData('manufacturer_id', e.target.value)}
                                className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                            >
                                {manufacturerList.map((m) => (
                                    <option key={m.id} value={m.id}>{m.name}</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-400 mb-1">Rack / Shelf</label>
                            <input
                                type="text"
                                value={data.rack_shelf}
                                onChange={(e) => setData('rack_shelf', e.target.value)}
                                placeholder="e.g. Shelf A-02"
                                className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-400 mb-1">Reorder Threshold</label>
                            <input
                                type="number"
                                value={data.reorder_level}
                                onChange={(e) => setData('reorder_level', parseInt(e.target.value) || 0)}
                                className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                            />
                        </div>
                    </div>

                    <div className="flex items-center gap-4 pt-2">
                        <label className="flex items-center gap-2 cursor-pointer text-sm text-slate-300">
                            <input
                                type="checkbox"
                                checked={data.prescription_required}
                                onChange={(e) => setData('prescription_required', e.target.checked)}
                                className="rounded border-slate-700 bg-slate-950 text-emerald-500 focus:ring-emerald-500"
                            />
                            <span>Prescription Required (Rx Only)</span>
                        </label>
                    </div>
                </div>

                {/* Packaging & Unit Conversions */}
                <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 space-y-4">
                    <div className="flex items-center justify-between border-b border-slate-800 pb-2">
                        <div>
                            <h2 className="text-base font-semibold text-white">Packaging & Conversions</h2>
                            <p className="text-xs text-slate-400">
                                Define multiple pack sizes (e.g., 1 Box = 10 Strips = 100 Tablets) with specific selling prices and barcodes.
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={addPackage}
                            className="flex items-center gap-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/30 px-3 py-1.5 text-xs font-bold text-emerald-400 hover:bg-emerald-500/20"
                        >
                            <Plus className="h-3.5 w-3.5" />
                            <span>Add Pack Option</span>
                        </button>
                    </div>

                    <div className="space-y-3">
                        {data.packages.map((pkg, idx) => (
                            <div
                                key={idx}
                                className="grid grid-cols-1 sm:grid-cols-12 gap-3 p-3.5 rounded-xl border border-slate-800/80 bg-slate-950/50 items-end"
                            >
                                <div className="sm:col-span-3">
                                    <label className="block text-[11px] font-semibold text-slate-400 mb-1">Package Name</label>
                                    <input
                                        type="text"
                                        value={pkg.name}
                                        onChange={(e) => updatePackageField(idx, 'name', e.target.value)}
                                        placeholder="e.g. Box of 100"
                                        className="w-full rounded-lg bg-slate-900 border border-slate-700 px-2.5 py-1.5 text-xs text-slate-100 focus:outline-none focus:border-emerald-500"
                                        required
                                    />
                                </div>

                                <div className="sm:col-span-2">
                                    <label className="block text-[11px] font-semibold text-slate-400 mb-1">
                                        Base Qty ({data.base_unit}s)
                                    </label>
                                    <input
                                        type="number"
                                        min="1"
                                        value={pkg.base_quantity}
                                        onChange={(e) => updatePackageField(idx, 'base_quantity', parseInt(e.target.value) || 1)}
                                        className="w-full rounded-lg bg-slate-900 border border-slate-700 px-2.5 py-1.5 text-xs text-slate-100 focus:outline-none focus:border-emerald-500"
                                        required
                                    />
                                </div>

                                <div className="sm:col-span-2">
                                    <label className="block text-[11px] font-semibold text-slate-400 mb-1">Sell Price (PKR)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={pkg.default_sell_price_paisa / 100}
                                        onChange={(e) => updatePackageField(idx, 'default_sell_price_paisa', Math.round(parseFloat(e.target.value || '0') * 100))}
                                        className="w-full rounded-lg bg-slate-900 border border-slate-700 px-2.5 py-1.5 text-xs text-slate-100 focus:outline-none focus:border-emerald-500"
                                        required
                                    />
                                </div>

                                <div className="sm:col-span-2">
                                    <label className="block text-[11px] font-semibold text-slate-400 mb-1">Purchase Cost (PKR)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={pkg.default_purchase_price_paisa / 100}
                                        onChange={(e) => updatePackageField(idx, 'default_purchase_price_paisa', Math.round(parseFloat(e.target.value || '0') * 100))}
                                        className="w-full rounded-lg bg-slate-900 border border-slate-700 px-2.5 py-1.5 text-xs text-slate-100 focus:outline-none focus:border-emerald-500"
                                    />
                                </div>

                                <div className="sm:col-span-2">
                                    <label className="block text-[11px] font-semibold text-slate-400 mb-1">Barcode</label>
                                    <input
                                        type="text"
                                        value={pkg.barcode}
                                        onChange={(e) => updatePackageField(idx, 'barcode', e.target.value)}
                                        placeholder="Scan barcode..."
                                        className="w-full rounded-lg bg-slate-900 border border-slate-700 px-2.5 py-1.5 text-xs text-slate-100 focus:outline-none focus:border-emerald-500 font-mono"
                                    />
                                </div>

                                <div className="sm:col-span-1 flex justify-center pb-1">
                                    <button
                                        type="button"
                                        onClick={() => removePackage(idx)}
                                        disabled={data.packages.length === 1}
                                        className="p-1.5 text-slate-500 hover:text-rose-400 disabled:opacity-30 disabled:hover:text-slate-500"
                                        title="Delete pack option"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Submit Actions */}
                <div className="flex justify-end gap-3">
                    <Link
                        href={route('medicines.index')}
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
                        <span>Save Medicine</span>
                    </button>
                </div>
            </form>

            {/* Quick Add Category Modal */}
            {showCategoryModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4">
                    <div className="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
                        <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                            <h3 className="text-base font-bold text-white flex items-center gap-2">
                                <Plus className="h-4 w-4 text-emerald-400" />
                                Add New Category
                            </h3>
                            <button
                                type="button"
                                onClick={() => setShowCategoryModal(false)}
                                className="rounded-lg p-1 text-slate-400 hover:bg-slate-800 hover:text-white"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        {categoryError && (
                            <div className="rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-400">
                                {categoryError}
                            </div>
                        )}

                        <form onSubmit={handleCreateCategory} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">
                                    Category Name <span className="text-rose-400">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={categoryName}
                                    onChange={(e) => setCategoryName(e.target.value)}
                                    placeholder="e.g. Antibiotics, Cardiovascular"
                                    autoFocus
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">
                                    Description (Optional)
                                </label>
                                <textarea
                                    value={categoryDescription}
                                    onChange={(e) => setCategoryDescription(e.target.value)}
                                    placeholder="Optional description or therapeutic classification..."
                                    rows={2}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                                />
                            </div>

                            <div className="flex justify-end gap-2 pt-2 border-t border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setShowCategoryModal(false)}
                                    className="rounded-xl border border-slate-700 px-4 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={categorySubmitting || !categoryName.trim()}
                                    className="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-500 disabled:opacity-50 flex items-center gap-1.5 shadow-lg shadow-emerald-600/30"
                                >
                                    {categorySubmitting ? 'Saving...' : 'Save & Select'}
                                </button>
                            </div>
                        </form>

                        {/* Existing Categories List */}
                        <div className="pt-3 border-t border-slate-800 space-y-2">
                            <div className="flex items-center justify-between text-xs text-slate-400 font-semibold">
                                <span>Existing Categories ({categoryList.length})</span>
                            </div>
                            <div className="max-h-36 overflow-y-auto space-y-1.5 pr-1">
                                {categoryList.map((cat) => (
                                    <div
                                        key={cat.id}
                                        className="flex items-center justify-between p-2 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-200"
                                    >
                                        <span className="font-medium truncate">{cat.name}</span>
                                        <button
                                            type="button"
                                            onClick={() => handleDeleteCategory(cat.id, cat.name)}
                                            className="p-1 text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 rounded-lg transition-colors"
                                            title={`Delete category "${cat.name}"`}
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Quick Add Manufacturer Modal */}
            {showManufacturerModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4">
                    <div className="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
                        <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                            <h3 className="text-base font-bold text-white flex items-center gap-2">
                                <Plus className="h-4 w-4 text-emerald-400" />
                                Add New Manufacturer
                            </h3>
                            <button
                                type="button"
                                onClick={() => setShowManufacturerModal(false)}
                                className="rounded-lg p-1 text-slate-400 hover:bg-slate-800 hover:text-white"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        {manufacturerError && (
                            <div className="rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-400">
                                {manufacturerError}
                            </div>
                        )}

                        <form onSubmit={handleCreateManufacturer} className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">
                                    Manufacturer / Company Name <span className="text-rose-400">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={manufacturerName}
                                    onChange={(e) => setManufacturerName(e.target.value)}
                                    placeholder="e.g. Getz Pharma, Sami Pharmaceuticals"
                                    autoFocus
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">
                                    Contact Info / Address (Optional)
                                </label>
                                <input
                                    type="text"
                                    value={manufacturerContact}
                                    onChange={(e) => setManufacturerContact(e.target.value)}
                                    placeholder="e.g. Karachi, Phone: 021-35000000"
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                                />
                            </div>

                            <div className="flex justify-end gap-2 pt-2 border-t border-slate-800">
                                <button
                                    type="button"
                                    onClick={() => setShowManufacturerModal(false)}
                                    className="rounded-xl border border-slate-700 px-4 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={manufacturerSubmitting || !manufacturerName.trim()}
                                    className="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-500 disabled:opacity-50 flex items-center gap-1.5 shadow-lg shadow-emerald-600/30"
                                >
                                    {manufacturerSubmitting ? 'Saving...' : 'Save & Select'}
                                </button>
                            </div>
                        </form>

                        {/* Existing Manufacturers List */}
                        <div className="pt-3 border-t border-slate-800 space-y-2">
                            <div className="flex items-center justify-between text-xs text-slate-400 font-semibold">
                                <span>Existing Manufacturers ({manufacturerList.length})</span>
                            </div>
                            <div className="max-h-36 overflow-y-auto space-y-1.5 pr-1">
                                {manufacturerList.map((mfg) => (
                                    <div
                                        key={mfg.id}
                                        className="flex items-center justify-between p-2 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-200"
                                    >
                                        <span className="font-medium truncate">{mfg.name}</span>
                                        <button
                                            type="button"
                                            onClick={() => handleDeleteManufacturer(mfg.id, mfg.name)}
                                            className="p-1 text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 rounded-lg transition-colors"
                                            title={`Delete manufacturer "${mfg.name}"`}
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
