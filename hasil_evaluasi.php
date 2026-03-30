<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

if (empty($_SESSION['user_id']) || empty($_SESSION['hasil_evaluasi'])) {
    header('Location: index.php');
    exit;
}

$hasil           = $_SESSION['hasil_evaluasi'];
$topik           = $hasil['topik'];
$skor            = $hasil['skor'];
$total           = $hasil['total'];
$persentase      = $hasil['persentase'];
$hasil_soal      = $hasil['hasil_soal'];
$profil_gabungan = $hasil['profil_gabungan'];
$nama            = $_SESSION['nama'];

$topik_label = [
    'dioda'      => 'Dioda',
    'transistor' => 'Transistor',
    'catu_daya'  => 'Catu Daya',
];

$warna = $persentase >= 80 ? '#27ae60' : ($persentase >= 60 ? '#2980b9' : '#e74c3c');
$pesan = $persentase >= 80
    ? 'Bagus sekali! Kamu memahami materi ini dengan baik.'
    : ($persentase >= 60
        ? 'Cukup baik. Ada beberapa konsep yang perlu diperkuat.'
        : 'Perlu belajar lebih lanjut. Coba baca ulang materi sebelum lanjut.');

// Hapus session evaluasi agar tidak muncul lagi
unset($_SESSION['hasil_evaluasi']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hasil Evaluasi — AdaptLearn PRE</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    min-height: 100vh;
    padding: 30px 20px;
}
.container {
    max-width: 640px;
    margin: 0 auto;
}
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
.hasil-header { text-align: center; margin-bottom: 24px; }
.skor-circle {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: <?= $warna ?>;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    color: #fff;
}
.skor-angka { font-size: 28px; font-weight: 700; line-height: 1; }
.skor-label { font-size: 11px; opacity: 0.85; margin-top: 2px; }
.hasil-header h2 { font-size: 20px; color: #1a1a2e; margin-bottom: 6px; }
.hasil-header p { font-size: 14px; color: #666; }
.pesan-box {
    padding: 14px 18px;
    border-radius: 10px;
    font-size: 14px;
    margin-bottom: 24px;
    border-left: 4px solid <?= $warna ?>;
    background: <?= $persentase >= 80 ? '#e8f8f0' : ($persentase >= 60 ? '#e8f4fd' : '#fde8e8') ?>;
    color: #333;
}
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
    margin-bottom: 12px;
    border: 1px solid #e0e0e0;
}
.soal-item.benar { border-color: #a8e6cf; background: #f0fff8; }
.soal-item.salah { border-color: #ffb3b3; background: #fff5f5; }
.soal-teks { font-size: 14px; font-weight: 600; color: #1a1a2e; margin-bottom: 10px; }
.jawaban-info {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 13px;
}
.jwb-badge {
    padding: 4px 10px;
    border-radius: 6px;
    font-weight: 600;
}
.jwb-benar { background: #d4edda; color: #155724; }
.jwb-salah { background: #f8d7da; color: #721c24; }
.jwb-kunci { background: #d1ecf1; color: #0c5460; }
.status-icon { font-size: 16px; float: right; }
.nav-buttons {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.btn {
    flex: 1;
    padding: 13px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    border: none;
    text-align: center;
    transition: all 0.2s;
}
.btn-primary { background: #0f3460; color: #fff; }
.btn-primary:hover { background: #16213e; }
.btn-outline { background: transparent; border: 2px solid #0f3460; color: #0f3460; }
.btn-outline:hover { background: #f0f7ff; }
</style>
</head>
<body>
<div class="container">

    <div class="card">
        <div class="hasil-header">
            <div class="skor-circle">
                <span class="skor-angka"><?= $persentase ?>%</span>
                <span class="skor-label"><?= $skor ?>/<?= $total ?></span>
            </div>
            <h2>Hasil Evaluasi — <?= htmlspecialchars($topik_label[$topik] ?? $topik) ?></h2>
            <p>Halo <strong><?= htmlspecialchars($nama) ?></strong>, berikut hasil evaluasimu.</p>
        </div>

        <div class="pesan-box"><?= htmlspecialchars($pesan) ?></div>

        <div class="section-title">Pembahasan per soal</div>

        <?php foreach ($hasil_soal as $i => $item): ?>
        <div class="soal-item <?= $item['benar'] ? 'benar' : 'salah' ?>">
            <span class="status-icon"><?= $item['benar'] ? '✓' : '✗' ?></span>
            <div class="soal-teks"><?= ($i+1) ?>. <?= htmlspecialchars($item['soal']) ?></div>
            <div class="jawaban-info">
                <span>
                    Jawabanmu:
                    <span class="jwb-badge <?= $item['benar'] ? 'jwb-benar' : 'jwb-salah' ?>">
                        <?= htmlspecialchars($item['jawaban']) ?>
                    </span>
                </span>
                <?php if (!$item['benar']): ?>
                <span>
                    Kunci:
                    <span class="jwb-badge jwb-kunci"><?= htmlspecialchars($item['kunci']) ?></span>
                </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="nav-buttons" style="margin-top:24px">
            <a href="/materi.php?topik=<?= urlencode($topik) ?>" class="btn btn-outline">← Kembali ke Materi</a>
            <a href="/materi.php?topik=<?= urlencode(array_keys($topik_label)[array_search($topik, array_keys($topik_label)) + 1] ?? $topik) ?>"
               class="btn btn-primary">Topik Berikutnya →</a>
        </div>
    </div>

</div>
</body>
</html>
