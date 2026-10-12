# Pakistan Offline Pharmacy POS — React, Laravel and Inertia Specification

Version: 2.0  
Scope: Retail pharmacy / medical store, operating without internet  
Market: Pakistan; currency PKR; timezone Asia/Karachi  
Status: Proposed functional requirements for planning and development

## 1. Purpose and scope

Build a pharmacy POS that manages medicine stock, purchases, sales, payments and reporting locally. The first release must work without internet, including login, billing, searching, printing, backups and report generation.

Two deployment options are supported:

- **Single computer:** application and database run on one machine.
- **Multiple counters:** application/database run on a local server; counters connect through the shop's LAN. All counters use one authoritative database.

Internet connectivity is not required for daily operation. A server or LAN failure in the multi-counter setup is different from an internet outage: counters cannot continue writing to the shared database while disconnected unless a separate synchronization architecture is developed.

This document proposes product behavior; it does not establish legal or clinical requirements. Prescription, controlled-medicine, tax and retention settings must be confirmed for the target pharmacy before implementation.

### Initial module summary

| Module | Release scope |
|---|---|
| Dashboard | Core |
| Medicine catalogue | Core |
| Batch and inventory | Core |
| Units and packaging | Core |
| POS and sales | Core |
| Purchases and suppliers | Core |
| Returns, expiry and damaged stock | Core |
| Customers and credit | Core |
| Cash, shifts and expenses | Core |
| Reports | Core |
| Users, permissions and audit | Core |
| Settings, import/export and backup | Core |
| Prescriptions and dispensing records | Include when prescription dispensing is in scope |
| Branches, full accounting, online ordering and AI | Future scope |

## 2. Shared operating rules

- Track stock by medicine, batch and location, with receipt-level cost records when the same batch is bought at different costs.
- Store quantities in a base unit and convert package quantities consistently.
- Record every stock change in a stock movement ledger.
- A sale must atomically save the invoice, payment/credit entries, batch allocations and stock movements. If any part fails, none of it is committed.
- Prevent overselling when two counters sell the same remaining stock.
- Suggest **FEFO: first expiry, first out**, among eligible batches.
- Block expired, quarantined and recalled stock from sales. Configure expiry-month interpretation explicitly when labels provide only month/year.
- No negative sellable stock by default.
- Posted financial and stock records are corrected through reversal or adjustment, rather than silent deletion.
- Reprinting a receipt must not create a sale or deduct stock again.
- Store money using fixed precision; configure rounding once and apply it consistently.
- Retain transaction-time prices, tax, conversion factors and cost allocations so later catalogue edits do not alter history.
- Require a reason and appropriate permission for refunds, stock corrections, price overrides and reversals.

## 3. Dashboard

### Purpose

Give the owner or manager a quick view of shop activity and operational problems.

### Features

- Today's gross sales, discounts, returns and net sales.
- Collections split by cash, card and other recorded payment methods.
- Credit sales and customer outstanding balances.
- Estimated gross margin, with a clear distinction from net profit.
- Low-stock medicines and medicines expiring within configurable periods.
- Supplier balances, pending purchase orders and cash closing differences.
- Fast-moving and slow-moving medicine summaries.
- Date, counter and user filters.

### Data and rules

Derived from posted transactions; drafts do not affect totals. Show sales separately from collections: collecting an old customer balance is not a new sale. Dashboard totals must reconcile with detailed reports.

### Permissions and acceptance

Cashiers see their own shift information; management sees business totals. Clicking a total opens its underlying transactions.

## 4. Medicine catalogue

### Purpose

Maintain the searchable master list of medicines and non-medicine pharmacy items.

### Main fields

Internal item code, brand name, generic name/ingredients, strength, dosage form, manufacturer, category, base unit, package definitions, barcodes, default selling price, reorder level, rack/shelf, prescription-required flag and active status.

### Features and workflow

1. Create the medicine and its packaging.
2. Assign barcodes and searchable aliases.
3. Set prices, reorder level and storage location.
4. Receive opening stock or purchases through inventory workflows.

Support search by brand, generic name, strength, barcode and item code. Support multiple barcodes for different packages, archive items, and validate imported records.

### Rules

Different strengths or dosage forms are separate items. Prevent duplicate internal codes and ambiguous barcode mappings. Opening quantity is not a freely editable catalogue field. Archiving an item must preserve historical invoices. Generic search helps locate products; it does not authorize automatic clinical substitution.

### Permissions, outputs and acceptance

Managers maintain the catalogue; cashiers search it. Outputs include medicine lists and barcode labels. A scan identifies the intended item and package unambiguously.

## 5. Batch and inventory

### Purpose

Maintain accurate stock and expiry visibility.

### Main fields

Medicine, batch number, expiry date/precision, location, receipt reference, received quantity, remaining quantity, unit cost, selling price where applicable, stock status and last movement date.

### Features

- Batch-wise stock and expiry alerts.
- Opening stock with batches and costs.
- Stock movement history: receipt, sale, return, adjustment, write-off and transfer.
- Physical counts with variance review and approved adjustments.
- Low-stock thresholds and reorder suggestions.
- Quarantine, recall and expired-stock handling.
- Stock valuation and non-sellable stock visibility.

### Workflow and rules

Receiving creates stock movements. Selling allocates eligible stock using FEFO. A sale spanning batches records each allocation. Counts generate variances; approval posts adjustments rather than overwriting balances.

