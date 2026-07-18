<?php
/* ============================================================
   Hasil & Pembahasan Evaluasi — AdaptLearn PRE
   ------------------------------------------------------------
   PERUBAHAN dari versi lama:
   Dulu membaca $_SESSION['hasil_evaluasi'] yang hanya terisi
   tepat setelah submit — sehingga halaman ini tidak bisa dibuka
   ulang. Sekarang membaca dari tabel `evaluasi_results`, jadi
   pembahasan bisa diakses kapan saja lewat evaluasi.php.

   Parameter: ?konten=<content_id>
   ============================================================ */

session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

require_login();

$user_id = $_SESSION['user_id'];
$pdo     = db();

// ── Tentukan evaluasi yang dibuka ────────────────────────────
$content_id = isset($_GET['konten']) ? (int) $_GET['konten'] : 0;

if ($content_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM evaluasi_results WHERE user_id = ? AND content_id = ? LIMIT 1");
    $stmt->execute([$user_id, $content_id]);
} else {
    // Tanpa parameter: tampilkan hasil terakhir
    $stmt = $pdo->prepare("SELECT * FROM evaluasi_results WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute([$user_id]);
}
$hasil = $stmt->fetch();

if (!$hasil) {
    header('Location: evaluasi.php');
    exit;
}

$content_id  = (int) $hasil['content_id'];
$topik       = $hasil['topik'];
$skor        = (int) $hasil['skor'];
$total       = (int) $hasil['total'];
$persentase  = (int) $hasil['persentase'];
$percobaan   = (int) $hasil['percobaan'];
$hasil_soal  = json_decode($hasil['hasil_soal'], true) ?? [];

$topik_list  = get_topik_list();
$topik_nama  = $topik_list[$topik] ?? $topik;

// Judul evaluasi
$stmt_j = $pdo->prepare("SELECT judul FROM `content` WHERE id = ? LIMIT 1");
$stmt_j->execute([$content_id]);
$judul_evaluasi = $stmt_j->fetchColumn() ?: 'Evaluasi';

// ── Pesan & warna berdasarkan capaian ────────────────────────
if ($persentase >= 80) {
    $status_kelas = 'buka';
    $status_ikon  = 'icon-party-popper';
    $pesan = 'Bagus sekali. Kamu sudah memahami materi ini dengan baik.';
} elseif ($persentase >= 60) {
    $status_kelas = 'info';
    $status_ikon  = 'icon-thumbs-up';
    $pesan = 'Cukup baik. Masih ada beberapa konsep yang perlu diperkuat.';
} else {
    $status_kelas = 'kunci';
    $status_ikon  = 'icon-book-open';
    $pesan = 'Perlu belajar lagi. Coba baca ulang materinya, lalu kerjakan sekali lagi.';
}

// ── Navigasi: konten setelah evaluasi ini ────────────────────
$profil = get_profil_siswa($user_id);
$next_konten_id = null;
$next_topik     = null;

if ($profil) {
    $rule = $pdo->prepare("SELECT urutan_content FROM adaptation_rules WHERE profil_gabungan = ? AND topik = ? LIMIT 1");
    $rule->execute([$profil['profil_gabungan'], $topik]);
    $urutan = array_values(array_unique(json_decode($rule->fetchColumn() ?: '[]', true) ?? []));

    $pos = array_search($content_id, $urutan);
    if ($pos !== false && isset($urutan[$pos + 1])) {
        $next_konten_id = (int) $urutan[$pos + 1];
    }
}

if (!$next_konten_id) {
    $keys = array_keys($topik_list);
    $idx  = array_search($topik, $keys);
    $next_topik = ($idx !== false) ? ($keys[$idx + 1] ?? null) : null;
}

$page_title   = 'Hasil Evaluasi — AdaptLearn PRE';
$topbar_aktif = 'evaluasi';
include __DIR__ . '/includes/topbar_siswa.php';
?>

<div class="crumb">
    <a href="home.php">Beranda</a>
    <span class="sep">›</span>
    <a href="evaluasi.php">Evaluasi</a>
    <span class="sep">›</span>
    <span class="now"><?= htmlspecialchars($topik_nama) ?></span>
</div>

<div class="wrap">

    <!-- RINGKASAN SKOR -->
    <div class="card tengah">
        <div style="width:96px;height:96px;border-radius:50%;margin:4px auto 16px;display:grid;place-items:center;
                    background:<?= $persentase >= 80 ? 'var(--teal-muda)' : ($persentase >= 60 ? 'var(--biru-muda)' : 'var(--coral-muda)') ?>">
            <div>
                <div style="font-size:28px;font-weight:800;letter-spacing:-1px;line-height:1;
                            color:<?= $persentase >= 80 ? 'var(--teal)' : ($persentase >= 60 ? 'var(--biru)' : 'var(--coral)') ?>">
                    <?= $persentase ?>%
                </div>
                <div style="font-size:11px;font-weight:700;color:var(--abu-muda);margin-top:3px"><?= $skor ?>/<?= $total ?> benar</div>
            </div>
        </div>

        <h2 style="font-size:18px;font-weight:800;letter-spacing:-.3px;line-height:1.4">
            <?= htmlspecialchars($judul_evaluasi) ?>
        </h2>
        <p style="font-size:12.5px;color:var(--abu-muda);font-weight:600;margin-top:4px">
            <?= htmlspecialchars($topik_nama) ?>
            <?php if ($percobaan > 1): ?>
                · percobaan ke-<?= $percobaan ?>
            <?php endif; ?>
        </p>
    </div>

    <!-- PESAN -->
    <div class="pt <?= $status_kelas ?>">
        <div class="pt-ic"><i class="<?= $status_ikon ?>"></i></div>
        <div>
            <b>
                <?php if ($persentase >= 80): ?>Hasil bagus
                <?php elseif ($persentase >= 60): ?>Sudah cukup baik
                <?php else: ?>Perlu diulang
                <?php endif; ?>
            </b>
            <p style="margin-bottom:0"><?= htmlspecialchars($pesan) ?></p>
        </div>
    </div>

    <!-- PEMBAHASAN -->
    <div class="sect"><i class="icon-list-checks"></i> Pembahasan per soal</div>

    <?php foreach ($hasil_soal as $i => $item): ?>
        <div class="card" style="border-left:4px solid <?= $item['benar'] ? 'var(--teal)' : 'var(--coral)' ?>">
            <div style="display:flex;gap:10px;align-items:flex-start;margin-bottom:12px">
                <span style="flex-shrink:0;width:24px;height:24px;border-radius:50%;display:grid;place-items:center;
                             font-size:12px;color:#fff;background:<?= $item['benar'] ? 'var(--teal)' : 'var(--coral)' ?>">
                    <i class="<?= $item['benar'] ? 'icon-check' : 'icon-x' ?>"></i>
                </span>
                <div style="font-size:13.5px;font-weight:700;line-height:1.55;flex:1">
                    <?= ($i + 1) ?>. <?= htmlspecialchars($item['soal']) ?>
                </div>
            </div>

            <?php if (!empty($item['opsi']) && is_array($item['opsi'])): ?>
                <ul class="opsi" style="margin-bottom:10px">
                    <?php foreach ($item['opsi'] as $huruf => $teks): ?>
                        <?php
                        $ini_jawaban = strtoupper($huruf) === strtoupper($item['jawaban']);
                        $ini_kunci   = strtoupper($huruf) === strtoupper($item['kunci']);

                        $gaya = 'background:var(--kartu);border-color:var(--garis)';
                        if ($ini_kunci) {
                            $gaya = 'background:var(--teal-muda);border-color:var(--teal)';
                        } elseif ($ini_jawaban) {
                            $gaya = 'background:var(--coral-muda);border-color:var(--coral)';
                        }
                        ?>
                        <li>
                            <div style="display:flex;align-items:center;gap:10px;padding:10px 13px;border:2px solid;border-radius:var(--r-sm);font-size:13px;<?= $gaya ?>">
                                <b style="flex-shrink:0"><?= htmlspecialchars($huruf) ?>.</b>
                                <span style="flex:1"><?= htmlspecialchars($teks) ?></span>
                                <?php if ($ini_kunci): ?>
                                    <span class="tag ok" style="flex-shrink:0"><i class="icon-check"></i> Kunci</span>
                                <?php elseif ($ini_jawaban): ?>
                                    <span class="tag" style="flex-shrink:0;background:var(--coral-muda);color:var(--coral)">
                                        <i class="icon-x"></i> Jawabanmu
                                    </span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="tags">
                    <span class="tag <?= $item['benar'] ? 'ok' : '' ?>" style="<?= $item['benar'] ? '' : 'background:var(--coral-muda);color:var(--coral)' ?>">
                        Jawabanmu: <?= htmlspecialchars($item['jawaban'] ?: '—') ?>
                    </span>
                    <?php if (!$item['benar']): ?>
                        <span class="tag ok">Kunci: <?= htmlspecialchars($item['kunci']) ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <!-- NAVIGASI -->
    <div class="nav-btn" style="margin-top:4px">
        <a href="evaluasi.php" class="btn btn-2"><i class="icon-arrow-left"></i> Rekap evaluasi</a>

        <?php if ($next_konten_id): ?>
            <a href="materi.php?topik=<?= urlencode($topik) ?>&konten=<?= $next_konten_id ?>" class="btn btn-1">
                Materi berikutnya <i class="icon-arrow-right"></i>
            </a>
        <?php elseif ($next_topik): ?>
            <a href="materi.php?topik=<?= urlencode($next_topik) ?>" class="btn btn-1">
                Topik berikutnya <i class="icon-arrow-right"></i>
            </a>
        <?php else: ?>
            <a href="materi.php?finish=1" class="btn btn-3"><i class="icon-flag"></i> Selesai semua materi</a>
        <?php endif; ?>
    </div>

</div>

<?php include __DIR__ . '/includes/bottomnav_siswa.php'; ?>
</body>
</html>
