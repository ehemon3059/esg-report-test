<?php
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';
require_once '../includes/helpers.php';

$pageTitle = 'Governance (ESRS G1)';
$success   = '';
$error     = '';

$selectedPeriod = sanitize($_POST['reporting_period'] ?? $_GET['period'] ?? date('Y-m'));

// Load existing
$stmt = $pdo->prepare('SELECT * FROM s_governance WHERE company_id = :cid AND reporting_period = :period');
$stmt->execute([':cid' => company_id(), ':period' => $selectedPeriod]);
$existing = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $data = [
        'g1_board_composition_independence' => trim($_POST['g1_board_composition_independence'] ?? ''),
        'g1_gender_diversity_pct'           => $_POST['g1_gender_diversity_pct'] !== '' ? (int)$_POST['g1_gender_diversity_pct'] : null,
        'g1_esg_oversight'                  => trim($_POST['g1_esg_oversight']                  ?? ''),
        'g1_whistleblower_cases'            => trim($_POST['g1_whistleblower_cases']            ?? ''),
        'g1_anti_corruption_policies'       => trim($_POST['g1_anti_corruption_policies']       ?? ''),
        'g1_related_party_controls'         => trim($_POST['g1_related_party_controls']         ?? ''),
    ];

    if ($existing) {
        $stmt = $pdo->prepare('
            UPDATE s_governance SET
                g1_board_composition_independence=:g1_board_composition_independence,
                g1_gender_diversity_pct=:g1_gender_diversity_pct,
                g1_esg_oversight=:g1_esg_oversight,
                g1_whistleblower_cases=:g1_whistleblower_cases,
                g1_anti_corruption_policies=:g1_anti_corruption_policies,
                g1_related_party_controls=:g1_related_party_controls,
                updated_by=:uid, updated_at=NOW()
            WHERE id=:id AND company_id=:cid
        ');
        $stmt->execute(array_merge($data, [':uid' => user_id(), ':id' => $existing['id'], ':cid' => company_id()]));
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO s_governance
                (id, company_id, reporting_period, status,
                 g1_board_composition_independence, g1_gender_diversity_pct, g1_esg_oversight,
                 g1_whistleblower_cases, g1_anti_corruption_policies, g1_related_party_controls,
                 created_by, created_at, updated_at)
            VALUES
                (:id, :cid, :period, \'DRAFT\',
                 :g1_board_composition_independence, :g1_gender_diversity_pct, :g1_esg_oversight,
                 :g1_whistleblower_cases, :g1_anti_corruption_policies, :g1_related_party_controls,
                 :uid, NOW(), NOW())
        ');
        $stmt->execute(array_merge($data, [':id' => uuid(), ':cid' => company_id(), ':period' => $selectedPeriod, ':uid' => user_id()]));
    }

    $success = 'Governance disclosures saved successfully.';
    $stmt = $pdo->prepare('SELECT * FROM s_governance WHERE company_id = :cid AND reporting_period = :period');
    $stmt->execute([':cid' => company_id(), ':period' => $selectedPeriod]);
    $existing = $stmt->fetch();
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
        <h2 class="text-2xl font-bold text-gray-900">Governance</h2>
        <p class="text-gray-500 text-base mt-1">ESRS G1 — Business conduct, board composition, and anti-corruption</p>
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
                <button class="status-btn text-xs px-2.5 py-1.5 bg-yellow-50 text-yellow-700 hover:bg-yellow-100 rounded font-medium" data-status="SUBMITTED" data-id="<?= $existing['id'] ?>" data-table="s_governance">Submit</button>
                <?php endif; ?>
                <?php if ($currentStatus === 'SUBMITTED' && is_admin()): ?>
                <button class="status-btn text-xs px-2.5 py-1.5 bg-green-50 text-green-700 hover:bg-green-100 rounded font-medium" data-status="APPROVED" data-id="<?= $existing['id'] ?>" data-table="s_governance">Approve</button>
                <button class="status-btn text-xs px-2.5 py-1.5 bg-red-50 text-red-700 hover:bg-red-100 rounded font-medium" data-status="REJECTED" data-id="<?= $existing['id'] ?>" data-table="s_governance">Reject</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <h3 class="text-base font-semibold text-gray-800">G1 — Business Conduct for period <span class="text-emerald-600"><?= htmlspecialchars($selectedPeriod, ENT_QUOTES, 'UTF-8') ?></span></h3>
        </div>
        <div class="p-6">
            <form method="POST" class="space-y-6">
                <input type="hidden" name="reporting_period" value="<?= htmlspecialchars($selectedPeriod, ENT_QUOTES, 'UTF-8') ?>">

                <!-- Board Composition -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Board Composition & Independence
                        <span class="text-gray-400 font-normal ml-1">— ESRS G1, GOV-1</span>
                    </label>
                    <textarea name="g1_board_composition_independence" rows="4"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none resize-none"
                              placeholder="Describe board structure, composition, number of independent directors..."><?= htmlspecialchars((string)$val('g1_board_composition_independence'), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <!-- Gender Diversity -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Board Gender Diversity (%)
                        <span class="text-gray-400 font-normal ml-1">— ESRS G1, GOV-1</span>
                    </label>
                    <p class="text-xs text-gray-400 mb-2">Percentage of board seats held by women (0–100).</p>
                    <input type="number" name="g1_gender_diversity_pct" min="0" max="100"
                           value="<?= htmlspecialchars((string)$val('g1_gender_diversity_pct'), ENT_QUOTES, 'UTF-8') ?>"
                           class="w-48 px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                           placeholder="e.g. 40">
                </div>

                <!-- ESG Oversight -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        ESG Oversight Mechanisms
                        <span class="text-gray-400 font-normal ml-1">— ESRS G1, GOV-2</span>
                    </label>
                    <textarea name="g1_esg_oversight" rows="3"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none resize-none"
                              placeholder="Describe how the board and management oversee ESG risks and opportunities..."><?= htmlspecialchars((string)$val('g1_esg_oversight'), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <!-- Whistleblower Cases -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Whistleblower Cases
                        <span class="text-gray-400 font-normal ml-1">— ESRS G1, G1-3</span>
                    </label>
                    <textarea name="g1_whistleblower_cases" rows="3"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none resize-none"
                              placeholder="Number of reports received, categories, and resolution outcomes..."><?= htmlspecialchars((string)$val('g1_whistleblower_cases'), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <!-- Anti-Corruption Policies -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Anti-Corruption & Bribery Policies
                        <span class="text-gray-400 font-normal ml-1">— ESRS G1, G1-1</span>
                    </label>
                    <input type="text" name="g1_anti_corruption_policies"
                           value="<?= htmlspecialchars((string)$val('g1_anti_corruption_policies'), ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                           placeholder="e.g. Anti-Bribery Policy (ISO 37001 certified), Zero-Tolerance Code of Conduct">
                </div>

                <!-- Related Party Controls -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Related-Party Transaction Controls
                        <span class="text-gray-400 font-normal ml-1">— ESRS G1, G1-2</span>
                    </label>
                    <input type="text" name="g1_related_party_controls"
                           value="<?= htmlspecialchars((string)$val('g1_related_party_controls'), ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                           placeholder="e.g. Audit Committee pre-approval required for all related-party transactions">
                </div>

                <div class="pt-2 border-t border-gray-100">
                    <button type="submit" name="save" value="1"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-8 rounded-lg text-base transition">
                        Save Governance Disclosures
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
