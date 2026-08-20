<?php
// ============================================================
// AdaptLearn PRE — Route Check (dipanggil elearning-bridge, BUKAN user)
// POST JSON: {"phone_number":"62xxx"}  + ?token=<wa_webhook_token>
// Respons  : {"success":true,"is_user":true|false}
//
// Dipakai WA Gateway untuk auto-detect: "nomor ini siswa/guru kami?"
// Harus cepat — gateway hanya menunggu 3 detik total.
// Taruh di folder api/ (sejajar wa_webhook.php).
// ============================================================
require_once '../config/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Validasi token — nilai diambil dari tabel pengaturan
$expected = get_pengaturan('wa_webhook_token', '');
$given    = $_GET['token'] ?? '';
if ($expected === '' || !hash_equals($expected, $given)) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

$data  = json_decode(file_get_contents('php://input'), true) ?: [];
$phone = preg_replace('/\D/', '', (string)($data['phone_number'] ?? ''));

if ($phone === '') {
    exit(json_encode(['success' => true, 'is_user' => false]));
}

try {
    $pdo  = db();
    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE nomor_wa = ? LIMIT 1");
    $stmt->execute([$phone]);
    $isUser = (bool) $stmt->fetchColumn();
    exit(json_encode(['success' => true, 'is_user' => $isUser]));
} catch (Throwable $e) {
    error_log('[route_check] ' . $e->getMessage());
    // Saat error database, jawab false — gateway tetap mendapat kepastian
    exit(json_encode(['success' => true, 'is_user' => false]));
}
