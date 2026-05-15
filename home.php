<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

require_login();

$user_id = $_SESSION['user_id'];
$profil  = get_profil_siswa($user_id);

if (!$profil) {
    header('Location: pretest.php');
    exit;
}

$pdo = db();

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
$warna_level = [
    'beginner'     => '#e67e22',
    'intermediate' => '#2980b9',
    'advanced'     => '#27ae60',
];

// Hitung progress per topik
$topik_list = get_topik_list();
$progress_topik = [];
$total_semua = 0;
$selesai_semua = 0;

foreach ($topik_list as $topik_key => $topik_label) {
    // Total konten di topik ini sesuai profil
    $stmt = $pdo->prepare("SELECT urutan_content FROM adaptation_rules WHERE profil_gabungan = ? AND topik = ?");
    $stmt->execute([$profil['profil_gabungan'], $topik_key]);
    $row = $stmt->fetch();
    $ids = $row ? array_unique(json_decode($row['urutan_content'], true)) : [];
    $total = count($ids);

    // Konten yang sudah selesai dibaca
    $stmt2 = $pdo->prepare("SELECT COUNT(DISTINCT content_id) FROM activity_log WHERE user_id = ? AND tipe = 'selesai_materi' AND topik = ?");
    $stmt2->execute([$user_id, $topik_key]);
    $selesai = (int) $stmt2->fetchColumn();

    // Jobsheet yang sudah diupload
    $stmt3 = $pdo->prepare("SELECT COUNT(*) FROM jobsheet_submissions WHERE user_id = ? AND topik = ?");
    $stmt3->execute([$user_id, $topik_key]);
    $job_upload = (int) $stmt3->fetchColumn();

    // Evaluasi yang sudah dikerjakan
    $stmt4 = $pdo->prepare("SELECT COUNT(DISTINCT content_id) FROM activity_log WHERE user_id = ? AND tipe = 'jawab_quiz' AND topik = ?");
    $stmt4->execute([$user_id, $topik_key]);
    $evaluasi_done = (int) $stmt4->fetchColumn();

    $persen = $total > 0 ? min(100, round(($selesai / $total) * 100)) : 0;

    $progress_topik[$topik_key] = [
        'label'         => $topik_label,
        'total'         => $total,
        'selesai'       => $selesai,
        'persen'        => $persen,
        'evaluasi_done' => $evaluasi_done,
        'job_upload'    => $job_upload,
    ];

    $total_semua   += $total;
    $selesai_semua += $selesai;
}

$persen_total = $total_semua > 0 ? min(100, round(($selesai_semua / $total_semua) * 100)) : 0;

// Status post-test
$akses_posttest = cek_akses_posttest($user_id);

// Konten terakhir dibuka (untuk tombol "lanjutkan")
$stmt_last = $pdo->prepare("
    SELECT a.topik, a.content_id, c.judul
    FROM activity_log a
    JOIN content c ON c.id = a.content_id
    WHERE a.user_id = ? AND a.tipe = 'buka_materi'
    ORDER BY a.created_at DESC LIMIT 1
");
$stmt_last->execute([$user_id]);
$last_konten = $stmt_last->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Beranda — AdaptLearn PRE</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; min-height: 100vh; }

