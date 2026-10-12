import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState, useEffect, useRef, useMemo } from 'react';
import axios from 'axios';
import {
    Search,
    Barcode,
    Trash2,
    Plus,
    Minus,
    PauseCircle,
    PlayCircle,
    CreditCard,
    DollarSign,
    User,
    CheckCircle,
    X,
    AlertCircle,
    ArrowRight,
    Volume2,
    VolumeX,
    Sparkles,
    ShoppingBag,
    Coins,
    MapPin,
    AlertTriangle,
    ShieldAlert,
    UserPlus,
    Tag,
    Receipt,
    Maximize2,
    HelpCircle,
    Layers,
    Clock,
    Zap,
    Check,
    Percent,
    KeyRound,
} from 'lucide-react';
import { MedicineItem, MedicinePackage } from '@/types';

interface CartLine {
    id: string; // unique cart line ID
    medicine: MedicineItem;
    selectedPackage: MedicinePackage;
    quantity: number; // package quantity
    unitPricePaisa: number;
    discountPaisa: number;
    discountMode?: 'percent' | 'fixed';
    discountValue?: number;
}

interface HeldBill {
    id: string;
    timestamp: string;
    title: string;
    customerId?: number;
    items: CartLine[];
    invoiceDiscountPaisa: number;
}

interface CustomerAccount {
    id: number;
    name: string;
    code: string;
    phone?: string;
    credit_limit_paisa: number;
    balance_paisa: number;
}

interface Props {
    currentShift: any;
    counters: Array<{ id: number; name: string; code: string }>;
    customers: CustomerAccount[];
    quickMedicines?: MedicineItem[];
    activeStaff?: Array<{ id: number; name: string; role: string }>;
    shiftStats?: {
        sales_count: number;
        sales_total_paisa: number;
    };
    config: {
        tax_percentage: number;
        currency_symbol: string;
    };
}

