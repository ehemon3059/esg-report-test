# ESG Reporting Platform — Master AI Build Prompt

> Copy this entire prompt and paste it to any AI coding assistant (ChatGPT, Claude, Gemini, Copilot, etc.).
> The AI will understand the full project context and build each step correctly.

---

## SYSTEM CONTEXT — READ THIS FIRST

You are a senior PHP developer helping build an **ESG (Environmental, Social, Governance) Reporting Platform** from scratch. This is a multi-tenant web application where companies register, enter sustainability data across multiple phases, and generate a downloadable ESG PDF report.

**Technology Stack (do not suggest alternatives):**
- **Backend:** PHP 8.x only — use PDO for all database queries
- **Database:** MySQL 8.x — database name is `esg_reporting_db`
- **Frontend:** jQuery 3.x for all AJAX and DOM interactions
- **CSS:** Tailwind CSS loaded via CDN in every HTML page
- **PDF:** TCPDF PHP library for report generation
- **Font Size:** ALL body text must be **12pt** — in Tailwind use `text-base`, in TCPDF use `SetFont('helvetica', '', 12)`
- **Primary Keys:** ALL tables use UUID (`VARCHAR(36)`) — never use AUTO_INCREMENT

**Non-negotiable rules you must always follow:**
1. Always use PDO prepared statements with named parameters — never concatenate user input into SQL
2. Always filter soft-deleted rows: `WHERE deleted_at IS NULL`
3. Always save `created_by = user_id()` on INSERT and `updated_by = user_id()` on UPDATE
4. Always check for an existing record before INSERT — use INSERT or UPDATE pattern (never blind INSERT) because reporting tables have `UNIQUE KEY (company_id, reporting_period)`
5. Always call `session_start()` at the top of every PHP file via `require_once '../includes/auth.php'`
6. The `reporting_period` column is `VARCHAR(7)` in `YYYY-MM` format — store it exactly as returned by `<input type="month">`
7. Never hard-code company IDs, user IDs, or site names — always read from the session or database

---

## DATABASE SCHEMA SUMMARY

The database file is `esg_reporting_db.sql`. It creates these tables:

```
companies              → Company profile (name, industry, country_of_registration)
users                  → Login accounts (email, bcrypt password, role: admin/user/viewer)
sites                  → Physical facilities per company (soft-deletable)
emission_factors       → CO₂e conversion library (seeded with DEFRA 2024 data)
fuel_activities        → Raw fuel usage per site — Scope 1 input
energy_activities      → Raw energy usage per site — Scope 2 input
emission_records       → Calculated tCO₂e results (output of calculation engine)
environmental_topics   → ESRS E1–E5 environmental data (one row per company per period)
social_topics          → ESRS S1–S4 social data (one row per company per period)
s_governance           → ESRS G1 governance data (one row per company per period)
eu_taxonomy            → EU Taxonomy alignment percentages
assurance              → Assurance/audit report details
esrs2_general_disclosures → ESRS 2 General Disclosures (CREATE THIS TABLE MANUALLY — not in SQL file)
```

**Seeded emission factors already in the database:**

| activity_type | scope                   | region | factor  | unit  |
|---------------|-------------------------|--------|---------|-------|
| diesel        | Scope 1                 | GLOBAL | 2.68720 | litre |
| petrol        | Scope 1                 | GLOBAL | 2.31380 | litre |
| natural_gas   | Scope 1                 | GLOBAL | 2.04220 | kWh   |
| lpg           | Scope 1                 | GLOBAL | 1.55540 | litre |
| electricity   | Scope 2 Location-Based  | UK     | 0.20765 | kWh   |
| electricity   | Scope 2 Location-Based  | MY     | 0.58500 | kWh   |
| electricity   | Scope 2 Location-Based  | US     | 0.38600 | kWh   |
| electricity   | Scope 2 Location-Based  | EU     | 0.27600 | kWh   |
| electricity   | Scope 2 Location-Based  | GLOBAL | 0.49000 | kWh   |

**Calculation formula used throughout:**
```
tCO₂e = (Volume or Consumption × EmissionFactor.factor) ÷ 1000
```
Example: 2,500 m³ natural gas × 2.04220 ÷ 1000 = **5.1055 tCO₂e**

---

## SHARED FILE STRUCTURE

Every feature depends on these three shared files. Build them first.

**`config/db.php`** — PDO connection:
```php
<?php
$pdo = new PDO('mysql:host=localhost;dbname=esg_reporting_db;charset=utf8mb4',
    'root', '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
     PDO::ATTR_EMULATE_PREPARES => false]);
```

**`includes/helpers.php`** — Three utility functions:
```php
<?php
function uuid(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
}
function sanitize(string $v): string {
    return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');
}
function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data); exit;
}
```

**`includes/auth.php`** — Session and role helpers:
```php
<?php
session_start();
function require_login(): void {
    if (empty($_SESSION['user_id'])) { header('Location: /auth/login.php'); exit; }
}
function company_id(): string { return $_SESSION['company_id'] ?? ''; }
function user_id(): string    { return $_SESSION['user_id']    ?? ''; }
function user_role(): string  { return $_SESSION['role']       ?? 'user'; }
function is_admin(): bool     { return user_role() === 'admin'; }
```