The same batch can be received more than once at different costs; preserve the receipt cost layers. Do not merge distinct expiry dates without review. Stock status must distinguish sellable stock from stock physically present but unavailable.

### Permissions, reports and acceptance

Inventory staff enter counts; managers approve discrepancies. Reports include stock on hand, movement ledger, expiry schedule, stock aging and valuation. Two counters competing for the last unit cannot both complete a sale.

## 6. Units and packaging

### Purpose

Support purchases and sales in boxes, strips and loose units.

### Main fields

Base unit, package name, conversion factor, package barcode, package selling price and allowed sale/purchase units.

### Example

| Package | Base-unit quantity |
|---|---:|
| Tablet | 1 |
| Strip of 10 tablets | 10 |
| Box of 10 strips | 100 |

Receiving two boxes adds 200 tablets. Selling one strip and three tablets deducts 13 tablets.

### Rules

Conversions are defined per item, not globally. Whole-tablet items do not permit fractional tablets. Liquid products require an explicit choice between selling a sealed bottle or measured volume. Package prices may differ from a simple multiplication of base-unit price.

Do not retroactively change conversion factors on posted transactions. If pack size changes, create a new package definition/version and preserve old records.

### Acceptance

Buying in boxes and selling in strips/tablets produces correct quantities, costs and receipt totals.

## 7. POS and sales

### Purpose

Provide fast and reliable counter billing.

### Main fields

Invoice number, timestamp, cashier, counter, shift, customer where applicable, items, units, quantities, batch allocations, unit prices, discounts, taxes, totals, payment methods and outstanding balance.

### Features

- Barcode scanning and keyboard-friendly search.
- Display stock, unit choices, prices and batch expiry.
- Line and invoice discounts with approval limits.
- Held bills, resumed bills and cancellation of drafts.
- Cash, card and other payment recording; split and partial payments.
- Credit sales for identified customers.
- Thermal receipt printing and A4 invoices.
- Reprint with a copy indicator.

### Workflow

1. Start a shift and add items.
2. Select units and quantities; check eligible stock.
3. Link a customer/prescription when required.
4. Apply permitted discounts and record payment.
5. Commit the sale and stock changes together.
6. Print the receipt; offer reprint if printing fails.

### Rules

Held bills do not reduce stock unless explicit reservation is implemented. Finalize-time stock validation is required. Prevent duplicate posting on repeated clicks or retries. An offline POS may record a card payment, but authorization depends on the external payment terminal/provider.

### Permissions and acceptance

Cashiers sell within limits; managers approve restricted overrides. Printer failure must leave a valid completed sale available for reprint. Interrupted posting must never leave an invoice without its corresponding stock/payment entries.

## 8. Purchases and suppliers

### Purpose

Control medicine receiving, purchase costs and supplier liabilities.

### Main fields

Supplier name/contact, opening balance, purchase order, supplier invoice number, dates, medicine, package quantity, bonus quantity, batch, expiry, price, discounts, taxes/charges, payment and remaining payable.

### Features and workflow

1. Create a supplier and optionally a purchase order.
2. Receive quantities against the order or enter a direct purchase.
3. Capture batch and expiry for each received line.
4. Enter invoice charges, discounts and bonus stock.
5. Post stock and supplier liability together.
6. Record immediate or later supplier payments.

Support partial receiving, purchase history, due dates, supplier statements and purchase returns.

### Rules

Purchase orders alone do not increase stock. Receiving/invoicing must avoid counting the same stock twice. Warn about duplicate supplier invoice references. Document how discounts, freight and bonus quantities affect unit cost.

Example: pay 1,000 for 100 tablets and receive 10 bonus tablets. If all cost is allocated across the 110 received tablets, cost is approximately 9.0909 per tablet before other charges. Store adequate cost precision and round displayed currency separately.

### Acceptance

Posting a purchase updates stock and supplier balance once; recording a payment reduces the payable without changing stock.

## 9. Returns, expiry and damaged stock

### Purpose

Handle customer returns, supplier returns and stock losses with traceable records.

### Main fields

Return/write-off number, original transaction, medicine, batch, quantity/unit, reason, condition, refund/credit amount, stock disposition, approving user and timestamp.

### Workflows

- **Customer return:** locate invoice, select remaining returnable quantity, inspect condition, choose quarantine/restock/write-off disposition, approve and refund or issue credit.
- **Supplier return:** select stock and supplier, dispatch returned quantities, track pending supplier credit and settle it when confirmed.
- **Expiry/damage write-off:** identify stock, record reason, approve and reduce physical stock.

### Rules

Do not refund or return more than the original quantity less previous returns. Use original transaction pricing and tax treatment as configured. Customer returns must not automatically become sellable. Distinguish stock sent to a supplier from a supplier credit actually received.

### Reports and acceptance

Return register, refund report, pending supplier credits and expiry/damage losses. The same invoice quantity cannot be refunded twice.

## 10. Customers and credit

### Purpose

Track customer purchases, credit balances and collections.

### Main fields

Customer code, name, optional phone/address, opening balance, credit limit, due terms, transactions and active status.

### Features

Customer history, partial payments, credit-limit controls, receipts, ledger, outstanding list and aging buckets.

### Rules and workflow

Anonymous walk-in cash sales do not require a customer record. Credit sales require an identified customer. Collections record payment method and invoice allocation, or explicit on-account credit. Refunds and credit notes must adjust the balance consistently. Overrides require permission and a reason.

Collect only necessary personal data. Access to customer/patient records should be restricted.

### Acceptance

