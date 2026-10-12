# Pharmacy POS — Code Audit

Audit date: 12 October 2026  
Repository: https://github.com/abdulrehman24/pharmacy-pos  
Reviewed commit: 7cb90d79d33d900f40624128b1ae059c12c8182a  
Stack: Laravel 12, React 18, Inertia 2, TypeScript, MySQL deployment / SQLite tests

## Verdict

**Do not deploy this commit for real pharmacy transactions yet.** There is substantial database-backed implementation, but authorization, historical-data preservation, returns, cash reconciliation and recovery have release-blocking defects. A successful frontend build does not establish financial or stock correctness.

This is a repository-wide static review plus an executed frontend build. It is not a completed running-system or physical-device certification. No source fixes, commits, pushes, production connections or changes to shop data were made.

## Verification performed and limits

| Check | Result |
|---|---|
| Repository checkout and commit identification | Completed |
| Routes, controllers, actions, services, models and migrations | Reviewed |
| React POS, receipts, prescriptions and related frontend structure | Reviewed |
| Existing feature tests and test configuration | Reviewed; not executed |
| npm ci --ignore-scripts --no-audit --no-fund | Passed; 240 packages installed |
| npm run build (tsc + Vite) | Passed; 2,591 modules transformed |
| PHP / Composer availability | Neither executable available |
| Laravel/PHPUnit execution | Not run: PHP/Composer unavailable |
| MySQL concurrency and migration execution | Not run |
| Browser end-to-end workflows | Not run: application server unavailable |
| Windows launcher, USB/LAN printer and backup restore | Not physically tested |
| Dependency vulnerability audit | Not performed; no security-clean claim |

Findings below are grounded in source inspection. Reproduction descriptions are targeted test scenarios, not claims that they were executed against the app. Line references refer to the reviewed commit.

## Priority index

| ID | Severity | Finding |
|---|---|---|
| A01 | Critical | Role enforcement absent from sensitive operations |
| A02 | Critical | Self-account deletion cascades into transaction history |
| A03 | High | Checkout retries generate fresh idempotency keys |
| A04 | High | Server idempotency does not verify payload/owner or resolve concurrent replay |
| A05 | High | Returns ignore invoice discount/tax allocation |
| A06 | High | Duplicate/concurrent return lines can exceed sold quantity |
| A07 | High | Multi-batch returns restore everything to the first allocation |
| A08 | High | Refund method is not reconciled with unpaid debt |
| A09 | High | Shift ownership and closing consistency are not enforced |
| A10 | High | Credit limits can be bypassed by partial payment and concurrent sales |
| A11 | High | Supplier payments and some changes corrupt expected drawer cash |
| A12 | High | Inventory writes do not share safe transaction/locking rules |
| A13 | High | Purchase packaging can belong to a different medicine |
| A14 | High | A later receipt silently overwrites existing batch expiry |
| A15 | High | Stock costs omit invoice-level discount allocation |
| A16 | High | Customer collections leave invoice balances/status stale |
| A17 | High | Reports misstate return-adjusted COGS, losses and collections |
| A18 | High | Backups are incomplete and shell invocation is fragile |
| A19 | High | Prescription restrictions are not connected to checkout |
| A20 | High | Known seed credentials and inactive-user login remain enabled |
| A21 | Medium | LAN print button uses invoice number against ID-bound route |
| A22 | Medium | Print state, serialization and Windows RAW support are incomplete |
| A23 | Medium | Audit infrastructure is present but not called |
| A24 | Medium | Pakistan date boundaries and offline assets are inconsistent |
| A25 | Medium | Catalogue writes can partially persist and nullable inputs can fail |
| A26 | Medium | Launcher relies on working directory and development server |
| A27 | Medium | Quarantined returns have no physical quantity record |
| A28 | Medium | Core module completeness and data-scale gaps remain |

Severity reflects potential impact in this app, not a formal CVSS score.

## A01 — Critical: Sensitive operations lack authorization

**Evidence:** routes/web.php:24 wraps business routes in auth only. SettingController.php:99 permits role=admin on staff creation without a manager/admin gate; createBackup/downloadBackup also lack authorization. Inventory approval, returns, reports and ledger actions generally have no policy checks. User::hasPermission exists but is not used to enforce these operations. Prescription verification has a role check, which does not protect the remaining modules.

**Impact:** A logged-in cashier can create an administrator, access backups, change shop tax/printer settings, approve stock counts and access sensitive financial/patient data. Public registration in routes/auth.php creates active cashier accounts, expanding the access path to any user able to reach the local server.