---

## THE 12 STEPS — BUILD IN THIS EXACT ORDER

---

### STEP 1 — User Registration

**What to build:** `auth/register.php`

**Purpose:** A new user signs up. The system creates one Company and one admin User linked to it, inside a database transaction so both succeed or both fail.

**Form fields the user fills in:**
- Full Name (text)
- Email Address (email, must be unique)
- Password (min 8 characters, bcrypt hashed before saving)
- Company Name (text)
- Industry (text)
- Country of Registration (text — this country code is used later for emission factor region matching)

**What happens on submit (POST):**
1. Validate all fields are not empty and email is valid format
2. Check email does not already exist in `users` table
3. Open a MySQL transaction with `$pdo->beginTransaction()`
4. INSERT into `companies` table — generate UUID for id
5. INSERT into `users` table — role must be `'admin'`, password must be `password_hash($password, PASSWORD_BCRYPT)`
6. Commit transaction — redirect to `/auth/login.php?registered=1`
7. On any error: `$pdo->rollBack()` and show error message

**Session is NOT started yet on this page** — the user is not logged in.

**After this step works:** The user can register. Test by checking phpMyAdmin that both a company row and a user row were created.

---

### STEP 2 — User Login

**What to build:** `auth/login.php` and `auth/logout.php`

**Purpose:** Authenticates the user, writes session variables used by every other page.

**Form fields:**
- Email (email input)
- Password (password input)

**What happens on submit (POST):**
1. Query `users` table: `WHERE email = :email AND deleted_at IS NULL`
2. Check `password_verify($_POST['password'], $user['password'])`
3. If valid — write these session variables:
   - `$_SESSION['user_id']    = $user['id']`
   - `$_SESSION['company_id'] = $user['company_id']`
   - `$_SESSION['role']       = $user['role']`
   - `$_SESSION['name']       = $user['name']`
4. Redirect to `/phase3/dashboard.php`
5. If invalid — show error: "Invalid email or password"

**Default login from seeded data:**
- Email: `admin@example.com`
- Password: `admin123`

**`auth/logout.php`:** Call `session_start()`, then `session_destroy()`, then redirect to `/auth/login.php`

**After this step works:** Login/logout cycle works. Every other page can call `require_login()` safely.

---

### STEP 3 — Company Profile & Site Management

**What to build:** `company/create.php`, `sites/index.php`, `sites/create.php`, `sites/edit.php`, `sites/delete.php`, `api/sites.php`

**Purpose:** After login, the company admin can view their company profile and manage physical sites (factories, offices, warehouses) that will be used when logging emissions data.

**Company page (`company/create.php`):**
- Show current company details (name, industry, country)
- Allow admin to update them via PUT/POST
- Query: `SELECT * FROM companies WHERE id = :id` using `company_id()` from session

**Sites — what a Site is:**
A Site represents one physical location. Fuel and energy consumption are recorded per site. A company can have many sites.

**Create Site (`sites/create.php`) — form fields:**
- Site Name (required, must be unique per company)
- Address (textarea, optional)
- Country (text, optional)

**Save logic:**
```
INSERT INTO sites (id, company_id, name, address, country, created_by, created_at, updated_at)
VALUES (uuid(), company_id(), name, address, country, user_id(), NOW(), NOW())
```

**List Sites (`sites/index.php`):**
```sql
SELECT * FROM sites
WHERE company_id = :company_id AND deleted_at IS NULL
ORDER BY created_at DESC
```

**Delete Site (`sites/delete.php`):**
```sql
UPDATE sites SET deleted_at = NOW(), deleted_by = :deleted_by
WHERE id = :id AND company_id = :company_id
```
⚠ NEVER use `DELETE FROM sites` — soft delete only.

**AJAX Endpoint (`api/sites.php`):**
Returns a JSON array of sites for this company. Used to populate `<select>` dropdowns on fuel and energy forms.
```php
// Returns: [{"id": "uuid...", "name": "Main Office"}, ...]
```
jQuery call example:
```javascript
$.getJSON('/api/sites.php', function(sites) {
    $.each(sites, function(i, s) {
        $('#site_id').append('<option value="'+s.id+'">'+s.name+'</option>');
    });
});
```

**After this step works:** Sites appear in a list. The AJAX endpoint returns JSON. Test by adding two sites and calling the API endpoint in the browser.

---

### STEP 4 — Emission Factors Library

**What to build:** `phase3/emission-factors.php`

**Purpose:** The emission factors library is the master data that powers all CO₂e calculations. The database is already seeded with 9 standard factors. Admins can add new factors or deactivate old ones.

**Table used:** `emission_factors`

**Display:** Show a table of all active factors (`WHERE is_active = 1`) with columns: Activity Type, Scope, Region, Factor Value, Unit, Source, Valid From.

**Add new factor — form fields:**
- Scope (select: `Scope 1` / `Scope 2 Location-Based` / `Scope 2 Market-Based` / `Scope 3`)
- Activity Type (text — e.g. `natural_gas`, `electricity` — must match exactly what the fuel/energy forms send)
- Region (text — e.g. `GLOBAL`, `UK`, `MY`)
- Factor Value (decimal number, e.g. `2.04220`)
- Unit (select: `litre` / `kWh` / `m3` / `kg`)
- Source (select: `DEFRA` / `IEA` / `EPA` / `Custom`)
- Version (text, e.g. `2024`)
- Valid From (date)

