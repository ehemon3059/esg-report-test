<?php
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';
require_once '../includes/helpers.php';

$pageTitle = 'Scope 3 Emissions';
$success   = '';
$error     = '';

// Auto-create table if missing
$pdo->exec("CREATE TABLE IF NOT EXISTS `scope3_activities` (
    `id`                VARCHAR(36)     NOT NULL,
    `company_id`        VARCHAR(36)     NOT NULL,
    `reporting_period`  VARCHAR(7)      NOT NULL COMMENT 'YYYY-MM',
    `category`          VARCHAR(100)    NOT NULL COMMENT 'GHG Protocol Category 1-15',
    `description`       TEXT            DEFAULT NULL,
    `tco2e_estimated`   DECIMAL(18,4)   DEFAULT NULL,
    `estimation_method` VARCHAR(255)    DEFAULT NULL,
    `data_quality`      ENUM('measured','calculated','estimated') DEFAULT 'estimated',
    `created_by`        VARCHAR(36)     NOT NULL,
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_scope3_company_period` (`company_id`, `reporting_period`),
    CONSTRAINT `fk_scope3_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_scope3_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$selectedPeriod = sanitize($_POST['reporting_period'] ?? $_GET['period'] ?? date('Y-m'));

// GHG Protocol Scope 3 categories
$categories = [
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

// Handle add new entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_entry'])) {
    $cat     = sanitize($_POST['category'] ?? '');
    $desc    = trim($_POST['description'] ?? '');
    $tco2e   = ($_POST['tco2e_estimated'] ?? '') !== '' ? (float)$_POST['tco2e_estimated'] : null;
    $method  = sanitize($_POST['estimation_method'] ?? '');
    $quality = in_array($_POST['data_quality'] ?? '', ['measured', 'calculated', 'estimated'])
               ? $_POST['data_quality'] : 'estimated';

    if ($cat === '') {
        $error = 'Please select a category.';
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO scope3_activities
                (id, company_id, reporting_period, category, description,
                 tco2e_estimated, estimation_method, data_quality, created_by, created_at, updated_at)
            VALUES
                (:id, :cid, :period, :cat, :desc,
                 :tco2e, :method, :quality, :uid, NOW(), NOW())
        ');
        $stmt->execute([
            ':id'      => uuid(),
            ':cid'     => company_id(),
            ':period'  => $selectedPeriod,
            ':cat'     => $cat,
            ':desc'    => $desc,
            ':tco2e'   => $tco2e,
            ':method'  => $method,
            ':quality' => $quality,
            ':uid'     => user_id(),
        ]);
        $success = 'Scope 3 entry added successfully.';
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $stmt = $pdo->prepare('DELETE FROM scope3_activities WHERE id = :id AND company_id = :cid');
    $stmt->execute([':id' => $_POST['delete_id'], ':cid' => company_id()]);
    header('Location: /esg-report-test/phase3/scope3.php?period='
        . urlencode($_POST['reporting_period'] ?? date('Y-m')) . '&deleted=1');
    exit;
}

// Load entries for selected period
$stmt = $pdo->prepare('
    SELECT * FROM scope3_activities
    WHERE company_id = :cid AND reporting_period = :period
    ORDER BY category ASC
');
$stmt->execute([':cid' => company_id(), ':period' => $selectedPeriod]);
$entries = $stmt->fetchAll();

$scope3Total = array_sum(array_column($entries, 'tco2e_estimated'));

require_once '../includes/header.php';
?>

<div class="space-y-6 max-w-4xl">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Scope 3 Emissions</h2>
        <p class="text-gray-500 text-base mt-1">Value chain indirect emissions — GHG Protocol Categories 1–15</p>
    </div>

    <!-- Info Banner -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-start space-x-3">
        <svg class="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
            <p class="text-sm font-semibold text-blue-800">ESRS E1 &amp; GHG Protocol Requirement</p>
            <p class="text-sm text-blue-700 mt-0.5">
                Scope 3 covers all indirect emissions in your upstream and downstream value chain.
                Estimates and spend-based calculations are acceptable where measured data is unavailable.
            </p>
        </div>
    </div>

    <?php if ($success): ?>
    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-center space-x-2">
        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm text-emerald-700 font-medium"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 flex items-center space-x-2">
        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm text-emerald-700 font-medium">Entry deleted successfully.</p>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 flex items-center space-x-2">
        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm text-red-700"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <?php endif; ?>

    <!-- Period Selector -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 flex items-end gap-4 flex-wrap">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Reporting Period</label>
            <input type="month" id="periodSelector"
                   value="<?= htmlspecialchars($selectedPeriod, ENT_QUOTES, 'UTF-8') ?>"
                   class="px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        <button onclick="window.location.href='?period='+document.getElementById('periodSelector').value"
                class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 px-5 rounded-lg text-base transition">
            Load
        </button>
        <?php if (!empty($entries)): ?>
        <div class="ml-auto text-right">
            <p class="text-xs text-gray-500">Scope 3 Total</p>
            <p class="text-2xl font-bold text-purple-700"><?= number_format($scope3Total, 4) ?></p>
            <p class="text-xs text-gray-400">tCO2e (estimated)</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Add New Entry Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <h3 class="text-base font-semibold text-gray-800">Add Scope 3 Entry</h3>
            <p class="text-xs text-gray-500 mt-0.5">Period: <span class="font-medium text-emerald-600"><?= htmlspecialchars($selectedPeriod, ENT_QUOTES, 'UTF-8') ?></span></p>
        </div>
        <div class="p-6">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="reporting_period" value="<?= htmlspecialchars($selectedPeriod, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="add_entry" value="1">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Category -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            GHG Protocol Category <span class="text-red-500">*</span>
                        </label>
                        <select name="category" required
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none bg-white">
                            <option value="">Select category...</option>
                            <?php foreach ($categories as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($key . ' — ' . $label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Estimated tCO2e -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estimated tCO2e</label>
                        <input type="number" name="tco2e_estimated" step="0.01" min="0"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                               placeholder="e.g. 125.50">
                    </div>

                    <!-- Data Quality -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data Quality</label>
                        <select name="data_quality"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none bg-white">
                            <option value="estimated">Estimated (spend-based)</option>
                            <option value="calculated">Calculated (activity data)</option>
                            <option value="measured">Measured (primary data)</option>
                        </select>
                    </div>

                    <!-- Estimation Method -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estimation Method</label>
                        <input type="text" name="estimation_method"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none"
                               placeholder="e.g. Spend-based, DEFRA factors, supplier data">
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="2"
                              class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 outline-none resize-none"
                              placeholder="Brief description of the emission source..."></textarea>
                </div>

                <button type="submit"
                        class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2.5 px-6 rounded-lg text-base transition">
                    Add Entry
                </button>
            </form>
        </div>
    </div>

    <!-- Entries Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-800">
                Scope 3 Entries — <?= htmlspecialchars($selectedPeriod, ENT_QUOTES, 'UTF-8') ?>
            </h3>
            <span class="text-sm text-gray-500"><?= count($entries) ?> entries</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Category</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Description</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Method</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Quality</th>
                        <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">tCO2e</th>
                        <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($entries as $i => $e): ?>
                    <tr class="<?= $i % 2 === 1 ? 'bg-gray-50' : 'bg-white' ?>">
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                <?= htmlspecialchars($e['category'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <div class="text-xs text-gray-500 mt-0.5">
                                <?= htmlspecialchars($categories[$e['category']] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </td>
                        <td class="py-3 px-4 text-gray-700 max-w-xs">
                            <?= htmlspecialchars($e['description'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="py-3 px-4 text-gray-500 text-xs">
                            <?= htmlspecialchars($e['estimation_method'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="py-3 px-4">
                            <?php
                            $qColors = [
                                'measured'   => 'bg-green-100 text-green-800',
                                'calculated' => 'bg-blue-100 text-blue-800',
                                'estimated'  => 'bg-amber-100 text-amber-800',
                            ];
                            $qc = $qColors[$e['data_quality']] ?? 'bg-gray-100 text-gray-700';
                            ?>
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium <?= $qc ?>">
                                <?= htmlspecialchars(ucfirst($e['data_quality']), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right font-bold text-purple-700">
                            <?= $e['tco2e_estimated'] !== null
                                ? number_format((float)$e['tco2e_estimated'], 4)
                                : '—' ?>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <form method="POST">
                                <input type="hidden" name="reporting_period" value="<?= htmlspecialchars($selectedPeriod, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="delete_id" value="<?= htmlspecialchars($e['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit"
                                        onclick="return confirm('Delete this entry?')"
                                        class="text-xs font-medium text-red-600 bg-red-50 hover:bg-red-100 px-2.5 py-1.5 rounded transition">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($entries)): ?>
                    <tr>
                        <td colspan="6" class="py-10 text-center text-gray-400">
                            No Scope 3 entries yet for <?= htmlspecialchars($selectedPeriod, ENT_QUOTES, 'UTF-8') ?>.
                            Add your first entry above.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($entries)): ?>
                <tfoot>
                    <tr class="bg-purple-50 border-t-2 border-purple-200">
                        <td colspan="4" class="py-3 px-4 font-bold text-purple-800 text-sm">
                            Total Scope 3 (Estimated)
                        </td>
                        <td class="py-3 px-4 text-right font-bold text-purple-800">
                            <?= number_format($scope3Total, 4) ?> tCO2e
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
