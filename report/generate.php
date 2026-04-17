<?php
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';
require_once '../includes/helpers.php';

$pageTitle = 'Generate ESG Report';

// Try to find TCPDF
$tcpdfPaths = [
    __DIR__ . '/../vendor/autoload.php',
    'g:/xamp/phpMyAdmin/vendor/autoload.php',
    'g:/xamp/htdocs/tcpdf/tcpdf.php',
];
$tcpdfAvailable = false;
foreach ($tcpdfPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $tcpdfAvailable = class_exists('TCPDF');
        if ($tcpdfAvailable) break;
    }
}

// Also try TCPDF standalone
if (!$tcpdfAvailable) {
    $standalonePaths = [
        'g:/xamp/phpMyAdmin/vendor/tecnickcom/tcpdf/tcpdf.php',
    ];
    foreach ($standalonePaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $tcpdfAvailable = class_exists('TCPDF');
            if ($tcpdfAvailable) break;
        }
    }
}

define('PDF_FONT_FAMILY', 'helvetica');
define('PDF_FONT_BODY',   12);
define('PDF_FONT_H1',     18);
define('PDF_FONT_H2',     14);
define('PDF_FONT_SMALL',  10);

$cid     = company_id();
$period  = sanitize($_POST['period'] ?? $_GET['period'] ?? '');
$action  = $_POST['action'] ?? $_GET['action'] ?? 'form';

// Fetch company
$stmt = $pdo->prepare('SELECT * FROM companies WHERE id = :id AND deleted_at IS NULL');
$stmt->execute([':id' => $cid]);
$company = $stmt->fetch();

// Fetch emissions
$stmt = $pdo->prepare('SELECT scope, ROUND(SUM(tco2e_calculated), 4) AS total FROM emission_records WHERE company_id = :id GROUP BY scope');
$stmt->execute([':id' => $cid]);
$emissions = [];
foreach ($stmt->fetchAll() as $r) {
    $emissions[$r['scope']] = $r['total'];
}

function fetchReport(PDO $db, string $table, string $cid, string $period) {
    $s = $db->prepare("SELECT * FROM {$table} WHERE company_id = :c AND reporting_period = :p LIMIT 1");
    $s->execute([':c' => $cid, ':p' => $period]);
    return $s->fetch();
}