**Save logic:**
```sql
INSERT INTO emission_factors
(id, activity_type, scope, region, factor, unit, source, version, is_active, valid_from, created_at, updated_at)
VALUES (:id, :activity_type, :scope, :region, :factor, :unit, :source, :version, 1, :valid_from, NOW(), NOW())
```

**Deactivate a factor:** `UPDATE emission_factors SET is_active = 0 WHERE id = :id`

⚠ The `activity_type` values entered here MUST exactly match the `fuel_type` values submitted on the fuel form and `energy_type` values on the energy form.

**After this step works:** The emission factors table is visible. Admins can add custom factors.

---

### STEP 5 — Fuel Consumption — Scope 1 Emissions

**What to build:** `phase3/fuel.php`

**Purpose:** Records raw fuel usage per site and immediately calculates and saves the CO₂e equivalent into `emission_records`. Scope 1 = direct emissions from fuels burned on-site.

**Form fields:**
- Site (select — populated from `/api/sites.php` via jQuery on page load)
- Date (date input — format `YYYY-MM-DD`)
- Fuel Type (select — values must match `activity_type` in `emission_factors`: `diesel`, `petrol`, `natural_gas`, `lpg`)
- Volume (number, decimal)
- Unit (select: `litre`, `m3`, `kg`, `tonne`)

**What happens on submit — 4 steps:**

**Step A — Save raw activity:**
```sql
INSERT INTO fuel_activities (id, site_id, date, fuel_type, volume, unit, created_at, updated_at)
VALUES (:id, :site_id, :date, :fuel_type, :volume, :unit, NOW(), NOW())
```

**Step B — Find matching emission factor:**
```sql
SELECT * FROM emission_factors
WHERE activity_type = :fuel_type AND scope = 'Scope 1' AND is_active = 1
LIMIT 1
```

**Step C — Calculate tCO₂e:**
```php
$tco2e = ($volume * $factor['factor']) / 1000;
```

**Step D — Save emission record:**
```sql
INSERT INTO emission_records
(id, company_id, scope, tco2e_calculated, fuel_activity_id, emission_factor_id, date_calculated, created_at)
VALUES (:id, :company_id, 'Scope 1', :tco2e, :fuel_activity_id, :emission_factor_id, NOW(), NOW())
```

**Show result to user:** After saving, display a success message showing the calculated tCO₂e rounded to 4 decimal places.

**After this step works:** Submit the form with 2,500 m³ of Natural Gas. Verify the emission_records table shows approximately 5.1055 tCO₂e.

---

### STEP 6 — Energy Consumption — Scope 2 Emissions

**What to build:** `phase3/energy.php`

**Purpose:** Records electricity and heating consumption per site and calculates Scope 2 (indirect) CO₂e. Scope 2 = emissions from purchased electricity, district heating, steam, or cooling.

**Form fields:**
- Site (select — populated from `/api/sites.php` via jQuery)
- Date (month input — returns `YYYY-MM`, append `-01` before saving to DATE column)
- Energy Type (select: `electricity`, `district_heating`, `steam`, `cooling`)
- Consumption (number, decimal)
- Unit (select: `kWh`, `MWh`, `GJ`)

**What happens on submit — 6 steps:**

**Step A — Normalize consumption to kWh:**
```php
$consumptionKwh = $consumption;
if ($unit === 'MWh') $consumptionKwh = $consumption * 1000;
if ($unit === 'GJ')  $consumptionKwh = $consumption * 277.78;
```

**Step B — Save raw energy activity:**
```sql
INSERT INTO energy_activities (id, site_id, date, energy_type, consumption, unit, created_at, updated_at)
VALUES (:id, :site_id, :date, :energy_type, :consumption, :unit, NOW(), NOW())
```

**Step C — Get company country for region matching:**
```sql
SELECT country_of_registration FROM companies WHERE id = :company_id
```

**Step D — Find matching factor (try company region first, fall back to GLOBAL):**
```sql
SELECT * FROM emission_factors
WHERE activity_type = 'electricity'
  AND scope = 'Scope 2 Location-Based'
  AND is_active = 1
  AND (region = :region OR region = 'GLOBAL')
ORDER BY CASE WHEN region = :region THEN 0 ELSE 1 END
LIMIT 1
```

**Step E — Calculate tCO₂e:**
```php
$tco2e = ($consumptionKwh * $factor['factor']) / 1000;
```

**Step F — Save emission record with scope = `'Scope 2 Location-Based'`**

**After this step works:** Both fuel and energy emissions appear in `emission_records`. Scope 1 and Scope 2 rows exist.

---

### STEP 7 — Emissions Dashboard

**What to build:** `phase3/dashboard.php` and `api/emissions-summary.php`

**Purpose:** A read-only summary page that aggregates all emission records for the company. Shows three metric cards (Scope 1, Scope 2, Total) and a detailed table of recent calculations.

**Aggregate query for metric cards:**
```sql
SELECT scope, ROUND(SUM(tco2e_calculated), 4) AS total_tco2e, COUNT(*) AS record_count
FROM emission_records
WHERE company_id = :company_id
GROUP BY scope
```

