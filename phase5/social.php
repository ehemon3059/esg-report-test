<?php
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';
require_once '../includes/helpers.php';

$pageTitle = 'Social Topics (ESRS S1–S4)';
$success   = '';
$error     = '';

$selectedPeriod = sanitize($_POST['reporting_period'] ?? $_GET['period'] ?? date('Y-m'));

// Load existing record
$stmt = $pdo->prepare('SELECT * FROM social_topics WHERE company_id = :cid AND reporting_period = :period');
$stmt->execute([':cid' => company_id(), ':period' => $selectedPeriod]);
$existing = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $s1_material = isset($_POST['s1_material']) ? 1 : 0;
    $s2_material = isset($_POST['s2_material']) ? 1 : 0;
    $s3_material = isset($_POST['s3_material']) ? 1 : 0;
    $s4_material = isset($_POST['s4_material']) ? 1 : 0;

    $data = [
        's1_material'                    => $s1_material,
        's1_employee_count_by_contract'  => trim($_POST['s1_employee_count_by_contract'] ?? ''),
        's1_health_and_safety'           => trim($_POST['s1_health_and_safety']          ?? ''),
        's1_training_hours_per_employee' => $_POST['s1_training_hours_per_employee'] !== '' ? (int)$_POST['s1_training_hours_per_employee'] : null,
        's2_material'                    => $s2_material,
        's2_pct_suppliers_audited'       => $_POST['s2_pct_suppliers_audited'] !== '' ? (int)$_POST['s2_pct_suppliers_audited'] : null,
        's2_remediation_actions'         => trim($_POST['s2_remediation_actions'] ?? ''),
        's3_material'                    => $s3_material,
        's3_community_engagement'        => trim($_POST['s3_community_engagement']     ?? ''),
        's3_complaints_and_outcomes'     => trim($_POST['s3_complaints_and_outcomes']  ?? ''),
        's4_material'                    => $s4_material,
        's4_product_safety_incidents'    => $_POST['s4_product_safety_incidents'] !== '' ? (int)$_POST['s4_product_safety_incidents'] : null,
        's4_consumer_remediation'        => trim($_POST['s4_consumer_remediation'] ?? ''),
    ];

    if ($existing) {
        $stmt = $pdo->prepare('
            UPDATE social_topics SET
                s1_material=:s1_material, s1_employee_count_by_contract=:s1_employee_count_by_contract,
                s1_health_and_safety=:s1_health_and_safety, s1_training_hours_per_employee=:s1_training_hours_per_employee,
                s2_material=:s2_material, s2_pct_suppliers_audited=:s2_pct_suppliers_audited,
                s2_remediation_actions=:s2_remediation_actions,
                s3_material=:s3_material, s3_community_engagement=:s3_community_engagement,
                s3_complaints_and_outcomes=:s3_complaints_and_outcomes,
                s4_material=:s4_material, s4_product_safety_incidents=:s4_product_safety_incidents,
                s4_consumer_remediation=:s4_consumer_remediation,
                updated_by=:uid, updated_at=NOW()
            WHERE id=:id AND company_id=:cid
        ');
        $stmt->execute(array_merge($data, [':uid' => user_id(), ':id' => $existing['id'], ':cid' => company_id()]));
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO social_topics
                (id, company_id, reporting_period, status,
                 s1_material, s1_employee_count_by_contract, s1_health_and_safety, s1_training_hours_per_employee,
                 s2_material, s2_pct_suppliers_audited, s2_remediation_actions,
                 s3_material, s3_community_engagement, s3_complaints_and_outcomes,
                 s4_material, s4_product_safety_incidents, s4_consumer_remediation,
                 created_by, created_at, updated_at)
            VALUES
                (:id, :cid, :period, \'DRAFT\',
                 :s1_material, :s1_employee_count_by_contract, :s1_health_and_safety, :s1_training_hours_per_employee,
                 :s2_material, :s2_pct_suppliers_audited, :s2_remediation_actions,
                 :s3_material, :s3_community_engagement, :s3_complaints_and_outcomes,
                 :s4_material, :s4_product_safety_incidents, :s4_consumer_remediation,
                 :uid, NOW(), NOW())
        ');
        $stmt->execute(array_merge($data, [':id' => uuid(), ':cid' => company_id(), ':period' => $selectedPeriod, ':uid' => user_id()]));
    }

    $success = 'Social topics saved successfully.';
    $stmt = $pdo->prepare('SELECT * FROM social_topics WHERE company_id = :cid AND reporting_period = :period');
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
        <h2 class="text-2xl font-bold text-gray-900">Social Topics</h2>
        <p class="text-gray-500 text-base mt-1">ESRS S1 Own Workforce · S2 Value Chain · S3 Communities · S4 Consumers</p>
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
            <div class="flex space-x-1">
                <?php if ($currentStatus === 'DRAFT'): ?>
                <button class="status-btn text-xs px-2.5 py-1.5 bg-yellow-50 text-yellow-700 hover:bg-yellow-100 rounded font-medium" data-status="UNDER_REVIEW" data-id="<?= $existing['id'] ?>" data-table="social_topics">Submit for Review</button>
                <?php endif; ?>
                <?php if ($currentStatus === 'UNDER_REVIEW' && is_admin()): ?>
                <button class="status-btn text-xs px-2.5 py-1.5 bg-green-50 text-green-700 hover:bg-green-100 rounded font-medium" data-status="APPROVED" data-id="<?= $existing['id'] ?>" data-table="social_topics">Approve</button>
                <button class="status-btn text-xs px-2.5 py-1.5 bg-red-50 text-red-700 hover:bg-red-100 rounded font-medium" data-status="REJECTED" data-id="<?= $existing['id'] ?>" data-table="social_topics">Reject</button>
                <?php endif; ?>
                <?php if ($currentStatus === 'APPROVED' && is_admin()): ?>
                <button class="status-btn text-xs px-2.5 py-1.5 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded font-medium" data-status="PUBLISHED" data-id="<?= $existing['id'] ?>" data-table="social_topics">Publish</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Form -->
    <form method="POST" class="space-y-4">
        <input type="hidden" name="reporting_period" value="<?= htmlspecialchars($selectedPeriod, ENT_QUOTES, 'UTF-8') ?>">

        <!-- S1 Own Workforce -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <input type="checkbox" id="acc-s1" class="accordion-toggle sr-only">
            <label for="acc-s1" class="flex items-center justify-between px-6 py-4 cursor-pointer bg-indigo-50 border-b border-indigo-100 hover:bg-indigo-100 transition">
                <div class="flex items-center space-x-3">
                    <span class="w-7 h-7 bg-indigo-600 text-white text-xs font-bold rounded flex items-center justify-center">S1</span>
                    <div>
                        <span class="font-semibold text-gray-900">Own Workforce</span>
                        <span class="text-gray-500 text-sm ml-2">Employees, health & safety, training</span>
                    </div>
                </div>
                <svg class="w-5 h-5 text-gray-400 accordion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            <div class="accordion-content p-6 space-y-4">
                <div class="flex items-center space-x-2">
                    <input type="checkbox" id="s1_material" name="s1_material" value="1" <?= $checked('s1_material') ?> class="w-4 h-4 text-emerald-600 rounded border-gray-300">
                    <label for="s1_material" class="text-sm font-medium text-gray-700">Own workforce is a material topic</label>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Employee Count by Contract Type</label>
                    <textarea name="s1_employee_count_by_contract" rows="3"
                              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none resize-none"
                              placeholder="e.g. Full-time: 450, Part-time: 80, Fixed-term: 35, Contractors: 20"><?= htmlspecialchars((string)$val('s1_employee_count_by_contract'), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Health & Safety Disclosures</label>
                    <textarea name="s1_health_and_safety" rows="3"
                              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none resize-none"
                              placeholder="Describe H&S management systems, incident rates, and initiatives..."><?= htmlspecialchars((string)$val('s1_health_and_safety'), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Average Training Hours per Employee</label>
                    <input type="number" name="s1_training_hours_per_employee" min="0"
                           value="<?= htmlspecialchars((string)$val('s1_training_hours_per_employee'), ENT_QUOTES, 'UTF-8') ?>"
                           class="w-48 px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                           placeholder="e.g. 40">
                </div>
            </div>
        </div>

        <!-- S2 Value Chain -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <input type="checkbox" id="acc-s2" class="accordion-toggle sr-only">
            <label for="acc-s2" class="flex items-center justify-between px-6 py-4 cursor-pointer bg-teal-50 border-b border-teal-100 hover:bg-teal-100 transition">
                <div class="flex items-center space-x-3">
                    <span class="w-7 h-7 bg-teal-600 text-white text-xs font-bold rounded flex items-center justify-center">S2</span>
                    <div>
                        <span class="font-semibold text-gray-900">Workers in the Value Chain</span>
                        <span class="text-gray-500 text-sm ml-2">Supplier audits and remediation</span>
                    </div>
                </div>
                <svg class="w-5 h-5 text-gray-400 accordion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            <div class="accordion-content p-6 space-y-4">
                <div class="flex items-center space-x-2">
                    <input type="checkbox" id="s2_material" name="s2_material" value="1" <?= $checked('s2_material') ?> class="w-4 h-4 text-emerald-600 rounded border-gray-300">
                    <label for="s2_material" class="text-sm font-medium text-gray-700">Value chain workers is a material topic</label>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">% Suppliers Audited</label>
                    <input type="number" name="s2_pct_suppliers_audited" min="0" max="100"
                           value="<?= htmlspecialchars((string)$val('s2_pct_suppliers_audited'), ENT_QUOTES, 'UTF-8') ?>"
                           class="w-48 px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                           placeholder="0">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Remediation Actions</label>
                    <textarea name="s2_remediation_actions" rows="3"
                              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none resize-none"
                              placeholder="Describe actions taken to address identified supplier issues..."><?= htmlspecialchars((string)$val('s2_remediation_actions'), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
            </div>
        </div>

        <!-- S3 Communities -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <input type="checkbox" id="acc-s3" class="accordion-toggle sr-only">
            <label for="acc-s3" class="flex items-center justify-between px-6 py-4 cursor-pointer bg-amber-50 border-b border-amber-100 hover:bg-amber-100 transition">
                <div class="flex items-center space-x-3">
                    <span class="w-7 h-7 bg-amber-600 text-white text-xs font-bold rounded flex items-center justify-center">S3</span>
                    <div>
                        <span class="font-semibold text-gray-900">Affected Communities</span>
                        <span class="text-gray-500 text-sm ml-2">Community engagement and complaints</span>
                    </div>
                </div>
                <svg class="w-5 h-5 text-gray-400 accordion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            <div class="accordion-content p-6 space-y-4">
                <div class="flex items-center space-x-2">
                    <input type="checkbox" id="s3_material" name="s3_material" value="1" <?= $checked('s3_material') ?> class="w-4 h-4 text-emerald-600 rounded border-gray-300">
                    <label for="s3_material" class="text-sm font-medium text-gray-700">Affected communities is a material topic</label>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Community Engagement Activities</label>
                    <textarea name="s3_community_engagement" rows="3"
                              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none resize-none"
                              placeholder="Describe consultations, partnerships, and social investment activities..."><?= htmlspecialchars((string)$val('s3_community_engagement'), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Complaints & Outcomes</label>
                    <textarea name="s3_complaints_and_outcomes" rows="3"
                              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none resize-none"
                              placeholder="Describe community complaints received and how they were resolved..."><?= htmlspecialchars((string)$val('s3_complaints_and_outcomes'), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
            </div>
        </div>

        <!-- S4 Consumers -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <input type="checkbox" id="acc-s4" class="accordion-toggle sr-only">
            <label for="acc-s4" class="flex items-center justify-between px-6 py-4 cursor-pointer bg-rose-50 border-b border-rose-100 hover:bg-rose-100 transition">
                <div class="flex items-center space-x-3">
                    <span class="w-7 h-7 bg-rose-600 text-white text-xs font-bold rounded flex items-center justify-center">S4</span>
                    <div>
                        <span class="font-semibold text-gray-900">Consumers & End-users</span>
                        <span class="text-gray-500 text-sm ml-2">Product safety and consumer remediation</span>
                    </div>
                </div>
                <svg class="w-5 h-5 text-gray-400 accordion-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            <div class="accordion-content p-6 space-y-4">
                <div class="flex items-center space-x-2">
                    <input type="checkbox" id="s4_material" name="s4_material" value="1" <?= $checked('s4_material') ?> class="w-4 h-4 text-emerald-600 rounded border-gray-300">
                    <label for="s4_material" class="text-sm font-medium text-gray-700">Consumers is a material topic</label>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Product Safety Incidents</label>
                    <input type="number" name="s4_product_safety_incidents" min="0"
                           value="<?= htmlspecialchars((string)$val('s4_product_safety_incidents'), ENT_QUOTES, 'UTF-8') ?>"
                           class="w-48 px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                           placeholder="0">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Consumer Remediation Actions</label>
                    <textarea name="s4_consumer_remediation" rows="3"
                              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none resize-none"
                              placeholder="Describe corrective actions and consumer redress mechanisms..."><?= htmlspecialchars((string)$val('s4_consumer_remediation'), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Save -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <button type="submit" name="save" value="1"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-8 rounded-lg text-base transition">
                Save All Social Topics
            </button>
        </div>
    </form>
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
