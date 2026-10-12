import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Pill, Plus, Search, Filter, Eye, MapPin, X, RotateCcw } from 'lucide-react';

interface Medicine {
    id: number;
    code: string;
    brand_name: string;
    generic_name?: string;
    strength?: string;
    dosage_form: string;
    base_unit: string;
    rack_shelf?: string;
    reorder_level: number;
    prescription_required: boolean;
    total_stock_base: number;
    category?: { id: number; name: string };
    manufacturer?: { id: number; name: string };
    packages: Array<{ id: number; name: string; base_quantity: number; default_sell_price_paisa: number }>;
}

interface Props {
    medicines: {
        data: Medicine[];
        links: any[];
        total: number;
        from: number;
        to: number;
    };
    categories: Array<{ id: number; name: string }>;
    manufacturers: Array<{ id: number; name: string }>;
    filters: { search?: string; category_id?: string; manufacturer_id?: string };
}

function pkr(paisa: number): string {
    return 'PKR ' + (paisa / 100).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function MedicinesIndex({ medicines, categories, manufacturers, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [categoryId, setCategoryId] = useState(filters.category_id || '');
    const [manufacturerId, setManufacturerId] = useState(filters.manufacturer_id || '');

    const handleFilter = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        router.get(
            route('medicines.index'),
            {
                search: search.trim() || undefined,
                category_id: categoryId || undefined,
                manufacturer_id: manufacturerId || undefined,
            },
            { preserveState: true }
        );
    };

    const resetFilters = () => {
        setSearch('');
        setCategoryId('');
        setManufacturerId('');
        router.get(route('medicines.index'), {}, { preserveState: true });
    };

    const hasActiveFilters = Boolean(search || categoryId || manufacturerId);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 w-full">
                    <div>
                        <h1 className="text-2xl font-black tracking-tight text-white flex items-center gap-2">
                            <Pill className="h-6 w-6 text-emerald-400" />
                            <span>Medicine Catalogue & Packaging</span>
                        </h1>
                        <p className="text-xs text-slate-400 mt-0.5">
                            Master pharmaceutical catalogue, unit packaging conversions, and retail pricing
                        </p>
                    </div>
                    <Link
                        href={route('medicines.create')}
                        className="flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-2.5 text-xs font-bold text-white shadow-lg shadow-emerald-600/25 hover:from-emerald-500 hover:to-teal-500 transition self-start sm:self-auto"
                    >
                        <Plus className="h-4 w-4" />
                        <span>Add New Medicine</span>
                    </Link>
                </div>
            }
        >
            <Head title="Medicines Catalogue" />

            {/* Filter Bar */}
            <div className="mb-6 rounded-2xl border border-slate-800 bg-slate-900/70 p-4 shadow-lg backdrop-blur-sm">
                <form onSubmit={handleFilter} className="flex flex-col md:flex-row gap-3">
                    {/* Search query */}
                    <div className="flex-1 relative">
                        <Search className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by brand name, generic formula, item code..."
                            className="w-full rounded-xl border border-slate-700 bg-slate-950 pl-10 pr-4 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                        />
                    </div>

                    {/* Category Filter */}
                    <select
                        value={categoryId}
                        onChange={(e) => {
                            setCategoryId(e.target.value);
                            router.get(
                                route('medicines.index'),
                                {
                                    search: search.trim() || undefined,
                                    category_id: e.target.value || undefined,
                                    manufacturer_id: manufacturerId || undefined,
                                },
                                { preserveState: true }
                            );
                        }}
                        className="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                    >
                        <option value="">All Categories</option>
                        {categories.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </select>

                    {/* Manufacturer Filter */}
                    <select
                        value={manufacturerId}
                        onChange={(e) => {
                            setManufacturerId(e.target.value);
                            router.get(
                                route('medicines.index'),
                                {
                                    search: search.trim() || undefined,
                                    category_id: categoryId || undefined,
                                    manufacturer_id: e.target.value || undefined,
                                },
                                { preserveState: true }
                            );
                        }}
                        className="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                    >
                        <option value="">All Manufacturers</option>
                        {manufacturers.map((m) => (
                            <option key={m.id} value={m.id}>
                                {m.name}
                            </option>
                        ))}
                    </select>

                    <button
                        type="submit"
                        className="rounded-xl bg-slate-800 border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-700 transition"
                    >
                        Filter
                    </button>

                    {hasActiveFilters && (
                        <button
                            type="button"
                            onClick={resetFilters}
                            className="rounded-xl border border-slate-800 px-3 py-2 text-xs font-medium text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition flex items-center justify-center gap-1"
                            title="Reset all filters"
                        >
                            <RotateCcw className="h-3.5 w-3.5" />
                            <span>Reset</span>
                        </button>
                    )}
                </form>
            </div>

            {/* Medicines List Table */}
            <div className="rounded-2xl border border-slate-800 bg-slate-900/70 overflow-hidden shadow-xl backdrop-blur-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-slate-900/90 border-b border-slate-800 text-slate-400 font-semibold uppercase tracking-wider">
                            <tr>
                                <th className="py-3 px-4">Code</th>
                                <th className="py-3 px-4">Brand & Strength</th>
                                <th className="py-3 px-4">Generic Formula</th>
                                <th className="py-3 px-4">Category</th>
                                <th className="py-3 px-4">Form</th>
                                <th className="py-3 px-4">Shelf Location</th>
                                <th className="py-3 px-4">Packaging & Retail</th>
                                <th className="py-3 px-4 text-right">Available Stock</th>
                                <th className="py-3 px-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800/60">
                            {medicines.data.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="py-12 text-center text-slate-500 text-sm">
                                        No medicines found matching the active filters.
                                    </td>
                                </tr>
                            ) : (
                                medicines.data.map((m) => {
                                    const isLowStock = m.total_stock_base > 0 && m.total_stock_base <= m.reorder_level;
                                    const isOutOfStock = m.total_stock_base <= 0;

                                    return (
                                        <tr key={m.id} className="hover:bg-slate-800/40 transition-colors">
                                            <td className="py-3 px-4 font-mono font-medium text-slate-400">
                                                {m.code}
                                            </td>
                                            <td className="py-3 px-4">
                                                <div className="font-bold text-sm text-slate-100 flex items-center gap-1.5">
                                                    <span>{m.brand_name} {m.strength}</span>
                                                    {m.prescription_required && (
                                                        <span className="text-[10px] font-bold px-1.5 py-0.2 rounded bg-rose-500/20 text-rose-400 border border-rose-500/30">
                                                            Rx
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="text-[11px] text-slate-500 font-medium">
                                                    {m.manufacturer?.name || 'No Manufacturer'}
                                                </div>
                                            </td>
                                            <td className="py-3 px-4 text-slate-300">
                                                {m.generic_name || '—'}
                                            </td>
                                            <td className="py-3 px-4 text-slate-400">
                                                {m.category?.name ? (
                                                    <span className="rounded-md bg-slate-800/80 px-2 py-0.5 text-[11px] text-slate-300">
                                                        {m.category.name}
                                                    </span>
                                                ) : '—'}
                                            </td>
                                            <td className="py-3 px-4">
                                                <span className="rounded-md bg-slate-800 px-2 py-0.5 text-slate-300 font-medium text-[11px]">
                                                    {m.dosage_form}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4">
                                                {m.rack_shelf ? (
                                                    <span className="inline-flex items-center gap-1 font-mono text-emerald-400 text-xs">
                                                        <MapPin className="h-3 w-3" />
                                                        {m.rack_shelf}
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-600">—</span>
                                                )}
                                            </td>
                                            <td className="py-3 px-4">
                                                <div className="space-y-1">
                                                    {m.packages.map((pkg) => (
                                                        <div key={pkg.id} className="text-[11px] text-slate-300 flex items-center gap-1.5">
                                                            <span className="font-semibold text-slate-200">{pkg.name}</span>
                                                            <span className="text-slate-500">({pkg.base_quantity} {m.base_unit})</span>
                                                            <span className="text-emerald-400 font-bold ml-auto">{pkr(pkg.default_sell_price_paisa)}</span>
                                                        </div>
                                                    ))}
                                                </div>
                                            </td>
                                            <td className="py-3 px-4 text-right">
                                                <div>
                                                    <span className={`font-black text-sm ${
                                                        isOutOfStock
                                                            ? 'text-rose-400'
                                                            : isLowStock
                                                            ? 'text-amber-400'
                                                            : 'text-slate-100'
                                                    }`}>
                                                        {m.total_stock_base}
                                                    </span>
                                                    <span className="text-slate-500 ml-1 text-xs">{m.base_unit}</span>
                                                </div>
                                                {isOutOfStock ? (
                                                    <span className="text-[10px] text-rose-400 font-bold">Out of Stock</span>
                                                ) : isLowStock ? (
                                                    <span className="text-[10px] text-amber-400 font-medium">Reorder Alert (≤ {m.reorder_level})</span>
                                                ) : null}
                                            </td>
                                            <td className="py-3 px-4 text-center">
                                                <Link
                                                    href={route('medicines.show', m.id)}
                                                    className="inline-flex items-center gap-1 rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-700 hover:text-white transition"
                                                >
                                                    <Eye className="h-3.5 w-3.5" />
                                                    <span>View</span>
                                                </Link>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                <div className="p-4 border-t border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-400">
                    <div>
                        Showing <span className="font-semibold text-slate-200">{medicines.from || 0}</span> to <span className="font-semibold text-slate-200">{medicines.to || 0}</span> of <span className="font-semibold text-slate-200">{medicines.total}</span> medicines
                    </div>
                    <div className="flex gap-1">
                        {medicines.links.map((link, idx) => (
                            <Link
                                key={idx}
                                href={link.url || '#'}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                                className={`px-3 py-1 rounded-lg transition ${
                                    link.active
                                        ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-600/30'
                                        : link.url
                                        ? 'text-slate-400 hover:bg-slate-800 hover:text-white'
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