**Recent calculations table — JOIN query:**
```sql
SELECT
    er.scope, er.tco2e_calculated, er.date_calculated,
    COALESCE(fa.fuel_type, ea.energy_type) AS activity_name,
    COALESCE(fa.volume, ea.consumption)    AS input_value,
    COALESCE(fa.unit, ea.unit)             AS input_unit,
    ef.factor, ef.unit AS factor_unit, ef.source,
    s.name AS site_name
FROM emission_records er
LEFT JOIN fuel_activities   fa ON er.fuel_activity_id   = fa.id
LEFT JOIN energy_activities ea ON er.energy_activity_id = ea.id
LEFT JOIN emission_factors  ef ON er.emission_factor_id = ef.id
LEFT JOIN sites              s ON COALESCE(fa.site_id, ea.site_id) = s.id
WHERE er.company_id = :company_id
ORDER BY er.date_calculated DESC
LIMIT 20
```

**Period filter (jQuery AJAX):**
When the user changes the dropdown (Last 30 Days / Last Quarter / Year to Date), call `/api/emissions-summary.php` with `{ period: value }` and update the metric cards without page reload.

**UI layout (from index.html reference):**
- Three stat cards side by side: Scope 1 (green), Scope 2 (blue), Total (gray)
- Period filter dropdown + Export button in the top right
- Table below with columns: Activity, Scope, Input Data, Calculation Details, Emissions (tCO₂e)

**After this step works:** The dashboard shows live totals. Changing the period dropdown refreshes the numbers via AJAX.

---

### STEP 8 — ESRS 2 General Disclosures

**What to build:** `phase4/esrs2.php`

**Purpose:** Captures high-level governance and materiality information about the company's sustainability reporting. One record per company per reporting period. This is Phase 4A of environmental reporting.

