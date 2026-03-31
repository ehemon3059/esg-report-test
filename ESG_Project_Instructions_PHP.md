# ESG Reporting Platform — Developer Project Instructions

> **Tech Stack:** PHP 8.x · MySQL 8.x · jQuery 3.x · Tailwind CSS (CDN)  
> **Database:** `esg_reporting_db` (from `esg_reporting_db.sql`)  
> **UI Reference:** `index.html`  
> **PDF Generation:** TCPDF (PHP library)

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Folder Structure](#2-folder-structure)
3. [Database Setup](#3-database-setup)
4. [Shared PHP Files (config, helpers, auth)](#4-shared-php-files)
5. [Phase 1 — Register → Login → Create Company](#5-phase-1--register--login--create-company)
6. [Phase 2 — Site Management](#6-phase-2--site-management)
7. [Phase 3A — Emission Factors Library](#7-phase-3a--emission-factors-library)
8. [Phase 3B — Fuel Consumption (Scope 1)](#8-phase-3b--fuel-consumption-scope-1)
9. [Phase 3C — Energy Consumption (Scope 2)](#9-phase-3c--energy-consumption-scope-2)
10. [Phase 3D — Emissions Dashboard](#10-phase-3d--emissions-dashboard)
11. [Phase 4A — ESRS 2 General Disclosures](#11-phase-4a--esrs-2-general-disclosures)
12. [Phase 4B — Environmental Topics (E1–E5)](#12-phase-4b--environmental-topics-e1e5)
13. [Phase 5A — Social Topics (S1–S4)](#13-phase-5a--social-topics-s1s4)
14. [Phase 5B — Governance (G1)](#14-phase-5b--governance-g1)
15. [Phase 6A — EU Taxonomy](#15-phase-6a--eu-taxonomy)
16. [Phase 6B — Assurance & Audit Reports](#16-phase-6b--assurance--audit-reports)
17. [Phase 7 — Generate ESG Report PDF (TCPDF)](#17-phase-7--generate-esg-report-pdf)
18. [Global Rules Every Developer Must Follow](#18-global-rules-every-developer-must-follow)

---

## 1. Project Overview

This is a multi-phase web application where companies log in, enter sustainability data step by step, and finally generate a downloadable ESG (Environmental, Social, Governance) PDF report.

**User journey:**

```
Register → Login → Create Company → Add Sites
  → Phase 3: Emissions Data (Fuel + Energy)
  → Phase 4: Environmental Reporting (ESRS E1–E5)
  → Phase 5: Social & Governance (S1–S4, G1)
  → Phase 6: EU Taxonomy & Assurance
  → Click "Generate Report" → Download ESG PDF
```

---

## 2. Folder Structure

Create the following folder and file layout on your server:

```
/esg-platform/
├── index.php                   ← Redirect to login or dashboard
├── /config/
│   └── db.php                  ← MySQL PDO connection
├── /includes/
│   ├── auth.php                ← Session check helper
│   ├── helpers.php             ← uuid(), sanitize(), etc.
│   └── header.php              ← HTML header + nav (shared)
│   └── footer.php              ← HTML footer (shared)
├── /auth/
│   ├── register.php            ← Registration form + logic
│   ├── login.php               ← Login form + logic
│   └── logout.php              ← Session destroy
├── /company/
│   └── create.php              ← Create company form (first login)
├── /sites/
│   ├── index.php               ← List all sites
│   ├── create.php              ← Add new site form
│   └── edit.php                ← Edit site
├── /phase3/
│   ├── emission-factors.php    ← Emission factors library CRUD
│   ├── fuel.php                ← Fuel consumption form
│   ├── energy.php              ← Energy consumption form
│   └── dashboard.php          ← Emissions summary dashboard
├── /phase4/
│   ├── esrs2.php               ← ESRS 2 General Disclosures form
│   └── environmental.php       ← E1–E5 accordion form
├── /phase5/
│   ├── social.php              ← S1–S4 form
│   └── governance.php          ← G1 form
├── /phase6/
│   ├── taxonomy.php            ← EU Taxonomy form
│   └── assurance.php           ← Assurance & Audit form
├── /api/
│   ├── sites.php               ← AJAX: return sites JSON for dropdowns
│   ├── emission-factors.php    ← AJAX: return factors for calculation
│   ├── calculate.php           ← AJAX: receive activity, return tCO2e
│   └── save-status.php         ← AJAX: update record status
├── /report/
│   └── generate.php            ← TCPDF report generation
└── /uploads/
    └── assurance/              ← PDF uploads storage folder
```

---

## 3. Database Setup

**Step 1 — Create the database**

Open phpMyAdmin or MySQL CLI and run:

```sql
SOURCE /path/to/esg_reporting_db.sql;
```

This creates the `esg_reporting_db` database and all tables.

**Database Tables Summary:**

| Table | Purpose |
|---|---|
| `companies` | Company profile |
| `users` | Login accounts (bcrypt passwords) |
| `sites` | Physical locations / facilities |
| `emission_factors` | CO₂e conversion factor library (seeded) |
| `fuel_activities` | Raw fuel usage entries (Scope 1) |
| `energy_activities` | Raw energy usage entries (Scope 2) |
| `emission_records` | Calculated CO₂e results |
| `environmental_topics` | ESRS E1–E5 report data |
| `social_topics` | ESRS S1–S4 report data |
| `s_governance` | ESRS G1 report data |
| `eu_taxonomy` | EU Taxonomy alignment data |
| `assurance` | Assurance & audit report data |

**Important: `reporting_period` column format**

All reporting tables use `VARCHAR(7)` for `reporting_period` in `YYYY-MM` format.  
Example: `"2025-12"` for December 2025.

The HTML forms use `<input type="month">` which returns `YYYY-MM` automatically — use that value directly.

---

## 4. Shared PHP Files

### `config/db.php` — Database Connection

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'esg_reporting_db');
define('DB_USER', 'root');         // change in production
define('DB_PASS', '');             // change in production

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die(json_encode(['error' => 'Database connection failed']));
}
```

### `includes/helpers.php` — UUID and Sanitize

```php
<?php
// Generate a UUID v4
function uuid(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Sanitize input
function sanitize(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

// Return JSON response and exit (for AJAX endpoints)
function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
```

### `includes/auth.php` — Session Protection

```php
<?php
session_start();

// Call this at the top of every protected page
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: /auth/login.php');
        exit;
    }
}

// Get logged-in user's company_id
function company_id(): string {
    return $_SESSION['company_id'] ?? '';
}

// Get logged-in user's id
function user_id(): string {
    return $_SESSION['user_id'] ?? '';
}

// Get logged-in user's role
function user_role(): string {
    return $_SESSION['role'] ?? 'user';
}

// Check if user is admin
function is_admin(): bool {
    return user_role() === 'admin';
}
```

---

## 5. Phase 1 — Register → Login → Create Company

### What to Build

| File | What it does |
|---|---|
| `auth/register.php` | Shows register form. On POST: creates company + admin user |
| `auth/login.php` | Shows login form. On POST: checks password, sets session |
| `auth/logout.php` | Destroys session, redirects to login |
| `company/create.php` | Only shown if user has no company yet |

---

### `auth/register.php` — Logic

```php
<?php
require_once '../config/db.php';
require_once '../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name        = sanitize($_POST['name']);
    $email       = sanitize($_POST['email']);
    $password    = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $companyName = sanitize($_POST['company_name']);
    $industry    = sanitize($_POST['industry'] ?? '');
    $country     = sanitize($_POST['country'] ?? '');

    $companyId = uuid();
    $userId    = uuid();

    // 1. Insert company first
    $stmt = $pdo->prepare("
        INSERT INTO companies (id, name, industry, country_of_registration, created_at, updated_at)
        VALUES (:id, :name, :industry, :country, NOW(), NOW())
    ");
    $stmt->execute([':id' => $companyId, ':name' => $companyName,
                    ':industry' => $industry, ':country' => $country]);

    // 2. Insert admin user linked to company
    $stmt = $pdo->prepare("
        INSERT INTO users (id, company_id, name, email, password, role, created_at, updated_at)
        VALUES (:id, :company_id, :name, :email, :password, 'admin', NOW(), NOW())
    ");
    $stmt->execute([':id' => $userId, ':company_id' => $companyId,
                    ':name' => $name, ':email' => $email, ':password' => $password]);

    header('Location: /auth/login.php?registered=1');
    exit;
}
```

**Register Form Fields:**
- Full Name, Email, Password, Company Name, Industry, Country

---

### `auth/login.php` — Logic

```php
<?php
session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("
        SELECT * FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1
    ");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Set session variables
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['role']       = $user['role'];
        $_SESSION['name']       = $user['name'];

        header('Location: /phase3/dashboard.php');
        exit;
    }

    $error = 'Invalid email or password.';
}
```

> **Default login from seed data:**  
> Email: `admin@example.com` | Password: `admin123`  
> Change this password immediately in production.

---

## 6. Phase 2 — Site Management

> **Prerequisite:** User is logged in and has a company.

### Tables Used: `sites`

### `sites/create.php` — Save a Site

```php
<?php
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';
require_once '../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("
        INSERT INTO sites (id, company_id, name, address, country, created_by, created_at, updated_at)
        VALUES (:id, :company_id, :name, :address, :country, :created_by, NOW(), NOW())
    ");
    $stmt->execute([
        ':id'         => uuid(),
        ':company_id' => company_id(),
        ':name'       => sanitize($_POST['name']),
        ':address'    => sanitize($_POST['address'] ?? ''),
        ':country'    => sanitize($_POST['country'] ?? ''),
        ':created_by' => user_id(),
    ]);
    header('Location: /sites/index.php');
    exit;
}
```

### `sites/index.php` — List Sites

```php
$stmt = $pdo->prepare("
    SELECT * FROM sites
    WHERE company_id = :company_id AND deleted_at IS NULL
    ORDER BY created_at DESC
");
$stmt->execute([':company_id' => company_id()]);
$sites = $stmt->fetchAll();
```

### Soft Delete a Site

```php
// sites/delete.php
$stmt = $pdo->prepare("
    UPDATE sites SET deleted_at = NOW(), deleted_by = :deleted_by
    WHERE id = :id AND company_id = :company_id
");
$stmt->execute([
    ':id'         => $_POST['site_id'],
    ':company_id' => company_id(),
    ':deleted_by' => user_id(),
]);
```

> **Rule:** NEVER use `DELETE FROM sites`. Always soft-delete so historical emission records remain intact.

### `api/sites.php` — AJAX Endpoint for Dropdowns

This endpoint is called by jQuery to populate `<select>` dropdowns dynamically.

```php
<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$stmt = $pdo->prepare("
    SELECT id, name FROM sites
    WHERE company_id = :company_id AND deleted_at IS NULL
    ORDER BY name ASC
");
$stmt->execute([':company_id' => company_id()]);
json_response($stmt->fetchAll());
```

**jQuery call (in any form page):**

```javascript
$.getJSON('/api/sites.php', function(sites) {
    var $select = $('#site_id');
    $select.empty().append('<option value="">Select Site</option>');
    $.each(sites, function(i, site) {
        $select.append('<option value="' + site.id + '">' + site.name + '</option>');
    });
});
```

---

## 7. Phase 3A — Emission Factors Library

> **Build this first** before fuel/energy forms. The calculation engine depends on it.  
> The database is already **seeded** with DEFRA 2024 factors from the SQL file.

### Table Used: `emission_factors`

### Key Seeded Data (from SQL)

| ID | Activity Type | Scope | Region | Factor | Unit |
|---|---|---|---|---|---|
| ef-0001 | diesel | Scope 1 | GLOBAL | 2.68720 | litre |
| ef-0002 | petrol | Scope 1 | GLOBAL | 2.31380 | litre |
| ef-0003 | natural_gas | Scope 1 | GLOBAL | 2.04220 | kWh |
| ef-0004 | lpg | Scope 1 | GLOBAL | 1.55540 | litre |
| ef-0010 | electricity | Scope 2 Location-Based | UK | 0.20765 | kWh |
| ef-0011 | electricity | Scope 2 Location-Based | MY | 0.58500 | kWh |
| ef-0012 | electricity | Scope 2 Location-Based | US | 0.38600 | kWh |
| ef-0013 | electricity | Scope 2 Location-Based | EU | 0.27600 | kWh |
| ef-0014 | electricity | Scope 2 Location-Based | GLOBAL | 0.49000 | kWh |

### `phase3/emission-factors.php` — List + Add

**List active factors:**

```php
$stmt = $pdo->query("
    SELECT * FROM emission_factors WHERE is_active = 1 ORDER BY scope, activity_type
");
$factors = $stmt->fetchAll();
```

**Add new factor:**

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("
        INSERT INTO emission_factors
        (id, activity_type, scope, region, factor, unit, source, version, is_active, valid_from, created_at, updated_at)
        VALUES (:id, :activity_type, :scope, :region, :factor, :unit, :source, :version, 1, :valid_from, NOW(), NOW())
    ");
    $stmt->execute([
        ':id'            => uuid(),
        ':activity_type' => sanitize($_POST['activity_type']),
        ':scope'         => $_POST['scope'],
        ':region'        => sanitize($_POST['region']),
        ':factor'        => (float)$_POST['factor'],
        ':unit'          => sanitize($_POST['unit']),
        ':source'        => sanitize($_POST['source'] ?? ''),
        ':version'       => sanitize($_POST['version'] ?? '2024'),
        ':valid_from'    => $_POST['valid_from'] ?? date('Y-m-d'),
    ]);
}
```

---

## 8. Phase 3B — Fuel Consumption (Scope 1)

### Tables Used: `fuel_activities`, `emission_records`, `emission_factors`

### Form Fields (from `index.html`)

| HTML Field Name | Type | Values |
|---|---|---|
| `site_id` | select | Dynamic from API |
| `date` | date | — |
| `fuel_type` | select | `natural_gas`, `diesel`, `petrol`, `lpg` |
| `volume` | number | — |
| `unit` | select | `litre`, `m3`, `kg`, `tonne` |

> **Note:** `fuel_type` values must match `activity_type` in `emission_factors` table (e.g., use `natural_gas` not `Natural Gas`).

### `phase3/fuel.php` — Save + Calculate

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $siteId   = $_POST['site_id'];
    $date     = $_POST['date'];
    $fuelType = sanitize($_POST['fuel_type']);
    $volume   = (float)$_POST['volume'];
    $unit     = sanitize($_POST['unit']);

    // 1. Save raw fuel activity
    $fuelActivityId = uuid();
    $stmt = $pdo->prepare("
        INSERT INTO fuel_activities (id, site_id, date, fuel_type, volume, unit, created_at, updated_at)
        VALUES (:id, :site_id, :date, :fuel_type, :volume, :unit, NOW(), NOW())
    ");
    $stmt->execute([':id' => $fuelActivityId, ':site_id' => $siteId,
                    ':date' => $date, ':fuel_type' => $fuelType,
                    ':volume' => $volume, ':unit' => $unit]);

    // 2. Find matching emission factor
    $stmt = $pdo->prepare("
        SELECT * FROM emission_factors
        WHERE activity_type = :activity_type
          AND scope = 'Scope 1'
          AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([':activity_type' => $fuelType]);
    $factor = $stmt->fetch();

    if ($factor) {
        // 3. Calculate: tCO2e = (volume × factor) ÷ 1000
        $tco2e = ($volume * $factor['factor']) / 1000;

        // 4. Save emission record
        $stmt = $pdo->prepare("
            INSERT INTO emission_records
            (id, company_id, scope, tco2e_calculated, fuel_activity_id, emission_factor_id, date_calculated, created_at)
            VALUES (:id, :company_id, 'Scope 1', :tco2e, :fuel_activity_id, :emission_factor_id, NOW(), NOW())
        ");
        $stmt->execute([
            ':id'                 => uuid(),
            ':company_id'         => company_id(),
            ':tco2e'              => $tco2e,
            ':fuel_activity_id'   => $fuelActivityId,
            ':emission_factor_id' => $factor['id'],
        ]);

        $success = "Calculated: " . round($tco2e, 4) . " tCO₂e";
    }
}
```

**CO₂e Calculation Formula:**

```
tCO₂e = (Volume × EmissionFactor.factor) ÷ 1000
```

Example: 2,500 m³ of Natural Gas × 2.04220 ÷ 1000 = **5.1055 tCO₂e**

---

## 9. Phase 3C — Energy Consumption (Scope 2)

### Tables Used: `energy_activities`, `emission_records`, `emission_factors`

### Form Fields (from `index.html`)

| HTML Field Name | Type | Values |
|---|---|---|
| `site_id` | select | Dynamic from API |
| `date` | month | YYYY-MM format |
| `energy_type` | select | `electricity`, `district_heating`, `steam` |
| `consumption` | number | — |
| `unit` | select | `kWh`, `MWh`, `GJ` |

### `phase3/energy.php` — Save + Calculate

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $siteId      = $_POST['site_id'];
    $date        = $_POST['date'] . '-01'; // month input gives YYYY-MM, add day for DATE column
    $energyType  = sanitize($_POST['energy_type']);
    $consumption = (float)$_POST['consumption'];
    $unit        = sanitize($_POST['unit']);

    // 1. Normalize to kWh
    $consumptionKwh = $consumption;
    if ($unit === 'MWh') $consumptionKwh = $consumption * 1000;
    if ($unit === 'GJ')  $consumptionKwh = $consumption * 277.78;

    // 2. Save raw energy activity
    $energyActivityId = uuid();
    $stmt = $pdo->prepare("
        INSERT INTO energy_activities (id, site_id, date, energy_type, consumption, unit, created_at, updated_at)
        VALUES (:id, :site_id, :date, :energy_type, :consumption, :unit, NOW(), NOW())
    ");
    $stmt->execute([':id' => $energyActivityId, ':site_id' => $siteId,
                    ':date' => $date, ':energy_type' => $energyType,
                    ':consumption' => $consumption, ':unit' => $unit]);

    // 3. Get company country for region matching
    $stmt = $pdo->prepare("SELECT country_of_registration FROM companies WHERE id = :id");
    $stmt->execute([':id' => company_id()]);
    $company = $stmt->fetch();
    $region = strtoupper($company['country_of_registration'] ?? 'GLOBAL');

    // 4. Find matching emission factor (try region first, fallback to GLOBAL)
    $stmt = $pdo->prepare("
        SELECT * FROM emission_factors
        WHERE activity_type = 'electricity'
          AND scope = 'Scope 2 Location-Based'
          AND is_active = 1
          AND (region = :region OR region = 'GLOBAL')
        ORDER BY CASE WHEN region = :region THEN 0 ELSE 1 END
        LIMIT 1
    ");
    $stmt->execute([':region' => $region]);
    $factor = $stmt->fetch();

    if ($factor) {
        // 5. Calculate tCO2e
        $tco2e = ($consumptionKwh * $factor['factor']) / 1000;

        // 6. Save emission record
        $stmt = $pdo->prepare("
            INSERT INTO emission_records
            (id, company_id, scope, tco2e_calculated, energy_activity_id, emission_factor_id, date_calculated, created_at)
            VALUES (:id, :company_id, 'Scope 2 Location-Based', :tco2e, :energy_activity_id, :emission_factor_id, NOW(), NOW())
        ");
        $stmt->execute([
            ':id'                  => uuid(),
            ':company_id'          => company_id(),
            ':tco2e'               => $tco2e,
            ':energy_activity_id'  => $energyActivityId,
            ':emission_factor_id'  => $factor['id'],
        ]);
    }
}
```

---

## 10. Phase 3D — Emissions Dashboard

### `phase3/dashboard.php` — Aggregate Query

```php
// Get Scope 1, Scope 2, and Total for the company
$stmt = $pdo->prepare("
    SELECT
        scope,
        SUM(tco2e_calculated) AS total_tco2e,
        COUNT(*) AS record_count
    FROM emission_records
    WHERE company_id = :company_id
    GROUP BY scope
");
$stmt->execute([':company_id' => company_id()]);
$summary = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [scope => total]
```

**Recent calculations table query:**

```php
$stmt = $pdo->prepare("
    SELECT
        er.id,
        er.scope,
        er.tco2e_calculated,
        er.date_calculated,
        COALESCE(fa.fuel_type, ea.energy_type) AS activity_name,
        COALESCE(fa.volume, ea.consumption)    AS input_value,
        COALESCE(fa.unit, ea.unit)             AS input_unit,
        ef.factor,
        ef.unit                                AS factor_unit,
        ef.source,
        s.name                                 AS site_name
    FROM emission_records er
    LEFT JOIN fuel_activities   fa ON er.fuel_activity_id   = fa.id
    LEFT JOIN energy_activities ea ON er.energy_activity_id = ea.id
    LEFT JOIN emission_factors  ef ON er.emission_factor_id = ef.id
    LEFT JOIN sites              s ON COALESCE(fa.site_id, ea.site_id) = s.id
    WHERE er.company_id = :company_id
    ORDER BY er.date_calculated DESC
    LIMIT 20
");
$stmt->execute([':company_id' => company_id()]);
$records = $stmt->fetchAll();
```

**Period filter with jQuery (AJAX):**

```javascript
$('#period-filter').on('change', function() {
    $.post('/api/emissions-summary.php', { period: $(this).val() }, function(data) {
        $('#scope1-total').text(data.scope1 + ' tCO₂e');
        $('#scope2-total').text(data.scope2 + ' tCO₂e');
        $('#total-carbon').text(data.total + ' tCO₂e');
    }, 'json');
});
```

---

## 11. Phase 4A — ESRS 2 General Disclosures

### Table Used: `esrs2_general_disclosures`

> **Note:** This table is NOT in the provided SQL file. Create it with this SQL:

```sql
CREATE TABLE IF NOT EXISTS `esrs2_general_disclosures` (
    `id`                             VARCHAR(36)  NOT NULL,
    `company_id`                     VARCHAR(36)  NOT NULL,
    `reporting_period`               VARCHAR(7)   NOT NULL COMMENT 'YYYY-MM',
    `consolidation_scope`            TEXT         DEFAULT NULL,
    `value_chain_boundaries`         TEXT         DEFAULT NULL,
    `board_role_in_sustainability`   TEXT         DEFAULT NULL,
    `esg_integration_in_remuneration` INT         DEFAULT NULL,
    `assessment_process`             TEXT         DEFAULT NULL,
    `created_by`                     VARCHAR(36)  NOT NULL,
    `updated_by`                     VARCHAR(36)  DEFAULT NULL,
    `created_at`                     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_esrs2_company_period` (`company_id`, `reporting_period`),
    CONSTRAINT `fk_esrs2_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Form Fields (from `index.html`)

| Field Name | HTML Element | DB Column |
|---|---|---|
| `reportingPeriod` | `input[type=month]` | `reporting_period` |
| `esgIntegrationInRemuneration` | `input[type=number]` 0-100 | `esg_integration_in_remuneration` |
| `consolidationScope` | `textarea` | `consolidation_scope` |
| `valueChainBoundaries` | `textarea` | `value_chain_boundaries` |
| `boardRoleInSustainability` | `textarea` | `board_role_in_sustainability` |
| `assessmentProcess` | `textarea` | `assessment_process` |

### `phase4/esrs2.php` — Save (INSERT or UPDATE)

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $period = sanitize($_POST['reportingPeriod']); // "2025-12"

    // Check if record already exists for this company + period
    $stmt = $pdo->prepare("
        SELECT id FROM esrs2_general_disclosures
        WHERE company_id = :company_id AND reporting_period = :period
    ");
    $stmt->execute([':company_id' => company_id(), ':period' => $period]);
    $existing = $stmt->fetch();

    if ($existing) {
        // UPDATE
        $stmt = $pdo->prepare("
            UPDATE esrs2_general_disclosures
            SET consolidation_scope = :cs,
                value_chain_boundaries = :vcb,
                board_role_in_sustainability = :brs,
                esg_integration_in_remuneration = :esg,
                assessment_process = :ap,
                updated_by = :updated_by,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':cs' => sanitize($_POST['consolidationScope']),
            ':vcb' => sanitize($_POST['valueChainBoundaries']),
            ':brs' => sanitize($_POST['boardRoleInSustainability']),
            ':esg' => (int)$_POST['esgIntegrationInRemuneration'],
            ':ap'  => sanitize($_POST['assessmentProcess']),
            ':updated_by' => user_id(),
            ':id' => $existing['id'],
        ]);
    } else {
        // INSERT
        $stmt = $pdo->prepare("
            INSERT INTO esrs2_general_disclosures
            (id, company_id, reporting_period, consolidation_scope, value_chain_boundaries,
             board_role_in_sustainability, esg_integration_in_remuneration, assessment_process,
             created_by, created_at, updated_at)
            VALUES
            (:id, :company_id, :period, :cs, :vcb, :brs, :esg, :ap, :created_by, NOW(), NOW())
        ");
        $stmt->execute([
            ':id'         => uuid(),
            ':company_id' => company_id(),
            ':period'     => $period,
            ':cs'         => sanitize($_POST['consolidationScope']),
            ':vcb'        => sanitize($_POST['valueChainBoundaries']),
            ':brs'        => sanitize($_POST['boardRoleInSustainability']),
            ':esg'        => (int)$_POST['esgIntegrationInRemuneration'],
            ':ap'         => sanitize($_POST['assessmentProcess']),
            ':created_by' => user_id(),
        ]);
    }

    header('Location: /phase4/esrs2.php?saved=1');
    exit;
}
```

---

## 12. Phase 4B — Environmental Topics (E1–E5)

### Table Used: `environmental_topics`

### `phase4/environmental.php` — Form Structure

The UI in `index.html` uses a **CSS accordion** (checkbox toggle). Each ESRS topic (E1 through E5) is a collapsible section.

**Form fields by topic:**

**E1 — Climate Change**

| Field | Type | DB Column |
|---|---|---|
| `e1_material` | checkbox (0 or 1) | `e1_material` |
| `e1_climatePolicy` | textarea | `e1_climate_policy` |
| `e1_reductionTarget` | text | `e1_reduction_target` |

**E2 — Pollution**

| Field | Type | DB Column |
|---|---|---|
| `e2_material` | checkbox | `e2_material` |
| `e2_nox_t_per_year` | number (decimal) | `e2_nox_t_per_year` |
| `e2_sox_t_per_year` | number (decimal) | `e2_sox_t_per_year` |

**E3 — Water & Marine**

| Field | Type | DB Column |
|---|---|---|
| `e3_material` | checkbox | `e3_material` |
| `e3_water_withdrawal_m3` | number | `e3_water_withdrawal_m3` |
| `e3_water_recycling_rate_pct` | number 0-100 | `e3_water_recycling_rate_pct` |

**E4 — Biodiversity**

| Field | Type | DB Column |
|---|---|---|
| `e4_material` | checkbox | `e4_material` |
| `e4_protected_areas_impact` | textarea | `e4_protected_areas_impact` |

**E5 — Circular Economy**

| Field | Type | DB Column |
|---|---|---|
| `e5_material` | checkbox | `e5_material` |
| `e5_recycling_rate_pct` | number 0-100 | `e5_recycling_rate_pct` |
| `e5_recycled_input_materials_pct` | number 0-100 | `e5_recycled_input_materials_pct` |

### Save Logic (INSERT or UPDATE)

Same pattern as ESRS 2 — check for existing record by `company_id + reporting_period`, then INSERT or UPDATE.

```php
// Checkbox handling — HTML checkboxes return nothing if unchecked
$e1_material = isset($_POST['e1_material']) ? 1 : 0;
$e2_material = isset($_POST['e2_material']) ? 1 : 0;
// ... repeat for all material checkboxes
```

### Display Linked Emissions in E1 Section

The UI shows Scope 1, Scope 2, and Total tCO₂e inside the E1 accordion:

```php
$stmt = $pdo->prepare("
    SELECT scope, SUM(tco2e_calculated) AS total
    FROM emission_records
    WHERE company_id = :company_id
    GROUP BY scope
");
$stmt->execute([':company_id' => company_id()]);
$emissionTotals = [];
foreach ($stmt->fetchAll() as $row) {
    $emissionTotals[$row['scope']] = $row['total'];
}
```

Then in HTML:
```html
<p>Scope 1: <?= round($emissionTotals['Scope 1'] ?? 0, 2) ?> tCO₂e</p>
<p>Scope 2: <?= round($emissionTotals['Scope 2 Location-Based'] ?? 0, 2) ?> tCO₂e</p>
```

---

## 13. Phase 5A — Social Topics (S1–S4)

### Table Used: `social_topics`

Same build pattern as `environmental_topics`.

### Form Fields by Topic

**S1 — Own Workforce**

| Field | DB Column |
|---|---|
| `s1_material` (checkbox) | `s1_material` |
| `s1_employee_count_by_contract` (textarea) | `s1_employee_count_by_contract` |
| `s1_health_and_safety` (textarea) | `s1_health_and_safety` |
| `s1_training_hours_per_employee` (number) | `s1_training_hours_per_employee` |

**S2 — Workers in Value Chain**

| Field | DB Column |
|---|---|
| `s2_material` | `s2_material` |
| `s2_pct_suppliers_audited` (0-100) | `s2_pct_suppliers_audited` |
| `s2_remediation_actions` (textarea) | `s2_remediation_actions` |

**S3 — Affected Communities**

| Field | DB Column |
|---|---|
| `s3_material` | `s3_material` |
| `s3_community_engagement` (textarea) | `s3_community_engagement` |
| `s3_complaints_and_outcomes` (textarea) | `s3_complaints_and_outcomes` |

**S4 — Consumers & End Users**

| Field | DB Column |
|---|---|
| `s4_material` | `s4_material` |
| `s4_product_safety_incidents` (number) | `s4_product_safety_incidents` |
| `s4_consumer_remediation` (textarea) | `s4_consumer_remediation` |

### Status Workflow

The `status` column in `social_topics` uses: `DRAFT → UNDER_REVIEW → APPROVED → PUBLISHED → REJECTED`

Only `admin` role can move to APPROVED or PUBLISHED. Add this check:

```php
// api/save-status.php
if (!is_admin()) {
    json_response(['error' => 'Unauthorized'], 403);
}
```

**jQuery AJAX to change status:**

```javascript
$('#btn-submit-review').on('click', function() {
    $.post('/api/save-status.php', {
        table:  'social_topics',
        id:     recordId,
        status: 'UNDER_REVIEW'
    }, function(res) {
        if (res.success) alert('Submitted for review.');
    }, 'json');
});
```

---

## 14. Phase 5B — Governance (G1)

### Table Used: `s_governance`

### Form Fields

| Field | Type | DB Column |
|---|---|---|
| `reporting_period` | month | `reporting_period` |
| `g1_board_composition_independence` | textarea | `g1_board_composition_independence` |
| `g1_gender_diversity_pct` | number 0-100 | `g1_gender_diversity_pct` |
| `g1_esg_oversight` | textarea | `g1_esg_oversight` |
| `g1_whistleblower_cases` | textarea | `g1_whistleblower_cases` |
| `g1_anti_corruption_policies` | text | `g1_anti_corruption_policies` |
| `g1_related_party_controls` | text | `g1_related_party_controls` |

Same INSERT/UPDATE pattern as other reporting tables.

---

## 15. Phase 6A — EU Taxonomy

### Table Used: `eu_taxonomy`

### Form Fields

| Field | Type | DB Column |
|---|---|---|
| `reporting_period` | month | `reporting_period` |
| `economic_activities` | textarea | `economic_activities` |
| `technical_screening_criteria` | textarea | `technical_screening_criteria` |
| `taxonomy_eligible_revenue_pct` | number 0-100 | `taxonomy_eligible_revenue_pct` |
| `taxonomy_aligned_revenue_pct` | number 0-100 | `taxonomy_aligned_revenue_pct` |
| `taxonomy_eligible_capex_pct` | number 0-100 | `taxonomy_eligible_capex_pct` |
| `taxonomy_aligned_capex_pct` | number 0-100 | `taxonomy_aligned_capex_pct` |
| `taxonomy_aligned_opex_pct` | number 0-100 | `taxonomy_aligned_opex_pct` |
| `dnsh_status` | select | `dnsh_status` |
| `social_safeguards_status` | select | `social_safeguards_status` |

**`dnsh_status` options:**
- `ALL_OBJECTIVES_PASSED`
- `SOME_OBJECTIVES_NOT_MET`
- `ASSESSMENT_IN_PROGRESS`

**`social_safeguards_status` options:**
- `FULL_COMPLIANCE`
- `NON_COMPLIANCE`
- `PARTIAL_REMEDIATION`

---

## 16. Phase 6B — Assurance & Audit Reports

### Table Used: `assurance`

### Form Fields (from `index.html`)

| Field | Type | DB Column |
|---|---|---|
| `reporting_period` | month | `reporting_period` |
| `provider` | text | `provider` |
| `level` | select: `limited` / `reasonable` | `level` |
| `standard` | text (e.g., ISAE 3000) | `standard` |
| `scope_description` | textarea | `scope_description` |
| `conclusion` | textarea | `conclusion` |
| `report_date` | date | `report_date` |

### Dynamic Checklist Progress Bar

The `index.html` has 4 checkboxes. Their completion progress bar must be calculated dynamically with jQuery — the hardcoded `25%` in the prototype is wrong.

```javascript
// Calculate checklist progress
function updateChecklistProgress() {
    var total   = $('.checklist-item').length;       // = 4
    var checked = $('.checklist-item:checked').length;
    var pct     = Math.round((checked / total) * 100);

    $('#checklist-progress-bar').css('width', pct + '%');
    $('#checklist-progress-label').text(pct + '%');
}

$('.checklist-item').on('change', updateChecklistProgress);
```

> **Note:** The `assurance` table does NOT have the individual checklist boolean columns from the Prisma schema. If you need to store individual checklist items, either add 4 `TINYINT` columns or store them as a JSON string in `scope_description`. Add the columns like this:

```sql
ALTER TABLE assurance
    ADD COLUMN checklist_data_collection_documented   TINYINT(1) DEFAULT 0,
    ADD COLUMN checklist_internal_controls_tested     TINYINT(1) DEFAULT 0,
    ADD COLUMN checklist_source_documentation_trail   TINYINT(1) DEFAULT 0,
    ADD COLUMN checklist_calculation_method_validated TINYINT(1) DEFAULT 0;
```

---

## 17. Phase 7 — Generate ESG Report PDF

> **Prerequisite:** All phases have at least DRAFT data for the selected reporting period.

### Install TCPDF

```bash
composer require tecnickcom/tcpdf
```

Or download manually from https://tcpdf.org and include it:

```php
require_once '/vendor/tecnickcom/tcpdf/tcpdf.php';
```

### `report/generate.php` — Full Flow

```php
<?php
session_start();
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';
require_once '../includes/helpers.php';
require_once '../vendor/autoload.php';  // TCPDF via Composer

$period = sanitize($_POST['period'] ?? $_GET['period']);
$cid    = company_id();

// ── 1. Fetch all data ──────────────────────────────────────────────────────

// Company info
$stmt = $pdo->prepare("SELECT * FROM companies WHERE id = :id");
$stmt->execute([':id' => $cid]);
$company = $stmt->fetch();

// Emission totals by scope
$stmt = $pdo->prepare("
    SELECT scope, ROUND(SUM(tco2e_calculated), 4) AS total
    FROM emission_records WHERE company_id = :id
    GROUP BY scope
");
$stmt->execute([':id' => $cid]);
$emissions = [];
foreach ($stmt->fetchAll() as $row) {
    $emissions[$row['scope']] = $row['total'];
}

// Environmental Topics
$stmt = $pdo->prepare("SELECT * FROM environmental_topics WHERE company_id = :id AND reporting_period = :p LIMIT 1");
$stmt->execute([':id' => $cid, ':p' => $period]);
$env = $stmt->fetch();

// Social Topics
$stmt = $pdo->prepare("SELECT * FROM social_topics WHERE company_id = :id AND reporting_period = :p LIMIT 1");
$stmt->execute([':id' => $cid, ':p' => $period]);
$social = $stmt->fetch();

// Governance
$stmt = $pdo->prepare("SELECT * FROM s_governance WHERE company_id = :id AND reporting_period = :p LIMIT 1");
$stmt->execute([':id' => $cid, ':p' => $period]);
$gov = $stmt->fetch();

// EU Taxonomy
$stmt = $pdo->prepare("SELECT * FROM eu_taxonomy WHERE company_id = :id AND reporting_period = :p LIMIT 1");
$stmt->execute([':id' => $cid, ':p' => $period]);
$tax = $stmt->fetch();

// Assurance
$stmt = $pdo->prepare("SELECT * FROM assurance WHERE company_id = :id AND reporting_period = :p LIMIT 1");
$stmt->execute([':id' => $cid, ':p' => $period]);
$assurance = $stmt->fetch();

// ── 2. Build PDF ───────────────────────────────────────────────────────────

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('ESG Platform');
$pdf->SetAuthor($company['name']);
$pdf->SetTitle($company['name'] . ' ESG Report ' . $period);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// Cover Section
$pdf->SetFont('helvetica', 'B', 20);
$pdf->Cell(0, 12, $company['name'], 0, 1, 'C');
$pdf->SetFont('helvetica', '', 13);
$pdf->Cell(0, 8, 'ESG Report — Reporting Period: ' . $period, 0, 1, 'C');
$pdf->Ln(10);

// Emissions Summary
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Emissions Summary', 0, 1);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 8, 'Scope 1 (Direct): ' . ($emissions['Scope 1'] ?? 0) . ' tCO₂e', 0, 1);
$pdf->Cell(0, 8, 'Scope 2 (Energy): ' . ($emissions['Scope 2 Location-Based'] ?? 0) . ' tCO₂e', 0, 1);
$total = ($emissions['Scope 1'] ?? 0) + ($emissions['Scope 2 Location-Based'] ?? 0);
$pdf->Cell(0, 8, 'Total Carbon Footprint: ' . $total . ' tCO₂e', 0, 1);
$pdf->Ln(5);

// Environmental Section
if ($env) {
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Environmental Topics (ESRS E1–E5)', 0, 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->MultiCell(0, 8, 'Climate Policy: ' . ($env['e1_climate_policy'] ?? 'N/A'));
    $pdf->MultiCell(0, 8, 'GHG Reduction Target: ' . ($env['e1_reduction_target'] ?? 'N/A'));
    $pdf->Cell(0, 8, 'Water Withdrawal: ' . ($env['e3_water_withdrawal_m3'] ?? 'N/A') . ' m³', 0, 1);
    $pdf->Cell(0, 8, 'Recycling Rate: ' . ($env['e5_recycling_rate_pct'] ?? 'N/A') . '%', 0, 1);
}

// Social Section
if ($social) {
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Social Topics (ESRS S1–S4)', 0, 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 8, 'Training Hours/Employee: ' . ($social['s1_training_hours_per_employee'] ?? 'N/A'), 0, 1);
    $pdf->Cell(0, 8, 'Suppliers Audited: ' . ($social['s2_pct_suppliers_audited'] ?? 'N/A') . '%', 0, 1);
    $pdf->Cell(0, 8, 'Product Safety Incidents: ' . ($social['s4_product_safety_incidents'] ?? 'N/A'), 0, 1);
}

// Governance Section
if ($gov) {
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Governance (ESRS G1)', 0, 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 8, 'Board Gender Diversity: ' . ($gov['g1_gender_diversity_pct'] ?? 'N/A') . '%', 0, 1);
    $pdf->MultiCell(0, 8, 'ESG Oversight: ' . ($gov['g1_esg_oversight'] ?? 'N/A'));
}

// EU Taxonomy Section
if ($tax) {
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'EU Taxonomy Alignment', 0, 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 8, 'Eligible Revenue: ' . ($tax['taxonomy_eligible_revenue_pct'] ?? 'N/A') . '%', 0, 1);
    $pdf->Cell(0, 8, 'Aligned Revenue: '  . ($tax['taxonomy_aligned_revenue_pct']  ?? 'N/A') . '%', 0, 1);
    $pdf->Cell(0, 8, 'DNSH Status: ' . ($tax['dnsh_status'] ?? 'N/A'), 0, 1);
}

// Assurance Section
if ($assurance) {
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Assurance & Audit', 0, 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 8, 'Provider: ' . ($assurance['provider'] ?? 'N/A'), 0, 1);
    $pdf->Cell(0, 8, 'Level: ' . ($assurance['level'] ?? 'N/A'), 0, 1);
    $pdf->MultiCell(0, 8, 'Conclusion: ' . ($assurance['conclusion'] ?? 'N/A'));
}

// ── 3. Output PDF ──────────────────────────────────────────────────────────
$filename = 'ESG_Report_' . $company['name'] . '_' . $period . '.pdf';
$filename = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $filename);
$pdf->Output($filename, 'D'); // 'D' = force download in browser
exit;
```

### "Generate Report" Button in HTML

```html
<form method="POST" action="/report/generate.php">
    <input type="hidden" name="period" value="2025-12">
    <button type="submit"
        class="px-8 py-3 bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-bold rounded-xl">
        Generate ESG Report PDF
    </button>
</form>
```

---

## 18. Global Rules Every Developer Must Follow

### 1. Always Use Prepared Statements

Never concatenate user input into SQL. Always use PDO prepared statements:

```php
// ❌ WRONG — SQL injection risk
$pdo->query("SELECT * FROM users WHERE email = '$email'");

// ✅ CORRECT
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
$stmt->execute([':email' => $email]);
```

### 2. Always Filter Soft-Deleted Records

Every SELECT on tables with `deleted_at` must filter it out:

```php
// ✅ Always add this
WHERE deleted_at IS NULL
```

### 3. Always Save `created_by` and `updated_by`

Every INSERT must include `created_by = user_id()`.  
Every UPDATE must include `updated_by = user_id()`.

### 4. Use INSERT or UPDATE Pattern (Never Duplicate)

Every reporting table has `UNIQUE KEY (company_id, reporting_period)`.  
Always check if a record exists first, then INSERT or UPDATE. Do NOT blindly INSERT.

### 5. Session Must Be Started on Every Page

```php
// At the very top of every PHP file
session_start();
require_once '../includes/auth.php';
require_login();
```

### 6. Reporting Period Format is Always `YYYY-MM`

The `<input type="month">` returns `YYYY-MM` automatically. Store this value directly in the `reporting_period` VARCHAR(7) column. Never convert it to a full date for these columns.

### 7. Status Transitions — Only Admins Can Approve

```
DRAFT → UNDER_REVIEW  (any logged-in user)
UNDER_REVIEW → APPROVED   (admin only)
APPROVED → PUBLISHED       (admin only)
UNDER_REVIEW → REJECTED    (admin only)
REJECTED → DRAFT           (any user, to re-submit)
```

### 8. Role Check Example

```php
if (user_role() !== 'admin') {
    http_response_code(403);
    exit('Access denied.');
}
```

### 9. UUID for All Primary Keys

All tables use `VARCHAR(36)` UUID primary keys. Use the `uuid()` helper from `helpers.php` for every INSERT.

### 10. Fix CSS Duplicates in index.html Before Using

The `index.html` prototype has duplicate CSS rules (`.accordion-content`, `.accordion-item`, `@keyframes shimmer`, scrollbar styles). When you copy styles from `index.html` into your shared CSS file, keep each rule only once.

---

*Stack: PHP 8 · MySQL 8 · jQuery 3 · Tailwind CSS · TCPDF*  
*Database: `esg_reporting_db.sql` · UI Reference: `index.html`*
