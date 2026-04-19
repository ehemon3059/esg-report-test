Tier 2 Compliance — Your Step-by-Step Build Plan
Total duration: 14 weeks (12 from the original plan + 2 added for realism)
Starting point: Your current Starter-level codebase (~6–8% of Tier 2 done)
Ending point: Production-ready Tier 2, ready for German SME procurement

Week 0 — Foundation Fixes (Before Tier 2 Work Starts)
Do these first or every later step will be harder.

Freeze current version — tag git as v1.0-starter so you can always ship Starter in parallel.
Install missing dependencies — add PHPMailer to composer.json (phpmailer/phpmailer). Run composer update.
Build a migrations system — create database/migrations/ folder, numbered SQL files (001_initial.sql, 002_roles.sql, …), plus a schema_migrations tracking table and a small PHP runner script.
Add CSRF protection — one helper in includes/helpers.php that issues and validates tokens; wire into every POST form.
Harden sessions — in includes/auth.php, set cookies to Secure, HttpOnly, SameSite=Lax. Regenerate session ID on login.
Rate-limit login — track failed attempts in a small table, block after 5 failures in 15 min.
Move uploads outside webroot — prepare the /var/evidence/ or equivalent path structure now, even if empty.
Deliverable: Migrations running, CSRF live, session cookies hardened, PHPMailer installed.

Week 1 — Step 1: Roles & Permissions
Why first: Every later feature calls require_role().

Write migration 002_roles.sql — expand users.role enum to owner, admin, editor, viewer, auditor. Create user_company_roles table (user_id, company_id, role, subsidiary_scope, period_scope).
Data migration — for every existing user, insert a row into user_company_roles preserving their current role. Promote the first admin of each company to owner.
Create includes/permissions.php with helpers: current_role(), require_role($min), can_edit($entity), can_view($entity), can_comment().
Add permission guard to the top of every page in phase3/, phase4/, phase5/, phase6/, sites/, company/, api/.
Build a simple users/index.php + users/invite.php for owners/admins to manage team members.
Test: create 5 test users (one per role), walk through every page, confirm blocks work.
Deliverable: Every page enforces role. Unauthorized requests return 403.

Weeks 2–3 — Step 2: Audit Trail
Why second: From here forward, every write must log. Retrofitting later means editing the same files twice.

Week 2 — Infrastructure
Migration 003_audit_log.sql — create audit_log table with fields: id, company_id, user_id, user_email, action, entity_type, entity_id, reporting_period, subsidiary_id, changes (JSON), ip_address, user_agent, created_at. Add indexes on (company_id, reporting_period), (entity_type, entity_id), created_at.
Create includes/audit.php with one function: audit_log($pdo, $action, $entity_type, $entity_id, $old_row, $new_row, $context). It computes a field-by-field diff and stores as JSON.
Document the helper in a docs/audit.md note for your future self.
Week 3 — Wire it everywhere
For every CRUD endpoint — fetch the old row before UPDATE/DELETE, call the helper after success. Files to touch:
phase3/fuel.php, phase3/energy.php, phase3/scope3.php, phase3/emission-factors.php
phase4/esrs2.php, phase4/environmental.php
phase5/social.php, phase5/governance.php
phase6/taxonomy.php, phase6/assurance.php
sites/create.php, sites/edit.php, sites/delete.php
company/create.php, auth/login.php, auth/logout.php
Build audit/index.php — filterable paginated log viewer (period, user, entity, action, date range).
Build audit/export.php — CSV export for a chosen period.
Deliverable: Every write creates an audit entry. Auditors can export.

Weeks 4–5 — Step 3: Multi-Subsidiary with Consolidation
Why third: Changes data model. DMA and evidence depend on subsidiary scope.