**Fix:** Close public registration after controlled initial setup. Add backend policies/gates to every sensitive route/action and object-level access to shifts/receipts. Verify authorization using direct HTTP requests, not just hidden buttons. Restrict printer destinations because editable hosts allow server-origin network connections.

**Required test:** Cashier POST /settings/user with role=admin returns 403 and creates nothing; backup download, count approval and unrestricted report/patient access are denied by role.

## A02 — Critical: Deleting a user can erase financial history

**Evidence:** ProfileController.php:56 calls $user->delete(). Sales migration 000009:17 uses cashier_id constrained users cascadeOnDelete; related sale lines and allocations also cascade. Other user-owned purchases/shifts/expenses have destructive relationships.

**Impact:** Deleting a cashier can delete invoices/allocations while stock deductions and customer/supplier balances remain. Reports and historical cost evidence are lost; drawer-related history can also disappear.

**Fix:** Remove ordinary account deletion. Disable users, retain identity references, and use restrictive foreign keys for posted financial records. Migrate constraints without deleting historical data.

**Required test:** Disabling a cashier retains all sales, purchases, stock movements, shifts and ledgers. Attempted physical deletion with posted records is rejected.

## A03 — High: UI retry can post a second sale

**Evidence:** Pos/Index.tsx:564 creates a new idempotency key inside each handleCheckout call.

**Scenario:** Server commits a sale, its response is lost, cashier retries. The cart remains but a new key is submitted, producing a second sale and stock deduction if stock allows.

**Fix:** Persist one checkout key and payload fingerprint per sale attempt until its outcome is resolved. Reuse the key on retry and recover the existing receipt. New key only for a deliberately new checkout.

## A04 — High: Server replay handling is incomplete

**Evidence:** FinalizeSale.php:33 checks for an existing key before the transaction and returns it without comparing payload or actor. Migration makes the key unique, but no duplicate-insert recovery is implemented.

**Impact:** Same key/different cart returns an unrelated invoice; simultaneous same-key requests can produce a unique-constraint error rather than the same successful response. Missing keys are generated server-side, preventing client retry recovery.

**Fix:** Require a client key, bind it to a canonical request hash and authorized context, claim it atomically, and resolve concurrent duplicates to the existing committed result. Return a clear conflict on changed content.

## A05 — High: Return amounts do not reflect invoice totals

**Evidence:** ProcessCustomerReturn.php:81 calculates rounded sale_line.total_paisa / quantity. PricingService records invoice discount and global tax only in the sale totals, not allocated to lines.

**Scenario:** One item costs PKR 100 and invoice discount is PKR 20. Customer pays PKR 80, but a full return refunds PKR 100. Conversely, applicable invoice tax is omitted from line refunds. Repeated unit rounding can also drift.

**Fix:** Persist deterministic allocation of invoice discounts/taxes across lines/units and calculate cumulative remaining refundable amounts. Enforce a reconciled refundable cap and exact final-unit rounding.

## A06 — High: Return quantity can be exceeded

**Evidence:** ProcessCustomerReturn.php:70 checks remainingReturnableQty without locking the sale/line. Return lines are created only after the input loop, and repeated sale_line_id entries are not rejected.

**Scenario:** A sale line has one remaining unit. Submit two entries for that same line with quantity=1: both checks see one remaining unit before either line is inserted. Concurrent return requests have the same race.

**Fix:** Reject or aggregate duplicate line IDs; lock the sale and lines, compute prior returns under the lock, and post quantities/refunds atomically.

## A07 — High: Returns lose original batch allocation

**Evidence:** ProcessCustomerReturn.php:90 uses allocations->first() for all returned quantity.

**Scenario:** A sale uses one unit from batch A and four from B. Returning all five restores all five to A, corrupting batch traceability, expiry and stock valuation.

**Fix:** Track return allocations against original sold lots and already-returned allocations. Where batch identity is uncertain, quarantine rather than invent the source. Test cross-lot returns with different costs.

## A08 — High: Refund and debt can disagree

**Evidence:** ProcessCustomerReturn only decrements customer balance for credit_adjustment; it does not reconcile sale.balance_paisa/payment_status and allows cash returns on unpaid credit sales. Credit adjustment is not capped to unpaid receivable and has no required-customer validation.

**Impact:** A customer can receive cash for a purchase never paid, while debt remains; a fully paid customer's return can create a negative balance without an explicit customer-credit workflow.

