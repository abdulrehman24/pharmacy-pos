# 🏥 Pakistan Pharmacy Management & POS System

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-11.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 11" />
  <img src="https://img.shields.io/badge/React-18-61DAFB?style=for-the-badge&logo=react&logoColor=black" alt="React 18" />
  <img src="https://img.shields.io/badge/TypeScript-5.x-3178C6?style=for-the-badge&logo=typescript&logoColor=white" alt="TypeScript" />
  <img src="https://img.shields.io/badge/Inertia.js-v1.x-9553E9?style=for-the-badge&logo=inertia&logoColor=white" alt="Inertia.js" />
  <img src="https://img.shields.io/badge/Tailwind_CSS-3.x-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white" alt="Tailwind CSS" />
  <img src="https://img.shields.io/badge/Database-MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL" />
  <img src="https://img.shields.io/badge/Currency-PKR_(Integer_Paisa)-006600?style=for-the-badge" alt="PKR Paisa" />
</p>

A comprehensive, production-grade **Pharmacy Management and Point of Sale (POS)** application specifically engineered for retail pharmacies, community medical stores, and hospital pharmacy dispensaries in Pakistan.

Designed for high-speed counter operations, automated **FEFO (First-Expiry-First-Out)** lot deduction, multi-unit packaging hierarchy (Tablets, Strips, Packs, Boxes, Syrups), customer credit (Khata), supplier receiving with bonus scheme amortizations, shift cash reconciliation, and 80mm thermal receipt printing.

---

## 📑 Table of Contents

