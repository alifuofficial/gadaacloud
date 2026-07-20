<p align="center">
  <img src="storage/app/public/media/logo_dark.png" alt="GadaaCloud Logo" width="260"/>
</p>

<h1 align="center">GadaaCloud ERP</h1>

<p align="center">
  A modern, modular, cloud-ready Enterprise Resource Planning platform built with Laravel 12 + React 18 + Inertia.js
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white"/>
  <img src="https://img.shields.io/badge/React-18.x-61DAFB?style=for-the-badge&logo=react&logoColor=black"/>
  <img src="https://img.shields.io/badge/TypeScript-5.x-3178C6?style=for-the-badge&logo=typescript&logoColor=white"/>
  <img src="https://img.shields.io/badge/Inertia.js-2.x-9553E9?style=for-the-badge&logo=inertia&logoColor=white"/>
  <img src="https://img.shields.io/badge/Vite-5.x-646CFF?style=for-the-badge&logo=vite&logoColor=white"/>
  <img src="https://img.shields.io/badge/TailwindCSS-3.x-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white"/>
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white"/>
</p>

---

## Table of Contents

- [Overview](#overview)
- [Key Features](#key-features)
- [Architecture](#architecture)
- [Module System](#module-system)
  - [Core Business Modules](#core-business-modules)
  - [HR & Workforce Modules](#hr--workforce-modules)
  - [Productivity & Collaboration](#productivity--collaboration)
  - [Payment Gateways](#payment-gateways)
  - [Integrations & Notifications](#integrations--notifications)
  - [Premium Add-ons](#premium-add-ons)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Configuration](#configuration)
- [Running the Application](#running-the-application)
- [Module Development](#module-development)
- [Contributing](#contributing)
- [License](#license)

---

## Overview

**GadaaCloud** is a fully modular, multi-tenant ERP system designed for businesses of all sizes. It combines a robust Laravel 12 backend with a modern React 18 + TypeScript frontend, delivered seamlessly through Inertia.js — no separate API layer needed.

Built around a **plug-and-play package architecture**, each business feature (Accounting, HRM, Inventory, CRM, etc.) lives as an independent module under `packages/workdo/`. Modules can be enabled, disabled, or extended without touching the core application.

GadaaCloud also includes a unique **Ethiopian Calendar** module, making it particularly suited for East African businesses requiring local date formats.

---

## Key Features

- 🏗️ **Modular Architecture** — 58+ plug-and-play modules under `packages/workdo/`
- 🧾 **Full Accounting Suite** — Double-entry bookkeeping, budget planner, goal tracking
- 👥 **HRM** — Employees, payroll, attendance, recruitment, training, performance reviews
- 📦 **Inventory Management** — Stock adjustments, reorder rules, audit trail, serial tracking
- 💳 **20+ Payment Gateways** — Stripe, PayPal, Razorpay, Flutterwave, M-Pesa and more
- 🤖 **AI Assistant** — Built-in AI tooling for productivity
- 📅 **Ethiopian Calendar** — Full Ge'ez calendar integration for local businesses
- 🔄 **Workflow Studio** — Custom multi-step document approval workflows
- 📊 **POS System** — Point-of-sale module with real-time inventory sync
- 🌍 **Multi-language** — i18n support with 20+ languages (AR, ZH, FR, DE, IT, JA, and more)
- 🔐 **Role-based Access Control** — Spatie permissions with granular module-level ACL
- 🔔 **Real-time Notifications** — Pusher + Laravel Echo WebSocket support
- 📁 **Media Management** — Spatie Media Library with AWS S3 support
- 🔑 **2FA** — Google Authenticator 2-factor authentication
- 🔗 **SSO** — Laravel Socialite for OAuth login (Google, Microsoft, etc.)
- 📧 **Email & SMS** — Twilio, Mailchimp, IMAP integration
- 🗓️ **Calendar** — FullCalendar integration with Google Calendar sync
- 📋 **Form Builder** — Dynamic form creation per module
- 🛒 **E-commerce** — Quotation, contracts, sales proposals, POS
- 📈 **Reports** — PDF/Excel export via jsPDF + PHPSpreadsheet

---

## Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                       Browser (React 18 + TS)                │
│   Inertia.js SPA │ Radix UI │ TailwindCSS │ Recharts         │
└────────────────────────────┬─────────────────────────────────┘
                             │  Inertia Protocol (no REST API)
┌────────────────────────────▼─────────────────────────────────┐
│                  Laravel 12 (PHP 8.2+)                       │
│  Routes │ Controllers │ Middleware │ Events │ Jobs │ Policies │
└──────┬──────────────────────────────────────────────┬────────┘
       │                                              │
┌──────▼──────────────┐                  ┌───────────▼────────┐
│   Core Application  │                  │  packages/workdo/  │
│   app/              │                  │  (58+ Modules)     │
│   ├── Models        │                  │  Each module has:  │
│   ├── Http          │◄─────────────────│  ├── src/          │
│   ├── Events        │   ServiceProvider│  │   ├── Http/      │
│   ├── Services      │   registration   │  │   ├── Models/    │
│   └── Helpers       │                  │  │   ├── Routes/    │
└──────┬──────────────┘                  │  │   ├── Resources/ │
       │                                 │  │   └── Database/  │
┌──────▼──────────────┐                  │  └── module.json   │
│   Database Layer    │                  └────────────────────┘
│   MySQL / SQLite    │
│   Migrations        │
│   Seeders           │
└─────────────────────┘
```

### Key Architectural Decisions

| Concern | Solution |
|---|---|
| Frontend–Backend communication | **Inertia.js** (server-driven SPA, no REST API) |
| UI component primitives | **Radix UI** (accessible, unstyled) |
| Styling | **TailwindCSS 3** + `tailwindcss-animate` |
| Rich text editing | **TipTap** editor |
| Charts & analytics | **Recharts** |
| Drag & drop | **@hello-pangea/dnd** |
| Calendar views | **FullCalendar** |
| PDF generation | **jsPDF** + **html2pdf.js** |
| Excel export | **PHPSpreadsheet** |
| File storage | Local disk or **AWS S3** (Flysystem) |
| Permissions | **Spatie Laravel Permission** |
| Media | **Spatie Media Library** |
| Real-time | **Pusher** + **Laravel Echo** |
| Authentication | Laravel Breeze + **Sanctum** + **Socialite** |
| 2FA | **Google2FA** |
| Build tool | **Vite 5** with manual chunk splitting |
| Asset bundling | Per-module `app.tsx` entry points auto-discovered via glob |

---

## Module System

Every feature in GadaaCloud is a **self-contained Laravel package** located in `packages/workdo/<ModuleName>/`. Each module declares itself via a `module.json` manifest and registers its own routes, controllers, models, migrations, seeders, and React components.

### Module Manifest (`module.json`)

```json
{
  "name": "Hrm",
  "alias": "HRM",
  "description": "Human Resource Management module",
  "priority": 30,
  "version": 5.0,
  "monthly_price": 0,
  "yearly_price": 0,
  "package_name": "hrm"
}
```

Modules with `priority` values are auto-installed in order during `php artisan app:install`. Modules with `monthly_price > 0` are premium add-ons.

---

### Core Business Modules

| Module | Description |
|---|---|
| **Account** | Chart of accounts, journal entries, tax management, financial reports |
| **DoubleEntry** | Full double-entry bookkeeping ledger |
| **BudgetPlanner** | Department & project budget planning and tracking |
| **Goal** | Company/team OKR-style goal tracking linked to accounting |
| **ProductService** | Product catalog, services, pricing, tax classes |
| **InventoryManagement** | Stock adjustments, reorder rules, safety stock alerts, serial tracking, audit logs |
| **Pos** | Point-of-sale terminal with real-time inventory sync |
| **Quotation** | Sales quotations with line items, discounts, and PDF export |
| **Contract** | Contract creation, e-signatures, and lifecycle management |
| **SalesProposal** | Proposal builder with approval workflows |
| **Lead** | CRM lead tracking, pipeline management |
| **ImportExport** | Bulk data import/export via Excel/CSV for all modules |

---

### HR & Workforce Modules

| Module | Description |
|---|---|
| **Hrm** | Employee profiles, departments, designations, attendance |
| **Recruitment** | Job postings, applicant tracking, interview scheduling |
| **Training** | Training programs, sessions, and employee participation |
| **Performance** | Performance reviews, KPIs, appraisal cycles |
| **Timesheet** | Time tracking per project/task with approval workflow |
| **Benefit** | Employee benefits and allowance management |

---

### Productivity & Collaboration

| Module | Description |
|---|---|
| **Taskly** | Kanban task management with drag-and-drop boards |
| **Calendar** | Team calendar with Google Calendar sync |
| **SupportTicket** | Internal helpdesk ticketing system |
| **FormBuilder** | Drag-and-drop form builder per module |
| **GadaacloudStudio** | Visual workflow builder for multi-step document approvals |
| **AIAssistant** | AI-powered assistant for summaries, drafts, and insights |
| **ZoomMeeting** | Schedule and manage Zoom meetings from within the platform |

---

### Payment Gateways

GadaaCloud ships with **20+ payment gateway integrations** as individual modules:

| Gateway | Module |
|---|---|
| Stripe | `Stripe` |
| PayPal | `Paypal` |
| Razorpay | `Razorpay` |
| Flutterwave | `Flutterwave` |
| Paystack | `Paystack` |
| Mollie | `Mollie` |
| Authorize.Net | `AuthorizeNet` |
| Cashfree | `Cashfree` |
| CinetPay | `CinetPay` |
| Coingate | `Coingate` |
| Easebuzz | `Easebuzz` |
| Fedapay | `Fedapay` |
| Iyzipay | `Iyzipay` |
| Khalti | `Khalti` |
| Mercado Pago | `Mercado` |
| Midtrans | `Midtrans` |
| Ozow | `Ozow` |
| PaiementPro | `PaiementPro` |
| PayHere | `PayHere` |
| PayTR | `PayTR` |
| PayTabs | `PayTab` |
| Payfast | `Payfast` |
| Tap | `Tap` |
| Toyyibpay | `Toyyibpay` |
| Xendit | `Xendit` |
| YooKassa | `YooKassa` |
| Aamarpay | `Aamarpay` |

---

### Integrations & Notifications

| Module | Description |
|---|---|
| **Slack** | Send notifications and alerts to Slack channels |
| **Telegram** | Telegram bot notifications |
| **Twilio** | SMS notifications via Twilio |
| **Webhook** | Outbound webhook dispatching for external integrations |
| **GoogleCaptcha** | reCAPTCHA v2/v3 for form protection |

---

### Premium Add-ons

| Module | Monthly | Yearly | Description |
|---|---|---|---|
| **GadaaCloud Studio** | $15 | $150 | Custom approval workflow builder |
| **Inventory Management** | $15 | $150 | Advanced inventory with audit trail |
| **Ethiopian Calendar** | $5 | $50 | Ge'ez calendar for East African businesses |

---

## Tech Stack

### Backend

| Technology | Version | Purpose |
|---|---|---|
| PHP | ^8.2 | Runtime |
| Laravel | ^12.0 | MVC Framework |
| Inertia.js (Laravel adapter) | ^2.0 | Server-driven SPA |
| Laravel Sanctum | ^4.2 | API token auth |
| Laravel Socialite | ^5.23 | OAuth SSO |
| Spatie Permission | ^6.21 | RBAC |
| Spatie Media Library | ^11.14 | File/media management |
| PHPSpreadsheet | ^5.1 | Excel import/export |
| Google2FA | ^2.3 | Two-factor auth |
| Pusher PHP Server | ^7.2 | WebSocket broadcasting |
| Twilio SDK | ^8.8 | SMS |
| Razorpay | ^2.9 | Payment gateway |
| Stripe PHP | ^17.6 | Payment gateway |
| Google API Client | ^2.18 | Google Calendar |
| Microsoft Graph | ^2.49 | Microsoft 365 integration |
| Mailchimp SDK | ^3.0 | Email marketing |
| Webklex IMAP | ^6.2 | IMAP email reading |
| Salla ZATCA | ^3.0 | Saudi e-invoicing compliance |
| Bacon QR Code | ^3.0 | QR code generation |
| Guzzle | ^7.10 | HTTP client |

### Frontend

| Technology | Version | Purpose |
|---|---|---|
| React | ^18.2 | UI framework |
| TypeScript | ^5.0 | Type safety |
| Inertia.js React | ^1.0 | SPA page routing |
| TailwindCSS | ^3.2 | Utility-first CSS |
| Radix UI | ^1–2.x | Accessible UI primitives |
| Lucide React | ^0.378 | Icon system |
| TipTap | ^3.3 | Rich text editor |
| Recharts | ^3.2 | Charts & analytics |
| FullCalendar | ^6.1 | Calendar views |
| @hello-pangea/dnd | ^18.0 | Drag-and-drop |
| Syncfusion Diagrams | ^31.2 | Workflow diagram canvas |
| jsPDF + html2pdf.js | ^3.0 / ^0.12 | PDF generation |
| i18next | ^25.3 | Internationalization |
| Pusher JS | ^8.4 | Real-time WebSocket client |
| Sonner | ^2.0 | Toast notifications |
| date-fns | ^3.6 | Date utilities |
| Kenat | ^4.0 | Ethiopian calendar library |
| jsBarcode | ^3.11 | Barcode generation |
| QRCode | ^1.5 | QR code rendering |

### Build & Dev Tools

| Tool | Purpose |
|---|---|
| Vite 5 | Frontend build tool |
| Laravel Vite Plugin | Hot module replacement |
| Ziggy | Laravel routes in JavaScript |
| Laravel Pint | PHP code style fixer |
| Laravel Debugbar | Development profiling |
| PHPUnit 11 | Backend testing |
| Laravel Sail | Docker dev environment |

---

## Project Structure

```
gadaacloud/
├── app/
│   ├── Classes/           # Module helper class (Module.php)
│   ├── Console/
│   │   └── Commands/      # Artisan commands
│   │       ├── InstallCommand.php       # php artisan app:install
│   │       ├── CreateReactPackage.php   # Scaffold new modules
│   │       ├── MakeCrudlyCommand.php    # CRUD generator
│   │       └── PackageSeed.php          # Per-module seeder runner
│   ├── Events/            # Application-wide domain events
│   ├── Helpers/
│   │   └── Helper.php     # Global helper functions (autoloaded)
│   ├── Http/
│   │   ├── Controllers/   # Core controllers
│   │   └── Middleware/    # Auth, install-check, etc.
│   ├── Models/            # Core Eloquent models (45 models)
│   ├── PathGenerators/    # Spatie Media path generators
│   ├── Providers/         # Service providers
│   ├── Services/          # Business logic services
│   └── Traits/            # Reusable model traits
│
├── packages/
│   └── workdo/            # All ERP modules live here
│       ├── Account/       # Accounting module
│       ├── Hrm/           # HR module
│       ├── InventoryManagement/
│       ├── Pos/
│       ├── Taskly/
│       ├── AIAssistant/
│       ├── GadaacloudStudio/
│       ├── EthiopianCalendar/
│       ├── Stripe/        # Payment gateway modules
│       ├── Paypal/
│       └── ... (58 modules total)
│           ├── module.json        # Module manifest
│           ├── composer.json      # Module autoloading
│           └── src/
│               ├── Http/
│               │   ├── Controllers/
│               │   └── Requests/
│               ├── Models/
│               ├── Routes/
│               │   └── web.php
│               ├── Database/
│               │   ├── migrations/
│               │   └── seeders/
│               └── Resources/
│                   ├── js/
│                   │   ├── app.tsx       # Module React entry point
│                   │   └── Pages/        # Inertia pages
│                   └── views/            # Blade templates (if any)
│
├── resources/
│   ├── css/app.css        # Global styles
│   ├── js/app.tsx         # Main React entry point
│   ├── lang/              # 20+ translation JSON files
│   └── views/
│       └── app.blade.php  # Inertia root template
│
├── routes/
│   ├── web.php            # Main web routes
│   ├── api.php            # API routes
│   ├── auth.php           # Auth routes
│   ├── installer.php      # Setup wizard routes
│   └── updater.php        # Update wizard routes
│
├── database/
│   ├── migrations/        # Core migrations
│   └── seeders/           # Core seeders
│
├── stubs/
│   ├── crudly/            # CRUD generator templates
│   └── react-package-stubs/ # Module scaffolding templates
│
├── public/                # Web root
├── storage/               # Logs, cache, uploads
├── tests/                 # Feature & unit tests
├── vite.config.js         # Vite config (auto-discovers module entry points)
├── tailwind.config.js     # TailwindCSS config
├── composer.json
└── package.json
```

---

## Prerequisites

| Requirement | Version |
|---|---|
| PHP | 8.2 or higher |
| Composer | 2.x |
| Node.js | 18 or higher |
| npm | 9 or higher |
| MySQL | 8.0+ (or SQLite for development) |

> **Windows users**: For long file paths (required by some vendor packages), enable long path support:
> ```powershell
> # Run as Administrator
> git config --system core.longpaths true
> New-ItemProperty -Path "HKLM:\SYSTEM\CurrentControlSet\Control\FileSystem" -Name "LongPathsEnabled" -Value 1 -PropertyType DWORD -Force
> ```

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/alifuofficial/gadaacloud.git
cd gadaacloud
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install Node.js dependencies

```bash
npm install
```

### 4. Set up environment

```bash
cp .env.example .env
php artisan key:generate
```

### 5. Configure your database

Edit `.env` and set your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gadaacloud
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 6. Run the installer

The installer command handles migrations, seeders, and module activation in the correct priority order:

```bash
php artisan app:install
```

This will:
- Run `migrate:fresh` to create all tables
- Seed the database with default data
- Discover and activate all 58+ modules in priority order
- Create the `storage/installed` flag file

> To reinstall from scratch: `php artisan app:install --force`

### 7. Link storage

```bash
php artisan storage:link
```

---

## Configuration

### Environment Variables

| Variable | Description | Example |
|---|---|---|
| `APP_NAME` | Application name | `GadaaCloud` |
| `APP_URL` | Application URL | `http://localhost` |
| `DB_CONNECTION` | Database driver | `mysql` / `sqlite` |
| `BROADCAST_CONNECTION` | WebSocket driver | `pusher` / `log` |
| `PUSHER_APP_ID` | Pusher app ID | — |
| `PUSHER_APP_KEY` | Pusher key | — |
| `PUSHER_APP_SECRET` | Pusher secret | — |
| `MAIL_MAILER` | Mail driver | `smtp` / `log` |
| `AWS_ACCESS_KEY_ID` | S3 key (optional) | — |
| `AWS_BUCKET` | S3 bucket (optional) | — |
| `GOOGLE_CLIENT_ID` | Google OAuth | — |
| `GOOGLE_CLIENT_SECRET` | Google OAuth secret | — |
| `TWILIO_SID` | Twilio account SID | — |
| `TWILIO_TOKEN` | Twilio auth token | — |
| `STRIPE_KEY` | Stripe publishable key | — |
| `STRIPE_SECRET` | Stripe secret key | — |

### File Storage

By default, the app uses local storage. To switch to AWS S3:

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
```

---

## Running the Application

### Development

Run both the Laravel dev server and Vite in separate terminals:

```bash
# Terminal 1 – PHP backend
php artisan serve

# Terminal 2 – Vite frontend (HMR)
npm run dev
```

Then visit: [http://localhost:8000](http://localhost:8000)

### Production Build

```bash
npm run build
php artisan optimize
```

### Queue Worker (required for jobs/events)

```bash
php artisan queue:work
```

### Using XAMPP

If using XAMPP, place the project in `htdocs/gadaacloud` and set `APP_URL=http://localhost/gadaacloud/public` in `.env`. Set the vhost to point to the `public/` directory for clean URLs.

---

## Module Development

### Scaffold a new module

```bash
php artisan make:react-package MyModule
```

This generates the full module structure under `packages/workdo/MyModule/` using the stubs in `stubs/react-package-stubs/`.

### Module structure

Each module is a self-contained Laravel package:

```
packages/workdo/MyModule/
├── module.json              # Module manifest
├── composer.json            # PSR-4 autoloading
└── src/
    ├── Providers/
    │   └── ServiceProvider.php    # Registers routes, views, migrations
    ├── Http/
    │   ├── Controllers/
    │   └── Requests/
    ├── Models/
    ├── Routes/
    │   └── web.php
    ├── Database/
    │   ├── migrations/
    │   └── seeders/
    └── Resources/
        └── js/
            ├── app.tsx            # Auto-discovered by Vite
            └── Pages/
                ├── Index.tsx
                ├── Create.tsx
                └── Edit.tsx
```

### CRUD generation

```bash
php artisan make:crudly MyModule MyModel
```

This generates controllers, requests, migrations, and React pages from the `stubs/crudly/` templates.

### Enable/disable a module

Modules are managed via the `add_ons` database table. You can toggle them programmatically or through the admin UI.

---

## Multi-language Support

GadaaCloud includes translation files for 20+ languages in `resources/lang/`:

`ar`, `da`, `de`, `el`, `es`, `fa`, `fi`, `fr`, `he`, `hi`, `it`, `ja`, `nl`, `pl`, `pt`, `pt-BR`, `ru`, `tr`, `zh`

The frontend uses **i18next** with `i18next-browser-languagedetector` for automatic locale detection.

---

## Contributing

1. Fork the repository
2. Create your feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add amazing feature'`
4. Push to the branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

Please follow the existing code style. Run `./vendor/bin/pint` before committing PHP code.

---

## License

This project is licensed under the **MIT License** — see the [LICENSE](LICENSE) file for details.

---

<p align="center">
  Built with ❤️ by the <strong>GadaaCloud</strong> team
</p>
