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

// Hanya soal pengetahuan (12 soal) — tidak ada SJT
$soal_list = SOAL_PENGETAHUAN;
$total     = count($soal_list); // 12

// Proses jawaban
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jawaban'])) {
    $no      = (int) $_POST['soal_no'];
    $jawaban = strtoupper(trim($_POST['jawaban']));

    $_SESSION['posttest_jwb'][] = $jawaban;
    $_SESSION['posttest_no']    = $no + 1;

    // Semua soal selesai
    if ($_SESSION['posttest_no'] >= $total) {
        $jwb_post = $_SESSION['posttest_jwb'];
        $skor_post = hitung_skor($jwb_post, KUNCI_JAWABAN);

        // Simpan ke database
        $pdo  = db();
        $stmt = $pdo->prepare("
            INSERT INTO post_test_results (user_id, jawaban_pengetahuan, skor_pengetahuan)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$user_id, json_encode($jwb_post), $skor_post]);

        // Ambil skor pre-test untuk N-Gain
        $profil    = get_profil_siswa($user_id);
        $skor_pre  = $profil ? (int) $profil['skor_pengetahuan'] : 0;
        $ngain     = hitung_ngain($skor_pre, $skor_post);

        // Log aktivitas
        log_aktivitas($user_id, 'jawab_quiz', null, 'post_test', [
            'skor_post'  => $skor_post,
            'skor_pre'   => $skor_pre,
            'ngain'      => $ngain['ngain'],
            'kategori'   => $ngain['kategori'],
        ]);

        // Simpan ke session untuk ditampilkan
        $_SESSION['hasil_posttest'] = [
            'skor_post'  => $skor_post,
            'skor_pre'   => $skor_pre,
            'ngain'      => $ngain['ngain'],
            'kategori'   => $ngain['kategori'],
            'jawaban'    => $jwb_post,
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Post-Test — AdaptLearn PRE</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #1a2e1a 0%, #163016 50%, #0f4020 100%);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.header { width: 100%; max-width: 600px; margin-bottom: 16px; }
.header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.header-top span { color: #fff; font-size: 13px; opacity: 0.85; }
.progress-bar {
    width: 100%;
    height: 6px;
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    background: #4ecdc4;
    border-radius: 10px;
    width: <?= $progress ?>%;
    transition: width 0.4s ease;
}
.card {
    background: #fff;
    border-radius: 16px;
    padding: 36px;
    width: 100%;
    max-width: 600px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: fadeIn 0.3s ease;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}
.label-posttest {
    display: inline-block;
    background: #e8f8f0;
    color: #155724;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 20px;
    margin-bottom: 8px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}
.nomor-soal { font-size: 13px; color: #999; margin-bottom: 16px; }
.pertanyaan { font-size: 16px; font-weight: 600; color: #1a1a2e; line-height: 1.6; margin-bottom: 28px; }
.opsi-list { list-style: none; display: flex; flex-direction: column; gap: 12px; }
.opsi-item label {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    border: 2px solid #e8e8e8;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
    color: #333;
    line-height: 1.5;
}
.opsi-item input[type="radio"] { display: none; }
.opsi-item label:hover { border-color: #27ae60; background: #f0fff8; }
.opsi-item input[type="radio"]:checked + label { border-color: #27ae60; background: #e8f8f0; font-weight: 600; }
.huruf {
    min-width: 28px;
    height: 28px;
    background: #f0f0f0;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 13px;
    color: #666;
    flex-shrink: 0;
}
.opsi-item input[type="radio"]:checked + label .huruf { background: #27ae60; color: #fff; }
.btn-next {
    width: 100%;
    margin-top: 24px;
    padding: 14px;
    background: #27ae60;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-next:hover { background: #219a52; }
.btn-next:disabled { background: #ccc; cursor: not-allowed; }
</style>
</head>
<body>

<div class="header">
    <div class="header-top">
        <span>Post-Test — <?= htmlspecialchars($_SESSION['nama']) ?></span>
        <span>Soal <?= $no_sekarang + 1 ?> / <?= $total ?></span>
    </div>
    <div class="progress-bar"><div class="progress-fill"></div></div>
</div>

<div class="card">
    <span class="label-posttest">Post-Test</span>
    <div class="nomor-soal">Soal <?= $no_sekarang + 1 ?> dari <?= $total ?></div>
    <div class="pertanyaan"><?= htmlspecialchars($soal['soal']) ?></div>

    <form method="POST" id="form-soal">
        <input type="hidden" name="soal_no" value="<?= $no_sekarang ?>">
        <ul class="opsi-list">
            <?php foreach ($soal['opsi'] as $huruf => $teks): ?>
            <li class="opsi-item">
                <input type="radio" name="jawaban" id="opsi_<?= $huruf ?>"
                       value="<?= $huruf ?>" onchange="document.getElementById('btn-next').disabled=false">
                <label for="opsi_<?= $huruf ?>">
                    <span class="huruf"><?= $huruf ?></span>
                    <?= htmlspecialchars($teks) ?>
                </label>
            </li>
            <?php endforeach; ?>
        </ul>
        <button type="submit" class="btn-next" id="btn-next" disabled>
            <?= $no_sekarang + 1 < $total ? 'Lanjut →' : 'Selesai →' ?>
        </button>
    </form>
</div>

</body>
</html>
