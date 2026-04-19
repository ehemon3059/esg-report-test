# ESG Reporting Platform — Project Overview

## What this project is

A **multi-phase web application** that helps companies collect sustainability data step by step and generate a downloadable **ESG (Environmental, Social, Governance) PDF report** at the end. The platform is designed around European reporting frameworks — **ESRS**, **EU Taxonomy**, and the **GHG Protocol** — so companies can produce disclosures that align with CSRD-style requirements.

In plain words: a company signs up, fills in information about their operations (fuel, energy, water, workforce, governance, etc.), and the system calculates their carbon emissions and assembles everything into a professional PDF report.

---

## Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.x (procedural, PDO) |
| Database | MySQL 8.x (`esg_reporting_db`) |
| Frontend | jQuery 3.x + Tailwind CSS (via CDN) |
| PDF engine | TCPDF (via Composer — `tecnickcom/tcpdf`) |
| Server | XAMPP (local development) |

No framework (Laravel/Symfony) is used — the codebase is deliberately simple PHP scripts organised by phase.

---

## User journey

```
Register → Login → Create Company → Add Sites
  → Phase 3: Emissions Data (Fuel + Energy + Scope 3)
  → Phase 4: Environmental Reporting (ESRS 2 + E1–E5)
  → Phase 5: Social & Governance (S1–S4 + G1)
  → Phase 6: EU Taxonomy + Assurance & Audit
  → Generate Report → Download ESG PDF
```

Each phase has its own form(s) and saves data scoped to the logged-in user's company and a `YYYY-MM` reporting period.

---

## Folder structure

```
esg-report-test/
├── index.php                    Landing page (redirects logged-in users to dashboard)
├── index.html                   Static marketing/landing preview
├── composer.json                Pulls in TCPDF
├── config/
│   └── db.php                   PDO connection to esg_reporting_db
├── includes/
│   ├── auth.php                 Session / login-required helper
│   ├── helpers.php              uuid(), sanitize(), etc.
│   ├── header.php               Shared nav + HTML head
│   └── footer.php               Shared footer
├── auth/
│   ├── register.php             Creates a user + company in one step
│   ├── login.php                Session login
│   └── logout.php               Session destroy
├── company/
│   └── create.php               Edit company profile
├── sites/
│   ├── index.php / create.php / edit.php / delete.php
│                                Manage physical facilities/locations
├── phase3/                      GHG emissions
│   ├── emission-factors.php     CRUD for DEFRA/EPA/IEA conversion factors
│   ├── fuel.php                 Scope 1 fuel consumption entries
│   ├── energy.php               Scope 2 energy consumption entries
│   ├── scope3.php               Scope 3 value-chain emissions
│   └── dashboard.php            Scope 1/2/3 summary + recent calculations
├── phase4/                      Environmental disclosures
│   ├── esrs2.php                ESRS 2 general disclosures (governance, materiality, value chain)
│   └── environmental.php        ESRS E1–E5 accordion form
├── phase5/                      Social + governance
│   ├── social.php               ESRS S1–S4 (workforce, value chain, communities, consumers)
│   └── governance.php           ESRS G1 (board composition, ethics, anti-corruption)
├── phase6/                      Final disclosures
│   ├── taxonomy.php             EU Taxonomy revenue / CapEx / OpEx KPIs + DNSH
│   └── assurance.php            Third-party assurance (provider, standard, conclusion)
├── api/                         AJAX endpoints
│   ├── sites.php                Sites dropdown JSON
│   ├── calculate.php            Activity → tCO2e calculator
│   ├── emissions-summary.php    Dashboard totals
│   └── save-status.php          Record status updates
├── report/
│   └── generate.php             TCPDF report builder (the big one — 732 lines)
├── database/
│   ├── esg_reporting_db.sql     Full schema + seed data
│   └── new-data.sql             Additional seed
└── uploads/
    └── assurance/               Uploaded assurance PDFs
```

---

## Database design

The schema uses **UUIDs** (VARCHAR(36)) as primary keys everywhere, with soft-delete timestamps on the main entities. Foreign keys cascade from `companies` so a deleted company wipes its data.

### Core tables

| Table | Purpose |
|---|---|
| `companies` | Company profile (name, industry, country, registration, website) |
| `users` | User accounts — bcrypt passwords, role enum (admin / user / viewer) |
| `sites` | Physical facilities belonging to a company |
| `emission_factors` | Reference library of CO₂e conversion factors (DEFRA 2024, EPA, EEA, IEA seeded) |

### Activity tables (raw input)

| Table | Purpose |
|---|---|
| `fuel_activities` | Scope 1 — diesel, petrol, natural gas, LPG |
| `energy_activities` | Scope 2 — electricity, heat consumption |
| `emission_records` | Calculated tCO₂e results, linking activities to factors used |