Customer ledger reconciles opening balance, credit invoices, returns, collections and current balance. Historical purchases remain accessible after a customer is archived.

## 11. Prescriptions and dispensing records

### Purpose and scope

Link prescriptions to dispensing records when required by the pharmacy's operating model. This is a recordkeeping workflow, not automated medical advice.

### Main fields

Prescription reference, patient details needed for the workflow, prescriber, prescription date, attached file/photo, prescribed items/instructions, pharmacist review, dispensed items/quantities and linked invoice.

### Features and workflow

Attach local scans/photos, search prescription history, link to sales, record partial dispensing and reviewer identity. A pharmacist reviews the prescription before completing restricted dispensing.

### Rules

Restrict sensitive attachments and record access where appropriate. Back up attachments with the database. Configure retention and dispensing restrictions after requirements are confirmed. Barcode or generic-name matches must not automatically approve substitutions, dosage changes or interactions.

If controlled medicines are sold, define a dedicated register with pharmacist approval, receipt/dispense movements and required record fields before enabling those workflows.

### Acceptance

Authorized users can trace prescription to invoice and dispensing history; unauthorized roles cannot view attachments.

## 12. Cash, shifts and expenses

### Purpose

Reconcile counter cash and record operating expenses.

### Main fields

Shift, cashier, counter, opening float, cash collections, cash refunds, cash in/out, expenses, counted closing cash, expected cash, variance and approval.

### Features and workflow

Open shift, record sales/collections and authorized cash movements, enter categorized expenses, count closing cash, calculate variance and close the shift.

### Rules

Expected cash = opening cash + cash collections + other cash in − cash refunds − cash expenses − other cash out.

Card sales do not increase cash drawer balance. Deposits to a bank and supplier cash payments must be categorized correctly. Avoid double-counting an expense entered through both expense and cash movement screens. Closed shifts cannot be silently edited.

### Reports and acceptance

Shift closing report, cashbook, expense report and variance report. Daily reconciliation separates sales revenue from cash received.

## 13. Reports

### Purpose

Provide reconciled business and inventory reporting.

### Required reports

| Report | Main contents |
|---|---|
| Sales summary/detail | Gross sales, discounts, returns, net sales, taxes and payment split |
| Medicine/category sales | Quantity, revenue and gross margin |
| Gross margin | Net sales excluding tax less allocated cost of goods sold |
| Purchase report | Supplier invoices, quantities, costs and returns |
| Stock on hand | Item, batch, expiry, location, sellable/non-sellable quantity |
| Stock valuation | Remaining quantities and documented cost basis |
| Stock ledger | Opening, all movements and closing quantity |
| Expiry report | Expired and upcoming expiries, quantities and cost at risk |
| Reorder report | Sellable stock, threshold and suggested quantity |
| Fast/slow movers | Movement over a selected period |
| Customer/supplier statements | Opening balances, transactions and closing balances |
| Aging | Outstanding invoices grouped by due-date age |
| Cash/shift reports | Expected cash, counted cash, variance and methods |
| Loss/returns report | Refunds, write-offs, expiry and pending supplier credits |
| Audit report | Sensitive changes, actor, reason and time |

### Rules

Support date range, medicine, category, user/counter and supplier/customer filters where applicable. Export CSV/Excel-compatible data and printable reports.

Gross margin is not net profit. Label any approximate profit measure and define which expenses and losses it includes. Preserve historical cost allocations and reconcile report totals with ledgers.

### Acceptance

The same date/filter selection produces consistent dashboard and report totals. Returns and old-balance collections are not counted as new sales.

## 14. Users, permissions and audit

### Purpose

Limit access and preserve accountability.

### Suggested roles

| Role | Typical access |
|---|---|
| Owner/admin | Settings, user management, approvals, all reports and backup/restore |
| Manager | Operational reports, purchases, inventory and authorized approvals |
| Pharmacist | Prescription review and dispensing workflows |
| Cashier | POS, permitted returns and own shift |
| Inventory operator | Receiving, stock counts and catalogue access as permitted |
| Accounts operator | Customer/supplier payments, expenses and financial reports |

Roles can be combined for small shops; permissions remain individually configurable.

### Features and rules

Local login, password protection, automatic lock, failed-login throttling, disabled users and session management. Passwords must be securely hashed, not stored in plain text.

Audit login/security events, price changes, discounts, refunds, cancellations, stock adjustments, permission changes and restores. Record actor, time, entity, reason and relevant before/after values without copying sensitive secrets.

Disable users instead of deleting their transaction history. Audit records are not editable through ordinary screens. A local database administrator may still alter underlying data; stronger tamper resistance requires additional measures.

### Acceptance

Restricted actions are enforced by the application/backend, not merely hidden buttons. Every approved override can be traced to its approving user.

## 15. Settings, import/export and backup

### Purpose

Configure the shop, migrate starting data and recover from failures.

### Settings

Pharmacy name/contact/logo, currency, timezone, numbering sequences, receipt width/template, printers, tax configuration, expiry alerts, reorder defaults, approval thresholds, package rounding and local backup schedule.

### Import/export

Templates for medicines, customers, suppliers and opening stock/balances. Preview before posting, validate rows, report errors and prevent duplicate imports. Opening stock must include batches, expiry, quantities and cost; opening financial balances require a reference date.

### Backup and restore

