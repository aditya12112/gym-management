# 🏋️ GymPro — Smart Gym Management System

A modern, role-based web application for fitness centers, CrossFit boxes, and personal training studios. GymPro streamlines member registrations, slot scheduling with crowd capacity limits, trainer workout & diet plan assignments, membership fee management, and automated expiry email reminders.

---

## 🚀 Key Features

### 👑 Admin Module
- **Role-Based Access Control (RBAC):** Manage Admins, Trainers, and Members from dedicated control views.
- **Gym White-Labeling & Settings (`admin_settings.php`):** Customize Gym Name, Currency Symbol (`₹`, `$`, `€`, `£`), Contact Info, and SMTP configuration without modifying code.
- **Payment Approvals:** Verify offline (Cash) or online (UPI / Card / Net Banking) submissions and dynamically stack membership subscription dates.
- **Time Slot Capacity Manager:** Set maximum capacity per time slot to prevent gym floor overcrowding.
- **Support / Complaint Desk:** View, triage, and reply to member inquiries in real-time.

### 🧑‍🏫 Trainer Portal
- **Member Plan Assignment:** Create and assign personalized diet regimens and exercise routines.
- **Leave Management:** Submit leave applications with automatic status tracking.
- **Roster & Booking Views:** Inspect member bookings per time slot.

### 👤 Member Portal
- **Smart Slot Booking:** Book training slots (enforcing a strict 1-slot-per-day rule and real-time seat availability).
- **Interactive BMI Calculator:** Instant client-side preview with color-coded gauge and personalized dietary/supplement guidance.
- **Membership Status & Renewals:** Track active subscription periods, validities, and past payments.
- **Feedback & Complaints:** Submit tickets directly to the administration.

### 🔒 Enterprise Security & Automation
- **Modern Hashing:** Standard `bcrypt` password encryption with automatic legacy hash upgrades.
- **CSRF Defense:** Full Cross-Site Request Forgery token validation on all sensitive operations.
- **SQL Injection Immune:** Parameterized prepared statements across all database queries.
- **Automated Expiry Notifier:** Scheduled background task (`notify_expiry.php`) sending branded email reminders before memberships expire.
- **Server Guard (`.htaccess`):** Strict Apache security rules blocking unauthorized public access to `.env` and `.sql` files.

---

## 🛠️ Technology Stack
- **Backend:** PHP (7.4 to 8.3 compatible)
- **Database:** MySQL / MariaDB (via `mysqli` prepared statements)
- **Frontend:** HTML5, Modern CSS3 (Custom Variables, Theme Engine, Dark/Light Mode), Vanilla JavaScript
- **Mailing:** PHPMailer (SMTP / TLS / SSL)
- **Server:** Apache / LiteSpeed / Nginx (FastCGI)

---

## 📦 Installation & Setup

### 1. Database Setup
1. Create a MySQL database (e.g., `gym_db`).
2. Import `schema.sql` via phpMyAdmin or command line:
   ```bash
   mysql -u username -p gym_db < schema.sql
   ```

### 2. Environment Configuration
1. Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```
2. Open `.env` and fill in your database credentials:
   ```env
   DB_HOST=localhost
   DB_USER=root
   DB_PASS=
   DB_NAME=gym_db
   ```

### 3. Default Login Credentials
| Role | Email | Password |
| :--- | :--- | :--- |
| **Super Admin** | `admin@gympro.com` | `admin123` |
| **Demo Trainer** | `trainer@gympro.com` | `trainer123` |

*(Note: Change these passwords upon initial login).*

### 4. Background Expiry Reminders (Cron Job)
Add a daily cron job (9:00 AM) to automatically notify members before their subscriptions expire:
```bash
curl -s "https://yourdomain.com/notify_expiry.php?secret=gymcron2024" > /dev/null 2>&1
```

---

## 📂 File Structure Overview

```text
gym_system/
├── assets/                    # Theme stylesheets and JS toggles
│   ├── style.css              # Main responsive styling (Dark/Light tokens)
│   └── theme.js               # Theme state manager
├── PHPMailer/                 # Standalone mailing library
├── .env                       # Local environment configuration
├── .env.example               # Environment template for deployment
├── .htaccess                  # Apache server security rules
├── auth.php                   # RBAC & CSRF token helpers
├── config.php                 # Central DB connection & session bootstrap
├── mailer.php                 # Decoupled transactional email engine
├── schema.sql                 # Complete database schema & seed data
├── index.php                  # Public landing page
├── login.php                  # Multi-role authentication portal
├── register.php               # Member self-registration with OTP
├── admin_dashboard.php        # Admin overview & management hub
├── admin_settings.php         # Gym branding & SMTP setup
├── trainer_dashboard.php      # Trainer control panel
├── member_dashboard.php       # Member self-service panel
├── book_slot.php              # Capacity-controlled slot booking
├── bmi_calculator.php         # Interactive BMI calculation engine
└── notify_expiry.php          # Scheduled expiry reminder script
```

---

## 📄 License
Commercial License. All rights reserved.