// Helper: label-value row for PDF
function pdfRow(TCPDF $pdf, string $label, string $value, bool $multiline = false): void {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(58, 6, $label . ':', 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    if ($multiline) {
        $pdf->MultiCell(0, 6, $value !== '' ? $value : '—', 0, 'L');
    } else {
        $pdf->Cell(0, 6, $value !== '' ? $value : '—', 0, 1);
    }
}

// Helper: convert database enum values to readable labels
function formatEnum(string $value): string {
    $map = [
        // DNSH Status
        'ALL_OBJECTIVES_PASSED'    => 'All Objectives Passed',
        'SOME_OBJECTIVES_NOT_MET'  => 'Some Objectives Not Met',
        'ASSESSMENT_IN_PROGRESS'   => 'Assessment In Progress',
        // Social Safeguards
        'FULL_COMPLIANCE'          => 'Full Compliance',
        'NON_COMPLIANCE'           => 'Non-Compliance',
        'PARTIAL_REMEDIATION'      => 'Partial Remediation',
        // Assurance Level
        'limited'                  => 'Limited Assurance',
        'reasonable'               => 'Reasonable Assurance',
        // Status
        'DRAFT'                    => 'Draft',
        'APPROVED'                 => 'Approved',
        'PUBLISHED'                => 'Published',
    ];
    return $map[$value] ?? ucwords(strtolower(str_replace('_', ' ', $value)));
}

// Generate PDF if requested
if ($action === 'generate' && $period !== '') {
    if (!$tcpdfAvailable) {
        // Redirect back with error
        header('Location: /esg-report-test/report/generate.php?error=tcpdf_missing');
        exit;
    }

    $env       = fetchReport($pdo, 'environmental_topics', $cid, $period);
    $social    = fetchReport($pdo, 'social_topics', $cid, $period);
    $gov       = fetchReport($pdo, 's_governance', $cid, $period);
    $tax       = fetchReport($pdo, 'eu_taxonomy', $cid, $period);
    $assurance = fetchReport($pdo, 'assurance', $cid, $period);

    // Try ESRS2 - table may not exist yet
    $esrs2 = null;
    try {
        $esrs2 = fetchReport($pdo, 'esrs2_general_disclosures', $cid, $period);
    } catch (PDOException $e) {
        // Table doesn't exist yet — skip
    }

    // Period date helpers — derive human-readable labels from YYYY-MM period string
    $periodDate      = DateTime::createFromFormat('Y-m', $period);
    $periodEndDate   = (clone $periodDate)->modify('last day of this month');
    $periodLabel     = $periodEndDate->format('d F Y');    // e.g. "31 March 2026"
    $periodYear      = $periodEndDate->format('Y');        // e.g. "2026"
    $periodMonthYear = $periodDate->format('F Y');         // e.g. "March 2026"

    // Scope 3 data (table may not exist yet)
    $scope3Entries = [];
    $scope3Total   = 0;
    try {
        $s3stmt = $pdo->prepare('
            SELECT * FROM scope3_activities
            WHERE company_id = :cid AND reporting_period = :p
            ORDER BY category ASC
        ');
        $s3stmt->execute([':cid' => $cid, ':p' => $period]);
        $scope3Entries = $s3stmt->fetchAll();
        $scope3Total   = array_sum(array_column($scope3Entries, 'tco2e_estimated'));
    } catch (PDOException $e) {
        // Table doesn't exist yet — skip silently
    }

    // Init TCPDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('ESG Reporting Platform');
    $pdf->SetAuthor($company['name'] ?? 'ESG Platform');
    $pdf->SetTitle('ESG Report - ' . $period);
    $pdf->SetSubject('Environmental, Social and Governance Report');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(20, 20, 20);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    // ========== COVER PAGE ==========
    $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_H1);
    $pdf->Ln(20);
    $pdf->SetFillColor(16, 185, 129);  // emerald-500
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 15, 'ESG SUSTAINABILITY REPORT', 0, 1, 'C', true);
    $pdf->Ln(5);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_H2);
    $pdf->Cell(0, 10, $company['name'] ?? '', 0, 1, 'C');
    $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
    $pdf->Cell(0, 8, 'Reporting Period: ' . $periodMonthYear, 0, 1, 'C');
    $pdf->Cell(0, 8, 'Generated: ' . date('d F Y'), 0, 1, 'C');
    $pdf->Ln(10);

    // ========== SECTION 1: COMPANY PROFILE ==========
    $pdf->SetFillColor(240, 253, 244); // green-50
    $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_H2);
    $pdf->Cell(0, 10, '1. Company Profile', 0, 1, 'L', true);
    $pdf->Ln(2);
    $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
    $pdf->Cell(0, 7, 'Company Name: ' . ($company['name'] ?? 'N/A'), 0, 1);
    $pdf->Cell(0, 7, 'Industry: ' . ($company['industry'] ?? 'N/A'), 0, 1);
    $pdf->Cell(0, 7, 'Country of Registration: ' . ($company['country_of_registration'] ?? 'N/A'), 0, 1);
    if (!empty($company['registration_number'])) {
        $pdf->Cell(0, 7, 'Registration Number: ' . $company['registration_number'], 0, 1);
    }
    if (!empty($company['website'])) {
        $pdf->Cell(0, 7, 'Website: ' . $company['website'], 0, 1);
    }
    $pdf->Ln(5);

    // ========== SECTION 2: GHG EMISSIONS ==========
    $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_H2);
    $pdf->Cell(0, 10, '2. GHG Emissions Summary', 0, 1, 'L', true);
    $pdf->Ln(2);
    $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
    $scope1 = (float)($emissions['Scope 1'] ?? 0);
    $scope2 = (float)($emissions['Scope 2 Location-Based'] ?? 0) + (float)($emissions['Scope 2 Market-Based'] ?? 0);
    $total  = $scope1 + $scope2;
    $pdf->Cell(0, 7, 'Scope 1 (Direct Emissions): ' . number_format($scope1, 4) . ' tCO2e', 0, 1);
    $pdf->Cell(0, 7, 'Scope 2 (Location-Based): ' . number_format($scope2, 4) . ' tCO2e', 0, 1);
    $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_BODY);
    $pdf->Cell(0, 7, 'Total Emissions (Scope 1+2): ' . number_format($total, 4) . ' tCO2e', 0, 1);
    $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
    $pdf->Cell(0, 7, 'Scope 3 (Value Chain, Estimated): ' .
        (!empty($scope3Entries)
            ? number_format($scope3Total, 4) . ' tCO2e (' . count($scope3Entries) . ' categories)'
            : 'Not reported this period'),
        0, 1);
    $pdf->Ln(3);
    $pdf->Cell(0, 6, 'Calculation method: GHG Protocol Corporate Standard, DEFRA 2024 emission factors.', 0, 1);
    $pdf->Ln(4);

    // Fuel activities breakdown table
    $stmt = $pdo->prepare('
        SELECT fa.date, s.name AS site_name, fa.fuel_type, fa.volume, fa.unit,
               er.tco2e_calculated, ef.source
        FROM fuel_activities fa
        JOIN sites s ON fa.site_id = s.id
        LEFT JOIN emission_records er ON er.fuel_activity_id = fa.id
        LEFT JOIN emission_factors ef ON er.emission_factor_id = ef.id
        WHERE s.company_id = :cid
        ORDER BY fa.date DESC
        LIMIT 20
    ');
    $stmt->execute([':cid' => $cid]);
    $fuelRows = $stmt->fetchAll();

    $energyStmt = $pdo->prepare('
        SELECT ea.date, s.name AS site_name, ea.energy_type, ea.consumption, ea.unit,
               er.tco2e_calculated, ef.region AS ef_region
        FROM energy_activities ea
        JOIN sites s ON ea.site_id = s.id
        LEFT JOIN emission_records er ON er.energy_activity_id = ea.id
        LEFT JOIN emission_factors ef ON er.emission_factor_id = ef.id
        WHERE s.company_id = :cid
        ORDER BY ea.date DESC
        LIMIT 20
    ');
    $energyStmt->execute([':cid' => $cid]);
    $energyRows = $energyStmt->fetchAll();

    if (!empty($fuelRows)) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'Scope 1 — Fuel Activities Detail:', 0, 1);
        $pdf->Ln(1);
        $pdf->SetFillColor(5, 78, 59);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(22, 6, 'Date',      1, 0, 'C', true);
        $pdf->Cell(38, 6, 'Site',      1, 0, 'C', true);
        $pdf->Cell(30, 6, 'Fuel Type', 1, 0, 'C', true);
        $pdf->Cell(22, 6, 'Volume',    1, 0, 'C', true);
        $pdf->Cell(14, 6, 'Unit',      1, 0, 'C', true);
        $pdf->Cell(26, 6, 'tCO2e',     1, 0, 'C', true);
        $pdf->Cell(18, 6, 'Source',    1, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 8);
        foreach ($fuelRows as $i => $r) {
            $bg = ($i % 2 === 0);
            $pdf->SetFillColor(245, 250, 247);
            $pdf->Cell(22, 5, $r['date'],                                          1, 0, 'C', $bg);
            $pdf->Cell(38, 5, $r['site_name'],                                     1, 0, 'L', $bg);
            $pdf->Cell(30, 5, ucwords(str_replace('_', ' ', $r['fuel_type'])),     1, 0, 'L', $bg);
            $pdf->Cell(22, 5, number_format((float)$r['volume'], 2),               1, 0, 'R', $bg);
            $pdf->Cell(14, 5, $r['unit'],                                          1, 0, 'C', $bg);
            $pdf->Cell(26, 5, $r['tco2e_calculated'] !== null
                              ? number_format((float)$r['tco2e_calculated'], 4)
                              : '—',                                               1, 0, 'R', $bg);
            $pdf->Cell(18, 5, $r['source'] ?? '—',                                1, 1, 'C', $bg);
        }
        $pdf->Ln(4);
    }

    if (!empty($energyRows)) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'Scope 2 — Energy Activities Detail:', 0, 1);
        $pdf->Ln(1);
        $pdf->SetFillColor(24, 95, 165);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(22, 6, 'Date',        1, 0, 'C', true);
        $pdf->Cell(38, 6, 'Site',        1, 0, 'C', true);
        $pdf->Cell(35, 6, 'Energy Type', 1, 0, 'C', true);
        $pdf->Cell(22, 6, 'Consumption', 1, 0, 'C', true);
        $pdf->Cell(14, 6, 'Unit',        1, 0, 'C', true);
        $pdf->Cell(26, 6, 'tCO2e',       1, 0, 'C', true);
        $pdf->Cell(13, 6, 'Region',      1, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 8);
        foreach ($energyRows as $i => $r) {
            $bg = ($i % 2 === 0);
            $pdf->SetFillColor(235, 245, 255);
            $pdf->Cell(22, 5, date('Y-m', strtotime($r['date'])),                      1, 0, 'C', $bg);
            $pdf->Cell(38, 5, $r['site_name'],                                         1, 0, 'L', $bg);
            $pdf->Cell(35, 5, ucwords(str_replace('_', ' ', $r['energy_type'])),       1, 0, 'L', $bg);
            $pdf->Cell(22, 5, number_format((float)$r['consumption'], 2),              1, 0, 'R', $bg);
            $pdf->Cell(14, 5, $r['unit'],                                              1, 0, 'C', $bg);
            $pdf->Cell(26, 5, $r['tco2e_calculated'] !== null
                              ? number_format((float)$r['tco2e_calculated'], 4)
                              : '—',                                                   1, 0, 'R', $bg);
            $pdf->Cell(13, 5, $r['ef_region'] ?? '—',                                 1, 1, 'C', $bg);
        }
        $pdf->Ln(4);
    }

    // ========== SECTION 2B: SCOPE 3 VALUE CHAIN ==========
    if (!empty($scope3Entries)) {
        $pdf->AddPage();
        $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_H2);
        $pdf->SetFillColor(88, 28, 135);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(0, 10, '2B. Scope 3 — Value Chain Emissions', 0, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(2);
        $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
        $pdf->MultiCell(0, 7,
            'The following Scope 3 categories were assessed for the reporting period ' . $periodMonthYear . '. ' .
            'Scope 3 covers all indirect emissions in the upstream and downstream value chain, ' .
            'in accordance with the GHG Protocol Corporate Value Chain (Scope 3) Standard.',
            0, 'L');
        $pdf->Ln(3);

        $s3cats = [
            'Cat 1'  => 'Purchased Goods & Services',
            'Cat 2'  => 'Capital Goods',
            'Cat 3'  => 'Fuel & Energy Related Activities',
            'Cat 4'  => 'Upstream Transportation & Distribution',
            'Cat 5'  => 'Waste Generated in Operations',
            'Cat 6'  => 'Business Travel',
            'Cat 7'  => 'Employee Commuting',
            'Cat 8'  => 'Upstream Leased Assets',
            'Cat 9'  => 'Downstream Transportation',
            'Cat 10' => 'Processing of Sold Products',
            'Cat 11' => 'Use of Sold Products',
            'Cat 12' => 'End-of-Life Treatment of Sold Products',
            'Cat 13' => 'Downstream Leased Assets',
            'Cat 14' => 'Franchises',
            'Cat 15' => 'Investments',
        ];

        // Table header
        $pdf->SetFillColor(88, 28, 135);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(22, 6, 'Category',    1, 0, 'C', true);
        $pdf->Cell(70, 6, 'Description', 1, 0, 'C', true);
        $pdf->Cell(40, 6, 'Method',      1, 0, 'C', true);
        $pdf->Cell(22, 6, 'Quality',     1, 0, 'C', true);
        $pdf->Cell(16, 6, 'tCO2e',       1, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 8);

        foreach ($scope3Entries as $i => $e) {
            $bg = ($i % 2 === 0);
            $pdf->SetFillColor(245, 240, 255);
            $pdf->Cell(22, 5, $e['category'],                                      1, 0, 'C', $bg);
            $pdf->Cell(70, 5, substr($e['description'] ?? '—', 0, 65),            1, 0, 'L', $bg);
            $pdf->Cell(40, 5, substr($e['estimation_method'] ?? '—', 0, 35),      1, 0, 'L', $bg);
            $pdf->Cell(22, 5, ucfirst($e['data_quality']),                         1, 0, 'C', $bg);
            $pdf->Cell(16, 5, $e['tco2e_estimated'] !== null
                              ? number_format((float)$e['tco2e_estimated'], 2) : '—',
                              1, 1, 'R', $bg);
        }
        // Total row
        $pdf->SetFillColor(220, 200, 255);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(154, 6, 'Total Scope 3 Estimated Emissions', 1, 0, 'R', true);
        $pdf->Cell(16,  6, number_format($scope3Total, 2), 1, 1, 'R', true);
        $pdf->Ln(3);

        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->MultiCell(0, 5,
            'Note: Scope 3 figures are estimates based on available activity data and spend-based ' .
            'calculations. These have not been subject to third-party verification unless explicitly ' .
            'stated in the assurance section.',
            0, 'L');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(5);
    }

    // ========== SECTION 3: ESRS 2 ==========
    if ($esrs2) {
        $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_H2);
        $pdf->Cell(0, 10, '3. ESRS 2 - General Disclosures', 0, 1, 'L', true);
        $pdf->Ln(2);
        $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
        if (!empty($esrs2['consolidation_scope'])) {
            $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_BODY);
            $pdf->Cell(0, 7, 'Consolidation Scope:', 0, 1);
            $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
            $pdf->MultiCell(0, 7, $esrs2['consolidation_scope'], 0, 'L');
        }
        if (!empty($esrs2['board_role_in_sustainability'])) {
            $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_BODY);
            $pdf->Cell(0, 7, 'Board Role in Sustainability:', 0, 1);
            $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
            $pdf->MultiCell(0, 7, $esrs2['board_role_in_sustainability'], 0, 'L');
        }
        if ($esrs2['esg_integration_in_remuneration'] !== null) {
            $pdf->Cell(0, 7, 'ESG Integration in Remuneration: ' . $esrs2['esg_integration_in_remuneration'] . '%', 0, 1);
        }
        $pdf->Ln(5);
    }

    // ========== SECTION 4: ENVIRONMENTAL ==========
    if ($env) {
        $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_H2);
        $pdf->Cell(0, 10, '4. Environmental Topics (ESRS E1-E5)', 0, 1, 'L', true);
        $pdf->Ln(2);
        $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);

        if ($env['e1_material']) {
            $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_BODY);
            $pdf->Cell(0, 7, 'E1 - Climate Change:', 0, 1);
            $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
            if (!empty($env['e1_climate_policy'])) {
                $pdf->MultiCell(0, 7, 'Climate Policy: ' . $env['e1_climate_policy'], 0, 'L');
            }
            if (!empty($env['e1_reduction_target'])) {
                $pdf->Cell(0, 7, 'Reduction Target: ' . $env['e1_reduction_target'], 0, 1);
            }
        }
        if ($env['e2_material']) {
            $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_BODY);
            $pdf->Cell(0, 7, 'E2 - Pollution:', 0, 1);
            $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
            $pdf->Cell(0, 7, 'NOx: ' . ($env['e2_nox_t_per_year'] ?? 'N/A') . ' t/yr | SOx: ' . ($env['e2_sox_t_per_year'] ?? 'N/A') . ' t/yr', 0, 1);
        }
        if ($env['e3_material']) {
            $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_BODY);
            $pdf->Cell(0, 7, 'E3 - Water:', 0, 1);
            $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
            $pdf->Cell(0, 7, 'Water Withdrawal: ' . ($env['e3_water_withdrawal_m3'] ?? 'N/A') . ' m³ | Recycling Rate: ' . ($env['e3_water_recycling_rate_pct'] ?? 'N/A') . '%', 0, 1);
        }
        // E4 — always show
        $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_BODY);
        $pdf->Cell(0, 7, 'E4 — Biodiversity & Ecosystems:', 0, 1);
        $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
        if (!$env['e4_material']) {
            $pdf->SetTextColor(150, 150, 150);
            $pdf->MultiCell(0, 5,
                'Not identified as a material topic for this reporting period, based on the outcome ' .
                'of a Double Materiality Assessment conducted in accordance with ESRS 1 requirements. ' .
                'The assessment evaluated both financial materiality (impact on company value) and ' .
                'impact materiality (the company\'s effects on people and environment).',
                0, 'L');
            $pdf->SetTextColor(0, 0, 0);
        } else {
            $pdf->MultiCell(0, 7, $env['e4_protected_areas_impact'] ?? '', 0, 'L');
        }
        $pdf->Ln(2);

        // E5 — always show
        $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_BODY);
        $pdf->Cell(0, 7, 'E5 — Resource Use & Circular Economy:', 0, 1);
        $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
        if (!$env['e5_material']) {
            $pdf->SetTextColor(150, 150, 150);
            $pdf->MultiCell(0, 5,
                'Not identified as a material topic for this reporting period, based on the outcome ' .
                'of a Double Materiality Assessment conducted in accordance with ESRS 1 requirements. ' .
                'The assessment evaluated both financial materiality (impact on company value) and ' .
                'impact materiality (the company\'s effects on people and environment).',
                0, 'L');
            $pdf->SetTextColor(0, 0, 0);
        } else {
            $pdf->Cell(0, 7, 'Recycling Rate: ' . ($env['e5_recycling_rate_pct'] ?? 'N/A') . '% | Recycled Inputs: ' . ($env['e5_recycled_input_materials_pct'] ?? 'N/A') . '%', 0, 1);
        }
        $pdf->Ln(5);
    }

    // ========== SECTION 5: SOCIAL ==========
    if ($social) {
        $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_H2);
        $pdf->Cell(0, 10, '5. Social Topics (ESRS S1-S4)', 0, 1, 'L', true);
        $pdf->Ln(2);
        $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);

        if ($social['s1_material']) {
            $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_BODY);
            $pdf->Cell(0, 7, 'S1 - Own Workforce:', 0, 1);
            $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
            $pdf->Cell(0, 7, 'Avg Training Hours/Employee: ' . ($social['s1_training_hours_per_employee'] ?? 'N/A'), 0, 1);
            if (!empty($social['s1_health_and_safety'])) {
                $pdf->MultiCell(0, 7, 'H&S: ' . $social['s1_health_and_safety'], 0, 'L');
            }
        }
        if ($social['s2_material']) {
            $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_BODY);
            $pdf->Cell(0, 7, 'S2 - Value Chain:', 0, 1);
            $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
            $pdf->Cell(0, 7, 'Suppliers Audited: ' . ($social['s2_pct_suppliers_audited'] ?? 'N/A') . '%', 0, 1);
        }
        // S3 — always show
        $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_BODY);
        $pdf->Cell(0, 7, 'S3 — Affected Communities:', 0, 1);
        $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
        if (!$social['s3_material']) {
            $pdf->SetTextColor(150, 150, 150);
            $pdf->MultiCell(0, 5,
                'Not identified as a material topic for this reporting period, based on the outcome ' .
                'of a Double Materiality Assessment conducted in accordance with ESRS 1 requirements. ' .
                'The assessment evaluated both financial materiality (impact on company value) and ' .
                'impact materiality (the company\'s effects on people and environment).',
                0, 'L');
            $pdf->SetTextColor(0, 0, 0);
        } else {
            $pdf->MultiCell(0, 7, $social['s3_community_engagement'] ?? '', 0, 'L');
            if (!empty($social['s3_complaints_and_outcomes'])) {
                $pdf->MultiCell(0, 7, 'Complaints & Outcomes: ' . $social['s3_complaints_and_outcomes'], 0, 'L');
            }
        }
        if ($social['s4_material']) {
            $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_BODY);
            $pdf->Cell(0, 7, 'S4 - Consumers:', 0, 1);
            $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
            $pdf->Cell(0, 7, 'Product Safety Incidents: ' . ($social['s4_product_safety_incidents'] ?? 'N/A'), 0, 1);
        }
        $pdf->Ln(5);
    }

    // ========== SECTION 6: GOVERNANCE ==========
    if ($gov) {
        $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_H2);
        $pdf->Cell(0, 10, '6. Governance (ESRS G1)', 0, 1, 'L', true);
        $pdf->Ln(2);
        $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
        $pdf->Cell(0, 7, 'Board Gender Diversity: ' . ($gov['g1_gender_diversity_pct'] ?? 'N/A') . '%', 0, 1);
        if (!empty($gov['g1_esg_oversight'])) {
            $pdf->MultiCell(0, 7, 'ESG Oversight: ' . $gov['g1_esg_oversight'], 0, 'L');
        }
        if (!empty($gov['g1_anti_corruption_policies'])) {
            $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_BODY);
            $pdf->Cell(0, 7, 'Anti-Corruption Policy:', 0, 1);
            $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
            $pdf->MultiCell(0, 7, $gov['g1_anti_corruption_policies'], 0, 'L');
        }
        if (!empty($gov['g1_related_party_controls'])) {
            $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_BODY);
            $pdf->Cell(0, 7, 'Related-Party Controls:', 0, 1);
            $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
            $pdf->MultiCell(0, 7, $gov['g1_related_party_controls'], 0, 'L');
        }
        if (!empty($gov['g1_board_composition_independence'])) {
            $pdf->MultiCell(0, 7, 'Board Composition: ' . $gov['g1_board_composition_independence'], 0, 'L');
        }
        $pdf->Ln(5);
    }

    // ========== SECTION 7: EU TAXONOMY ==========
    if ($tax) {
        $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_H2);
        $pdf->Cell(0, 10, '7. EU Taxonomy Alignment', 0, 1, 'L', true);
        $pdf->Ln(2);
        $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
        $pdf->Cell(0, 7, 'Eligible Revenue: ' . ($tax['taxonomy_eligible_revenue_pct'] ?? 'N/A') . '%  |  Aligned Revenue: ' . ($tax['taxonomy_aligned_revenue_pct'] ?? 'N/A') . '%', 0, 1);
        $pdf->Cell(0, 7, 'Eligible CapEx: ' . ($tax['taxonomy_eligible_capex_pct'] ?? 'N/A') . '%  |  Aligned CapEx: ' . ($tax['taxonomy_aligned_capex_pct'] ?? 'N/A') . '%', 0, 1);
        $pdf->Cell(0, 7, 'Aligned OpEx: ' . ($tax['taxonomy_aligned_opex_pct'] ?? 'N/A') . '%', 0, 1);
        $pdf->Cell(0, 7, 'DNSH Status: ' . formatEnum($tax['dnsh_status'] ?? ''), 0, 1);
        $pdf->Cell(0, 7, 'Social Safeguards: ' . formatEnum($tax['social_safeguards_status'] ?? ''), 0, 1);
        $pdf->Ln(5);
    }

    // ========== SECTION 8: ASSURANCE ==========
    if ($assurance) {
        $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_H2);
        $pdf->Cell(0, 10, '8. Assurance & Audit', 0, 1, 'L', true);
        $pdf->Ln(2);
        $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
        $pdf->Cell(0, 7, 'Provider: ' . ($assurance['provider'] ?? 'N/A'), 0, 1);
        $pdf->Cell(0, 7, 'Level: ' . formatEnum($assurance['level'] ?? ''), 0, 1);
        $pdf->Cell(0, 7, 'Standard: ' . ($assurance['standard'] ?? 'N/A'), 0, 1);
        if (!empty($assurance['report_date'])) {
            $pdf->Cell(0, 7, 'Report Date: ' . date('d F Y', strtotime($assurance['report_date'])), 0, 1);
        }
        if (!empty($assurance['conclusion'])) {
            $pdf->SetFont(PDF_FONT_FAMILY, 'B', PDF_FONT_BODY);
            $pdf->Cell(0, 7, 'Conclusion:', 0, 1);
            $pdf->SetFont(PDF_FONT_FAMILY, '', PDF_FONT_BODY);
            $pdf->MultiCell(0, 7, $assurance['conclusion'], 0, 'L');
            $pdf->Ln(2);
            $pdf->SetFont('helvetica', 'I', 9);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 5, 'Period covered by this assurance engagement ends: ' . $periodLabel, 0, 1);
            $pdf->SetTextColor(0, 0, 0);
        }
        $pdf->Ln(5);
    }

    // ========== FOOTER ==========
    $pdf->SetFont(PDF_FONT_FAMILY, 'I', PDF_FONT_SMALL);
    $pdf->Cell(0, 7, 'This report was generated by the ESG Reporting Platform on ' . date('d F Y H:i') . ' UTC', 0, 1, 'C');
    $pdf->Cell(0, 7, 'Reporting Period: ' . $periodMonthYear . ' | Company ID: ' . $cid, 0, 1, 'C');

    $filename = 'ESG_Report_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $company['name'] ?? 'Company') . '_' . $period . '.pdf';
    $pdf->Output($filename, 'D');
    exit;
}