**Fix:** Separate stock disposition, receivable reversal, customer credit and refund payment. Apply returns to the invoice's current financial state; limit cash refunds to appropriately settled value and update invoice allocations/status.

## A09 — High: Shift assignment and close are unsafe

**Evidence:** FinalizeSale accepts any open shift ID and falls back to any latest open shift, or no shift. PosController and collections/returns also fall back across users. ShiftController.close/cashMovement lack owner checks and locked state transitions.

**Impact:** Transactions hit another cashier's drawer or no shift; one user can close another's shift; a checkout can post after close computes expected cash.

**Fix:** Require the authenticated user's authorized open counter shift. Lock it in all financial operations and during close. Enforce one open shift per intended user/counter rule and validate inactive counters.

## A10 — High: Credit enforcement is bypassable

**Evidence:** FinalizeSale.php:84 checks credit limits only for payment_method=credit and outside the posting transaction. The partial-payment branch does not enforce customer active state or credit limit.

**Scenario:** Select cash with received=0 and a customer to obtain credit without its limit checks. Two concurrent credit sales can both pass the pre-transaction balance check.

**Fix:** Apply the same account/limit rule to every outstanding amount; lock the customer and evaluate balance plus new debt inside the transaction.

## A11 — High: Expected drawer cash is wrong

**Evidence:** Shift.php:82 subtracts change across all payment methods. SupplierController.recordPayment links supplier payments to shifts, but Shift.calculateExpectedCashPaisa never subtracts supplier cash payments. PostPurchase records upfront cash payment without a drawer movement.

**Scenarios:** Pay supplier PKR 500 from drawer: expected cash stays PKR 500 too high. A card sale with over-tendered received amount creates change that is subtracted from cash even though cashSales excludes the card transaction.

**Fix:** Post typed financial payment movements with source account/method. Reconcile cash-only inflows/outflows including supplier payments and upfront purchases. Disallow noncash change unless an explicit cash withdrawal/change workflow is modeled.

## A12 — High: Other stock writes can conflict with checkout

**Evidence:** InventoryController.writeOff:176 performs multiple writes with no DB transaction or stock row lock. Supplier return checks availability before its transaction and does not lock. PostStockAdjustment:16 checks draft state outside the transaction and reads/saves lots without locks. Count input trusts browser system_quantity at InventoryController:146. Adjustment clamps quantity at zero but writes the full requested variance.

**Impact:** Partial write-offs, duplicated approval, lost stock updates or oversubscription can occur. Example: current stock=2, stored variance=-5; adjustment writes final stock=0 but ledger movement=-5, so balances disagree.

**Fix:** Put every stock mutation in a transaction with a shared consistent locking strategy. Obtain system quantity and medicine/batch/location relationships from the server. Lock approval state, reject stale counts or reconcile count-time movements, and record the actual applied movement.

## A13 — High: Purchase package is not scoped to its medicine

**Evidence:** PostPurchase.php:62 looks up MedicinePackage by ID alone; PurchaseController only validates that the package exists.

**Scenario:** Purchase medicine A using a package from medicine B with base_quantity=100. Stock for A is incorrectly multiplied by B's conversion.

**Fix:** Query package through the selected medicine and validate the relationship before posting. Test a mismatched payload returns 422 with no stock/payable writes.

## A14 — High: Receiving rewrites existing expiry

**Evidence:** InventoryService.php:114 updates an existing medicine/batch's expiry whenever the new receipt differs. Batch uniqueness excludes expiry.

**Impact:** An old expired lot can become eligible for sale when a later receipt changes its shared batch expiry.

**Fix:** Reject inconsistent batch expiry for review or represent a distinct validated batch identity. Never silently extend the expiry of existing stock.

## A15 — High: Purchase discount does not reduce stock cost

**Evidence:** PostPurchase calculates unitCostPaisa from gross package cost/received quantity before applying invoice discount. The discounted payable and undiscounted lot valuation diverge.

**Scenario:** Stock with gross cost PKR 1,000 and PKR 100 discount is valued at PKR 1,000 while invoice cost is PKR 900.

**Fix:** Define net cost allocation for invoice discounts, charges and appropriate tax treatment and persist it across receipt layers. Preserve sufficient decimal precision.

Additional linkage defect: InventoryService.php:141 writes reference_type=Purchase with reference_id=stockLot.id, which is not necessarily the purchase ID. Pass the actual purchase/line reference.

