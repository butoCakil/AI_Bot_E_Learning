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
    // Ambil dari database
    $stmt = $pdo->prepare("
        SELECT p.*, pr.skor_pengetahuan as skor_pre
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

    $ngain = hitung_ngain((int)$row['skor_pre'], (int)$row['skor_pengetahuan']);
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

$warna_ngain = $ngain > 0.7
    ? '#27ae60'
    : ($ngain >= 0.3 ? '#2980b9' : '#e74c3c');

// Pembahasan per soal
$soal_list = SOAL_PENGETAHUAN;
$kunci     = KUNCI_JAWABAN;
$hasil_soal = [];
foreach ($soal_list as $i => $soal) {
    $jwb    = strtoupper($jawaban[$i] ?? '');
    $benar  = $jwb === $kunci[$i];
    $hasil_soal[] = ['soal' => $soal['soal'], 'jawaban' => $jwb, 'kunci' => $kunci[$i], 'benar' => $benar];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hasil Post-Test — AdaptLearn PRE</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #1a2e1a 0%, #163016 50%, #0f4020 100%);
    min-height: 100vh;
    padding: 30px 20px;
}
.container { max-width: 640px; margin: 0 auto; }
.card {
    background: #fff;
    border-radius: 16px;
    padding: 32px;
    margin-bottom: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    animation: fadeIn 0.4s ease;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}
.hasil-header { text-align: center; margin-bottom: 28px; }
.hasil-header h2 { font-size: 22px; color: #1a1a2e; margin-bottom: 6px; }
.hasil-header p { font-size: 14px; color: #888; }

/* N-Gain box */
.ngain-box {
    background: <?= $warna_ngain ?>;
    color: #fff;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    margin-bottom: 20px;
}
.ngain-nilai { font-size: 48px; font-weight: 700; line-height: 1; }
.ngain-label { font-size: 14px; opacity: 0.85; margin-top: 4px; }
.ngain-kategori { font-size: 18px; font-weight: 700; margin-top: 8px; }

/* Perbandingan skor */
.skor-compare {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 12px;
    align-items: center;
    margin-bottom: 20px;
}
.skor-box { background: #f8f9fa; border-radius: 10px; padding: 16px; text-align: center; }
.skor-label { font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
.skor-nilai { font-size: 28px; font-weight: 700; color: #1a1a2e; }
.skor-maks  { font-size: 13px; color: #aaa; }
.arrow-box  { font-size: 24px; color: #ccc; text-align: center; }

.rumus-box {
    background: #f0f7ff;
    border-radius: 10px;
    padding: 14px 16px;
    font-size: 13px;
    color: #444;
    margin-bottom: 20px;
    line-height: 1.7;
}
.rumus-box strong { color: #0f3460; }

.section-title {
    font-size: 14px;
    font-weight: 700;
    color: #0f3460;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 14px;
}
.soal-item {
    padding: 14px;
    border-radius: 10px;
    margin-bottom: 10px;
    border: 1px solid #e0e0e0;
}
.soal-item.benar { border-color: #a8e6cf; background: #f0fff8; }
.soal-item.salah { border-color: #ffb3b3; background: #fff5f5; }
.soal-teks { font-size: 13px; font-weight: 600; color: #1a1a2e; margin-bottom: 8px; }
.jawaban-info { display: flex; gap: 12px; flex-wrap: wrap; font-size: 12px; }
.jwb-badge { padding: 3px 8px; border-radius: 6px; font-weight: 600; }
.jwb-benar { background: #d4edda; color: #155724; }
.jwb-salah { background: #f8d7da; color: #721c24; }
.jwb-kunci { background: #d1ecf1; color: #0c5460; }
.status-icon { font-size: 14px; float: right; }
.btn {
    display: block;
    width: 100%;
    padding: 13px;
    background: #0f3460;
    color: #fff;
    border-radius: 10px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    text-align: center;
    transition: background 0.2s;
    margin-top: 8px;
    border: none;
    cursor: pointer;
}
.btn:hover { background: #16213e; }
</style>
</head>
<body>
<div class="container">

    <div class="card">
        <div class="hasil-header">
            <h2>Hasil Post-Test</h2>
            <p>Halo <strong><?= htmlspecialchars($_SESSION['nama']) ?></strong>, berikut hasil post-test dan peningkatan belajarmu.</p>
        </div>

        <!-- N-Gain -->
        <div class="ngain-box">
            <div class="ngain-nilai"><?= number_format($ngain, 2) ?></div>
            <div class="ngain-label">Nilai N-Gain</div>
            <div class="ngain-kategori">Kategori: <?= htmlspecialchars($kategori) ?></div>
        </div>

        <!-- Perbandingan skor -->
        <div class="skor-compare">
            <div class="skor-box">
                <div class="skor-label">Skor Pre-Test</div>
                <div class="skor-nilai"><?= $skor_pre ?></div>
                <div class="skor-maks">dari 12</div>
            </div>
            <div class="arrow-box">→</div>
            <div class="skor-box">
                <div class="skor-label">Skor Post-Test</div>
                <div class="skor-nilai"><?= $skor_post ?></div>
                <div class="skor-maks">dari 12</div>
            </div>
        </div>

        <!-- Rumus N-Gain -->
        <div class="rumus-box">
            <strong>Perhitungan N-Gain (Hake, 1999):</strong><br>
            g = (Skor Post - Skor Pre) / (Skor Maks - Skor Pre)<br>
            g = (<?= $skor_post ?> - <?= $skor_pre ?>) / (12 - <?= $skor_pre ?>)
            = <strong><?= number_format($ngain, 4) ?></strong>
            → Kategori <strong><?= $kategori ?></strong>
        </div>

        <a href="profil.php" class="btn">Kembali ke Profil</a>
    </div>

    <!-- Pembahasan -->
    <div class="card">
        <div class="section-title">Pembahasan per soal</div>
        <?php foreach ($hasil_soal as $i => $item): ?>
        <div class="soal-item <?= $item['benar'] ? 'benar' : 'salah' ?>">
            <span class="status-icon"><?= $item['benar'] ? '✓' : '✗' ?></span>
            <div class="soal-teks"><?= ($i+1) ?>. <?= htmlspecialchars($item['soal']) ?></div>
            <div class="jawaban-info">
                <span>Jawabanmu: <span class="jwb-badge <?= $item['benar'] ? 'jwb-benar' : 'jwb-salah' ?>"><?= $item['jawaban'] ?></span></span>
                <?php if (!$item['benar']): ?>
                <span>Kunci: <span class="jwb-badge jwb-kunci"><?= $item['kunci'] ?></span></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</div>
</body>
</html>