Week 4 — Schema + migration
Migration 004_subsidiaries.sql — create subsidiaries table (id, company_id, parent_subsidiary_id, name, legal_form, country, vat_id, ownership_percentage, consolidation_method, is_active).
Migration 005_subsidiary_fks.sql — add subsidiary_id column to: sites, fuel_activities, energy_activities, emission_records, esrs2_general_disclosures, environmental_topics, social_topics, s_governance, eu_taxonomy, assurance. Add (subsidiary_id, reporting_period) index on activity tables.
Migration 006_default_subsidiary.sql — for every company, insert a "Main Entity" subsidiary; backfill subsidiary_id on all existing rows.
Test migration on staging DB copy first. Back up before running on prod.
Week 5 — Code + UI
Add subsidiary selector to includes/header.php next to the period picker. Store choice in $_SESSION['active_subsidiary'] (null = consolidated).
Create includes/query_scope.php helper that injects the subsidiary filter into queries. Update every SELECT in phase3–phase6.
Build includes/consolidation.php — implements full / proportional / equity / none logic.
Build subsidiaries/index.php, create.php, edit.php, delete.php. Enforce the Tier 2 limit of 3. Admin/owner only.
Modify report/generate.php to accept a scope parameter: single subsidiary, consolidated, or breakdown (per-subsidiary section).
Deliverable: Create up to 3 subsidiaries, assign data, generate consolidated or per-entity PDFs.

Week 6 — Step 4: Evidence Locker
Why fourth: DMA IROs attach evidence. Build this before DMA.

Migration 007_evidence.sql — evidence_files table (id, company_id, subsidiary_id, reporting_period, entity_type, entity_id, field_name, filename, stored_path, mime_type, file_size, file_hash, description, tags, uploaded_by, deleted_at).
Configure storage: /var/evidence/{company_id}/{YYYY-MM}/{uuid}.{ext} — outside webroot.
Build includes/evidence.php:
store_evidence() — validates MIME (PDF/JPEG/PNG/XLSX/DOCX/CSV), caps at 25 MB, computes SHA-256, moves file, inserts row.
delete_evidence() — soft-delete with deleted_at.
Build evidence/download.php — checks permissions, verifies company ownership (cross-tenant guard), re-hashes file vs stored hash, streams if match, logs download as audit entry.
Add an "Evidence" panel to every disclosure form — drag-drop upload with description + tags, list of attached files.
Build evidence/index.php — master list, filters by tag/period/subsidiary/entity.
Move the existing uploads/assurance/ files into the new storage scheme and update phase6/assurance.php to use it.
Deliverable: Any disclosure field accepts files. Searchable, integrity-verified, permission-scoped.

Weeks 7–9 — Step 5: Double Materiality Assessment
Why here: Uses roles, audit, subsidiaries, and evidence. Biggest single feature.

Week 7 — Stakeholders + topic catalog
Migration 008_dma_tables.sql — create esrs_topics, dma_stakeholders, dma_thresholds.
Seed esrs_topics — minimum 30 rows covering E1–E5, S1–S4, G1 and sub-topics (you can expand to 80+ later, but 30 is fine for MVP). Reference official EFRAG standard codes.
Build dma/stakeholders.php — CRUD + influence/interest matrix visualization.
Build dma/thresholds.php — admin sets company-level impact_threshold and financial_threshold (default 3.0 each).
Week 8 — IRO inventory + scoring
Migration 009_dma_iros.sql — dma_iros (with all scoring fields and workflow statuses) + dma_iro_stakeholders junction.
Document the scoring methodology in docs/methodology/dma_scoring.md. Every auditor will ask.
Build dma/iros.php — list with filters.
Build dma/iro-edit.php — form with sliders, live score computation in JS.
Build dma/approve.php — approval workflow (admin/owner).
Week 9 — Matrix + auto-unlock
Add Chart.js via CDN to includes/header.php.
Build dma/matrix.php — scatter plot, threshold lines, IRO-type colors, quadrant labels, hover tooltips.
Build includes/materiality.php with material_topics($pdo, $company_id, $period) helper.
Update phase4/environmental.php, phase5/social.php, phase5/governance.php — collapse non-material sections, show "assessed as not material" banner.
Extend report/generate.php — add DMA section with stakeholder list, IRO table, matrix as embedded image, material topics + rationale.
Deliverable: Full DMA workflow from stakeholders → IROs → matrix → automatic disclosure gating.

