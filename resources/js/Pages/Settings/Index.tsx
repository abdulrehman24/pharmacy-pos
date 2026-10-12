import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { Settings, Printer, Database, Users, Plus, Download, Save, HardDrive, CheckCircle } from 'lucide-react';

interface Props {
    settings: {
        shop_name: string;
        shop_address: string;
        shop_phone: string;
        currency_symbol: string;
        tax_percentage: number;
        receipt_footer: string;
        expiry_alert_days: number;
        default_paper_width: number;
    };
    printerProfiles: any[];
    users: any[];
    backups: any[];
}

export default function SettingsIndex({ settings, printerProfiles, users, backups }: Props) {
    const [activeTab, setActiveTab] = useState<'general' | 'printers' | 'backups' | 'users'>('general');

    // General Settings Form
    const genForm = useForm({
        shop_name: settings.shop_name,
        shop_address: settings.shop_address,
        shop_phone: settings.shop_phone,
        currency_symbol: settings.currency_symbol,
        tax_percentage: settings.tax_percentage,
        receipt_footer: settings.receipt_footer,
        expiry_alert_days: settings.expiry_alert_days,
        default_paper_width: settings.default_paper_width,
    });

    // Printer Profile Form
    const printerForm = useForm({
        name: 'LAN ESC/POS Counter 1',
        connection_type: 'network',
        host: '192.168.1.200',
        port: 9100,
        columns: 42,
        paper_width: 80,
        has_cutter: true,
        is_default: false,
    });

    // New User Form
    const userForm = useForm({
        name: '',
        email: '',
        password: '',
        role: 'cashier',
        pin_code: '',
        phone: '',
    });

    const submitGeneral = (e: React.FormEvent) => {
        e.preventDefault();
        genForm.post(route('settings.update'));
    };

    const submitPrinter = (e: React.FormEvent) => {
        e.preventDefault();
        printerForm.post(route('settings.printer.store'), {
            onSuccess: () => printerForm.reset(),
        });
    };

    const submitUser = (e: React.FormEvent) => {
        e.preventDefault();
        userForm.post(route('settings.user.store'), {
            onSuccess: () => userForm.reset(),
        });
    };

    const triggerBackup = () => {
        router.post(route('settings.backup.create'));
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                        <Settings className="h-6 w-6 text-emerald-400" />
                        System Settings & Configuration
                    </h1>
                    <p className="text-sm text-slate-400">Shop details, thermal printer profiles, offline database backups, and staff permissions</p>
                </div>
            }
        >
            <Head title="Settings & Hardware" />

            {/* Navigation Tabs */}
            <div className="flex border-b border-slate-800 mb-6 gap-2">
                {[
                    { key: 'general', label: 'Shop Details & Receipt', icon: Settings },
                    { key: 'printers', label: 'Thermal Printers', icon: Printer },
                    { key: 'backups', label: 'Database Backup & Restore', icon: Database },
                    { key: 'users', label: 'Staff Roles & PINs', icon: Users },
                ].map((t) => (
                    <button
                        key={t.key}
                        onClick={() => setActiveTab(t.key as any)}
                        className={`flex items-center gap-2 py-3 px-4 text-xs font-semibold border-b-2 transition ${
                            activeTab === t.key
                                ? 'border-emerald-500 text-emerald-400'
                                : 'border-transparent text-slate-400 hover:text-slate-200'
                        }`}
                    >
                        <t.icon className="h-4 w-4" />
                        <span>{t.label}</span>
                    </button>
                ))}
            </div>

            {/* Tab 1: General Settings */}
            {activeTab === 'general' && (
                <div className="max-w-3xl rounded-2xl border border-slate-800 bg-slate-900/60 p-6 space-y-4">
                    <form onSubmit={submitGeneral} className="space-y-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Pharmacy Trade Name *</label>
                                <input
                                    type="text"
                                    value={genForm.data.shop_name}
                                    onChange={(e) => genForm.setData('shop_name', e.target.value)}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Contact Phone Numbers *</label>
                                <input
                                    type="text"
                                    value={genForm.data.shop_phone}
                                    onChange={(e) => genForm.setData('shop_phone', e.target.value)}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                    required
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-400 mb-1">Physical Address *</label>
                            <input
                                type="text"
                                value={genForm.data.shop_address}
                                onChange={(e) => genForm.setData('shop_address', e.target.value)}
                                className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                required
                            />
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Currency Code</label>
                                <input
                                    type="text"
                                    value={genForm.data.currency_symbol}
                                    onChange={(e) => genForm.setData('currency_symbol', e.target.value)}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 font-bold"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Default Sales Tax (%)</label>
                                <input
                                    type="number"
                                    min="0"
                                    max="100"
                                    value={genForm.data.tax_percentage}
                                    onChange={(e) => genForm.setData('tax_percentage', Number(e.target.value))}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-400 mb-1">Default Receipt Width</label>
                                <select
                                    value={genForm.data.default_paper_width}
                                    onChange={(e) => genForm.setData('default_paper_width', Number(e.target.value))}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                >
                                    <option value={80}>80 mm Thermal Roll (Standard)</option>
                                    <option value={58}>58 mm Thermal Roll (Compact)</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-400 mb-1">
                                Expiry Warning Threshold (Days)
                            </label>
                            <input
                                type="number"
                                min="1"
                                max="365"
                                value={genForm.data.expiry_alert_days}
                                onChange={(e) => genForm.setData('expiry_alert_days', Number(e.target.value))}
                                className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-400 mb-1">
                                Receipt Footer Notice & Return Policy
                            </label>
                            <textarea
                                value={genForm.data.receipt_footer}
                                onChange={(e) => genForm.setData('receipt_footer', e.target.value)}
                                className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                rows={3}
                            />
                        </div>

                        <div className="pt-2">
                            <button
                                type="submit"
                                disabled={genForm.processing}
                                className="flex items-center gap-2 rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-emerald-500 shadow"
                            >
                                <Save className="h-4 w-4" />
                                <span>Save Settings</span>
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {/* Tab 2: Thermal Printers */}
            {activeTab === 'printers' && (
                <div className="space-y-6">
                    <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-6">
                        <h2 className="text-base font-semibold text-white mb-4">Configured Printer Profiles</h2>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            {printerProfiles.map((p) => (
                                <div key={p.id} className="rounded-xl border border-slate-800 bg-slate-950 p-4 space-y-2">
                                    <div className="flex justify-between items-start">
                                        <div>
                                            <div className="font-bold text-slate-100 text-sm flex items-center gap-2">
                                                <span>{p.name}</span>
                                                {p.is_default && (
                                                    <span className="rounded bg-emerald-500/20 text-emerald-400 px-1.5 py-0.5 text-[10px] font-bold">
                                                        DEFAULT
                                                    </span>
                                                )}
                                            </div>
                                            <div className="text-xs text-slate-400 capitalize">
                                                Type: {p.connection_type} {p.host ? `(${p.host}:${p.port})` : ''}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="text-xs text-slate-500">
                                        Paper: {p.paper_width}mm • Columns: {p.columns} • Cutter: {p.has_cutter ? 'Supported' : 'No'}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Add Printer Profile Form */}
                    <div className="max-w-2xl rounded-2xl border border-slate-800 bg-slate-900/60 p-6 space-y-4">
                        <h2 className="text-base font-semibold text-white border-b border-slate-800 pb-2">
                            Add LAN / Thermal Printer Profile
                        </h2>
                        <form onSubmit={submitPrinter} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-400 mb-1">Profile Name *</label>
                                    <input
                                        type="text"
                                        value={printerForm.data.name}
                                        onChange={(e) => printerForm.setData('name', e.target.value)}
                                        className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-400 mb-1">Connection Type</label>
                                    <select
                                        value={printerForm.data.connection_type}
                                        onChange={(e) => printerForm.setData('connection_type', e.target.value)}
                                        className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                    >
                                        <option value="network">LAN Direct Socket (TCP/IP)</option>
                                        <option value="browser">Browser Print Queue (USB / Driver)</option>
                                    </select>
                                </div>
                            </div>

                            {printerForm.data.connection_type === 'network' && (
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-400 mb-1">Printer IP Address</label>
                                        <input
                                            type="text"
                                            value={printerForm.data.host}
                                            onChange={(e) => printerForm.setData('host', e.target.value)}
                                            placeholder="192.168.1.200"
                                            className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 font-mono"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-semibold text-slate-400 mb-1">Port (Default 9100)</label>
                                        <input
                                            type="number"
                                            value={printerForm.data.port}
                                            onChange={(e) => printerForm.setData('port', Number(e.target.value))}
                                            className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 font-mono"
                                        />
                                    </div>
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-400 mb-1">Paper Roll Width</label>
                                    <select
                                        value={printerForm.data.paper_width}
                                        onChange={(e) => printerForm.setData('paper_width', Number(e.target.value))}
                                        className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                    >
                                        <option value={80}>80 mm</option>
                                        <option value={58}>58 mm</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-400 mb-1">Columns / Characters per line</label>
                                    <input
                                        type="number"
                                        value={printerForm.data.columns}
                                        onChange={(e) => printerForm.setData('columns', Number(e.target.value))}
                                        className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                    />
                                </div>
                            </div>

                            <button
                                type="submit"
                                disabled={printerForm.processing}
                                className="rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-emerald-500 shadow"
                            >
                                Add Printer Profile
                            </button>
                        </form>
                    </div>
                </div>
            )}

            {/* Tab 3: Backups & Restore */}
            {activeTab === 'backups' && (
                <div className="space-y-6">
                    <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 flex items-center justify-between">
                        <div>
                            <h2 className="text-base font-semibold text-white flex items-center gap-2">
                                <HardDrive className="h-5 w-5 text-emerald-400" />
                                <span>Local Database Backup Engine</span>
                            </h2>
                            <p className="text-xs text-slate-400 mt-1 max-w-xl">
                                Generates consistent MySQL SQL dump archives containing all medicines, batches, invoices, ledgers, and accounts. Store copies on an external USB device or separate drive.
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={triggerBackup}
                            className="flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-emerald-600/30 hover:bg-emerald-500 transition"
                        >
                            <Database className="h-4 w-4" />
                            <span>Create Instant Backup Now</span>
                        </button>
                    </div>

                    {/* Backups List */}
                    <div className="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden shadow-sm">
                        <div className="p-4 border-b border-slate-800">
                            <h2 className="font-semibold text-white">Backup Archives History</h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="bg-slate-900 border-b border-slate-800 text-slate-400 font-semibold uppercase">
                                    <tr>
                                        <th className="py-3 px-4">Backup File</th>
                                        <th className="py-3 px-4">Timestamp</th>
                                        <th className="py-3 px-4 text-right">Size</th>
                                        <th className="py-3 px-4 text-center">Status</th>
                                        <th className="py-3 px-4 text-center">Download</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-800/60">
                                    {backups.length === 0 ? (
                                        <tr><td colSpan={5} className="py-6 text-center text-slate-500">No backup runs recorded.</td></tr>
                                    ) : (
                                        backups.map((b) => (
                                            <tr key={b.id}>
                                                <td className="py-3 px-4 font-mono font-medium text-slate-200">{b.file_name}</td>
                                                <td className="py-3 px-4 text-slate-400">
                                                    {new Date(b.created_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' })}
                                                </td>
                                                <td className="py-3 px-4 text-right font-mono text-slate-300">
                                                    {(b.file_size_bytes / 1024).toFixed(1)} KB
                                                </td>
                                                <td className="py-3 px-4 text-center">
                                                    <span className={`inline-block px-2 py-0.5 rounded text-[11px] font-semibold capitalize ${
                                                        b.status === 'completed'
                                                            ? 'bg-emerald-500/20 text-emerald-400'
                                                            : 'bg-rose-500/20 text-rose-400'
                                                    }`}>
                                                        {b.status}
                                                    </span>
                                                </td>
                                                <td className="py-3 px-4 text-center">
                                                    {b.status === 'completed' && (
                                                        <a
                                                            href={route('settings.backup.download', b.id)}
                                                            className="inline-flex items-center gap-1 text-emerald-400 hover:underline"
                                                        >
                                                            <Download className="h-3.5 w-3.5" />
                                                            <span>Download</span>
                                                        </a>
                                                    )}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            )}

            {/* Tab 4: Staff Users & Permissions */}
            {activeTab === 'users' && (
                <div className="space-y-6">
                    <div className="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden shadow-sm">
                        <div className="p-4 border-b border-slate-800">
                            <h2 className="font-semibold text-white">Registered Staff & Operators</h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="bg-slate-900 border-b border-slate-800 text-slate-400 font-semibold uppercase">
                                    <tr>
                                        <th className="py-3 px-4">Staff Name</th>
                                        <th className="py-3 px-4">Email</th>
                                        <th className="py-3 px-4">Role</th>
                                        <th className="py-3 px-4">PIN Code</th>
                                        <th className="py-3 px-4">Phone</th>
                                        <th className="py-3 px-4 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-800/60">
                                    {users.map((u) => (
                                        <tr key={u.id}>
                                            <td className="py-3 px-4 font-bold text-slate-100 text-sm">{u.name}</td>
                                            <td className="py-3 px-4 text-slate-300">{u.email}</td>
                                            <td className="py-3 px-4">
                                                <span className="capitalize rounded bg-slate-800 px-2 py-0.5 text-emerald-400 font-medium">
                                                    {u.role}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4 font-mono text-slate-400">{u.pin_code || '—'}</td>
                                            <td className="py-3 px-4 text-slate-300">{u.phone || '—'}</td>
                                            <td className="py-3 px-4 text-center">
                                                <span className="text-emerald-400 font-semibold text-[11px]">Active</span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Add Staff Form */}
                    <div className="max-w-2xl rounded-2xl border border-slate-800 bg-slate-900/60 p-6 space-y-4">
                        <h2 className="text-base font-semibold text-white border-b border-slate-800 pb-2">
                            Register New Staff Account
                        </h2>
                        <form onSubmit={submitUser} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-400 mb-1">Full Name *</label>
                                    <input
                                        type="text"
                                        value={userForm.data.name}
                                        onChange={(e) => userForm.setData('name', e.target.value)}
                                        className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-400 mb-1">Email / Username *</label>
                                    <input
                                        type="email"
                                        value={userForm.data.email}
                                        onChange={(e) => userForm.setData('email', e.target.value)}
                                        className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                        required
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-3 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-400 mb-1">Role *</label>
                                    <select
                                        value={userForm.data.role}
                                        onChange={(e) => userForm.setData('role', e.target.value)}
                                        className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                    >
                                        <option value="admin">Admin / Owner</option>
                                        <option value="manager">Manager</option>
                                        <option value="pharmacist">Pharmacist</option>
                                        <option value="cashier">Cashier</option>
                                        <option value="inventory">Inventory Operator</option>
                                        <option value="accounts">Accounts Operator</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-400 mb-1">Password *</label>
                                    <input
                                        type="password"
                                        value={userForm.data.password}
                                        onChange={(e) => userForm.setData('password', e.target.value)}
                                        className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-400 mb-1">Quick PIN (4-Digits)</label>
                                    <input
                                        type="text"
                                        value={userForm.data.pin_code}
                                        onChange={(e) => userForm.setData('pin_code', e.target.value)}
                                        maxLength={10}
                                        placeholder="e.g. 1234"
                                        className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 font-mono"
                                    />
                                </div>
                            </div>

                            <button
                                type="submit"
                                disabled={userForm.processing}
                                className="rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-emerald-500 shadow"
                            >
                                Register Staff Member
                            </button>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