// Show the form page
require_once '../includes/header.php';

// Get available periods from data
$stmt = $pdo->prepare('
    SELECT DISTINCT DATE_FORMAT(date_calculated, \'%Y-%m\') AS period
    FROM emission_records
    WHERE company_id = :cid
    ORDER BY period DESC
');
$stmt->execute([':cid' => $cid]);
$availablePeriods = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="space-y-6 max-w-2xl">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Generate ESG Report</h2>
        <p class="text-gray-500 text-base mt-1">Download a comprehensive PDF ESG report for any reporting period</p>
    </div>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'tcpdf_missing'): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="flex items-start space-x-3">
            <svg class="w-5 h-5 text-red-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <p class="text-sm font-semibold text-red-800">TCPDF Library Not Found</p>
                <p class="text-sm text-red-700 mt-1">TCPDF is required to generate PDF reports. Please run <code class="bg-red-100 px-1 rounded">composer require tecnickcom/tcpdf</code> in the project root, or install TCPDF manually.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$tcpdfAvailable): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
        <div class="flex items-start space-x-3">
            <svg class="w-5 h-5 text-amber-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <p class="text-sm font-semibold text-amber-800">TCPDF Not Available</p>
                <p class="text-sm text-amber-700 mt-1">
                    PDF generation requires TCPDF. Install it via Composer:
                    <code class="bg-amber-100 px-1.5 py-0.5 rounded font-mono text-xs">composer require tecnickcom/tcpdf</code>
                </p>
                <p class="text-sm text-amber-700 mt-1">Run this command in: <code class="bg-amber-100 px-1.5 py-0.5 rounded font-mono text-xs">g:\xamp\htdocs\esg-report-test\</code></p>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-center space-x-2">
        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p class="text-sm text-emerald-700 font-medium">TCPDF is available. PDF generation is ready.</p>
    </div>
    <?php endif; ?>

    <!-- Report Configuration -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <h3 class="text-base font-semibold text-gray-800">Report Configuration</h3>
        </div>
        <div class="p-6">
            <form method="POST" class="space-y-5">
                <input type="hidden" name="action" value="generate">

                <!-- Reporting Period -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reporting Period <span class="text-red-500">*</span></label>
                    <input type="month" name="period"
                           value="<?= htmlspecialchars($period ?: date('Y-m'), ENT_QUOTES, 'UTF-8') ?>"
                           class="px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                           required>
                    <?php if (!empty($availablePeriods)): ?>
                    <p class="text-xs text-gray-400 mt-1">Periods with emission data: <?= implode(', ', array_map('htmlspecialchars', array_slice($availablePeriods, 0, 6))) ?></p>
                    <?php endif; ?>
                </div>

                <!-- Report Contents Summary -->
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-100">
                    <p class="text-sm font-medium text-gray-700 mb-2">Report will include:</p>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li class="flex items-center space-x-2"><svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg><span>Company profile</span></li>
                        <li class="flex items-center space-x-2"><svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg><span>GHG emissions summary (all recorded data)</span></li>
                        <li class="flex items-center space-x-2"><svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg><span>ESRS 2 general disclosures</span></li>
                        <li class="flex items-center space-x-2"><svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg><span>Environmental (E1–E5), Social (S1–S4), Governance (G1)</span></li>
                        <li class="flex items-center space-x-2"><svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg><span>EU Taxonomy alignment</span></li>
                        <li class="flex items-center space-x-2"><svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg><span>Assurance & audit information</span></li>
                    </ul>
                    <p class="text-xs text-gray-400 mt-3">Sections without data for the selected period will be omitted automatically.</p>
                </div>

                <button type="submit" <?= !$tcpdfAvailable ? 'disabled' : '' ?>
                        class="<?= $tcpdfAvailable ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-gray-400 cursor-not-allowed' ?> text-white font-semibold py-3 px-8 rounded-lg text-base transition flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span>Download PDF Report</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Emissions Summary for selected company -->
    <?php if (!empty($emissions)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <h3 class="text-base font-semibold text-gray-800">Current Emissions Data (All Time)</h3>
        </div>
        <div class="p-6 grid grid-cols-3 gap-4">
            <?php
            $s1t = (float)($emissions['Scope 1'] ?? 0);
            $s2t = (float)($emissions['Scope 2 Location-Based'] ?? 0) + (float)($emissions['Scope 2 Market-Based'] ?? 0);
            $tot = $s1t + $s2t;
            ?>
            <div class="text-center bg-green-50 rounded-lg p-4 border border-green-100">
                <p class="text-xs text-green-600 font-medium">Scope 1</p>
                <p class="text-xl font-bold text-green-700"><?= number_format($s1t, 4) ?></p>
                <p class="text-xs text-green-500">tCO2e</p>
            </div>
            <div class="text-center bg-blue-50 rounded-lg p-4 border border-blue-100">
                <p class="text-xs text-blue-600 font-medium">Scope 2</p>
                <p class="text-xl font-bold text-blue-700"><?= number_format($s2t, 4) ?></p>
                <p class="text-xs text-blue-500">tCO2e</p>
            </div>
            <div class="text-center bg-gray-50 rounded-lg p-4 border border-gray-200">
                <p class="text-xs text-gray-600 font-medium">Total</p>
                <p class="text-xl font-bold text-gray-700"><?= number_format($tot, 4) ?></p>
                <p class="text-xs text-gray-500">tCO2e</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
