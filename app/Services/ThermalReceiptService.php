<?php

namespace App\Services;

use InvalidArgumentException;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;

final class ThermalReceiptService
{
    private function ascii(string $text): string
    {
        // Transliterate Urdu/Arabic numerals to standard digits
        $urduDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $latinDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $normalized = str_replace($urduDigits, $latinDigits, $text);

        // Transliterate UTF-8 text if iconv is available
        if (function_exists('iconv')) {
            $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
            if ($translit !== false && strlen(trim($translit)) > 0) {
                return preg_replace('/[^\x20-\x7E]/', '', $translit) ?? '';
            }
        }

        // Clean non-printable control characters
        $clean = preg_replace('/[^\x20-\x7E]/', '', $normalized);
        return $clean ?: $normalized;
    }

    private function money(int $paisa): string
    {
        if ($paisa < 0) {
            return '-PKR ' . intdiv(abs($paisa), 100) . '.' . str_pad((string) (abs($paisa) % 100), 2, '0', STR_PAD_LEFT);
        }
        return 'PKR ' . intdiv($paisa, 100) . '.' . str_pad((string) ($paisa % 100), 2, '0', STR_PAD_LEFT);
    }

    public function send(
        array $receipt,
        string $host,
        int $port = 9100,
        int $columns = 42,
        bool $hasCutter = false
    ): void {
        if ($columns < 24 || $columns > 64 || $port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Invalid printer profile.');
        }

        // Prepare and validate all text BEFORE contacting the printer.
        $lines = [];
        $add = function (string $text) use (&$lines, $columns): void {
            $text = $this->ascii($text);
            foreach (explode("\n", wordwrap($text, $columns, "\n", true)) as $line) {
                $lines[] = $line;
            }
        };

        $pair = function (string $label, string $value) use (&$lines, $columns, $add): void {
            $label = $this->ascii($label);
            $value = $this->ascii($value);
            if (strlen($label) + strlen($value) + 1 <= $columns) {
                $lines[] = $label . str_repeat(' ', $columns - strlen($label) - strlen($value)) . $value;
            } else {
                $add($label);
                $add($value);
            }
        };

        $shop = $receipt['shop'];
        $sale = $receipt['sale'];

        $add($shop['name']);
        $add($shop['address']);
        $add($shop['phone']);
        if (!empty($sale['copy'])) {
            $add('*** DUPLICATE COPY ***');
        }
        $add('Invoice: ' . $sale['number']);
        $add(($sale['local_datetime'] ?? date('d-M-Y h:i A')) . ' PKT');
        $add('Cashier: ' . $sale['cashier']);
        $add(str_repeat('-', $columns));

        foreach ($sale['items'] as $item) {
            $add($item['name']);
            $pair(
                $item['quantity'] . ' ' . $item['unit'] . ' @ ' . $this->money($item['price_paisa']),
                $this->money($item['total_paisa'])
            );
        }

        $add(str_repeat('-', $columns));

        foreach ([
            'Subtotal' => 'subtotal_paisa',
            'Discount' => 'discount_paisa',
            'Tax' => 'tax_paisa',
            'Total' => 'total_paisa',
            'Received' => 'received_paisa',
            'Change' => 'change_paisa',
            'Balance' => 'balance_paisa',
        ] as $label => $field) {
            if (isset($sale[$field])) {
                $pair($label, $this->money($sale[$field]));
            }
        }

        $add('Payment: ' . $sale['payment_label']);
        $add(str_repeat('-', $columns));
        $add($shop['footer']);

        // Connect and print
        $connector = new NetworkPrintConnector($host, $port, 3);
        $printer = null;
        try {
            $printer = new Printer($connector);
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->selectPrintMode(Printer::MODE_FONT_A);
            $printer->text(implode("\n", $lines) . "\n");
            $printer->feed(4);
            if ($hasCutter) {
                $printer->cut(Printer::CUT_PARTIAL);
            }
        } finally {
            if ($printer !== null) {
                $printer->close();
            } else {
                $connector->close();
            }
        }
    }
}