**⚠ Important:** The `esrs2_general_disclosures` table does NOT exist in the provided SQL file. You must create it first:
```sql
CREATE TABLE IF NOT EXISTS `esrs2_general_disclosures` (
    `id`                               VARCHAR(36) NOT NULL,
    `company_id`                       VARCHAR(36) NOT NULL,
    `reporting_period`                 VARCHAR(7)  NOT NULL COMMENT 'YYYY-MM',
    `consolidation_scope`              TEXT        DEFAULT NULL,
    `value_chain_boundaries`           TEXT        DEFAULT NULL,
    `board_role_in_sustainability`     TEXT        DEFAULT NULL,
    `esg_integration_in_remuneration`  INT         DEFAULT NULL,
    `assessment_process`               TEXT        DEFAULT NULL,
    `created_by`                       VARCHAR(36) NOT NULL,
    `updated_by`                       VARCHAR(36) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_esrs2_company_period` (`company_id`, `reporting_period`),
    CONSTRAINT `fk_esrs2_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Form fields:**

| HTML field name | Input type | DB column |
|---|---|---|
| `reportingPeriod` | month | `reporting_period` |
| `esgIntegrationInRemuneration` | number 0–100 | `esg_integration_in_remuneration` |
| `consolidationScope` | textarea | `consolidation_scope` |
| `valueChainBoundaries` | textarea | `value_chain_boundaries` |
| `boardRoleInSustainability` | textarea | `board_role_in_sustainability` |
| `assessmentProcess` | textarea | `assessment_process` |

**Save logic — always INSERT or UPDATE (never blind INSERT):**
```php
// 1. Check if record exists
$stmt = $pdo->prepare('SELECT id FROM esrs2_general_disclosures
    WHERE company_id = :cid AND reporting_period = :period');
$stmt->execute([':cid' => company_id(), ':period' => $period]);
$existing = $stmt->fetch();

// 2. UPDATE if exists, INSERT if not
if ($existing) {
    // UPDATE ... WHERE id = :id
} else {
    // INSERT with new uuid()
}
```

**After this step works:** Submit the form twice for the same period. Verify that only one row exists in the table (the second submit updated the first row).

---

### STEP 9 — Environmental Topics (ESRS E1–E5)

**What to build:** `phase4/environmental.php`

**Purpose:** A tabbed accordion form capturing detailed environmental disclosures across five ESRS topics. Uses the `environmental_topics` table. One record per company per period.

**UI structure:** Five collapsible accordion panels (use CSS checkbox toggle from index.html prototype). Each panel has a "Is this topic material?" checkbox at the top.

**Fields by accordion section:**

**E1 — Climate Change** (show live Scope 1 + Scope 2 totals from emission_records inside this section):
- `e1_material` checkbox → `e1_material` (TINYINT 0/1)
- `e1_climatePolicy` textarea → `e1_climate_policy`
- `e1_reductionTarget` text → `e1_reduction_target`

**E2 — Pollution:**
- `e2_material` checkbox → `e2_material`
- `e2_nox_t_per_year` number → `e2_nox_t_per_year`
- `e2_sox_t_per_year` number → `e2_sox_t_per_year`

**E3 — Water & Marine:**
- `e3_material` checkbox → `e3_material`
- `e3_water_withdrawal_m3` number → `e3_water_withdrawal_m3`
- `e3_water_recycling_rate_pct` number 0–100 → `e3_water_recycling_rate_pct`

**E4 — Biodiversity:**
- `e4_material` checkbox → `e4_material`
- `e4_protected_areas_impact` textarea → `e4_protected_areas_impact`

**E5 — Circular Economy:**
- `e5_material` checkbox → `e5_material`
- `e5_recycling_rate_pct` number 0–100 → `e5_recycling_rate_pct`
- `e5_recycled_input_materials_pct` number 0–100 → `e5_recycled_input_materials_pct`

**Checkbox PHP handling:**
```php
$e1_material = isset($_POST['e1_material']) ? 1 : 0;
// repeat for e2, e3, e4, e5
```

**Status workflow** (stored in `status` column):
```
DRAFT → UNDER_REVIEW → APPROVED → PUBLISHED
                     ↘ REJECTED → DRAFT
```
Any user can move to UNDER_REVIEW. Only `admin` role can APPROVE, PUBLISH, or REJECT.

**After this step works:** Fill out the E1 section. The live emissions numbers from Step 7 appear inside the E1 accordion panel.

---

### STEP 10 — Social & Governance Reporting (ESRS S1–S4, G1)

**What to build:** `phase5/social.php` and `phase5/governance.php`

**Purpose:** Captures social and governance disclosures. Same accordion pattern as Step 9. Two separate tables: `social_topics` and `s_governance`.

**Social Topics (`social_topics`) — fields by section:**

**S1 — Own Workforce:**
- `s1_material` checkbox
- `s1_employee_count_by_contract` textarea (breakdown by contract type)
- `s1_health_and_safety` textarea
- `s1_training_hours_per_employee` integer

**S2 — Workers in Value Chain (LkSG compliance):**
- `s2_material` checkbox
- `s2_pct_suppliers_audited` integer 0–100
- `s2_remediation_actions` textarea

**S3 — Affected Communities:**
- `s3_material` checkbox
- `s3_community_engagement` textarea
- `s3_complaints_and_outcomes` textarea

**S4 — Consumers & End Users:**
- `s4_material` checkbox
- `s4_product_safety_incidents` integer
- `s4_consumer_remediation` textarea

**Governance (`s_governance`) — G1 fields:**
- `reporting_period` month input
- `g1_board_composition_independence` textarea
- `g1_gender_diversity_pct` integer 0–100
- `g1_esg_oversight` textarea
- `g1_whistleblower_cases` textarea
- `g1_anti_corruption_policies` text
- `g1_related_party_controls` text

**Both tables have UNIQUE KEY (company_id, reporting_period) — use INSERT or UPDATE pattern.**

**Status change AJAX endpoint (`api/save-status.php`):**
```php
// Whitelist allowed tables
$allowed = ['social_topics','environmental_topics','s_governance','eu_taxonomy','assurance'];
if (!in_array($_POST['table'], $allowed)) { json_response(['error' => 'Invalid table'], 400); }

// Admin check for restricted statuses
if (in_array($_POST['status'], ['APPROVED','PUBLISHED','REJECTED']) && !is_admin()) {
    json_response(['error' => 'Unauthorized'], 403);
}

// Update
$stmt = $pdo->prepare('UPDATE ' . $_POST['table'] . ' SET status = :s WHERE id = :id AND company_id = :cid');
$stmt->execute([':s' => $_POST['status'], ':id' => $_POST['id'], ':cid' => company_id()]);
json_response(['success' => true]);
```

**jQuery call to change status:**
```javascript
$('#btn-submit-review').on('click', function() {
    $.post('/api/save-status.php',
        { table: 'social_topics', id: recordId, status: 'UNDER_REVIEW' },
        function(res) { if (res.success) location.reload(); }, 'json');
});
```

**After this step works:** Social and Governance forms save data. Status badge updates without page reload.

---

### STEP 11 — EU Taxonomy & Assurance Reports

**What to build:** `phase6/taxonomy.php` and `phase6/assurance.php`

**Purpose:** Captures EU Taxonomy alignment percentages and third-party assurance/audit information. Final data collection phase before PDF generation.

**EU Taxonomy (`eu_taxonomy`) — form fields:**

| HTML field name | Input type | DB column |
|---|---|---|
| `reporting_period` | month | `reporting_period` |
| `economic_activities` | textarea | `economic_activities` |
| `technical_screening_criteria` | textarea | `technical_screening_criteria` |
| `taxonomy_eligible_revenue_pct` | number 0–100 | `taxonomy_eligible_revenue_pct` |
| `taxonomy_aligned_revenue_pct` | number 0–100 | `taxonomy_aligned_revenue_pct` |
| `taxonomy_eligible_capex_pct` | number 0–100 | `taxonomy_eligible_capex_pct` |
| `taxonomy_aligned_capex_pct` | number 0–100 | `taxonomy_aligned_capex_pct` |
| `taxonomy_aligned_opex_pct` | number 0–100 | `taxonomy_aligned_opex_pct` |
| `dnsh_status` | select | `dnsh_status` |
| `social_safeguards_status` | select | `social_safeguards_status` |

**Enum values for dropdowns:**
- `dnsh_status`: `ALL_OBJECTIVES_PASSED` / `SOME_OBJECTIVES_NOT_MET` / `ASSESSMENT_IN_PROGRESS`
- `social_safeguards_status`: `FULL_COMPLIANCE` / `NON_COMPLIANCE` / `PARTIAL_REMEDIATION`

**Assurance (`assurance`) — form fields:**

| HTML field name | Input type | DB column |
|---|---|---|
| `reporting_period` | month | `reporting_period` |
| `provider` | text | `provider` |
| `level` | select: `limited` / `reasonable` | `level` |
| `standard` | text (e.g. ISAE 3000) | `standard` |
| `scope_description` | textarea | `scope_description` |
| `conclusion` | textarea | `conclusion` |
| `report_date` | date | `report_date` |
| `checklist_*` (4 checkboxes) | checkbox | 4 TINYINT columns |

**⚠ Important:** The `assurance` table is missing 4 checklist columns. Add them:
```sql
ALTER TABLE assurance
    ADD COLUMN checklist_data_collection_documented   TINYINT(1) DEFAULT 0,
    ADD COLUMN checklist_internal_controls_tested     TINYINT(1) DEFAULT 0,
    ADD COLUMN checklist_source_documentation_trail   TINYINT(1) DEFAULT 0,
    ADD COLUMN checklist_calculation_method_validated TINYINT(1) DEFAULT 0;
```

**Dynamic checklist progress bar (jQuery — replace hardcoded 25% from index.html):**
```javascript
function updateChecklistProgress() {
    var total   = $('.checklist-item').length;         // always 4
    var checked = $('.checklist-item:checked').length; // 0 to 4
    var pct     = Math.round((checked / total) * 100);
    $('#checklist-progress-bar').css('width', pct + '%');
    $('#checklist-progress-label').text(pct + '%');
}
$(document).ready(function() {
    updateChecklistProgress();
    $('.checklist-item').on('change', updateChecklistProgress);
});
```

**After this step works:** All 6 data collection phases are complete. The database has rows in all reporting tables for the selected period.

---

### STEP 12 — Generate ESG Report PDF (TCPDF)

**What to build:** `report/generate.php` and the Generate Report button in the UI

**Purpose:** The final step. Fetches all data from all tables for the selected company and reporting period, then renders a formatted A4 PDF using TCPDF and forces a browser download.

**Install TCPDF:**
```bash
composer require tecnickcom/tcpdf
# then in PHP:
require_once '../vendor/autoload.php';
```

**Font size constants — define these at the top of generate.php:**
```php
define('PDF_FONT_FAMILY', 'helvetica');
define('PDF_FONT_BODY',   12);  // ← ALL body text must be 12pt
define('PDF_FONT_H1',     18);  // Section/phase titles
define('PDF_FONT_H2',     14);  // Sub-section headings
define('PDF_FONT_SMALL',  10);  // Captions only
```

**Generate Report button (HTML form):**
```html
<form method="POST" action="/report/generate.php">
    <input type="hidden" name="period" value="<?= htmlspecialchars($selectedPeriod) ?>">
    <button type="submit"
        class="px-8 py-3 bg-gradient-to-r from-emerald-600 to-teal-600
               text-white text-base font-bold rounded-xl">
        Generate ESG Report PDF
    </button>
</form>
```

**`report/generate.php` — complete logic:**

```php
<?php
session_start();
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';
require_once '../includes/helpers.php';
require_once '../vendor/autoload.php';

define('PDF_FONT_FAMILY', 'helvetica');
define('PDF_FONT_BODY', 12);
define('PDF_FONT_H1',   18);
define('PDF_FONT_H2',   14);

$period = sanitize($_POST['period'] ?? $_GET['period'] ?? '');
$cid    = company_id();

// ── STEP 1: Fetch all data from DB ────────────────────────────────────
$stmt = $pdo->prepare('SELECT * FROM companies WHERE id = :id');
$stmt->execute([':id' => $cid]);
$company = $stmt->fetch();

$stmt = $pdo->prepare('
    SELECT scope, ROUND(SUM(tco2e_calculated), 4) AS total
    FROM emission_records WHERE company_id = :id GROUP BY scope
');
$stmt->execute([':id' => $cid]);
$emissions = [];
foreach ($stmt->fetchAll() as $r) { $emissions[$r['scope']] = $r['total']; }

function fetchReport(PDO $db, string $table, string $cid, string $period): array|false {
    $s = $db->prepare('SELECT * FROM '.$table.' WHERE company_id=:c AND reporting_period=:p LIMIT 1');
    $s->execute([':c' => $cid, ':p' => $period]);
    return $s->fetch();
}
$env       = fetchReport($pdo, 'environmental_topics',       $cid, $period);
$social    = fetchReport($pdo, 'social_topics',              $cid, $period);
$gov       = fetchReport($pdo, 's_governance',               $cid, $period);
$tax       = fetchReport($pdo, 'eu_taxonomy',                $cid, $period);
$assurance = fetchReport($pdo, 'assurance',                  $cid, $period);
$esrs2     = fetchReport($pdo, 'esrs2_general_disclosures',  $cid, $period);

// ── STEP 2: Init TCPDF ────────────────────────────────────────────────
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('ESG Platform');
$pdf->SetAuthor($company['name']);
$pdf->SetTitle($company['name'] . ' ESG Report ' . $period);
$pdf->SetMargins(20, 20, 20);
$pdf->SetAutoPageBreak(true, 25);
$pdf->AddPage();

// ── STEP 3: Cover Page ────────────────────────────────────────────────
$pdf->SetFont(PDF_FONT_FAMILY, 'B', 24);
$pdf->Cell(0, 14, $company['name'], 0, 1, 'C');
$pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_H2);
$pdf->Cell(0, 10, 'ESG Sustainability Report — Period: ' . $period, 0, 1, 'C');
$pdf->Cell(0, 8,  'Country: ' . ($company['country_of_registration'] ?? 'N/A'), 0, 1, 'C');
$pdf->Ln(10);

// ── STEP 4: ESRS 2 General Disclosures ───────────────────────────────
if ($esrs2) {
    $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_H1);
    $pdf->Cell(0, 10, 'ESRS 2 — General Disclosures', 0, 1);
    $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY); // 12pt
    $pdf->MultiCell(0, 8, 'Consolidation Scope: ' . ($esrs2['consolidation_scope'] ?? 'N/A'));
    $pdf->MultiCell(0, 8, 'Board Role in Sustainability: ' . ($esrs2['board_role_in_sustainability'] ?? 'N/A'));
    $pdf->Cell(0, 8, 'ESG Remuneration Integration: ' . ($esrs2['esg_integration_in_remuneration'] ?? 'N/A') . '%', 0, 1);
    $pdf->Ln(5);
}

