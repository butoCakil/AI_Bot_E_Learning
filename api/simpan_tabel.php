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

if (empty($_SESSION['user_id'])) gagal(401, 'Belum login');

$user_id    = (int) $_SESSION['user_id'];
$content_id = (int) ($_POST['content_id'] ?? 0);
$topik      = trim($_POST['topik'] ?? '');
$input      = json_decode($_POST['jawaban'] ?? '', true);

if (!$content_id || $topik === '' || !is_array($input)) gagal(400, 'Parameter tidak lengkap');

$konten = ambil_konten($content_id);
if (!$konten || $konten['tipe'] === 'evaluasi') gagal(404, 'Konten tidak ditemukan');

$sel = ti_daftar_sel($konten['isi'] ?? '');
if (!$sel) gagal(400, 'Konten ini tidak memiliki tabel isian');
if (fb_terkunci($user_id, $content_id)) gagal(423, 'Isian sudah dinilai guru dan dikunci');

// Hanya simpan kunci yang memang ada di konten
$bersih = [];
$kosong = 0;
$detail = ti_detail_sel($konten['isi'] ?? '');
foreach ($sel as $k) {
    $v = mb_substr(trim((string) ($input[$k] ?? '')), 0, 200);
    $pil = $detail[$k]['pilihan'] ?? null;
    if ($pil && $v !== '' && !in_array($v, $pil, true)) gagal(400, 'Pilihan isian tidak valid, muat ulang halaman');
    $bersih[$k] = $v;
    if ($v === '') $kosong++;
}
$lengkap = $kosong === 0 ? 1 : 0;

$st = db()->prepare("
    INSERT INTO tabel_input_jawaban (user_id, content_id, topik, jawaban, lengkap)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        topik   = VALUES(topik),
        jawaban = VALUES(jawaban),
        lengkap = VALUES(lengkap)
");
$st->execute([$user_id, $content_id, $topik, json_encode($bersih, JSON_UNESCAPED_UNICODE), $lengkap]);

log_aktivitas($user_id, 'isi_tabel', $content_id, $topik, ['kosong' => $kosong, 'total' => count($sel)]);

$s = tandai_selesai_jika_lengkap($user_id, $konten, $topik);
echo json_encode(['ok' => true, 'lengkap' => (bool) $lengkap, 'kosong' => $kosong, 'total' => count($sel)] + $s, JSON_UNESCAPED_UNICODE);