function pkr(paisa: number): string {
    return 'PKR ' + (paisa / 100).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function getChangeBreakdown(changePaisa: number): string[] {
    let rupees = Math.floor(changePaisa / 100);
    if (rupees <= 0) return [];
    const notes = [5000, 1000, 500, 100, 50, 20, 10, 5];
    const result: string[] = [];
    for (const note of notes) {
        if (rupees >= note) {
            const count = Math.floor(rupees / note);
            rupees %= note;
            result.push(`${count}x Rs ${note.toLocaleString()}`);
        }
    }
    if (rupees > 0) {
        result.push(`Rs ${rupees} coin/note`);
    }
    return result;
}

export default function PosIndex({
    currentShift,
    counters,
    customers: initialCustomers,
    quickMedicines = [],
    activeStaff = [],
    shiftStats,
    config,
}: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<MedicineItem[]>([]);
    const [isSearching, setIsSearching] = useState(false);
    const [cart, setCart] = useState<CartLine[]>([]);
    const [invoiceDiscountMode, setInvoiceDiscountMode] = useState<'percent' | 'fixed'>('fixed');
    const [invoiceDiscountPercent, setInvoiceDiscountPercent] = useState<number>(0);
    const [invoiceDiscountFixed, setInvoiceDiscountFixed] = useState<number>(0);
    const [customers, setCustomers] = useState<CustomerAccount[]>(initialCustomers);
    const [selectedCustomerId, setSelectedCustomerId] = useState<number | undefined>(undefined);
    const [heldBills, setHeldBills] = useState<HeldBill[]>([]);
    const [isPaymentOpen, setIsPaymentOpen] = useState(false);
    const [paymentMethod, setPaymentMethod] = useState<'cash' | 'card' | 'credit' | 'jazzcash' | 'easypaisa' | 'bank_transfer'>('cash');
    const [receivedPaisa, setReceivedPaisa] = useState<number>(0);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [checkoutIdempotencyKey, setCheckoutIdempotencyKey] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [heldDrawerOpen, setHeldDrawerOpen] = useState(false);
    const [soundEnabled, setSoundEnabled] = useState(true);
    const [activeTab, setActiveTab] = useState<'cart' | 'otc'>('cart');
    const [otcCategory, setOtcCategory] = useState<string>('all');
    const [isNewCustomerOpen, setIsNewCustomerOpen] = useState(false);
    const [newCustomerForm, setNewCustomerForm] = useState({ name: '', phone: '', credit_limit: 10000 });
    const [isSavingCustomer, setIsSavingCustomer] = useState(false);
    const [lineDiscountModal, setLineDiscountModal] = useState<{
        index: number;
        mode: 'percent' | 'fixed';
        value: number;
    } | null>(null);

    // Quick Cashier Switch states
    const [isSwitchCashierOpen, setIsSwitchCashierOpen] = useState(false);
    const [selectedSwitchStaff, setSelectedSwitchStaff] = useState<{ id: number; name: string; role: string } | null>(null);
    const [switchPinCode, setSwitchPinCode] = useState('');
    const [switchCashierError, setSwitchCashierError] = useState<string | null>(null);
    const [isSwitchingCashier, setIsSwitchingCashier] = useState(false);

    const searchInputRef = useRef<HTMLInputElement>(null);
    const receivedInputRef = useRef<HTMLInputElement>(null);

    // Audio synthesizer using Web Audio API (100% offline, 0 external files)
    const playSound = (type: 'beep' | 'error' | 'success') => {
        if (!soundEnabled) return;
        try {
            const AudioCtx = window.AudioContext || (window as any).webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            if (type === 'beep') {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(1150, ctx.currentTime);
                gain.gain.setValueAtTime(0.12, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.08);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.08);
            } else if (type === 'error') {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(260, ctx.currentTime);
                gain.gain.setValueAtTime(0.18, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.22);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.22);
            } else if (type === 'success') {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(750, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(1400, ctx.currentTime + 0.15);
                gain.gain.setValueAtTime(0.14, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.2);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.2);
            }
        } catch {
            // Audio policy fallback
        }
    };

    // Load held bills & sound preference from localStorage on mount
    useEffect(() => {
        try {
            const saved = localStorage.getItem('pos_held_bills');
            if (saved) setHeldBills(JSON.parse(saved));
            const soundPref = localStorage.getItem('pos_sound_pref');
            if (soundPref !== null) setSoundEnabled(soundPref === 'true');
        } catch (e) {
            console.error('Failed to parse storage', e);
        }
    }, []);

    // Reset idempotency key whenever cart or payment parameters change
    useEffect(() => {
        setCheckoutIdempotencyKey(null);
    }, [cart, invoiceDiscountPercent, invoiceDiscountFixed, selectedCustomerId, paymentMethod, receivedPaisa]);

    // Save held bills to localStorage
    const saveHeldBills = (bills: HeldBill[]) => {
        setHeldBills(bills);
        localStorage.setItem('pos_held_bills', JSON.stringify(bills));
    };

    const toggleSound = () => {
        const next = !soundEnabled;
        setSoundEnabled(next);
        localStorage.setItem('pos_sound_pref', String(next));
    };

    const handleSwitchCashierDigit = (digit: string) => {
        if (switchPinCode.length < 4) {
            const nextPin = switchPinCode + digit;
            setSwitchPinCode(nextPin);
            if (nextPin.length === 4) {
                submitSwitchCashier(nextPin, selectedSwitchStaff?.id);
            }
        }
    };

    const handleSwitchCashierBackspace = () => {
        setSwitchPinCode((prev) => prev.slice(0, -1));
    };

    const handleSwitchCashierClear = () => {
        setSwitchPinCode('');
    };

    const submitSwitchCashier = async (pin: string, staffId?: number) => {
        setIsSwitchingCashier(true);
        setSwitchCashierError(null);
        try {
            const res = await axios.post('/pos/switch-cashier', {
                pin_code: pin,
                user_id: staffId || undefined,
            });
            if (res.data.success) {
                playSound('success');
                setIsSwitchCashierOpen(false);
                setSwitchPinCode('');
                setSelectedSwitchStaff(null);
                router.reload();
            }
        } catch (err: any) {
            playSound('error');
            setSwitchPinCode('');
            setSwitchCashierError(err.response?.data?.message || 'Invalid PIN code. Please try again.');
        } finally {
            setIsSwitchingCashier(false);
        }
    };

    // Keyboard Shortcuts (F1, F2, F4, F8, F9, ESC)
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (isSwitchCashierOpen) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    setIsSwitchCashierOpen(false);
                } else if (/^[0-9]$/.test(e.key)) {
                    e.preventDefault();
                    handleSwitchCashierDigit(e.key);
                } else if (e.key === 'Backspace') {
                    e.preventDefault();
                    handleSwitchCashierBackspace();
                }
                return;
            }
            if (e.key === 'F2') {
                e.preventDefault();
                searchInputRef.current?.focus();
            } else if (e.key === 'F4') {
                e.preventDefault();
                setIsNewCustomerOpen(true);
            } else if (e.key === 'F8') {
                e.preventDefault();
                handleHoldBill();
            } else if (e.key === 'F9') {
                e.preventDefault();
                if (cart.length > 0) {
                    openPayment();
                }
            } else if (e.key === 'Escape') {
                if (isPaymentOpen) {
                    setIsPaymentOpen(false);
                } else if (heldDrawerOpen) {
                    setHeldDrawerOpen(false);
                } else if (isNewCustomerOpen) {
                    setIsNewCustomerOpen(false);
                } else if (lineDiscountModal) {
                    setLineDiscountModal(null);
                } else {
                    setSearchResults([]);
                }
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [cart, isPaymentOpen, heldDrawerOpen, isNewCustomerOpen, lineDiscountModal, isSwitchCashierOpen, selectedSwitchStaff, switchPinCode]);

    // Live Debounced Medicine & Barcode Search
    useEffect(() => {
        const timer = setTimeout(async () => {
            if (searchQuery.trim().length > 0) {
                setIsSearching(true);
                try {
                    const res = await axios.get(route('pos.search'), {
                        params: { q: searchQuery.trim() },
                    });
                    const items: MedicineItem[] = res.data;
                    setSearchResults(items);

                    // If exact single barcode match, automatically add to cart!
                    if (items.length === 1 && (items[0] as any).matched_barcode) {
                        addToCart(items[0], (items[0] as any).selected_package_id);
                        setSearchQuery('');
                        setSearchResults([]);
                    }
                } catch (err) {
                    console.error('Search error', err);
                } finally {
                    setIsSearching(false);
                }
            } else {
                setSearchResults([]);
            }
        }, 180);

        return () => clearTimeout(timer);
    }, [searchQuery]);

    // Add item to cart
    const addToCart = (med: MedicineItem, packageId?: number) => {
        setErrorMessage(null);
        if (med.sellable_stock_base <= 0) {
            playSound('error');
            setErrorMessage(`Cannot add '${med.brand_name}'. No sellable non-expired stock available.`);
            return;
        }

        const selectedPkg = packageId
            ? med.packages.find((p) => p.id === packageId) || med.packages[0]
            : med.packages.find((p) => p.is_default_sale) || med.packages[0];

        const lineId = `${med.id}_${selectedPkg.id}`;
        const existingIndex = cart.findIndex((line) => line.id === lineId);

        if (existingIndex > -1) {
            const line = cart[existingIndex];
            const newQty = line.quantity + 1;
            const requiredBase = newQty * selectedPkg.base_quantity;

            if (requiredBase > med.sellable_stock_base) {
                playSound('error');
                setErrorMessage(`Stock limit reached for '${med.brand_name}'. Available: ${med.sellable_stock_base} ${med.base_unit}(s).`);
                return;
            }

            const updatedCart = [...cart];
            updatedCart[existingIndex] = { ...line, quantity: newQty };
            setCart(updatedCart);
            playSound('beep');
        } else {
            if (selectedPkg.base_quantity > med.sellable_stock_base) {
                playSound('error');
                setErrorMessage(`Package requires ${selectedPkg.base_quantity} ${med.base_unit}(s), but only ${med.sellable_stock_base} available.`);
                return;
            }

            setCart([
                ...cart,
                {
                    id: lineId,
                    medicine: med,
                    selectedPackage: selectedPkg,
                    quantity: 1,
                    unitPricePaisa: selectedPkg.sell_price_paisa,
                    discountPaisa: 0,
                    discountMode: 'percent',
                    discountValue: 0,
                },
            ]);
            playSound('beep');
        }
    };

    // Update line package
    const updatePackage = (index: number, newPkgId: number) => {
        const line = cart[index];
        const newPkg = line.medicine.packages.find((p) => p.id === newPkgId);
        if (!newPkg) return;

        const newLineId = `${line.medicine.id}_${newPkg.id}`;
        let newDiscountPaisa = line.discountPaisa;
        if (line.discountMode === 'percent' && line.discountValue) {
            newDiscountPaisa = Math.round((line.quantity * newPkg.sell_price_paisa * line.discountValue) / 100);
        } else if (line.discountMode === 'fixed') {
            newDiscountPaisa = Math.min(line.quantity * newPkg.sell_price_paisa, line.discountPaisa);
        }

        const updated = [...cart];
        updated[index] = {
            ...line,
            id: newLineId,
            selectedPackage: newPkg,
            unitPricePaisa: newPkg.sell_price_paisa,
            discountPaisa: newDiscountPaisa,
        };
        setCart(updated);
    };

    // Update quantity
    const updateQuantity = (index: number, delta: number) => {
        const line = cart[index];
        const newQty = line.quantity + delta;
        if (newQty <= 0) {
            removeLine(index);
            return;
        }

        const requiredBase = newQty * line.selectedPackage.base_quantity;
        if (requiredBase > line.medicine.sellable_stock_base) {
            playSound('error');
            setErrorMessage(`Cannot exceed available stock of ${line.medicine.sellable_stock_base} ${line.medicine.base_unit}(s).`);
            return;
        }

        let newDiscountPaisa = line.discountPaisa;
        if (line.discountMode === 'percent' && line.discountValue) {
            newDiscountPaisa = Math.round((newQty * line.unitPricePaisa * line.discountValue) / 100);
        } else if (line.discountMode === 'fixed') {
            newDiscountPaisa = Math.min(newQty * line.unitPricePaisa, line.discountPaisa);
        }

        const updated = [...cart];
        updated[index] = { ...line, quantity: newQty, discountPaisa: newDiscountPaisa };
        setCart(updated);
        playSound('beep');
    };

    const setQuantityDirect = (index: number, qtyVal: number) => {
        if (isNaN(qtyVal) || qtyVal <= 0) return;
        const line = cart[index];
        const requiredBase = qtyVal * line.selectedPackage.base_quantity;
        if (requiredBase > line.medicine.sellable_stock_base) {
            playSound('error');
            setErrorMessage(`Max available is ${Math.floor(line.medicine.sellable_stock_base / line.selectedPackage.base_quantity)} packs.`);
            return;
        }

        let newDiscountPaisa = line.discountPaisa;
        if (line.discountMode === 'percent' && line.discountValue) {
            newDiscountPaisa = Math.round((qtyVal * line.unitPricePaisa * line.discountValue) / 100);
        } else if (line.discountMode === 'fixed') {
            newDiscountPaisa = Math.min(qtyVal * line.unitPricePaisa, line.discountPaisa);
        }

        const updated = [...cart];
        updated[index] = { ...line, quantity: qtyVal, discountPaisa: newDiscountPaisa };
        setCart(updated);
    };

    const removeLine = (index: number) => {
        const updated = [...cart];
        updated.splice(index, 1);
        setCart(updated);
    };

    const applyLineDiscount = (index: number, mode: 'percent' | 'fixed', value: number) => {
        const updated = [...cart];
        const line = updated[index];
        if (!line) return;
        const lineSubtotal = line.quantity * line.unitPricePaisa;
        const calculatedDiscountPaisa = mode === 'percent'
            ? Math.round((lineSubtotal * value) / 100)
            : Math.round(value * 100);

        updated[index] = {
            ...line,
            discountPaisa: Math.min(lineSubtotal, Math.max(0, calculatedDiscountPaisa)),
            discountMode: mode,
            discountValue: value,
        };
        setCart(updated);
        setLineDiscountModal(null);
    };

    // Calculate Cart Totals
    const totals = useMemo(() => {
        let subtotalPaisa = 0;
        let lineDiscountTotal = 0;

        for (const item of cart) {
            const itemSubtotal = item.quantity * item.unitPricePaisa;
            subtotalPaisa += itemSubtotal;
            lineDiscountTotal += item.discountPaisa;
        }

        const calculatedInvoiceDiscountPaisa =
            invoiceDiscountMode === 'percent'
                ? Math.round((subtotalPaisa * invoiceDiscountPercent) / 100)
                : Math.round(invoiceDiscountFixed * 100);

        const totalDiscountPaisa = lineDiscountTotal + calculatedInvoiceDiscountPaisa;
        const taxableAmount = Math.max(0, subtotalPaisa - totalDiscountPaisa);
        const taxPaisa = Math.round((taxableAmount * (config.tax_percentage || 0)) / 100);
        const netTotalPaisa = taxableAmount + taxPaisa;

        return {
            subtotalPaisa,
            invoiceDiscountPaisa: calculatedInvoiceDiscountPaisa,
            lineDiscountTotal,
            discountPaisa: totalDiscountPaisa,
            taxPaisa,
            netTotalPaisa,
        };
    }, [cart, invoiceDiscountMode, invoiceDiscountPercent, invoiceDiscountFixed, config.tax_percentage]);

    // Open Payment dialog
    const openPayment = () => {
        if (cart.length === 0) return;
        setReceivedPaisa(totals.netTotalPaisa);
        setIsPaymentOpen(true);
        setTimeout(() => receivedInputRef.current?.select(), 100);
    };

    // Park / Hold current bill
    const handleHoldBill = () => {
        if (cart.length === 0) return;
        const newHeld: HeldBill = {
            id: 'held_' + Date.now(),
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            title: `Bill #${heldBills.length + 1} (${cart.length} items)`,
            customerId: selectedCustomerId,
            items: cart,
            invoiceDiscountPaisa: totals.invoiceDiscountPaisa,
        };

        saveHeldBills([...heldBills, newHeld]);
        setCart([]);
        setInvoiceDiscountPercent(0);
        setInvoiceDiscountFixed(0);
        setSelectedCustomerId(undefined);
        setErrorMessage(null);
    };

    // Resume a held bill
    const resumeHeldBill = (bill: HeldBill) => {
        setCart(bill.items);
        setInvoiceDiscountMode('fixed');
        setInvoiceDiscountFixed(bill.invoiceDiscountPaisa / 100);
        setInvoiceDiscountPercent(0);
        setSelectedCustomerId(bill.customerId);
        saveHeldBills(heldBills.filter((b) => b.id !== bill.id));
        setHeldDrawerOpen(false);
    };

    // Discard held bill
    const discardHeldBill = (id: string) => {
        saveHeldBills(heldBills.filter((b) => b.id !== id));
    };

    // Quick tender banknote adders
    const addCashTender = (amountPkr: number) => {
        setReceivedPaisa((prev) => prev + amountPkr * 100);
        playSound('beep');
    };

    // Quick discount preset chips
    const applyPresetDiscount = (val: number) => {
        if (invoiceDiscountMode === 'percent') {
            setInvoiceDiscountPercent(val);
        } else {
            setInvoiceDiscountFixed(val);
        }
    };

    // Quick customer creation
    const handleCreateCustomer = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!newCustomerForm.name.trim()) return;
        setIsSavingCustomer(true);
        try {
            const res = await axios.post(route('customers.quick-store'), {
                name: newCustomerForm.name,
                phone: newCustomerForm.phone,
                credit_limit_paisa: newCustomerForm.credit_limit * 100,
            });
            if (res.data.success) {
                const newCust = res.data.customer;
                setCustomers((prev) => [newCust, ...prev]);
                setSelectedCustomerId(newCust.id);
                setIsNewCustomerOpen(false);
                setNewCustomerForm({ name: '', phone: '', credit_limit: 10000 });
                playSound('success');
            }
        } catch (err: any) {
            playSound('error');
            alert(err.response?.data?.message || 'Failed to save customer');
        } finally {
            setIsSavingCustomer(false);
        }
    };

    // Finalize checkout
    const handleCheckout = async () => {
        if (cart.length === 0 || isSubmitting) return;

        setErrorMessage(null);
        setIsSubmitting(true);

        const idempotencyKey = checkoutIdempotencyKey || ('pos_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9));
        if (!checkoutIdempotencyKey) {
            setCheckoutIdempotencyKey(idempotencyKey);
        }

        const payload = {
            idempotency_key: idempotencyKey,
            shift_id: currentShift?.id,
            counter_id: currentShift?.counter_id,
            customer_id: selectedCustomerId,
            payment_method: paymentMethod,
            received_paisa: paymentMethod === 'credit' ? 0 : receivedPaisa,
            discount_paisa: totals.invoiceDiscountPaisa,
            items: cart.map((line) => ({
                medicine_id: line.medicine.id,
                medicine_package_id: line.selectedPackage.id,
                quantity: line.quantity,
                unit_price_paisa: line.unitPricePaisa,
                discount_paisa: line.discountPaisa,
            })),
        };

        try {
            const res = await axios.post(route('pos.checkout'), payload);
            if (res.data.success) {
                playSound('success');
                setCheckoutIdempotencyKey(null);
                setCart([]);
                setInvoiceDiscountPercent(0);
                setInvoiceDiscountFixed(0);
                setIsPaymentOpen(false);
                router.visit(res.data.receipt_url);
            }
        } catch (err: any) {
            playSound('error');
            const msg = err.response?.data?.message || err.response?.data?.error || 'Checkout failed. Please check stock or inputs.';
            setErrorMessage(msg);
        } finally {
            setIsSubmitting(false);
        }
    };

    const selectedCustomer = customers.find((c) => c.id === selectedCustomerId);
    const changePaisa = Math.max(0, receivedPaisa - totals.netTotalPaisa);
    const shortPaisa = Math.max(0, totals.netTotalPaisa - receivedPaisa);
    const changeBreakdown = getChangeBreakdown(changePaisa);

    return (
        <AuthenticatedLayout>
            <Head title="POS Counter Terminal" />

            {/* Error Banner */}
            {errorMessage && (
                <div className="mb-3 rounded-xl border border-rose-500/40 bg-rose-500/15 p-3 flex items-center justify-between text-rose-300 text-xs shadow-lg animate-in fade-in">
                    <div className="flex items-center gap-2.5">
                        <AlertCircle className="h-4 w-4 text-rose-400 shrink-0" />
                        <span className="font-semibold">{errorMessage}</span>
                    </div>
                    <button onClick={() => setErrorMessage(null)} className="text-slate-400 hover:text-white p-1">
                        <X className="h-3.5 w-3.5" />
                    </button>
                </div>
            )}

            {/* Counter Cockpit HUD Header */}
            <div className="mb-3 rounded-2xl border border-slate-800 bg-slate-900/90 px-4 py-2.5 flex flex-wrap items-center justify-between gap-3 shadow-md backdrop-blur-md">
                <div className="flex items-center gap-3">
                    <div className="flex items-center gap-2">
                        <span className="relative flex h-2.5 w-2.5">
                            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                        </span>
                        <span className="font-bold text-xs text-white">
                            {currentShift?.counter?.name ?? 'Counter 1'}
                        </span>
                    </div>

                    <span className="text-slate-700 hidden sm:inline">|</span>

                    <div className="text-xs text-slate-300 hidden sm:flex items-center gap-1.5 bg-slate-950/60 px-2.5 py-1 rounded-lg border border-slate-800/80">
                        <span className="text-slate-500">Cashier:</span>
                        <span className="font-semibold text-emerald-300">{currentShift?.user?.name ?? 'Active Shift'}</span>
                        {activeStaff && activeStaff.length > 0 && (
                            <button
                                type="button"
                                onClick={() => {
                                    setIsSwitchCashierOpen(true);
                                    setSelectedSwitchStaff(null);
                                    setSwitchPinCode('');
                                    setSwitchCashierError(null);
                                }}
                                className="ml-1 text-[10px] uppercase font-bold tracking-wider bg-emerald-950/60 text-emerald-400 hover:bg-emerald-900/80 border border-emerald-500/30 px-1.5 py-0.5 rounded transition flex items-center gap-1"
                                title="Quick Cashier Switch (4-Digit PIN)"
                            >
                                <KeyRound className="h-2.5 w-2.5" />
                                <span>Switch</span>
                            </button>
                        )}
                    </div>

                    {shiftStats && (
                        <>
                            <span className="text-slate-700 hidden md:inline">|</span>
                            <div className="text-xs text-slate-400 hidden md:flex items-center gap-2">
                                <span>Shift Total: <strong className="text-emerald-400 font-mono">{pkr(shiftStats.sales_total_paisa)}</strong></span>
                                <span>•</span>
                                <span>{shiftStats.sales_count} bills</span>
                            </div>
                        </>
                    )}
                </div>

                <div className="flex items-center gap-2">
                    {/* View Switcher: Cart vs Fast OTC Grid */}
                    <div className="flex rounded-lg bg-slate-950 p-0.5 border border-slate-800 text-xs">
                        <button
                            type="button"
                            onClick={() => setActiveTab('cart')}
                            className={`flex items-center gap-1 px-2.5 py-1 rounded-md transition ${
                                activeTab === 'cart'
                                    ? 'bg-emerald-600 text-white font-bold shadow-sm'
                                    : 'text-slate-400 hover:text-white'
                            }`}
                        >
                            <ShoppingBag className="h-3.5 w-3.5" />
                            <span>Cart ({cart.length})</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('otc')}
                            className={`flex items-center gap-1 px-2.5 py-1 rounded-md transition ${
                                activeTab === 'otc'
                                    ? 'bg-emerald-600 text-white font-bold shadow-sm'
                                    : 'text-slate-400 hover:text-white'
                            }`}
                        >
                            <Zap className="h-3.5 w-3.5 text-amber-300" />
                            <span>Fast OTC Shelf</span>
                        </button>
                    </div>

                    <button
                        type="button"
                        onClick={toggleSound}
                        title={soundEnabled ? 'Scanner Audio Enabled' : 'Scanner Muted'}
                        className="rounded-lg border border-slate-700 bg-slate-800 p-1.5 text-slate-300 hover:text-white transition"
                    >
                        {soundEnabled ? <Volume2 className="h-4 w-4 text-emerald-400" /> : <VolumeX className="h-4 w-4 text-slate-500" />}
                    </button>
                </div>
            </div>

            {/* Main Terminal Grid: 8 Cols Left (Search/Cart/OTC), 4 Cols Right (Ticket/Checkout) */}
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 h-[calc(100vh-10.5rem)]">
                {/* Left Section (Col 8) */}
                <div className="lg:col-span-8 flex flex-col h-full rounded-2xl border border-slate-800 bg-slate-900/80 p-4 shadow-xl backdrop-blur-md overflow-hidden">
                    {/* Fast Search Input Bar */}
                    <div className="relative mb-3">
                        <div className="flex items-center gap-2.5 rounded-xl border border-slate-700 bg-slate-950 px-3.5 py-2.5 shadow-inner focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500/25 transition-all">
                            <Barcode className="h-5 w-5 text-emerald-400 shrink-0" />
                            <input
                                ref={searchInputRef}
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Scan Barcode or Search Brand Name / Formula / Shelf (F2)..."
                                className="w-full bg-transparent text-sm text-slate-100 placeholder-slate-500 focus:outline-none"
                                autoFocus
                            />
                            {isSearching && (
                                <div className="animate-spin h-4 w-4 border-2 border-emerald-500 border-t-transparent rounded-full shrink-0" />
                            )}
                            <kbd className="hidden sm:inline-block rounded-md bg-slate-800 border border-slate-700 px-2 py-0.5 text-xs font-mono text-slate-300">
                                F2
                            </kbd>
                        </div>

                        {/* Search Dropdown Results */}
                        {searchResults.length > 0 && (
                            <div className="absolute left-0 right-0 top-13 z-30 max-h-96 overflow-y-auto rounded-xl border border-slate-700 bg-slate-900/98 shadow-2xl divide-y divide-slate-800 backdrop-blur-md">
                                {searchResults.map((item) => {
                                    const isLowStock = item.sellable_stock_base > 0 && item.sellable_stock_base <= (item.reorder_level || 10);
                                    const isOutOfStock = item.sellable_stock_base <= 0;

                                    return (
                                        <div
                                            key={item.id}
                                            onClick={() => {
                                                addToCart(item);
                                                setSearchQuery('');
                                                setSearchResults([]);
                                                searchInputRef.current?.focus();
                                            }}
                                            className="flex items-center justify-between p-3 hover:bg-slate-800/90 cursor-pointer transition group"
                                        >
                                            <div className="space-y-0.5">
                                                <div className="font-semibold text-slate-100 flex items-center gap-2 text-sm">
                                                    <span className="group-hover:text-emerald-300 transition-colors">{item.brand_name} {item.strength}</span>
                                                    <span className="text-[11px] px-1.5 py-0.2 rounded bg-slate-800 text-slate-300 border border-slate-700">
                                                        {item.dosage_form}
                                                    </span>
                                                    {item.prescription_required && (
                                                        <span className="text-[10px] font-bold px-1.5 py-0.2 rounded bg-rose-500/20 text-rose-400 border border-rose-500/30">
                                                            Rx
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="text-xs text-slate-400 flex items-center gap-2">
                                                    <span>{item.generic_name || '—'}</span>
                                                    {item.rack_shelf && (
                                                        <span className="flex items-center gap-1 text-emerald-400 font-mono text-[11px]">
                                                            <MapPin className="h-3 w-3" /> {item.rack_shelf}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <div className="font-bold text-sm text-emerald-400">
                                                    {pkr(item.packages[0]?.sell_price_paisa ?? 0)}
                                                    <span className="text-[11px] text-slate-400 font-normal"> / {item.packages[0]?.name}</span>
                                                </div>
                                                <div className="text-xs mt-0.5">
                                                    {isOutOfStock ? (
                                                        <span className="inline-flex px-1.5 py-0.2 rounded bg-rose-500/20 text-rose-400 font-bold text-[10px] border border-rose-500/30">
                                                            Out of Stock
                                                        </span>
                                                    ) : isLowStock ? (
                                                        <span className="inline-flex px-1.5 py-0.2 rounded bg-amber-500/20 text-amber-300 font-semibold text-[10px] border border-amber-500/30">
                                                            {item.sellable_stock_base} {item.base_unit} left
                                                        </span>
                                                    ) : (
                                                        <span className="text-slate-400 text-xs">
                                                            Stock: <span className="text-slate-200 font-semibold">{item.sellable_stock_base} {item.base_unit}</span>
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>

                    {/* View: Fast OTC Touch Shelf */}
                    {activeTab === 'otc' ? (
                        <div className="flex-1 overflow-y-auto">
                            <div className="flex items-center justify-between mb-2.5">
                                <span className="text-xs font-bold text-slate-300 flex items-center gap-1.5">
                                    <Zap className="h-3.5 w-3.5 text-amber-400" /> Fast-Moving Over-the-Counter (OTC) Medicines
                                </span>
                                <span className="text-[11px] text-slate-500">Tap to instantly add to cart</span>
                            </div>

                            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5 pb-2">
                                {quickMedicines.map((med) => {
                                    const defaultPkg = med.packages.find((p) => p.is_default_sale) || med.packages[0];
                                    const isAvailable = med.sellable_stock_base > 0;

                                    return (
                                        <button
                                            key={med.id}
                                            type="button"
                                            disabled={!isAvailable}
                                            onClick={() => addToCart(med)}
                                            className={`rounded-xl border p-2.5 text-left transition flex flex-col justify-between group h-24 ${
                                                isAvailable
                                                    ? 'border-slate-800 bg-slate-950/70 hover:border-emerald-500/50 hover:bg-slate-800/80 active:scale-95 shadow-sm'
                                                    : 'border-slate-800/40 bg-slate-950/30 opacity-40 cursor-not-allowed'
                                            }`}
                                        >
                                            <div>
                                                <div className="font-bold text-xs text-slate-100 group-hover:text-emerald-300 truncate">
                                                    {med.brand_name} {med.strength}
                                                </div>
                                                <div className="text-[10px] text-slate-400 flex items-center gap-1 mt-0.5">
                                                    <span>{med.dosage_form}</span>
                                                    {med.rack_shelf && (
                                                        <span className="font-mono text-emerald-400">• {med.rack_shelf}</span>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="flex items-center justify-between mt-1 pt-1 border-t border-slate-900">
                                                <span className="font-bold text-xs text-emerald-400">
                                                    {pkr(defaultPkg?.sell_price_paisa ?? 0)}
                                                </span>
                                                <span className="text-[10px] text-slate-500">
                                                    {isAvailable ? `${med.sellable_stock_base} ${med.base_unit}` : 'Out'}
                                                </span>
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    ) : (
                        /* View: Cart Items Table */
                        <div className="flex-1 overflow-y-auto rounded-xl border border-slate-800 bg-slate-950/70 shadow-inner">
                            {cart.length === 0 ? (
                                <div className="flex flex-col items-center justify-center h-full text-slate-500 py-12">
                                    <div className="rounded-2xl bg-slate-900/80 p-5 border border-slate-800 mb-3 shadow-lg">
                                        <ShoppingBag className="h-10 w-10 text-emerald-500/60 stroke-1" />
                                    </div>
                                    <div className="text-sm font-semibold text-slate-300">Cart is Empty</div>
                                    <div className="text-xs text-slate-500 mt-1 text-center max-w-xs">
                                        Scan barcode or search medicine to start bill. Or switch to{' '}
                                        <button
                                            type="button"
                                            onClick={() => setActiveTab('otc')}
                                            className="text-emerald-400 underline font-semibold hover:text-emerald-300"
                                        >
                                            Fast OTC Shelf
                                        </button>
                                    </div>
                                </div>
                            ) : (
                                <table className="w-full text-left text-xs">
                                    <thead className="sticky top-0 z-10 bg-slate-900 border-b border-slate-800 text-slate-400 font-semibold uppercase tracking-wider">
                                        <tr>
                                            <th className="py-2.5 px-3">Item Description</th>
                                            <th className="py-2.5 px-3">Pack Unit</th>
                                            <th className="py-2.5 px-3 text-center min-w-[130px]">Qty</th>
                                            <th className="py-2.5 px-3 text-right">Price</th>
                                            <th className="py-2.5 px-3 text-right">Total</th>
                                            <th className="py-2.5 px-3 text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-800/60">
                                        {cart.map((line, idx) => (
                                            <tr key={line.id} className="hover:bg-slate-800/40 transition-colors">
                                                <td className="py-2.5 px-3">
                                                    <div className="font-semibold text-slate-100">{line.medicine.brand_name} {line.medicine.strength}</div>
                                                    <div className="text-[11px] text-slate-400 flex items-center gap-1.5 mt-0.5">
                                                        <span>Avail: {line.medicine.sellable_stock_base} {line.medicine.base_unit}</span>
                                                        {line.medicine.rack_shelf && (
                                                            <span>• Shelf: <span className="text-emerald-400 font-mono">{line.medicine.rack_shelf}</span></span>
                                                        )}
                                                        {line.discountPaisa > 0 && (
                                                            <span className="text-rose-400 font-bold ml-1">Disc: -{pkr(line.discountPaisa)}</span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="py-2.5 px-3">
                                                    <select
                                                        value={line.selectedPackage.id}
                                                        onChange={(e) => updatePackage(idx, Number(e.target.value))}
                                                        className="rounded-lg bg-slate-900 border border-slate-700 py-1 px-2 text-xs text-slate-200 focus:outline-none focus:border-emerald-500"
                                                    >
                                                        {line.medicine.packages.map((pkg) => (
                                                            <option key={pkg.id} value={pkg.id}>
                                                                {pkg.name} ({pkg.base_quantity} {line.medicine.base_unit})
                                                            </option>
                                                        ))}
                                                    </select>
                                                </td>
                                                <td className="py-2.5 px-3 min-w-[130px]">
                                                    <div className="flex items-center justify-center gap-1.5">
                                                        <button
                                                            type="button"
                                                            onClick={() => updateQuantity(idx, -1)}
                                                            className="rounded-lg p-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 transition shrink-0"
                                                            title="Decrease Quantity"
                                                        >
                                                            <Minus className="h-3 w-3" />
                                                        </button>
                                                        <input
                                                            type="number"
                                                            min="1"
                                                            value={line.quantity}
                                                            onChange={(e) => {
                                                                const val = parseInt(e.target.value, 10);
                                                                if (!isNaN(val)) setQuantityDirect(idx, val);
                                                            }}
                                                            className="w-16 min-w-[4rem] text-center rounded-lg bg-slate-900 border border-slate-700 py-1 px-1 font-black text-sm text-white focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                                        />
                                                        <button
                                                            type="button"
                                                            onClick={() => updateQuantity(idx, 1)}
                                                            className="rounded-lg p-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 transition shrink-0"
                                                            title="Increase Quantity"
                                                        >
                                                            <Plus className="h-3 w-3" />
                                                        </button>
                                                    </div>
                                                </td>
                                                <td className="py-2.5 px-3 text-right font-medium text-slate-300">
                                                    {pkr(line.unitPricePaisa)}
                                                </td>
                                                <td className="py-2.5 px-3 text-right font-bold text-emerald-400">
                                                    <div>{pkr(Math.max(0, line.quantity * line.unitPricePaisa - line.discountPaisa))}</div>
                                                    {line.discountPaisa > 0 && (
                                                        <div className="text-[10px] font-semibold text-amber-400">
                                                            -{line.discountMode === 'percent' ? `${line.discountValue}%` : pkr(line.discountPaisa)}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="py-2.5 px-3 text-center">
                                                    <div className="flex items-center justify-center gap-1">
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                const curLine = cart[idx];
                                                                setLineDiscountModal({
                                                                    index: idx,
                                                                    mode: curLine.discountMode || (curLine.discountPaisa > 0 ? 'fixed' : 'percent'),
                                                                    value: curLine.discountValue ?? (curLine.discountPaisa > 0 ? curLine.discountPaisa / 100 : 0),
                                                                });
                                                            }}
                                                            className={`p-1 rounded transition ${
                                                                line.discountPaisa > 0
                                                                    ? 'text-amber-400 bg-amber-500/10 hover:bg-amber-500/20 ring-1 ring-amber-500/40'
                                                                    : 'text-slate-500 hover:text-amber-400 hover:bg-amber-500/10'
                                                            }`}
                                                            title={line.discountPaisa > 0 ? `Discount applied: -${pkr(line.discountPaisa)}` : 'Add line discount'}
                                                        >
                                                            <Tag className="h-3.5 w-3.5" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => removeLine(idx)}
                                                            className="p-1 rounded text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 transition"
                                                            title="Remove item"
                                                        >
                                                            <Trash2 className="h-3.5 w-3.5" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    )}

                    {/* Bottom Action Bar */}
                    <div className="mt-3 pt-3 border-t border-slate-800 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={handleHoldBill}
                                disabled={cart.length === 0}
                                className="flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800/90 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-700 hover:text-white disabled:opacity-40 transition"
                            >
                                <PauseCircle className="h-4 w-4 text-amber-400" />
                                <span>Hold (F8)</span>
                            </button>

                            <button
                                type="button"
                                onClick={() => setHeldDrawerOpen(true)}
                                className="relative flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800/90 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-700 hover:text-white transition"
                            >
                                <PlayCircle className="h-4 w-4 text-blue-400" />
                                <span>Parked</span>
                                {heldBills.length > 0 && (
                                    <span className="rounded-full bg-blue-500 px-1.5 text-[10px] font-bold text-white">
                                        {heldBills.length}
                                    </span>
                                )}
                            </button>

                            <button
                                type="button"
                                onClick={() => setCart([])}
                                disabled={cart.length === 0}
                                className="rounded-xl border border-slate-800 px-3 py-1.5 text-xs font-medium text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 disabled:opacity-40 transition"
                            >
                                Clear
                            </button>
                        </div>

                        <div className="text-xs text-slate-400">
                            Items: <span className="font-bold text-slate-200">{cart.length}</span>
                            <span className="mx-2">•</span>
                            Packs: <span className="font-bold text-slate-200">{cart.reduce((s, i) => s + i.quantity, 0)}</span>
                        </div>
                    </div>
                </div>

                {/* Right Section: Ticket & Checkout (Col 4) */}
                <div className="lg:col-span-4 flex flex-col h-full rounded-2xl border border-slate-800 bg-slate-900/80 p-4 sm:p-5 shadow-xl backdrop-blur-md">
                    {/* Customer Selection & Quick Add */}
                    <div className="mb-3.5">
                        <div className="flex items-center justify-between mb-1.5">
                            <label className="text-xs font-semibold text-slate-400 flex items-center gap-1.5">
                                <User className="h-3.5 w-3.5 text-slate-400" /> Customer Account
                            </label>
                            <button
                                type="button"
                                onClick={() => setIsNewCustomerOpen(true)}
                                className="text-[11px] font-semibold text-emerald-400 hover:text-emerald-300 flex items-center gap-1"
                            >
                                <UserPlus className="h-3 w-3" />
                                <span>+ New Khata (F4)</span>
                            </button>
                        </div>

                        <select
                            value={selectedCustomerId ?? ''}
                            onChange={(e) => setSelectedCustomerId(e.target.value ? Number(e.target.value) : undefined)}
                            className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3.5 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                        >
                            <option value="">Walk-in Customer (Cash / Digital)</option>
                            {customers.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name} ({c.code}) {c.phone ? `• ${c.phone}` : ''}
                                </option>
                            ))}
                        </select>

                        {/* Customer Khata Status Card */}
                        {selectedCustomer && (
                            <div className="mt-2.5 rounded-xl border border-indigo-500/30 bg-indigo-500/10 p-2.5 text-xs">
                                <div className="flex justify-between text-indigo-300 font-semibold">
                                    <span>Khata Credit Limit:</span>
                                    <span>{pkr(selectedCustomer.credit_limit_paisa)}</span>
                                </div>
                                <div className="flex justify-between text-slate-300 mt-1">
                                    <span>Current Outstanding:</span>
                                    <span className="font-bold text-rose-400">{pkr(selectedCustomer.balance_paisa)}</span>
                                </div>
                                <div className="mt-1.5 w-full bg-slate-800 rounded-full h-1 overflow-hidden">
                                    <div
                                        className={`h-full ${
                                            selectedCustomer.balance_paisa / Math.max(1, selectedCustomer.credit_limit_paisa) > 0.85
                                                ? 'bg-rose-500'
                                                : 'bg-indigo-500'
                                        }`}
                                        style={{
                                            width: `${Math.min(100, Math.round((selectedCustomer.balance_paisa / Math.max(1, selectedCustomer.credit_limit_paisa)) * 100))}%`,
                                        }}
                                    />
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Invoice Discount with Mode Switcher & Preset Chips */}
                    <div className="mb-3 rounded-xl border border-slate-800 bg-slate-900/60 p-2.5">
                        <div className="flex justify-between items-center mb-2">
                            <span className="text-xs font-semibold text-slate-300 flex items-center gap-1.5">
                                <Tag className="h-3.5 w-3.5 text-amber-400" />
                                Special Invoice Discount
                            </span>
                            {/* Mode Toggle: % vs PKR */}
                            <div className="flex rounded-lg bg-slate-950 p-0.5 border border-slate-800 text-[11px] font-bold">
                                <button
                                    type="button"
                                    onClick={() => setInvoiceDiscountMode('percent')}
                                    className={`flex items-center gap-1 px-2 py-0.5 rounded-md transition ${
                                        invoiceDiscountMode === 'percent'
                                            ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30'
                                            : 'text-slate-400 hover:text-slate-200'
                                    }`}
                                >
                                    <Percent className="h-3 w-3" />
                                    <span>%</span>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setInvoiceDiscountMode('fixed')}
                                    className={`flex items-center gap-1 px-2 py-0.5 rounded-md transition ${
                                        invoiceDiscountMode === 'fixed'
                                            ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'
                                            : 'text-slate-400 hover:text-slate-200'
                                    }`}
                                >
                                    <span>PKR</span>
                                </button>
                            </div>
                        </div>

                        {/* Presets Chips */}
                        <div className="flex items-center gap-1 mb-2 overflow-x-auto pb-0.5">
                            {invoiceDiscountMode === 'percent' ? (
                                <>
                                    {[0, 5, 10, 15, 20].map((pct) => (
                                        <button
                                            key={pct}
                                            type="button"
                                            onClick={() => applyPresetDiscount(pct)}
                                            className={`rounded-md px-2 py-0.5 text-[11px] font-medium transition ${
                                                invoiceDiscountPercent === pct
                                                    ? 'bg-amber-500 text-slate-950 font-bold'
                                                    : 'bg-slate-800 hover:bg-slate-700 text-slate-300'
                                            }`}
                                        >
                                            {pct === 0 ? 'Clear' : `${pct}%`}
                                        </button>
                                    ))}
                                </>
                            ) : (
                                <>
                                    {[0, 50, 100, 200, 500].map((amt) => (
                                        <button
                                            key={amt}
                                            type="button"
                                            onClick={() => applyPresetDiscount(amt)}
                                            className={`rounded-md px-2 py-0.5 text-[11px] font-medium transition ${
                                                invoiceDiscountFixed === amt
                                                    ? 'bg-emerald-500 text-slate-950 font-bold'
                                                    : 'bg-slate-800 hover:bg-slate-700 text-slate-300'
                                            }`}
                                        >
                                            {amt === 0 ? 'Clear' : `Rs ${amt}`}
                                        </button>
                                    ))}
                                </>
                            )}
                        </div>

                        {/* Input Box */}
                        <div className="relative">
                            <input
                                type="number"
                                min="0"
                                max={invoiceDiscountMode === 'percent' ? 100 : undefined}
                                step={invoiceDiscountMode === 'percent' ? '0.5' : '1'}
                                value={
                                    invoiceDiscountMode === 'percent'
                                        ? invoiceDiscountPercent > 0
                                            ? invoiceDiscountPercent
                                            : ''
                                        : invoiceDiscountFixed > 0
                                          ? invoiceDiscountFixed
                                          : ''
                                }
                                onChange={(e) => {
                                    const val = Math.max(0, Number(e.target.value) || 0);
                                    if (invoiceDiscountMode === 'percent') {
                                        setInvoiceDiscountPercent(Math.min(100, val));
                                    } else {
                                        setInvoiceDiscountFixed(val);
                                    }
                                }}
                                placeholder={invoiceDiscountMode === 'percent' ? 'Enter discount %' : 'Enter discount amount (PKR)'}
                                className="w-full rounded-lg bg-slate-950 border border-slate-700/80 px-3 py-1.5 text-sm font-semibold text-slate-100 placeholder-slate-600 focus:outline-none focus:border-emerald-500 pr-12"
                            />
                            <div className="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">
                                {invoiceDiscountMode === 'percent' ? '%' : 'PKR'}
                            </div>
                        </div>

                        {/* Calculated deduction preview */}
                        {totals.invoiceDiscountPaisa > 0 && (
                            <div className="mt-1.5 flex justify-between items-center text-[11px] text-amber-400 font-medium px-1">
                                <span>Deduction:</span>
                                <span>- {pkr(totals.invoiceDiscountPaisa)}</span>
                            </div>
                        )}
                    </div>

                    {/* Summary Totals Calculation */}
                    <div className="flex-1 space-y-2.5 border-t border-b border-slate-800/80 py-3.5 my-1 text-sm">
                        <div className="flex justify-between text-slate-400">
                            <span>Subtotal</span>
                            <span className="font-semibold text-slate-200">{pkr(totals.subtotalPaisa)}</span>
                        </div>
                        {totals.discountPaisa > 0 && (
                            <div className="flex justify-between text-rose-400">
                                <span>Total Savings</span>
                                <span className="font-semibold">- {pkr(totals.discountPaisa)}</span>
                            </div>
                        )}
                        {config.tax_percentage > 0 && (
                            <div className="flex justify-between text-slate-400">
                                <span>Tax ({config.tax_percentage}%)</span>
                                <span className="font-semibold text-slate-200">{pkr(totals.taxPaisa)}</span>
                            </div>
                        )}
                        <div className="pt-2 border-t border-slate-800 flex justify-between items-baseline">
                            <span className="text-base font-bold text-slate-100">Payable Total</span>
                            <span className="text-3xl font-black text-emerald-400 tracking-tight">{pkr(totals.netTotalPaisa)}</span>
                        </div>
                    </div>

                    {/* Big Pay / Finalize Button */}
                    <button
                        type="button"
                        onClick={openPayment}
                        disabled={cart.length === 0}
                        className="w-full flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 py-3.5 text-base font-bold text-white shadow-lg shadow-emerald-600/30 hover:from-emerald-500 hover:to-teal-500 active:scale-[0.98] disabled:opacity-40 disabled:cursor-not-allowed transition"
                    >
                        <span>Checkout / Payment</span>
                        <kbd className="font-mono text-xs bg-emerald-800/70 border border-emerald-400/30 px-2 py-0.5 rounded text-white">F9</kbd>
                        <ArrowRight className="h-5 w-5 ml-1" />
                    </button>
                </div>
            </div>

            {/* Payment Modal */}
            {isPaymentOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-md p-4 animate-in fade-in duration-150">
                    <div className="w-full max-w-lg rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl">
                        <div className="flex items-center justify-between pb-3.5 border-b border-slate-800">
                            <div>
                                <h3 className="text-lg font-bold text-white flex items-center gap-2">
                                    <Sparkles className="h-5 w-5 text-emerald-400" />
                                    Complete Transaction
                                </h3>
                                <p className="text-xs text-slate-400">Confirm payment method and tender</p>
                            </div>
                            <button onClick={() => setIsPaymentOpen(false)} className="rounded-lg p-1.5 text-slate-400 hover:text-white hover:bg-slate-800 transition">
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        {/* Large Payable Header */}
                        <div className="my-3.5 rounded-xl border border-slate-800 bg-slate-950 p-3.5 text-center">
                            <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">Net Payable Amount</span>
                            <div className="text-3xl font-black text-emerald-400 mt-1">{pkr(totals.netTotalPaisa)}</div>
                        </div>

                        {/* Payment Method Selector */}
                        <div className="grid grid-cols-3 gap-2 mb-3.5">
                            {[
                                { key: 'cash', label: 'Cash Tender', icon: DollarSign, color: 'text-emerald-400' },
                                { key: 'card', label: 'Card Machine', icon: CreditCard, color: 'text-blue-400' },
                                { key: 'jazzcash', label: 'JazzCash', icon: Coins, color: 'text-amber-400' },
                                { key: 'easypaisa', label: 'Easypaisa', icon: Coins, color: 'text-emerald-300' },
                                { key: 'bank_transfer', label: 'Bank Transfer', icon: CreditCard, color: 'text-cyan-400' },
                                { key: 'credit', label: 'Khata (Credit)', icon: User, color: 'text-indigo-400' },
                            ].map((pm) => (
                                <button
                                    key={pm.key}
                                    type="button"
                                    onClick={() => setPaymentMethod(pm.key as any)}
                                    className={`flex flex-col items-center justify-center rounded-xl p-2.5 text-xs font-semibold border transition-all ${
                                        paymentMethod === pm.key
                                            ? 'border-emerald-500 bg-emerald-500/20 text-emerald-200 shadow-md ring-1 ring-emerald-500/40'
                                            : 'border-slate-800 bg-slate-950 text-slate-400 hover:bg-slate-800/80'
                                    }`}
                                >
                                    <pm.icon className={`h-4 w-4 mb-1 ${pm.color}`} />
                                    <span>{pm.label}</span>
                                </button>
                            ))}
                        </div>

                        {/* Cash Tender Input with Pakistani Rupee Quick Buttons & Smart Change Breakdown */}
                        {paymentMethod === 'cash' && (
                            <div className="space-y-3 mb-3.5 rounded-xl border border-slate-800 bg-slate-950 p-3.5">
                                <label className="block text-xs font-semibold text-slate-300 flex justify-between items-center">
                                    <span>Cash Tendered by Customer (PKR)</span>
                                    <span className="text-[11px] text-slate-500">Tap note or enter amount</span>
                                </label>
                                <input
                                    ref={receivedInputRef}
                                    type="number"
                                    min="0"
                                    value={receivedPaisa / 100}
                                    onChange={(e) => setReceivedPaisa(Math.max(0, Math.round(Number(e.target.value) * 100)))}
                                    className="w-full rounded-xl bg-slate-900 border border-slate-700 px-3.5 py-2 text-2xl font-black text-white focus:outline-none focus:border-emerald-500"
                                />

                                <div>
                                    <div className="grid grid-cols-4 gap-1.5">
                                        <button
                                            type="button"
                                            onClick={() => setReceivedPaisa(totals.netTotalPaisa)}
                                            className="rounded-lg bg-slate-800 border border-slate-700 py-1.5 text-xs font-bold text-slate-200 hover:bg-slate-700 transition"
                                        >
                                            Exact Bill
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setReceivedPaisa(500 * 100)}
                                            className="rounded-lg note-btn-500 py-1.5 text-xs font-bold transition shadow-sm"
                                        >
                                            Rs 500
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setReceivedPaisa(1000 * 100)}
                                            className="rounded-lg note-btn-1000 py-1.5 text-xs font-bold transition shadow-sm"
                                        >
                                            Rs 1,000
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setReceivedPaisa(5000 * 100)}
                                            className="rounded-lg note-btn-5000 py-1.5 text-xs font-bold transition shadow-sm"
                                        >
                                            Rs 5,000
                                        </button>
                                    </div>
                                    <div className="grid grid-cols-3 gap-1.5 mt-1.5">
                                        <button
                                            type="button"
                                            onClick={() => addCashTender(100)}
                                            className="rounded-lg bg-slate-800/80 border border-slate-700 py-1 text-[11px] font-semibold text-slate-300 hover:bg-slate-700 transition"
                                        >
                                            + Rs 100
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => addCashTender(500)}
                                            className="rounded-lg bg-slate-800/80 border border-slate-700 py-1 text-[11px] font-semibold text-slate-300 hover:bg-slate-700 transition"
                                        >
                                            + Rs 500
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => addCashTender(1000)}
                                            className="rounded-lg bg-slate-800/80 border border-slate-700 py-1 text-[11px] font-semibold text-slate-300 hover:bg-slate-700 transition"
                                        >
                                            + Rs 1,000
                                        </button>
                                    </div>
                                </div>

                                {/* Intelligent Change Due & Return Advice */}
                                <div className="pt-2 border-t border-slate-800">
                                    {receivedPaisa >= totals.netTotalPaisa ? (
                                        <div className="rounded-xl bg-emerald-500/10 border border-emerald-500/30 p-2.5">
                                            <div className="flex justify-between items-center">
                                                <span className="text-xs font-semibold text-emerald-300">Change Due:</span>
                                                <span className="text-xl font-black text-emerald-400">{pkr(changePaisa)}</span>
                                            </div>
                                            {changeBreakdown.length > 0 && (
                                                <div className="text-[11px] text-emerald-300/80 mt-1 flex items-center gap-1">
                                                    <span>Return:</span>
                                                    <span className="font-mono font-bold text-white">
                                                        {changeBreakdown.join(' • ')}
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    ) : (
                                        <div className="flex justify-between items-center rounded-xl bg-amber-500/10 border border-amber-500/30 p-2.5">
                                            <span className="text-xs font-semibold text-amber-300">Remaining Due:</span>
                                            <span className="text-base font-bold text-amber-400">{pkr(shortPaisa)}</span>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        {paymentMethod === 'credit' && (
                            <div className="mb-3.5 rounded-xl border border-indigo-500/30 bg-indigo-500/10 p-3 text-xs text-indigo-300">
                                <div className="font-semibold text-sm mb-1 text-white">Khata Credit Sale</div>
                                This sale will be charged to: <strong>{selectedCustomer?.name || 'No Customer Selected'}</strong>.
                                {!selectedCustomerId && (
                                    <div className="font-bold text-rose-400 mt-1.5 flex items-center gap-1.5">
                                        <AlertTriangle className="h-4 w-4" />
                                        Please select an existing customer account first.
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Confirmation Buttons */}
                        <div className="flex gap-2.5">
                            <button
                                type="button"
                                onClick={() => setIsPaymentOpen(false)}
                                className="flex-1 rounded-xl border border-slate-700 py-2.5 text-sm font-semibold text-slate-300 hover:bg-slate-800 transition"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={handleCheckout}
                                disabled={isSubmitting || (paymentMethod === 'credit' && !selectedCustomerId)}
                                className="flex-1 flex items-center justify-center gap-2 rounded-xl bg-emerald-600 py-2.5 text-sm font-bold text-white hover:bg-emerald-500 shadow-lg shadow-emerald-600/30 disabled:opacity-40 transition"
                            >
                                {isSubmitting ? (
                                    <div className="flex items-center gap-2">
                                        <div className="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full" />
                                        <span>Finalizing...</span>
                                    </div>
                                ) : (
                                    <>
                                        <CheckCircle className="h-4 w-4" />
                                        <span>Confirm & Print Sale</span>
                                    </>
                                )}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Quick Customer Registration Modal */}
            {isNewCustomerOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-sm p-4 animate-in fade-in">
                    <div className="w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-2xl">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-800">
                            <h3 className="font-bold text-base text-white flex items-center gap-2">
                                <UserPlus className="h-4 w-4 text-emerald-400" />
                                <span>Create Customer Khata</span>
                            </h3>
                            <button onClick={() => setIsNewCustomerOpen(false)} className="rounded p-1 text-slate-400 hover:text-white">
                                <X className="h-4 w-4" />
                            </button>
                        </div>

                        <form onSubmit={handleCreateCustomer} className="space-y-3 my-3.5 text-xs">
                            <div>
                                <label className="block font-semibold text-slate-300 mb-1">Customer Full Name *</label>
                                <input
                                    type="text"
                                    required
                                    value={newCustomerForm.name}
                                    onChange={(e) => setNewCustomerForm({ ...newCustomerForm, name: e.target.value })}
                                    placeholder="e.g. Ch. Muhammad Akram"
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                                    autoFocus
                                />
                            </div>

                            <div>
                                <label className="block font-semibold text-slate-300 mb-1">Mobile Phone Number</label>
                                <input
                                    type="text"
                                    value={newCustomerForm.phone}
                                    onChange={(e) => setNewCustomerForm({ ...newCustomerForm, phone: e.target.value })}
                                    placeholder="0300-1234567"
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                                />
                            </div>

                            <div>
                                <label className="block font-semibold text-slate-300 mb-1">Credit Limit (PKR)</label>
                                <input
                                    type="number"
                                    min="0"
                                    step="500"
                                    value={newCustomerForm.credit_limit}
                                    onChange={(e) => setNewCustomerForm({ ...newCustomerForm, credit_limit: Number(e.target.value) })}
                                    className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                                />
                            </div>

                            <div className="flex gap-2.5 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setIsNewCustomerOpen(false)}
                                    className="flex-1 rounded-xl border border-slate-700 py-2 font-semibold text-slate-300 hover:bg-slate-800 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSavingCustomer || !newCustomerForm.name.trim()}
                                    className="flex-1 rounded-xl bg-emerald-600 py-2 font-bold text-white hover:bg-emerald-500 disabled:opacity-50 transition"
                                >
                                    {isSavingCustomer ? 'Saving...' : 'Save & Select'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Line Discount Modal */}
            {lineDiscountModal && cart[lineDiscountModal.index] && (() => {
                const curLine = cart[lineDiscountModal.index];
                const lineSubtotal = curLine.quantity * curLine.unitPricePaisa;
                const calcDiscountPaisa =
                    lineDiscountModal.mode === 'percent'
                        ? Math.round((lineSubtotal * (lineDiscountModal.value || 0)) / 100)
                        : Math.round((lineDiscountModal.value || 0) * 100);
                const boundedDiscountPaisa = Math.min(lineSubtotal, Math.max(0, calcDiscountPaisa));
                const finalLineTotalPaisa = Math.max(0, lineSubtotal - boundedDiscountPaisa);

                return (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-sm p-4 animate-in fade-in">
                        <div className="w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-2xl">
                            <div className="flex items-center justify-between pb-3 border-b border-slate-800">
                                <h3 className="font-bold text-sm text-white flex items-center gap-2">
                                    <Tag className="h-4 w-4 text-amber-400" />
                                    <span>Item Discount — {curLine.medicine.brand_name}</span>
                                </h3>
                                <button
                                    onClick={() => setLineDiscountModal(null)}
                                    className="rounded p-1 text-slate-400 hover:text-white"
                                >
                                    <X className="h-4 w-4" />
                                </button>
                            </div>

                            <div className="my-4 space-y-4">
                                {/* Item Details Summary */}
                                <div className="flex items-center justify-between p-2.5 rounded-xl bg-slate-950 border border-slate-800 text-xs">
                                    <div>
                                        <div className="font-semibold text-slate-200">{curLine.selectedPackage.name}</div>
                                        <div className="text-slate-400">Qty: {curLine.quantity} × {pkr(curLine.unitPricePaisa)}</div>
                                    </div>
                                    <div className="text-right">
                                        <div className="text-slate-400 text-[10px] uppercase font-bold tracking-wider">Subtotal</div>
                                        <div className="text-sm font-bold text-slate-200">{pkr(lineSubtotal)}</div>
                                    </div>
                                </div>

                                {/* Mode Switcher: % vs PKR */}
                                <div>
                                    <label className="block text-xs font-semibold text-slate-400 mb-1.5">Discount Type</label>
                                    <div className="grid grid-cols-2 gap-2 p-1 rounded-xl bg-slate-950 border border-slate-800">
                                        <button
                                            type="button"
                                            onClick={() => setLineDiscountModal({
                                                ...lineDiscountModal,
                                                mode: 'percent',
                                                value: lineDiscountModal.mode === 'percent' ? lineDiscountModal.value : (lineSubtotal > 0 ? Math.round((boundedDiscountPaisa / lineSubtotal) * 100) : 0),
                                            })}
                                            className={`flex items-center justify-center gap-2 py-2 rounded-lg text-xs font-bold transition ${
                                                lineDiscountModal.mode === 'percent'
                                                    ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40'
                                                    : 'text-slate-400 hover:text-slate-200'
                                            }`}
                                        >
                                            <Percent className="h-3.5 w-3.5" />
                                            <span>Percentage (%)</span>
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setLineDiscountModal({
                                                ...lineDiscountModal,
                                                mode: 'fixed',
                                                value: lineDiscountModal.mode === 'fixed' ? lineDiscountModal.value : boundedDiscountPaisa / 100,
                                            })}
                                            className={`flex items-center justify-center gap-2 py-2 rounded-lg text-xs font-bold transition ${
                                                lineDiscountModal.mode === 'fixed'
                                                    ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                                                    : 'text-slate-400 hover:text-slate-200'
                                            }`}
                                        >
                                            <Tag className="h-3.5 w-3.5" />
                                            <span>Fixed Amount (PKR)</span>
                                        </button>
                                    </div>
                                </div>

                                {/* Presets */}
                                <div>
                                    <label className="block text-xs font-semibold text-slate-400 mb-1.5">Quick Presets</label>
                                    <div className="flex gap-1.5 overflow-x-auto pb-1">
                                        {lineDiscountModal.mode === 'percent' ? (
                                            [0, 5, 10, 15, 20, 25].map((pct) => (
                                                <button
                                                    key={pct}
                                                    type="button"
                                                    onClick={() => setLineDiscountModal({ ...lineDiscountModal, value: pct })}
                                                    className={`px-2.5 py-1 rounded-lg text-xs font-semibold transition ${
                                                        lineDiscountModal.value === pct
                                                            ? 'bg-amber-500 text-slate-950 font-bold'
                                                            : 'bg-slate-800 hover:bg-slate-700 text-slate-300'
                                                    }`}
                                                >
                                                    {pct === 0 ? 'Clear' : `${pct}%`}
                                                </button>
                                            ))
                                        ) : (
                                            [0, 20, 50, 100, 200, 500].map((amt) => (
                                                <button
                                                    key={amt}
                                                    type="button"
                                                    onClick={() => setLineDiscountModal({ ...lineDiscountModal, value: amt })}
                                                    className={`px-2.5 py-1 rounded-lg text-xs font-semibold transition ${
                                                        lineDiscountModal.value === amt
                                                            ? 'bg-emerald-500 text-slate-950 font-bold'
                                                            : 'bg-slate-800 hover:bg-slate-700 text-slate-300'
                                                    }`}
                                                >
                                                    {amt === 0 ? 'Clear' : `Rs ${amt}`}
                                                </button>
                                            ))
                                        )}
                                    </div>
                                </div>

                                {/* Value Input */}
                                <div>
                                    <label className="block text-xs font-semibold text-slate-300 mb-1.5">
                                        {lineDiscountModal.mode === 'percent' ? 'Discount Percentage (%)' : 'Discount Amount (PKR)'}
                                    </label>
                                    <div className="relative">
                                        <input
                                            type="number"
                                            min="0"
                                            max={lineDiscountModal.mode === 'percent' ? 100 : lineSubtotal / 100}
                                            step={lineDiscountModal.mode === 'percent' ? '0.5' : '1'}
                                            value={lineDiscountModal.value > 0 ? lineDiscountModal.value : ''}
                                            onChange={(e) => {
                                                const val = Math.max(0, Number(e.target.value) || 0);
                                                setLineDiscountModal({
                                                    ...lineDiscountModal,
                                                    value: lineDiscountModal.mode === 'percent' ? Math.min(100, val) : val,
                                                });
                                            }}
                                            placeholder={lineDiscountModal.mode === 'percent' ? 'e.g. 10' : 'e.g. 50'}
                                            className="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2.5 text-base font-bold text-white focus:outline-none focus:border-emerald-500 pr-12"
                                            autoFocus
                                        />
                                        <div className="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">
                                            {lineDiscountModal.mode === 'percent' ? '%' : 'PKR'}
                                        </div>
                                    </div>
                                </div>

                                {/* Live Preview Box */}
                                <div className="rounded-xl bg-slate-950/80 border border-slate-800/80 p-3 space-y-1.5 text-xs">
                                    <div className="flex justify-between text-slate-400">
                                        <span>Original Total</span>
                                        <span>{pkr(lineSubtotal)}</span>
                                    </div>
                                    <div className="flex justify-between text-amber-400 font-medium">
                                        <span>Discount Deducted ({lineDiscountModal.mode === 'percent' ? `${lineDiscountModal.value}%` : 'Fixed'})</span>
                                        <span>- {pkr(boundedDiscountPaisa)}</span>
                                    </div>
                                    <div className="pt-1.5 border-t border-slate-800 flex justify-between text-emerald-400 font-bold text-sm">
                                        <span>Net Line Total</span>
                                        <span>{pkr(finalLineTotalPaisa)}</span>
                                    </div>
                                </div>

                                {/* Actions */}
                                <div className="flex gap-2 pt-2">
                                    <button
                                        type="button"
                                        onClick={() => applyLineDiscount(lineDiscountModal.index, 'fixed', 0)}
                                        className="rounded-xl border border-slate-700 px-4 py-2.5 text-xs font-semibold text-slate-300 hover:bg-slate-800 transition"
                                    >
                                        Remove Discount
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setLineDiscountModal(null)}
                                        className="rounded-xl border border-slate-800 px-4 py-2.5 text-xs font-semibold text-slate-400 hover:bg-slate-800 hover:text-white transition"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => applyLineDiscount(lineDiscountModal.index, lineDiscountModal.mode, lineDiscountModal.value)}
                                        className="flex-1 rounded-xl bg-emerald-600 hover:bg-emerald-500 py-2.5 text-xs font-bold text-white shadow-lg shadow-emerald-950 transition"
                                    >
                                        Apply Discount
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                );
            })()}

            {/* Held Bills Drawer */}
            {heldDrawerOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-end bg-black/60 backdrop-blur-sm animate-in fade-in">
                    <div className="h-full w-96 border-l border-slate-800 bg-slate-900 p-5 flex flex-col shadow-2xl">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-800">
                            <h3 className="font-bold text-white flex items-center gap-2">
                                <PlayCircle className="h-5 w-5 text-blue-400" />
                                Parked Bills ({heldBills.length})
                            </h3>
                            <button onClick={() => setHeldDrawerOpen(false)} className="rounded p-1 text-slate-400 hover:text-white">
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <div className="flex-1 overflow-y-auto divide-y divide-slate-800 my-4">
                            {heldBills.length === 0 ? (
                                <div className="text-center text-slate-500 text-xs py-12">No held bills parked.</div>
                            ) : (
                                heldBills.map((hb) => (
                                    <div key={hb.id} className="py-3 flex items-center justify-between">
                                        <div>
                                            <div className="font-semibold text-slate-200 text-sm">{hb.title}</div>
                                            <div className="text-xs text-slate-400">Parked at {hb.timestamp}</div>
                                        </div>
                                        <div className="flex gap-2">
                                            <button
                                                type="button"
                                                onClick={() => resumeHeldBill(hb)}
                                                className="rounded-lg bg-blue-600/20 text-blue-400 hover:bg-blue-600/30 px-2.5 py-1 text-xs font-semibold"
                                            >
                                                Resume
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => discardHeldBill(hb.id)}
                                                className="rounded-lg bg-rose-600/20 text-rose-400 hover:bg-rose-600/30 p-1 text-xs"
                                                title="Discard"
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </div>
            )}

            {/* Quick Cashier Switch Modal (4-Digit PIN) */}
            {isSwitchCashierOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-md p-4 animate-in fade-in duration-200">
                    <div className="w-full max-w-md rounded-3xl border border-slate-800 bg-slate-900/95 p-6 shadow-2xl backdrop-blur-xl">
                        <div className="flex items-center justify-between pb-4 border-b border-slate-800">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    <KeyRound className="h-5 w-5" />
                                </div>
                                <div>
                                    <h3 className="font-bold text-white text-base">Quick Cashier Switch</h3>
                                    <p className="text-xs text-slate-400">Switch terminal operator with 4-digit PIN</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => {
                                    setIsSwitchCashierOpen(false);
                                    setSwitchPinCode('');
                                    setSelectedSwitchStaff(null);
                                    setSwitchCashierError(null);
                                }}
                                className="rounded-xl p-2 text-slate-400 hover:bg-slate-800 hover:text-white transition"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        {switchCashierError && (
                            <div className="mt-4 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-300 flex items-center gap-2">
                                <AlertCircle className="h-4 w-4 shrink-0 text-rose-400" />
                                <span>{switchCashierError}</span>
                            </div>
                        )}

                        {!selectedSwitchStaff ? (
                            <div className="mt-4 space-y-3">
                                <div className="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                    Select Cashier / Staff Member
                                </div>
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-64 overflow-y-auto pr-1">
                                    {activeStaff.map((staff) => (
                                        <button
                                            key={staff.id}
                                            type="button"
                                            onClick={() => {
                                                setSelectedSwitchStaff(staff);
                                                setSwitchPinCode('');
                                                setSwitchCashierError(null);
                                            }}
                                            className="flex flex-col items-start p-3 rounded-2xl border border-slate-800 bg-slate-950/60 hover:bg-emerald-500/10 hover:border-emerald-500/30 text-left transition group"
                                        >
                                            <div className="font-bold text-slate-200 text-sm group-hover:text-emerald-300">
                                                {staff.name}
                                            </div>
                                            <div className="mt-1 flex items-center gap-1.5">
                                                <span className="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 border border-slate-700">
                                                    {staff.role}
                                                </span>
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        ) : (
                            <div className="mt-4 space-y-4">
                                <div className="flex items-center justify-between bg-slate-950/60 p-3 rounded-2xl border border-slate-800">
                                    <div className="flex items-center gap-2.5">
                                        <div className="h-8 w-8 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center font-bold text-xs">
                                            {selectedSwitchStaff.name.slice(0, 2).toUpperCase()}
                                        </div>
                                        <div>
                                            <div className="font-bold text-slate-200 text-sm">{selectedSwitchStaff.name}</div>
                                            <div className="text-[10px] text-slate-400 uppercase tracking-wider">{selectedSwitchStaff.role}</div>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setSelectedSwitchStaff(null);
                                            setSwitchPinCode('');
                                            setSwitchCashierError(null);
                                        }}
                                        className="text-xs text-slate-400 hover:text-white px-2 py-1 rounded-lg hover:bg-slate-800 transition"
                                    >
                                        Change Staff
                                    </button>
                                </div>

                                {/* PIN Dots */}
                                <div className="flex justify-center items-center gap-4 py-3 bg-slate-950/40 rounded-2xl border border-slate-800/60">
                                    {[0, 1, 2, 3].map((idx) => (
                                        <div
                                            key={idx}
                                            className={`h-4 w-4 rounded-full border-2 transition-all duration-200 ${
                                                switchPinCode.length > idx
                                                    ? 'bg-emerald-400 border-emerald-400 shadow-[0_0_12px_rgba(52,211,153,0.6)] scale-110'
                                                    : 'border-slate-700 bg-slate-950'
                                            }`}
                                        />
                                    ))}
                                </div>

                                {/* Keypad */}
                                <div className="grid grid-cols-3 gap-2">
                                    {['1', '2', '3', '4', '5', '6', '7', '8', '9'].map((digit) => (
                                        <button
                                            key={digit}
                                            type="button"
                                            disabled={isSwitchingCashier}
                                            onClick={() => handleSwitchCashierDigit(digit)}
                                            className="h-12 rounded-xl bg-slate-800/80 hover:bg-slate-700 active:bg-emerald-600 font-bold text-lg text-white border border-slate-700/60 transition disabled:opacity-50"
                                        >
                                            {digit}
                                        </button>
                                    ))}
                                    <button
                                        type="button"
                                        disabled={isSwitchingCashier || switchPinCode.length === 0}
                                        onClick={handleSwitchCashierClear}
                                        className="h-12 rounded-xl bg-slate-800/40 hover:bg-rose-500/20 text-xs font-semibold text-slate-400 hover:text-rose-300 border border-slate-800 transition disabled:opacity-30"
                                    >
                                        Clear
                                    </button>
                                    <button
                                        type="button"
                                        disabled={isSwitchingCashier}
                                        onClick={() => handleSwitchCashierDigit('0')}
                                        className="h-12 rounded-xl bg-slate-800/80 hover:bg-slate-700 active:bg-emerald-600 font-bold text-lg text-white border border-slate-700/60 transition disabled:opacity-50"
                                    >
                                        0
                                    </button>
                                    <button
                                        type="button"
                                        disabled={isSwitchingCashier || switchPinCode.length === 0}
                                        onClick={handleSwitchCashierBackspace}
                                        className="h-12 rounded-xl bg-slate-800/40 hover:bg-slate-700 text-xs font-semibold text-slate-400 hover:text-white border border-slate-800 transition disabled:opacity-30"
                                    >
                                        ⌫ Back
                                    </button>
                                </div>

                                <div className="text-center text-[11px] text-slate-500">
                                    Numpad or physical keyboard supported
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
