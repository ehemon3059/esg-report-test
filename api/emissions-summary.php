<?php
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';
require_once '../includes/helpers.php';

$period = $_GET['period'] ?? 'all';

$dateFilter = '';
$params = [':cid' => company_id()];

switch ($period) {
    case 'last30':
        $dateFilter = 'AND date_calculated >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        break;
    case 'last_quarter':
        $dateFilter = 'AND date_calculated >= DATE_SUB(NOW(), INTERVAL 3 MONTH)';
        break;
    case 'ytd':
        $dateFilter = 'AND YEAR(date_calculated) = YEAR(NOW())';
        break;
    default:
        $dateFilter = '';
        break;
}

$sql = "
    SELECT scope,
           ROUND(SUM(tco2e_calculated), 4) AS total,
           COUNT(*) AS cnt
    FROM emission_records
    WHERE company_id = :cid
    {$dateFilter}
    GROUP BY scope
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$scope1 = 0;
$scope2 = 0;
$recordCount = 0;

foreach ($stmt->fetchAll() as $r) {
    $recordCount += $r['cnt'];
    if ($r['scope'] === 'Scope 1') {
        $scope1 = (float)$r['total'];
    } elseif (str_starts_with($r['scope'], 'Scope 2')) {
        $scope2 += (float)$r['total'];
    }
}

json_response([
    'scope1_total'  => $scope1,
    'scope2_total'  => $scope2,
    'grand_total'   => $scope1 + $scope2,
    'record_count'  => $recordCount,
    'period'        => $period,
]);
