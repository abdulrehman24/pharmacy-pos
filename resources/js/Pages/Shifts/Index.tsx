import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { Clock, DollarSign, Plus, ArrowDown, ArrowUp, CheckCircle, AlertTriangle, X } from 'lucide-react';

interface Props {
    activeShift: any;
    expectedCashPaisa: number;
    pastShifts: {
        data: any[];
        links: any[];
        total: number;
    };
    counters: any[];
}

function pkr(paisa: number): string {
    return 'PKR ' + (paisa / 100).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function ShiftsIndex({ activeShift, expectedCashPaisa, pastShifts, counters }: Props) {
    const [openShiftModal, setOpenShiftModal] = useState(false);
    const [closeShiftModal, setCloseShiftModal] = useState(false);
    const [cashMoveModal, setCashMoveModal] = useState<'cash_in' | 'cash_out' | null>(null);

    // Form to open shift
    const openForm = useForm({
        counter_id: counters[0]?.id || 1,
        opening_float_paisa: 500000, // PKR 5,000 float
        notes: '',
    });

    // Form to close shift
    const closeForm = useForm({
        counted_cash_paisa: expectedCashPaisa,
        notes: '',
    });

    // Form for cash in/out movement
    const moveForm = useForm({
        type: 'cash_in',
        amount_paisa: 100000,
        reason: '',
    });

    const submitOpen = (e: React.FormEvent) => {
        e.preventDefault();
        openForm.post(route('shifts.open'), {
            onSuccess: () => setOpenShiftModal(false),
        });
    };

    const submitClose = (e: React.FormEvent) => {
        e.preventDefault();
        closeForm.post(route('shifts.close', activeShift.id), {
            onSuccess: () => setCloseShiftModal(false),
        });
    };

    const submitCashMove = (e: React.FormEvent) => {
        e.preventDefault();
        moveForm.post(route('shifts.cash-movement', activeShift.id), {
            onSuccess: () => setCashMoveModal(null),
        });
    };

    const countedPaisa = closeForm.data.counted_cash_paisa;
    const currentVariancePaisa = countedPaisa - expectedCashPaisa;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 w-full">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                            <Clock className="h-6 w-6 text-emerald-400" />
                            Cashier Shifts & Drawer Balancing
                        </h1>
                        <p className="text-sm text-slate-400">Reconcile opening float, counter sales, drawer movements and closing variance</p>
                    </div>

                    {!activeShift ? (
                        <button
                            onClick={() => setOpenShiftModal(true)}
                            className="flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-emerald-600/30 hover:bg-emerald-500 transition"
                        >
                            <Plus className="h-4 w-4" />
                            <span>Open New Shift</span>
                        </button>
                    ) : (
                        <div className="flex gap-2">
                            <button
                                onClick={() => {
                                    moveForm.setData('type', 'cash_in');
                                    setCashMoveModal('cash_in');
                                }}
                                className="flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs font-semibold text-emerald-300 hover:bg-slate-700"
                            >
                                <ArrowDown className="h-3.5 w-3.5" />
                                <span>Cash In</span>
                            </button>
                            <button
                                onClick={() => {
                                    moveForm.setData('type', 'cash_out');
                                    setCashMoveModal('cash_out');
                                }}
                                className="flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs font-semibold text-rose-300 hover:bg-slate-700"
                            >
                                <ArrowUp className="h-3.5 w-3.5" />
                                <span>Cash Out</span>
                            </button>
                            <button
                                onClick={() => {
                                    closeForm.setData('counted_cash_paisa', expectedCashPaisa);
                                    setCloseShiftModal(true);
                                }}
                                className="flex items-center gap-1.5 rounded-xl bg-amber-600 px-4 py-2 text-xs font-bold text-white hover:bg-amber-500 shadow"
                            >
                                <span>Close Shift & Reconcile</span>
                            </button>
                        </div>
                    )}
                </div>
            }
        >
            <Head title="Cashier Shifts" />

            {/* Active Shift Overview Card */}
            {activeShift ? (
                <div className="mb-6 rounded-2xl border border-emerald-500/30 bg-slate-900/80 p-6 shadow-xl">
                    <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-slate-800 pb-4 mb-4">
                        <div>
                            <span className="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 mb-1">
                                ACTIVE OPEN SHIFT
                            </span>
                            <h2 className="text-xl font-bold text-white">
                                {activeShift.counter?.name || 'Counter 1'} • Cashier: {activeShift.user?.name}
                            </h2>
                            <div className="text-xs text-slate-400">
                                Opened: {new Date(activeShift.opened_at).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' })}
                            </div>
                        </div>

                        <div className="mt-4 sm:mt-0 text-left sm:text-right">
                            <div className="text-xs text-slate-400">Authoritative Expected Cash Drawer</div>
                            <div className="text-3xl font-black text-emerald-400 mt-0.5">{pkr(expectedCashPaisa)}</div>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                        <div className="rounded-xl border border-slate-800 bg-slate-950 p-3">
                            <span className="text-slate-400">Opening Float</span>
                            <div className="text-base font-bold text-slate-200 mt-1">{pkr(activeShift.opening_float_paisa)}</div>
                        </div>
                        <div className="rounded-xl border border-slate-800 bg-slate-950 p-3">
                            <span className="text-slate-400">Drawer Cash Movements</span>
                            <div className="text-base font-bold text-slate-200 mt-1">
                                {activeShift.cash_movements?.length || 0} entries
                            </div>
                        </div>
                        <div className="rounded-xl border border-slate-800 bg-slate-950 p-3">
                            <span className="text-slate-400">Operating Expenses</span>
                            <div className="text-base font-bold text-slate-200 mt-1">
                                {pkr(activeShift.expenses?.reduce((sum: number, e: any) => sum + e.amount_paisa, 0) || 0)}
                            </div>
                        </div>
                        <div className="rounded-xl border border-slate-800 bg-slate-950 p-3">
                            <span className="text-slate-400">Counter IP / Address</span>
                            <div className="text-base font-mono font-medium text-slate-300 mt-1">
                                {activeShift.counter?.ip_address || '127.0.0.1'}
                            </div>
                        </div>
                    </div>
                </div>
            ) : (
                <div className="mb-6 rounded-2xl border border-slate-800 bg-slate-900/40 p-8 text-center text-slate-400">
                    <Clock className="h-10 w-10 text-slate-600 mx-auto mb-2" />
                    <div className="text-base font-semibold text-slate-200">No shift currently active for this counter</div>
                    <p className="text-xs text-slate-500 max-w-md mx-auto mt-1">
                        Open a cashier shift and provide the drawer starting float to begin issuing sales receipts.
                    </p>
                </div>
            )}

            {/* Shift History Table */}
            <div className="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden shadow-sm">
                <div className="p-4 border-b border-slate-800">
                    <h2 className="font-semibold text-white">Closed Shifts Audit Register</h2>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-slate-900 border-b border-slate-800 text-slate-400 font-semibold uppercase">
                            <tr>
                                <th className="py-3 px-4">Counter</th>
                                <th className="py-3 px-4">Cashier</th>
                                <th className="py-3 px-4">Opened At</th>
                                <th className="py-3 px-4">Closed At</th>
                                <th className="py-3 px-4 text-right">Float</th>
                                <th className="py-3 px-4 text-right">Expected</th>
                                <th className="py-3 px-4 text-right">Counted</th>
                                <th className="py-3 px-4 text-right">Variance</th>
                                <th className="py-3 px-4">Notes</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800/60">
                            {pastShifts.data.map((s) => (
                                <tr key={s.id} className="hover:bg-slate-800/30">
                                    <td className="py-3 px-4 font-bold text-slate-200">{s.counter?.name}</td>
                                    <td className="py-3 px-4 text-slate-300">{s.user?.name}</td>
                                    <td className="py-3 px-4 text-slate-400">
                                        {new Date(s.opened_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' })}
                                    </td>
                                    <td className="py-3 px-4 text-slate-400">
                                        {s.closed_at ? new Date(s.closed_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' }) : 'Open'}
                                    </td>
                                    <td className="py-3 px-4 text-right text-slate-300">{pkr(s.opening_float_paisa)}</td>
                                    <td className="py-3 px-4 text-right font-medium text-slate-200">{pkr(s.expected_cash_paisa || 0)}</td>
                                    <td className="py-3 px-4 text-right font-bold text-slate-100">{pkr(s.counted_cash_paisa || 0)}</td>
                                    <td className="py-3 px-4 text-right font-bold">
                                        <span className={s.variance_paisa === 0 ? 'text-slate-400' : s.variance_paisa > 0 ? 'text-emerald-400' : 'text-rose-400'}>
                                            {s.variance_paisa != null ? (s.variance_paisa > 0 ? `+${pkr(s.variance_paisa)}` : pkr(s.variance_paisa)) : '—'}
                                        </span>
                                    </td>
                                    <td className="py-3 px-4 text-slate-400 text-[11px]">{s.notes || '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Modal: Open Shift */}
            {openShiftModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
                    <div className="w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-800">
                            <h3 className="text-base font-bold text-white flex items-center gap-2">
                                <Clock className="h-5 w-5 text-emerald-400" />
                                Open Counter Shift
                            </h3>
                            <button onClick={() => setOpenShiftModal(false)} className="rounded p-1 text-slate-400 hover:text-white">
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={submitOpen} className="space-y-4 my-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Select Counter *</label>
                                <select
                                    value={openForm.data.counter_id}
                                    onChange={(e) => openForm.setData('counter_id', Number(e.target.value))}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                >
                                    {counters.map((c) => (
                                        <option key={c.id} value={c.id}>{c.name} ({c.code})</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Opening Cash Float (PKR) *</label>
                                <input
                                    type="number"
                                    min="0"
                                    step="100"
                                    value={openForm.data.opening_float_paisa / 100}
                                    onChange={(e) => openForm.setData('opening_float_paisa', Math.round(Number(e.target.value) * 100))}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 font-bold"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Notes</label>
                                <input
                                    type="text"
                                    value={openForm.data.notes}
                                    onChange={(e) => openForm.setData('notes', e.target.value)}
                                    placeholder="e.g. Morning shift start..."
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                />
                            </div>

                            <div className="flex gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setOpenShiftModal(false)}
                                    className="flex-1 rounded-xl border border-slate-700 py-2.5 text-xs font-semibold text-slate-300 hover:bg-slate-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={openForm.processing}
                                    className="flex-1 rounded-xl bg-emerald-600 py-2.5 text-xs font-bold text-white hover:bg-emerald-500 shadow"
                                >
                                    Confirm Shift Open
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal: Close Shift & Reconcile */}
            {closeShiftModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
                    <div className="w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-800">
                            <div>
                                <h3 className="text-base font-bold text-white flex items-center gap-2">
                                    <CheckCircle className="h-5 w-5 text-amber-400" />
                                    Reconcile & Close Shift
                                </h3>
                                <p className="text-xs text-slate-400">Count physical cash in drawer and record variance</p>
                            </div>
                            <button onClick={() => setCloseShiftModal(false)} className="rounded p-1 text-slate-400 hover:text-white">
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={submitClose} className="space-y-4 my-4">
                            <div className="rounded-xl border border-slate-800 bg-slate-950 p-3 text-xs flex justify-between">
                                <span className="text-slate-400">Expected System Cash:</span>
                                <span className="font-bold text-slate-100">{pkr(expectedCashPaisa)}</span>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">
                                    Physically Counted Drawer Cash (PKR) *
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    step="1"
                                    value={closeForm.data.counted_cash_paisa / 100}
                                    onChange={(e) => closeForm.setData('counted_cash_paisa', Math.round(Number(e.target.value) * 100))}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 font-bold"
                                    required
                                />
                            </div>

                            <div className="rounded-xl border border-slate-800 bg-slate-950 p-3 text-xs flex justify-between items-center">
                                <span className="text-slate-400">Drawer Difference / Variance:</span>
                                <span className={`font-bold text-sm ${currentVariancePaisa === 0 ? 'text-emerald-400' : currentVariancePaisa > 0 ? 'text-teal-400' : 'text-rose-400'}`}>
                                    {currentVariancePaisa > 0 ? `+${pkr(currentVariancePaisa)}` : pkr(currentVariancePaisa)}
                                </span>
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Closing Remarks / Discrepancy Reason</label>
                                <textarea
                                    value={closeForm.data.notes}
                                    onChange={(e) => closeForm.setData('notes', e.target.value)}
                                    placeholder="Explain any overage or shortage..."
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                    rows={2}
                                />
                            </div>

                            <div className="flex gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setCloseShiftModal(false)}
                                    className="flex-1 rounded-xl border border-slate-700 py-2.5 text-xs font-semibold text-slate-300 hover:bg-slate-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={closeForm.processing}
                                    className="flex-1 rounded-xl bg-amber-600 py-2.5 text-xs font-bold text-white hover:bg-amber-500 shadow"
                                >
                                    Close & Lock Shift
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal: Cash In / Out */}
            {cashMoveModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
                    <div className="w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-800">
                            <h3 className="text-base font-bold text-white capitalize">
                                Record {cashMoveModal.replace('_', ' ')}
                            </h3>
                            <button onClick={() => setCashMoveModal(null)} className="rounded p-1 text-slate-400 hover:text-white">
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <form onSubmit={submitCashMove} className="space-y-4 my-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Amount (PKR) *</label>
                                <input
                                    type="number"
                                    min="1"
                                    step="1"
                                    value={moveForm.data.amount_paisa / 100}
                                    onChange={(e) => moveForm.setData('amount_paisa', Math.round(Number(e.target.value) * 100))}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 font-bold"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Reason / Purpose *</label>
                                <input
                                    type="text"
                                    value={moveForm.data.reason}
                                    onChange={(e) => moveForm.setData('reason', e.target.value)}
                                    placeholder={cashMoveModal === 'cash_in' ? 'e.g. Added change float' : 'e.g. Bank deposit'}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                    required
                                />
                            </div>

                            <div className="flex gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setCashMoveModal(null)}
                                    className="flex-1 rounded-xl border border-slate-700 py-2.5 text-xs font-semibold text-slate-300 hover:bg-slate-800"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={moveForm.processing}
                                    className="flex-1 rounded-xl bg-emerald-600 py-2.5 text-xs font-bold text-white hover:bg-emerald-500 shadow"
                                >
                                    Post Movement
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
