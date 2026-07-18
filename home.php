<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

require_login();

$user_id = $_SESSION['user_id'];
$profil  = get_profil_siswa($user_id);

if (!$profil) {
    header('Location: welcome.php');
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

// Ikon per topik (fallback ke ikon umum jika slug tak dikenal)
$ikon_topik = [
    'dioda'                 => 'icon-zap',
    'transistor_dasar'      => 'icon-circuit-board',
    'transistor_pengukuran' => 'icon-gauge',
    'catu_daya'             => 'icon-battery-charging',
];

// ── Progress per topik ───────────────────────────────────────
$topik_list     = get_topik_list();
$progress_topik = [];
$total_semua    = 0;
$selesai_semua  = 0;

foreach ($topik_list as $topik_key => $topik_label) {
    $stmt = $pdo->prepare("SELECT urutan_content FROM adaptation_rules WHERE profil_gabungan = ? AND topik = ?");
    $stmt->execute([$profil['profil_gabungan'], $topik_key]);
    $row = $stmt->fetch();
    $ids = $row ? array_unique(json_decode($row['urutan_content'], true)) : [];
    $total = count($ids);

    $stmt2 = $pdo->prepare("SELECT COUNT(DISTINCT content_id) FROM activity_log WHERE user_id = ? AND tipe = 'selesai_materi' AND topik = ?");
    $stmt2->execute([$user_id, $topik_key]);
    $selesai = (int) $stmt2->fetchColumn();

    $stmt3 = $pdo->prepare("SELECT COUNT(*) FROM jobsheet_submissions WHERE user_id = ? AND topik = ?");
    $stmt3->execute([$user_id, $topik_key]);
    $job_upload = (int) $stmt3->fetchColumn();

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

// ── Status post-test ─────────────────────────────────────────
$akses_posttest = cek_akses_posttest($user_id);

// ── Konten terakhir dibuka ───────────────────────────────────
$stmt_last = $pdo->prepare("
    SELECT a.topik, a.content_id, c.judul
    FROM activity_log a
    JOIN content c ON c.id = a.content_id
    WHERE a.user_id = ? AND a.tipe = 'buka_materi'
    ORDER BY a.created_at DESC LIMIT 1
");
$stmt_last->execute([$user_id]);
$last_konten = $stmt_last->fetch();

// ── Streak belajar ───────────────────────────────────────────
// Hari "aktif" = ada aktivitas belajar (bukan sekadar login).
$stmt_hari = $pdo->prepare("
    SELECT DISTINCT DATE(created_at) AS tgl
    FROM activity_log
    WHERE user_id = ?
      AND tipe IN ('buka_materi','selesai_materi','jawab_quiz','upload_jobsheet','tanya_bot')
    ORDER BY tgl DESC
    LIMIT 60
");
$stmt_hari->execute([$user_id]);
$hari_aktif = array_column($stmt_hari->fetchAll(), 'tgl');
$set_aktif  = array_flip($hari_aktif);

$hari_ini    = date('Y-m-d');
$kemarin     = date('Y-m-d', strtotime('-1 day'));
$aktif_hari_ini = isset($set_aktif[$hari_ini]);

// Hitung hari beruntun. Streak tetap hidup jika kemarin aktif
// (siswa masih punya kesempatan belajar hari ini).
$streak = 0;
if ($aktif_hari_ini || isset($set_aktif[$kemarin])) {
    $cursor = $aktif_hari_ini ? $hari_ini : $kemarin;
    while (isset($set_aktif[$cursor])) {
        $streak++;
        $cursor = date('Y-m-d', strtotime($cursor . ' -1 day'));
    }
}

// 7 hari terakhir (Senin–Minggu minggu ini)
$awal_minggu = date('Y-m-d', strtotime('monday this week'));
$minggu_ini  = [];
$inisial_hari = ['S', 'S', 'R', 'K', 'J', 'S', 'M']; // Sen Sel Rab Kam Jum Sab Min
for ($i = 0; $i < 7; $i++) {
    $tgl = date('Y-m-d', strtotime("$awal_minggu +$i day"));
    $minggu_ini[] = [
        'tgl'    => $tgl,
        'huruf'  => $inisial_hari[$i],
        'aktif'  => isset($set_aktif[$tgl]),
        'ini'    => $tgl === $hari_ini,
        'depan'  => $tgl > $hari_ini,
    ];
}

if ($streak === 0) {
    $pesan_streak = 'Belajar hari ini untuk memulai streak-mu.';
} elseif (!$aktif_hari_ini) {
    $pesan_streak = 'Streak-mu masih hidup. Belajar hari ini biar tidak putus.';
} elseif ($streak < 3) {
    $pesan_streak = 'Awal yang bagus. Lanjutkan besok ya.';
} else {
    $pesan_streak = 'Konsisten begini yang bikin paham. Pertahankan.';
}

// ── Susun langkah alur belajar ───────────────────────────────
$steps = [[
    'label'  => 'Pre-Test',
    'sub'    => 'Selesai · ' . $profil['skor_pengetahuan'] . '/12',
    'done'   => true,
    'link'   => null,
]];

foreach ($topik_list as $slug => $nama) {
    $tp   = $progress_topik[$slug];
    $done = $tp['persen'] >= 100;
    $steps[] = [
        'label' => $nama,
        'sub'   => $done
            ? 'Selesai · ' . $tp['selesai'] . '/' . $tp['total'] . ' materi'
            : ($tp['selesai'] > 0
                ? 'Sedang berjalan · ' . $tp['selesai'] . '/' . $tp['total']
                : ($tp['total'] > 0 ? 'Belum dimulai · ' . $tp['total'] . ' materi' : 'Belum ada materi')),
        'done'  => $done,
        'link'  => 'materi.php?topik=' . urlencode($slug),
    ];
}

$posttest_done = isset($akses_posttest['sudah_selesai']);
$steps[] = [
    'label' => 'Post-Test',
    'sub'   => $posttest_done ? 'Selesai' : ($akses_posttest['boleh'] ? 'Siap dikerjakan' : 'Terkunci'),
    'done'  => $posttest_done,
    'link'  => $akses_posttest['boleh'] && !$posttest_done ? 'posttest.php' : null,
    'kunci' => !$akses_posttest['boleh'] && !$posttest_done,
];

// Tentukan langkah aktif
$aktif_idx = count($steps);
foreach ($steps as $i => $s) {
    if (!$s['done']) { $aktif_idx = $i; break; }
}

// Tinggi garis rail yang terisi (proporsi langkah selesai)
$jml_done  = count(array_filter($steps, fn($s) => $s['done']));
$rail_isi  = count($steps) > 1 ? round(($jml_done - 0.5) / (count($steps) - 1) * 100) : 0;
$rail_isi  = max(0, min(100, $rail_isi));

$page_title   = 'Beranda — AdaptLearn PRE';
$topbar_aktif = 'beranda';
include __DIR__ . '/includes/topbar_siswa.php';
?>

<div class="crumb">
    <span class="now">Beranda</span>
    <span class="sep">·</span>
    <span>SMK Negeri Bansari</span>
</div>

<div class="wrap">

    <!-- HERO -->
    <div class="hero">
        <h1>Selamat belajar, <em><?= htmlspecialchars(explode(' ', $_SESSION['nama'])[0]) ?>!</em></h1>
        <p class="sub">Materi sudah disesuaikan dengan cara belajarmu. Lanjutkan dari tempat terakhir.</p>
        <div class="chips">
            <span class="chip pro">
                <i class="icon-target"></i>
                <?= htmlspecialchars($label_profil[$profil['profil_learning']] ?? $profil['profil_learning']) ?>
            </span>
            <span class="chip lvl">
                <i class="icon-signal"></i>
                <?= htmlspecialchars($label_level[$profil['level_kemampuan']] ?? $profil['level_kemampuan']) ?>
            </span>
            <span class="chip">
                <i class="icon-clipboard-check"></i>
                Pre-Test <?= $profil['skor_pengetahuan'] ?>/12
            </span>
        </div>
    </div>

    <!-- STREAK -->
    <div class="streak">
        <div class="streak-top"><i class="icon-flame"></i> Streak belajar</div>
        <div class="streak-num">
            <b><?= $streak ?></b>
            <span>hari beruntun</span>
        </div>
        <div class="streak-msg"><?= htmlspecialchars($pesan_streak) ?></div>
        <div class="dots">
            <?php foreach ($minggu_ini as $h): ?>
                <?php
                $kelas = '';
                if ($h['aktif'])          $kelas = 'ok';
                elseif ($h['ini'])        $kelas = 'now';
                ?>
                <div class="dot <?= $kelas ?>">
                    <i><?php
                        if ($h['aktif'])      echo '&check;';
                        elseif ($h['ini'])    echo '<span class="icon-flame"></span>';
                    ?></i>
                    <small><?= $h['huruf'] ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ALUR BELAJAR -->
    <div class="card">
        <div class="card-h">
            <h3><i class="icon-route"></i> Alur belajarmu</h3>
            <span class="pct"><?= $persen_total ?>%</span>
        </div>
        <p class="card-sub"><?= $selesai_semua ?> dari <?= $total_semua ?> materi selesai</p>
        <div class="bar" style="margin-bottom:14px"><i style="width:<?= $persen_total ?>%"></i></div>

        <div class="rail">
            <div class="rail-line"><div class="rail-fill" style="height:<?= $rail_isi ?>%"></div></div>

            <?php foreach ($steps as $i => $s): ?>
                <?php $kelas = $s['done'] ? 'ok' : ($i === $aktif_idx ? 'now' : ''); ?>
                <div class="step <?= $kelas ?>">
                    <div class="step-ic">
                        <?php if ($s['done']): ?>
                            &check;
                        <?php elseif (!empty($s['kunci'])): ?>
                            <i class="icon-lock"></i>
                        <?php else: ?>
                            <?= $i + 1 ?>
                        <?php endif; ?>
                    </div>
                    <div class="step-tx">
                        <b><?= htmlspecialchars($s['label']) ?></b>
                        <small><?= htmlspecialchars($s['sub']) ?></small>
                    </div>
                    <?php if ($i === $aktif_idx && !empty($s['link'])): ?>
                        <a href="<?= $s['link'] ?>" class="step-go">Lanjut <i class="icon-arrow-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- TOMBOL AKSI -->
    <div class="cta">
        <?php if ($last_konten): ?>
            <a href="materi.php?topik=<?= urlencode($last_konten['topik']) ?>&konten=<?= (int) $last_konten['content_id'] ?>" class="btn btn-1">
                <i class="icon-play"></i> Lanjutkan belajar
            </a>
        <?php else: ?>
            <a href="materi.php" class="btn btn-1"><i class="icon-play"></i> Mulai belajar</a>
        <?php endif; ?>
        <a href="profil.php" class="btn btn-2" style="flex:0 0 auto" title="Profil saya"><i class="icon-user"></i></a>
    </div>

    <!-- PROGRESS PER TOPIK -->
    <div class="sect"><i class="icon-library"></i> Progress per topik</div>
    <div class="grid">
        <?php foreach ($progress_topik as $key => $tp): ?>
            <?php
            $status = $tp['persen'] >= 100 ? 's-done' : ($tp['persen'] > 0 ? 's-go' : 's-belum');
            $ikon   = $ikon_topik[$key] ?? 'icon-book-open';
            ?>
            <a href="materi.php?topik=<?= urlencode($key) ?>" class="tk <?= $status ?>">
                <div class="tk-h">
                    <b><?= htmlspecialchars($tp['label']) ?></b>
                    <span class="tk-ic"><i class="<?= $ikon ?>"></i></span>
                </div>
                <span class="tk-pct"><?= $tp['persen'] ?>%</span>
                <div class="bar"><i class="<?= $tp['persen'] >= 100 ? 'done' : '' ?>" style="width:<?= $tp['persen'] ?>%"></i></div>
                <div class="tags">
                    <span class="tag <?= $tp['selesai'] > 0 ? 'ok' : '' ?>">
                        <i class="icon-book-open"></i> <?= $tp['selesai'] ?>/<?= $tp['total'] ?>
                    </span>
                    <?php if ($tp['evaluasi_done'] > 0): ?>
                        <span class="tag ok"><i class="icon-check"></i> Evaluasi</span>
                    <?php endif; ?>
                    <?php if ($tp['job_upload'] > 0): ?>
                        <span class="tag ok"><i class="icon-paperclip"></i> Jobsheet</span>
                    <?php endif; ?>
                </div>
                <span class="tk-go">
                    <?php if ($tp['persen'] >= 100): ?>
                        <i class="icon-circle-check"></i> Selesai
                    <?php elseif ($tp['persen'] > 0): ?>
                        Lanjutkan <i class="icon-arrow-right"></i>
                    <?php else: ?>
                        Mulai <i class="icon-arrow-right"></i>
                    <?php endif; ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- POST-TEST -->
    <?php if ($posttest_done): ?>
        <div class="pt buka">
            <div class="pt-ic"><i class="icon-circle-check"></i></div>
            <div>
                <b>Post-Test selesai</b>
                <p>Kamu sudah menyelesaikan post-test. Lihat hasil dan perkembanganmu di halaman profil.</p>
                <a href="profil.php" class="btn btn-2 btn-sm"><i class="icon-chart-line"></i> Lihat hasil</a>
            </div>
        </div>
    <?php elseif ($akses_posttest['boleh']): ?>
        <div class="pt buka">
            <div class="pt-ic"><i class="icon-target"></i></div>
            <div>
                <b>Post-Test sudah terbuka</b>
                <p>Semua materi sudah kamu selesaikan. Saatnya mengukur seberapa jauh kamu berkembang.</p>
                <a href="posttest.php" class="btn btn-3 btn-sm">Kerjakan post-test <i class="icon-arrow-right"></i></a>
            </div>
        </div>
    <?php else: ?>
        <div class="pt kunci">
            <div class="pt-ic"><i class="icon-lock"></i></div>
            <div>
                <b>Post-Test</b>
                <p><?= htmlspecialchars($akses_posttest['alasan']) ?></p>
                <span class="btn btn-off btn-sm">Belum tersedia</span>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/bottomnav_siswa.php'; ?>
</body>
</html>
