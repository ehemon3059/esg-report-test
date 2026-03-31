<?php
require_once '../includes/auth.php';
require_login();
require_once '../config/db.php';
require_once '../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'POST required'], 405);
}

$allowed = ['social_topics', 'environmental_topics', 's_governance', 'eu_taxonomy', 'assurance'];
$table   = $_POST['table'] ?? '';
$status  = $_POST['status'] ?? '';
$id      = $_POST['id'] ?? '';

if (!in_array($table, $allowed)) {
    json_response(['error' => 'Invalid table'], 400);
}

if ($id === '' || $status === '') {
    json_response(['error' => 'Missing parameters'], 400);
}

// Only admins can approve, publish, or reject
if (in_array($status, ['APPROVED', 'PUBLISHED', 'REJECTED']) && !is_admin()) {
    json_response(['error' => 'Unauthorized — admin role required'], 403);
}

// Determine valid statuses per table
$validStatuses = [
    'environmental_topics' => ['DRAFT', 'UNDER_REVIEW', 'APPROVED', 'PUBLISHED', 'REJECTED'],
    'social_topics'        => ['DRAFT', 'UNDER_REVIEW', 'APPROVED', 'PUBLISHED', 'REJECTED'],
    's_governance'         => ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED'],
    'eu_taxonomy'          => ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED'],
    'assurance'            => ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED'],
];

if (!in_array($status, $validStatuses[$table] ?? [])) {
    json_response(['error' => 'Invalid status for this table'], 400);
}

// Use table names directly — they are whitelisted above
$stmt = $pdo->prepare("UPDATE {$table} SET status = :s, updated_by = :uid WHERE id = :id AND company_id = :cid");
$stmt->execute([
    ':s'   => $status,
    ':uid' => user_id(),
    ':id'  => $id,
    ':cid' => company_id(),
]);

if ($stmt->rowCount() === 0) {
    json_response(['error' => 'Record not found or no permission'], 404);
}

json_response(['success' => true, 'new_status' => $status]);
