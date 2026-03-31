<?php
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';

$stmt = $pdo->prepare('SELECT id, name FROM sites WHERE company_id = :cid AND deleted_at IS NULL ORDER BY name');
$stmt->execute([':cid' => company_id()]);

header('Content-Type: application/json');
echo json_encode($stmt->fetchAll());
