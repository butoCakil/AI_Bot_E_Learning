<?php
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

if (!$topik || !$profil_gabungan || empty($jawaban)) {
    header('Location: /materi.php?topik=' . urlencode($topik));
    exit;
}

// Ambil konten evaluasi untuk topik ini
$pdo  = db();
$stmt = $pdo->prepare("
    SELECT * FROM `content`
    WHERE topik = ? AND tipe = 'evaluasi'
    LIMIT 1
");
$stmt->execute([$topik]);
$konten = $stmt->fetch();

if (!$konten) {
    header('Location: /materi.php?topik=' . urlencode($topik));
    exit;
}

$soal_list = json_decode($konten['isi'], true) ?? [];

// Hitung skor
$skor       = 0;
$total      = count($soal_list);
$hasil_soal = [];

foreach ($soal_list as $i => $soal) {
    $jwb_siswa = strtoupper(trim($jawaban[$i] ?? ''));
    $benar     = $jwb_siswa === strtoupper($soal['kunci']);
    if ($benar) $skor++;
    $hasil_soal[] = [
        'soal'       => $soal['soal'],
        'jawaban'    => $jwb_siswa,
        'kunci'      => $soal['kunci'],
        'benar'      => $benar,
    ];
}

$persentase = $total > 0 ? round(($skor / $total) * 100) : 0;

// Log aktivitas
log_aktivitas($user_id, 'jawab_quiz', $konten['id'], $topik, [
    'skor'           => $skor,
    'total'          => $total,
    'persentase'     => $persentase,
    'profil_gabungan' => $profil_gabungan,
]);

// Simpan ke session untuk ditampilkan
$_SESSION['hasil_evaluasi'] = [
    'topik'          => $topik,
    'skor'           => $skor,
    'total'          => $total,
    'persentase'     => $persentase,
    'hasil_soal'     => $hasil_soal,
    'profil_gabungan' => $profil_gabungan,
];

header('Location: /hasil_evaluasi.php?topik=' . urlencode($topik));
exit;
?>
