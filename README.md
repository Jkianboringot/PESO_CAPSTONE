# PESO Connect

A web-based workforce skills registration and analytics system built for the Public Employment Service Office (PESO) of Catanduanes Province. It replaces paper-based registration and manual DOLE reporting with a centralized digital platform.


---

## What It Does

- Lets residents register their skills online — no office visit required
- Detects duplicate registrations using a phonetic + demographic matching algorithm
- Provides an interactive analytics dashboard with charts by skill, education, barangay, and registration trend
- Generates DOLE BLE-compliant workforce reports as CSV or Excel
- Maintains a full audit trail for RA 10173 (Data Privacy Act) compliance

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12 (PHP 8.3+) |
| Reactive UI | Livewire 3 |
| Database | MySQL 8.0 |
| CSS / UI | Bootstrap 5.3 |
| Charts | Chart.js 4 |
| Build Tool | Vite 5 |
| Excel Export | Maatwebsite/Excel 3 |

---

## Prerequisites

Make sure these are installed before you begin:

- PHP 8.2+ — `php --version`
- Composer 2.x — `composer --version`
- MySQL 8.0+ — `mysql --version`
- Node.js 18+ and npm — `node --version`
- Git — `git --version`

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/Jkianboringot/PESO_CAPSTONE
cd PESO_CONNECT
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install frontend dependencies

```bash
npm install
```

### 4. Set up your environment file

```bash
cp .env.example .env
php artisan key:generate
```

Open `.env` and set your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=peso_connect
DB_USERNAME=root
DB_PASSWORD=your_password

APP_NAME="PESO Connect"
APP_URL=http://localhost:8000
SESSION_DRIVER=database
```

### 5. Create the database

```bash
mysql -u root -p -e "CREATE DATABASE peso_connect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 6. Run migrations and seed data

```bash
php artisan migrate:fresh --seed
```

This creates all 11 tables and seeds:
- All 11 Catanduanes municipalities and their barangays
- 10 PQF-aligned skill categories and ~45 skills
- Staff and Administrator roles
- A default admin account (see credentials below)

### 7. Build frontend assets

```bash
npm run build
```

### 8. Start the development server

```bash
php artisan serve
```

The app is now running at **http://localhost:8000**

---

## Default Credentials

> **Change these before deploying to production.**

| Field | Value |
|---|---|
| URL | http://localhost:8000/login |
| Email | admin@peso-catanduanes.gov.ph |
| Password | PESoAdmin@2025! |

---

## User Roles

There are three access levels in the system:

**Resident Applicant** — No login required. Accesses the public registration form at `/register`. Submits personal info, education, and skills. Receives a unique Reference ID on completion.

**PESO Staff** — Authenticated users with the `staff` role. Can manage applicant records, review duplicate flags, view analytics, and download DOLE reports. Cannot manage user accounts.

**Administrator** — Has all staff permissions plus access to `/admin/users` for creating, editing, and deactivating staff accounts and `/admin/audit-logs` viewing activity logs of staff and admin.

---

## Key URLs

| URL | Access | Description |
|---|---|---|
| `/` | Public | Landing page |
| `/register` | Public | Resident skills registration form |
| `/login` | Public | Staff / Admin login |
| `/dashboard` | Staff + Admin | Summary statistics |
| `/applicants` | Staff + Admin | Applicant records (search, filter, edit) |
| `/analytics` | Staff + Admin | Workforce analytics dashboard |
| `/duplicates` | Staff + Admin | Duplicate flag review queue |
| `/reports` | Staff + Admin | DOLE report generator |
| `/skills-gap` | Staff + Admin | Skills gap analysis |
| `/geogrophical` | Staff + Admin | Geogrophical management |
| `/admin/users` | Admin only | User account management |
| `/admin/autdit-logs` | Admin only | Audit logs |
---

## Project Structure

```
peso-connect/
├── app/
│   ├── Livewire/              # All reactive UI components
│   ├── Models/                # Eloquent models (Applicant, Skill, etc.)
│   ├── Exports/               # ApplicantsExport (Maatwebsite/Excel)
│   └── Providers/              # DuplicateDetectionService, AuditLogService
├── database/
│   ├── migrations/            # 11 ordered migration files
│   └── seeders/               # Geography, skills, roles, admin user
├── resources/
│   └── views/
│       ├── layouts/           # app.blade.php, guest.blade.php
│       └── livewire/          # Blade views for each component
└── routes/
    └── web.php                # All application routes
```

---

## How Duplicate Detection Works

When a new applicant submits the form, the system scores them against all existing active applicants using three criteria:

1. **Phonetic name match** — Uses PHP's `metaphone()` function on the last name, with an 85% string similarity fallback for typos
2. **Exact birthdate match** — Compares full date of birth
3. **Contact number match** — Compares the last 7 digits (handles `+63`, `0`, and `63` prefixes)

A score of **2 or more** creates a duplicate flag and sets the applicant's status to `Flagged`. PESO staff then review flagged pairs side-by-side and choose to Merge, Retain Both, or Delete the newer record.

---


## License

This project was developed as a capstone project for BSIS 3A at Catanduanes State University (2025–2026). For academic and institutional use.
