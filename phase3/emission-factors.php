<?php
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';
require_once '../includes/helpers.php';

$pageTitle = 'Emission Factors';
$success   = '';
$error     = '';

// Handle deactivate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deactivate') {
    if (is_admin()) {
        $stmt = $pdo->prepare('UPDATE emission_factors SET is_active = 0 WHERE id = :id');
        $stmt->execute([':id' => $_POST['factor_id'] ?? '']);
        $success = 'Emission factor deactivated.';
    } else {
        $error = 'Only admins can deactivate emission factors.';
    }
}

// Handle add new
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (is_admin()) {
        $scope        = $_POST['scope']         ?? '';
        $activityType = trim($_POST['activity_type'] ?? '');
        $region       = trim($_POST['region']   ?? '');
        $factor       = trim($_POST['factor']   ?? '');
        $unit         = $_POST['unit']          ?? '';
        $source       = $_POST['source']        ?? '';
        $version      = trim($_POST['version']  ?? '');
        $validFrom    = $_POST['valid_from']    ?? '';

        $allowedScopes  = ['Scope 1', 'Scope 2 Location-Based', 'Scope 2 Market-Based', 'Scope 3'];
        $allowedUnits   = ['litre', 'kWh', 'm3', 'kg'];
        $allowedSources = ['DEFRA', 'IEA', 'EPA', 'Custom'];

        if (!in_array($scope, $allowedScopes) || $activityType === '' || !is_numeric($factor) || !in_array($unit, $allowedUnits) || !in_array($source, $allowedSources)) {
            $error = 'Please fill all required fields correctly.';
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO emission_factors (id, activity_type, scope, region, factor, unit, source, version, is_active, valid_from, created_at, updated_at)
                VALUES (:id, :activity_type, :scope, :region, :factor, :unit, :source, :version, 1, :valid_from, NOW(), NOW())
            ');
            $stmt->execute([
                ':id'           => uuid(),
                ':activity_type' => $activityType,
                ':scope'        => $scope,
                ':region'       => $region,
                ':factor'       => $factor,
                ':unit'         => $unit,
                ':source'       => $source,
                ':version'      => $version,
                ':valid_from'   => $validFrom ?: null,
            ]);
            $success = 'Emission factor added successfully.';
        }
    } else {
        $error = 'Only admins can add emission factors.';
    }
}

// Load all active factors
$stmt = $pdo->prepare('SELECT * FROM emission_factors WHERE is_active = 1 ORDER BY scope, activity_type');
$stmt->execute();
$factors = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Emission Factors Library</h2>
        <p class="text-gray-500 text-base mt-1">Reference CO2e conversion factors for emissions calculations</p>
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

    <!-- Add New Factor (Admin only) -->
    <?php if (is_admin()): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <h3 class="text-base font-semibold text-gray-800">Add New Emission Factor</h3>
        </div>
        <div class="p-6">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Scope -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Scope <span class="text-red-500">*</span></label>
                        <select name="scope" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white">
                            <option value="">Select scope...</option>
                            <option value="Scope 1">Scope 1 (Direct)</option>
                            <option value="Scope 2 Location-Based">Scope 2 Location-Based</option>
                            <option value="Scope 2 Market-Based">Scope 2 Market-Based</option>
                            <option value="Scope 3">Scope 3 (Value Chain)</option>
                        </select>
                    </div>

                    <!-- Activity Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Activity Type <span class="text-red-500">*</span></label>
                        <input type="text" name="activity_type" required
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                               placeholder="e.g. diesel, electricity">
                    </div>

                    <!-- Region -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Region</label>
                        <input type="text" name="region"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                               placeholder="e.g. GLOBAL, MY, UK">
                    </div>

                    <!-- Factor Value -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Factor Value (kg CO2e) <span class="text-red-500">*</span></label>
                        <input type="number" name="factor" required step="0.00001" min="0"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                               placeholder="e.g. 2.68720">
                    </div>

                    <!-- Unit -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit <span class="text-red-500">*</span></label>
                        <select name="unit" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white">
                            <option value="">Select unit...</option>
                            <option value="litre">Litre</option>
                            <option value="kWh">kWh</option>
                            <option value="m3">m3</option>
                            <option value="kg">kg</option>
                        </select>
                    </div>

                    <!-- Source -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Source <span class="text-red-500">*</span></label>
                        <select name="source" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white">
                            <option value="">Select source...</option>
                            <option value="DEFRA">DEFRA</option>
                            <option value="IEA">IEA</option>
                            <option value="EPA">EPA</option>
                            <option value="Custom">Custom</option>
                        </select>
                    </div>

                    <!-- Version -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Version</label>
                        <input type="text" name="version"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                               placeholder="e.g. 2024">
                    </div>

                    <!-- Valid From -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Valid From</label>
                        <input type="date" name="valid_from"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    </div>

                    <!-- Submit -->
                    <div class="flex items-end">
                        <button type="submit"
                                class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-4 rounded-lg text-base transition">
                            Add Factor
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Factors Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-800">Active Emission Factors</h3>
            <span class="text-sm text-gray-500"><?= count($factors) ?> factors</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-base">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Activity Type</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Scope</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Region</th>
                        <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Factor (kg CO2e)</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Unit</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Source</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Version</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Valid From</th>
                        <?php if (is_admin()): ?><th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($factors as $i => $f): ?>
                    <tr class="<?= $i % 2 === 1 ? 'bg-gray-50' : 'bg-white' ?> hover:bg-emerald-50 transition-colors">
                        <td class="py-3 px-4 font-medium text-gray-900"><?= htmlspecialchars($f['activity_type'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3 px-4">
                            <?php
                            $scopeColors = [
                                'Scope 1' => 'bg-green-100 text-green-800',
                                'Scope 2 Location-Based' => 'bg-blue-100 text-blue-800',
                                'Scope 2 Market-Based' => 'bg-indigo-100 text-indigo-800',
                                'Scope 3' => 'bg-purple-100 text-purple-800',
                            ];
                            $cls = $scopeColors[$f['scope']] ?? 'bg-gray-100 text-gray-700';
                            ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= $cls ?>">
                                <?= htmlspecialchars($f['scope'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-gray-600"><?= htmlspecialchars($f['region'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3 px-4 text-right font-mono text-gray-900"><?= number_format((float)$f['factor'], 5) ?></td>
                        <td class="py-3 px-4 text-gray-600"><?= htmlspecialchars($f['unit'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3 px-4 text-gray-600"><?= htmlspecialchars($f['source'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3 px-4 text-gray-600"><?= htmlspecialchars($f['version'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3 px-4 text-gray-500 text-sm"><?= $f['valid_from'] ? date('d M Y', strtotime($f['valid_from'])) : '—' ?></td>
                        <?php if (is_admin()): ?>
                        <td class="py-3 px-4 text-right">
                            <form method="POST" onsubmit="return confirm('Deactivate this emission factor?')">
                                <input type="hidden" name="action" value="deactivate">
                                <input type="hidden" name="factor_id" value="<?= htmlspecialchars($f['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit"
                                        class="text-xs font-medium text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 px-2.5 py-1.5 rounded transition">
                                    Deactivate
                                </button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($factors)): ?>
                    <tr><td colspan="9" class="py-8 text-center text-gray-400">No active emission factors found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