## A16 — High: Collections do not settle invoices

**Evidence:** CustomerController.php:107 creates CustomerPayment and decrements aggregate customer balance only. No invoice payment allocation updates sales balances or status. Supplier payments likewise do not update purchase paid amounts.

**Impact:** Customer balance can become zero while the sale remains unpaid and its receipt shows an old balance. Reliable invoice aging and allocation-based return handling are unavailable.

**Fix:** Introduce invoice allocations or explicit on-account credits with reconciliation. Prevent accidental over-collection or intentionally represent customer deposits; handle duplicate payment submission.

## A17 — High: Financial reports misstate margins/collections

**Evidence:** ReportController:49 and DashboardController calculate margin after deducting return revenue but keep all sale COGS; both clamp negative margin to zero. Reports:62 sums package quantity grouped by medicine name, mixing boxes/strips/tablets. Payment breakdown uses invoice totals instead of settled amounts for partial sales; dashboard collections omit old-credit collections/refunds and use full totals for noncash partial payments.

**Impact:** Returned/restocked goods leave incorrect COGS; real losses are hidden; collection reports overstate receipts. Same-name items or different units can merge incorrectly.

**Fix:** Define separate sales, tax, return revenue, returned cost, losses and payment-ledger reports. Allow negative margin; group by medicine ID and use base quantities or split units. Include payment allocations and cash reconciliation.

## A18 — High: Backup is not a complete recovery solution

**Evidence:** SettingController.php:126–163 runs interpolated mysqldump arguments through exec, redirects stderr into SQL output, and does not request single-transaction consistency. Only SQL is saved; prescription files are stored separately. No application restore/verification flow is routed, and routes/console.php has no backup schedule.

**Impact:** Config values containing shell-special characters can break invocation or be interpreted by the shell; credentials appear in command arguments. SQL may contain diagnostics. Database-only restore loses prescription attachments; no proven complete recovery exists. Second-resolution names can collide.

**Fix:** Use a process argument array, protected credential configuration, consistent InnoDB dump, separate diagnostics, unique filenames and verified backup manifests. Include private attachments and recovery key/config securely. Provide protected restore with pre-restore backup and run an actual recovery drill. Restrict downloads under A01.

The deployment guide has a scheduled-script example, which is documentation rather than an installed scheduler; its success echo does not verify dump exit status.

## A19 — High: Prescription workflow is disconnected

**Evidence:** Catalogue/POS expose prescription_required, but FinalizeSale has no prescription verification/link validation. PrescriptionController:98 marks status=dispensed during verification even without an invoice; dispensing records do not capture actual medicine quantities in this path.

**Impact:** A restricted medicine can be sold without review, while a prescription can be recorded as dispensed without any sale.

**Fix:** Separate verified and dispensed states; link prescription lines and pharmacist approval to checkout, record actual quantities/partial dispensing and enforce required review. Add authorized attachment viewing/download; the current page shows an attachment indicator but no complete retrieval workflow.

## A20 — High: Seed credentials and inactive accounts

**Evidence:** DatabaseSeeder:113–154 creates privileged/default staff with known fixed passwords. Deployment guide instructs db:seed --force. LoginRequest:45 checks email/password without is_active; middleware does not enforce active status.

**Impact:** A freshly deployed seeded installation has publicly knowable privileged credentials; disabled accounts can still authenticate.

**Fix:** Separate demo seeds from production bootstrap. Generate unique first-use credentials, require rotation, and reject inactive accounts at login and on existing sessions. Protect any functional PIN with appropriate hashing.

## A21 — Medium: LAN print route does not bind

**Evidence:** Sales/Receipt.tsx:68 posts /sales/{sale.number}/print, but the route binds Sale by its default ID. Sale model does not override getRouteKeyName. ReceiptDataFactory supplies sale.id.

**Impact:** LAN Print on a normal INV-prefixed invoice should return 404 before reaching the printer.

**Fix:** Use the named Ziggy route with sale.id, or deliberately bind invoice_number consistently. Add HTTP binding and UI integration tests.

## A22 — Medium: Print workflow is incomplete

**Evidence:** ReceiptController sends synchronously with no per-printer lock/queue, marks browser fallback submitted, and routes windows_raw profiles to the browser fallback. There is no unknown/uncertain outcome state. Copy flag is user-controlled; browser prints do not create audit jobs. ThermalReceiptService silently removes non-ASCII characters.

