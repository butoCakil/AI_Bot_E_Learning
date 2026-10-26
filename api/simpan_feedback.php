<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/syarat_konten.php';
require_once dirname(__DIR__) . '/includes/feedback.php';

session_start();
header('Content-Type: application/json');

function keluar(int $code, array $data) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'guru') keluar(403, ['ok' => false, 'msg' => 'Khusus guru']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST')                     keluar(405, ['ok' => false, 'msg' => 'Metode tidak diizinkan']);

$uid      = (int) ($_POST['user_id'] ?? 0);
$cid      = (int) ($_POST['content_id'] ?? 0);
$predikat = (int) ($_POST['predikat'] ?? 0);
$komentar = mb_substr(trim((string) ($_POST['komentar'] ?? '')), 0, 1000);

if (!$uid || !$cid) keluar(400, ['ok' => false, 'msg' => 'Parameter tidak lengkap']);

$konten = ambil_konten($cid);
if (!$konten || $konten['tipe'] === 'evaluasi') keluar(404, ['ok' => false, 'msg' => 'Konten tidak ditemukan']);

$pdo = db();
$st  = $pdo->prepare("SELECT id FROM jobsheet_submissions WHERE user_id = ? AND content_id = ?");
$st->execute([$uid, $cid]);
$sub_id = $st->fetchColumn();
$st = $pdo->prepare("SELECT 1 FROM tabel_input_jawaban WHERE user_id = ? AND content_id = ?");
$st->execute([$uid, $cid]);
$ada_tabel = (bool) $st->fetchColumn();
if (!$sub_id && !$ada_tabel) keluar(400, ['ok' => false, 'msg' => 'Siswa belum mengirim tugas ini']);

$skala = fb_skala_konten($konten);
if ($predikat < 1 || $predikat > fb_maks($skala)) keluar(400, ['ok' => false, 'msg' => 'Pilih predikat dulu']);

$minta = $predikat <= 2;   // revisi ditentukan predikat: ★1–2 / Belum–Hampir sesuai
if ($minta && $komentar === '') {
    keluar(400, ['ok' => false, 'msg' => 'Tulis komentar: siswa perlu tahu apa yang harus diperbaiki']);
}

// Nilai angka (hanya tugas wajib upload, opsional)
$raw_nilai = str_replace(',', '.', trim((string) ($_POST['nilai'] ?? '')));
if ($skala === 'bintang' && $sub_id && !$minta) {
    if ($raw_nilai === '')       keluar(400, ['ok' => false, 'msg' => 'Isi nilai angka 0–100 untuk predikat ini']);
    if (!is_numeric($raw_nilai)) keluar(400, ['ok' => false, 'msg' => 'Nilai harus angka 0–100']);
    $nilai = max(0, min(100, (float) $raw_nilai));
    $pdo->prepare("UPDATE jobsheet_submissions SET nilai = ?, dinilai_at = NOW() WHERE id = ?")->execute([$nilai, $sub_id]);
}

$guru_id = isset($_SESSION['guru_id']) ? (int) $_SESSION['guru_id'] : null;
fb_simpan($uid, $konten, $predikat, $komentar, $minta, $guru_id);

keluar(200, ['ok' => true, 'skala' => $skala, 'label' => fb_label($skala, $predikat), 'minta_revisi' => $minta]);
