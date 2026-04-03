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
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.card {
    background: #fff;
    border-radius: 16px;
    padding: 40px;
    width: 100%;
    max-width: 440px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    text-align: center;
}
.icon { font-size: 48px; margin-bottom: 16px; }
h2 { font-size: 20px; color: #1a1a2e; margin-bottom: 12px; }
.alasan {
    font-size: 14px;
    color: #555;
    line-height: 1.7;
    background: #f8f9fa;
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 24px;
}
.btn {
    display: inline-block;
    padding: 12px 24px;
    background: #0f3460;
    color: #fff;
    border-radius: 10px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: background 0.2s;
}
.btn:hover { background: #16213e; }
</style>
</head>
<body>
<div class="card">
    <div class="icon"><?= $sudah_selesai ? '✓' : '🔒' ?></div>
    <h2><?= $sudah_selesai ? 'Post-Test Sudah Selesai' : 'Post-Test Belum Bisa Diakses' ?></h2>
    <div class="alasan"><?= htmlspecialchars($akses['alasan']) ?></div>
    <?php if ($sudah_selesai): ?>
        <a href="hasil_posttest.php" class="btn">Lihat Hasil →</a>
    <?php else: ?>
        <a href="materi.php" class="btn">Kembali ke Materi</a>
    <?php endif; ?>
</div>
</body>
</html>