/* TOPBAR */
.topbar {
    background: #0f3460;
    color: #fff;
    padding: 0 24px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.topbar-brand { font-weight: 700; font-size: 16px; letter-spacing: 0.5px; }
.topbar-brand span { font-weight: 300; opacity: 0.7; font-size: 13px; margin-left: 8px; }
.topbar-right { display: flex; align-items: center; gap: 16px; font-size: 13px; }
.topbar-nama { opacity: 0.9; }
.topbar-logout { color: rgba(255,255,255,0.6); text-decoration: none; font-size: 12px; }
.topbar-logout:hover { color: #fff; }

/* BREADCRUMB */
.breadcrumb {
    background: #fff;
    border-bottom: 1px solid #e8e8e8;
    padding: 10px 24px;
    font-size: 13px;
    color: #888;
}
.breadcrumb strong { color: #0f3460; }

/* LAYOUT */
.container { max-width: 900px; margin: 0 auto; padding: 24px 16px; }

/* HERO */
.hero {
    background: linear-gradient(135deg, #0f3460 0%, #16213e 100%);
    border-radius: 16px;
    padding: 28px 32px;
    color: #fff;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
}
.hero-left h1 { font-size: 22px; margin-bottom: 4px; }
.hero-left p { opacity: 0.75; font-size: 14px; }
.hero-profil {
    background: rgba(255,255,255,0.12);
    border-radius: 12px;
    padding: 14px 20px;
    text-align: center;
    min-width: 160px;
    flex-shrink: 0;
}
.hero-profil .profil-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.7; margin-bottom: 4px; }
.hero-profil .profil-nama { font-size: 14px; font-weight: 700; margin-bottom: 8px; }
.badge {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    color: #fff;
}

/* PROGRESS TOTAL */
.progress-total {
    background: #fff;
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.progress-total-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.progress-total-header h3 { font-size: 14px; color: #333; font-weight: 600; }
.progress-total-header span { font-size: 20px; font-weight: 700; color: #0f3460; }
.progress-bar-wrap { background: #e8ecf0; border-radius: 99px; height: 10px; overflow: hidden; }
.progress-bar-fill { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #0f3460, #2980b9); transition: width 0.6s ease; }

/* TOPIK GRID */
.section-title { font-size: 13px; font-weight: 700; color: #666; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 12px; }
.topik-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
.topik-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    border-top: 4px solid #e0e0e0;
    transition: box-shadow 0.2s;
}
.topik-card.done { border-top-color: #27ae60; }
.topik-card.progress { border-top-color: #2980b9; }
.topik-card.belum { border-top-color: #e0e0e0; }
.topik-card h4 { font-size: 15px; color: #1a1a2e; margin-bottom: 12px; }
.topik-persen { font-size: 24px; font-weight: 700; color: #0f3460; margin-bottom: 6px; }
.topik-bar-wrap { background: #e8ecf0; border-radius: 99px; height: 6px; overflow: hidden; margin-bottom: 12px; }
.topik-bar-fill { height: 100%; border-radius: 99px; background: #2980b9; }
.topik-bar-fill.done { background: #27ae60; }
.topik-stats { display: flex; gap: 8px; flex-wrap: wrap; }
.topik-stat {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 99px;
    background: #f0f2f5;
    color: #555;
}
.topik-stat.ok { background: #e8f8ef; color: #27ae60; }
.topik-link { display: block; margin-top: 12px; font-size: 12px; color: #0f3460; text-decoration: none; font-weight: 600; }
.topik-link:hover { text-decoration: underline; }

/* ALUR BELAJAR */
.alur-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.alur-steps {
    display: flex;
    align-items: center;
    gap: 0;
    margin-top: 16px;
    overflow-x: auto;
}
.alur-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    min-width: 80px;
    position: relative;
}
.alur-step:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 16px;
    right: -50%;
    width: 100%;
    height: 2px;
    background: #e0e0e0;
    z-index: 0;
}
.alur-step.done:not(:last-child)::after { background: #27ae60; }
.step-icon {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: #e0e0e0;
    color: #999;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 6px;
    position: relative;
    z-index: 1;
}
.alur-step.done .step-icon { background: #27ae60; color: #fff; }
.alur-step.aktif .step-icon { background: #0f3460; color: #fff; box-shadow: 0 0 0 3px rgba(15,52,96,0.2); }
.step-label { font-size: 10px; text-align: center; color: #666; line-height: 1.3; }
.alur-step.done .step-label { color: #27ae60; font-weight: 600; }
.alur-step.aktif .step-label { color: #0f3460; font-weight: 700; }

/* CTA */
.cta-row { display: flex; gap: 12px; margin-bottom: 24px; }
.btn {
    padding: 13px 24px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    border: none;
    transition: all 0.2s;
    display: inline-block;
}
.btn-primary { background: #0f3460; color: #fff; }
.btn-primary:hover { background: #16213e; }
.btn-outline { background: transparent; border: 2px solid #0f3460; color: #0f3460; }
.btn-outline:hover { background: #f0f7ff; }
.btn-success { background: #27ae60; color: #fff; }
.btn-success:hover { background: #219150; }
.btn-disabled { background: #e0e0e0; color: #999; cursor: not-allowed; }

/* POSTTEST CARD */
.posttest-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    border-left: 4px solid #e0e0e0;
}
.posttest-card.boleh { border-left-color: #27ae60; }
.posttest-card.terkunci { border-left-color: #e67e22; }
.posttest-card h3 { font-size: 15px; color: #333; margin-bottom: 6px; }
.posttest-card p { font-size: 13px; color: #666; margin-bottom: 12px; }

@media (max-width: 600px) {
    .topik-grid { grid-template-columns: 1fr; }
    .hero { flex-direction: column; }
    .cta-row { flex-direction: column; }
}
</style>
</head>
<body>

<!-- TOPBAR -->
<?php $topbar_aktif="beranda"; include __DIR__ . "/includes/topbar_siswa.php"; ?>

<!-- BREADCRUMB -->
<div class="breadcrumb">
    <strong>Beranda</strong> · SMK Negeri Bansari
</div>

<div class="container">

    <!-- HERO -->
    <div class="hero">
        <div class="hero-left">
            <h1>Halo, <?= htmlspecialchars(explode(' ', $_SESSION['nama'])[0]) ?>! 👋</h1>
            <p>Selamat datang di AdaptLearn PRE. Materi disesuaikan dengan profil belajarmu.</p>
            <div style="margin-top:16px">
                <span style="font-size:12px;opacity:0.7">Skor Pre-Test: </span>
                <strong><?= $profil['skor_pengetahuan'] ?>/12</strong>
                &nbsp;·&nbsp;
                <span style="font-size:12px;opacity:0.7">Pre-Test: </span>
                <strong><?= date('d/m/Y', strtotime($profil['created_at'])) ?></strong>
            </div>
        </div>
        <div class="hero-profil">
            <div class="profil-label">Profil Belajar</div>
            <div class="profil-nama"><?= $label_profil[$profil['profil_learning']] ?? $profil['profil_learning'] ?></div>
            <span class="badge" style="background:<?= $warna_level[$profil['level_kemampuan']] ?? '#888' ?>">
                <?= $label_level[$profil['level_kemampuan']] ?? $profil['level_kemampuan'] ?>
            </span>
        </div>
    </div>

    <!-- ALUR BELAJAR -->
    <div class="alur-card">
        <div class="section-title">📍 Alur Belajarmu</div>
        <div class="alur-steps">
            <?php
            $steps = [['label' => 'Pre-Test', 'done' => true]];
            foreach ($topik_list as $slug => $nama) {
                $steps[] = ['label' => 'Materi ' . $nama, 'done' => ($progress_topik[$slug]['persen'] ?? 0) >= 100];
            }
            $steps[] = ['label' => 'Post-Test', 'done' => isset($akses_posttest['sudah_selesai'])];
            $aktif_idx = 0;
            foreach ($steps as $i => $s) {
                if (!$s['done']) { $aktif_idx = $i; break; }
                $aktif_idx = $i + 1;
            }
            foreach ($steps as $i => $s):
                $kelas = $s['done'] ? 'done' : ($i === $aktif_idx ? 'aktif' : '');
            ?>
            <div class="alur-step <?= $kelas ?>">
                <div class="step-icon"><?= $s['done'] ? '✓' : ($i + 1) ?></div>
                <div class="step-label"><?= $s['label'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- PROGRESS TOTAL -->
    <div class="progress-total">
        <div class="progress-total-header">
            <h3>Progress Keseluruhan Materi</h3>
            <span><?= $persen_total ?>%</span>
        </div>
        <div class="progress-bar-wrap">
            <div class="progress-bar-fill" style="width:<?= $persen_total ?>%"></div>
        </div>
    </div>

    <!-- TOMBOL AKSI -->
    <div class="cta-row">
        <?php if ($last_konten): ?>
            <a href="materi.php?topik=<?= $last_konten['topik'] ?>&konten=<?= $last_konten['content_id'] ?>" class="btn btn-primary">
                ▶ Lanjutkan: <?= htmlspecialchars(mb_strimwidth($last_konten['judul'], 0, 35, '...')) ?>
            </a>
        <?php else: ?>
            <a href="materi.php" class="btn btn-primary">▶ Mulai Belajar</a>
        <?php endif; ?>
        <a href="profil.php" class="btn btn-outline">👤 Lihat Profil Lengkap</a>
    </div>

    <!-- PROGRESS PER TOPIK -->
    <div class="section-title">📚 Progress per Topik</div>
    <div class="topik-grid">
        <?php foreach ($progress_topik as $key => $tp): ?>
        <?php
        $kelas_card = $tp['persen'] >= 100 ? 'done' : ($tp['persen'] > 0 ? 'progress' : 'belum');
        ?>
        <div class="topik-card <?= $kelas_card ?>">
            <h4><?= $tp['label'] ?></h4>
            <div class="topik-persen"><?= $tp['persen'] ?>%</div>
            <div class="topik-bar-wrap">
                <div class="topik-bar-fill <?= $tp['persen'] >= 100 ? 'done' : '' ?>" style="width:<?= $tp['persen'] ?>%"></div>
            </div>
            <div class="topik-stats">
                <span class="topik-stat <?= $tp['selesai'] > 0 ? 'ok' : '' ?>">
                    📖 <?= $tp['selesai'] ?>/<?= $tp['total'] ?> materi
                </span>
                <?php if ($tp['evaluasi_done'] > 0): ?>
                <span class="topik-stat ok">✅ Evaluasi</span>
                <?php endif; ?>
                <?php if ($tp['job_upload'] > 0): ?>
                <span class="topik-stat ok">📎 Jobsheet</span>
                <?php endif; ?>
            </div>
            <a href="materi.php?topik=<?= $key ?>" class="topik-link">
                <?= $tp['persen'] >= 100 ? '✓ Selesai' : ($tp['persen'] > 0 ? 'Lanjutkan →' : 'Mulai →') ?>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- STATUS POST-TEST -->
    <div class="posttest-card <?= $akses_posttest['boleh'] ? 'boleh' : 'terkunci' ?>">
        <h3>🎯 Post-Test</h3>
        <?php if (isset($akses_posttest['sudah_selesai'])): ?>
            <p>Kamu sudah menyelesaikan post-test. Lihat hasilmu di halaman profil.</p>
            <a href="profil.php" class="btn btn-outline">Lihat Hasil →</a>
        <?php elseif ($akses_posttest['boleh']): ?>
            <p>Post-test sudah tersedia. Kerjakan sekarang!</p>
            <a href="posttest.php" class="btn btn-success">Kerjakan Post-Test →</a>
        <?php else: ?>
            <p>🔒 <?= htmlspecialchars($akses_posttest['alasan']) ?></p>
            <span class="btn btn-disabled">Belum Tersedia</span>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
