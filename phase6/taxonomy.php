<?php
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';
require_once '../includes/helpers.php';

$pageTitle = 'EU Taxonomy';
$success   = '';
$error     = '';

$selectedPeriod = sanitize($_POST['reporting_period'] ?? $_GET['period'] ?? date('Y-m'));

// Load existing
$stmt = $pdo->prepare('SELECT * FROM eu_taxonomy WHERE company_id = :cid AND reporting_period = :period');
$stmt->execute([':cid' => company_id(), ':period' => $selectedPeriod]);
$existing = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $data = [
        'economic_activities'              => trim($_POST['economic_activities']              ?? ''),
        'technical_screening_criteria'     => trim($_POST['technical_screening_criteria']     ?? ''),
        'taxonomy_eligible_revenue_pct'    => $_POST['taxonomy_eligible_revenue_pct']    !== '' ? (int)$_POST['taxonomy_eligible_revenue_pct']    : null,
        'taxonomy_aligned_revenue_pct'     => $_POST['taxonomy_aligned_revenue_pct']     !== '' ? (int)$_POST['taxonomy_aligned_revenue_pct']     : null,
        'taxonomy_eligible_capex_pct'      => $_POST['taxonomy_eligible_capex_pct']      !== '' ? (int)$_POST['taxonomy_eligible_capex_pct']      : null,
        'taxonomy_aligned_capex_pct'       => $_POST['taxonomy_aligned_capex_pct']       !== '' ? (int)$_POST['taxonomy_aligned_capex_pct']       : null,
        'taxonomy_aligned_opex_pct'        => $_POST['taxonomy_aligned_opex_pct']        !== '' ? (int)$_POST['taxonomy_aligned_opex_pct']        : null,
        'dnsh_status'                      => $_POST['dnsh_status']    !== '' ? $_POST['dnsh_status']    : null,
        'social_safeguards_status'         => $_POST['social_safeguards_status'] !== '' ? $_POST['social_safeguards_status'] : null,
    ];

    $allowedDnsh     = ['ALL_OBJECTIVES_PASSED', 'SOME_OBJECTIVES_NOT_MET', 'ASSESSMENT_IN_PROGRESS', ''];
    $allowedSafeguards = ['FULL_COMPLIANCE', 'NON_COMPLIANCE', 'PARTIAL_REMEDIATION', ''];

    if (!in_array($_POST['dnsh_status'] ?? '', $allowedDnsh) || !in_array($_POST['social_safeguards_status'] ?? '', $allowedSafeguards)) {
        $error = 'Invalid status values selected.';
    } else {
        if ($existing) {
            $stmt = $pdo->prepare('
                UPDATE eu_taxonomy SET
                    economic_activities=:economic_activities,
                    technical_screening_criteria=:technical_screening_criteria,
                    taxonomy_eligible_revenue_pct=:taxonomy_eligible_revenue_pct,
                    taxonomy_aligned_revenue_pct=:taxonomy_aligned_revenue_pct,
                    taxonomy_eligible_capex_pct=:taxonomy_eligible_capex_pct,
                    taxonomy_aligned_capex_pct=:taxonomy_aligned_capex_pct,
                    taxonomy_aligned_opex_pct=:taxonomy_aligned_opex_pct,
                    dnsh_status=:dnsh_status,
                    social_safeguards_status=:social_safeguards_status,
                    updated_by=:uid, updated_at=NOW()
                WHERE id=:id AND company_id=:cid
            ');
            $stmt->execute(array_merge($data, [':uid' => user_id(), ':id' => $existing['id'], ':cid' => company_id()]));
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO eu_taxonomy
                    (id, company_id, reporting_period, status,
                     economic_activities, technical_screening_criteria,
                     taxonomy_eligible_revenue_pct, taxonomy_aligned_revenue_pct,
                     taxonomy_eligible_capex_pct, taxonomy_aligned_capex_pct,
                     taxonomy_aligned_opex_pct, dnsh_status, social_safeguards_status,
                     created_by, created_at, updated_at)
                VALUES
                    (:id, :cid, :period, \'DRAFT\',
                     :economic_activities, :technical_screening_criteria,
                     :taxonomy_eligible_revenue_pct, :taxonomy_aligned_revenue_pct,
                     :taxonomy_eligible_capex_pct, :taxonomy_aligned_capex_pct,
                     :taxonomy_aligned_opex_pct, :dnsh_status, :social_safeguards_status,
                     :uid, NOW(), NOW())
            ');
            $stmt->execute(array_merge($data, [':id' => uuid(), ':cid' => company_id(), ':period' => $selectedPeriod, ':uid' => user_id()]));
        }
        $success = 'EU Taxonomy data saved successfully.';
        $stmt = $pdo->prepare('SELECT * FROM eu_taxonomy WHERE company_id = :cid AND reporting_period = :period');
        $stmt->execute([':cid' => company_id(), ':period' => $selectedPeriod]);
        $existing = $stmt->fetch();
    }
}

