<?php
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';
require_once '../includes/helpers.php';

$pageTitle = 'Fuel Consumption';
$success   = '';
$error     = '';
$tco2eResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteId   = $_POST['site_id']   ?? '';
    $date     = $_POST['date']      ?? '';
    $fuelType = $_POST['fuel_type'] ?? '';
    $volume   = $_POST['volume']    ?? '';
    $unit     = $_POST['unit']      ?? '';

    $allowedFuels = ['diesel', 'petrol', 'natural_gas', 'lpg'];
    $allowedUnits = ['litre', 'm3', 'kg', 'tonne'];

    if ($siteId === '' || $date === '' || !in_array($fuelType, $allowedFuels) || !is_numeric($volume) || (float)$volume <= 0 || !in_array($unit, $allowedUnits)) {
        $error = 'Please fill all fields correctly.';
    } else {
        // Step 1: Insert fuel activity
        $fuelId = uuid();
        $stmt = $pdo->prepare('
            INSERT INTO fuel_activities (id, site_id, date, fuel_type, volume, unit, created_at, updated_at)
            VALUES (:id, :site_id, :date, :fuel_type, :volume, :unit, NOW(), NOW())
        ');
        $stmt->execute([
            ':id'        => $fuelId,
            ':site_id'   => $siteId,
            ':date'      => $date,
            ':fuel_type' => $fuelType,
            ':volume'    => $volume,
            ':unit'      => $unit,
        ]);

        // Step 2: Select emission factor
        $stmt = $pdo->prepare('
            SELECT * FROM emission_factors
            WHERE activity_type = :fuel_type
              AND scope = \'Scope 1\'
              AND is_active = 1
            ORDER BY
                CASE WHEN region = \'GLOBAL\' THEN 1 ELSE 0 END
            LIMIT 1
        ');
        $stmt->execute([':fuel_type' => $fuelType]);
        $factor = $stmt->fetch();

        if (!$factor) {
            $error = 'No active emission factor found for ' . htmlspecialchars($fuelType, ENT_QUOTES, 'UTF-8') . '. Please add one in the Emission Factors library.';
        } else {
            // Step 3: Calculate tCO2e
            $volumeValue = (float)$volume;
            $tco2e = ($volumeValue * (float)$factor['factor']) / 1000;

            // Step 4: Insert emission record
            $stmt = $pdo->prepare('
                INSERT INTO emission_records (id, company_id, scope, tco2e_calculated, fuel_activity_id, emission_factor_id, date_calculated, created_at)
                VALUES (:id, :company_id, \'Scope 1\', :tco2e, :fuel_id, :ef_id, NOW(), NOW())
            ');
            $stmt->execute([
                ':id'         => uuid(),
                ':company_id' => company_id(),
                ':tco2e'      => $tco2e,
                ':fuel_id'    => $fuelId,
                ':ef_id'      => $factor['id'],
            ]);

            $tco2eResult = $tco2e;
            $success = 'Fuel activity recorded successfully.';
        }
    }
}

// Load recent fuel activities
$stmt = $pdo->prepare('
    SELECT fa.*, s.name AS site_name,
           er.tco2e_calculated,
           ef.factor AS ef_factor, ef.source AS ef_source
    FROM fuel_activities fa
    JOIN sites s ON fa.site_id = s.id
    LEFT JOIN emission_records er ON er.fuel_activity_id = fa.id
    LEFT JOIN emission_factors ef ON er.emission_factor_id = ef.id
    WHERE s.company_id = :company_id
    ORDER BY fa.created_at DESC
    LIMIT 25
');
$stmt->execute([':company_id' => company_id()]);
$recentActivities = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Fuel Consumption</h2>
        <p class="text-gray-500 text-base mt-1">Record Scope 1 direct fuel combustion emissions</p>
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
            <h3 class="text-base font-semibold text-gray-800">Record Fuel Consumption</h3>
            <p class="text-sm text-gray-500 mt-0.5">Scope 1 — Direct emissions from owned/controlled sources</p>
        </div>
        <div class="p-6">
            <form method="POST" id="fuelForm">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <!-- Site -->
                    <div>
                        <label for="site_id" class="block text-sm font-medium text-gray-700 mb-1">Site <span class="text-red-500">*</span></label>
                        <select id="site_id" name="site_id" required
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white">
                            <option value="">Loading sites...</option>
                        </select>
                    </div>

                    <!-- Date -->
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-500">*</span></label>
                        <input type="date" id="date" name="date" required
                               value="<?= htmlspecialchars($_POST['date'] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    </div>

                    <!-- Fuel Type -->
                    <div>
                        <label for="fuel_type" class="block text-sm font-medium text-gray-700 mb-1">Fuel Type <span class="text-red-500">*</span></label>
                        <select id="fuel_type" name="fuel_type" required
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white">
                            <option value="">Select fuel type...</option>
                            <option value="diesel"      <?= ($_POST['fuel_type'] ?? '') === 'diesel'      ? 'selected' : '' ?>>Diesel</option>
                            <option value="petrol"      <?= ($_POST['fuel_type'] ?? '') === 'petrol'      ? 'selected' : '' ?>>Petrol / Gasoline</option>
                            <option value="natural_gas" <?= ($_POST['fuel_type'] ?? '') === 'natural_gas' ? 'selected' : '' ?>>Natural Gas</option>
                            <option value="lpg"         <?= ($_POST['fuel_type'] ?? '') === 'lpg'         ? 'selected' : '' ?>>LPG</option>
                        </select>
                    </div>

                    <!-- Volume -->
                    <div>
                        <label for="volume" class="block text-sm font-medium text-gray-700 mb-1">Volume / Quantity <span class="text-red-500">*</span></label>
                        <input type="number" id="volume" name="volume" required step="0.01" min="0.01"
                               value="<?= htmlspecialchars($_POST['volume'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                               placeholder="e.g. 500.00">
                    </div>

                    <!-- Unit -->
                    <div>
                        <label for="unit" class="block text-sm font-medium text-gray-700 mb-1">Unit <span class="text-red-500">*</span></label>
                        <select id="unit" name="unit" required
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white">
                            <option value="">Select unit...</option>
                            <option value="litre"  <?= ($_POST['unit'] ?? '') === 'litre'  ? 'selected' : '' ?>>Litre (L)</option>
                            <option value="m3"     <?= ($_POST['unit'] ?? '') === 'm3'     ? 'selected' : '' ?>>Cubic Metre (m³)</option>
                            <option value="kg"     <?= ($_POST['unit'] ?? '') === 'kg'     ? 'selected' : '' ?>>Kilogram (kg)</option>
                            <option value="tonne"  <?= ($_POST['unit'] ?? '') === 'tonne'  ? 'selected' : '' ?>>Tonne</option>
                        </select>
                    </div>

                    <!-- Calculate Preview -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estimated CO2e</label>
                        <div id="calcPreview" class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-base text-gray-500">
                            Fill fields to preview
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

    <!-- Recent Activities Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-800">Recent Fuel Activities</h3>
            <span class="text-sm text-gray-500">Last 25 records</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-base">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Date</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Site</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Fuel Type</th>
                        <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Volume</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Unit</th>
                        <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">tCO2e</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Factor Source</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($recentActivities as $i => $a): ?>
                    <tr class="<?= $i % 2 === 1 ? 'bg-gray-50' : 'bg-white' ?>">
                        <td class="py-3 px-4 text-gray-600"><?= htmlspecialchars($a['date'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3 px-4 font-medium text-gray-900"><?= htmlspecialchars($a['site_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3 px-4 text-gray-700 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $a['fuel_type']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3 px-4 text-right font-mono text-gray-900"><?= number_format((float)$a['volume'], 2) ?></td>
                        <td class="py-3 px-4 text-gray-600"><?= htmlspecialchars($a['unit'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3 px-4 text-right">
                            <?php if ($a['tco2e_calculated'] !== null): ?>
                            <span class="font-semibold text-emerald-700"><?= number_format((float)$a['tco2e_calculated'], 4) ?></span>
                            <?php else: ?>
                            <span class="text-gray-400">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-gray-500 text-sm"><?= htmlspecialchars($a['ef_source'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentActivities)): ?>
                    <tr><td colspan="7" class="py-8 text-center text-gray-400">No fuel activities recorded yet.</td></tr>
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

// Live calculation preview
function calcPreview() {
    var fuelType = $('#fuel_type').val();
    var volume   = parseFloat($('#volume').val());
    var unit     = $('#unit').val();

    if (!fuelType || !volume || volume <= 0 || !unit) {
        $('#calcPreview').text('Fill fields to preview').removeClass('text-emerald-700 font-semibold').addClass('text-gray-500');
        return;
    }

    $.post('/esg-report-test/api/calculate.php', { fuel_type: fuelType, volume: volume, unit: unit }, function(resp) {
        if (resp.tco2e !== undefined) {
            $('#calcPreview').html('<span class="text-emerald-700 font-semibold">~' + parseFloat(resp.tco2e).toFixed(4) + ' tCO2e</span>').removeClass('text-gray-500');
        } else {
            $('#calcPreview').text('No factor found').addClass('text-gray-500').removeClass('text-emerald-700');
        }
    }, 'json').fail(function() {
        $('#calcPreview').text('Preview unavailable');
    });
}

$('#fuel_type, #volume, #unit').on('change input', function() {
    clearTimeout(window.calcTimer);
    window.calcTimer = setTimeout(calcPreview, 400);
});
</script>

<?php require_once '../includes/footer.php'; ?>