Week 10 — Step 6: Auditor Login & Comments
Migration 010_auditor_comments.sql — auditor_invitations (with token, expires_at — default 30 days, not 90), comments (threaded via parent_comment_id, status = open/resolved/dismissed).
Build auditor/invite.php — admin enters email/name/firm/period scope. Generate token via random_bytes(32). Send email via PHPMailer.
Build auditor/accept.php — token validation, password setup, auto-create user + auditor role row scoped to the invited period.
Lock down auditor UI — hide edit buttons, block admin/settings/subsidiary/user pages. Allow only: disclosures (read-only), evidence viewer, DMA matrix, audit log, comments.
Add a comment icon next to every major disclosure field. Sidebar thread UI. Editors can reply, attach evidence, mark resolved.
Build comments/inbox.php — all open comments across disclosures.
Email notifications — admin gets emailed on new comment, auditor gets emailed on resolution.
Deliverable: Auditor invited → scoped login → threaded comments → resolution tracking.

Week 11 — Steps 7 & 8: Reporting Periods + Custom Branding
Part 1 — Multiple Reporting Periods
Migration 011_reporting_periods.sql — reporting_periods table (company_id, period, period_type, label, start_date, end_date, status, locked_by, locked_at).
Turn the header period picker into a dropdown showing label + status (open / locked / submitted).
Guard every write across phase3–phase6 with a period-status check. Locked = 403 + audit log entry.
Build periods/index.php — CRUD + lock/unlock (owner only).
Part 2 — Custom Branding
Migration 012_branding.sql — add brand_logo_path, brand_primary_color, brand_secondary_color, brand_font, brand_footer_text to companies table.
Build company/branding.php — logo upload, color pickers, live preview.
Update report/generate.php to apply brand fields to TCPDF header, headings, footer, fonts.
Deliverable: Multiple periods with locking + white-label PDFs.

Weeks 12–14 — Step 9: Testing, Security, Documentation
Week 12 — End-to-end testing
Run the full flow 3 times with 3 personas: real-estate firm, construction company, property manager. Each: create company → 3 subsidiaries → data for 2 periods → run DMA → invite auditor → resolve comments → generate branded consolidated PDF.
Load test: insert 10,000 synthetic audit log entries; check filter/pagination performance; add indexes where EXPLAIN shows table scans.
Week 13 — Security hardening
Permission boundary tests: log in as auditor, manipulate URLs to hit edit endpoints — must 403.
Cross-tenant tests: user from company A attempts to read/write company B data — must 403.
File upload fuzz: oversize files, wrong MIME, double extensions, malicious filenames, zip bombs.
SQL audit: grep entire codebase for string concatenation in queries. Everything must be prepared statements.
Backup/restore drill: restore last night's backup to staging, verify data integrity, time the operation.
Consider external pen test here (~€2,000–€4,000, German provider like SySS).
Week 14 — Documentation
User guide per feature with screenshots.
Admin guide (roles, permissions, lock/unlock, subsidiaries).
Auditor quick-start (1-page PDF).
DMA methodology explainer (doubles as sales tool).
Status page setup (Instatus, €20/month).
Security page on marketing site (hosting, encryption, last pen-test date, certifications in progress).
Deliverable: Production-ready Tier 2. Passes German SME procurement basics.

Parallel tracks (run alongside the weekly work)
These don't block features but must finish before you charge Tier 2 pricing:

Hosting migration — move from XAMPP to Hetzner Cloud (Germany). Do this in Week 4–6 during the subsidiary migration (natural break point).
GDPR/DPA — commission a bilingual DPA template (€800–€1,500, German law firm). Start in Week 1, have by Week 8.
Privacy policy + cookie policy — iubenda subscription (~€30/year), customize for your stack.
ISO 27001 prep — buy a policy template pack (Drata/Vanta/Sprinto ~€200–€500/month). Start collecting evidence from Week 1. Don't go for certification yet; just build the paper trail.
Billing — Paddle or Lemon Squeezy as Merchant of Record (handles EU VAT). Set up in Week 11.
What to do right now (next 7 days)
Tag current repo as v1.0-starter.
Set up the migrations folder + runner.
Add PHPMailer, CSRF helper, session hardening, login rate-limit.
Provision a Hetzner Cloud account (don't migrate yet — just have it ready).
Contact a German law firm for a DPA quote.
Sketch the user_company_roles schema on paper and confirm it handles auditor-scoped-to-one-period cleanly before you write migration 002.
Once Week 0 is done, start Step 1 with confidence.

Want me to save this plan as a project memory, or write it to a file in the repo (e.g. docs/tier2-plan.md)? I won't do either without you asking.