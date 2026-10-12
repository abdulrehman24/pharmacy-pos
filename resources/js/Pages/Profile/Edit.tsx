import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState, useRef, FormEventHandler } from 'react';
import {
    User,
    Mail,
    Phone,
    KeyRound,
    Lock,
    Shield,
    ShieldCheck,
    Save,
    CheckCircle2,
    AlertCircle,
    Eye,
    EyeOff,
    Trash2,
    Sparkles,
    Clock,
    ArrowLeft,
    Check,
    X,
} from 'lucide-react';
import { PageProps } from '@/types';

interface ProfileUser {
    id: number;
    name: string;
    email: string;
    phone?: string;
    role: string;
    is_active: boolean;
    has_pin: boolean;
    pin_code?: string;
    created_at?: string;
}

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
    profileUser?: ProfileUser;
}

export default function Edit({ mustVerifyEmail, status, profileUser }: Props) {
    const authUser = usePage<PageProps>().props.auth.user;
    const currentUser: ProfileUser = profileUser || {
        id: authUser.id,
        name: authUser.name,
        email: authUser.email,
        phone: authUser.phone,
        role: authUser.role,
        is_active: authUser.is_active,
        has_pin: Boolean(authUser.pin_code),
        pin_code: authUser.pin_code || '',
        created_at: 'Active',
    };

    const [activeTab, setActiveTab] = useState<'profile' | 'pin' | 'password'>('profile');
    const [showPin, setShowPin] = useState(false);
    const [confirmDeleteOpen, setConfirmDeleteOpen] = useState(false);

    // Profile Info Form
    const profileForm = useForm({
        name: currentUser.name,
        email: currentUser.email,
        phone: currentUser.phone || '',
    });

    const submitProfile: FormEventHandler = (e) => {
        e.preventDefault();
        profileForm.patch(route('profile.update'), {
            preserveScroll: true,
        });
    };

    // Quick PIN Form
    const pinForm = useForm({
        pin_code: currentUser.pin_code || '',
    });

    const handlePinDigit = (digit: string) => {
        if (pinForm.data.pin_code.length < 4) {
            pinForm.setData('pin_code', pinForm.data.pin_code + digit);
        }
    };

    const handlePinBackspace = () => {
        pinForm.setData('pin_code', pinForm.data.pin_code.slice(0, -1));
    };

    const handlePinClear = () => {
        pinForm.setData('pin_code', '');
    };

    const submitPin: FormEventHandler = (e) => {
        e.preventDefault();
        pinForm.put(route('profile.pin.update'), {
            preserveScroll: true,
        });
    };

    const removePin = () => {
        if (confirm('Are you sure you want to remove your Quick 4-Digit PIN?')) {
            pinForm.setData('pin_code', '');
            pinForm.put(route('profile.pin.update'), {
                preserveScroll: true,
            });
        }
    };

    // Password Update Form
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const newPasswordInput = useRef<HTMLInputElement>(null);

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submitPassword: FormEventHandler = (e) => {
        e.preventDefault();
        passwordForm.put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => passwordForm.reset(),
            onError: () => {
                if (passwordForm.errors.password) {
                    passwordForm.reset('password', 'password_confirmation');
                    newPasswordInput.current?.focus();
                }
                if (passwordForm.errors.current_password) {
                    passwordForm.reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    // Delete Account Form
    const deleteForm = useForm({
        password: '',
    });

    const submitDelete: FormEventHandler = (e) => {
        e.preventDefault();
        deleteForm.delete(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => setConfirmDeleteOpen(false),
            onFinish: () => deleteForm.reset(),
        });
    };

    const getRoleColor = (role: string) => {
        switch (role) {
            case 'admin':
                return 'bg-purple-500/10 text-purple-400 border-purple-500/30';
            case 'manager':
                return 'bg-sky-500/10 text-sky-400 border-sky-500/30';
            case 'pharmacist':
                return 'bg-teal-500/10 text-teal-400 border-teal-500/30';
            default:
                return 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30';
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="User Settings & Profile" />

            <div className="max-w-5xl mx-auto space-y-6">
                {/* Header & Overview Banner */}
                <div className="rounded-3xl border border-slate-800 bg-slate-900/95 p-6 shadow-xl backdrop-blur-xl flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div className="flex items-center gap-4">
                        <div className="h-16 w-16 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 p-0.5 shadow-lg shadow-emerald-500/20">
                            <div className="h-full w-full rounded-2xl bg-slate-950 flex items-center justify-center font-bold text-2xl text-emerald-400">
                                {currentUser.name.slice(0, 2).toUpperCase()}
                            </div>
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl sm:text-2xl font-bold text-white tracking-tight">
                                    {currentUser.name}
                                </h1>
                                <span className={`text-[11px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full border ${getRoleColor(currentUser.role)}`}>
                                    {currentUser.role}
                                </span>
                            </div>
                            <div className="text-xs text-slate-400 flex flex-wrap items-center gap-3 mt-1">
                                <span>{currentUser.email}</span>
                                {currentUser.phone && (
                                    <>
                                        <span>•</span>
                                        <span>{currentUser.phone}</span>
                                    </>
                                )}
                                <span>•</span>
                                <span className="flex items-center gap-1 text-emerald-400">
                                    <KeyRound className="h-3 w-3" />
                                    {currentUser.has_pin ? '4-Digit PIN Configured' : 'No PIN Set'}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 self-start md:self-center">
                        <Link
                            href={route('dashboard')}
                            className="flex items-center gap-1.5 rounded-xl border border-slate-800 bg-slate-950 px-3.5 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800 hover:text-white transition"
                        >
                            <ArrowLeft className="h-3.5 w-3.5" />
                            <span>Back to POS</span>
                        </Link>
                    </div>
                </div>

                {/* Tabs Navigation */}
                <div className="flex flex-wrap border-b border-slate-800/80 gap-2">
                    <button
                        type="button"
                        onClick={() => setActiveTab('profile')}
                        className={`flex items-center gap-2 px-4 py-2.5 rounded-t-xl text-xs sm:text-sm font-semibold transition border-b-2 ${
                            activeTab === 'profile'
                                ? 'bg-slate-900 border-emerald-500 text-emerald-400'
                                : 'border-transparent text-slate-400 hover:text-slate-200'
                        }`}
                    >
                        <User className="h-4 w-4" />
                        <span>Profile & Details</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('pin')}
                        className={`flex items-center gap-2 px-4 py-2.5 rounded-t-xl text-xs sm:text-sm font-semibold transition border-b-2 ${
                            activeTab === 'pin'
                                ? 'bg-slate-900 border-emerald-500 text-emerald-400'
                                : 'border-transparent text-slate-400 hover:text-slate-200'
                        }`}
                    >
                        <KeyRound className="h-4 w-4" />
                        <span>Quick 4-Digit PIN</span>
                        {currentUser.has_pin && (
                            <span className="h-2 w-2 rounded-full bg-emerald-400"></span>
                        )}
                    </button>

                    <button
                        type="button"
                        onClick={() => setActiveTab('password')}
                        className={`flex items-center gap-2 px-4 py-2.5 rounded-t-xl text-xs sm:text-sm font-semibold transition border-b-2 ${
                            activeTab === 'password'
                                ? 'bg-slate-900 border-emerald-500 text-emerald-400'
                                : 'border-transparent text-slate-400 hover:text-slate-200'
                        }`}
                    >
                        <Lock className="h-4 w-4" />
                        <span>Change Password</span>
                    </button>
                </div>

                {/* Tab 1: Profile Information */}
                {activeTab === 'profile' && (
                    <div className="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-xl backdrop-blur-xl">
                        <div className="flex items-center gap-3 pb-4 border-b border-slate-800">
                            <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                <User className="h-5 w-5" />
                            </div>
                            <div>
                                <h2 className="text-base font-bold text-white">Personal Information</h2>
                                <p className="text-xs text-slate-400">Update your profile display name, email, and contact phone.</p>
                            </div>
                        </div>

                        <form onSubmit={submitProfile} className="mt-6 space-y-4 max-w-xl">
                            <div>
                                <label className="block text-xs font-semibold text-slate-300 mb-1.5">Full Name</label>
                                <div className="relative">
                                    <User className="absolute left-3.5 top-3 h-4 w-4 text-slate-500" />
                                    <input
                                        type="text"
                                        value={profileForm.data.name}
                                        onChange={(e) => profileForm.setData('name', e.target.value)}
                                        required
                                        className="w-full rounded-xl border border-slate-800 bg-slate-950 pl-10 pr-4 py-2.5 text-sm text-slate-100 placeholder-slate-600 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                    />
                                </div>
                                {profileForm.errors.name && (
                                    <p className="mt-1 text-xs text-rose-400">{profileForm.errors.name}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-300 mb-1.5">Email Address</label>
                                <div className="relative">
                                    <Mail className="absolute left-3.5 top-3 h-4 w-4 text-slate-500" />
                                    <input
                                        type="email"
                                        value={profileForm.data.email}
                                        onChange={(e) => profileForm.setData('email', e.target.value)}
                                        required
                                        className="w-full rounded-xl border border-slate-800 bg-slate-950 pl-10 pr-4 py-2.5 text-sm text-slate-100 placeholder-slate-600 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                    />
                                </div>
                                {profileForm.errors.email && (
                                    <p className="mt-1 text-xs text-rose-400">{profileForm.errors.email}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-300 mb-1.5">Contact Phone Number</label>
                                <div className="relative">
                                    <Phone className="absolute left-3.5 top-3 h-4 w-4 text-slate-500" />
                                    <input
                                        type="text"
                                        placeholder="0300-1234567"
                                        value={profileForm.data.phone}
                                        onChange={(e) => profileForm.setData('phone', e.target.value)}
                                        className="w-full rounded-xl border border-slate-800 bg-slate-950 pl-10 pr-4 py-2.5 text-sm text-slate-100 placeholder-slate-600 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                    />
                                </div>
                                {profileForm.errors.phone && (
                                    <p className="mt-1 text-xs text-rose-400">{profileForm.errors.phone}</p>
                                )}
                            </div>

                            <div className="pt-2">
                                <button
                                    type="submit"
                                    disabled={profileForm.processing}
                                    className="flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white shadow-lg shadow-emerald-600/30 hover:bg-emerald-500 transition disabled:opacity-50"
                                >
                                    <Save className="h-4 w-4" />
                                    <span>{profileForm.processing ? 'Saving...' : 'Save Profile Changes'}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Tab 2: Quick 4-Digit PIN */}
                {activeTab === 'pin' && (
                    <div className="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-xl backdrop-blur-xl">
                        <div className="flex items-center justify-between pb-4 border-b border-slate-800">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    <KeyRound className="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 className="text-base font-bold text-white">Quick Staff PIN (4-Digits)</h2>
                                    <p className="text-xs text-slate-400">Used for fast login and cashier handover at the POS counter.</p>
                                </div>
                            </div>
                            {currentUser.has_pin && (
                                <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-400 border border-emerald-500/20">
                                    <Check className="h-3.5 w-3.5" /> PIN Configured
                                </span>
                            )}
                        </div>

                        <div className="mt-4 rounded-2xl border border-slate-800 bg-slate-950/60 p-4 text-xs text-slate-400 flex items-start gap-3">
                            <Sparkles className="h-4 w-4 text-emerald-400 shrink-0 mt-0.5" />
                            <p>
                                With a 4-digit PIN configured, you can log in at the login screen simply by tapping your name and typing your PIN.
                                At the POS counter, cashiers can quickly switch terminal shifts without requiring full password re-entry.
                            </p>
                        </div>

                        <form onSubmit={submitPin} className="mt-6 space-y-6 max-w-md">
                            <div>
                                <label className="block text-xs font-semibold text-slate-300 mb-2">
                                    4-Digit Numeric PIN Code
                                </label>

                                {/* PIN Display Box with Dots & Reveal */}
                                <div className="flex items-center gap-3">
                                    <div className="relative flex-1">
                                        <input
                                            type={showPin ? 'text' : 'password'}
                                            maxLength={4}
                                            placeholder="••••"
                                            value={pinForm.data.pin_code}
                                            onChange={(e) => {
                                                const val = e.target.value.replace(/[^0-9]/g, '').slice(0, 4);
                                                pinForm.setData('pin_code', val);
                                            }}
                                            className="w-full rounded-xl border border-slate-800 bg-slate-950 px-4 py-3 text-center text-xl font-mono tracking-widest text-emerald-400 placeholder-slate-700 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPin(!showPin)}
                                            className="absolute right-3 top-3 text-slate-500 hover:text-slate-300 p-1"
                                            title="Toggle PIN Visibility"
                                        >
                                            {showPin ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                        </button>
                                    </div>

                                    {/* Visual Dots */}
                                    <div className="flex gap-2">
                                        {[0, 1, 2, 3].map((idx) => (
                                            <div
                                                key={idx}
                                                className={`h-4 w-4 rounded-full border-2 transition-all duration-200 ${
                                                    pinForm.data.pin_code.length > idx
                                                        ? 'bg-emerald-400 border-emerald-400 shadow-[0_0_10px_rgba(52,211,153,0.5)] scale-110'
                                                        : 'border-slate-800 bg-slate-950'
                                                }`}
                                            />
                                        ))}
                                    </div>
                                </div>
                                {pinForm.errors.pin_code && (
                                    <p className="mt-2 text-xs text-rose-400">{pinForm.errors.pin_code}</p>
                                )}
                            </div>

                            {/* Touch Numpad */}
                            <div className="rounded-2xl border border-slate-800 bg-slate-950/40 p-4">
                                <div className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-3 text-center">
                                    Touch Numpad
                                </div>
                                <div className="grid grid-cols-3 gap-2">
                                    {['1', '2', '3', '4', '5', '6', '7', '8', '9'].map((d) => (
                                        <button
                                            key={d}
                                            type="button"
                                            onClick={() => handlePinDigit(d)}
                                            className="h-11 rounded-xl bg-slate-800/80 hover:bg-slate-700 active:bg-emerald-600 font-bold text-base text-white border border-slate-700/60 transition"
                                        >
                                            {d}
                                        </button>
                                    ))}
                                    <button
                                        type="button"
                                        onClick={handlePinClear}
                                        className="h-11 rounded-xl bg-slate-800/40 hover:bg-rose-500/20 text-xs font-semibold text-slate-400 hover:text-rose-300 border border-slate-800 transition"
                                    >
                                        Clear
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => handlePinDigit('0')}
                                        className="h-11 rounded-xl bg-slate-800/80 hover:bg-slate-700 active:bg-emerald-600 font-bold text-base text-white border border-slate-700/60 transition"
                                    >
                                        0
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handlePinBackspace}
                                        className="h-11 rounded-xl bg-slate-800/40 hover:bg-slate-700 text-xs font-semibold text-slate-400 hover:text-white border border-slate-800 transition"
                                    >
                                        ⌫ Back
                                    </button>
                                </div>
                            </div>

                            <div className="flex items-center gap-3 pt-2">
                                <button
                                    type="submit"
                                    disabled={pinForm.processing || pinForm.data.pin_code.length !== 4}
                                    className="flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white shadow-lg shadow-emerald-600/30 hover:bg-emerald-500 transition disabled:opacity-50"
                                >
                                    <Save className="h-4 w-4" />
                                    <span>{pinForm.processing ? 'Saving...' : 'Update 4-Digit PIN'}</span>
                                </button>

                                {currentUser.has_pin && (
                                    <button
                                        type="button"
                                        onClick={removePin}
                                        disabled={pinForm.processing}
                                        className="rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-2.5 text-xs font-semibold text-rose-300 hover:bg-rose-500/20 transition"
                                    >
                                        Remove PIN
                                    </button>
                                )}
                            </div>
                        </form>
                    </div>
                )}

                {/* Tab 3: Change Password */}
                {activeTab === 'password' && (
                    <div className="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-xl backdrop-blur-xl">
                        <div className="flex items-center gap-3 pb-4 border-b border-slate-800">
                            <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                <Lock className="h-5 w-5" />
                            </div>
                            <div>
                                <h2 className="text-base font-bold text-white">Update Password</h2>
                                <p className="text-xs text-slate-400">Ensure your account uses a strong, secure password.</p>
                            </div>
                        </div>

                        <form onSubmit={submitPassword} className="mt-6 space-y-4 max-w-xl">
                            <div>
                                <label className="block text-xs font-semibold text-slate-300 mb-1.5">Current Password</label>
                                <div className="relative">
                                    <Lock className="absolute left-3.5 top-3 h-4 w-4 text-slate-500" />
                                    <input
                                        ref={currentPasswordInput}
                                        type="password"
                                        value={passwordForm.data.current_password}
                                        onChange={(e) => passwordForm.setData('current_password', e.target.value)}
                                        required
                                        className="w-full rounded-xl border border-slate-800 bg-slate-950 pl-10 pr-4 py-2.5 text-sm text-slate-100 placeholder-slate-600 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                    />
                                </div>
                                {passwordForm.errors.current_password && (
                                    <p className="mt-1 text-xs text-rose-400">{passwordForm.errors.current_password}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-300 mb-1.5">New Password</label>
                                <div className="relative">
                                    <Lock className="absolute left-3.5 top-3 h-4 w-4 text-slate-500" />
                                    <input
                                        ref={newPasswordInput}
                                        type="password"
                                        value={passwordForm.data.password}
                                        onChange={(e) => passwordForm.setData('password', e.target.value)}
                                        required
                                        className="w-full rounded-xl border border-slate-800 bg-slate-950 pl-10 pr-4 py-2.5 text-sm text-slate-100 placeholder-slate-600 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                    />
                                </div>
                                {passwordForm.errors.password && (
                                    <p className="mt-1 text-xs text-rose-400">{passwordForm.errors.password}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-300 mb-1.5">Confirm New Password</label>
                                <div className="relative">
                                    <Lock className="absolute left-3.5 top-3 h-4 w-4 text-slate-500" />
                                    <input
                                        type="password"
                                        value={passwordForm.data.password_confirmation}
                                        onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)}
                                        required
                                        className="w-full rounded-xl border border-slate-800 bg-slate-950 pl-10 pr-4 py-2.5 text-sm text-slate-100 placeholder-slate-600 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                    />
                                </div>
                                {passwordForm.errors.password_confirmation && (
                                    <p className="mt-1 text-xs text-rose-400">{passwordForm.errors.password_confirmation}</p>
                                )}
                            </div>

                            <div className="pt-2">
                                <button
                                    type="submit"
                                    disabled={passwordForm.processing}
                                    className="flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white shadow-lg shadow-emerald-600/30 hover:bg-emerald-500 transition disabled:opacity-50"
                                >
                                    <Save className="h-4 w-4" />
                                    <span>{passwordForm.processing ? 'Updating...' : 'Update Password'}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Security & Audit Notice Box */}
                <div className="rounded-3xl border border-slate-800/80 bg-slate-950/60 p-5 flex items-center justify-between gap-4 text-xs text-slate-400">
                    <div className="flex items-center gap-3">
                        <ShieldCheck className="h-5 w-5 text-emerald-400 shrink-0" />
                        <div>
                            <span className="font-semibold text-slate-200">Audit Trail Compliance:</span> All credential and PIN updates are recorded with immutable timestamps in the pharmacy system audit event log.
                        </div>
                    </div>
                    <div className="font-mono text-[11px] text-slate-500 hidden sm:block">
                        User ID: #{currentUser.id}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
