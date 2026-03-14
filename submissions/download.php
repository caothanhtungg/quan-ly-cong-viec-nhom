<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_login();

$user = current_user();
$conn = getConnection();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(404);
    exit('File không tồn tại.');
}

$stmt = sqlsrv_query(
    $conn,
    "SELECT TOP 1
        s.id,
        s.file_name,
        s.file_path,
        s.submitted_by,
        t.assigned_to,
        t.team_id
     FROM submissions s
     INNER JOIN tasks t ON s.task_id = t.id
     WHERE s.id = ?",
    [$id]
);
$submission = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;

if (!$submission) {
    http_response_code(404);
    exit('File không tồn tại.');
}

$userId = (int)$user['id'];
$teamId = (int)($user['team_id'] ?? 0);
$canAccess = false;

if ($user['role'] === 'admin') {
    $canAccess = true;
} elseif ($user['role'] === 'leader' && $teamId > 0 && $teamId === (int)$submission['team_id']) {
    $canAccess = true;
} elseif ($user['role'] === 'member' && $userId === (int)$submission['submitted_by']) {
    $canAccess = true;
}

if (!$canAccess) {
    http_response_code(403);
    exit('Bạn không có quyền truy cập file này.');
}

$projectRoot = realpath(__DIR__ . '/..');
$relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$submission['file_path']);
$filePath = $projectRoot . DIRECTORY_SEPARATOR . ltrim($relativePath, DIRECTORY_SEPARATOR);
$realFilePath = realpath($filePath);
$allowedRoot = realpath(__DIR__ . '/../assets/uploads/submissions');

if (
    !$realFilePath ||
    !$allowedRoot ||
    strpos($realFilePath, $allowedRoot) !== 0 ||
    !is_file($realFilePath)
) {
    http_response_code(404);
    exit('Không tìm thấy tệp trên máy chủ.');
}

$downloadName = basename((string)$submission['file_name']);
$mimeType = mime_content_type($realFilePath);

if ($mimeType === false) {
    $mimeType = 'application/octet-stream';
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . rawurlencode($downloadName) . '"');
header('Content-Length: ' . filesize($realFilePath));
header('X-Content-Type-Options: nosniff');
readfile($realFilePath);
exit;