// ── STEP 5: Emissions Summary ─────────────────────────────────────────
$pdf->AddPage();
$pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_H1);
$pdf->Cell(0, 10, 'Emissions Summary (tCO2e)', 0, 1);
$pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY); // 12pt
$pdf->Cell(0, 8, 'Scope 1 — Direct Emissions: ' . ($emissions['Scope 1'] ?? 0) . ' tCO2e', 0, 1);
$pdf->Cell(0, 8, 'Scope 2 — Energy Emissions: ' . ($emissions['Scope 2 Location-Based'] ?? 0) . ' tCO2e', 0, 1);
$total = ($emissions['Scope 1'] ?? 0) + ($emissions['Scope 2 Location-Based'] ?? 0);
$pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_BODY); // 12pt bold
$pdf->Cell(0, 8, 'Total Carbon Footprint: ' . round($total, 4) . ' tCO2e', 0, 1);
$pdf->Ln(5);

// ── STEP 6: Environmental Topics E1–E5 ───────────────────────────────
if ($env) {
    $pdf->AddPage();
    $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_H1);
    $pdf->Cell(0, 10, 'Environmental Topics (ESRS E1–E5)', 0, 1);
    $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_H2);
    $pdf->Cell(0, 8, 'E1 — Climate Change', 0, 1);
    $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY); // 12pt
    $pdf->MultiCell(0, 8, 'Climate Policy: '   . ($env['e1_climate_policy']    ?? 'N/A'));
    $pdf->MultiCell(0, 8, 'Reduction Target: ' . ($env['e1_reduction_target']  ?? 'N/A'));
    $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_H2);
    $pdf->Cell(0, 8, 'E2 — Pollution', 0, 1);
    $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY); // 12pt
    $pdf->Cell(0, 8, 'NOx: ' . ($env['e2_nox_t_per_year'] ?? 'N/A') . ' t/year', 0, 1);
    $pdf->Cell(0, 8, 'SOx: ' . ($env['e2_sox_t_per_year'] ?? 'N/A') . ' t/year', 0, 1);
    $pdf->Cell(0, 8, 'Water Withdrawal: '   . ($env['e3_water_withdrawal_m3']      ?? 'N/A') . ' m3', 0, 1);
    $pdf->Cell(0, 8, 'Water Recycling: '    . ($env['e3_water_recycling_rate_pct'] ?? 'N/A') . '%', 0, 1);
    $pdf->Cell(0, 8, 'Recycling Rate: '     . ($env['e5_recycling_rate_pct']       ?? 'N/A') . '%', 0, 1);
}

