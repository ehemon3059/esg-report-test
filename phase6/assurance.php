<?php
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';
require_once '../includes/helpers.php';

$pageTitle = 'Assurance & Audit';

// Add checklist columns if not exist (safe ALTER with IF NOT EXISTS for MySQL 8+)
$alterStatements = [
    "ALTER TABLE assurance ADD COLUMN IF NOT EXISTS checklist_data_collection_documented TINYINT(1) DEFAULT 0",
    "ALTER TABLE assurance ADD COLUMN IF NOT EXISTS checklist_internal_controls_tested TINYINT(1) DEFAULT 0",
    "ALTER TABLE assurance ADD COLUMN IF NOT EXISTS checklist_source_documentation_trail TINYINT(1) DEFAULT 0",
    "ALTER TABLE assurance ADD COLUMN IF NOT EXISTS checklist_calculation_method_validated TINYINT(1) DEFAULT 0",
];
foreach ($alterStatements as $sql) {
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        // Column may already exist on older MySQL without IF NOT EXISTS support — safe to ignore
    }
}

$success = '';
$error   = '';

$selectedPeriod = sanitize($_POST['reporting_period'] ?? $_GET['period'] ?? date('Y-m'));

// Load existing
$stmt = $pdo->prepare('SELECT * FROM assurance WHERE company_id = :cid AND reporting_period = :period');
$stmt->execute([':cid' => company_id(), ':period' => $selectedPeriod]);
$existing = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $provider     = trim($_POST['provider']          ?? '');
    $level        = $_POST['level']                  ?? '';
    $standard     = trim($_POST['standard']          ?? '');
    $scope_desc   = trim($_POST['scope_description'] ?? '');
    $conclusion   = trim($_POST['conclusion']        ?? '');
    $report_date  = $_POST['report_date']            ?? '';

    $checklist_data   = isset($_POST['checklist_data_collection_documented'])   ? 1 : 0;
    $checklist_ctrl   = isset($_POST['checklist_internal_controls_tested'])      ? 1 : 0;
    $checklist_source = isset($_POST['checklist_source_documentation_trail'])    ? 1 : 0;
    $checklist_calc   = isset($_POST['checklist_calculation_method_validated'])  ? 1 : 0;

    $allowedLevels = ['limited', 'reasonable', ''];
    if (!in_array($level, $allowedLevels)) {
        $error = 'Invalid assurance level.';
    } else {
        $data = [
            'provider'                               => $provider,
            'level'                                  => $level ?: null,
            'standard'                               => $standard,
            'scope_description'                      => $scope_desc,
            'conclusion'                             => $conclusion,
            'report_date'                            => $report_date ?: null,
            'checklist_data_collection_documented'   => $checklist_data,
            'checklist_internal_controls_tested'     => $checklist_ctrl,
            'checklist_source_documentation_trail'   => $checklist_source,
            'checklist_calculation_method_validated' => $checklist_calc,
        ];

        if ($existing) {
            $stmt = $pdo->prepare('
                UPDATE assurance SET
                    provider=:provider, level=:level, standard=:standard,
                    scope_description=:scope_description, conclusion=:conclusion,
                    report_date=:report_date,
                    checklist_data_collection_documented=:checklist_data_collection_documented,
                    checklist_internal_controls_tested=:checklist_internal_controls_tested,
                    checklist_source_documentation_trail=:checklist_source_documentation_trail,
                    checklist_calculation_method_validated=:checklist_calculation_method_validated,
                    updated_by=:uid, updated_at=NOW()
                WHERE id=:id AND company_id=:cid
            ');
            $stmt->execute(array_merge($data, [':uid' => user_id(), ':id' => $existing['id'], ':cid' => company_id()]));
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO assurance
                    (id, company_id, reporting_period,
                     provider, level, standard, scope_description, conclusion, report_date,
                     checklist_data_collection_documented,
                     checklist_internal_controls_tested,
                     checklist_source_documentation_trail,
                     checklist_calculation_method_validated,
                     created_by, created_at, updated_at)
                VALUES
                    (:id, :cid, :period,
                     :provider, :level, :standard, :scope_description, :conclusion, :report_date,
                     :checklist_data_collection_documented,
                     :checklist_internal_controls_tested,
                     :checklist_source_documentation_trail,
                     :checklist_calculation_method_validated,
                     :uid, NOW(), NOW())
            ');
            $stmt->execute(array_merge($data, [':id' => uuid(), ':cid' => company_id(), ':period' => $selectedPeriod, ':uid' => user_id()]));
        }

        $success = 'Assurance data saved successfully.';
        $stmt = $pdo->prepare('SELECT * FROM assurance WHERE company_id = :cid AND reporting_period = :period');
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
$chk = function(string $key) use ($existing, $postData, $isPost): string {
    if ($isPost) return isset($postData[$key]) ? 'checked' : '';
    return !empty($existing[$key]) ? 'checked' : '';
};

require_once '../includes/header.php';
?>

<div class="space-y-6 max-w-3xl">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Assurance & Audit</h2>
        <p class="text-gray-500 text-base mt-1">Third-party assurance engagement details and readiness checklist</p>
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

    <!-- Period Selector -->
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
    </div>

    <!-- Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <h3 class="text-base font-semibold text-gray-800">Assurance Details — <span class="text-emerald-600"><?= htmlspecialchars($selectedPeriod, ENT_QUOTES, 'UTF-8') ?></span></h3>
        </div>
        <div class="p-6">
            <form method="POST" class="space-y-6">
                <input type="hidden" name="reporting_period" value="<?= htmlspecialchars($selectedPeriod, ENT_QUOTES, 'UTF-8') ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Provider -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Assurance Provider</label>
                        <input type="text" name="provider"
                               value="<?= htmlspecialchars((string)$val('provider'), ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                               placeholder="e.g. Deloitte, KPMG, BDO">
                    </div>

                    <!-- Level -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Assurance Level</label>
                        <select name="level"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none bg-white">
                            <option value="">Select level...</option>
                            <option value="limited"     <?= $val('level') === 'limited'     ? 'selected' : '' ?>>Limited Assurance</option>
                            <option value="reasonable"  <?= $val('level') === 'reasonable'  ? 'selected' : '' ?>>Reasonable Assurance</option>
                        </select>
                    </div>

                    <!-- Standard -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Assurance Standard</label>
                        <input type="text" name="standard"
                               value="<?= htmlspecialchars((string)$val('standard'), ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                               placeholder="e.g. ISAE 3000, AA1000AS">
                    </div>

                    <!-- Report Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Report Date</label>
                        <input type="date" name="report_date"
                               value="<?= htmlspecialchars((string)$val('report_date'), ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none">
                    </div>
                </div>

                <!-- Scope Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Scope of Assurance</label>
                    <textarea name="scope_description" rows="3"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none resize-none"
                              placeholder="Describe what data, time period, and disclosures are covered by the assurance engagement..."><?= htmlspecialchars((string)$val('scope_description'), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <!-- Conclusion -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Assurance Conclusion</label>
                    <p class="text-xs text-amber-600 mt-0.5 mb-1">
                        Tip: Ensure your conclusion references the correct period end date
                        (<?= date('d F Y', strtotime($selectedPeriod . '-01 last day of this month')) ?>).
                    </p>
                    <textarea name="conclusion" rows="4"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none resize-none"
                              placeholder="State the assurance provider's conclusion or opinion on the sustainability information..."><?= htmlspecialchars((string)$val('conclusion'), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <!-- Checklist -->
                <div class="border border-gray-200 rounded-xl overflow-hidden">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                        <h4 class="text-sm font-semibold text-gray-800">Pre-Assurance Readiness Checklist</h4>
                        <p class="text-xs text-gray-400 mt-0.5">Complete these steps before engaging your assurance provider</p>
                    </div>
                    <div class="p-5 space-y-4">
                        <!-- Progress Bar -->
                        <div class="mb-2">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs text-gray-600 font-medium">Progress</span>
                                <span class="text-xs font-bold text-emerald-600" id="checklist-progress-label">0%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div id="checklist-progress-bar"
                                     class="bg-emerald-500 h-2.5 rounded-full transition-all duration-300"
                                     style="width: 0%"></div>
                            </div>
                        </div>

                        <!-- Checklist Items -->
                        <label class="flex items-start space-x-3 cursor-pointer group">
                            <input type="checkbox" name="checklist_data_collection_documented" value="1"
                                   <?= $chk('checklist_data_collection_documented') ?>
                                   class="checklist-item w-4 h-4 text-emerald-600 rounded border-gray-300 focus:ring-emerald-500 mt-0.5">
                            <div>
                                <span class="text-sm font-medium text-gray-800">Data collection process documented</span>
                                <p class="text-xs text-gray-400 mt-0.5">All data sources and collection methodologies are documented</p>
                            </div>
                        </label>

                        <label class="flex items-start space-x-3 cursor-pointer group">
                            <input type="checkbox" name="checklist_internal_controls_tested" value="1"
                                   <?= $chk('checklist_internal_controls_tested') ?>
                                   class="checklist-item w-4 h-4 text-emerald-600 rounded border-gray-300 focus:ring-emerald-500 mt-0.5">
                            <div>
                                <span class="text-sm font-medium text-gray-800">Internal controls tested</span>
                                <p class="text-xs text-gray-400 mt-0.5">Internal verification and review procedures have been completed</p>
                            </div>
                        </label>

                        <label class="flex items-start space-x-3 cursor-pointer group">
                            <input type="checkbox" name="checklist_source_documentation_trail" value="1"
                                   <?= $chk('checklist_source_documentation_trail') ?>
                                   class="checklist-item w-4 h-4 text-emerald-600 rounded border-gray-300 focus:ring-emerald-500 mt-0.5">
                            <div>
                                <span class="text-sm font-medium text-gray-800">Source documentation trail established</span>
                                <p class="text-xs text-gray-400 mt-0.5">Audit trail links each disclosure back to primary source documents</p>
                            </div>
                        </label>

                        <label class="flex items-start space-x-3 cursor-pointer group">
                            <input type="checkbox" name="checklist_calculation_method_validated" value="1"
                                   <?= $chk('checklist_calculation_method_validated') ?>
                                   class="checklist-item w-4 h-4 text-emerald-600 rounded border-gray-300 focus:ring-emerald-500 mt-0.5">
                            <div>
                                <span class="text-sm font-medium text-gray-800">Calculation methodology validated</span>
                                <p class="text-xs text-gray-400 mt-0.5">Emission calculations and KPI methodologies have been independently reviewed</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="pt-2 border-t border-gray-100">
                    <button type="submit" name="save" value="1"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-8 rounded-lg text-base transition">
                        Save Assurance Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateChecklistProgress() {
    var total   = $('.checklist-item').length;
    var checked = $('.checklist-item:checked').length;
    var pct     = total > 0 ? Math.round((checked / total) * 100) : 0;
    $('#checklist-progress-bar').css('width', pct + '%');
    $('#checklist-progress-label').text(pct + '%');

    // Change color based on completion
    var bar = $('#checklist-progress-bar');
    bar.removeClass('bg-red-500 bg-yellow-500 bg-emerald-500');
    if (pct < 50)        bar.addClass('bg-red-500');
    else if (pct < 100)  bar.addClass('bg-yellow-500');
    else                 bar.addClass('bg-emerald-500');
}

$(document).ready(function() {
    updateChecklistProgress();
    $('.checklist-item').on('change', updateChecklistProgress);
});
</script>

<?php require_once '../includes/footer.php'; ?>