$postData = $_POST;
$isPost   = ($_SERVER['REQUEST_METHOD'] === 'POST');
$val = function(string $key, $fallback = '') use ($existing, $postData, $isPost) {
    if ($isPost) return $postData[$key] ?? $fallback;
    return $existing[$key] ?? $fallback;
};

$statusColors = [
    'DRAFT'     => 'bg-gray-100 text-gray-700',
    'SUBMITTED' => 'bg-yellow-100 text-yellow-800',
    'APPROVED'  => 'bg-green-100 text-green-800',
    'REJECTED'  => 'bg-red-100 text-red-800',
];
$currentStatus = $existing['status'] ?? 'DRAFT';

require_once '../includes/header.php';
?>

<div class="space-y-6 max-w-3xl">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">EU Taxonomy Alignment</h2>
        <p class="text-gray-500 text-base mt-1">Report on taxonomy eligibility and alignment for revenue, CapEx, and OpEx</p>
    </div>

    <?php if ($success): ?>
    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-center space-x-2">
        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p class="text-sm text-emerald-700 font-medium"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 flex items-center space-x-2">
        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p class="text-sm text-red-700"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
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
            <span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold <?= $statusColors[$currentStatus] ?? 'bg-gray-100 text-gray-700' ?>">
                <?= htmlspecialchars($currentStatus, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <div class="flex space-x-1">
                <?php if ($currentStatus === 'DRAFT'): ?>
                <button class="status-btn text-xs px-2.5 py-1.5 bg-yellow-50 text-yellow-700 hover:bg-yellow-100 rounded font-medium" data-status="SUBMITTED" data-id="<?= $existing['id'] ?>" data-table="eu_taxonomy">Submit</button>
                <?php endif; ?>
                <?php if ($currentStatus === 'SUBMITTED' && is_admin()): ?>
                <button class="status-btn text-xs px-2.5 py-1.5 bg-green-50 text-green-700 hover:bg-green-100 rounded font-medium" data-status="APPROVED" data-id="<?= $existing['id'] ?>" data-table="eu_taxonomy">Approve</button>
                <button class="status-btn text-xs px-2.5 py-1.5 bg-red-50 text-red-700 hover:bg-red-100 rounded font-medium" data-status="REJECTED" data-id="<?= $existing['id'] ?>" data-table="eu_taxonomy">Reject</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <h3 class="text-base font-semibold text-gray-800">EU Taxonomy Data — <span class="text-emerald-600"><?= htmlspecialchars($selectedPeriod, ENT_QUOTES, 'UTF-8') ?></span></h3>
        </div>
        <div class="p-6">
            <form method="POST" class="space-y-6">
                <input type="hidden" name="reporting_period" value="<?= htmlspecialchars($selectedPeriod, ENT_QUOTES, 'UTF-8') ?>">

                <!-- Economic Activities -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Economic Activities Assessed</label>
                    <textarea name="economic_activities" rows="3"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none resize-none"
                              placeholder="List the economic activities assessed under the EU Taxonomy, referencing NACE codes where applicable..."><?= htmlspecialchars((string)$val('economic_activities'), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <!-- Technical Screening Criteria -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Technical Screening Criteria Applied</label>
                    <textarea name="technical_screening_criteria" rows="3"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none resize-none"
                              placeholder="Describe the technical screening criteria used to assess substantial contribution..."><?= htmlspecialchars((string)$val('technical_screening_criteria'), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <!-- KPI Grid -->
                <div>
                    <p class="text-sm font-semibold text-gray-700 mb-3">Key Performance Indicators (%)</p>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Eligible Revenue %</label>
                            <input type="number" name="taxonomy_eligible_revenue_pct" min="0" max="100"
                                   value="<?= htmlspecialchars((string)$val('taxonomy_eligible_revenue_pct'), ENT_QUOTES, 'UTF-8') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                                   placeholder="0">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Aligned Revenue %</label>
                            <input type="number" name="taxonomy_aligned_revenue_pct" min="0" max="100"
                                   value="<?= htmlspecialchars((string)$val('taxonomy_aligned_revenue_pct'), ENT_QUOTES, 'UTF-8') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                                   placeholder="0">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Eligible CapEx %</label>
                            <input type="number" name="taxonomy_eligible_capex_pct" min="0" max="100"
                                   value="<?= htmlspecialchars((string)$val('taxonomy_eligible_capex_pct'), ENT_QUOTES, 'UTF-8') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                                   placeholder="0">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Aligned CapEx %</label>
                            <input type="number" name="taxonomy_aligned_capex_pct" min="0" max="100"
                                   value="<?= htmlspecialchars((string)$val('taxonomy_aligned_capex_pct'), ENT_QUOTES, 'UTF-8') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                                   placeholder="0">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Aligned OpEx %</label>
                            <input type="number" name="taxonomy_aligned_opex_pct" min="0" max="100"
                                   value="<?= htmlspecialchars((string)$val('taxonomy_aligned_opex_pct'), ENT_QUOTES, 'UTF-8') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                                   placeholder="0">
                        </div>
                    </div>
                </div>

                <!-- DNSH Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">DNSH Assessment Status</label>
                    <p class="text-xs text-gray-400 mb-2">Do No Significant Harm — assessment against the 6 environmental objectives</p>
                    <select name="dnsh_status"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none bg-white">
                        <option value="">Not assessed</option>
                        <option value="ALL_OBJECTIVES_PASSED"     <?= $val('dnsh_status') === 'ALL_OBJECTIVES_PASSED'     ? 'selected' : '' ?>>All Objectives Passed</option>
                        <option value="SOME_OBJECTIVES_NOT_MET"   <?= $val('dnsh_status') === 'SOME_OBJECTIVES_NOT_MET'   ? 'selected' : '' ?>>Some Objectives Not Met</option>
                        <option value="ASSESSMENT_IN_PROGRESS"    <?= $val('dnsh_status') === 'ASSESSMENT_IN_PROGRESS'    ? 'selected' : '' ?>>Assessment In Progress</option>
                    </select>
                </div>

                <!-- Social Safeguards -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Minimum Social Safeguards Status</label>
                    <p class="text-xs text-gray-400 mb-2">Alignment with OECD Guidelines, UN Guiding Principles, ILO standards</p>
                    <select name="social_safeguards_status"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none bg-white">
                        <option value="">Not assessed</option>
                        <option value="FULL_COMPLIANCE"      <?= $val('social_safeguards_status') === 'FULL_COMPLIANCE'      ? 'selected' : '' ?>>Full Compliance</option>
                        <option value="NON_COMPLIANCE"       <?= $val('social_safeguards_status') === 'NON_COMPLIANCE'       ? 'selected' : '' ?>>Non-Compliance</option>
                        <option value="PARTIAL_REMEDIATION"  <?= $val('social_safeguards_status') === 'PARTIAL_REMEDIATION'  ? 'selected' : '' ?>>Partial Remediation</option>
                    </select>
                </div>

                <div class="pt-2 border-t border-gray-100">
                    <button type="submit" name="save" value="1"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-8 rounded-lg text-base transition">
                        Save EU Taxonomy Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$('.status-btn').on('click', function() {
    var status = $(this).data('status');
    var id     = $(this).data('id');
    var table  = $(this).data('table');
    if (!confirm('Change status to ' + status + '?')) return;
    $.post('/esg-report-test/api/save-status.php', { status: status, id: id, table: table }, function(resp) {
        if (resp.success) location.reload();
        else alert(resp.error || 'Failed');
    }, 'json');
});
</script>

<?php require_once '../includes/footer.php'; ?>
