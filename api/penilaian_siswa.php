<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/syarat_konten.php';
require_once dirname(__DIR__) . '/includes/rekap_tugas.php';

session_start();
header('Content-Type: application/json');

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Khusus guru']);
    exit;
}

$uid = (int) ($_GET['id'] ?? 0);
$det = $uid ? rt_detail($uid) : null;
if (!$det) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'msg' => 'Siswa tidak ditemukan']);
    exit;
}

echo json_encode(['ok' => true] + $det, JSON_UNESCAPED_UNICODE);
