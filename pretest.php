<?php
session_start();
require_once 'config/config.php';
require_once 'config/soal_pretest.php';
require_once 'includes/functions.php';

require_login();

// Error dari percobaan sebelumnya
$pretest_error = $_SESSION['pretest_error'] ?? null;
unset($_SESSION['pretest_error']);

// Inisialisasi sesi pretest
if (empty($_SESSION['pretest_mulai'])) {
    $_SESSION['pretest_mulai'] = true;
    $_SESSION['soal_no']       = 0;
    $_SESSION['jawaban_pngt']  = [];
    $_SESSION['jawaban_sjt']   = [];
}

// Gabung semua soal: 12 pengetahuan + 8 SJT
$semua_soal = [];
foreach (SOAL_PENGETAHUAN as $s) {
    $semua_soal[] = array_merge($s, ['bagian' => 'pengetahuan']);
}
foreach (SOAL_SJT as $s) {
    $semua_soal[] = array_merge($s, ['bagian' => 'sjt']);
}
$total = count($semua_soal); // 20

// ── Proses jawaban ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jawaban'])) {
    $no      = (int) $_POST['soal_no'];
    $jawaban = strtoupper(trim($_POST['jawaban']));
    $soal    = $semua_soal[$no];

    if ($soal['bagian'] === 'pengetahuan') {
        $_SESSION['jawaban_pngt'][] = $jawaban;
    } else {
        $_SESSION['jawaban_sjt'][] = $jawaban;
    }

    $_SESSION['soal_no'] = $no + 1;

    // Semua soal selesai — proses langsung (tanpa cURL)
    if ($_SESSION['soal_no'] >= $total) {

        $user_id_pt   = (int) $_SESSION['user_id'];
        $jawaban_pngt = $_SESSION['jawaban_pngt'];
        $jawaban_sjt  = $_SESSION['jawaban_sjt'];

        if (count($jawaban_pngt) !== 12 || count($jawaban_sjt) !== 8) {
            $_SESSION['pretest_error'] = 'Jumlah jawaban tidak sesuai. Silakan ulangi pre-test.';
            unset($_SESSION['pretest_mulai'], $_SESSION['soal_no'],
                  $_SESSION['jawaban_pngt'], $_SESSION['jawaban_sjt']);
            header('Location: pretest.php');
            exit;
        }

        $skor_pt     = hitung_skor($jawaban_pngt, KUNCI_JAWABAN);
        $klasifikasi = classify_siswa($jawaban_sjt, $skor_pt);

        if ($klasifikasi['status'] !== 'ok') {
            error_log('Pretest klasifikasi gagal (user ' . $user_id_pt . '): ' . $klasifikasi['message']);
            $_SESSION['pretest_error'] = 'Sistem gagal memproses hasil pre-test. Hubungi guru atau coba lagi.';
            unset($_SESSION['pretest_mulai'], $_SESSION['soal_no'],
                  $_SESSION['jawaban_pngt'], $_SESSION['jawaban_sjt']);
            header('Location: pretest.php');
            exit;
        }

        $pretest_id = simpan_pretest($user_id_pt, $jawaban_pngt, $jawaban_sjt, $skor_pt, $klasifikasi);

        log_aktivitas($user_id_pt, 'pretest', null, null, [
            'pretest_id'      => $pretest_id,
            'skor'            => $skor_pt,
            'profil_gabungan' => $klasifikasi['profil_gabungan'],
        ]);

        $_SESSION['hasil_pretest'] = [
            'status'          => 'ok',
            'pretest_id'      => $pretest_id,
            'skor'            => $skor_pt,
            'level'           => $klasifikasi['level'],
            'profil_learning' => $klasifikasi['profil_learning'],
            'profil_gabungan' => $klasifikasi['profil_gabungan'],
            'probabilitas'    => $klasifikasi['probabilitas'],
        ];

        unset($_SESSION['pretest_mulai'], $_SESSION['soal_no'],
              $_SESSION['jawaban_pngt'], $_SESSION['jawaban_sjt']);

        header('Location: hasil.php');
        exit;
    }

    header('Location: pretest.php');
    exit;
}

