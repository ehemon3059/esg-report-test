<?php
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';
require_once '../includes/helpers.php';

$pageTitle = 'Environmental Topics (ESRS E1–E5)';
$success   = '';
$error     = '';

$selectedPeriod = sanitize($_POST['reporting_period'] ?? $_GET['period'] ?? date('Y-m'));

// Load existing record
$stmt = $pdo->prepare('SELECT * FROM environmental_topics WHERE company_id = :cid AND reporting_period = :period');
$stmt->execute([':cid' => company_id(), ':period' => $selectedPeriod]);
$existing = $stmt->fetch();

// Emission totals for E1 section
$stmt = $pdo->prepare('
    SELECT scope, ROUND(SUM(tco2e_calculated), 4) AS total
    FROM emission_records WHERE company_id = :cid GROUP BY scope
');
$stmt->execute([':cid' => company_id()]);
$emTotals = [];
foreach ($stmt->fetchAll() as $r) { $emTotals[$r['scope']] = $r['total']; }
$s1 = (float)($emTotals['Scope 1'] ?? 0);
$s2 = (float)($emTotals['Scope 2 Location-Based'] ?? 0) + (float)($emTotals['Scope 2 Market-Based'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $e1_material   = isset($_POST['e1_material']) ? 1 : 0;
    $e2_material   = isset($_POST['e2_material']) ? 1 : 0;
    $e3_material   = isset($_POST['e3_material']) ? 1 : 0;
    $e4_material   = isset($_POST['e4_material']) ? 1 : 0;
    $e5_material   = isset($_POST['e5_material']) ? 1 : 0;

    $data = [
        'e1_material'              => $e1_material,
        'e1_climate_policy'        => trim($_POST['e1_climate_policy']        ?? ''),
        'e1_reduction_target'      => trim($_POST['e1_reduction_target']       ?? ''),
        'e2_material'              => $e2_material,
        'e2_nox_t_per_year'        => $_POST['e2_nox_t_per_year']        !== '' ? (float)$_POST['e2_nox_t_per_year']        : null,
        'e2_sox_t_per_year'        => $_POST['e2_sox_t_per_year']        !== '' ? (float)$_POST['e2_sox_t_per_year']        : null,
        'e3_material'              => $e3_material,
        'e3_water_withdrawal_m3'   => $_POST['e3_water_withdrawal_m3']   !== '' ? (float)$_POST['e3_water_withdrawal_m3']   : null,
        'e3_water_recycling_rate_pct' => $_POST['e3_water_recycling_rate_pct'] !== '' ? (int)$_POST['e3_water_recycling_rate_pct'] : null,
        'e4_material'              => $e4_material,
        'e4_protected_areas_impact' => trim($_POST['e4_protected_areas_impact'] ?? ''),
        'e5_material'              => $e5_material,
        'e5_recycling_rate_pct'            => $_POST['e5_recycling_rate_pct']            !== '' ? (int)$_POST['e5_recycling_rate_pct']            : null,
        'e5_recycled_input_materials_pct'  => $_POST['e5_recycled_input_materials_pct']  !== '' ? (int)$_POST['e5_recycled_input_materials_pct']  : null,
    ];

    if ($existing) {
        $stmt = $pdo->prepare('
            UPDATE environmental_topics SET
                e1_material=:e1_material, e1_climate_policy=:e1_climate_policy, e1_reduction_target=:e1_reduction_target,
                e2_material=:e2_material, e2_nox_t_per_year=:e2_nox_t_per_year, e2_sox_t_per_year=:e2_sox_t_per_year,
                e3_material=:e3_material, e3_water_withdrawal_m3=:e3_water_withdrawal_m3, e3_water_recycling_rate_pct=:e3_water_recycling_rate_pct,
                e4_material=:e4_material, e4_protected_areas_impact=:e4_protected_areas_impact,
                e5_material=:e5_material, e5_recycling_rate_pct=:e5_recycling_rate_pct, e5_recycled_input_materials_pct=:e5_recycled_input_materials_pct,
                updated_by=:uid, updated_at=NOW()
            WHERE id=:id AND company_id=:cid
        ');
        $stmt->execute(array_merge($data, [':uid' => user_id(), ':id' => $existing['id'], ':cid' => company_id()]));
    } else {
        $id = uuid();
        $stmt = $pdo->prepare('
            INSERT INTO environmental_topics
                (id, company_id, reporting_period, status,
                 e1_material, e1_climate_policy, e1_reduction_target,
                 e2_material, e2_nox_t_per_year, e2_sox_t_per_year,
                 e3_material, e3_water_withdrawal_m3, e3_water_recycling_rate_pct,
                 e4_material, e4_protected_areas_impact,
                 e5_material, e5_recycling_rate_pct, e5_recycled_input_materials_pct,
                 created_by, created_at, updated_at)
            VALUES
                (:id, :cid, :period, \'DRAFT\',
                 :e1_material, :e1_climate_policy, :e1_reduction_target,
                 :e2_material, :e2_nox_t_per_year, :e2_sox_t_per_year,
                 :e3_material, :e3_water_withdrawal_m3, :e3_water_recycling_rate_pct,
                 :e4_material, :e4_protected_areas_impact,
                 :e5_material, :e5_recycling_rate_pct, :e5_recycled_input_materials_pct,
                 :uid, NOW(), NOW())
        ');
        $stmt->execute(array_merge($data, [':id' => $id, ':cid' => company_id(), ':period' => $selectedPeriod, ':uid' => user_id()]));
    }

    $success = 'Environmental topics saved successfully.';
    $stmt = $pdo->prepare('SELECT * FROM environmental_topics WHERE company_id = :cid AND reporting_period = :period');
    $stmt->execute([':cid' => company_id(), ':period' => $selectedPeriod]);
    $existing = $stmt->fetch();
}

$postData = $_POST;
$isPost   = ($_SERVER['REQUEST_METHOD'] === 'POST');
$val = function(string $key, $fallback = '') use ($existing, $postData, $isPost) {
    if ($isPost) return $postData[$key] ?? $fallback;
    return $existing[$key] ?? $fallback;
};
$checked = function(string $key) use ($existing, $postData, $isPost): string {
    if ($isPost) return isset($postData[$key]) ? 'checked' : '';
    return !empty($existing[$key]) ? 'checked' : '';
};

$statusColors = [
    'DRAFT'        => 'bg-gray-100 text-gray-700',
    'UNDER_REVIEW' => 'bg-yellow-100 text-yellow-800',
    'APPROVED'     => 'bg-green-100 text-green-800',
    'PUBLISHED'    => 'bg-blue-100 text-blue-800',
    'REJECTED'     => 'bg-red-100 text-red-800',
];
$currentStatus = $existing['status'] ?? 'DRAFT';

require_once '../includes/header.php';
?>

<div class="space-y-6 max-w-3xl">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Environmental Topics</h2>
        <p class="text-gray-500 text-base mt-1">ESRS E1 Climate Change · E2 Pollution · E3 Water · E4 Biodiversity · E5 Circular Economy</p>
    </div>

    <?php if ($success): ?>
    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-center space-x-2">
        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p class="text-sm text-emerald-700 font-medium"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <?php endif; ?>

    <!-- Period + Status -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 flex items-end gap-4 flex-wrap">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Reporting Period</label>
            <input type="month" id="periodSelector" value="<?= htmlspecialchars($selectedPeriod, ENT_QUOTES, 'UTF-8') ?>"
                   class="px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        <button onclick="window.location.href='?period='+document.getElementById('periodSelector').value"
                class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 px-5 rounded-lg text-base transition">
            Load
        </button>
        <?php if ($existing): ?>
        <div class="ml-auto flex items-center space-x-3">
            <span class="text-sm text-gray-500">Status:</span>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold <?= $statusColors[$currentStatus] ?? 'bg-gray-100 text-gray-700' ?>">
                <?= htmlspecialchars($currentStatus, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <div class="flex space-x-1" id="statusButtons">
                <?php if ($currentStatus === 'DRAFT'): ?>
                <button class="status-btn text-xs px-2.5 py-1.5 bg-yellow-50 text-yellow-700 hover:bg-yellow-100 rounded font-medium transition" data-status="UNDER_REVIEW" data-id="<?= $existing['id'] ?>" data-table="environmental_topics">Submit for Review</button>
                <?php endif; ?>
                <?php if ($currentStatus === 'UNDER_REVIEW' && is_admin()): ?>
                <button class="status-btn text-xs px-2.5 py-1.5 bg-green-50 text-green-700 hover:bg-green-100 rounded font-medium transition" data-status="APPROVED" data-id="<?= $existing['id'] ?>" data-table="environmental_topics">Approve</button>
                <button class="status-btn text-xs px-2.5 py-1.5 bg-red-50 text-red-700 hover:bg-red-100 rounded font-medium transition" data-status="REJECTED" data-id="<?= $existing['id'] ?>" data-table="environmental_topics">Reject</button>
                <?php endif; ?>
                <?php if ($currentStatus === 'APPROVED' && is_admin()): ?>
                <button class="status-btn text-xs px-2.5 py-1.5 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded font-medium transition" data-status="PUBLISHED" data-id="<?= $existing['id'] ?>" data-table="environmental_topics">Publish</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Form -->
    <form method="POST" class="space-y-4">
        <input type="hidden" name="reporting_period" value="<?= htmlspecialchars($selectedPeriod, ENT_QUOTES, 'UTF-8') ?>">

        <!-- E1 Climate Change -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <input type="checkbox" id="acc-e1" class="accordion-toggle sr-only">
            <label for="acc-e1" class="flex items-center justify-between px-6 py-4 cursor-pointer bg-green-50 border-b border-green-100 hover:bg-green-100 transition">
                <div class="flex items-center space-x-3">
                    <span class="w-7 h-7 bg-green-600 text-white text-xs font-bold rounded flex items-center justify-center">E1</span>
                    <div>
                        <span class="font-semibold text-gray-900">Climate Change</span>
                        <span class="text-gray-500 text-sm ml-2">GHG emissions, reduction targets & climate policy</span>
                    </div>
                </div>
                <svg class="w-5 h-5 text-gray-400 accordion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </label>
            <div class="accordion-content p-6 space-y-4">
                <!-- Scope totals display -->
                <div class="grid grid-cols-3 gap-3 mb-4">
                    <div class="bg-green-50 rounded-lg p-3 text-center border border-green-100">
                        <p class="text-xs text-green-600 font-medium">Scope 1 Total</p>
                        <p class="text-xl font-bold text-green-700"><?= number_format($s1, 4) ?></p>
                        <p class="text-xs text-green-500">tCO2e</p>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-3 text-center border border-blue-100">
                        <p class="text-xs text-blue-600 font-medium">Scope 2 Total</p>
                        <p class="text-xl font-bold text-blue-700"><?= number_format($s2, 4) ?></p>
                        <p class="text-xs text-blue-500">tCO2e</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3 text-center border border-gray-200">
                        <p class="text-xs text-gray-600 font-medium">Combined Total</p>
                        <p class="text-xl font-bold text-gray-700"><?= number_format($s1 + $s2, 4) ?></p>
                        <p class="text-xs text-gray-500">tCO2e</p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <input type="checkbox" id="e1_material" name="e1_material" value="1" <?= $checked('e1_material') ?> class="w-4 h-4 text-emerald-600 rounded border-gray-300 focus:ring-emerald-500">
                    <label for="e1_material" class="text-sm font-medium text-gray-700">Climate change is a material topic for this company</label>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Climate Policy</label>
                    <textarea name="e1_climate_policy" rows="3"
                              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none resize-none"
                              placeholder="Describe your climate change mitigation and adaptation policies..."><?= htmlspecialchars((string)$val('e1_climate_policy'), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reduction Target</label>
                    <input type="text" name="e1_reduction_target"
                           value="<?= htmlspecialchars((string)$val('e1_reduction_target'), ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                           placeholder="e.g. 30% reduction by 2030 vs 2020 baseline">
                </div>
            </div>
        </div>

        <!-- E2 Pollution -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <input type="checkbox" id="acc-e2" class="accordion-toggle sr-only">
            <label for="acc-e2" class="flex items-center justify-between px-6 py-4 cursor-pointer bg-orange-50 border-b border-orange-100 hover:bg-orange-100 transition">
                <div class="flex items-center space-x-3">
                    <span class="w-7 h-7 bg-orange-600 text-white text-xs font-bold rounded flex items-center justify-center">E2</span>
                    <div>
                        <span class="font-semibold text-gray-900">Pollution</span>
                        <span class="text-gray-500 text-sm ml-2">NOx, SOx and other air pollutants</span>
                    </div>
                </div>
                <svg class="w-5 h-5 text-gray-400 accordion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            <div class="accordion-content p-6 space-y-4">
                <div class="flex items-center space-x-2">
                    <input type="checkbox" id="e2_material" name="e2_material" value="1" <?= $checked('e2_material') ?> class="w-4 h-4 text-emerald-600 rounded border-gray-300 focus:ring-emerald-500">
                    <label for="e2_material" class="text-sm font-medium text-gray-700">Pollution is a material topic for this company</label>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">NOx Emissions (tonnes/year)</label>
                        <input type="number" name="e2_nox_t_per_year" step="0.01" min="0"
                               value="<?= htmlspecialchars((string)$val('e2_nox_t_per_year'), ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                               placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">SOx Emissions (tonnes/year)</label>
                        <input type="number" name="e2_sox_t_per_year" step="0.01" min="0"
                               value="<?= htmlspecialchars((string)$val('e2_sox_t_per_year'), ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                               placeholder="0.00">
                    </div>
                </div>
            </div>
        </div>

        <!-- E3 Water -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <input type="checkbox" id="acc-e3" class="accordion-toggle sr-only">
            <label for="acc-e3" class="flex items-center justify-between px-6 py-4 cursor-pointer bg-blue-50 border-b border-blue-100 hover:bg-blue-100 transition">
                <div class="flex items-center space-x-3">
                    <span class="w-7 h-7 bg-blue-600 text-white text-xs font-bold rounded flex items-center justify-center">E3</span>
                    <div>
                        <span class="font-semibold text-gray-900">Water & Marine Resources</span>
                        <span class="text-gray-500 text-sm ml-2">Water withdrawal and recycling</span>
                    </div>
                </div>
                <svg class="w-5 h-5 text-gray-400 accordion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            <div class="accordion-content p-6 space-y-4">
                <div class="flex items-center space-x-2">
                    <input type="checkbox" id="e3_material" name="e3_material" value="1" <?= $checked('e3_material') ?> class="w-4 h-4 text-emerald-600 rounded border-gray-300 focus:ring-emerald-500">
                    <label for="e3_material" class="text-sm font-medium text-gray-700">Water is a material topic for this company</label>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Water Withdrawal (m³)</label>
                        <input type="number" name="e3_water_withdrawal_m3" step="0.01" min="0"
                               value="<?= htmlspecialchars((string)$val('e3_water_withdrawal_m3'), ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                               placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Water Recycling Rate (%)</label>
                        <input type="number" name="e3_water_recycling_rate_pct" min="0" max="100"
                               value="<?= htmlspecialchars((string)$val('e3_water_recycling_rate_pct'), ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                               placeholder="0">
                    </div>
                </div>
            </div>
        </div>

        <!-- E4 Biodiversity -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <input type="checkbox" id="acc-e4" class="accordion-toggle sr-only">
            <label for="acc-e4" class="flex items-center justify-between px-6 py-4 cursor-pointer bg-emerald-50 border-b border-emerald-100 hover:bg-emerald-100 transition">
                <div class="flex items-center space-x-3">
                    <span class="w-7 h-7 bg-emerald-600 text-white text-xs font-bold rounded flex items-center justify-center">E4</span>
                    <div>
                        <span class="font-semibold text-gray-900">Biodiversity & Ecosystems</span>
                        <span class="text-gray-500 text-sm ml-2">Impacts on protected areas</span>
                    </div>
                </div>
                <svg class="w-5 h-5 text-gray-400 accordion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            <div class="accordion-content p-6 space-y-4">
                <div class="flex items-center space-x-2">
                    <input type="checkbox" id="e4_material" name="e4_material" value="1" <?= $checked('e4_material') ?> class="w-4 h-4 text-emerald-600 rounded border-gray-300 focus:ring-emerald-500">
                    <label for="e4_material" class="text-sm font-medium text-gray-700">Biodiversity is a material topic for this company</label>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Impact on Protected Areas</label>
                    <textarea name="e4_protected_areas_impact" rows="3"
                              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none resize-none"
                              placeholder="Describe any operational impacts on biodiversity-sensitive or protected areas..."><?= htmlspecialchars((string)$val('e4_protected_areas_impact'), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
            </div>
        </div>

        <!-- E5 Circular Economy -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <input type="checkbox" id="acc-e5" class="accordion-toggle sr-only">
            <label for="acc-e5" class="flex items-center justify-between px-6 py-4 cursor-pointer bg-purple-50 border-b border-purple-100 hover:bg-purple-100 transition">
                <div class="flex items-center space-x-3">
                    <span class="w-7 h-7 bg-purple-600 text-white text-xs font-bold rounded flex items-center justify-center">E5</span>
                    <div>
                        <span class="font-semibold text-gray-900">Resource Use & Circular Economy</span>
                        <span class="text-gray-500 text-sm ml-2">Recycling rates and circular inputs</span>
                    </div>
                </div>
                <svg class="w-5 h-5 text-gray-400 accordion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            <div class="accordion-content p-6 space-y-4">
                <div class="flex items-center space-x-2">
                    <input type="checkbox" id="e5_material" name="e5_material" value="1" <?= $checked('e5_material') ?> class="w-4 h-4 text-emerald-600 rounded border-gray-300 focus:ring-emerald-500">
                    <label for="e5_material" class="text-sm font-medium text-gray-700">Circular economy is a material topic for this company</label>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Recycling Rate (%)</label>
                        <input type="number" name="e5_recycling_rate_pct" min="0" max="100"
                               value="<?= htmlspecialchars((string)$val('e5_recycling_rate_pct'), ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                               placeholder="0">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Recycled Input Materials (%)</label>
                        <input type="number" name="e5_recycled_input_materials_pct" min="0" max="100"
                               value="<?= htmlspecialchars((string)$val('e5_recycled_input_materials_pct'), ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                               placeholder="0">
                    </div>
                </div>
            </div>
        </div>

        <!-- Save -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <button type="submit" name="save" value="1"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-8 rounded-lg text-base transition">
                Save All Environmental Topics
            </button>
        </div>
    </form>
</div>

<script>
$('.status-btn').on('click', function() {
    var btn    = $(this);
    var status = btn.data('status');
    var id     = btn.data('id');
    var table  = btn.data('table');

    if (!confirm('Change status to ' + status + '?')) return;

    $.post('/esg-report-test/api/save-status.php', { status: status, id: id, table: table }, function(resp) {
        if (resp.success) {
            location.reload();
        } else {
            alert(resp.error || 'Failed to update status');
        }
    }, 'json').fail(function() {
        alert('Request failed. Please try again.');
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