- [System Architecture & Core Principles](#-system-architecture--core-principles)
- [Comprehensive Functional Modules](#-comprehensive-functional-modules)
  - [1. POS & Counter Billing Module](#1-pos--counter-billing-module)
  - [2. Medicine Catalogue & Master Data](#2-medicine-catalogue--master-data)
  - [3. Inventory & FEFO Batch Tracking](#3-inventory--fefo-batch-tracking)
  - [4. Purchases & Goods Receiving (GRN)](#4-purchases--goods-receiving-grn)
  - [5. Customer Accounts & Credit (Khata)](#5-customer-accounts--credit-khata)
  - [6. Supplier Payables & Returns](#6-supplier-payables--returns)
  - [7. Customer Returns & Refunds](#7-customer-returns--refunds)
  - [8. Cashier Shifts & Cash Drawers](#8-cashier-shifts--cash-drawers)
  - [9. Store Operating Expenses](#9-store-operating-expenses)
  - [10. Prescription Dispensing & Digital Rx](#10-prescription-dispensing--digital-rx)
  - [11. Business Reports & Stock Audits](#11-business-reports--stock-audits)
  - [12. Settings, Hardware & Thermal Printing](#12-settings-hardware--thermal-printing)
- [Keyboard Shortcuts](#-pos-keyboard-shortcuts)
- [One-Click Launchers (Mac & Windows)](#-one-click-launchers-for-non-technical-staff)
- [Default Seeded Credentials](#-default-seeded-credentials)
- [Installation & Local Setup](#-installation--local-setup)
- [Testing & Quality Assurance](#-testing--quality-assurance)

---

## 🏛 System Architecture & Core Principles

| Principle | Implementation Detail |
| :--- | :--- |
| **Integer Paisa Precision** | All monetary values are strictly computed and stored as integer paisas (`*_paisa`, where `1 PKR = 100 paisa`). This prevents floating-point rounding anomalies across bulk sales, partial refunds, and taxes. |
| **FEFO Batch Allocation** | During checkout, medicines are automatically deducted from the earliest-expiring active batches (**First-Expiry-First-Out**) to prevent stock wastage. |
| **Multi-Tier Packaging** | A medicine has a single base unit (e.g. `tablet` or `ml`) and multiple sales packaging definitions (e.g. `Strip of 10`, `Box of 100`) with custom packaging conversion factors and sell prices. |
| **100% Offline Capability** | Built-in offline audio synthesizer via Web Audio API (0 external MP3 files), localStorage held-bill backups, and local SQLite/MySQL persistence. |
| **Hardware Agnostic** | Standard 80mm thermal printer support via ESC/POS raw TCP/IP (port 9100) or browser-native raster printing with clean thermal cuts. |

---

## 📦 Comprehensive Functional Modules

### 1. POS & Counter Billing Module
* **High-Speed Live Search**: Instant debouncing search across Brand Names, Generic Molecules/Salts, Barcodes, and Shelf/Rack coordinates.
* **Direct Barcode Auto-Add**: Automatically detects single exact barcode hits and appends the item to the cart without cashier mouse intervention.
* **Multi-Packaging Switcher**: Effortlessly switch between Unit/Loose Tablets, Strips, or Full Boxes with automatic price recalculation.
* **Dual-Mode Discount Engine**:
  * **Line-Item Discounts**: Cashiers can click the discount tag (`🏷️`) on any cart item to apply either a **Percentage (%)** or **Fixed PKR** discount. Real-time preview calculates original amount, deduction, and net total. Percentage discounts dynamically rescale if quantity is adjusted.
  * **Special Invoice Discounts**: Switch between **%** and **PKR Fixed** modes in the right billing panel with quick preset chips (`5%`, `10%`, `15%`, `20%` / `Rs 50`, `Rs 100`, `Rs 200`, `Rs 500`).
* **Bill Parking / Holding**: Park incomplete sales (`F8`) into temporary memory when customers fetch additional items. Held bills can be resumed or discarded anytime.
* **Tender & Payment Types**:
  * Cash with quick banknote buttons (`+100`, `+500`, `+1,000`, `+5,000 PKR`) and instant change calculator.
  * Debit/Credit Card.
  * Customer Khata (Credit account balance check and credit limit validation).
  * Digital Wallets (JazzCash, EasyPaisa, Direct Bank Transfer).
* **Audio Feedback**: Synthesizes soft sine-wave confirmation beeps, error alerts, and success chimes completely offline.

### 2. Medicine Catalogue & Master Data
* **Comprehensive Medicine Records**: Tracks Brand Name, Generic / Salt Formula, Strength, Category, Manufacturer, Shelf Location, and Reorder Minimums.
* **Regulatory Compliance**:
  * **Narcotic / Controlled Substance** flags for strict prescription compliance.
  * **Prescription Required (Rx)** flags to notify cashiers before checkout.
  * **Cold Chain Indicators (2°C - 8°C)** with visual warning badges.
* **Packaging & Conversion Rates**: Defines multiple selling units per item with custom barcodes, packaging multiplier, and trade/retail prices.
* **Quick-Store Modals**: Add new Categories or Manufacturers on the fly without navigating away from the intake screen.

### 3. Inventory & FEFO Batch Tracking
* **Batch & Expiry Management**: Every received stock lot maintains its unique Batch Number, Manufacturing Date, and Expiry Date.
* **FEFO Order Auto-Enforcement**: Expired lots are automatically quarantined and blocked from checkout; oldest valid batches are dispensed first.
* **Stock Ledger & Audit Trail**: Full ledger history logging all movements: Purchases (`purchase_in`), Sales (`sale_out`), Customer Returns (`customer_return`), Supplier Returns (`supplier_return`), Adjustments (`adjustment`), and Write-Offs (`write_off`).
* **Physical Stock Counts**: Create scheduled stock audits, record physical vs system quantities, compute discrepancies, and apply manager-approved balance reconciliations.
* **Damaged / Expired Write-Offs**: Formal write-off process with reason codes (Expired, Broken/Damaged, Stolen, Clinical Waste).

### 4. Purchases & Goods Receiving (GRN)
* **Purchase Orders & Intake**: Record supplier deliveries with Supplier Invoice Numbers and Delivery Dates.
* **Multi-Batch Entry**: Intake multiple batches with different expiry dates and cost prices within a single purchase bill.
* **Bonus Quantity Handling**: Intelligently handles manufacturer bonus schemes (e.g. *Buy 100 + Get 10 Free*), auto-amortizing the cost over the total quantity to reflect the true effective unit cost.
* **Automated Stock Lot Creation**: Automatically injects stock into corresponding shelf locations and updates average purchase costs.

### 5. Customer Accounts & Credit (Khata)
* **Walk-In vs Registered Accounts**: Supports unregistered cash customers alongside dedicated credit accounts (Khata).
* **Credit Limit Enforcement**: Configure customer-specific maximum credit ceilings (`credit_limit_paisa`). Over-limit credit transactions are blocked with clear cashier warnings.
* **Outstanding Balances & Visual Meters**: Real-time progress indicators showing customer debt relative to their approved limit.
* **Payment Intake**: Receive partial or full Khata settlements with printable payment receipts and historical ledger balance updates.
* **Quick Customer Creation**: Cashiers can press `F4` at the counter to register a new customer in seconds.

### 6. Supplier Payables & Returns
* **Vendor Directory**: Manage pharmaceutical distributors and direct manufacturers with contact details and payment terms.
* **Payables Ledger**: Tracks total invoiced goods, payments released, and current outstanding liability per distributor.
* **Supplier Payments**: Record payments with cheque/transaction references.
* **Supplier Returns (Debit Notes)**: Return damaged or near-expiry batches to distributors with reason codes and automated account balance deduction.

### 7. Customer Returns & Refunds
* **Invoice Lookup**: Fast search by invoice number (`INV-XXXXXX`) or barcode scan to retrieve purchased line items.
* **Quantity Safeguards**: Prevents returning more than the original invoiced quantity or processing duplicate returns.
* **Restock vs Damage Disposition**: Returned items can be restored to active inventory lots or flagged as damaged.
* **Automatic Credit/Cash Adjustment**: Automatically refunds cash or reduces the customer's outstanding Khata balance.

### 8. Cashier Shifts & Cash Drawers
* **Shift Opening / Float**: Cashiers begin each shift by entering their opening cash float.
* **Counter Association**: Maps shifts to physical workstations (`POS-01`, `POS-02`).
* **Mid-Shift Cash Movements**: Log Cash-In and Cash-Out (e.g. bank drops, cash drawer relief).
* **Automated Reconciliation**: At shift close, the system calculates Expected Cash based on sales, refunds, expenses, and movements.
* **Variance Auditing**: Tracks exact discrepancies (Over/Short) with manager notes and timestamps.

### 9. Store Operating Expenses
* **Daily Store Expenses**: Log overhead expenses directly from the cashier drawer or main account.
* **Categorization**: Group expenses by Utilities (Electricity/Generator Fuel), Refreshments, Staff Meals, Store Maintenance, Stationary, and Municipal Dues.

### 10. Prescription Dispensing & Digital Rx
* **Prescription Records**: Store Patient Name, Contact, Attending Doctor, Clinic Name, and Digital Prescription Photo.
* **Pharmacist Verification**: Qualified Pharmacists (`PharmD`) can verify and sign off on dispensed controlled medications.
* **Dispensing Trail**: Connects specific POS sales invoices directly to verified prescription records.

### 11. Business Reports & Stock Audits
* **Sales Analytics**: Gross Sales, Discounts Given, Taxes Collected, and Net Realized Revenue.
* **Profitability Reports**: Computes gross margin by comparing actual sale price against batch purchase cost.
* **Expiry Forecasting**: 30-day, 60-day, and 90-day warning alerts for expiring pharmaceuticals.
* **Low Stock / Reorder Reports**: Identifies items at or below minimum threshold.
* **CSV / Excel Exports**: One-click exports of sales registries and current stock valuations.

### 12. Settings, Hardware & Thermal Printing
* **Pharmacy Branding**: Store Name, Address, Contact Numbers, Drug Sales License Number, NTN, and STRN.
* **Receipt Customization**: Configurable receipt headers, footer return policies, and paper widths (80mm vs 58mm).
* **ESC/POS Thermal Printing**: Supports network/Ethernet ESC/POS printers via raw TCP socket or browser print dialogue.
* **Database Backups**: On-demand manual database dump creation with secure download links.

---

## ⌨️ POS Keyboard Shortcuts

| Shortcut | Action | Description |
| :---: | :---: | :--- |
| **`F2`** | Focus Search | Immediately jumps cursor to medicine search input box |
| **`F4`** | New Khata | Opens the quick customer creation modal |
| **`F8`** | Hold Bill | Parks the current active cart to held memory |
| **`F9`** | Pay / Checkout | Opens the payment drawer with exact amount pre-filled |
| **`ESC`** | Close Modal | Closes active dialogs, drawers, and search dropdowns |

---

## 🚀 One-Click Launchers for Non-Technical Staff

The application includes zero-configuration launcher scripts so non-technical pharmacy cashiers can start the system without opening a terminal:

### 🪟 Windows Setup
Located in `launcher/windows/`:
1. **`Start_Pharmacy_POS.bat`**: Double-click to automatically start the MySQL service, boot the background server on port `8080`, and open Google Chrome / Microsoft Edge in clean desktop app mode (`--app=http://localhost:8080/pos`).
2. **`Start_Silent.vbs`**: Starts the application silently in the background without leaving a command prompt window open.
3. **`Install_AutoStart.bat`**: Adds the application to the Windows Startup folder so the POS opens automatically when the computer boots.

### 🍎 macOS Setup
Located in `launcher/mac/`:
1. **`Start_Pharmacy_POS.command`**: Double-click to launch the background server and launch Chrome in desktop app mode (`--app=http://127.0.0.1:8080/pos`).

---

## 🔐 Default Seeded Credentials

When seeded via `php artisan db:seed`, the system creates pre-configured accounts for each pharmacy role:

| Role | Email | Password | PIN Code | Primary Permissions |
| :--- | :--- | :--- | :---: | :--- |
| **Admin** | `admin@pharmacy.local` | `admin123` | `1234` | Full System Access, Settings, Backups, Users |
| **Manager** | `manager@pharmacy.local` | `manager123` | `2345` | Inventory, Purchases, Shifts, Reports, Returns |
| **Cashier** | `cashier@pharmacy.local` | `cashier123` | `4567` | POS Billing, Shift Reconciliation, Customer Returns |
| **Pharmacist** | `pharmacist@pharmacy.local` | `pharma123` | `3456` | POS Billing, Prescriptions, Catalogue, Stock Audits |

---

## 🛠 Installation & Local Setup

### System Requirements
* **PHP**: 8.2 or higher (with `pdo_mysql`, `mbstring`, `bcmath`, `curl`, `gd` extensions enabled)
* **Composer**: 2.x
* **Node.js**: 18.x or 20.x & npm
* **MySQL**: 8.0+ or MariaDB 10.4+

### Step-by-Step Installation

1. **Clone Repository & Enter Workspace**:
   ```bash
   git clone <repo-url> "pharmacy-pos"
   cd "pharmacy-pos"
   ```

2. **Install Dependencies**:
   ```bash
   composer install
   npm install
   ```

3. **Configure Environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Update `.env` with your MySQL database credentials:
   ```ini
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=pharmacy_pos
   DB_USERNAME=root
   DB_PASSWORD=your_password
   ```

4. **Run Migrations & Seed Sample Data**:
   ```bash
   php artisan migrate:fresh --seed
   ```

5. **Build Frontend Assets**:
   ```bash
   npm run build
   ```
   *(For active development with hot-reloading, run `npm run dev`)*

6. **Start Local Development Server**:
   ```bash
   php artisan serve --port=8080
   ```
   Open your browser at [http://127.0.0.1:8080](http://127.0.0.1:8080) and log in with any seeded account.

---

## 🧪 Testing & Quality Assurance

The codebase includes an extensive suite of automated unit and feature tests covering cashier shifts, customer returns, catalogue management, FEFO stock allocation, and checkout idempotency.

Run all automated tests with:
```bash
php artisan test
```

To run individual test suites:
```bash
# Test POS and Checkout workflow
php artisan test --filter=SaleCheckoutTest

# Test FEFO inventory allocation
php artisan test --filter=PurchaseReceivingTest

# Test Cashier Shift reconciliation
php artisan test --filter=CashierShiftTest
```

---

## 📄 License
This software is developed and licensed for proprietary pharmacy operations. All rights reserved.
