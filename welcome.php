<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';
require_login();

$user_id = $_SESSION['user_id'];
$profil  = get_profil_siswa($user_id);

// Jika sudah punya profil, langsung ke home
if ($profil) {
    header('Location: home.php');
    exit;
}

$nama = $_SESSION['nama'] ?? 'Siswa';
$kelas = $_SESSION['kelas'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Selamat Datang — AdaptLearn PRE</title>
<style>
* { -webkit-text-size-adjust: 100%; text-size-adjust: 100%; }
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; overflow: hidden; }
body {
    font-family: 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.card {
    background: #fff;
    border-radius: 20px;
    padding: 40px 44px;
    max-width: 560px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    text-align: center;
}
.avatar { font-size: 56px; margin-bottom: 16px; }
.greeting { font-size: 13px; color: #888; margin-bottom: 4px; }
.nama { font-size: 22px; font-weight: 700; color: #1a1a2e; margin-bottom: 4px; }
.kelas { font-size: 13px; color: #aaa; margin-bottom: 24px; }
.divider { height: 1px; background: #f0f0f0; margin: 0 -44px 24px; }
.section-title {
    font-size: 15px; font-weight: 700; color: #0f3460;
    margin-bottom: 8px;
}
.desc {
    font-size: 13px; color: #555; line-height: 1.7;
    margin-bottom: 24px;
}
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 12px;
    margin-bottom: 28px;
}
.info-item {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 14px 10px;
}
.info-num { font-size: 22px; font-weight: 800; color: #0f3460; }
.info-label { font-size: 11px; color: #888; margin-top: 2px; }
.notice {
    background: #fff8e1;
    border: 1px solid #ffe082;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 12px;
    color: #7d5a00;
    margin-bottom: 24px;
    text-align: left;
}
.btn-start {
    display: block;
    width: 100%;
    padding: 14px;
    background: #0f3460;
    color: #fff;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: background 0.2s;
}
.btn-start:hover { background: #16213e; }
.btn-logout {
    display: block;
    margin-top: 12px;
    font-size: 12px;
    color: #aaa;
    text-decoration: none;
}
.btn-logout:hover { color: #e74c3c; }
</style>
</head>
<body>
<div class="card">
    <div class="avatar">👋</div>
    <div class="greeting">Selamat datang,</div>
    <div class="nama"><?= htmlspecialchars($nama) ?></div>
    <div class="kelas"><?= htmlspecialchars($kelas) ?></div>
    <div class="divider"></div>

    <div class="section-title">📋 Sebelum Mulai Belajar</div>
    <div class="desc">
        Untuk menyesuaikan materi dengan kebutuhan belajarmu, kamu perlu mengerjakan
        <strong>Pre-Test</strong> terlebih dahulu. Hasilnya akan menentukan profil dan
        level belajarmu secara otomatis.
    </div>

    <div class="info-grid">
        <div class="info-item">
            <div class="info-num">20</div>
            <div class="info-label">Soal</div>
        </div>
        <div class="info-item">
            <div class="info-num">~10</div>
            <div class="info-label">Menit</div>
        </div>
        <div class="info-item">
            <div class="info-num">1×</div>
            <div class="info-label">Cukup sekali</div>
        </div>
    </div>

    <div class="notice">
        💡 Jawab dengan jujur sesuai kemampuanmu. Tidak ada jawaban benar atau salah —
        hasil pre-test hanya digunakan untuk menyesuaikan materi belajarmu.
    </div>

    <a href="pretest.php" class="btn-start">Saya Siap — Mulai Pre-Test →</a>
    <a href="logout.php" class="btn-logout">Keluar dari akun</a>
</div>
</body>
</html>
