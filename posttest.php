<?php
session_start();
require_once 'config/config.php';
require_once 'config/soal_pretest.php';
require_once 'includes/functions.php';

require_login();

$user_id = $_SESSION['user_id'];

// Cek akses
$akses = cek_akses_posttest($user_id);
if (!$akses['boleh']) {
    $sudah_selesai = $akses['sudah_selesai'] ?? false;
    include 'views/posttest_locked.php';
    exit;
}

// Inisialisasi sesi post-test
if (empty($_SESSION['posttest_mulai'])) {
    $_SESSION['posttest_mulai'] = true;
    $_SESSION['posttest_no']    = 0;
    $_SESSION['posttest_jwb']   = [];
}

// Hanya soal pengetahuan (12 soal)
$soal_list = SOAL_PENGETAHUAN;
$total     = count($soal_list);

// ── Proses jawaban ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jawaban'])) {
    $no      = (int) $_POST['soal_no'];
    $jawaban = strtoupper(trim($_POST['jawaban']));

    $_SESSION['posttest_jwb'][] = $jawaban;
    $_SESSION['posttest_no']    = $no + 1;

    if ($_SESSION['posttest_no'] >= $total) {
        $jwb_post  = $_SESSION['posttest_jwb'];
        $skor_post = hitung_skor($jwb_post, KUNCI_JAWABAN);

        $pdo  = db();
        $stmt = $pdo->prepare("
            INSERT INTO post_test_results (user_id, jawaban_pengetahuan, skor_pengetahuan)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$user_id, json_encode($jwb_post), $skor_post]);

        $profil   = get_profil_siswa($user_id);
        $skor_pre = $profil ? (int) $profil['skor_pengetahuan'] : 0;
        $ngain    = hitung_ngain($skor_pre, $skor_post);

        log_aktivitas($user_id, 'jawab_quiz', null, 'post_test', [
            'skor_post' => $skor_post,
            'skor_pre'  => $skor_pre,
            'ngain'     => $ngain['ngain'],
            'kategori'  => $ngain['kategori'],
        ]);

        $_SESSION['hasil_posttest'] = [
            'skor_post' => $skor_post,
            'skor_pre'  => $skor_pre,
            'ngain'     => $ngain['ngain'],
            'kategori'  => $ngain['kategori'],
            'jawaban'   => $jwb_post,
        ];

        unset($_SESSION['posttest_mulai'], $_SESSION['posttest_no'], $_SESSION['posttest_jwb']);
        header('Location: hasil_posttest.php');
        exit;
    }

    header('Location: posttest.php');
    exit;
}

$no_sekarang = $_SESSION['posttest_no'];
$soal        = $soal_list[$no_sekarang];
$progress    = round(($no_sekarang / $total) * 100);
$terakhir    = ($no_sekarang + 1) >= $total;

$page_title   = 'Post-Test — AdaptLearn PRE';
$tanpa_nav    = true;
include __DIR__ . '/includes/topbar_siswa.php';
?>

<div class="wrap" style="max-width:640px">

    <!-- PROGRESS -->
    <div class="card" style="padding:14px 18px">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:9px">
            <span class="chip ok" style="margin:0"><i class="icon-target"></i> Post-Test</span>
            <span style="font-size:12px;font-weight:800;color:var(--teal);white-space:nowrap">
                Soal <?= $no_sekarang + 1 ?>/<?= $total ?>
            </span>
        </div>
        <div class="bar tipis"><i class="done" style="width:<?= $progress ?>%"></i></div>
    </div>

    <!-- SOAL -->
    <div class="card">
        <div style="font-size:16px;font-weight:700;line-height:1.65;margin-bottom:22px">
            <?= htmlspecialchars($soal['soal']) ?>
        </div>

        <form method="POST" id="form-soal">
            <input type="hidden" name="soal_no" value="<?= $no_sekarang ?>">
            <ul class="opsi">
                <?php foreach ($soal['opsi'] as $huruf => $teks): ?>
                    <li>
                        <label class="opsi-teal">
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

            <button type="submit" class="btn btn-3 btn-full" id="btn-next" disabled style="margin-top:20px">
                <?php if ($terakhir): ?>
                    <i class="icon-check"></i> Selesai
                <?php else: ?>
                    Lanjut <i class="icon-arrow-right"></i>
                <?php endif; ?>
            </button>
        </form>
    </div>

    <div class="tengah">
        <a href="#" onclick="return konfirmasiKeluar()" style="font-size:12px;color:var(--abu-muda);font-weight:600;text-decoration:none">
            <i class="icon-x"></i> Keluar dari post-test
        </a>
    </div>

</div>

<style>
/* Post-test pakai aksen teal untuk opsi terpilih */
.opsi-teal:hover { border-color: var(--teal) !important; background: var(--teal-muda) !important; }
.opsi input[type=radio]:checked + span b { color: var(--teal); }
.opsi-teal:has(input:checked) { border-color: var(--teal); background: var(--teal-muda); }
</style>

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
