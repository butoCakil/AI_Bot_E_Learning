<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

session_start();
header('Content-Type: application/json');

function keluar(int $code, array $data) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'guru') keluar(403, ['ok' => false, 'msg' => 'Khusus guru']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST')                     keluar(405, ['ok' => false, 'msg' => 'Metode tidak diizinkan']);

$sid = (int) ($_POST['submission_id'] ?? 0);
$raw = str_replace(',', '.', trim($_POST['nilai'] ?? ''));
if (!$sid || $raw === '' || !is_numeric($raw)) keluar(400, ['ok' => false, 'msg' => 'Nilai harus angka 0–100']);

$nilai = max(0, min(100, (float) $raw));
$pdo   = db();

$cek = $pdo->prepare("SELECT 1 FROM jobsheet_submissions WHERE id = ?");
$cek->execute([$sid]);
if (!$cek->fetchColumn()) keluar(404, ['ok' => false, 'msg' => 'Kiriman tidak ditemukan']);

$pdo->prepare("UPDATE jobsheet_submissions SET nilai = ?, dinilai_at = NOW() WHERE id = ?")->execute([$nilai, $sid]);
keluar(200, ['ok' => true, 'nilai' => $nilai]);
