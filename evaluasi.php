<?php
/* ============================================================
   Rekap Evaluasi — AdaptLearn PRE
   ------------------------------------------------------------
   Menampilkan seluruh evaluasi yang ADA DI ALUR ADAPTIF siswa
   (sumber: adaptation_rules), beserta status pengerjaannya.

   Catatan penting:
   Daftar evaluasi TIDAK diambil langsung dari tabel `content`,
   melainkan dari urutan_content milik profil siswa. Jika diambil
   langsung dari `content`, siswa bisa melihat evaluasi yang tidak
   termasuk jalur belajarnya dan link-nya akan mentok di materi.php.
   ============================================================ */

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

$pdo        = db();
$topik_list = get_topik_list();

// ── Kumpulkan evaluasi dari alur adaptif tiap topik ──────────
$daftar     = [];
$jml_selesai = 0;
$jml_total   = 0;
$total_persen = 0;

foreach ($topik_list as $slug => $nama_topik) {
    $stmt = $pdo->prepare("SELECT urutan_content FROM adaptation_rules WHERE profil_gabungan = ? AND topik = ? LIMIT 1");
    $stmt->execute([$profil['profil_gabungan'], $slug]);
    $urutan = json_decode($stmt->fetchColumn() ?: '[]', true) ?? [];
    $urutan = array_values(array_unique($urutan));

    if (!$urutan) continue;

    // Ambil hanya konten bertipe evaluasi dari urutan tersebut
    $ph   = implode(',', array_fill(0, count($urutan), '?'));
    $stmt = $pdo->prepare("SELECT id, judul, isi FROM `content` WHERE id IN ($ph) AND tipe = 'evaluasi' AND aktif = 1");
    $stmt->execute($urutan);
    $evaluasi_topik = $stmt->fetchAll();

    if (!$evaluasi_topik) continue;

    foreach ($evaluasi_topik as $ev) {
        // Hasil pengerjaan (jika ada)
        $stmt_h = $pdo->prepare("
            SELECT skor, total, persentase, percobaan, updated_at
            FROM evaluasi_results
            WHERE user_id = ? AND content_id = ?
            LIMIT 1
        ");
        $stmt_h->execute([$user_id, $ev['id']]);
        $hasil = $stmt_h->fetch();

        // Jumlah soal (untuk evaluasi yang belum dikerjakan)
        $jml_soal = count(json_decode($ev['isi'], true) ?? []);

        // Kesiapan: berapa materi di topik ini sudah diselesaikan
        $stmt_s = $pdo->prepare("SELECT COUNT(DISTINCT content_id) FROM activity_log WHERE user_id = ? AND tipe = 'selesai_materi' AND topik = ?");
        $stmt_s->execute([$user_id, $slug]);
        $materi_selesai = (int) $stmt_s->fetchColumn();

        $daftar[] = [
            'content_id'     => (int) $ev['id'],
            'judul'          => $ev['judul'],
            'topik_slug'     => $slug,
            'topik_nama'     => $nama_topik,
            'jml_soal'       => $jml_soal,
            'hasil'          => $hasil ?: null,
            'materi_selesai' => $materi_selesai,
        ];

        $jml_total++;
        if ($hasil) {
            $jml_selesai++;
            $total_persen += (int) $hasil['persentase'];
        }
    }
}

$rata_rata   = $jml_selesai > 0 ? round($total_persen / $jml_selesai) : 0;
$persen_rekap = $jml_total > 0 ? round(($jml_selesai / $jml_total) * 100) : 0;

$page_title   = 'Evaluasi — AdaptLearn PRE';
$topbar_aktif = 'evaluasi';
include __DIR__ . '/includes/topbar_siswa.php';
?>

<div class="crumb">
    <a href="home.php">Beranda</a>
    <span class="sep">›</span>
    <span class="now">Evaluasi</span>
</div>

<div class="wrap">

    <div class="hero">
        <h1>Rekap <em>evaluasi</em></h1>
        <p class="sub">Semua evaluasi di jalur belajarmu. Yang belum dikerjakan bisa langsung dibuka dari sini.</p>
        <?php if ($jml_total > 0): ?>
        <div class="chips">
            <span class="chip <?= $jml_selesai === $jml_total ? 'ok' : '' ?>">
                <i class="icon-clipboard-check"></i> <?= $jml_selesai ?>/<?= $jml_total ?> dikerjakan
            </span>
            <?php if ($jml_selesai > 0): ?>
            <span class="chip <?= $rata_rata >= 75 ? 'ok' : ($rata_rata >= 60 ? '' : 'bad') ?>">
                <i class="icon-trending-up"></i> Rata-rata <?= $rata_rata ?>%
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($jml_total === 0): ?>

        <div class="card">
            <div class="kosong">
                <i class="icon-clipboard-list"></i>
                <b>Belum ada evaluasi di jalur belajarmu</b>
                <p>Evaluasi akan muncul di sini begitu tersedia pada topik yang kamu pelajari.</p>
                <a href="materi.php" class="btn btn-1 btn-sm" style="margin-top:16px"><i class="icon-book-open"></i> Buka materi</a>
            </div>
        </div>

    <?php else: ?>

        <div class="card">
            <div class="card-h">
                <h3><i class="icon-list-checks"></i> Progress evaluasi</h3>
                <span class="pct"><?= $persen_rekap ?>%</span>
            </div>
            <p class="card-sub"><?= $jml_selesai ?> dari <?= $jml_total ?> evaluasi selesai</p>
            <div class="bar"><i class="<?= $persen_rekap >= 100 ? 'done' : '' ?>" style="width:<?= $persen_rekap ?>%"></i></div>
        </div>

        <div class="sect"><i class="icon-clipboard-list"></i> Daftar evaluasi</div>

        <?php foreach ($daftar as $d): ?>
            <?php
            $hasil = $d['hasil'];
            if ($hasil) {
                $p = (int) $hasil['persentase'];
                $kelas_pt = $p >= 75 ? 'buka' : ($p >= 60 ? 'info' : 'kunci');
            } else {
                $kelas_pt = 'info';
            }
            ?>
            <div class="pt <?= $kelas_pt ?>">
                <div class="pt-ic">
                    <?php if ($hasil): ?>
                        <i class="icon-circle-check"></i>
                    <?php else: ?>
                        <i class="icon-clipboard-list"></i>
                    <?php endif; ?>
                </div>
                <div style="flex:1;min-width:0">
                    <b><?= htmlspecialchars($d['judul']) ?></b>
                    <p style="margin-bottom:8px"><?= htmlspecialchars($d['topik_nama']) ?></p>

                    <div class="tags" style="margin-bottom:12px">
                        <?php if ($hasil): ?>
                            <span class="tag ok"><i class="icon-check"></i> Sudah dikerjakan</span>
                            <span class="tag <?= (int) $hasil['persentase'] >= 75 ? 'ok' : '' ?>">
                                <i class="icon-award"></i> <?= (int) $hasil['skor'] ?>/<?= (int) $hasil['total'] ?> · <?= (int) $hasil['persentase'] ?>%
                            </span>
                            <?php if ((int) $hasil['percobaan'] > 1): ?>
                                <span class="tag"><i class="icon-refresh-cw"></i> <?= (int) $hasil['percobaan'] ?>× percobaan</span>
                            <?php endif; ?>
                            <span class="tag"><i class="icon-clock"></i> <?= date('d/m/Y', strtotime($hasil['updated_at'])) ?></span>
                        <?php else: ?>
                            <span class="tag"><i class="icon-circle-help"></i> <?= $d['jml_soal'] ?> soal</span>
                            <?php if ($d['materi_selesai'] > 0): ?>
                                <span class="tag ok"><i class="icon-book-open"></i> <?= $d['materi_selesai'] ?> materi dibaca</span>
                            <?php else: ?>
                                <span class="tag"><i class="icon-info"></i> Baca materinya dulu</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="rata" style="flex-wrap:wrap">
                        <?php if ($hasil): ?>
                            <a href="hasil_evaluasi.php?konten=<?= $d['content_id'] ?>" class="btn btn-2 btn-sm">
                                <i class="icon-eye"></i> Lihat pembahasan
                            </a>
                            <a href="materi.php?topik=<?= urlencode($d['topik_slug']) ?>&konten=<?= $d['content_id'] ?>" class="btn btn-2 btn-sm">
                                <i class="icon-refresh-cw"></i> Kerjakan ulang
                            </a>
                        <?php else: ?>
                            <a href="materi.php?topik=<?= urlencode($d['topik_slug']) ?>&konten=<?= $d['content_id'] ?>" class="btn btn-1 btn-sm">
                                Kerjakan sekarang <i class="icon-arrow-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/bottomnav_siswa.php'; ?>
</body>
</html>
