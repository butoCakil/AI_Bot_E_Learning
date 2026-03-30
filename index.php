<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

// Jika sudah registrasi, langsung ke pretest
if (!empty($_SESSION['user_id'])) {
    header('Location: pretest.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = trim($_POST['nama'] ?? '');
    $kelas    = trim($_POST['kelas'] ?? '');
    $nomor_wa = trim($_POST['nomor_wa'] ?? '');

    if (!$nama || !$kelas || !$nomor_wa) {
        $error = 'Semua field wajib diisi.';
    } elseif (!preg_match('/^[0-9]{10,15}$/', $nomor_wa)) {
        $error = 'Nomor WA tidak valid. Gunakan format: 628123456789';
    } else {
        $user = get_or_create_user($nomor_wa, $nama);
        // Update kelas jika berbeda
        $pdo = db();
        $pdo->prepare("UPDATE users SET nama=?, kelas=? WHERE id=?")
            ->execute([$nama, $kelas, $user['id']]);

        $_SESSION['user_id']  = $user['id'];
        $_SESSION['nama']     = $nama;
        $_SESSION['nomor_wa'] = $nomor_wa;
        $_SESSION['kelas']    = $kelas;

        log_aktivitas($user['id'], 'login', null, null, ['kelas' => $kelas]);
        header('Location: pretest.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AdaptLearn PRE — SMK Bansari</title>
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
    max-width: 420px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
  }
  .logo {
    text-align: center;
    margin-bottom: 28px;
  }
  .logo h1 {
    font-size: 24px;
    color: #0f3460;
    font-weight: 700;
  }
  .logo p {
    font-size: 13px;
    color: #666;
    margin-top: 4px;
  }
  .badge {
    display: inline-block;
    background: #e8f4fd;
    color: #0f3460;
    font-size: 11px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
    margin-top: 8px;
    letter-spacing: 0.5px;
  }
  .form-group {
    margin-bottom: 18px;
  }
  label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #444;
    margin-bottom: 6px;
  }
  input, select {
    width: 100%;
    padding: 12px 14px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-size: 14px;
    transition: border-color 0.2s;
    outline: none;
    color: #333;
  }
  input:focus, select:focus {
    border-color: #0f3460;
  }
  .hint {
    font-size: 11px;
    color: #999;
    margin-top: 4px;
  }
  .error {
    background: #fff0f0;
    border: 1px solid #ffcccc;
    color: #cc0000;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 18px;
  }
  .btn {
    width: 100%;
    padding: 14px;
    background: #0f3460;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
  }
  .btn:hover { background: #16213e; }
  .btn:active { transform: scale(0.98); }
  .info-box {
    background: #f0f7ff;
    border-left: 4px solid #0f3460;
    padding: 12px 14px;
    border-radius: 0 8px 8px 0;
    margin-bottom: 24px;
    font-size: 13px;
    color: #444;
    line-height: 1.6;
  }
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <h1>AdaptLearn PRE</h1>
    <p>Penerapan Rangkaian Elektronika</p>
    <span class="badge">SMK Negeri Bansari</span>
  </div>

  <div class="info-box">
    Sebelum memulai, isi data diri kamu. Pre-test ini terdiri dari <strong>20 soal</strong> (12 soal pengetahuan + 8 skenario belajar) untuk menentukan materi yang paling sesuai untukmu.
  </div>

  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label>Nama Lengkap</label>
      <input type="text" name="nama" placeholder="Contoh: Budi Santoso"
             value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required>
    </div>
    <div class="form-group">
      <label>Kelas</label>
      <select name="kelas" required>
        <option value="">— Pilih Kelas —</option>
        <option value="XI TEI" <?= ($_POST['kelas'] ?? '') === 'XI TEI' ? 'selected' : '' ?>>XI TEI</option>
        <option value="XII TEI" <?= ($_POST['kelas'] ?? '') === 'XII TEI' ? 'selected' : '' ?>>XII TEI</option>
      </select>
    </div>
    <div class="form-group">
      <label>Nomor WhatsApp</label>
      <input type="text" name="nomor_wa" placeholder="Contoh: 628123456789"
             value="<?= htmlspecialchars($_POST['nomor_wa'] ?? '') ?>" required>
      <p class="hint">Gunakan format internasional tanpa + (contoh: 628123456789)</p>
    </div>
    <button type="submit" class="btn">Mulai Pre-Test →</button>
  </form>
</div>
</body>
</html>