**Impact:** Concurrent print jobs have no application serialization, browser-ready is mistaken for submitted, RAW support appears selectable but is not implemented, and Urdu names disappear. Repeat originals can be unmarked copies.

**Fix:** Implement serialized per-printer jobs, distinguish browser_ready/submitted/failed/unknown, implement or remove RAW selection, audit reprints and render Urdu as supported raster/driver output. Test 58/80 mm margins and physical devices.

## A23 — Medium: Audit records are not generated

**Evidence:** AuditEvent::log exists, but repository search finds no application callers/observers writing the required events.

**Impact:** Staff/user creation, discounts, approvals, settings, refunds and backup access lack the promised audit history.

**Fix:** Explicitly record events at successful domain transitions with actor, reason, approval and appropriate before/after snapshots; exclude secrets/patient content where unnecessary. Add tests for event creation and denial paths.

## A24 — Medium: Offline/date behavior is inconsistent

**Evidence:** config/app.php:68 uses UTC; sale invoice dates, FEFO and reporting use now()/whereDate without consistently converting local boundaries. Receipt timestamps and dashboard day selection use Karachi separately. app.blade.php:10–11 fetches Bunny fonts online.

**Impact:** Around Pakistan midnight, billing/report/expiry boundaries disagree. Daily totals may differ from receipt dates. Network font calls violate locally bundled asset requirements, although fallback fonts can still render.

**Fix:** Choose one timestamp storage policy with explicit Karachi start/end ranges for queries and expiry rules. Test boundary times. Bundle fonts locally and test fully disconnected startup.

## A25 — Medium: Catalogue/nullable-input failures

**Evidence:** MedicineController.store creates medicine/packages/barcodes without one transaction. Incoming barcode uniqueness is checked before trimming and does not reject duplicate barcodes within the same array. Several controllers access nullable validated keys directly rather than using defaults.

**Impact:** A duplicate barcode insert can fail after creating partial catalogue records. Omitting optional fields in valid direct requests can trigger undefined-key errors rather than a normal successful operation.

**Fix:** Normalize before validation, enforce distinct incoming barcodes/default-package rules, wrap catalogue writes atomically, and use explicit nullable defaults. Apply the same approach to multi-write prescription/count operations.

## A26 — Medium: Launcher behavior is fragile

**Evidence:** Start_Silent.vbs:3 derives batch path from CurrentDirectory, not its own script location. Startup/task-scheduler paths may therefore resolve incorrectly. Start_Pharmacy_POS.bat:24 launches artisan serve and checks only whether a port is listening, not whether the app is healthy. Guide references 8000 while script uses 8080.

**Fix:** Resolve script paths with WScript.ScriptFullName, implement health/readiness checks and clear failure messages, and use a managed production local web service. Test reboot, login/logout, changed working directory and port conflict.

## A27 — Medium: Quarantined returns disappear from physical stock

**Evidence:** InventoryService.restockItem creates a zero-quantity movement for quarantine/write_off but does not create a separately quantified quarantine lot or explicit returned-stock loss record.

**Impact:** Stock is physically returned to the shop but cannot be counted/reconciled as quarantined stock. Write-off losses from returned goods are not represented coherently.

**Fix:** Receive into a non-sellable disposition/location with traceable allocation, then perform a separate approved disposal/release movement. Reconcile physical and sellable quantities.

## A28 — Medium: Completeness and scale gaps

**Evidence:** Routes provide catalogue create/show but no edit/archive workflow; no import/opening-stock workflow, purchase-order/partial-receiving workflow, payment allocations/aging reports, recall management screen, permission editor or restore endpoint. Return supplier workflow immediately reduces any selected supplier payable without verifying original supplier provenance or waiting for credit confirmation. Pos/Counts/Purchases load complete active item/customer lists; dashboard performs per-medicine stock queries.

**Impact:** Existing-stock onboarding and ongoing corrections need manual work; supplier returns can affect the wrong supplier; large catalogues can cause slow pages and excessive queries.

**Fix:** Complete the core gaps, distinguish dispatched returns from confirmed credits, associate lots with original supplier/purchase, add pagination/server lookup and aggregate stock queries. Benchmark realistic thousands-of-medicines data.

## Module coverage against specification

“Partial” means meaningful code exists but the module is incomplete or has correctness defects; it is not runtime acceptance.

