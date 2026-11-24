<p align="center">
  <img src="storage/app/public/media/logo_dark.png" alt="GadaaCloud Logo" width="280"/>
</p>

<h1 align="center">GadaaCloud ERP</h1>

<p align="center">
  <strong>Next-Generation Cloud ERP & E-Invoicing Platform for High-Growth African Enterprises</strong>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 12"/>
  <img src="https://img.shields.io/badge/React-18.x-61DAFB?style=for-the-badge&logo=react&logoColor=black" alt="React 18"/>
  <img src="https://img.shields.io/badge/TypeScript-5.x-3178C6?style=for-the-badge&logo=typescript&logoColor=white" alt="TypeScript"/>
  <img src="https://img.shields.io/badge/Inertia.js-2.x-9553E9?style=for-the-badge&logo=inertia&logoColor=white" alt="Inertia.js 2"/>
  <img src="https://img.shields.io/badge/TailwindCSS-3.x-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white" alt="TailwindCSS"/>
  <img src="https://img.shields.io/badge/Vite-5.x-646CFF?style=for-the-badge&logo=vite&logoColor=white" alt="Vite 5"/>
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2+"/>
  <img src="https://img.shields.io/badge/MoR_EIMS-Directive_1142-059669?style=for-the-badge&logo=shield&logoColor=white" alt="MoR EIMS Compliant"/>
</p>

<p align="center">
  <a href="#overview">Overview</a> •
  <a href="#system-architecture">Architecture</a> •
  <a href="#flagship-capabilities">Flagship Capabilities</a> •
  <a href="#active-modules-directory">Module Directory</a> •
  <a href="#installation--setup">Quick Start</a> •
  <a href="#module-development">SDK & CLI</a> •
  <a href="#security--compliance">Security & Compliance</a>
</p>

---

## Overview

**GadaaCloud** is a modular, multi-tenant Enterprise Resource Planning (ERP) suite engineered specifically for fast-growing enterprises, conglomerates, manufacturing plants, civil engineering contractors, and trading firms in East Africa and emerging markets.

By fusing **Laravel 12** on the backend with **React 18, TypeScript, and Inertia.js 2.0** on the frontend, GadaaCloud eliminates traditional API serialization bottlenecks. Every UI interaction delivers single-page application (SPA) responsiveness backed by the robustness and security of enterprise PHP.

### Core Distinctions

