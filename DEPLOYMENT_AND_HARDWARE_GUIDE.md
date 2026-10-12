# Pakistan Offline Pharmacy POS — Deployment & Hardware Integration Guide

## 1. System Overview & Architecture

This application is an enterprise-grade, offline-first Point of Sale (POS), Inventory, and Financial Management System built specifically for pharmacies and drugstores in Pakistan.

### Core Architecture Highlights
- **100% Offline Capability**: Completely operational without an active internet connection. All CSS, JavaScript, fonts, and icons are bundled locally (`public/build/`). No external CDN or cloud dependencies exist.
- **Authoritative Backend**: Financial calculations, taxes, line discounts, lot cost layers, inventory conversions, and cash drawer reconciliations are executed authoritatively by Laravel on the server.
- **Currency & Precision**: Standard currency is the Pakistani Rupee (PKR). All monetary figures in the database are stored as 64-bit integer paisa (`*_paisa`, where PKR 1.00 = 100 paisa) to eliminate IEEE 754 floating-point rounding errors. Lot unit costs are stored with `decimal(14,4)` precision to account for supplier bonus stock discounts.
- **Timezone**: All business transactions, cashier shift stamps, and audit movements operate in `Asia/Karachi` (UTC+5).
- **Concurrency & Deadlock Prevention**: In multi-counter environments, checkout requests lock inventory rows using database-level `lockForUpdate()`. Cart items are strictly sorted by `medicine_id` ascending prior to locking to prevent database deadlocks. Each checkout requires a unique `idempotency_key` to prevent accidental double-billing.

---

## 2. Deployment Topologies

Two deployment topologies are officially supported:

### Topology A: Single-Computer Standalone
For small standalone pharmacies operating a single billing counter where the server and cashier interface run on the same physical PC.
```
+-------------------------------------------------------------+
| Single Pharmacy Computer (Windows 10/11 or Linux)           |
|                                                             |
|  [ Web Browser (Chrome/Edge in Kiosk Mode) ]                |
|                     | (HTTP / Localhost)                    |
|  [ Local Web Server: Nginx / Apache / Laragon / Caddy ]      |
|                     |                                       |
|  [ PHP 8.2+ FastCGI Engine ]                                |
|                     |                                       |
|  [ MySQL 8.x / MariaDB Database (pharmacy_pos) ]            |
|                     |                                       |
|  [ Direct USB / COM ESC/POS Thermal Printer (58mm / 80mm) ]  |
+-------------------------------------------------------------+
```

### Topology B: Multi-Counter Local Area Network (LAN)
For busy retail pharmacies with 2 to 10 billing counters, separate inventory receiving desks, and a manager's office.
```
                               +--------------------------------------------+
                               | Dedicated In-Shop Local Server (No Monitor)|
                               | - Static LAN IP: 192.168.1.100             |
                               | - Windows Server / Ubuntu Linux            |
                               | - Nginx / Apache + PHP 8.2-FPM             |
                               | - MySQL 8.x (InnoDB ACID transactions)     |
                               +--------------------------------------------+
                                                     |
                                       +-------------+-------------+
                                       | Gigabit Switch / LAN Hub  |
                                       +-------------+-------------+
                                                     |
             +-----------------------+---------------+-----------------------+
             |                       |                                       |
+--------------------------+ +--------------------------+ +--------------------------+
| Counter 1 PC             | | Counter 2 PC             | | Network Thermal Printer  |
| - IP: 192.168.1.101      | | - IP: 192.168.1.102      | | - IP: 192.168.1.200:9100 |
| - Barcode Scanner (USB)  | | - Barcode Scanner (USB)  | | - ESC/POS 80mm Ethernet  |
| - Chrome -> 192.168.1.100| | - Chrome -> 192.168.1.100| | - Shared by Counters     |
+--------------------------+ +--------------------------+ +--------------------------+
```

---

## 3. Production Installation & Setup Walkthrough

