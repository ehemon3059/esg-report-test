<?php
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';

$pageTitle = 'Emissions Dashboard';

// Emission totals by scope
$stmt = $pdo->prepare('
    SELECT scope,
           ROUND(SUM(tco2e_calculated), 4) AS total_tco2e,
           COUNT(*) AS record_count
    FROM emission_records
    WHERE company_id = :cid
    GROUP BY scope
');
$stmt->execute([':cid' => company_id()]);
$scopeTotals = [];
$totalRecords = 0;
foreach ($stmt->fetchAll() as $r) {
    $scopeTotals[$r['scope']] = $r['total_tco2e'];
    $totalRecords += $r['record_count'];
}

$scope1Total = (float)($scopeTotals['Scope 1'] ?? 0);
$scope2Total = (float)($scopeTotals['Scope 2 Location-Based'] ?? 0) + (float)($scopeTotals['Scope 2 Market-Based'] ?? 0);
$grandTotal  = $scope1Total + $scope2Total;

// Recent calculations
$stmt = $pdo->prepare('
    SELECT er.scope, er.tco2e_calculated, er.date_calculated,
           COALESCE(fa.fuel_type, ea.energy_type) AS activity_name,
           COALESCE(fa.volume, ea.consumption) AS input_value,
           COALESCE(fa.unit, ea.unit) AS input_unit,
           ef.factor, ef.unit AS factor_unit, ef.source,
           s.name AS site_name
    FROM emission_records er
    LEFT JOIN fuel_activities fa    ON er.fuel_activity_id  = fa.id
    LEFT JOIN energy_activities ea  ON er.energy_activity_id = ea.id
    LEFT JOIN emission_factors ef   ON er.emission_factor_id = ef.id
    LEFT JOIN sites s ON COALESCE(fa.site_id, ea.site_id) = s.id
    WHERE er.company_id = :company_id
    ORDER BY er.date_calculated DESC
    LIMIT 20
');
$stmt->execute([':company_id' => company_id()]);
$recentCalcs = $stmt->fetchAll();

// Company info for welcome message
$stmt = $pdo->prepare('SELECT name FROM companies WHERE id = :id AND deleted_at IS NULL');
$stmt->execute([':id' => company_id()]);
$company = $stmt->fetch();

require_once '../includes/header.php';
?>

<div class="space-y-6">
    <!-- Welcome Banner -->
    <div class="bg-gradient-to-r from-emerald-600 to-teal-600 rounded-xl p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold">Welcome back, <?= htmlspecialchars($_SESSION['name'] ?? 'User', ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="text-emerald-100 text-base mt-1"><?= htmlspecialchars($company['name'] ?? '', ENT_QUOTES, 'UTF-8') ?> — GHG Emissions Dashboard</p>
            </div>
            <div class="text-right">
                <p class="text-emerald-200 text-sm">Total Records</p>
                <p class="text-3xl font-bold"><?= $totalRecords ?></p>
            </div>
        </div>
    </div>

    <!-- Period Filter -->
    <div class="flex items-center space-x-3">
        <label class="text-sm font-medium text-gray-700">View Period:</label>
        <select id="periodFilter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none bg-white">
            <option value="all" selected>All Time</option>
            <option value="last30">Last 30 Days</option>
            <option value="last_quarter">Last Quarter</option>
            <option value="ytd">Year to Date</option>
        </select>
        <div id="periodLoading" class="hidden">
            <svg class="w-4 h-4 text-emerald-600 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        </div>
    </div>

    <!-- Scope Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <!-- Scope 1 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" id="card-scope1">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/>
                    </svg>
                </div>
                <span class="text-xs font-medium text-green-700 bg-green-50 px-2.5 py-1 rounded-full">Scope 1</span>
            </div>
            <p class="text-sm text-gray-500 mb-1">Direct Emissions</p>
            <p class="text-3xl font-bold text-gray-900" id="val-scope1"><?= number_format($scope1Total, 4) ?></p>
            <p class="text-sm text-gray-400 mt-1">tCO2e</p>
        </div>

        <!-- Scope 2 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" id="card-scope2">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <span class="text-xs font-medium text-blue-700 bg-blue-50 px-2.5 py-1 rounded-full">Scope 2</span>
            </div>
            <p class="text-sm text-gray-500 mb-1">Indirect Emissions</p>
            <p class="text-3xl font-bold text-gray-900" id="val-scope2"><?= number_format($scope2Total, 4) ?></p>
            <p class="text-sm text-gray-400 mt-1">tCO2e</p>
        </div>

        <!-- Total -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" id="card-total">
            <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <span class="text-xs font-medium text-gray-700 bg-gray-100 px-2.5 py-1 rounded-full">Total</span>
            </div>
            <p class="text-sm text-gray-500 mb-1">Combined Emissions</p>
            <p class="text-3xl font-bold text-gray-900" id="val-total"><?= number_format($grandTotal, 4) ?></p>
            <p class="text-sm text-gray-400 mt-1">tCO2e (Scope 1 + 2)</p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <a href="/esg-report-test/phase3/fuel.php" class="bg-white border border-gray-200 rounded-xl p-4 flex items-center space-x-3 hover:border-emerald-300 hover:shadow-sm transition group">
            <div class="w-8 h-8 bg-emerald-50 rounded-lg flex items-center justify-center group-hover:bg-emerald-100 transition">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </div>
            <span class="text-sm font-medium text-gray-700">Add Fuel</span>
        </a>
        <a href="/esg-report-test/phase3/energy.php" class="bg-white border border-gray-200 rounded-xl p-4 flex items-center space-x-3 hover:border-emerald-300 hover:shadow-sm transition group">
            <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center group-hover:bg-blue-100 transition">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </div>
            <span class="text-sm font-medium text-gray-700">Add Energy</span>
        </a>
        <a href="/esg-report-test/sites/create.php" class="bg-white border border-gray-200 rounded-xl p-4 flex items-center space-x-3 hover:border-emerald-300 hover:shadow-sm transition group">
            <div class="w-8 h-8 bg-purple-50 rounded-lg flex items-center justify-center group-hover:bg-purple-100 transition">
                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </div>
            <span class="text-sm font-medium text-gray-700">Add Site</span>
        </a>
        <a href="/esg-report-test/report/generate.php" class="bg-white border border-gray-200 rounded-xl p-4 flex items-center space-x-3 hover:border-emerald-300 hover:shadow-sm transition group">
            <div class="w-8 h-8 bg-amber-50 rounded-lg flex items-center justify-center group-hover:bg-amber-100 transition">
                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <span class="text-sm font-medium text-gray-700">Generate PDF</span>
        </a>
    </div>

    <!-- Recent Calculations Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-800">Recent Emission Calculations</h3>
            <span class="text-sm text-gray-500">Last 20 entries</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-base">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Date</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Site</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Activity</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Scope</th>
                        <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">Input</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Unit</th>
                        <th class="text-right py-3 px-4 text-sm font-semibold text-gray-600">tCO2e</th>
                        <th class="text-left py-3 px-4 text-sm font-semibold text-gray-600">Source</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($recentCalcs as $i => $r): ?>
                    <tr class="<?= $i % 2 === 1 ? 'bg-gray-50' : 'bg-white' ?>">
                        <td class="py-3 px-4 text-gray-500 text-sm"><?= date('d M Y', strtotime($r['date_calculated'])) ?></td>
                        <td class="py-3 px-4 text-gray-700"><?= htmlspecialchars($r['site_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3 px-4 font-medium text-gray-900 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $r['activity_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3 px-4">
                            <?php
                            $cls = match(true) {
                                str_starts_with($r['scope'], 'Scope 1') => 'bg-green-100 text-green-800',
                                str_starts_with($r['scope'], 'Scope 2') => 'bg-blue-100 text-blue-800',
                                default => 'bg-gray-100 text-gray-700',
                            };
                            ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= $cls ?>">
                                <?= htmlspecialchars($r['scope'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right font-mono text-gray-900"><?= $r['input_value'] !== null ? number_format((float)$r['input_value'], 2) : '—' ?></td>
                        <td class="py-3 px-4 text-gray-500"><?= htmlspecialchars($r['input_unit'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="py-3 px-4 text-right font-bold text-emerald-700"><?= number_format((float)$r['tco2e_calculated'], 4) ?></td>
                        <td class="py-3 px-4 text-gray-400 text-sm"><?= htmlspecialchars($r['source'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentCalcs)): ?>
                    <tr>
                        <td colspan="8" class="py-10 text-center text-gray-400">
                            No emission records yet. Start by adding fuel or energy consumption.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$('#periodFilter').on('change', function() {
    var period = $(this).val();
    $('#periodLoading').removeClass('hidden');

    $.getJSON('/esg-report-test/api/emissions-summary.php', { period: period }, function(data) {
        if (data.error) {
            console.error(data.error);
            return;
        }
        $('#val-scope1').text(parseFloat(data.scope1_total || 0).toFixed(4));
        $('#val-scope2').text(parseFloat(data.scope2_total || 0).toFixed(4));
        $('#val-total').text(parseFloat(data.grand_total || 0).toFixed(4));
    }).always(function() {
        $('#periodLoading').addClass('hidden');
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