* 🇪🇹 **National E-Invoicing Compliance (EIMS)**: Built-in support for Ethiopian Ministry of Revenues (MoR) Directive No. 1142/2026, including cryptographic fiscal hashing, QR code generation, and audit verification.
* 📅 **Dual Calendar Engine (Ge'ez & Gregorian)**: Native bidirectional date conversion supporting the Ethiopian Calendar alongside Gregorian dates across all financial and HR workflows.
* 🤖 **Autonomous AI Copilot**: Persistent-memory conversational AI assistant capable of multi-step forecasting, executive summarization, and business process automation.
* 🏗️ **Heavy-Industry Project Portfolio Management (Taskly PPM)**: Earned Value Management (EVM), Critical Path Gantt dependencies, Construction Bill of Quantities (BoQ) with Daily Site Diaries, Maritime Demurrage Countdown Clocks, and CapEx/OpEx internal governance.
* ⚡ **Zero-Overhead Modular Architecture**: 34 high-performance enterprise modules running on an auto-discovering package framework. Zero foreign gateway bloat.

---

## System Architecture

GadaaCloud operates on an event-driven, modular package architecture (`packages/workdo/*`). Each business domain is encapsulated with dedicated controllers, Eloquent models, migrations, and React components.

```text
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                                CLIENT TIER (Browser SPA)                                │
│   React 18  │  TypeScript 5  │  TailwindCSS  │  Radix UI Primitives  │  Recharts & TipTap │
└────────────────────────────────────────────┬────────────────────────────────────────────┘
                                             │  Inertia.js Protocol (Zero REST Overhead)
┌────────────────────────────────────────────▼────────────────────────────────────────────┐
│                        APPLICATION SERVER (Laravel 12 / PHP 8.2+)                       │
│                                                                                         │
│   ┌────────────────────────────────┐            ┌──────────────────────────────────┐   │
│   │   Kernel & Security Gateway    │            │   GadaaCloud Copilot (AI Engine) │   │
│   │   • Dynamic Route Dispatcher   │            │   • Persistent Multi-Turn Memory │   │
│   │   • Sanctum Token Auth & 2FA   │───────────►│   • Operational Intelligence     │   │
│   │   • Spatie Multi-Tenant RBAC   │            │   • Predictive Financial Insights│   │
│   └───────────────┬────────────────┘            └──────────────────────────────────┘   │
│                   │                                                                     │
│                   ▼                                                                     │
│   ┌────────────────────────────────────────────────────────────────────────────────┐   │
│   │                   WorkDo Modular Engine (packages/workdo/*)                    │   │
│   │                                                                                │   │
│   │   🇪🇹 Regulatory & Local      💼 Projects & Portfolios      📈 Financial Suite   │   │
│   │   • EIMS (MoR Directive 1142)  • Taskly PPM (EVM Banner)   • Account Ledgers   │   │
│   │   • Ethiopian Ge'ez Calendar   • Critical Path Gantt (FS)  • Double-Entry      │   │
│   │                                • BoQ & Site Diaries        • Budget Planner    │   │
│   │   📦 Supply Chain & Trade      • Port Demurrage Clocks     • CBE/Telebirr Bank │   │
│   │   • Inventory & Adjustments    • CapEx / OpEx In-House       Reconciliation    │   │
│   │   • Import/Export LC Pipeline  • Public Tracking Portal                        │   │
│   │                                                                                │   │
│   │   👥 People & Workforce        🤝 CRM & Engagements        ⚙️ Platform Studio   │   │
│   │   • HRM, Payroll & Leave       • Lead Pipeline & CRM       • GadaaCloud Studio │   │
│   │   • Recruitment & Training     • Support Ticket System     • Document Chatter  │   │
│   │   • Performance (KPI) Appraisals• Dynamic Form Builder     • Webhooks & Comms  │   │
│   └───────────────────────────────────────┬────────────────────────────────────────┘   │
└───────────────────────────────────────────┼─────────────────────────────────────────────┘
                                            │
               ┌────────────────────────────┼────────────────────────────┐
               ▼                            ▼                            ▼
┌──────────────────────────────┐┌──────────────────────┐┌────────────────────────────────┐
│       Database Layer         ││   Real-time Engine   ││         Storage Tier           │
│   MySQL 8.0+ / MariaDB 10.4+ ││   Pusher / WebSockets││   AWS S3 / Local Media Library │
│   Strict Multi-Tenant Scoping││   Laravel Echo Broadcast│   Spatie Media / Flysystem   │
└──────────────────────────────┘└──────────────────────┘└────────────────────────────────┘
```

---

## Flagship Capabilities

### 1. Ethiopian E-Invoicing (`EIMS` — Directive No. 1142/2026)
Built from the ground up for the Ethiopian Ministry of Revenues electronic invoicing mandate:
* **Cryptographic Verification**: Generates compliant invoice hash chains and QR codes containing mandatory fiscal metadata (TIN, Invoice Number, Taxable Amount, VAT, and Date).
* **Dual Invoice Rendering**: Compliant printable PDFs containing bilingual English and Amharic tax disclosures.
* **Fiscal Audit Trail**: Immutable ledger of sent, acknowledged, and validated e-invoices with dispute/credit note tracking.

### 2. Modernized Project Portfolio Management (`Taskly PPM`)
Upgraded from basic task management into an enterprise Project Portfolio Management (PPM) engine:
* **Earned Value Management (EVM)**: Live metrics banner tracking **Planned Value (PV)**, **Earned Value (EV)**, **Actual Cost (AC)**, **Cost Variance (CV)**, **Schedule Variance (SV)**, **Cost Performance Index (CPI)**, and **Schedule Performance Index (SPI)**.
* **Gantt Predecessor Dependencies**: Finish-to-Start (FS) sequential task relationships with automatic cascade calculations and bottleneck warnings.
* **Construction & Contracting Workspace**: Itemized **Bill of Quantities (BoQ)** with variance tracking, subcontractor progress, and **Daily Site Supervisor Diaries** (weather conditions, labor headcount, equipment logs, and site safety observations).
* **Maritime Import/Export Logistics & Demurrage**: Live container dwell tracking against port free-day baselines with real-time demurrage penalty clocks and Letter of Credit (LC) milestones.
* **In-House / Corporate Initiative Governance**: Seamless toggle between external client delivery and internal corporate projects with **CapEx**, **OpEx**, **R&D**, and **Strategic PMO** classification, Executive Sponsor oversight, and target ROI savings tracking.
* **Client Tracking Magic Links**: Cryptographically secure 32-character tokens (`/p/track/{token}`) for external client self-service, complete with milestone sign-off workflows and financial privacy shields.
* **System Setup Project Preferences**: Centralized configuration to toggle industry workspaces, free day baselines, timesheet approval policies, and EVM risk warning thresholds.

### 3. Core Financial & Double-Entry Accounting (`Account` & `DoubleEntry`)
* Complete chart of accounts, multi-currency journal entries, and automated financial statements (Balance Sheet, Profit & Loss, Trial Balance).
* **Local Bank Reconciliation**: Dedicated statement parsers for **Commercial Bank of Ethiopia (CBE)**, **Telebirr**, and generic bank CSVs.
* Budget Planner and OKR-style financial goal tracking.

### 4. Supply Chain, Inventory & Global Trade (`InventoryManagement` & `ImportExport`)
* Multi-warehouse stock tracking, batch numbers, serial numbers, reorder level alerts, and inventory adjustments.
* Specialized Letter of Credit (LC) tracking, marine insurance verification, and customs clearance pipeline.

### 5. Human Resource Management (`Hrm`, `Recruitment`, `Training`)
* Comprehensive employee lifecycle management, attendance tracking, leave balances, performance appraisals, and automated payroll runs.

### 6. Autonomous Copilot (`GadaaCloudCopilot`)
* Embedded AI agent designed with long-term conversational memory, financial predictive analysis, automated drafting, and operational guidance across the ERP.

---

## Active Modules Directory

Following our codebase optimization, GadaaCloud runs on **34 purpose-built, high-performance modules**:

| Domain | Active Modules | Description |
|---|---|---|
| **Ethiopian Localization** | `EIMS`, `EthiopianCalendar` | MoR Directive 1142 e-invoicing and Ge'ez dual calendar conversion |
| **Finance & Accounting** | `Account`, `DoubleEntry`, `BudgetPlanner`, `Goal` | Full financial suite, double-entry ledgers, and budget controls |
| **Project & Operations** | `Taskly`, `ImportExport`, `InventoryManagement`, `Pos`, `ProductService`, `Quotation`, `Contract` | EVM PPM, international logistics, warehouse control, POS terminal |
| **HR & Workforce** | `Hrm`, `Recruitment`, `Training`, `Timesheet`, `Performance` | Employee directory, talent acquisition, attendance, and appraisals |
| **CRM & Client Services** | `Lead`, `SupportTicket`, `LandingPage`, `FormBuilder` | Lead pipeline, helpdesk tickets, CMS, dynamic form engine |
| **AI, Automation & Tools** | `GadaaCloudCopilot`, `GadaacloudStudio`, `ERPMigrate`, `Chatter`, `AIAssistant`, `Calendar`, `GoogleCaptcha`, `Slack`, `Telegram`, `Twilio`, `Webhook`, `ZoomMeeting` | AI Copilot, BPMN approval studio, data migration, notifications |

---

## Local Ethiopian Payment Rails (Integration Roadmap)

GadaaCloud has intentionally decoupled obsolete international and foreign regional payment gateways to focus directly on Ethiopian local payment rails:

```
┌─────────────────────────────────────────────────────────────┐
│                 GadaaCloud Payment Architecture             │
├──────────────────────────────┬──────────────────────────────┤
│  Rail                        │  Protocol / Channel          │
├──────────────────────────────┼──────────────────────────────┤
│  📱 Telebirr SuperApp        │  Direct H5 / Merchant API    │
│  🏦 CBE Birr (Commercial Bank)│ Mobile Banking Push / QR     │
│  ⚡ Chapa Payment Gateway    │  Web Checkout & Webhooks     │
│  💳 SantimPay / ArifPay      │  Local Switch & Cards (EthSwitch)│
│  🏛️ Direct Bank Transfer     │  Automated Reference Auditing│
└──────────────────────────────┴──────────────────────────────┘
```

---

## Tech Stack

```
Backend:       PHP 8.2+ │ Laravel 12.x │ Inertia.js (Laravel Adapter)
Frontend:      React 18.x │ TypeScript 5.x │ TailwindCSS 3.x │ Radix UI
Asset Build:   Vite 5.x │ Laravel Vite Plugin │ Dynamic Package Glob
Database:      MySQL 8.0+ / MariaDB 10.4+ │ SQLite (Dev)
State / Auth:  Inertia SPA State │ Laravel Sanctum │ Spatie Permissions
Broadcasting:  Pusher PHP │ Laravel Echo │ WebSockets
Document/PDF:  jsPDF │ html2pdf.js │ PHPSpreadsheet
Analytics:     Recharts │ FullCalendar │ Kenat Ge'ez Library
```

---

## Installation & Setup

### Prerequisites

* **PHP**: `>= 8.2` (with `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`)
* **Node.js**: `>= 18.x` and **npm**: `>= 9.x`
* **Composer**: `>= 2.x`
* **MySQL / MariaDB**: `>= 8.0`

> **Note for Windows Developers**: Enable long path support to prevent vendor path collisions:
> ```powershell
> git config --system core.longpaths true
> New-ItemProperty -Path "HKLM:\SYSTEM\CurrentControlSet\Control\FileSystem" -Name "LongPathsEnabled" -Value 1 -PropertyType DWORD -Force
> ```

### Quick Start

1. **Clone the repository**:
   ```bash
   git clone https://github.com/alifuofficial/gadaacloud.git
   cd gadaacloud
   ```

2. **Install PHP and Node dependencies**:
   ```bash
   composer install
   npm install
   ```

3. **Configure environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Setup Database credentials in `.env`**:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=gadaacloud
   DB_USERNAME=root
   DB_PASSWORD=your_password
   ```

5. **Execute Automated Enterprise Installer**:
   ```bash
   php artisan app:install
   ```
   *This command executes database migrations, populates default seeds, registers permissions, and activates core packages in their exact dependency order.*

6. **Create symbolic storage link**:
   ```bash
   php artisan storage:link
   ```

7. **Compile Assets & Launch**:
   ```bash
   # Terminal 1: Backend Server
   php artisan serve

   # Terminal 2: Frontend Dev Server (HMR)
   npm run dev
   ```

---

## Module Development SDK

GadaaCloud provides built-in Artisan CLI generators to rapidly scaffold enterprise-ready modules following the WorkDo package architecture:

### Scaffold a New Package
```bash
php artisan make:react-package LogisticsHub
```
*Creates a fully typed package skeleton in `packages/workdo/LogisticsHub/` complete with PSR-4 autoloading, ServiceProvider, routes, controllers, and Inertia React pages.*

### Generate CRUD Resources
```bash
php artisan make:crudly LogisticsHub Consignment
```
*Generates full controller, form requests, Eloquent model, database migrations, and React DataTable / Modal interfaces based on verified platform stubs.*

---

## Security & Compliance

* **Strict Role-Based Access Control (RBAC)**: Powered by Spatie Laravel Permission with granular tenant-level authorization and role hierarchy (`superadmin`, `company`, `employee`, `client`).
* **Multi-Factor Authentication (2FA)**: Time-based One-Time Password (TOTP) authentication via Google Authenticator.
* **Tenant Data Segregation**: Strict `created_by` tenant scoping across all financial and operational queries.
* **Audit Trails & Security Headers**: Full activity logging and CSRF/XSS sanitization via DOMPurify and Laravel middleware.

---

## License

GadaaCloud is licensed under the **[MIT License](LICENSE)**.

<p align="center">
  <sub>Engineered with precision for African Enterprises by the <strong>GadaaCloud Team</strong>.</sub>
</p>
