<?php
session_start();
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/syarat_konten.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Belum login']);
    exit;
}

$user_id    = (int) $_SESSION['user_id'];
$content_id = (int) ($_POST['content_id'] ?? 0);
$topik      = trim($_POST['topik'] ?? '');

if (!$content_id || $topik === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Parameter tidak lengkap']);
    exit;
}

$konten = ambil_konten($content_id);
if (!$konten) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'msg' => 'Konten tidak ditemukan']);
    exit;
}

$s = tandai_selesai_jika_lengkap($user_id, $konten, $topik);
echo json_encode(['ok' => true] + $s, JSON_UNESCAPED_UNICODE);