- Automated consistent database backups.
- Include prescription attachments and required configuration.
- Store a copy on a separate drive/device; a second folder on the same failed disk is insufficient.
- Show last successful backup, failures and destination availability.
- Protect backups containing business/patient data and document recovery credentials.
- Configure retention without deleting the only known-good backup.
- Restrict restores; stop writes, preserve a pre-restore copy and verify the restored dataset.
- Test recovery on a separate instance before production use.

### Acceptance

A restored installation reproduces stock, invoices, balances, users and prescription attachments. Printer and barcode configuration works without internet. The system visibly reports backup failures.

## 16. Local architecture and hardware

### Architecture requirements

- No remote API, cloud login, online license check or internet asset loading may block core POS operation.
- Single-machine deployment can use an embedded or local database, chosen for the implementation.
- Multi-counter deployment needs a local transactional database/server with concurrency controls.
- Client retries use unique operation identifiers to prevent duplicate transactions.
- Use schema migrations and a backup before upgrades.
- Document installer, local server startup, network address, ports and recovery steps.
- Internet loss does not affect LAN operation; server failure requires recovery.

The selected framework stack is Laravel + React + Inertia, with local MySQL; implementation details follow in sections 24–29.

### Hardware

POS computer(s), barcode scanner, supported thermal receipt printer, optional A4 printer/cash drawer, separate backup device and a UPS. Multi-counter installations also need a reliable router/switch and local server.

## 17. Key data entities

Medicines; package definitions and barcodes; batches; receipt cost layers; locations; stock movements; stock counts; suppliers; purchase orders; goods receipts; purchase invoices; supplier payments/credits; customers; sales invoices and lines; batch allocations; payments and allocations; returns; write-offs; shifts; cash movements; expenses; prescriptions and attachments; users; roles; permissions; audit events; settings; backup history.

Keep stock movements, payment entries and posted transaction references linked. Historical invoices retain snapshots of descriptions, units, prices and applicable tax.

## 18. Main end-to-end workflows

1. **Setup:** configure shop → create users → import catalogue → enter opening stock/balances → verify reports → test backup/restore.
2. **Purchase:** supplier → optional order → receiving with batches/expiry → invoice posting → supplier payment.
3. **Sale:** open shift → scan/search → choose quantity/unit → validate stock and restrictions → payment/credit → atomic posting → receipt.
4. **Customer return:** original invoice → eligible quantity → inspection/disposition → approval → refund/credit and stock movement.
5. **Expiry:** alert → quarantine/block → supplier return or approved write-off → financial/stock reporting.
6. **Daily close:** reconcile cash → approve variance → close shift → verify backup.

## 19. Release acceptance checklist

- Complete a purchase and sale with internet disconnected.
- Buy boxes, sell strips/tablets and verify base-unit stock.
- Correctly allocate a sale across multiple batches.
- Block expired, recalled and quarantined stock.
- Prevent concurrent overselling and duplicate sale retries.
- Verify partial payments, credit limits and later collections.
- Verify customer returns, supplier returns and write-offs.
- Preserve historical margins after purchase/selling price changes.
- Recover cleanly from printer failure and interrupted transactions.
- Enforce role permissions and capture approval/audit records.
- Reconcile dashboard, stock ledger, customer/supplier balances and cash closing.
- Restore a backup on a separate installation with attachments intact.

## 20. Future modules

These are separate from the current offline release:

| Module | Possible future features |
|---|---|
| Branches and warehouses | Transfers, consolidated reports and branch permissions |
| Full accounting | General ledger, journals, bank reconciliation and financial statements |
| Cloud synchronization | Remote backups, remote management and defined conflict handling |
| Online pharmacy orders | Catalogue, prescription submission, review and delivery |
| Loyalty and messaging | Points, consent-based reminders and digital receipts |
| Forecasting | Demand estimates, purchase suggestions and expiry-risk analysis |
| SaaS administration | Tenant isolation, subscriptions and business onboarding |
| Tax integrations | Integrations scoped to applicable requirements |

## 21. Decisions to confirm before development

Target operating system; one PC or multiple counters; prescription/controlled-medicine scope; packaging and loose-sale rules; applicable tax setup; existing data migration; credit policies; cost valuation method; return approvals; printer models; backup device and retention; whether future branches/cloud access are expected.

## 22. Reference and provenance

