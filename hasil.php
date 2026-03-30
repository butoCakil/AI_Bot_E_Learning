<?php
session_start();
require_once 'config/config.php';

if (empty($_SESSION['hasil_pretest']) || empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$hasil  = $_SESSION['hasil_pretest'];
$nama   = $_SESSION['nama'];
$kelas  = $_SESSION['kelas'];

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
$deskripsi_profil = [
    'guided_step'       => 'Kamu belajar paling efektif dengan panduan langkah demi langkah. Sistem akan menyajikan materi secara terstruktur dan bertahap untukmu.',
    'conceptual'        => 'Kamu belajar paling efektif dengan memahami konsep secara mendalam terlebih dahulu. Sistem akan menyajikan penjelasan konseptual yang lengkap untukmu.',
    'practice_oriented' => 'Kamu belajar paling efektif dengan langsung praktik dan eksplorasi. Sistem akan menyajikan tantangan dan proyek nyata untukmu.',
];
$warna_level = [
    'beginner'     => '#e67e22',
    'intermediate' => '#2980b9',
    'advanced'     => '#27ae60',
];

$profil  = $hasil['profil_learning'];
$level   = $hasil['level'];
$skor    = $hasil['skor'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hasil Pre-Test — AdaptLearn PRE</title>
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
    max-width: 500px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    text-align: center;
    animation: fadeIn 0.5s ease;
  }
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .checkmark {
    width: 64px;
    height: 64px;
    background: #e8f8f0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 30px;
  }
  h2 {
    font-size: 20px;
    color: #1a1a2e;
    margin-bottom: 6px;
  }
  .subtitle {
    font-size: 14px;
    color: #888;
    margin-bottom: 28px;
  }
  .profil-box {
    background: #f0f7ff;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
  }
  .profil-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #0f3460;
    margin-bottom: 6px;
  }
  .profil-nama {
    font-size: 22px;
    font-weight: 700;
    color: #0f3460;
    margin-bottom: 8px;
  }
  .profil-desc {
    font-size: 13px;
    color: #555;
    line-height: 1.6;
  }
  .stats {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
  }
  .stat-box {
    flex: 1;
    background: #f8f8f8;
    border-radius: 10px;
    padding: 14px;
  }
  .stat-label {
    font-size: 11px;
    color: #999;
    margin-bottom: 4px;
  }
  .stat-value {
    font-size: 20px;
    font-weight: 700;
    color: #1a1a2e;
  }
  .level-badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
    color: #fff;
    background: <?= $warna_level[$level] ?? '#666' ?>;
  }
  .btn {
    display: block;
    width: 100%;
    padding: 14px;
    background: #0f3460;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: background 0.2s;
    margin-top: 8px;
  }
  .btn:hover { background: #16213e; }
  .btn-outline {
    background: transparent;
    border: 2px solid #0f3460;
    color: #0f3460;
  }
  .btn-outline:hover { background: #f0f7ff; }
</style>
</head>
<body>
<div class="card">
  <div class="checkmark">✓</div>
  <h2>Pre-Test Selesai!</h2>
  <p class="subtitle">Halo, <strong><?= htmlspecialchars($nama) ?></strong> — <?= htmlspecialchars($kelas) ?></p>

  <div class="profil-box">
    <div class="profil-label">Profil Belajarmu</div>
    <div class="profil-nama"><?= $label_profil[$profil] ?? $profil ?></div>
    <div class="profil-desc"><?= $deskripsi_profil[$profil] ?? '' ?></div>
  </div>

  <div class="stats">
    <div class="stat-box">
      <div class="stat-label">Skor Pengetahuan</div>
      <div class="stat-value"><?= $skor ?> / 12</div>
    </div>
    <div class="stat-box">
      <div class="stat-label">Level</div>
      <div class="stat-value">
        <span class="level-badge"><?= $label_level[$level] ?? $level ?></span>
      </div>
    </div>
  </div>

  <a href="materi.php" class="btn">Mulai Belajar →</a>
  <a href="index.php" class="btn btn-outline" style="margin-top:10px">Ulangi Pre-Test</a>
</div>
</body>
</html>
