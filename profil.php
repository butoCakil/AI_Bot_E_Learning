<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

require_login();

$pdo     = db();
$user_id = $_SESSION['user_id'];

// ── Profil belajar ───────────────────────────────────────────
$profil = get_profil_siswa($user_id);

// ── Riwayat pre-test ─────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM pre_test_results WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$riwayat_pretest = $stmt->fetchAll();

// ── Rekap evaluasi ───────────────────────────────────────────
// Sumber: tabel evaluasi_results (bukan activity_log), karena
// menyimpan skor & jumlah percobaan secara terstruktur.
$stmt = $pdo->prepare("
    SELECT e.topik, e.content_id, e.skor, e.total, e.persentase, e.percobaan, e.updated_at, c.judul
    FROM evaluasi_results e
    LEFT JOIN `content` c ON c.id = e.content_id
    WHERE e.user_id = ?
    ORDER BY e.updated_at DESC
");
$stmt->execute([$user_id]);
$rekap_eval = $stmt->fetchAll();

// ── Post-test & N-Gain ───────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT pt.*, p.skor_pengetahuan AS skor_pre
    FROM post_test_results pt
    JOIN pre_test_results p ON p.user_id = pt.user_id
    WHERE pt.user_id = ?
    ORDER BY pt.created_at DESC, p.created_at DESC
    LIMIT 1
");
$stmt->execute([$user_id]);
$posttest   = $stmt->fetch();
$ngain_data = $posttest
    ? hitung_ngain((int) $posttest['skor_pre'], (int) $posttest['skor_pengetahuan'])
    : null;

// ── Progress materi per topik ────────────────────────────────
$stmt = $pdo->prepare("
    SELECT topik, COUNT(DISTINCT content_id) AS dibuka
    FROM activity_log
    WHERE user_id = ? AND tipe = 'selesai_materi' AND topik IS NOT NULL
    GROUP BY topik
");
$stmt->execute([$user_id]);
$progress_topik = array_column($stmt->fetchAll(), 'dibuka', 'topik');

// Total konten per topik dari adaptation_rules
$topik_label        = array_filter(get_topik_list(), function ($slug) {
    return empty(get_sub_topik($slug));   // buang parent yang punya sub-topik
}, ARRAY_FILTER_USE_KEY);
$total_konten_topik = [];
if ($profil) {
    foreach (array_keys($topik_label) as $topik) {
        $stmt = $pdo->prepare("SELECT urutan_content FROM adaptation_rules WHERE profil_gabungan = ? AND topik = ?");
        $stmt->execute([$profil['profil_gabungan'], $topik]);
        $row = $stmt->fetch();
        $total_konten_topik[$topik] = $row
            ? count(array_unique(json_decode($row['urutan_content'], true) ?? []))
            : 0;
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
$deskripsi_profil = [
    'guided_step'       => 'Kamu belajar paling efektif dengan panduan langkah demi langkah. Materi disajikan secara terstruktur dan bertahap.',
    'conceptual'        => 'Kamu belajar paling efektif dengan memahami konsep secara mendalam lebih dulu. Materi disajikan dengan penjelasan konseptual yang lengkap.',
    'practice_oriented' => 'Kamu belajar paling efektif dengan langsung praktik dan eksplorasi. Materi disajikan dengan tantangan dan proyek nyata.',
];

// Inisial avatar
$pecah   = preg_split('/\s+/', trim($_SESSION['nama']));
$inisial = strtoupper(mb_substr($pecah[0], 0, 1));
if (count($pecah) > 1) $inisial .= strtoupper(mb_substr(end($pecah), 0, 1));

$page_title   = 'Profil — AdaptLearn PRE';
$topbar_aktif = 'profil';
include __DIR__ . '/includes/topbar_siswa.php';
?>

<div class="crumb">
    <a href="home.php">Beranda</a>
    <span class="sep">›</span>
    <span class="now">Profil</span>
</div>

<div class="wrap">

    <!-- IDENTITAS -->
    <div class="card">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:<?= $profil ? '18px' : '0' ?>">
            <div style="width:58px;height:58px;border-radius:50%;background:var(--biru-muda);color:var(--biru-tua);
                        display:grid;place-items:center;font-size:20px;font-weight:800;border:2.5px solid var(--biru-200);flex-shrink:0">
                <?= htmlspecialchars($inisial) ?>
            </div>
            <div style="min-width:0">
                <h1 style="font-size:19px;font-weight:800;letter-spacing:-.4px;line-height:1.3">
                    <?= htmlspecialchars($_SESSION['nama']) ?>
                </h1>
                <div class="tags" style="margin-top:7px">
                    <span class="tag"><i class="icon-id-card"></i> <?= htmlspecialchars($_SESSION['nis']) ?></span>
                    <span class="tag"><i class="icon-users"></i> <?= htmlspecialchars($_SESSION['kelas'] ?? '-') ?></span>
                    <?php if (!empty($_SESSION['nomor_wa'])): ?>
                        <span class="tag"><i class="icon-phone"></i> <?= htmlspecialchars($_SESSION['nomor_wa']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($profil): ?>
            <div class="chips" style="margin-top:0">
                <span class="chip pro"><i class="icon-target"></i> <?= $label_profil[$profil['profil_learning']] ?? $profil['profil_learning'] ?></span>
                <span class="chip lvl"><i class="icon-signal"></i> <?= $label_level[$profil['level_kemampuan']] ?? $profil['level_kemampuan'] ?></span>
                <span class="chip"><i class="icon-clipboard-check"></i> Pre-Test <?= $profil['skor_pengetahuan'] ?>/12</span>
                <span class="chip"><i class="icon-calendar"></i> <?= date('d M Y', strtotime($profil['created_at'])) ?></span>
            </div>
            <p style="font-size:12.5px;color:var(--abu);line-height:1.6;margin-top:14px">
                <?= $deskripsi_profil[$profil['profil_learning']] ?? '' ?>
            </p>
        <?php endif; ?>
    </div>

    <?php if (!$profil): ?>

        <div class="card">
            <div class="kosong">
                <i class="icon-clipboard-list"></i>
                <b>Kamu belum mengerjakan pre-test</b>
                <p>Pre-test menentukan profil belajar dan urutan materi yang sesuai untukmu.</p>
                <a href="pretest.php" class="btn btn-1 btn-sm" style="margin-top:16px">
                    Mulai pre-test <i class="icon-arrow-right"></i>
                </a>
            </div>
        </div>

    <?php else: ?>

        <div class="cta">
            <a href="materi.php" class="btn btn-1"><i class="icon-play"></i> Lanjut belajar</a>
            <a href="evaluasi.php" class="btn btn-2"><i class="icon-clipboard-list"></i> Evaluasi</a>
        </div>

        <!-- POST-TEST & N-GAIN -->
        <?php if ($posttest && $ngain_data): ?>
            <?php
            $ng       = (float) $ngain_data['ngain'];
            $warna_ng = $ng > 0.7 ? 'var(--teal)' : ($ng >= 0.3 ? 'var(--biru)' : 'var(--coral)');
            $bg_ng    = $ng > 0.7 ? 'var(--teal-muda)' : ($ng >= 0.3 ? 'var(--biru-muda)' : 'var(--coral-muda)');
            ?>
            <div class="sect"><i class="icon-trending-up"></i> Hasil post-test &amp; N-Gain</div>
            <div class="card">
                <div style="display:grid;grid-template-columns:1fr auto 1fr auto 1.2fr;gap:10px;align-items:center;margin-bottom:16px">
                    <div class="tengah" style="background:var(--kanvas);border-radius:var(--r-sm);padding:14px 8px">
                        <div style="font-size:10px;font-weight:800;color:var(--abu-muda);text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px">Pre-Test</div>
                        <div style="font-size:22px;font-weight:800;letter-spacing:-.6px"><?= $posttest['skor_pre'] ?><span style="font-size:12px;color:var(--abu-muda)">/12</span></div>
                    </div>
                    <i class="icon-arrow-right" style="color:var(--abu-muda);font-size:16px"></i>
                    <div class="tengah" style="background:var(--kanvas);border-radius:var(--r-sm);padding:14px 8px">
                        <div style="font-size:10px;font-weight:800;color:var(--abu-muda);text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px">Post-Test</div>
                        <div style="font-size:22px;font-weight:800;letter-spacing:-.6px"><?= $posttest['skor_pengetahuan'] ?><span style="font-size:12px;color:var(--abu-muda)">/12</span></div>
                    </div>
                    <i class="icon-equal" style="color:var(--abu-muda);font-size:16px"></i>
                    <div class="tengah" style="background:<?= $bg_ng ?>;border-radius:var(--r-sm);padding:14px 8px">
                        <div style="font-size:10px;font-weight:800;color:<?= $warna_ng ?>;text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px">N-Gain</div>
                        <div style="font-size:22px;font-weight:800;letter-spacing:-.6px;color:<?= $warna_ng ?>"><?= number_format($ng, 2) ?></div>
                        <div style="font-size:11px;font-weight:800;color:<?= $warna_ng ?>;margin-top:2px"><?= $ngain_data['kategori'] ?></div>
                    </div>
                </div>

                <div style="background:var(--kanvas);border-radius:var(--r-sm);padding:12px 14px;font-size:12.5px;color:var(--abu);line-height:1.7">
                    <b style="color:var(--tinta)">Perhitungan N-Gain (Hake, 1999)</b><br>
                    g = (<?= $posttest['skor_pengetahuan'] ?> − <?= $posttest['skor_pre'] ?>) ÷ (12 − <?= $posttest['skor_pre'] ?>)
                    = <b style="color:<?= $warna_ng ?>"><?= number_format($ng, 4) ?></b>
                    → kategori <b style="color:<?= $warna_ng ?>"><?= $ngain_data['kategori'] ?></b>
                </div>

                <div class="rata" style="margin-top:12px;justify-content:space-between;flex-wrap:wrap">
                    <span style="font-size:11.5px;color:var(--abu-muda);font-weight:600">
                        Dikerjakan <?= date('d M Y H:i', strtotime($posttest['created_at'])) ?>
                    </span>
                    <a href="hasil_posttest.php" class="btn btn-2 btn-sm">
                        <i class="icon-eye"></i> Lihat pembahasan
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- PROGRESS PER TOPIK -->
        <div class="sect"><i class="icon-chart-line"></i> Progress per topik</div>
        <div class="card" style="display:flex;flex-direction:column;gap:14px">
            <?php foreach ($topik_label as $slug => $nama): ?>
                <?php
                $dibuka  = $progress_topik[$slug] ?? 0;
                $total   = $total_konten_topik[$slug] ?? 0;
                $pct     = $total > 0 ? min(100, round(($dibuka / $total) * 100)) : 0;
                $selesai = $pct >= 100;
                ?>
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:7px">
                        <span style="font-size:13px;font-weight:700;display:flex;align-items:center;gap:6px">
                            <?= htmlspecialchars($nama) ?>
                            <?php if ($selesai): ?>
                                <i class="icon-circle-check" style="color:var(--teal);font-size:14px"></i>
                            <?php endif; ?>
                        </span>
                        <span style="font-size:11.5px;color:var(--abu-muda);font-weight:700;white-space:nowrap">
                            <?= $dibuka ?>/<?= $total ?> · <?= $pct ?>%
                        </span>
                    </div>
                    <div class="bar tipis"><i class="<?= $selesai ? 'done' : '' ?>" style="width:<?= $pct ?>%"></i></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- REKAP EVALUASI -->
        <div class="sect"><i class="icon-clipboard-list"></i> Rekap evaluasi</div>
        <?php if ($rekap_eval): ?>
            <div class="grid">
                <?php foreach ($rekap_eval as $e): ?>
                    <?php
                    $p     = (int) $e['persentase'];
                    $warna = $p >= 80 ? 'var(--teal)' : ($p >= 60 ? 'var(--biru)' : 'var(--coral)');
                    $bg    = $p >= 80 ? 'var(--teal-muda)' : ($p >= 60 ? 'var(--biru-muda)' : 'var(--coral-muda)');
                    ?>
                    <a href="hasil_evaluasi.php?konten=<?= (int) $e['content_id'] ?>" class="tk">
                        <div class="tk-h">
                            <b style="font-size:13px"><?= htmlspecialchars($topik_label[$e['topik']] ?? $e['topik']) ?></b>
                            <span class="tk-ic" style="background:<?= $bg ?>;color:<?= $warna ?>"><i class="icon-award"></i></span>
                        </div>
                        <span class="tk-pct" style="color:<?= $warna ?>"><?= $p ?>%</span>
                        <div class="bar"><i style="width:<?= $p ?>%;background:<?= $warna ?>"></i></div>
                        <div class="tags">
                            <span class="tag"><i class="icon-check"></i> <?= (int) $e['skor'] ?>/<?= (int) $e['total'] ?></span>
                            <?php if ((int) $e['percobaan'] > 1): ?>
                                <span class="tag"><i class="icon-refresh-cw"></i> <?= (int) $e['percobaan'] ?>×</span>
                            <?php endif; ?>
                            <span class="tag"><i class="icon-clock"></i> <?= date('d/m/y', strtotime($e['updated_at'])) ?></span>
                        </div>
                        <span class="tk-go" style="color:var(--biru)">Lihat pembahasan <i class="icon-arrow-right"></i></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="kosong">
                    <i class="icon-clipboard-list"></i>
                    <b>Belum ada evaluasi yang dikerjakan</b>
                    <p>Kerjakan evaluasi di tiap topik untuk mengukur pemahamanmu.</p>
                    <a href="evaluasi.php" class="btn btn-2 btn-sm" style="margin-top:16px">
                        Lihat daftar evaluasi <i class="icon-arrow-right"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- RIWAYAT PRE-TEST -->
        <?php if (count($riwayat_pretest) > 1): ?>
            <div class="sect"><i class="icon-history"></i> Riwayat pre-test</div>
            <div class="card" style="display:flex;flex-direction:column;gap:9px">
                <?php foreach ($riwayat_pretest as $i => $r): ?>
                    <div class="rata" style="background:var(--kanvas);border-radius:var(--r-sm);padding:11px 13px">
                        <span style="width:24px;height:24px;border-radius:50%;background:<?= $i === 0 ? 'var(--biru)' : 'var(--abu-muda)' ?>;
                                     color:#fff;display:grid;place-items:center;font-size:11px;font-weight:800;flex-shrink:0">
                            <?= count($riwayat_pretest) - $i ?>
                        </span>
                        <div style="flex:1;min-width:0">
                            <div style="font-size:12.5px;font-weight:700">
                                <?= $label_profil[$r['profil_learning']] ?? $r['profil_learning'] ?>
                                <span style="color:var(--abu-muda);font-weight:600">·</span>
                                <?= $label_level[$r['level_kemampuan']] ?? $r['level_kemampuan'] ?>
                            </div>
                            <div style="font-size:11px;color:var(--abu-muda);font-weight:600;margin-top:2px">
                                Skor <?= $r['skor_pengetahuan'] ?>/12 · <?= date('d M Y H:i', strtotime($r['created_at'])) ?>
                            </div>
                        </div>
                        <?php if ($i === 0): ?>
                            <span class="tag ok" style="flex-shrink:0"><i class="icon-check"></i> Aktif</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="card tengah" style="padding:16px">
            <p style="font-size:12px;color:var(--abu-muda);line-height:1.6;margin-bottom:12px">
                Mengulang pre-test akan mengubah profil belajar dan urutan materimu.
            </p>
            <a href="pretest.php" class="btn btn-2 btn-sm" style="display:inline-flex">
                <i class="icon-refresh-cw"></i> Ulangi pre-test
            </a>
        </div>

    <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/bottomnav_siswa.php'; ?>
</body>
</html>