// ── STEP 7: Social Topics S1–S4 ──────────────────────────────────────
if ($social) {
    $pdf->AddPage();
    $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_H1);
    $pdf->Cell(0, 10, 'Social Topics (ESRS S1–S4)', 0, 1);
    $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY); // 12pt
    $pdf->Cell(0, 8, 'Training Hours per Employee: ' . ($social['s1_training_hours_per_employee'] ?? 'N/A'), 0, 1);
    $pdf->Cell(0, 8, 'Suppliers Audited: '           . ($social['s2_pct_suppliers_audited']       ?? 'N/A') . '%', 0, 1);
    $pdf->Cell(0, 8, 'Product Safety Incidents: '    . ($social['s4_product_safety_incidents']    ?? 'N/A'), 0, 1);
    $pdf->MultiCell(0, 8, 'Community Engagement: '   . ($social['s3_community_engagement']        ?? 'N/A'));
}

// ── STEP 8: Governance G1 ────────────────────────────────────────────
if ($gov) {
    $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_H2);
    $pdf->Cell(0, 10, 'Governance (ESRS G1)', 0, 1);
    $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY); // 12pt
    $pdf->Cell(0, 8, 'Board Gender Diversity: ' . ($gov['g1_gender_diversity_pct'] ?? 'N/A') . '%', 0, 1);
    $pdf->MultiCell(0, 8, 'ESG Oversight: ' . ($gov['g1_esg_oversight'] ?? 'N/A'));
    $pdf->MultiCell(0, 8, 'Anti-Corruption Policies: ' . ($gov['g1_anti_corruption_policies'] ?? 'N/A'));
}

