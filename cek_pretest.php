<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

require_login();

$user_id = $_SESSION['user_id'];
$profil  = get_profil_siswa($user_id);

// Belum pernah pre-test — langsung ke pre-test
if (!$profil) {
    header('Location: pretest.php');
    exit;
}

// Sudah pre-test — tanya mau lanjut atau ulangi
$label_profil = [
    'guided_step'       => 'Guided-Step Learner',
    'conceptual'        => 'Conceptual Learner',
    'practice_oriented' => 'Practice-Oriented Learner',
];
$label_level = [
    'beginner'     => 'Pemula',
    'intermediate' => 'Menengah',
    'advanced'     => 'Mahir',
];
$warna_level = [
    'beginner'     => '#e67e22',
    'intermediate' => '#2980b9',
    'advanced'     => '#27ae60',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Selamat Datang — AdaptLearn PRE</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
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
    border-radius: 16px;
    padding: 40px;
    width: 100%;
    max-width: 460px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    text-align: center;
}
.greeting { font-size: 13px; color: #888; margin-bottom: 6px; }
h2 { font-size: 22px; color: #1a1a2e; margin-bottom: 24px; }
.profil-box {
    background: #f0f7ff;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
}
.profil-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #0f3460; margin-bottom: 6px; }
.profil-nama { font-size: 20px; font-weight: 700; color: #0f3460; margin-bottom: 12px; }
.stats { display: flex; gap: 12px; justify-content: center; }
.stat-box { background: #fff; border-radius: 8px; padding: 10px 16px; }
.stat-label { font-size: 11px; color: #999; margin-bottom: 4px; }
.stat-value { font-size: 18px; font-weight: 700; color: #1a1a2e; }
.badge {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 700;
    color: #fff;
}
.tgl { font-size: 12px; color: #aaa; margin-top: 12px; }
.btn-group { display: flex; flex-direction: column; gap: 10px; }
.btn {
    padding: 14px;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    border: none;
    transition: all 0.2s;
    display: block;
}
.btn-primary { background: #0f3460; color: #fff; }
.btn-primary:hover { background: #16213e; }
.btn-outline { background: transparent; border: 2px solid #0f3460; color: #0f3460; }
.btn-outline:hover { background: #f0f7ff; }
.logout-link { font-size: 12px; color: #aaa; margin-top: 16px; display: block; }
.logout-link:hover { color: #666; }
</style>
</head>
<body>
<div class="card">
    <p class="greeting">Selamat datang kembali,</p>
    <h2><?= htmlspecialchars($_SESSION['nama']) ?></h2>

    <div class="profil-box">
        <div class="profil-label">Profil Belajarmu</div>
        <div class="profil-nama"><?= $label_profil[$profil['profil_learning']] ?? $profil['profil_learning'] ?></div>
        <div class="stats">
            <div class="stat-box">
                <div class="stat-label">Skor</div>
                <div class="stat-value"><?= $profil['skor_pengetahuan'] ?>/12</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Level</div>
                <div class="stat-value">
                    <span class="badge" style="background:<?= $warna_level[$profil['level_kemampuan']] ?? '#888' ?>">
                        <?= $label_level[$profil['level_kemampuan']] ?? $profil['level_kemampuan'] ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="tgl">Pre-test terakhir: <?= date('d/m/Y H:i', strtotime($profil['created_at'])) ?></div>
    </div>

    <div class="btn-group">
        <a href="materi.php" class="btn btn-primary">Lanjutkan Belajar →</a>
        <a href="pretest.php" class="btn btn-outline">Ulangi Pre-Test</a>
    </div>

    <a href="logout.php" class="logout-link">Keluar dari akun</a>
</div>
</body>
</html>
