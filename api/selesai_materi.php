<?php
session_start();
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$user_id   = (int) $_SESSION['user_id'];
$content_id = (int) ($_POST['content_id'] ?? 0);
$topik     = $_POST['topik'] ?? '';

if (!$content_id || !$topik) {
    http_response_code(400);
    exit;
}

log_aktivitas($user_id, 'selesai_materi', $content_id, $topik);

http_response_code(200);
echo json_encode(['ok' => true]);
