<?php
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';
require_once '../includes/helpers.php';

$pageTitle   = 'Energy Consumption';
$success     = '';
$error       = '';
$tco2eResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteId     = $_POST['site_id']     ?? '';
    $dateMonth  = $_POST['date']        ?? '';  // YYYY-MM
    $energyType = $_POST['energy_type'] ?? '';
    $consumption = $_POST['consumption'] ?? '';
    $unit        = $_POST['unit']        ?? '';

    $allowedTypes = ['electricity', 'district_heating', 'steam', 'cooling'];
    $allowedUnits = ['kWh', 'MWh', 'GJ'];

    if ($siteId === '' || $dateMonth === '' || !in_array($energyType, $allowedTypes) || !is_numeric($consumption) || (float)$consumption <= 0 || !in_array($unit, $allowedUnits)) {
        $error = 'Please fill all fields correctly.';
    } else {
        // Step 1: Normalise to kWh
        $rawConsumption = (float)$consumption;
        $consumptionKwh = match($unit) {
            'MWh' => $rawConsumption * 1000,
            'GJ'  => $rawConsumption * 277.78,
            default => $rawConsumption,  // already kWh
        };

        $dateValue = $dateMonth . '-01';

        // Step 2: Insert energy activity
        $energyId = uuid();
        $stmt = $pdo->prepare('
            INSERT INTO energy_activities (id, site_id, date, energy_type, consumption, unit, created_at, updated_at)
            VALUES (:id, :site_id, :date, :energy_type, :consumption, :unit, NOW(), NOW())
        ');
        $stmt->execute([
            ':id'          => $energyId,
            ':site_id'     => $siteId,
            ':date'        => $dateValue,
            ':energy_type' => $energyType,
            ':consumption' => $rawConsumption,
            ':unit'        => $unit,
        ]);

        // Step 3: Get company country
        $stmt = $pdo->prepare('SELECT country_of_registration FROM companies WHERE id = :cid AND deleted_at IS NULL');
        $stmt->execute([':cid' => company_id()]);
        $companyRow = $stmt->fetch();
        $region = strtoupper(trim($companyRow['country_of_registration'] ?? 'GLOBAL'));

        // Step 4: Find best emission factor (prefer regional, fall back to GLOBAL)
        $stmt = $pdo->prepare('
            SELECT * FROM emission_factors
            WHERE activity_type = \'electricity\'
              AND scope = \'Scope 2 Location-Based\'
              AND is_active = 1
              AND (region = :region OR region = \'GLOBAL\')
            ORDER BY
                CASE WHEN region = :region2 THEN 0 ELSE 1 END
            LIMIT 1
        ');
        $stmt->execute([':region' => $region, ':region2' => $region]);
        $factor = $stmt->fetch();

        if (!$factor) {
            $error = 'No active emission factor found for electricity. Please add one in the Emission Factors library.';
        } else {
            // Step 5: Calculate tCO2e (factor is kg CO2e per kWh, divide by 1000 for tCO2e)
            $tco2e = ($consumptionKwh * (float)$factor['factor']) / 1000;

            // Step 6: Insert emission record
            $stmt = $pdo->prepare('
                INSERT INTO emission_records (id, company_id, scope, tco2e_calculated, energy_activity_id, emission_factor_id, date_calculated, created_at)
                VALUES (:id, :company_id, \'Scope 2 Location-Based\', :tco2e, :energy_id, :ef_id, NOW(), NOW())
            ');
            $stmt->execute([
                ':id'         => uuid(),
                ':company_id' => company_id(),
                ':tco2e'      => $tco2e,
                ':energy_id'  => $energyId,
                ':ef_id'      => $factor['id'],
            ]);

            $tco2eResult = $tco2e;
            $success = 'Energy activity recorded successfully. Factor used: ' . htmlspecialchars($factor['region'], ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars($factor['source'] ?? '', ENT_QUOTES, 'UTF-8') . ')';
        }
    }
}

// Load recent energy activities
$stmt = $pdo->prepare('
    SELECT ea.*, s.name AS site_name,
           er.tco2e_calculated,
           ef.region AS ef_region, ef.source AS ef_source
    FROM energy_activities ea
    JOIN sites s ON ea.site_id = s.id
    LEFT JOIN emission_records er ON er.energy_activity_id = ea.id
    LEFT JOIN emission_factors ef ON er.emission_factor_id = ef.id
    WHERE s.company_id = :company_id
    ORDER BY ea.created_at DESC
    LIMIT 25
');
$stmt->execute([':company_id' => company_id()]);
$recentActivities = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Energy Consumption</h2>
        <p class="text-gray-500 text-base mt-1">Record Scope 2 indirect emissions from purchased energy</p>
    </div>

    <?php if ($success): ?>
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
        <div class="flex items-start space-x-3">
            <svg class="w-5 h-5 text-emerald-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <p class="text-sm font-medium text-emerald-800"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($tco2eResult !== null): ?>
                <p class="text-sm text-emerald-700 mt-1">
                    Calculated emissions: <strong class="text-lg"><?= number_format($tco2eResult, 4) ?> tCO2e</strong>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 flex items-center space-x-2">
        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p class="text-sm text-red-700"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <?php endif; ?>

    <!-- Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <h3 class="text-base font-semibold text-gray-800">Record Energy Consumption</h3>
            <p class="text-sm text-gray-500 mt-0.5">Scope 2 — Indirect emissions from purchased electricity, heat, steam, or cooling</p>
        </div>
        <div class="p-6">
            <form method="POST">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <!-- Site -->
                    <div>
                        <label for="site_id" class="block text-sm font-medium text-gray-700 mb-1">Site <span class="text-red-500">*</span></label>
                        <select id="site_id" name="site_id" required
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white">
                            <option value="">Loading sites...</option>
                        </select>
                    </div>

                    <!-- Month -->
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Reporting Month <span class="text-red-500">*</span></label>
                        <input type="month" id="date" name="date" required
                               value="<?= htmlspecialchars($_POST['date'] ?? date('Y-m'), ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    </div>

                    <!-- Energy Type -->
                    <div>
                        <label for="energy_type" class="block text-sm font-medium text-gray-700 mb-1">Energy Type <span class="text-red-500">*</span></label>
                        <select id="energy_type" name="energy_type" required
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white">
                            <option value="">Select type...</option>
                            <option value="electricity"       <?= ($_POST['energy_type'] ?? '') === 'electricity'       ? 'selected' : '' ?>>Electricity</option>
                            <option value="district_heating"  <?= ($_POST['energy_type'] ?? '') === 'district_heating'  ? 'selected' : '' ?>>District Heating</option>
                            <option value="steam"             <?= ($_POST['energy_type'] ?? '') === 'steam'             ? 'selected' : '' ?>>Steam</option>
                            <option value="cooling"           <?= ($_POST['energy_type'] ?? '') === 'cooling'           ? 'selected' : '' ?>>Cooling</option>
                        </select>
                    </div>

                    <!-- Consumption -->
                    <div>
                        <label for="consumption" class="block text-sm font-medium text-gray-700 mb-1">Consumption <span class="text-red-500">*</span></label>
                        <input type="number" id="consumption" name="consumption" required step="0.01" min="0.01"
                               value="<?= htmlspecialchars($_POST['consumption'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                               placeholder="e.g. 10000">
                    </div>

                    <!-- Unit -->
                    <div>
                        <label for="unit" class="block text-sm font-medium text-gray-700 mb-1">Unit <span class="text-red-500">*</span></label>
                        <select id="unit" name="unit" required
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white">
                            <option value="">Select unit...</option>
                            <option value="kWh" <?= ($_POST['unit'] ?? '') === 'kWh' ? 'selected' : '' ?>>kWh</option>
                            <option value="MWh" <?= ($_POST['unit'] ?? '') === 'MWh' ? 'selected' : '' ?>>MWh</option>
                            <option value="GJ"  <?= ($_POST['unit'] ?? '') === 'GJ'  ? 'selected' : '' ?>>GJ</option>
                        </select>
                    </div>

                    <!-- Info note -->
                    <div class="flex items-start">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs text-blue-700 w-full">
                            <strong>Note:</strong> Electricity emission factor is automatically selected based on your company's country of registration. MWh and GJ are normalised to kWh before calculation.
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-8 rounded-lg text-base transition">
                        Save & Calculate
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-800">Recent Energy Activities</h3>
            <span class="text-sm text-gray-500">Last 25 records</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-base">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Date</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Site</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Energy Type</th>
                        <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Consumption</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Unit</th>
                        <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">tCO2e</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Region</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($recentActivities as $i => $a): ?>
                    <tr class="<?= $i % 2 === 1 ? 'bg-gray-50' : 'bg-white' ?>">
                        <td class="py-3 px-4 text-gray-600"><?= htmlspecialchars(date('M Y', strtotime($a['date'])), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3 px-4 font-medium text-gray-900"><?= htmlspecialchars($a['site_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3 px-4 text-gray-700 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $a['energy_type']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3 px-4 text-right font-mono text-gray-900"><?= number_format((float)$a['consumption'], 2) ?></td>
                        <td class="py-3 px-4 text-gray-600"><?= htmlspecialchars($a['unit'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3 px-4 text-right">
                            <?php if ($a['tco2e_calculated'] !== null): ?>
                            <span class="font-semibold text-blue-700"><?= number_format((float)$a['tco2e_calculated'], 4) ?></span>
                            <?php else: ?>
                            <span class="text-gray-400">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-gray-500 text-sm"><?= htmlspecialchars($a['ef_region'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentActivities)): ?>
                    <tr><td colspan="7" class="py-8 text-center text-gray-400">No energy activities recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Populate sites dropdown via AJAX
$.getJSON('/esg-report-test/api/sites.php', function(sites) {
    var $sel = $('#site_id');
    $sel.empty().append('<option value="">Select a site...</option>');
    if (sites.length === 0) {
        $sel.append('<option value="" disabled>No sites found — add a site first</option>');
    }
    $.each(sites, function(i, s) {
        var opt = $('<option>').val(s.id).text(s.name);
        <?php if (!empty($_POST['site_id'])): ?>
        if (s.id === '<?= addslashes($_POST['site_id'] ?? '') ?>') opt.prop('selected', true);
        <?php endif; ?>
        $sel.append(opt);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
