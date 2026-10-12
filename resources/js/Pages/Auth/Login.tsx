import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState, useEffect } from 'react';
import { KeyRound, Mail, User as UserIcon, Shield, Sparkles, Delete, ArrowLeft } from 'lucide-react';

interface StaffMember {
    id: number;
    name: string;
    role: string;
}

interface Props {
    status?: string;
    canResetPassword: boolean;
    staff?: StaffMember[];
}

export default function Login({ status, canResetPassword, staff = [] }: Props) {
    const [loginMode, setLoginMode] = useState<'pin' | 'password'>(staff && staff.length > 0 ? 'pin' : 'password');
    const [selectedStaff, setSelectedStaff] = useState<StaffMember | null>(null);

    // Password form
    const passwordForm = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    // Quick PIN form
    const pinForm = useForm({
        user_id: '' as number | '',
        pin_code: '',
    });

    const submitPassword: FormEventHandler = (e) => {
        e.preventDefault();
        passwordForm.post(route('login'), {
            onFinish: () => passwordForm.reset('password'),
        });
    };

    const handleSelectStaff = (member: StaffMember) => {
        setSelectedStaff(member);
        pinForm.setData({
            user_id: member.id,
            pin_code: '',
        });
        pinForm.clearErrors();
    };

    const handlePinInput = (digit: string) => {
        if (pinForm.data.pin_code.length < 4) {
            const newPin = pinForm.data.pin_code + digit;
            pinForm.setData('pin_code', newPin);
            if (newPin.length === 4 && selectedStaff) {
                // Auto-submit on 4th digit
                pinForm.transform(() => ({
                    user_id: selectedStaff.id,
                    pin_code: newPin,
                }));
                pinForm.post(route('login.pin'), {
                    onError: () => pinForm.setData('pin_code', ''),
                });
            }
        }
    };

    const handlePinBackspace = () => {
        pinForm.setData('pin_code', pinForm.data.pin_code.slice(0, -1));
    };

    const handlePinClear = () => {
        pinForm.setData('pin_code', '');
    };

    // Keyboard listener for physical numpad typing
    useEffect(() => {
        if (loginMode !== 'pin' || !selectedStaff) return;

        const onKeyDown = (e: KeyboardEvent) => {
            if (/^[0-9]$/.test(e.key)) {
                e.preventDefault();
                handlePinInput(e.key);
            } else if (e.key === 'Backspace') {
                e.preventDefault();
                handlePinBackspace();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                setSelectedStaff(null);
            }
        };

        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [loginMode, selectedStaff, pinForm.data.pin_code]);

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
        <GuestLayout>
            <Head title="Staff Sign In" />

            {status && (
                <div className="mb-4 text-xs font-semibold text-emerald-400 bg-emerald-500/10 p-3 rounded-xl border border-emerald-500/20">
                    {status}
                </div>
            )}

            {/* Mode Switcher Tabs */}
            {staff && staff.length > 0 && (
                <div className="mb-6 grid grid-cols-2 rounded-xl bg-slate-900 p-1 border border-slate-800 text-xs font-bold">
                    <button
                        type="button"
                        onClick={() => {
                            setLoginMode('pin');
                            setSelectedStaff(null);
                        }}
                        className={`flex items-center justify-center gap-2 rounded-lg py-2 transition ${
                            loginMode === 'pin'
                                ? 'bg-emerald-600 text-white shadow'
                                : 'text-slate-400 hover:text-slate-200'
                        }`}
                    >
                        <KeyRound className="h-3.5 w-3.5" />
                        <span>Quick Staff PIN</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => setLoginMode('password')}
                        className={`flex items-center justify-center gap-2 rounded-lg py-2 transition ${
                            loginMode === 'password'
                                ? 'bg-emerald-600 text-white shadow'
                                : 'text-slate-400 hover:text-slate-200'
                        }`}
                    >
                        <Mail className="h-3.5 w-3.5" />
                        <span>Email & Password</span>
                    </button>
                </div>
            )}

            {/* Quick PIN Mode */}
            {loginMode === 'pin' && staff && staff.length > 0 ? (
                <div>
                    {!selectedStaff ? (
                        <div className="space-y-3">
                            <div className="text-center mb-4">
                                <h2 className="text-base font-bold text-white">Select Staff Account</h2>
                                <p className="text-xs text-slate-400 mt-0.5">Tap your profile to unlock counter access</p>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5 max-h-72 overflow-y-auto pr-1">
                                {staff.map((member) => (
                                    <button
                                        key={member.id}
                                        type="button"
                                        onClick={() => handleSelectStaff(member)}
                                        className="flex items-center gap-3 p-3 rounded-xl border border-slate-800 bg-slate-900/60 hover:bg-slate-800/80 hover:border-emerald-500/40 text-left transition group active:scale-95"
                                    >
                                        <div className="h-9 w-9 rounded-xl bg-slate-800 group-hover:bg-emerald-500/20 text-slate-300 group-hover:text-emerald-400 flex items-center justify-center font-bold text-sm shrink-0 transition">
                                            {member.name.charAt(0)}
                                        </div>
                                        <div className="overflow-hidden">
                                            <div className="font-bold text-xs text-slate-200 truncate group-hover:text-white">
                                                {member.name}
                                            </div>
                                            <span className={`inline-block text-[10px] font-semibold uppercase px-1.5 py-0.2 rounded border mt-0.5 ${getRoleColor(member.role)}`}>
                                                {member.role}
                                            </span>
                                        </div>
                                    </button>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-5">
                            {/* Selected Staff Header */}
                            <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                                <button
                                    type="button"
                                    onClick={() => setSelectedStaff(null)}
                                    className="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-white"
                                >
                                    <ArrowLeft className="h-3.5 w-3.5" />
                                    <span>Change Staff</span>
                                </button>
                                <div className="text-right">
                                    <div className="font-bold text-xs text-white">{selectedStaff.name}</div>
                                    <span className={`inline-block text-[10px] font-semibold uppercase px-1.5 py-0.2 rounded border ${getRoleColor(selectedStaff.role)}`}>
                                        {selectedStaff.role}
                                    </span>
                                </div>
                            </div>

                            {/* PIN Display Dots */}
                            <div className="text-center space-y-2">
                                <div className="text-xs text-slate-400">Enter 4-Digit Security PIN</div>
                                <div className="flex justify-center gap-3 py-2">
                                    {[0, 1, 2, 3].map((idx) => (
                                        <div
                                            key={idx}
                                            className={`h-4 w-4 rounded-full transition-all duration-150 ${
                                                pinForm.data.pin_code.length > idx
                                                    ? 'bg-emerald-400 scale-125 shadow-lg shadow-emerald-400/50'
                                                    : 'border border-slate-700 bg-slate-900'
                                            }`}
                                        />
                                    ))}
                                </div>
                                {pinForm.errors.pin_code && (
                                    <p className="text-xs text-rose-400 font-semibold animate-shake">
                                        {pinForm.errors.pin_code}
                                    </p>
                                )}
                            </div>

                            {/* On-screen Numeric Keypad */}
                            <div className="grid grid-cols-3 gap-2 max-w-xs mx-auto">
                                {['1', '2', '3', '4', '5', '6', '7', '8', '9'].map((digit) => (
                                    <button
                                        key={digit}
                                        type="button"
                                        disabled={pinForm.processing}
                                        onClick={() => handlePinInput(digit)}
                                        className="h-12 rounded-xl border border-slate-800 bg-slate-900/80 hover:bg-slate-800 text-lg font-bold text-white transition active:scale-95 disabled:opacity-50"
                                    >
                                        {digit}
                                    </button>
                                ))}
                                <button
                                    type="button"
                                    onClick={handlePinClear}
                                    disabled={pinForm.processing || pinForm.data.pin_code.length === 0}
                                    className="h-12 rounded-xl border border-slate-800 bg-slate-900/40 hover:bg-slate-800 text-xs font-semibold text-slate-400 hover:text-white transition disabled:opacity-40"
                                >
                                    Clear
                                </button>
                                <button
                                    type="button"
                                    disabled={pinForm.processing}
                                    onClick={() => handlePinInput('0')}
                                    className="h-12 rounded-xl border border-slate-800 bg-slate-900/80 hover:bg-slate-800 text-lg font-bold text-white transition active:scale-95 disabled:opacity-50"
                                >
                                    0
                                </button>
                                <button
                                    type="button"
                                    onClick={handlePinBackspace}
                                    disabled={pinForm.processing || pinForm.data.pin_code.length === 0}
                                    className="h-12 rounded-xl border border-slate-800 bg-slate-900/40 hover:bg-slate-800 flex items-center justify-center text-slate-400 hover:text-white transition disabled:opacity-40"
                                >
                                    <Delete className="h-5 w-5" />
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            ) : (
                /* Standard Email & Password Mode */
                <form onSubmit={submitPassword} className="space-y-4">
                    <div>
                        <InputLabel htmlFor="email" value="Staff Email Address" />
                        <TextInput
                            id="email"
                            type="email"
                            name="email"
                            value={passwordForm.data.email}
                            className="mt-1 block w-full"
                            autoComplete="username"
                            isFocused={true}
                            onChange={(e) => passwordForm.setData('email', e.target.value)}
                            required
                        />
                        <InputError message={passwordForm.errors.email} className="mt-1 text-xs" />
                    </div>

                    <div>
                        <InputLabel htmlFor="password" value="Password" />
                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            value={passwordForm.data.password}
                            className="mt-1 block w-full"
                            autoComplete="current-password"
                            onChange={(e) => passwordForm.setData('password', e.target.value)}
                            required
                        />
                        <InputError message={passwordForm.errors.password} className="mt-1 text-xs" />
                    </div>

                    <div className="flex items-center justify-between text-xs">
                        <label className="flex items-center text-slate-400">
                            <Checkbox
                                name="remember"
                                checked={passwordForm.data.remember}
                                onChange={(e) =>
                                    passwordForm.setData(
                                        'remember',
                                        (e.target.checked || false) as false,
                                    )
                                }
                            />
                            <span className="ms-2">Keep me signed in</span>
                        </label>

                        {canResetPassword && (
                            <Link
                                href={route('password.request')}
                                className="text-slate-400 underline hover:text-slate-200"
                            >
                                Forgot password?
                            </Link>
                        )}
                    </div>

                    <PrimaryButton className="w-full justify-center py-2.5" disabled={passwordForm.processing}>
                        Sign In with Password
                    </PrimaryButton>
                </form>
            )}
        </GuestLayout>
    );
}
