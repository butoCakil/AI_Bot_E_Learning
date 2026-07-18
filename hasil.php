<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Ambil dari session, fallback ke database
if (!empty($_SESSION['hasil_pretest'])) {
    $hasil = $_SESSION['hasil_pretest'];
    unset($_SESSION['hasil_pretest']);
} else {
    $pdo_h  = db();
    $stmt_h = $pdo_h->prepare("
        SELECT * FROM pre_test_results
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt_h->execute([$_SESSION['user_id']]);
    $row_h = $stmt_h->fetch();

    if (!$row_h) {
        header('Location: pretest.php');
        exit;
    }

    $hasil = [
        'profil_learning' => $row_h['profil_learning'],
        'level'           => $row_h['level_kemampuan'],
        'skor'            => $row_h['skor_pengetahuan'],
        'probabilitas'    => json_decode($row_h['probabilitas'], true),
    ];
}

$nama  = $_SESSION['nama'];
$kelas = $_SESSION['kelas'] ?? '-';

$label_profil = [
    'guided_step'       => 'Guided-Step Learner',
    'conceptual'        => 'Conceptual Learner',
    'practice_oriented' => 'Practice-Oriented Learner',
];
$label_level = [
    'beginner'     => 'Pemula',
    'intermediate' => 'Menengah',
    'advanced'     => 'Mahir',
];
$deskripsi_profil = [
    'guided_step'       => 'Kamu belajar paling efektif dengan panduan langkah demi langkah. Materi akan disajikan terstruktur dan bertahap.',
    'conceptual'        => 'Kamu belajar paling efektif dengan memahami konsep mendalam lebih dulu. Materi akan disajikan dengan penjelasan konseptual lengkap.',
    'practice_oriented' => 'Kamu belajar paling efektif dengan langsung praktik dan eksplorasi. Materi akan disajikan dengan tantangan dan proyek nyata.',
];
$ikon_profil = [
    'guided_step'       => 'icon-footprints',
    'conceptual'        => 'icon-brain',
    'practice_oriented' => 'icon-wrench',
];

$profil = $hasil['profil_learning'];
$level  = $hasil['level'];
$skor   = $hasil['skor'];

$page_title = 'Hasil Pre-Test — AdaptLearn PRE';
$tanpa_nav  = true;
include __DIR__ . '/includes/topbar_siswa.php';
?>

<div class="wrap" style="max-width:560px">

    <!-- HEADER SUKSES -->
    <div class="card tengah">
        <div style="width:64px;height:64px;border-radius:50%;background:var(--teal-muda);color:var(--teal);
                    display:grid;place-items:center;font-size:28px;margin:4px auto 16px">
            <i class="icon-circle-check"></i>
        </div>
        <h1 style="font-size:20px;font-weight:800;letter-spacing:-.4px;margin-bottom:6px">Pre-Test selesai!</h1>
        <p style="font-size:13px;color:var(--abu)">
            Halo <b style="color:var(--tinta)"><?= htmlspecialchars($nama) ?></b> · <?= htmlspecialchars($kelas) ?>
        </p>
    </div>

    <!-- PROFIL BELAJAR -->
    <div class="card" style="border-top:4px solid var(--biru)">
        <div class="tengah">
            <div style="width:56px;height:56px;border-radius:16px;background:var(--biru-muda);color:var(--biru);
                        display:grid;place-items:center;font-size:26px;margin:0 auto 14px">
                <i class="<?= $ikon_profil[$profil] ?? 'icon-target' ?>"></i>
            </div>
            <div style="font-size:11px;font-weight:800;color:var(--abu-muda);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">
                Profil belajarmu
            </div>
            <div style="font-size:22px;font-weight:800;letter-spacing:-.5px;color:var(--biru-tua);margin-bottom:10px">
                <?= $label_profil[$profil] ?? $profil ?>
            </div>
            <p style="font-size:13px;color:var(--abu);line-height:1.65">
                <?= $deskripsi_profil[$profil] ?? '' ?>
            </p>
        </div>
    </div>

    <!-- SKOR & LEVEL -->
    <div class="cta">
        <div class="card tengah" style="flex:1;padding:18px">
            <div style="font-size:11px;font-weight:800;color:var(--abu-muda);text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px">
                Skor pengetahuan
            </div>
            <div style="font-size:30px;font-weight:800;letter-spacing:-1px;color:var(--tinta)">
                <?= $skor ?><span style="font-size:15px;color:var(--abu-muda)">/12</span>
            </div>
        </div>
        <div class="card tengah" style="flex:1;padding:18px">
            <div style="font-size:11px;font-weight:800;color:var(--abu-muda);text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px">
                Level
            </div>
            <div style="margin-top:4px">
                <span class="chip lvl" style="font-size:13px;padding:8px 16px"><i class="icon-signal"></i> <?= $label_level[$level] ?? $level ?></span>
            </div>
        </div>
    </div>

    <!-- AKSI -->
    <a href="materi.php" class="btn btn-1 btn-full" style="padding:15px">
        <i class="icon-play"></i> Mulai belajar sekarang
    </a>
    <a href="home.php" class="btn btn-2 btn-full">
        <i class="icon-house"></i> Ke beranda
    </a>

</div>

</body>
</html>
