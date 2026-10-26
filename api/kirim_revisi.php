<?php
session_start();
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/syarat_konten.php';
require_once dirname(__DIR__) . '/includes/feedback.php';
header('Content-Type: application/json');

function gagal(int $code, string $msg) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['user_id']))            gagal(401, 'Belum login');
if ($_SERVER['REQUEST_METHOD'] !== 'POST')  gagal(405, 'Metode tidak diizinkan');

$uid = (int) $_SESSION['user_id'];
$cid = (int) ($_POST['content_id'] ?? 0);
if (!$cid) gagal(400, 'Parameter tidak lengkap');

$konten = ambil_konten($cid);
if (!$konten) gagal(404, 'Konten tidak ditemukan');

$fb = fb_ambil($uid, $cid);
if (!$fb || (int) $fb['minta_revisi'] !== 1) gagal(400, 'Tidak ada permintaan revisi untuk materi ini');

// Revisi hanya bisa dikirim bila kiriman lengkap
$s = syarat_konten($uid, $konten);
if (isset($s['item']['upload']) && !$s['item']['upload']['ok']) gagal(400, 'Upload file revisimu dulu');
if (isset($s['item']['tabel'])  && !$s['item']['tabel']['ok'])  gagal(400, 'Lengkapi dan simpan isian tabel dulu');

if (!fb_kirim_revisi($uid, $cid)) gagal(409, 'Revisi sudah terkirim');
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
