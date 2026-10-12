import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState, useEffect } from 'react';
import {
    LayoutDashboard,
    ShoppingCart,
    Pill,
    Truck,
    Boxes,
    FileSpreadsheet,
    RotateCcw,
    Users,
    Building2,
    Clock,
    ReceiptText,
    FileSignature,
    BarChart3,
    Settings,
    LogOut,
    Menu,
    X,
    WifiOff,
    CheckCircle2,
    AlertCircle,
    Keyboard,
    Activity,
    UserCheck,
    KeyRound,
    User,
} from 'lucide-react';
import { PageProps } from '@/types';

interface NavItem {
    name: string;
    href: string;
    icon: any;
    highlight?: boolean;
    roles?: string[];
}

interface NavGroup {
    title: string;
    items: NavItem[];
}

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const page = usePage<PageProps>();
    const user = page.props.auth.user;
    const flash = page.props.flash;
    const shop = (page.props as any).shop || { name: 'Al-Shifa Pharmacy', currency: 'PKR' };

    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [hotkeysOpen, setHotkeysOpen] = useState(false);
    const [timeStr, setTimeStr] = useState('');
    const [showFlash, setShowFlash] = useState(true);

    // Live Clock in PKT (Asia/Karachi)
    useEffect(() => {
        const updateTime = () => {
            const now = new Date();
            setTimeStr(
                now.toLocaleTimeString('en-PK', {
                    timeZone: 'Asia/Karachi',
                    hour12: true,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                })
            );
        };
        updateTime();
        const timer = setInterval(updateTime, 1000);
        return () => clearInterval(timer);
    }, []);

    // Dismiss flash toast after 6 seconds
    useEffect(() => {
        if (flash?.success || flash?.error) {
            setShowFlash(true);
            const timer = setTimeout(() => setShowFlash(false), 6000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    // Keyboard shortcut for Hotkeys Help: '?'
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === '?' && !['INPUT', 'TEXTAREA'].includes((e.target as HTMLElement)?.tagName)) {
                e.preventDefault();
                setHotkeysOpen((prev) => !prev);
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    const userRole = user?.role || 'cashier';
    const isAdmin = userRole === 'admin';

    const navGroups: NavGroup[] = [
        {
            title: 'Operations',
            items: [
                { name: 'POS Counter (F1)', href: route('pos.index'), icon: ShoppingCart, highlight: true },
                { name: 'Dashboard', href: route('dashboard'), icon: LayoutDashboard },
                { name: 'Cashier Shifts', href: route('shifts.index'), icon: Clock },
                { name: 'Returns & Losses', href: route('returns.index'), icon: RotateCcw },
            ],
        },
        {
            title: 'Inventory & Catalogue',
            items: [
                { name: 'Medicines', href: route('medicines.index'), icon: Pill },
                { name: 'Purchases (Receiving)', href: route('purchases.index'), icon: Truck, roles: ['admin', 'manager', 'pharmacist', 'inventory'] },
                { name: 'Batches & Stock Lots', href: route('inventory.index'), icon: Boxes },
                { name: 'Stock Movement Ledger', href: route('inventory.ledger'), icon: FileSpreadsheet, roles: ['admin', 'manager', 'pharmacist', 'inventory'] },
                { name: 'Physical Stock Audits', href: route('inventory.counts'), icon: Activity, roles: ['admin', 'manager', 'pharmacist'] },
            ],
        },
        {
            title: 'Accounts & Ledgers',
            items: [
                { name: 'Customers (Khata)', href: route('customers.index'), icon: Users },
                { name: 'Suppliers Ledger', href: route('suppliers.index'), icon: Building2, roles: ['admin', 'manager', 'pharmacist', 'accounts'] },
                { name: 'Operating Expenses', href: route('expenses.index'), icon: ReceiptText, roles: ['admin', 'manager', 'cashier', 'accounts'] },
            ],
        },
        {
            title: 'Administration',
            items: [
                { name: 'Prescriptions (Rx)', href: route('prescriptions.index'), icon: FileSignature },
                { name: 'Reports & Margins', href: route('reports.index'), icon: BarChart3, roles: ['admin', 'manager'] },
                { name: 'Settings & Backups', href: route('settings.index'), icon: Settings, roles: ['admin'] },
            ],
        },
        {
            title: 'Account & Security',
            items: [
                { name: 'My Profile & PIN', href: route('profile.edit'), icon: UserCheck },
            ],
        },
    ];

    const hasAccess = (item: NavItem): boolean => {
        if (isAdmin) return true;
        if (!item.roles || item.roles.length === 0) return true;
        return item.roles.includes(userRole);
    };

    const filteredNavGroups = navGroups
        .map((group) => ({
            ...group,
            items: group.items.filter(hasAccess),
        }))
        .filter((group) => group.items.length > 0);

    return (
        <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col antialiased">
            {/* Topbar */}
            <header className="sticky top-0 z-30 flex h-16 w-full items-center justify-between border-b border-slate-800/80 bg-slate-900/95 px-4 backdrop-blur-md sm:px-6 shadow-sm">
                <div className="flex items-center gap-3">
                    <button
                        onClick={() => setSidebarOpen(!sidebarOpen)}
                        className="rounded-xl p-2 text-slate-400 hover:bg-slate-800 hover:text-white md:hidden transition"
                        title="Toggle Navigation"
                    >
                        {sidebarOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                    </button>

                    <Link href="/dashboard" className="flex items-center gap-2.5 group">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-700 shadow-lg shadow-emerald-500/25 text-white font-bold text-xl group-hover:scale-105 transition-transform">
                            +
                        </div>
                        <div>
                            <div className="font-bold tracking-tight text-white flex items-center gap-2 text-sm sm:text-base">
                                {shop.name}
                                <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[11px] font-semibold text-emerald-400 border border-emerald-500/20">
                                    <WifiOff className="h-3 w-3" /> Offline LAN
                                </span>
                            </div>
                            <div className="text-[11px] text-slate-400 flex items-center gap-2">
                                <span>Pakistan Pharmacy POS</span>
                                <span>•</span>
                                <span className="font-mono text-emerald-400 font-semibold">{timeStr || 'PKT'}</span>
                            </div>
                        </div>
                    </Link>
                </div>

                <div className="flex items-center gap-2 sm:gap-3">
                    {/* Hotkeys Help Button */}
                    <button
                        type="button"
                        onClick={() => setHotkeysOpen(true)}
                        className="hidden md:flex items-center gap-1.5 rounded-xl border border-slate-800 bg-slate-950 px-2.5 py-1.5 text-xs text-slate-400 hover:text-slate-200 hover:border-slate-700 transition"
                        title="Keyboard Shortcuts (Press ?)"
                    >
                        <Keyboard className="h-3.5 w-3.5 text-slate-400" />
                        <span>Shortcuts</span>
                        <kbd className="rounded bg-slate-800 px-1 py-0.2 text-[10px] text-slate-400 font-mono">?</kbd>
                    </button>

                    {/* Instant POS Button */}
                    <Link
                        href={route('pos.index')}
                        className="flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 px-3.5 py-2 text-xs sm:text-sm font-bold text-white shadow-lg shadow-emerald-600/30 hover:from-emerald-500 hover:to-teal-500 transition active:scale-95"
                    >
                        <ShoppingCart className="h-4 w-4" />
                        <span>Billing Counter</span>
                        <kbd className="hidden lg:inline-block rounded bg-emerald-800/80 px-1.5 py-0.5 text-[10px] font-mono">F1</kbd>
                    </Link>

                    {/* User Profile & Settings */}
                    <div className="flex items-center gap-1.5 sm:gap-2 border-l border-slate-800/80 pl-2 sm:pl-3">
                        <Link
                            href={route('profile.edit')}
                            className="flex items-center gap-2 px-2 py-1 rounded-xl hover:bg-slate-800/60 transition group text-right"
                            title="My Profile & Security Settings"
                        >
                            <div className="h-8 w-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-xs group-hover:border-emerald-500/50 group-hover:scale-105 transition">
                                {user.name ? user.name.slice(0, 2).toUpperCase() : 'U'}
                            </div>
                            <div className="hidden sm:block">
                                <div className="text-xs font-semibold text-slate-200 group-hover:text-white transition">
                                    {user.name}
                                </div>
                                <div className="text-[10px] font-bold uppercase tracking-wider text-emerald-400">{user.role}</div>
                            </div>
                        </Link>

                        <Link
                            href={route('profile.edit')}
                            className="rounded-xl p-2 text-slate-400 hover:bg-slate-800 hover:text-emerald-400 transition"
                            title="User Settings (PIN, Password, Profile)"
                        >
                            <Settings className="h-4 w-4" />
                        </Link>

                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="rounded-xl p-2 text-slate-400 hover:bg-rose-500/10 hover:text-rose-400 transition"
                            title="Log Out"
                        >
                            <LogOut className="h-4 w-4" />
                        </Link>
                    </div>
                </div>
            </header>

            {/* Floating Toast Notification */}
            {showFlash && (flash?.success || flash?.error) && (
                <div className="fixed bottom-5 right-5 z-50 max-w-md animate-in slide-in-from-bottom-5 fade-in duration-200">
                    {flash.success && (
                        <div className="flex items-center gap-3 rounded-2xl border border-emerald-500/30 bg-slate-900/95 p-4 text-sm text-emerald-300 shadow-2xl backdrop-blur-md glow-emerald">
                            <CheckCircle2 className="h-5 w-5 text-emerald-400 shrink-0" />
                            <span className="flex-1 font-medium">{flash.success}</span>
                            <button
                                onClick={() => setShowFlash(false)}
                                className="text-slate-400 hover:text-white p-1 rounded-lg"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                    )}
                    {flash.error && (
                        <div className="flex items-center gap-3 rounded-2xl border border-rose-500/30 bg-slate-900/95 p-4 text-sm text-rose-300 shadow-2xl backdrop-blur-md glow-rose">
                            <AlertCircle className="h-5 w-5 text-rose-400 shrink-0" />
                            <span className="flex-1 font-medium">{flash.error}</span>
                            <button
                                onClick={() => setShowFlash(false)}
                                className="text-slate-400 hover:text-white p-1 rounded-lg"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                    )}
                </div>
            )}

            <div className="flex flex-1 overflow-hidden">
                {/* Sidebar Navigation */}
                <aside
                    className={`fixed inset-y-0 left-0 z-20 w-64 transform border-r border-slate-800/80 bg-slate-900/95 backdrop-blur-md pt-16 transition-transform duration-200 ease-in-out md:static md:translate-x-0 md:pt-0 ${
                        sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                    }`}
                >
                    <div className="flex h-full flex-col justify-between overflow-y-auto px-3 py-4 space-y-4">
                        <nav className="space-y-4">
                            {filteredNavGroups.map((group, gIdx) => (
                                <div key={gIdx} className="space-y-1">
                                    <div className="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                        {group.title}
                                    </div>
                                    {group.items.map((item) => {
                                        const Icon = item.icon;
                                        const isActive = window.location.pathname === new URL(item.href, window.location.origin).pathname;
                                        return (
                                            <Link
                                                key={item.name}
                                                href={item.href}
                                                onClick={() => setSidebarOpen(false)}
                                                className={`flex items-center gap-3 rounded-xl px-3 py-2 text-xs font-semibold transition group ${
                                                    item.highlight
                                                        ? 'bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 border border-emerald-500/20 shadow-sm'
                                                        : isActive
                                                        ? 'bg-slate-800 text-white shadow-sm border-l-2 border-emerald-400'
                                                        : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-200'
                                                }`}
                                            >
                                                <Icon
                                                    className={`h-4 w-4 shrink-0 transition-transform group-hover:scale-110 ${
                                                        item.highlight
                                                            ? 'text-emerald-400'
                                                            : isActive
                                                            ? 'text-emerald-400'
                                                            : 'text-slate-400'
                                                    }`}
                                                />
                                                <span className="truncate">{item.name}</span>
                                            </Link>
                                        );
                                    })}
                                </div>
                            ))}
                        </nav>

                        <div className="rounded-2xl border border-slate-800/80 bg-slate-950/70 p-3 text-[11px] text-slate-500 space-y-1">
                            <div className="flex justify-between">
                                <span>Currency:</span>
                                <span className="font-semibold text-slate-300">{shop.currency} (PKR)</span>
                            </div>
                            <div className="flex justify-between">
                                <span>Local Port:</span>
                                <span className="font-mono text-emerald-400 font-semibold">:8080</span>
                            </div>
                            <div className="flex justify-between">
                                <span>DB Engine:</span>
                                <span className="text-slate-400 font-medium">MySQL InnoDB</span>
                            </div>
                        </div>
                    </div>
                </aside>

                {/* Main Content Area */}
                <main className="flex-1 overflow-y-auto bg-slate-950 p-4 sm:p-6 lg:p-8">
                    {header && (
                        <div className="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            {header}
                        </div>
                    )}
                    {children}
                </main>
            </div>

            {/* Hotkeys Modal */}
            {hotkeysOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4">
                    <div className="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
                        <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                            <div className="flex items-center gap-2">
                                <Keyboard className="h-5 w-5 text-emerald-400" />
                                <h3 className="text-base font-bold text-white">POS Keyboard Shortcuts</h3>
                            </div>
                            <button
                                onClick={() => setHotkeysOpen(false)}
                                className="text-slate-400 hover:text-white p-1 rounded-lg"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <div className="space-y-2.5 text-xs">
                            <div className="flex items-center justify-between p-2.5 rounded-xl bg-slate-950 border border-slate-800">
                                <span className="text-slate-300 font-medium">Billing Counter Quick Access</span>
                                <kbd className="rounded-lg bg-slate-800 px-2 py-1 font-mono font-bold text-emerald-400">F1</kbd>
                            </div>
                            <div className="flex items-center justify-between p-2.5 rounded-xl bg-slate-950 border border-slate-800">
                                <span className="text-slate-300 font-medium">Focus Barcode / Medicine Search</span>
                                <kbd className="rounded-lg bg-slate-800 px-2 py-1 font-mono font-bold text-emerald-400">F2</kbd>
                            </div>
                            <div className="flex items-center justify-between p-2.5 rounded-xl bg-slate-950 border border-slate-800">
                                <span className="text-slate-300 font-medium">Hold Current Bill (Draft)</span>
                                <kbd className="rounded-lg bg-slate-800 px-2 py-1 font-mono font-bold text-amber-400">F8</kbd>
                            </div>
                            <div className="flex items-center justify-between p-2.5 rounded-xl bg-slate-950 border border-slate-800">
                                <span className="text-slate-300 font-medium">Open Tender / Payment Modal</span>
                                <kbd className="rounded-lg bg-slate-800 px-2 py-1 font-mono font-bold text-emerald-400">F9</kbd>
                            </div>
                            <div className="flex items-center justify-between p-2.5 rounded-xl bg-slate-950 border border-slate-800">
                                <span className="text-slate-300 font-medium">Clear / Cancel Active Bill</span>
                                <kbd className="rounded-lg bg-slate-800 px-2 py-1 font-mono font-bold text-rose-400">F4</kbd>
                            </div>
                            <div className="flex items-center justify-between p-2.5 rounded-xl bg-slate-950 border border-slate-800">
                                <span className="text-slate-300 font-medium">Close Modal / Drawer</span>
                                <kbd className="rounded-lg bg-slate-800 px-2 py-1 font-mono font-bold text-slate-300">ESC</kbd>
                            </div>
                        </div>

                        <div className="pt-2 text-right">
                            <button
                                onClick={() => setHotkeysOpen(false)}
                                className="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-500 shadow-md transition"
                            >
                                Got it
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
