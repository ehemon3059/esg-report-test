<?php
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';
require_once '../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'POST required'], 405);
}

$fuelType = trim($_POST['fuel_type'] ?? '');
$volume   = (float)($_POST['volume'] ?? 0);
$unit     = trim($_POST['unit'] ?? '');

if ($fuelType === '' || $volume <= 0 || $unit === '') {
    json_response(['error' => 'Missing parameters'], 400);
}

$stmt = $pdo->prepare('
    SELECT * FROM emission_factors
    WHERE activity_type = :fuel_type
      AND scope = \'Scope 1\'
      AND is_active = 1
    ORDER BY CASE WHEN region = \'GLOBAL\' THEN 1 ELSE 0 END
    LIMIT 1
');
$stmt->execute([':fuel_type' => $fuelType]);
$factor = $stmt->fetch();

if (!$factor) {
    json_response(['error' => 'No emission factor found for ' . $fuelType], 404);
}

$tco2e = ($volume * (float)$factor['factor']) / 1000;

json_response([
    'tco2e'        => round($tco2e, 6),
    'factor_used'  => $factor['id'],
    'factor_value' => $factor['factor'],
    'factor_unit'  => $factor['unit'],
    'factor_source' => $factor['source'],
    'region'       => $factor['region'],
]);
