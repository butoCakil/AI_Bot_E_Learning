<?php
session_start();
require_once 'config/config.php';
require_once 'config/soal_pretest.php';
require_once 'includes/functions.php';

require_login();

$user_id = $_SESSION['user_id'];
$pdo     = db();

// Ambil dari session atau database
if (!empty($_SESSION['hasil_posttest'])) {
    $hasil = $_SESSION['hasil_posttest'];
    unset($_SESSION['hasil_posttest']);
} else {
    $stmt = $pdo->prepare("
        SELECT p.*, pr.skor_pengetahuan AS skor_pre
        FROM post_test_results p
        JOIN pre_test_results pr ON pr.user_id = p.user_id
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC, pr.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();

    if (!$row) {
        header('Location: profil.php');
        exit;
    }

    $ngain = hitung_ngain((int) $row['skor_pre'], (int) $row['skor_pengetahuan']);
    $hasil = [
        'skor_post' => (int) $row['skor_pengetahuan'],
        'skor_pre'  => (int) $row['skor_pre'],
        'ngain'     => $ngain['ngain'],
        'kategori'  => $ngain['kategori'],
        'jawaban'   => json_decode($row['jawaban_pengetahuan'], true),
    ];
}

$skor_pre  = $hasil['skor_pre'];
$skor_post = $hasil['skor_post'];
$ngain     = $hasil['ngain'];
$kategori  = $hasil['kategori'];
$jawaban   = $hasil['jawaban'] ?? [];

$warna_ng = $ngain > 0.7 ? 'var(--teal)' : ($ngain >= 0.3 ? 'var(--biru)' : 'var(--coral)');
$bg_ng    = $ngain > 0.7 ? 'var(--teal-muda)' : ($ngain >= 0.3 ? 'var(--biru-muda)' : 'var(--coral-muda)');
$selisih  = $skor_post - $skor_pre;

// Pembahasan per soal
$soal_list  = SOAL_PENGETAHUAN;
$kunci      = KUNCI_JAWABAN;
$hasil_soal = [];
foreach ($soal_list as $i => $soal) {
    $jwb   = strtoupper($jawaban[$i] ?? '');
    $benar = $jwb === $kunci[$i];
    $hasil_soal[] = [
        'soal'    => $soal['soal'],
        'opsi'    => $soal['opsi'] ?? [],
        'jawaban' => $jwb,
        'kunci'   => $kunci[$i],
        'benar'   => $benar,
    ];
}
$jml_benar = count(array_filter($hasil_soal, fn($h) => $h['benar']));

$page_title   = 'Hasil Post-Test — AdaptLearn PRE';
$topbar_aktif = 'profil';
include __DIR__ . '/includes/topbar_siswa.php';
?>

<div class="crumb">
    <a href="home.php">Beranda</a>
    <span class="sep">›</span>
    <a href="profil.php">Profil</a>
    <span class="sep">›</span>
    <span class="now">Hasil Post-Test</span>
</div>

<div class="wrap" style="max-width:640px">

    <!-- N-GAIN -->
    <div class="card tengah" style="background:<?= $bg_ng ?>;border-color:transparent">
        <div style="font-size:11px;font-weight:800;color:<?= $warna_ng ?>;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px">
            Nilai N-Gain
        </div>
        <div style="font-size:52px;font-weight:800;letter-spacing:-2px;line-height:1;color:<?= $warna_ng ?>">
            <?= number_format($ngain, 2) ?>
        </div>
        <div style="margin-top:10px">
            <span style="display:inline-block;background:<?= $warna_ng ?>;color:#fff;font-size:13px;font-weight:800;
                         padding:6px 16px;border-radius:99px">
                Kategori <?= htmlspecialchars($kategori) ?>
            </span>
        </div>
    </div>

    <!-- PERBANDINGAN SKOR -->
    <div class="card">
        <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:12px;align-items:center">
            <div class="tengah">
                <div style="font-size:10px;font-weight:800;color:var(--abu-muda);text-transform:uppercase;letter-spacing:.6px;margin-bottom:5px">Pre-Test</div>
                <div style="font-size:28px;font-weight:800;letter-spacing:-1px"><?= $skor_pre ?></div>
                <div style="font-size:11px;color:var(--abu-muda)">dari 12</div>
            </div>
            <div class="tengah">
                <i class="icon-arrow-right" style="font-size:20px;color:var(--abu-muda)"></i>
                <?php if ($selisih !== 0): ?>
                    <div style="font-size:11px;font-weight:800;margin-top:4px;color:<?= $selisih > 0 ? 'var(--teal)' : 'var(--coral)' ?>">
                        <?= $selisih > 0 ? '+' : '' ?><?= $selisih ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="tengah">
                <div style="font-size:10px;font-weight:800;color:var(--abu-muda);text-transform:uppercase;letter-spacing:.6px;margin-bottom:5px">Post-Test</div>
                <div style="font-size:28px;font-weight:800;letter-spacing:-1px;color:<?= $warna_ng ?>"><?= $skor_post ?></div>
                <div style="font-size:11px;color:var(--abu-muda)">dari 12</div>
            </div>
        </div>
    </div>

    <!-- RUMUS -->
    <div class="card" style="background:var(--kanvas)">
        <div style="font-size:12.5px;color:var(--abu);line-height:1.8">
            <b style="color:var(--tinta)">Perhitungan N-Gain (Hake, 1999)</b><br>
            g = (Skor Post − Skor Pre) ÷ (Skor Maks − Skor Pre)<br>
            g = (<?= $skor_post ?> − <?= $skor_pre ?>) ÷ (12 − <?= $skor_pre ?>)
            = <b style="color:<?= $warna_ng ?>"><?= number_format($ngain, 4) ?></b>
            → kategori <b style="color:<?= $warna_ng ?>"><?= htmlspecialchars($kategori) ?></b>
        </div>
    </div>

    <div class="cta">
        <a href="profil.php" class="btn btn-1"><i class="icon-user"></i> Ke profil</a>
        <a href="home.php" class="btn btn-2"><i class="icon-house"></i> Beranda</a>
    </div>

    <!-- PEMBAHASAN -->
    <div class="sect"><i class="icon-list-checks"></i> Pembahasan · <?= $jml_benar ?>/<?= count($hasil_soal) ?> benar</div>

    <?php foreach ($hasil_soal as $i => $item): ?>
        <div class="card" style="border-left:4px solid <?= $item['benar'] ? 'var(--teal)' : 'var(--coral)' ?>">
            <div style="display:flex;gap:10px;align-items:flex-start;margin-bottom:<?= !empty($item['opsi']) ? '12px' : '0' ?>">
                <span style="flex-shrink:0;width:24px;height:24px;border-radius:50%;display:grid;place-items:center;
                             font-size:12px;color:#fff;background:<?= $item['benar'] ? 'var(--teal)' : 'var(--coral)' ?>">
                    <i class="<?= $item['benar'] ? 'icon-check' : 'icon-x' ?>"></i>
                </span>
                <div style="font-size:13.5px;font-weight:700;line-height:1.55;flex:1">
                    <?= ($i + 1) ?>. <?= htmlspecialchars($item['soal']) ?>
                </div>
            </div>

            <?php if (!empty($item['opsi']) && is_array($item['opsi'])): ?>
                <ul class="opsi">
                    <?php foreach ($item['opsi'] as $huruf => $teks): ?>
                        <?php
                        $ini_jawaban = strtoupper($huruf) === strtoupper($item['jawaban']);
                        $ini_kunci   = strtoupper($huruf) === strtoupper($item['kunci']);
                        $gaya = 'background:var(--kartu);border-color:var(--garis)';
                        if ($ini_kunci)        $gaya = 'background:var(--teal-muda);border-color:var(--teal)';
                        elseif ($ini_jawaban)  $gaya = 'background:var(--coral-muda);border-color:var(--coral)';
                        ?>
                        <li>
                            <div style="display:flex;align-items:center;gap:10px;padding:10px 13px;border:2px solid;border-radius:var(--r-sm);font-size:13px;<?= $gaya ?>">
                                <b style="flex-shrink:0"><?= htmlspecialchars($huruf) ?>.</b>
                                <span style="flex:1"><?= htmlspecialchars($teks) ?></span>
                                <?php if ($ini_kunci): ?>
                                    <span class="tag ok" style="flex-shrink:0"><i class="icon-check"></i> Kunci</span>
                                <?php elseif ($ini_jawaban): ?>
                                    <span class="tag" style="flex-shrink:0;background:var(--coral-muda);color:var(--coral)"><i class="icon-x"></i> Jawabanmu</span>
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

</div>

<?php include __DIR__ . '/includes/bottomnav_siswa.php'; ?>
</body>
</html>