$no_sekarang  = $_SESSION['soal_no'];
$soal         = $semua_soal[$no_sekarang];
$progress     = round(($no_sekarang / $total) * 100);
$is_sjt       = $soal['bagian'] === 'sjt';
$bagian_label = $is_sjt ? 'Bagian B — Skenario belajar' : 'Bagian A — Pengetahuan';
$terakhir     = ($no_sekarang + 1) >= $total;

$page_title   = 'Pre-Test — AdaptLearn PRE';
$tanpa_nav    = true;
include __DIR__ . '/includes/topbar_siswa.php';
?>

<div class="wrap" style="max-width:640px">

    <!-- PROGRESS -->
    <div class="card" style="padding:14px 18px">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:9px">
            <span style="font-size:12px;font-weight:700;color:var(--abu);min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?= htmlspecialchars($_SESSION['nama']) ?> · <?= htmlspecialchars($_SESSION['kelas'] ?? '-') ?>
            </span>
            <span style="font-size:12px;font-weight:800;color:var(--biru);white-space:nowrap">
                Soal <?= $no_sekarang + 1 ?>/<?= $total ?>
            </span>
        </div>
        <div class="bar tipis"><i style="width:<?= $progress ?>%"></i></div>
    </div>

    <?php if ($pretest_error): ?>
        <div class="pt" style="border-left-color:var(--coral)">
            <div class="pt-ic" style="background:var(--coral-muda);color:var(--coral)"><i class="icon-triangle-alert"></i></div>
            <div>
                <b>Terjadi masalah</b>
                <p style="margin-bottom:0"><?= htmlspecialchars($pretest_error) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- SOAL -->
    <div class="card">
        <div class="rata" style="margin-bottom:16px">
            <span class="chip <?= $is_sjt ? 'pro' : '' ?>" style="margin:0">
                <i class="<?= $is_sjt ? 'icon-message-circle-question' : 'icon-book-open' ?>"></i>
                <?= $bagian_label ?>
            </span>
        </div>

        <div style="font-size:16px;font-weight:700;line-height:1.65;margin-bottom:22px">
            <?= htmlspecialchars($soal['soal']) ?>
        </div>

        <form method="POST" id="form-soal">
            <input type="hidden" name="soal_no" value="<?= $no_sekarang ?>">
            <ul class="opsi">
                <?php foreach ($soal['opsi'] as $huruf => $teks): ?>
                    <li>
                        <label>
                            <input type="radio" name="jawaban" value="<?= $huruf ?>"
                                   onchange="document.getElementById('btn-next').disabled=false">
                            <span style="display:flex;align-items:flex-start;gap:10px;flex:1">
                                <b style="flex-shrink:0"><?= $huruf ?>.</b>
                                <span><?= htmlspecialchars($teks) ?></span>
                            </span>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>

            <button type="submit" class="btn btn-1 btn-full" id="btn-next" disabled style="margin-top:20px">
                <?php if ($terakhir): ?>
                    <i class="icon-check"></i> Selesai &amp; lihat hasil
                <?php else: ?>
                    Lanjut <i class="icon-arrow-right"></i>
                <?php endif; ?>
            </button>
        </form>
    </div>

    <?php if ($is_sjt): ?>
        <p style="font-size:11.5px;color:var(--abu-muda);text-align:center;line-height:1.6">
            Tidak ada jawaban benar atau salah di bagian ini. Pilih yang paling sesuai dengan caramu belajar.
        </p>
    <?php endif; ?>

    <div class="tengah">
        <a href="#" onclick="return konfirmasiKeluar()" style="font-size:12px;color:var(--abu-muda);font-weight:600;text-decoration:none">
            <i class="icon-x"></i> Keluar dari pre-test
        </a>
    </div>

</div>

<script>
function konfirmasiKeluar() {
    if (confirm('Yakin ingin keluar? Jawaban yang sudah diisi tidak akan tersimpan.')) {
        window.location.href = 'home.php';
    }
    return false;
}
</script>
</body>
</html>