### Reporting tables (one row per `company_id` + `reporting_period`)

| Table | ESRS coverage |
|---|---|
| `esrs2_general_disclosures` | ESRS 2 — governance, materiality, value chain |
| `environmental_topics` | ESRS E1–E5 — climate, pollution, water, biodiversity, circular economy |
| `social_topics` | ESRS S1–S4 — workforce, value chain workers, communities, end-users |
| `s_governance` | ESRS G1 — board, ethics, whistleblowing, anti-corruption |
| `eu_taxonomy` | Eligible/Aligned Revenue, CapEx, OpEx + DNSH + social safeguards |
| `assurance` | Third-party assurance engagement details |

`reporting_period` is always a `VARCHAR(7)` in `YYYY-MM` format, matching what `<input type="month">` produces.

---

## How the emission calculation works

1. User picks a **site** and enters activity data (e.g. "5,800 litres of diesel on 2026-01-08").
2. System looks up the matching **emission factor** in the library (e.g. diesel = 2.68720 kg CO₂e / litre, DEFRA 2024).
3. Multiplies activity × factor → converts kg to tonnes → stores the result in `emission_records` with a link to both the activity row and the factor row used.
4. Dashboard aggregates by **Scope** (1, 2 Location-Based, 2 Market-Based, 3) and displays recent calculations alongside source attribution.

This separation means any future change to emission factors doesn't overwrite historical calculations — each record carries a permanent pointer to the exact factor version used.

---

## The PDF report (`report/generate.php`)

This is the centrepiece. It pulls the full picture for a chosen reporting period — company profile, site list, scope-by-scope emission totals, all ESRS disclosures, EU Taxonomy KPIs, and the assurance statement — and assembles them into a single branded PDF via **TCPDF**. The file is 732 lines and includes helper functions for label/value rows, enum-to-label conversion, and multi-page layout. TCPDF is loaded from either the local `vendor/` folder or a fallback XAMPP path.

---

## What's been built so far

- [x] Registration + login + logout (bcrypt, session-based)
- [x] Company profile creation and editing
- [x] Site management (CRUD)
- [x] Emission factor library (CRUD + seeded with DEFRA/EPA/EEA/IEA factors)
- [x] Scope 1 fuel consumption entry + auto-calculation
- [x] Scope 2 energy consumption entry + auto-calculation
- [x] Scope 3 value-chain emissions entry
- [x] Emissions dashboard with scope totals and recent calculations
- [x] ESRS 2 general disclosures form
- [x] ESRS E1–E5 environmental topics form (accordion UI)
- [x] ESRS S1–S4 social topics form
- [x] ESRS G1 governance form
- [x] EU Taxonomy form (Revenue/CapEx/OpEx KPIs + DNSH + safeguards)
- [x] Assurance & audit form with PDF upload
- [x] End-to-end PDF report generation via TCPDF
- [x] Two full test-data walkthroughs committed (`ESG_Project_Instructions_PHP.md`, `2nd-compnay-profile-info.md`) — one generic company and one Construction/Real-Estate company (Bauwerk) in the EU

---

## Test data

Two complete fixture walkthroughs are included so the end-to-end flow can be exercised without inventing data:

1. **`ESG_Project_Instructions_PHP.md`** — the original developer brief plus walkthrough data.
2. **`2nd-compnay-profile-info.md`** — a realistic second company (Bauwerk Real Estate Group, DE) with fuel, energy, E1–E5, S1–S4, G1, EU Taxonomy, and KPMG assurance data ready to paste into the forms.

Running both back-to-back produces two distinct ESG PDFs from the same codebase — a useful way to verify the report generator isn't hardcoded to a single company's shape.

---

## How to run it locally

1. Start XAMPP (Apache + MySQL).
2. Import `database/esg_reporting_db.sql` via phpMyAdmin. This creates the DB, all tables, a default admin (`admin@example.com` / `admin123`), and seeds common emission factors.
3. Run `composer install` at the project root to pull TCPDF into `vendor/`.
4. Open `http://localhost/esg-report-test/` in a browser.
5. Register a new account or sign in as admin, then follow the phase-by-phase flow.

---

## Summary in one paragraph

This is a self-contained PHP/MySQL application that walks a company through every section of a modern European sustainability report — from raw fuel and electricity readings all the way to EU Taxonomy revenue KPIs and third-party assurance statements — and at the end produces a single, downloadable PDF aligned with ESRS and the GHG Protocol. The architecture is deliberately kept framework-free so every step is readable as ordinary PHP: one folder per reporting phase, one table per ESRS topic area, and one big TCPDF script that ties everything together at the end.