// ── STEP 9: EU Taxonomy ───────────────────────────────────────────────
if ($tax) {
    $pdf->AddPage();
    $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_H1);
    $pdf->Cell(0, 10, 'EU Taxonomy Alignment', 0, 1);
    $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY); // 12pt
    $pdf->Cell(0, 8, 'Eligible Revenue: ' . ($tax['taxonomy_eligible_revenue_pct'] ?? 'N/A') . '%', 0, 1);
    $pdf->Cell(0, 8, 'Aligned Revenue: '  . ($tax['taxonomy_aligned_revenue_pct']  ?? 'N/A') . '%', 0, 1);
    $pdf->Cell(0, 8, 'Eligible CapEx: '   . ($tax['taxonomy_eligible_capex_pct']   ?? 'N/A') . '%', 0, 1);
    $pdf->Cell(0, 8, 'Aligned CapEx: '    . ($tax['taxonomy_aligned_capex_pct']    ?? 'N/A') . '%', 0, 1);
    $pdf->Cell(0, 8, 'Aligned OpEx: '     . ($tax['taxonomy_aligned_opex_pct']     ?? 'N/A') . '%', 0, 1);
    $pdf->Cell(0, 8, 'DNSH Status: '      . ($tax['dnsh_status']                   ?? 'N/A'), 0, 1);
    $pdf->Cell(0, 8, 'Social Safeguards: '. ($tax['social_safeguards_status']       ?? 'N/A'), 0, 1);
}

// ── STEP 10: Assurance ────────────────────────────────────────────────
if ($assurance) {
    $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_H2);
    $pdf->Cell(0, 10, 'Assurance & Audit', 0, 1);
    $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY); // 12pt
    $pdf->Cell(0, 8, 'Provider: ' . ($assurance['provider'] ?? 'N/A'), 0, 1);
    $pdf->Cell(0, 8, 'Level: '    . ($assurance['level']    ?? 'N/A'), 0, 1);
    $pdf->Cell(0, 8, 'Standard: ' . ($assurance['standard'] ?? 'N/A'), 0, 1);
    $pdf->MultiCell(0, 8, 'Conclusion: ' . ($assurance['conclusion'] ?? 'N/A'));
}

// ── STEP 11: Output PDF as download ──────────────────────────────────
$filename = preg_replace('/[^A-Za-z0-9_\-.]/', '_',
    'ESG_Report_' . ($company['name'] ?? 'Company') . '_' . $period . '.pdf');
$pdf->Output($filename, 'D'); // 'D' = force browser download
exit;
```

**After this step works:** Click the Generate Report button. A PDF downloads immediately. Open it and verify:
- All body text is 12pt
- Scope 1 and Scope 2 totals match what the dashboard shows
- All sections (E1–E5, S1–S4, G1, EU Taxonomy, Assurance) appear with the correct data

---

## QUICK REFERENCE — WHAT TO BUILD AT EACH STEP

| Step | File(s) | Table(s) | Tests to run |
|------|---------|----------|-------------|
| 1 | auth/register.php | companies, users | Check phpMyAdmin for both rows |
| 2 | auth/login.php, logout.php | users | Login works, session has user_id |
| 3 | sites/*.php, api/sites.php | sites | Sites list shows. API returns JSON |
| 4 | phase3/emission-factors.php | emission_factors | New factor appears in table |
| 5 | phase3/fuel.php | fuel_activities, emission_records | Record shows ~5.1055 tCO₂e |
| 6 | phase3/energy.php | energy_activities, emission_records | Scope 2 record appears |
| 7 | phase3/dashboard.php, api/emissions-summary.php | emission_records | Totals match DB sum |
| 8 | phase4/esrs2.php | esrs2_general_disclosures | Second submit updates, not inserts |
| 9 | phase4/environmental.php | environmental_topics | E1 panel shows live emission totals |
| 10 | phase5/social.php, governance.php | social_topics, s_governance | Status changes via AJAX |
| 11 | phase6/taxonomy.php, assurance.php | eu_taxonomy, assurance | Progress bar updates dynamically |
| 12 | report/generate.php | all tables | PDF downloads with 12pt body text |

---

## HOW TO USE THIS PROMPT WITH AN AI

When you start a new conversation with an AI assistant, begin with:

> "I am building an ESG Reporting Platform. Here is the full project context. Please help me build **Step [N] — [Name]** only. Do not build any other steps yet."

Then paste this entire document above your request.

After completing each step, test it manually, then start a new message:

> "Step [N] is working. Now please help me build **Step [N+1] — [Name]**."

This ensures the AI stays focused on one step at a time and does not skip ahead or mix up steps.

---

*Stack: PHP 8 · MySQL 8 · jQuery 3 · Tailwind CSS · TCPDF · All body text: 12pt*
