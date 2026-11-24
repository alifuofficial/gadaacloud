# GadaaCloud ERP — Enterprise Architectural Directives & System Guardian

These instructions are permanent, foundational architectural guidelines for all AI agents working on the **GadaaCloud** codebase. Always adhere strictly to these principles when planning, writing, modifying, debugging, or reviewing code.

---

## 1. The ERP Provider Mindset (End-to-End Workflow Awareness)
Think like a Tier-1 enterprise ERP architect (SAP, Oracle, Odoo, NetSuite):
* **No Module Is an Island**: Business processes flow continuously across departments. Always evaluate how a change in one module connects upstream and downstream:
  * **Order-to-Cash**: Lead ➔ Quotation ➔ Contract ➔ Invoicing (MoR EIMS) ➔ Warehouse Stock Deduction ➔ Accounts Receivable ➔ Bank Reconciliation (CBE / Telebirr).
  * **Procure-to-Pay**: Requisition ➔ Purchase Order ➔ Import/Export (LC & Customs) ➔ Demurrage Clock ➔ Warehouse Receipt ➔ Accounts Payable.
  * **Project-to-Profit**: Project Scope (Client vs In-House) ➔ Predecessor Gantt ➔ Timesheets & Loaded Labor Cost ➔ EVM (CPI/SPI) ➔ Milestone Stage-Gate Approval ➔ Client Invoicing.
* When adding or updating a feature, identify every module touched by that workflow and ensure end-to-end data integrity.

---

## 2. Blast-Radius & Impact Assessment (Zero Cross-Module Regressions)
* **Pre-Modification Impact Analysis**: Before modifying any shared table, controller, model, migration, or route, identify all modules that read or write that resource.
* **Non-Breaking Architecture**: Upgrading or refactoring Module A must **never break or degrade Module B**.
* **Decoupled Graceful Degradation**: Core modules must operate independently of optional add-ons. If an optional package is uninstalled or disabled, other modules must continue running without fatal exceptions (use optional chaining `?.`, null guards, and `PlanModuleCheck`).

---

## 3. Modular Architecture Standards (`packages/workdo/*`)
* Follow the WorkDo modular package pattern strictly:
  * Package manifest: `module.json` with unique alias and priority.
  * Autoloading: PSR-4 declared in `composer.json` and registered via `PackageServiceProvider`.
  * Service Provider: Auto-registers routes, views, database migrations, and seeders.
  * Frontend: Independent React entry point `app.tsx` and Inertia pages under `src/Resources/js/Pages/`.
  * Navigation: Declared via `menus/company-menu.ts` with explicit route names and permission checks.

---

## 4. Multi-Tenant Data Isolation & Security Gates
* **Strict Tenant Scoping**: Every database query, record creation, and settings update must enforce multi-tenancy via `created_by = creatorId()` and proper role hierarchy (`superadmin`, `company`, `employee`, `client`).
* **Cross-Tenant Prevention**: Never expose or leak company records, employee data, or financial ledgers across different company workspaces.

---

## 5. Defensive Frontend Typing & Rendering Safety
* **Safe Prop Fallbacks**: Never assume props passed from Inertia are populated or non-null. Always supply default values (e.g. `users = []`, `companyDefaults || {}`).
* **Nullish Coalescing**: Guard all array methods with `(items || []).map(...)` and `(items || []).filter(...)` to prevent fatal white-screen unhandled exceptions.
* **Form & Select Safety**: In Radix UI Select components, never pass an empty string `""` as an item value; use a sentinel value like `"none"` with proper fallback handling.

---

## 6. Localization & Compliance Integrity
* **Ethiopian Ministry of Revenues (MoR) EIMS**: Maintain full compliance with Directive No. 1142/2026 for electronic invoicing, cryptographic QR hashes, and fiscal auditing.
* **Dual Calendar Engine**: Respect both Gregorian and Ethiopian Ge'ez (`Kenat`) calendars across all date pickers, timesheets, payroll cycles, and reporting periods.

---

## 7. Strict Git Deployment Protocol
* **Local First**: All migrations, features, bug fixes, and asset builds (`npm run build`) must be tested and committed locally first.
* **Never Push Without Instruction**: Never push to remote GitHub (`git push`) unless the user explicitly gives the command: `"push"`.
