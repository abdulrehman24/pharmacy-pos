import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import { Printer, ArrowLeft, Check, AlertCircle, Wifi } from 'lucide-react';

type ReceiptItem = {
    id: number;
    name: string;
    quantity: number;
    unit: string;
    price_paisa: number;
    total_paisa: number;
};

export type ReceiptData = {
    id?: number;
    number: string;
    timestamp: string; // ISO 8601
    local_datetime?: string;
    cashier: string;
    copy: boolean;
    items: ReceiptItem[];
    subtotal_paisa: number;
    discount_paisa: number;
    tax_paisa: number;
    total_paisa: number;
    received_paisa: number;
    change_paisa: number;
    balance_paisa: number;
    payment_label: string;
};

type Props = {
    sale: ReceiptData;
    shop: { name: string; address: string; phone: string; footer: string };
    paperWidth: 58 | 80;
};

function money(paisa: number) {
    if (!Number.isSafeInteger(paisa)) return 'PKR 0.00';
    return 'PKR ' + (paisa / 100).toFixed(2);
}

export default function Receipt({ sale, shop, paperWidth = 80 }: Props) {
    const [lanPrinting, setLanPrinting] = useState(false);
    const [lanStatus, setLanStatus] = useState<string | null>(null);

    const printedAt = new Intl.DateTimeFormat('en-PK', {
        timeZone: 'Asia/Karachi',
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(sale.timestamp));

    const summaries = [
        ['Subtotal', sale.subtotal_paisa],
        ['Discount', sale.discount_paisa],
        ['Tax', sale.tax_paisa],
        ['Total', sale.total_paisa],
        ['Received', sale.received_paisa],
        ['Change', sale.change_paisa],
        ['Balance', sale.balance_paisa],
    ] as const;

    const triggerLanPrint = async () => {
        setLanPrinting(true);
        setLanStatus(null);
        try {
            const res = await axios.post(`/sales/${sale.id}/print`);
            setLanStatus(res.data.message || 'LAN print job submitted.');
        } catch (err: any) {
            setLanStatus(err.response?.data?.message || 'Failed to communicate with LAN ESC/POS printer.');
        } finally {
            setLanPrinting(false);
        }
    };

    return (
        <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col items-center p-4">
            <Head title={'Receipt ' + sale.number} />

            <style>{`
                @page { size: auto; margin: 3mm; }
                .receipt {
                    font: 12px 'Courier New', Courier, monospace, Arial, sans-serif;
                    color: #000;
                    background: #fff;
                    margin: 8px auto;
                    overflow-wrap: anywhere;
                    padding: 8px 12px;
                    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                }
                .receipt h1 { font-size: 16px; font-weight: bold; margin: 0 0 4px; text-transform: uppercase; }
                .receipt .center { text-align: center; }
                .receipt .row { display: flex; justify-content: space-between; gap: 6px; }
                .receipt .item { padding: 4px 0; border-bottom: 1px dashed #777; }
                .receipt .totals { border-top: 1px dashed #777; padding-top: 4px; }
                @media print {
                    body { background: #fff !important; }
                    .receipt-controls { display: none !important; }
                    .receipt { margin: 0; box-shadow: none; padding: 0; }
                    .receipt .item, .receipt .totals { break-inside: avoid; }
                }
            `}</style>

            {/* Navigation & Controls Header (Hidden during Print) */}
            <div className="receipt-controls w-full max-w-xl mb-6 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-xl">
                <Link
                    href={route('pos.index')}
                    className="flex items-center gap-2 rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-700 hover:text-white transition"
                >
                    <ArrowLeft className="h-4 w-4" />
                    <span>Back to POS</span>
                </Link>

                <div className="flex items-center gap-2">
                    {/* LAN ESC/POS Print */}
                    <button
                        onClick={triggerLanPrint}
                        disabled={lanPrinting}
                        className="flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-800/80 px-3.5 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-700 hover:text-white transition"
                        title="Direct TCP ESC/POS socket printing"
                    >
                        <Wifi className="h-4 w-4 text-emerald-400" />
                        <span>LAN Print</span>
                    </button>

                    {/* Browser Print Dialog */}
                    <button
                        onClick={async () => {
                            await document.fonts.ready;
                            window.print();
                        }}
                        className="flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-lg shadow-emerald-600/30 hover:bg-emerald-500 transition active:scale-95"
                    >
                        <Printer className="h-4 w-4" />
                        <span>Print Receipt</span>
                    </button>
                </div>
            </div>

            {lanStatus && (
                <div className="receipt-controls mb-4 max-w-xl text-center text-xs px-4 py-2 rounded-lg bg-slate-800 text-slate-300 border border-slate-700">
                    {lanStatus}
                </div>
            )}

            {/* Thermal Receipt Body */}
            <main
                className="receipt"
                style={{ width: paperWidth === 58 ? '50mm' : '72mm' }}
            >
                <header className="center mb-2">
                    <h1>{shop.name}</h1>
                    <div style={{ fontSize: '11px' }}>{shop.address}</div>
                    <div style={{ fontSize: '11px' }}>{shop.phone}</div>
                    {sale.copy && (
                        <div style={{ margin: '4px 0', fontWeight: 'bold', border: '1px dashed #000', padding: '2px' }}>
                            *** DUPLICATE COPY ***
                        </div>
                    )}
                </header>

                <div style={{ fontSize: '11px', borderBottom: '1px solid #000', paddingBottom: '4px', marginBottom: '4px' }}>
                    <div>Invoice: <strong>{sale.number}</strong></div>
                    <div>Date: {printedAt}</div>
                    <div>Cashier: {sale.cashier}</div>
                </div>

                {/* Line Items */}
                <section>
                    {sale.items.map((item) => (
                        <div className="item" key={item.id}>
                            <div style={{ fontWeight: 600 }}>{item.name}</div>
                            <div className="row">
                                <span>
                                    {item.quantity} {item.unit} @ {money(item.price_paisa)}
                                </span>
                                <span>{money(item.total_paisa)}</span>
                            </div>
                        </div>
                    ))}
                </section>

                {/* Totals */}
                <section className="totals mt-2">
                    {summaries.map(([label, amount]) => (
                        <div className="row" key={label} style={{ fontSize: '11px' }}>
                            <span>{label}</span>
                            <span style={{ fontWeight: label === 'Total' ? 'bold' : 'normal' }}>
                                {money(amount)}
                            </span>
                        </div>
                    ))}
                    <div style={{ fontSize: '11px', marginTop: '4px' }}>
                        Method: <strong>{sale.payment_label}</strong>
                    </div>
                </section>

                {/* Footer */}
                <p className="center mt-4" style={{ fontSize: '10px', borderTop: '1px dashed #777', paddingTop: '4px' }}>
                    {shop.footer}
                </p>
            </main>
        </div>
    );
}