### 3.1 Prerequisites
- **Operating System**: Windows 10/11 Pro (recommended for retail POS hardware compatibility) or Ubuntu 22.04/24.04 LTS.
- **PHP**: Version 8.2 or 8.3 with the following extensions enabled:
  - `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `sockets`, `curl`, `fileinfo`, `gd`.
  *(Note: The `sockets` extension is required for direct ESC/POS network printing).*
- **Database**: MySQL 8.0+ or 8.4 LTS (InnoDB storage engine, `utf8mb4_unicode_ci`).
- **Web Server**: Nginx or Apache (configured with document root set to Laravel's `/public` folder).
- **Node.js**: v20 or v22 (used on build machine only; not required on production cashier PCs).

### 3.2 Step-by-Step Server Setup

1. **Clone or Copy Application Files** to the host server (e.g. `C:\pharmacy_pos` or `/var/www/pharmacy_pos`).
2. **Configure Environment File**:
   Copy `.env.example` to `.env` and verify key settings:
   ```env
   APP_NAME="Pakistan Pharmacy POS"
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=http://192.168.1.100
   APP_TIMEZONE=Asia/Karachi
   APP_LOCALE=en

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=pharmacy_pos
   DB_USERNAME=pharmacy_user
   DB_PASSWORD=YourSecureLocalPassword

   SESSION_DRIVER=database
   SESSION_LIFETIME=720
   CACHE_STORE=file
   ```

3. **Install Production Dependencies & Precompile Frontend Assets**:
   Run on the build or server machine:
   ```bash
   # Install PHP dependencies without development packages
   composer install --no-dev --optimize-autoloader

   # Install Node packages and build bundled React + Inertia assets
   npm ci
   npm run build

   # Generate encryption key (first install only)
   php artisan key:generate --force

   # Run database migrations and seed default administrative data
   php artisan migrate --force
   php artisan db:seed --force

   # Optimize route, configuration, and view caches
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

4. **Nginx Server Configuration Example (`/etc/nginx/sites-available/pharmacy_pos`)**:
   ```nginx
   server {
       listen 80;
       server_name 192.168.1.100 pharmacy.local;
       root /var/www/pharmacy_pos/public;

       index index.php index.html;

       client_max_body_size 20M;

       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }

       location ~ \.php$ {
           include snippets/fastcgi-php.conf;
           fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
           fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
           include fastcgi_params;
       }

       location ~ /\.(?!well-known).* {
           deny all;
       }
   }
   ```

---

## 4. Hardware Integration: Barcode Scanners, Thermal Printers & Cash Drawers

### 4.1 USB Barcode Scanners
- **Supported Models**: Any standard 1D or 2D handheld USB laser/CCD scanner (Honeywell, Zebra, Datalogic, Netum, Eyoyo).
- **Configuration**:
  - Configure the scanner to operate in **USB HID Keyboard Emulation Mode** (standard factory default).
  - Ensure the scanner transmits a carriage return (`[ENTER]`) suffix after each barcode scan.
  - The POS billing screen listens for rapid keystroke sequences and immediately adds the scanned item to the active bill.

### 4.2 Thermal Receipt Printers (58mm vs 80mm)

The system supports two complementary printing paths:

#### Method 1: Direct Network/LAN ESC/POS Printing (`ThermalReceiptService`)
*Best for: Shared high-speed counter printing where the printer is connected via Ethernet LAN cable.*
- **Protocol**: Direct raw TCP socket stream over port `9100`.
- **Supported Hardware**: Epson TM-T20/TM-T82, Xprinter XP-N160M / XP-Q800, Rongta RP80, Black Copper BC-85AC, Posiflex.
- **Capabilities**:
  - Zero browser dialog delay (sub-200ms instantaneous print).
  - Automatic paper cutter command (`GS V \x42 \x00`).
  - Cash drawer kick pulse (`ESC p \x00 \x19 \xfa`).
  - Configured via Settings (`/settings`) under **Printer Profiles** by specifying the printer's Static IP (e.g. `192.168.1.200`) and Port (`9100`).

