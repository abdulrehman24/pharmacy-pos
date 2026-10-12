import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ReceiptText, Plus, X } from 'lucide-react';

interface Expense {
    id: number;
    category: string;
    amount_paisa: number;
    description: string;
    receipt_ref?: string;
    created_at: string;
    user: { name: string };
    shift?: { id: number };
}

interface Props {
    expenses: {
        data: Expense[];
        links: any[];
        total: number;
    };
    categories: Record<string, string>;
}

function pkr(paisa: number): string {
    return 'PKR ' + (paisa / 100).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function ExpensesIndex({ expenses, categories }: Props) {
    const [createModal, setCreateModal] = useState(false);

    const { data, setData, post, processing, reset } = useForm({
        category: 'utilities',
        amount_paisa: 50000,
        description: '',
        receipt_ref: '',
    });

    const submitExpense = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('expenses.store'), {
            onSuccess: () => {
                setCreateModal(false);
                reset();
            },
        });
    };

    const totalExpensesPaisa = expenses.data.reduce((sum, e) => sum + e.amount_paisa, 0);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 w-full">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                            <ReceiptText className="h-6 w-6 text-emerald-400" />
                            Operating Expenses & Drawer Payouts
                        </h1>
                        <p className="text-sm text-slate-400">Track counter petty cash payouts, utilities, supplies, and shop overheads</p>
                    </div>
                    <button
                        onClick={() => setCreateModal(true)}
                        className="flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-emerald-600/30 hover:bg-emerald-500 transition"
                    >
                        <Plus className="h-4 w-4" />
                        <span>Record Expense</span>
                    </button>
                </div>
            }
        >
            <Head title="Expenses" />

            {/* Summary Banner */}
            <div className="mb-6 rounded-2xl border border-slate-800 bg-slate-900/60 p-5 flex items-center justify-between">
                <div>
                    <div className="text-xs text-slate-400 font-medium">Page Total Operating Expenses</div>
                    <div className="text-2xl font-black text-rose-400 mt-1">{pkr(totalExpensesPaisa)}</div>
                </div>
                <div className="text-right text-xs text-slate-400">
                    Total recorded entries: <strong className="text-slate-200">{expenses.total}</strong>
                </div>
            </div>

            {/* Expenses Table */}
            <div className="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-slate-900 border-b border-slate-800 text-slate-400 font-semibold uppercase tracking-wider">
                            <tr>
                                <th className="py-3 px-4">Date / Time</th>
                                <th className="py-3 px-4">Category</th>
                                <th className="py-3 px-4">Description</th>
                                <th className="py-3 px-4">Receipt Ref</th>
                                <th className="py-3 px-4">Logged By</th>
                                <th className="py-3 px-4 text-right">Amount (PKR)</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800/60">
                            {expenses.data.map((e) => (
                                <tr key={e.id} className="hover:bg-slate-800/30">
                                    <td className="py-3 px-4 font-mono text-slate-400">
                                        {new Date(e.created_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' })}
                                    </td>
                                    <td className="py-3 px-4">
                                        <span className="rounded bg-slate-800 px-2 py-0.5 text-slate-300 font-medium">
                                            {categories[e.category] || e.category}
                                        </span>
                                    </td>
                                    <td className="py-3 px-4 font-medium text-slate-200">{e.description}</td>
                                    <td className="py-3 px-4 font-mono text-slate-400">{e.receipt_ref || '—'}</td>
                                    <td className="py-3 px-4 text-slate-300">{e.user?.name}</td>
                                    <td className="py-3 px-4 text-right font-bold text-sm text-rose-400">{pkr(e.amount_paisa)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Add Expense Modal */}
            {createModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
                    <div className="w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-800">
                            <h3 className="text-base font-bold text-white flex items-center gap-2">
                                <ReceiptText className="h-5 w-5 text-emerald-400" />
                                Record Operating Expense
                            </h3>
                            <button onClick={() => setCreateModal(false)} className="rounded p-1 text-slate-400 hover:text-white">
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={submitExpense} className="space-y-4 my-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Expense Category *</label>
                                <select
                                    value={data.category}
                                    onChange={(e) => setData('category', e.target.value)}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                >
                                    {Object.entries(categories).map(([k, label]) => (
                                        <option key={k} value={k}>{label}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Amount (PKR) *</label>
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
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Description / Narration *</label>
                                <input
                                    type="text"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="e.g. Electric bill payment, Tea for staff..."
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Receipt / Voucher #</label>
                                <input
                                    type="text"
                                    value={data.receipt_ref}
                                    onChange={(e) => setData('receipt_ref', e.target.value)}
                                    placeholder="e.g. Bill #89012"
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
                                    Save Expense
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