| Module | Status | Main remaining work |
|---|---|---|
| Dashboard | Partial | Correct collections/margin/time ranges; authorization and scale |
| Medicine catalogue | Partial | Edit/archive, atomic writes, imports, package validation |
| Batches/inventory | Partial | Shared locking, safe counts, recall/quarantine and opening stock |
| Units/packaging | Partial | Purchase relationship checks and historical/default rules |
| POS/sales | Partial | Retry safety, shift/credit enforcement, prescription linkage |
| Purchases/suppliers | Partial | Net costs, invoice allocations, orders/partial receipts and duplicate handling |
| Returns/losses | Unsafe for release | Refund amounts, duplicate/concurrent checks, allocations/dispositions |
| Customers/credit | Partial | Limit enforcement, payment allocations and accurate settlement |
| Prescriptions | Partial | Review/dispensing linkage, quantity records and attachment access |
| Cash/shifts/expenses | Partial | Ownership, closing locks, supplier cash and payment ledger |
| Reports | Partial | Return costs, negative margins, settlement and aging reports |
| Permissions/audit | Unsafe for release | Route policies, public registration, inactive enforcement, event hooks |
| Settings/backups | Partial | Recovery completeness, scheduling, authorization, tested restore |
| Receipts/hardware | Partial | ID-bound LAN URL, RAW support, job states, hardware acceptance |
| Offline installation | Partial | Local fonts, managed server, robust startup and recovery |

## What is already useful

- Prices and package conversion are generally resolved on the Laravel side.
- Sale/purchase/customer-return actions use database transactions.
- Checkout FEFO filters sellable, non-expired batches and uses row locks.
- Receipt cost layers and sale allocation cost snapshots exist.
- Integer paisa fields, invoice snapshots and unique checkout keys are present.
- Login rate limiting and hashed passwords are provided.
- Core React pages and domain feature tests exist; TypeScript/build passes.

These foundations should be preserved while correcting the defects.

## Recommended repair order

1. Close unauthorized access, remove destructive account deletion and replace production seed credentials.
2. Repair return quantities/refunds/allocations and payment/receivable semantics.
3. Implement common stock/shift/customer locking and stable idempotency.
4. Reconcile costs, cash movements, invoice settlement and reports.
5. Complete recoverable backups and deployment safety.
6. Fix printer routing/job states and prescription integration.
7. Complete imports, catalogue maintenance, audit events and missing operational reports.
8. Run acceptance on local MySQL and chosen Windows/printer hardware.

## Required regression and acceptance tests

- Cashier direct requests denied for user creation, backup, count approval, protected reports and prescriptions.
- Inactive login and existing inactive sessions denied.
- Disable staff without removing posted history.
- Lost checkout response + retry returns the same sale.
- Same key/different payload conflicts; simultaneous same-key requests resolve correctly.
- Two counters selling last stock; sale versus write-off/return/count races.
- Duplicate and concurrent return lines; multi-batch return quantities and costs.
- Full/partial returns with invoice discounts, taxes and final-unit rounding.
- Unpaid-credit return, partially settled return and excess credit adjustment.
- Partial-cash credit bypass and simultaneous credit-limit requests.
- Supplier payments, purchase payments, collections and noncash overpayment in shift closing.
- Sale versus shift close; another cashier's shift manipulation.
- Foreign package on purchase, conflicting batch expiry and net discounted cost.
- Invoice allocation/settlement, report loss visibility and returned COGS.
- Minimal valid payloads with omitted optional fields; duplicate normalized barcode rollback.
- Database + attachments backup with successful restoration and failure diagnostics.
- LAN print route, unknown result, serialized jobs and marked reprints.
- Karachi midnight/date boundaries; disconnected login/billing/reboot.

The current tests use in-memory SQLite. Sequential happy-path tests do not establish MySQL row-lock/concurrency correctness. Add independent MySQL connections/processes for race tests and keep SQLite tests only where appropriate.

## Handoff prompt for Antigravity

Read this audit and the project specification completely. Work from the reviewed commit or identify any newer changes first. Reproduce each finding with a focused test, then repair in the order listed above. Preserve existing data and use additive/reversible migrations where possible. Do not rebuild the interface or add future cloud/AI modules while financial/security defects remain.

Implement server-side authorization, preserve transaction history, fix checkout and return idempotency/concurrency, reconcile payments/costs/reports, and prove backup restoration. Maintain a checklist mapping every audit ID to its test, change and remaining limitation. Run PHP tests, MySQL concurrency tests, TypeScript checking and production build. Do not label printer or Windows behavior verified without actual device testing.
