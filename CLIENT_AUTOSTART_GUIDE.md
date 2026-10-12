# Non-Technical Client Auto-Start & 1-Click Launch Guide

This guide explains how to set up the Pharmacy POS so that a non-technical shopkeeper or cashier never has to touch a terminal, run commands, or know what `php artisan serve` means.

---

## Method 1: The 1-Click Desktop Shortcut (Recommended)

Your client gets a standard desktop icon labeled **"Pharmacy POS"** on their Windows Desktop.

### What happens when the client double-clicks the icon:
1. It silently checks if the background server is already running.
2. If not running, it **starts the server silently in the background** (no black command prompt or terminal window flashes on screen).
3. It opens **Google Chrome or Edge in App Mode** (`--app=http://localhost:8000/pos`), which displays the POS as a **clean, native desktop window** with no browser tabs, search bar, or address bar.

### How to set it up for your client:
1. Open the project folder on the client's PC.
2. Navigate into `launcher\windows\`.
3. Right-click **`Install_AutoStart.bat`** and select **Run as Administrator** (or double-click it).
4. The script will:
   - Create a clean **`Pharmacy POS`** shortcut directly on their **Desktop**.
   - Ask if you want to auto-boot the server when Windows starts.
5. You are done! The client now simply double-clicks **"Pharmacy POS"** on their Desktop every day.

---

## Method 2: 100% Zero-Click (Starts Automatically When PC Powers On)

If you want the server to start automatically when the computer turns on in the morning before the cashier even sits down:

### Option A: Using the Windows Startup Folder (Simplest)
1. Press `Win + R`, type `shell:startup`, and press **Enter**.
2. Copy [`launcher/windows/Start_Silent.vbs`](launcher/windows/Start_Silent.vbs) into this folder (or create a shortcut to it).
3. Whenever Windows turns on and the cashier logs into Windows, the server will boot automatically in the background.
4. The cashier just opens their browser or desktop shortcut.

### Option B: Using Windows Task Scheduler (Runs Before Login)
1. Press `Win + R`, type `taskschd.msc`, and press **Enter**.
2. Click **Create Basic Task**:
   - **Name**: `Pharmacy POS Background Server`
   - **Trigger**: `When the computer starts`
   - **Action**: `Start a program`
   - **Program/script**: `wscript.exe`
   - **Arguments**: `"C:\path\to\pharmacy_pos\launcher\windows\Start_Silent.vbs"`
3. Check **Run whether user is logged on or not**.

---

## Method 3: Permanent Local Web Server (Nginx / Laragon / Apache as Windows Service)

For high-volume retail pharmacies running 2 to 10 LAN counters:
1. Install **Laragon** or **XAMPP** on the server PC.
2. In Laragon / XAMPP, enable **"Run as Service"** for both Apache/Nginx and MySQL.
3. This registers them as official Windows Services:
   - They boot before any user logs in.
   - They never crash or close if a user logs off.
   - Any PC connected to the shop Wi-Fi or LAN cable can access `http://192.168.1.100/pos`.

---

## Testing on macOS (Development Machine)
If you are currently on macOS and want to test the 1-click launcher experience right now:
- Double-click [`launcher/mac/Start_Pharmacy_POS.command`](launcher/mac/Start_Pharmacy_POS.command).
- It will verify the background server and open Google Chrome directly in App window mode.
