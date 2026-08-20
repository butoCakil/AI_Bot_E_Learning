<?php
/* ============================================================
   API Evaluasi — AdaptLearn PRE
   ------------------------------------------------------------
   Menerima jawaban evaluasi, menghitung skor, menyimpan hasil
   ke tabel `evaluasi_results`, lalu redirect ke hasil_evaluasi.php

   PERUBAHAN dari versi lama:
   - Konten evaluasi diambil berdasarkan content_id (bukan topik),
     supaya tetap benar jika 1 topik punya >1 evaluasi.
   - Hasil disimpan permanen di `evaluasi_results` (bukan hanya
     session), agar pembahasan bisa dibuka kapan saja.
   - Tetap menulis ke activity_log tipe 'jawab_quiz' agar
     perhitungan progress di home.php / materi.php tidak berubah.
   ============================================================ */

session_start();
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

$user_id         = (int) $_SESSION['user_id'];
$topik           = $_POST['topik'] ?? '';
$profil_gabungan = $_POST['profil_gabungan'] ?? '';
$jawaban         = $_POST['jawaban'] ?? [];
$content_id      = isset($_POST['content_id']) ? (int) $_POST['content_id'] : 0;

if (!$topik || !$profil_gabungan || empty($jawaban)) {
    header('Location: /materi.php?topik=' . urlencode($topik));
    exit;
}

$pdo = db();

// ── Ambil konten evaluasi ────────────────────────────────────
// Utamakan content_id (akurat). Fallback ke topik demi kompatibilitas
// dengan form lama yang belum mengirim content_id.
if ($content_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM `content` WHERE id = ? AND tipe = 'evaluasi' LIMIT 1");
    $stmt->execute([$content_id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM `content` WHERE topik = ? AND tipe = 'evaluasi' LIMIT 1");
    $stmt->execute([$topik]);
}
$konten = $stmt->fetch();

if (!$konten) {
    header('Location: /materi.php?topik=' . urlencode($topik));
    exit;
}

$content_id = (int) $konten['id'];
$soal_list  = json_decode($konten['isi'], true) ?? [];

if (empty($soal_list)) {
    header('Location: /materi.php?topik=' . urlencode($topik) . '&konten=' . $content_id);
    exit;
}

// ── Hitung skor ──────────────────────────────────────────────
$skor       = 0;
$total      = count($soal_list);
$hasil_soal = [];

foreach ($soal_list as $i => $soal) {
    $jwb_siswa = strtoupper(trim($jawaban[$i] ?? ''));
    $kunci     = strtoupper(trim($soal['kunci'] ?? ''));
    $benar     = ($jwb_siswa !== '' && $jwb_siswa === $kunci);
    if ($benar) $skor++;

    $hasil_soal[] = [
        'soal'    => $soal['soal'] ?? '',
        'opsi'    => $soal['opsi'] ?? [],
        'jawaban' => $jwb_siswa,
        'kunci'   => $kunci,
        'benar'   => $benar,
    ];
}

$persentase = $total > 0 ? (int) round(($skor / $total) * 100) : 0;

// ── Simpan permanen ke evaluasi_results ──────────────────────
// UNIQUE(user_id, content_id): jika mengulang, baris di-update
// dan kolom percobaan bertambah.
$stmt_simpan = $pdo->prepare("
    INSERT INTO `evaluasi_results`
        (user_id, content_id, topik, jawaban, hasil_soal, skor, total, persentase, profil_gabungan, percobaan)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE
        jawaban         = VALUES(jawaban),
        hasil_soal      = VALUES(hasil_soal),
        skor            = VALUES(skor),
        total           = VALUES(total),
        persentase      = VALUES(persentase),
        profil_gabungan = VALUES(profil_gabungan),
        percobaan       = percobaan + 1
");
$stmt_simpan->execute([
    $user_id,
    $content_id,
    $topik,
    json_encode($jawaban, JSON_UNESCAPED_UNICODE),
    json_encode($hasil_soal, JSON_UNESCAPED_UNICODE),
    $skor,
    $total,
    $persentase,
    $profil_gabungan,
]);

// ── Log aktivitas (dipakai perhitungan progress) ─────────────
log_aktivitas($user_id, 'jawab_quiz', $content_id, $topik, [
    'skor'            => $skor,
    'total'           => $total,
    'persentase'      => $persentase,
    'profil_gabungan' => $profil_gabungan,
]);

log_aktivitas($user_id, 'selesai_materi', $content_id, $topik);

// Konten evaluasi tidak melewati /api/selesai_materi.php,
// jadi tandai selesai di sini agar ikut terhitung progress.
log_aktivitas($user_id, 'selesai_materi', $content_id, $topik);

header('Location: /hasil_evaluasi.php?konten=' . $content_id);
exit;
