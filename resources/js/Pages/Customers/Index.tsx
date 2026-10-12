import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { Users, Plus, Search, Eye, X } from 'lucide-react';

interface Customer {
    id: number;
    code: string;
    name: string;
    phone?: string;
    cnic?: string;
    address?: string;
    credit_limit_paisa: number;
    balance_paisa: number;
    is_active: boolean;
}

interface Props {
    customers: {
        data: Customer[];
        links: any[];
        total: number;
    };
    filters: { search?: string };
}

function pkr(paisa: number): string {
    return 'PKR ' + (paisa / 100).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function CustomersIndex({ customers, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [createModal, setCreateModal] = useState(false);

    const { data, setData, post, processing, reset, errors } = useForm({
        code: 'CUST-' + Math.floor(100 + Math.random() * 900),
        name: '',
        phone: '',
        cnic: '',
        address: '',
        credit_limit_paisa: 1000000, // PKR 10,000
    });

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('customers.index'), { search }, { preserveState: true });
    };

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('customers.store'), {
            onSuccess: () => {
                setCreateModal(false);
                reset();
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 w-full">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                            <Users className="h-6 w-6 text-emerald-400" />
                            Customers & Credit Khata Ledger
                        </h1>
                        <p className="text-sm text-slate-400">Customer directory, receivables tracking, credit limits, and collections</p>
                    </div>
                    <button
                        onClick={() => setCreateModal(true)}
                        className="flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-emerald-600/30 hover:bg-emerald-500 transition"
                    >
                        <Plus className="h-4 w-4" />
                        <span>Add New Customer</span>
                    </button>
                </div>
            }
        >
            <Head title="Customer Khata" />

            {/* Filter Bar */}
            <div className="mb-6 rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
                <form onSubmit={handleSearch} className="flex gap-3">
                    <div className="flex-1 relative">
                        <Search className="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by customer name, code or phone number..."
                            className="w-full rounded-xl border border-slate-700 bg-slate-950 pl-10 pr-4 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emerald-500"
                        />
                    </div>
                    <button
                        type="submit"
                        className="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-700"
                    >
                        Search
                    </button>
                </form>
            </div>

            {/* Customers Table */}
            <div className="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-slate-900 border-b border-slate-800 text-slate-400 font-semibold uppercase tracking-wider">
                            <tr>
                                <th className="py-3 px-4">Customer Code</th>
                                <th className="py-3 px-4">Name</th>
                                <th className="py-3 px-4">Phone Number</th>
                                <th className="py-3 px-4">CNIC</th>
                                <th className="py-3 px-4 text-right">Credit Limit</th>
                                <th className="py-3 px-4 text-right">Outstanding Khata</th>
                                <th className="py-3 px-4 text-center">Statement</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800/60">
                            {customers.data.map((c) => (
                                <tr key={c.id} className="hover:bg-slate-800/30">
                                    <td className="py-3 px-4 font-mono font-bold text-slate-400">{c.code}</td>
                                    <td className="py-3 px-4 font-bold text-slate-100 text-sm">{c.name}</td>
                                    <td className="py-3 px-4 text-slate-300">{c.phone || '—'}</td>
                                    <td className="py-3 px-4 text-slate-400 font-mono">{c.cnic || '—'}</td>
                                    <td className="py-3 px-4 text-right font-medium text-slate-300">{pkr(c.credit_limit_paisa)}</td>
                                    <td className="py-3 px-4 text-right font-bold text-sm">
                                        <span className={c.balance_paisa > 0 ? 'text-rose-400' : 'text-slate-400'}>
                                            {pkr(c.balance_paisa)}
                                        </span>
                                    </td>
                                    <td className="py-3 px-4 text-center">
                                        <Link
                                            href={route('customers.show', c.id)}
                                            className="inline-flex items-center gap-1 rounded-lg bg-slate-800 px-2.5 py-1 text-xs font-semibold text-indigo-300 hover:bg-slate-700 hover:text-white"
                                        >
                                            <Eye className="h-3.5 w-3.5" />
                                            <span>Khata Details</span>
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                <div className="p-4 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
                    <div>Showing {customers.data.length} of {customers.total} customers</div>
                    <div className="flex gap-1">
                        {customers.links.map((link, idx) => (
                            <Link
                                key={idx}
                                href={link.url || '#'}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                                className={`px-2.5 py-1 rounded ${
                                    link.active
                                        ? 'bg-emerald-600 text-white font-bold'
                                        : link.url
                                        ? 'text-slate-400 hover:bg-slate-800'
                                        : 'text-slate-600 cursor-not-allowed'
                                }`}
                            />
                        ))}
                    </div>
                </div>
            </div>

            {/* Add Customer Modal */}
            {createModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
                    <div className="w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-800">
                            <h3 className="text-base font-bold text-white flex items-center gap-2">
                                <Users className="h-5 w-5 text-emerald-400" />
                                Register Customer Account
                            </h3>
                            <button onClick={() => setCreateModal(false)} className="rounded p-1 text-slate-400 hover:text-white">
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={submitCreate} className="space-y-4 my-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Customer Code</label>
                                <input
                                    type="text"
                                    value={data.code}
                                    onChange={(e) => setData('code', e.target.value)}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Full Name *</label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g. Haji Muhammad Aslam"
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                    required
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-400 mb-1">Phone Number</label>
                                    <input
                                        type="text"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        placeholder="0300-1234567"
                                        className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-400 mb-1">CNIC (Optional)</label>
                                    <input
                                        type="text"
                                        value={data.cnic}
                                        onChange={(e) => setData('cnic', e.target.value)}
                                        placeholder="35201-XXXXXXX-X"
                                        className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Credit Limit (PKR)</label>
                                <input
                                    type="number"
                                    step="100"
                                    value={data.credit_limit_paisa / 100}
                                    onChange={(e) => setData('credit_limit_paisa', Math.round(Number(e.target.value) * 100))}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Address</label>
                                <input
                                    type="text"
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                    placeholder="e.g. Street 4, Gulberg, Lahore"
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                />
                            </div>

                            <div className="flex gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setCreateModal(false)}
                                    className="flex-1 rounded-xl border border-slate-700 py-2.5 text-xs font-semibold text-slate-300 hover:bg-slate-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex-1 rounded-xl bg-emerald-600 py-2.5 text-xs font-bold text-white hover:bg-emerald-500 shadow"
                                >
                                    Create Account
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