#### Method 2: Browser Driver Printing (`window.print()`)
*Best for: Direct USB receipt printers connected to cashier workstations.*
- **How It Works**:
  - When a cashier completes a sale, the system routes to `/sales/{sale}/receipt`.
  - A responsive, pixel-accurate thermal CSS receipt is rendered with dedicated styles:
    ```css
    @media print {
      @page { margin: 0; size: 80mm auto; }
      body { width: 80mm; margin: 0; padding: 2mm; font-family: monospace; }
    }
    ```
- **Silent Kiosk Printing Configuration (Google Chrome / Microsoft Edge)**:
  To eliminate the print confirmation dialog on cashier computers:
  1. Right-click the Google Chrome desktop shortcut and select **Properties**.
  2. In the **Target** field, append:
     ```
     --kiosk --kiosk-printing "http://192.168.1.100/pos"
     ```
  3. Set the default Windows printer to the receipt printer, set paper size to `80 x 297 mm` or `58 x 297 mm`, and disable "Headers and Footers" in print options.
  4. Workstations will print the thermal receipt instantly without cashier intervention upon pressing `F9` or `Enter`.

### 4.3 Cash Drawer Integration
- Connect the RJ11/RJ12 drawer kick cable directly from the cash drawer into the back of the thermal receipt printer (labeled `DK` or `Cash Drawer`).
- When sending ESC/POS raw bytes, `ThermalReceiptService` transmits the standard Epson drawer kick sequence `\x1b\x70\x00\x19\xfa` immediately prior to cutting the receipt.

---

## 5. Daily Cashier Shift & Financial Reconciliation

To guarantee tight financial controls and detect till leakage:
1. **Shift Opening**: At start of shift, the cashier enters the physical drawer opening float (e.g., PKR 5,000) under `/shifts`.
2. **Sales & Collections**:
   - Cash sales automatically increment drawer cash.
   - Credit sales increment customer ledger balance without affecting physical cash.
   - Split tenders (Cash + Card/JazzCash) accurately allocate the cash portion.
3. **Mid-Day Cash Drop & Expenses**:
   - Mid-day cash removals to the pharmacy safe are logged via **Cash Movement -> Cash Out**.
   - Operational drawer disbursements (Tea, cleaning, maintenance) are logged via **Expenses**.
4. **Shift Closing & Variance**:
   - At shift end, the cashier physically counts cash and submits the total.
   - The system executes the authoritative reconciliation formula:
     $$\text{Expected Cash} = \text{Opening Float} + \text{Net Cash Sales} + \text{Cash In} - \text{Cash Out} - \text{Expenses} - \text{Cash Returns}$$
   - Any cash surplus or shortage is permanently recorded in `shifts.variance_paisa`.

---

## 6. Backup, Disaster Recovery & High-Availability

### 6.1 Instant Manual Database Backup
- Managers or administrators can navigate to **Settings -> Database Backups** (`/settings`) and click **"Generate Database Backup Now"**.
- A compressed `.sql` snapshot of the entire database schema and data is written directly to `storage/app/backups/`.

### 6.2 Automated Nightly Backup Script (Windows)
Create a batch script `C:\scripts\backup_pharmacy_pos.bat`:
```bat
@echo off
set TIMESTAMP=%date:~10,4%-%date:~4,2%-%date:~7,2%_%time:~0,2%-%time:~3,2%
set BACKUP_DIR=D:\PharmacyBackups
mkdir %BACKUP_DIR% 2>nul

"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe" -u pharmacy_user -pYourPassword pharmacy_pos > "%BACKUP_DIR%\pharmacy_pos_%TIMESTAMP%.sql"

echo Backup completed: %BACKUP_DIR%\pharmacy_pos_%TIMESTAMP%.sql
```
Schedule this script in **Windows Task Scheduler** to run daily at 02:00 AM. Always keep a secondary copy on an external USB flash drive.

### 6.3 Disaster Recovery / Cold Restore
To restore on a new computer:
```bash
# 1. Create a fresh database
mysql -u root -p -e "CREATE DATABASE pharmacy_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Import the backup file
mysql -u root -p pharmacy_pos < "D:\PharmacyBackups\pharmacy_pos_latest.sql"

# 3. Ensure .env has the original APP_KEY and start the web server
```
