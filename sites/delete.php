<?php
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /esg-report-test/sites/index.php');
    exit;
}

$id = $_POST['id'] ?? '';

if ($id !== '') {
    $stmt = $pdo->prepare('
        UPDATE sites
        SET deleted_at = NOW(), deleted_by = :deleted_by
        WHERE id = :id AND company_id = :company_id AND deleted_at IS NULL
    ');
    $stmt->execute([
        ':deleted_by'  => user_id(),
        ':id'          => $id,
        ':company_id'  => company_id(),
    ]);
}

header('Location: /esg-report-test/sites/index.php?deleted=1');
exit;
