<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

require_login();

$user_id = $_SESSION['user_id'];
$pdo     = db();

// Data profil terbaru
$profil = get_profil_siswa($user_id);

// Semua riwayat pre-test
$riwayat_pretest = $pdo->prepare("
    SELECT * FROM pre_test_results
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$riwayat_pretest->execute([$user_id]);
$riwayat_pretest = $riwayat_pretest->fetchAll();

// Rekap evaluasi per topik
$rekap_eval = $pdo->prepare("
    SELECT topik,
           COUNT(*) as jumlah,
           MAX(JSON_EXTRACT(detail, '$.persentase')) as skor_terbaik,
           MAX(created_at) as tgl_terakhir
    FROM activity_log
    WHERE user_id = ? AND tipe = 'jawab_quiz'
    GROUP BY topik
");
$rekap_eval->execute([$user_id]);
$rekap_eval = $rekap_eval->fetchAll();

// Progress materi per topik (jumlah konten yang pernah dibuka)
$progress_topik = $pdo->prepare("
    SELECT topik,
           COUNT(DISTINCT content_id) as dibuka
    FROM activity_log
    WHERE user_id = ? AND tipe = 'buka_materi' AND topik IS NOT NULL
    GROUP BY topik
");
$progress_topik->execute([$user_id]);
$progress_topik = array_column($progress_topik->fetchAll(), 'dibuka', 'topik');

// Total konten per topik dari adaptation_rules
$total_konten_topik = [];
if ($profil) {
    foreach (['dioda', 'transistor', 'catu_daya'] as $topik) {
        $stmt = $pdo->prepare("
            SELECT urutan_content FROM adaptation_rules
            WHERE profil_gabungan = ? AND topik = ?
        ");
        $stmt->execute([$profil['profil_gabungan'], $topik]);
        $row = $stmt->fetch();
        if ($row) {
            $ids = json_decode($row['urutan_content'], true);
            $total_konten_topik[$topik] = count(array_unique($ids));
        } else {
            $total_konten_topik[$topik] = 1;
        }
    }
}

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
$topik_label = [
    'dioda'      => 'Dioda',
    'transistor' => 'Transistor',
    'catu_daya'  => 'Catu Daya',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profil Saya — AdaptLearn PRE</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; }

.topbar {
    background: #0f3460;
    color: #fff;
    padding: 12px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.topbar-left { font-weight: 700; font-size: 16px; }
.topbar-nav { display: flex; gap: 20px; align-items: center; }
.topbar-nav a {
    color: rgba(255,255,255,0.75);
    text-decoration: none;
    font-size: 13px;
    transition: color 0.2s;
}
.topbar-nav a:hover, .topbar-nav a.aktif { color: #fff; }
.topbar-nav a.aktif { font-weight: 600; }

.container { max-width: 800px; margin: 0 auto; padding: 28px 20px; }

.card {
    background: #fff;
    border-radius: 12px;
    padding: 28px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    margin-bottom: 20px;
}
.section-title {
    font-size: 15px;
    font-weight: 700;
    color: #0f3460;
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e8f0fb;
}

/* ── PROFIL HEADER ── */
.profil-header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 24px;
}
.avatar {
    width: 64px;
    height: 64px;
    background: #0f3460;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
}
.profil-info h2 { font-size: 20px; color: #1a1a2e; margin-bottom: 4px; }
.profil-meta { font-size: 13px; color: #888; }
.profil-meta span { margin-right: 12px; }

/* ── PROFIL BELAJAR ── */
.profil-belajar {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}
.info-box {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 16px;
}
.info-label { font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
.info-value { font-size: 16px; font-weight: 700; color: #1a1a2e; }
.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 700;
    color: #fff;
}

/* ── PROGRESS TOPIK ── */
.progress-list { display: flex; flex-direction: column; gap: 14px; }
.progress-item { }
.progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}
.progress-label { font-size: 14px; font-weight: 600; color: #333; }
.progress-pct { font-size: 13px; color: #888; }
.progress-bar {
    height: 8px;
    background: #e8e8e8;
    border-radius: 10px;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    border-radius: 10px;
    background: #0f3460;
    transition: width 0.5s ease;
}
.progress-fill.selesai { background: #27ae60; }

/* ── EVALUASI ── */
.eval-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
}
.eval-card {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 16px;
    text-align: center;
}
.eval-topik { font-size: 13px; font-weight: 600; color: #444; margin-bottom: 8px; }
.eval-skor {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 4px;
}
.eval-tgl { font-size: 11px; color: #aaa; }
.empty-state { text-align: center; padding: 24px; color: #aaa; font-size: 13px; }

/* ── RIWAYAT ── */
.riwayat-list { display: flex; flex-direction: column; gap: 10px; }
.riwayat-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 16px;
    background: #f8f9fa;
    border-radius: 10px;
    font-size: 13px;
}
.riwayat-no {
    width: 24px;
    height: 24px;
    background: #0f3460;
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    flex-shrink: 0;
}
.riwayat-info { flex: 1; }
.riwayat-profil { font-weight: 600; color: #1a1a2e; }
.riwayat-detail { font-size: 12px; color: #888; margin-top: 2px; }

/* ── TOMBOL ── */
.btn-group { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 4px; }
.btn {
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    border: none;
    transition: all 0.2s;
}
.btn-primary { background: #0f3460; color: #fff; }
.btn-primary:hover { background: #16213e; }
.btn-outline { background: transparent; border: 2px solid #0f3460; color: #0f3460; }
.btn-outline:hover { background: #f0f7ff; }
.btn-danger { background: transparent; border: 2px solid #e74c3c; color: #e74c3c; }
.btn-danger:hover { background: #fff0f0; }

@media (max-width: 600px) {
    .profil-belajar { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<div class="topbar">
    <div class="topbar-left">AdaptLearn PRE</div>
    <nav class="topbar-nav">
        <a href="materi.php">Materi</a>
        <a href="profil.php" class="aktif">Profil</a>
        <a href="logout.php">Keluar</a>
    </nav>
</div>

<div class="container">

    <!-- PROFIL HEADER -->
    <div class="card">
        <div class="profil-header">
            <div class="avatar"><?= strtoupper(mb_substr($_SESSION['nama'], 0, 1)) ?></div>
            <div class="profil-info">
                <h2><?= htmlspecialchars($_SESSION['nama']) ?></h2>
                <div class="profil-meta">
                    <span>NIS: <?= htmlspecialchars($_SESSION['nis']) ?></span>
                    <span>Kelas: <?= htmlspecialchars($_SESSION['kelas'] ?? '-') ?></span>
                    <span>WA: <?= htmlspecialchars($_SESSION['nomor_wa'] ?? '-') ?></span>
                </div>
            </div>
        </div>

        <?php if ($profil): ?>
        <div class="profil-belajar">
            <div class="info-box">
                <div class="info-label">Profil Belajar</div>
                <div class="info-value"><?= $label_profil[$profil['profil_learning']] ?? $profil['profil_learning'] ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Level Kemampuan</div>
                <div class="info-value">
                    <span class="badge" style="background:<?= $warna_level[$profil['level_kemampuan']] ?? '#888' ?>">
                        <?= $label_level[$profil['level_kemampuan']] ?? $profil['level_kemampuan'] ?>
                    </span>
                </div>
            </div>
            <div class="info-box">
                <div class="info-label">Skor Pre-Test</div>
                <div class="info-value"><?= $profil['skor_pengetahuan'] ?> / 12</div>
            </div>
            <div class="info-box">
                <div class="info-label">Pre-Test Terakhir</div>
                <div class="info-value" style="font-size:13px">
                    <?= date('d M Y', strtotime($profil['created_at'])) ?>
                </div>
            </div>
        </div>

        <div class="btn-group">
            <a href="materi.php" class="btn btn-primary">Lanjut Belajar →</a>
            <a href="pretest.php" class="btn btn-outline">Ulangi Pre-Test</a>
        </div>

        <?php else: ?>
        <div class="empty-state">Kamu belum mengerjakan pre-test.</div>
        <div class="btn-group" style="margin-top:12px">
            <a href="pretest.php" class="btn btn-primary">Mulai Pre-Test →</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- PROGRESS TOPIK -->
    <?php if ($profil): ?>
    <div class="card">
        <div class="section-title">Progress Belajar per Topik</div>
        <div class="progress-list">
            <?php foreach ($topik_label as $slug => $nama): ?>
            <?php
                $dibuka = $progress_topik[$slug] ?? 0;
                $total  = $total_konten_topik[$slug] ?? 1;
                $pct    = min(100, round(($dibuka / $total) * 100));
                $selesai = $pct >= 100;
            ?>
            <div class="progress-item">
                <div class="progress-header">
                    <span class="progress-label">
                        <?= htmlspecialchars($nama) ?>
                        <?php if ($selesai): ?>
                            <span style="color:#27ae60;font-size:12px"> ✓ Selesai</span>
                        <?php endif; ?>
                    </span>
                    <span class="progress-pct"><?= $dibuka ?>/<?= $total ?> materi (<?= $pct ?>%)</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill <?= $selesai ? 'selesai' : '' ?>"
                         style="width:<?= $pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- REKAP EVALUASI -->
    <div class="card">
        <div class="section-title">Rekap Evaluasi</div>
        <?php if ($rekap_eval): ?>
        <div class="eval-grid">
            <?php foreach ($rekap_eval as $e):
                $skor = (float)$e['skor_terbaik'];
                $c = $skor >= 80 ? '#27ae60' : ($skor >= 60 ? '#2980b9' : '#e74c3c');
            ?>
            <div class="eval-card">
                <div class="eval-topik"><?= htmlspecialchars($topik_label[$e['topik']] ?? $e['topik']) ?></div>
                <div class="eval-skor" style="color:<?= $c ?>"><?= $skor ?>%</div>
                <div class="eval-tgl">
                    <?= $e['jumlah'] ?> kali dikerjakan<br>
                    Terakhir: <?= date('d/m/Y', strtotime($e['tgl_terakhir'])) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="empty-state">Belum ada evaluasi yang dikerjakan.</div>
        <?php endif; ?>
    </div>

    <!-- RIWAYAT PRE-TEST -->
    <?php if (count($riwayat_pretest) > 1): ?>
    <div class="card">
        <div class="section-title">Riwayat Pre-Test</div>
        <div class="riwayat-list">
            <?php foreach ($riwayat_pretest as $i => $r): ?>
            <div class="riwayat-item">
                <div class="riwayat-no"><?= count($riwayat_pretest) - $i ?></div>
                <div class="riwayat-info">
                    <div class="riwayat-profil">
                        <?= $label_profil[$r['profil_learning']] ?? $r['profil_learning'] ?>
                        — <span class="badge" style="background:<?= $warna_level[$r['level_kemampuan']] ?? '#888' ?>;font-size:11px">
                            <?= $label_level[$r['level_kemampuan']] ?? $r['level_kemampuan'] ?>
                        </span>
                    </div>
                    <div class="riwayat-detail">
                        Skor: <?= $r['skor_pengetahuan'] ?>/12
                        · <?= date('d M Y H:i', strtotime($r['created_at'])) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div>
</body>
</html>