Reference page: [CloudPOS pharmacy management software](https://cloudpos.pk/pharmacy-management-software).

The reference advertises billing, stock/batch/expiry management, prescriptions, suppliers, accounting, reporting, branch management and online/offline features. This specification adapts that broad feature set to the user's offline-first scope and adds proposed implementation rules and acceptance criteria. It is not a claim that CloudPOS implements every workflow described here.

## 23. Pakistan receipt printing and devices

Research checked: 11 October 2026 (Pakistan time). These are examples listed by Pakistani vendors, not a market-share ranking or a guarantee of compatibility.

### Device selection

Use an **80 mm thermal receipt printer** as the default target: it provides more room for medicine names, quantities and amounts. Support 58 mm as a compact alternative, with a narrower layout. Thermal receipt rolls differ from adhesive barcode labels; use a suitable label printer for stock labels.

| Option | Listed specifications | Suggested use |
|---|---|---|
| Black Copper BC-89AC | 80 mm thermal, USB + LAN; seller describes auto-cutter and 58 mm support | Candidate for local counter or shared LAN receipt printing |
| Epson TM-T82X | 80 mm thermal, USB + serial in the referenced Pakistani listing | Candidate for a USB-connected counter |
| 58 mm thermal printer | Narrow receipt roll; features vary by exact model | Small counter with simpler receipts; validate cutter and protocol separately |

Sources:

- [Black Copper BC-89AC — Transworld Pakistan](https://www.transworld.com.pk/product/label-printers/black-copper/black-copper-bc-89ac-thermal-receipt-printer)
- [Epson TM-T82X — Mustafa Computers Pakistan](https://mustafacomputers.pk/products/epson-tm-t82x-pos-receipt-printer-usb-serial)
- [58 mm thermal device example — ATC Global Pakistan](https://atcglobal.pk/product/mini-thermal-printer/)

Before purchase, confirm exact interface variant, OS driver, ESC/POS command support, printable width, auto-cutter, local warranty and compatibility with the actual application. Do not assume all devices of a brand have identical command support. Prices are intentionally omitted because listings and quotes vary.

### Pakistan defaults

- Display currency as **PKR** or **Rs.**; raw text samples use PKR.
- Display invoice dates/times in Asia/Karachi; store a consistent timestamp representation.
- Allow cash, card, bank transfer, JazzCash and Easypaisa as payment records. Recording a method offline does not verify a digital payment; cashier confirmation or a separate terminal is needed.
- Store local and international phone formats without using the number as a mandatory customer ID.
- Use English medicine names; offer Urdu customer-facing text if required.
- Tax fields must be configurable. Do not assume one tax rate for all medicines or claim an ordinary offline receipt is an FBR-registered digital invoice.

### Receipt contents

Pharmacy name, address/phone, invoice number, local date/time, cashier/counter, item name and strength, sale unit, quantity, price, discount, line total, subtotal, tax if applicable, payable total, amount received, change, credit balance and a configurable return-policy footer.

Show batch/expiry details where required by the workflow. Keep patient details off ordinary receipts unless necessary. Reprints should show COPY. Never print a fabricated tax registration, regulatory invoice number or QR code.

### Printing methods

| Method | How it works | Limits |
|---|---|---|
| Browser + installed printer driver | Local HTML receipt uses window.print() | Opens print dialog; no reliable physical-print confirmation |
| Windows RAW spooler | Local Python/native component sends ESC/POS bytes to installed queue | Requires Windows and a compatible raw-capable printer |
| LAN direct printing | Local process sends bytes to configured printer IP/port | Only for devices supporting that transport; not ordinary browser JavaScript |

USB is simplest for one counter. A shared LAN printer requires job serialization and clear counter identification. Paper width is not the same as printable width; calibrate margins and characters per line.

### Printing module requirements

Store per-counter printer name/IP, connection type, paper width, characters per line, cutter support, copies and receipt template. Provide test print, reprint and troubleshooting screens.

Commit the sale before printing. Maintain print-job records linked to the invoice, including submitted/failed/unknown status. A submitted spooler job or successful socket write does not prove paper was printed. Do not automatically retry an uncertain job: it may already have printed. Let the cashier inspect the printer and reprint a marked copy.

Urdu needs shaping and a locally installed/bundled font. For direct ESC/POS, render shaped Urdu as a supported raster image or use a vendor SDK; the ASCII example below deliberately rejects non-ASCII text. Browser/driver printing is the easier starting point for Urdu.


## 24. React + Laravel + Inertia architecture

### Chosen stack

| Layer | Choice and responsibility |
|---|---|
| Backend | Laravel: authentication, validation, permissions, business rules and transactions |
| Frontend | React + TypeScript: POS interface, forms, reports and receipts |
| Integration | Inertia: Laravel routes/controllers deliver React page props |
| Database | Local MySQL with InnoDB transactions and row locking |
| Styling | Tailwind CSS, built locally |
| Assets | Vite production build; bundled icons/fonts; no CDN dependency |
| Printing | React/driver printing initially; Laravel ESC/POS service for compatible LAN printers |
| Background work | Local database queue and supervised worker if asynchronous printing is enabled |

Use one Laravel repository. A separate React API application is unnecessary for the initial scope. Keep business rules in Laravel services/actions rather than duplicating them in React.

Choose mutually compatible framework/package versions at project creation, pin composer.lock and package-lock.json, and record the PHP/Node/database versions used by the installer. Versions are deliberately not hardcoded here.

### Offline meaning

The browser talks to Laravel on localhost or the shop LAN. Laravel talks to local MySQL. Inertia needs that local server for navigation and mutations; it does not provide disconnected-counter synchronization by itself.

| Deployment | Setup |
|---|---|
| Single PC | Web server + PHP + Laravel + MySQL + browser on one computer |
| Multiple counters | One local server runs Laravel/MySQL; browser counters connect through LAN |

For deployment, configure Apache/Nginx or an equivalent managed local web server with its document root pointing to Laravel's public directory. Do not use the Vite development server or artisan serve as the permanent shop installation.

Start the web server, database, scheduler and optional queue worker automatically after reboot. In the multi-counter setup, give the server a stable LAN address, protect access with firewall rules, and serve only public assets. Keep database credentials and .env private. Use secure local transport where supported and appropriate.

### Proposed project structure

```text
app/
  Actions/Sales/FinalizeSale.php
  Actions/Purchases/PostPurchase.php
  Actions/Inventory/PostStockAdjustment.php
  Http/Controllers/
    PosController.php
    SaleController.php
    ReceiptController.php
    PurchaseController.php
    InventoryController.php
    ReportController.php
  Http/Requests/
    FinalizeSaleRequest.php
    StorePurchaseRequest.php
  Models/
  Policies/
  Services/
    InventoryService.php
    PricingService.php
    ReceiptDataFactory.php
    ThermalReceiptService.php
resources/js/
  pages/
    Dashboard.tsx
    Pos/Index.tsx
    Sales/Receipt.tsx
    Medicines/
    Inventory/
    Purchases/
    Suppliers/
    Customers/
    Returns/
    Prescriptions/
    Shifts/
    Expenses/
    Reports/
    Settings/
  components/
    BarcodeInput.tsx
    MedicineSearch.tsx
    CartTable.tsx
    PaymentDialog.tsx
    BatchSelector.tsx
routes/web.php
database/migrations/
tests/Feature/
```

Match the starter kit's actual page-directory casing and resolver configuration. This is a proposed structure, not generated application source.

### Module implementation mapping

| Module | Main Laravel responsibility | React page/components |
|---|---|---|
| Dashboard | Aggregated authorized queries | Dashboard cards and filters |
| Medicines | Validation, unique codes/barcodes, catalogue policies | Catalogue list, editor, package form |
| Inventory | Stock movements, batch eligibility, count approval | Batch list, ledger, counts, alerts |
| Units | Conversion snapshots and integer base quantities | Unit selector and package editor |
| POS | Authoritative price/stock validation and finalization | Search, cart, payment modal |
| Purchases | Receipt cost layers, stock and payable posting | Purchase editor, receiving, supplier ledger |
| Returns | Returnable quantity, disposition, credit/refund posting | Invoice lookup and return form |
| Customers | Credit limits, allocations and statements | Customer editor, collections, ledger |
| Prescriptions | Protected attachments and dispensing review | Upload, review and linked records |
| Shifts/expenses | Cash movement posting and reconciliation | Open/close shift, expenses |
| Reports | Filtered reconciled queries and exports | Reports and print/export controls |
| Permissions | Policies, middleware and approval checks | Permission-aware controls |
| Settings/backup | Validated settings and managed backup/restore | Configuration and backup status |

## 25. Database design and sale integrity

### Proposed tables

| Group | Tables and important fields |
|---|---|
| Catalogue | medicines; medicine_packages (base_quantity, price_paisa); medicine_barcodes |
| Stock | locations; batches (expiry, status); stock_lots (batch/location/receipt/cost/remaining_base_qty); stock_movements |
| Counting | stock_counts; stock_count_lines; adjustment approvals |
| Purchasing | suppliers; purchase_orders/lines; goods_receipts/lines; purchase_invoices/lines; supplier_payments and allocations |
| Sales | sales (invoice_number, idempotency_key, status, totals_paisa); sale_lines (name/unit/price snapshots); sale_allocations (lot, quantity, cost snapshot) |
| Customer finance | customers; customer_payments; payment_allocations; opening balances |
| Returns/losses | sales_returns/lines; purchase_returns/lines; supplier_credit_notes; write_offs/lines |
| Counter finance | counters; shifts; cash_movements; expenses; payment_methods |
| Prescriptions | prescriptions; prescription_attachments; dispensing_records |
| Security | users; roles; permissions; role/user associations; audit_events |
| Operations | settings; printer_profiles; print_jobs; backup_runs |

Use foreign keys and indexes for medicine lookup, expiry queries, stock allocation and transaction reports. Add unique invoice numbers and checkout idempotency keys. Add explicit print-job IDs and statuses. Scope stock by location even when initially there is one shop.

Store invoice money in integer paisa; retain higher precision for unit-cost allocation, e.g. decimal cost fields. Return decimal strings from Laravel when precision matters. React formats amounts but does not determine authoritative totals.

### Finalization sequence

1. Authenticate the user; validate cashier shift and permissions.
2. Validate medicine/package IDs and positive quantities using a Form Request.
3. Resolve prices, conversions, discounts and taxes on the server.
4. Start DB::transaction.
5. Claim a unique idempotency key; return the existing posted invoice on a repeated request. Reject the same key with different checkout content.
6. Lock authoritative item/location stock control rows in a consistent order, then eligible stock lots using lockForUpdate.
7. Allocate eligible lots by expiry, with deterministic tie-breaking; reject insufficient stock.
8. Save the invoice, line snapshots, lot allocations, stock movements, payments and credit entries.
9. Commit the transaction.
10. Create/dispatch a print job after commit, or redirect to the receipt.

Every stock-writing path must honor the same locking strategy. Do not put printer/network operations inside a database transaction: a printer error must not roll back a completed sale. Never trust posted browser totals. Invoice sequencing must be concurrency-safe; do not generate numbers using max(id) + 1.

The sequence above is an implementation requirement rather than a complete checkout code sample. It requires schema, cost method and permission decisions before coding.

## 26. React receipt component

File: resources/js/pages/Sales/Receipt.tsx

This example consumes finalized invoice props from Laravel. Paper width is configurable; printable width starts at 72 mm for an 80 mm roll and 50 mm for a 58 mm roll. Calibrate against the driver/device.

```tsx
import { Head } from '@inertiajs/react';

type ReceiptItem = {
  id: number;
  name: string;
  quantity: number;
  unit: string;
  price_paisa: number;
  total_paisa: number;
};

export type ReceiptData = {
  number: string;
  timestamp: string; // ISO 8601 with UTC offset or Z.
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
  if (!Number.isSafeInteger(paisa)) throw new Error('Invalid amount');
  return 'PKR ' + (paisa / 100).toFixed(2);
}

export default function Receipt({ sale, shop, paperWidth }: Props) {
  const printedAt = new Intl.DateTimeFormat('en-PK', {
    timeZone: 'Asia/Karachi', dateStyle: 'medium', timeStyle: 'short',
  }).format(new Date(sale.timestamp));
  const summaries = [
    ['Subtotal', sale.subtotal_paisa], ['Discount', sale.discount_paisa],
    ['Tax', sale.tax_paisa], ['Total', sale.total_paisa],
    ['Received', sale.received_paisa], ['Change', sale.change_paisa],
    ['Balance', sale.balance_paisa],
  ] as const;

  return (
    <>
      <Head title={'Receipt ' + sale.number} />
      <style>{`
        @page { size: auto; margin: 3mm; }
        .receipt { font: 12px Arial, sans-serif; color: #000;
          margin: 8px auto; overflow-wrap: anywhere; }
        .receipt h1 { font-size: 17px; margin: 0 0 4px; }
        .receipt .center { text-align: center; }
        .receipt .row { display: flex; justify-content: space-between; gap: 6px; }
        .receipt .item { padding: 6px 0; border-bottom: 1px dashed #777; }
        @media print {
          .receipt-controls { display: none; }
          .receipt { margin: 0; }
          .receipt .item, .receipt .totals { break-inside: avoid; }
        }
      `}</style>
      <div className="receipt-controls">
        <button onClick={async () => {
          await document.fonts.ready;
          window.print();
        }}>Print receipt</button>
      </div>
      <main className="receipt"
        style={{ width: paperWidth === 58 ? '50mm' : '72mm' }}>
        <header className="center">
          <h1>{shop.name}</h1>
          <div>{shop.address}</div><div>{shop.phone}</div>
          {sale.copy && <strong>COPY</strong>}
        </header>
        <p>Invoice: {sale.number}<br />{printedAt}<br />
          Cashier: {sale.cashier}</p>
        {sale.items.map(item => (
          <section className="item" key={item.id}>
            <div>{item.name}</div>
            <div className="row">
              <span>{item.quantity} {item.unit} @ {money(item.price_paisa)}</span>
              <span>{money(item.total_paisa)}</span>
            </div>
          </section>
        ))}
        <section className="totals">
          {summaries.map(([label, amount]) => (
            <div className="row" key={label}>
              <span>{label}</span><span>{money(amount)}</span>
            </div>
          ))}
          <div>Payment: {sale.payment_label}</div>
        </section>
        <p className="center">{shop.footer}</p>
      </main>
    </>
  );
}
```

Render this page without the ordinary application sidebar/header. Browser printing opens a dialog and does not confirm physical output. Configure driver paper width, 100% scale and disabled browser headers/footers. Reprints should be requested through a controlled route that supplies copy=true and records the reprint event.

React escapes text values by default. Do not use dangerouslySetInnerHTML for receipt data. Urdu blocks need lang="ur", direction="rtl" and a locally installed/bundled font with shaping support.

### Laravel receipt route and controller

```php
<?php
// routes/web.php — illustrative routes; configure policies and permission gates.
use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/sales/{sale}/receipt', [ReceiptController::class, 'show'])
        ->name('sales.receipt');
    Route::post('/sales/{sale}/print', [ReceiptController::class, 'print'])
        ->name('sales.print');
});
```

```php
<?php
namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\ReceiptDataFactory;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    public function show(Sale $sale, ReceiptDataFactory $factory): Response
    {
        Gate::authorize('view', $sale);
        abort_unless($sale->status === 'posted', 409);
        // Factory supplies the exact typed DTO consumed by Receipt.tsx.
        return Inertia::render('Sales/Receipt', $factory->forSale($sale));
    }
}
```

ReceiptDataFactory is an application-specific service to implement. It must output sale, shop and paperWidth, load saved invoice snapshots, format the timestamp with an offset, enforce integer paisa serialization and omit unnecessary patient data.

The print method below is separate pseudocode for production orchestration:

```text
authorize print/reprint permission
validate approved counter/printer profile
load posted invoice DTO
create unique print-job request with original/copy flag
dispatch a local worker after database commit
worker serializes access to that printer and calls ThermalReceiptService
record submitted/failed/unknown result; do not promise physically printed status
```

## 27. Laravel direct thermal printing service

Install during development/build:

```bash
composer require mike42/escpos-php
```

Include the vendor directory in the offline installation. The example uses a **LAN ESC/POS printer reachable from the Laravel host**. Confirm the device supports raw TCP on the configured port. The library also offers Windows connectors, but USB queue/share access is environment-specific and must be separately configured/tested.

File: app/Services/ThermalReceiptService.php

```php
<?php
namespace App\Services;

use InvalidArgumentException;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;

final class ThermalReceiptService
{
    private function ascii(string $text): string
    {
        if (preg_match('/[^\x20-\x7E]/', $text)) {
            throw new InvalidArgumentException(
                'Use raster rendering for Urdu; control text is prohibited.'
            );
        }
        return $text;
    }

    private function money(int $paisa): string
    {
        if ($paisa < 0) {
            throw new InvalidArgumentException('Negative receipt amount.');
        }
        return 'PKR '.intdiv($paisa, 100).'.'.
            str_pad((string) ($paisa % 100), 2, '0', STR_PAD_LEFT);
    }

    public function send(array $receipt, string $host, int $port = 9100,
                         int $columns = 42, bool $hasCutter = false): void
    {
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
        $pair = function (string $label, string $value)
            use (&$lines, $columns, $add): void {
            $label = $this->ascii($label);
            $value = $this->ascii($value);
            if (strlen($label) + strlen($value) + 1 <= $columns) {
                $lines[] = $label.str_repeat(' ',
                    $columns - strlen($label) - strlen($value)).$value;
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
        if ($sale['copy']) $add('COPY');
        $add('Invoice: '.$sale['number']);
        $add($sale['local_datetime'].' PKT');
        $add('Cashier: '.$sale['cashier']);
        $add(str_repeat('-', $columns));
        foreach ($sale['items'] as $item) {
            $add($item['name']);
            $pair($item['quantity'].' '.$item['unit'].' @ '.
                $this->money($item['price_paisa']),
                $this->money($item['total_paisa']));
        }
        $add(str_repeat('-', $columns));
        foreach ([
            'Subtotal' => 'subtotal_paisa', 'Discount' => 'discount_paisa',
            'Tax' => 'tax_paisa', 'Total' => 'total_paisa',
            'Received' => 'received_paisa', 'Change' => 'change_paisa',
            'Balance' => 'balance_paisa',
        ] as $label => $field) {
            $pair($label, $this->money($sale[$field]));
        }
        $add('Payment: '.$sale['payment_label']);
        $add($shop['footer']);

        // Destination comes only from trusted admin-managed configuration.
        $connector = new NetworkPrintConnector($host, $port, 5);
        $printer = null;
        try {
            $printer = new Printer($connector);
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->selectPrintMode(Printer::MODE_FONT_A);
            $printer->text(implode("\n", $lines)."\n");
            $printer->feed(4);
            if ($hasCutter) $printer->cut(Printer::CUT_PARTIAL);
        } finally {
            if ($printer !== null) {
                $printer->close();
            } else {
                $connector->close();
            }
        }
    }
}
```

ReceiptDataFactory must supply local_datetime in Asia/Karachi for direct printing in addition to the ISO timestamp used by React. Amounts must be validated integers and line quantities validated before calling this service.

Call send only from a serialized per-printer local job. Use the tested printer capability profile in production where needed; the default profile is not a promise of support for every device. Success means commands were submitted; transmission failures can leave an uncertain/partially printed receipt. Do not automatically resend uncertain jobs.

### USB and client/server limitations

- A printer attached to the Laravel server can use a tested local Windows queue/share connector or OS printing helper.
- A USB printer attached to a cashier PC is not automatically visible to a separate Laravel server.
- For cashier USB printing, begin with browser/driver printing or deploy a per-counter native printing bridge with authenticated requests and restricted origins/destinations.
- Do not expose an unrestricted local print endpoint.
- No silent printing, drawer pulse, Urdu raster renderer or physical printer-status confirmation is implemented by the samples.

## 28. Setup and offline deployment

1. Create a Laravel application using a compatible React/Inertia starter kit.
2. Configure local MySQL and session authentication; do not require cloud authentication or email verification for counter login.
3. Build migrations, policies, Form Requests, services and React pages for the module list.
4. Implement catalogue, purchases and stock ledger before checkout.
5. Implement atomic checkout, returns, payments, shifts and reports.
6. Integrate and test the selected printer.
7. Install/build dependencies on the development machine.
8. Package PHP/web server/database as appropriate, Laravel source, vendor dependencies, public/build assets, local fonts and documented setup.
9. Configure automatic service startup, local scheduler and optional worker.
10. Import opening data, reconcile balances and test backup/restore before shop use.

Build commands, run on the development/build machine:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
```

Run migrate only after backing up an existing installation and reviewing its migrations. Use a newly generated APP_KEY on first setup and preserve it across upgrades/restores. Set APP_DEBUG=false. Install required PHP extensions for the selected framework, database and printing features.

Production does not need a Node development server when prebuilt assets are supplied. SSR is not needed for the internal POS. Configure .env for each deployment, keep database traffic local, and avoid public exposure of management or restore functions.

## 29. Development phases and checks

| Phase | Deliverable |
|---|---|
| 1 | Local installation, authentication, roles, catalogue and units |
| 2 | Purchases, batch stock, opening balances and movement ledger |
| 3 | POS, payment handling, concurrency protection and receipts |
| 4 | Returns, cash shifts, credit ledgers and expenses |
| 5 | Reports, optional prescriptions, backup/restore and installer |

Required integration checks: concurrent checkout against the same stock; repeated checkout idempotency key; posting rollback; FEFO/expiry blocking; bonus-stock costs; historical margin preservation; return limits; credit collections; permissions; print failure after committed sale; offline reboot; local LAN operation; database/attachment restore.

The embedded snippets are implementation examples, not a complete application. ReceiptDataFactory, policies, schema, print-job orchestration and checkout must be implemented. Compile/lint and integration-test them against the project's pinned dependencies. Actual printer behavior must be checked on hardware.

## 30. Sources

Documentation consulted on 11 October 2026 (Pakistan time):

- [Laravel starter kits](https://laravel.com/docs/13.x/starter-kits)
- [Laravel database transactions](https://laravel.com/docs/13.x/database)
- [Inertia server-side setup](https://inertiajs.com/server-side-setup)
- [ESC/POS PHP package and connector examples](https://github.com/mike42/escpos-php)
- [MDN browser print behavior](https://developer.mozilla.org/en-US/docs/Web/API/Window/print)

Module and device references are retained in sections 22–23. The architecture, table design and workflow rules are proposed for this project.
