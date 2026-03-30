<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

// Jika sudah login, redirect
if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'siswa') {
    header('Location: cek_pretest.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nis      = trim($_POST['nis'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$nis || !$password) {
        $error = 'NIS dan password wajib diisi.';
    } else {
        $user = login_siswa($nis, $password);
        if ($user) {
            set_session_siswa($user);
            log_aktivitas($user['id'], 'login', null, null, ['nis' => $nis]);
            header('Location: cek_pretest.php');
            exit;
        } else {
            $error = 'NIS atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Siswa — AdaptLearn PRE</title>
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
    max-width: 400px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.logo { text-align: center; margin-bottom: 28px; }
.logo h1 { font-size: 24px; color: #0f3460; font-weight: 700; }
.logo p { font-size: 13px; color: #666; margin-top: 4px; }
.badge {
    display: inline-block;
    background: #e8f4fd;
    color: #0f3460;
    font-size: 11px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
    margin-top: 8px;
}
.form-group { margin-bottom: 18px; }
label { display: block; font-size: 13px; font-weight: 600; color: #444; margin-bottom: 6px; }
input {
    width: 100%;
    padding: 12px 14px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-size: 14px;
    transition: border-color 0.2s;
    outline: none;
    color: #333;
}
input:focus { border-color: #0f3460; }
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
    transition: background 0.2s;
}
.btn:hover { background: #16213e; }
.hint { font-size: 12px; color: #999; text-align: center; margin-top: 16px; }
</style>
</head>
<body>
<div class="card">
    <div class="logo">
        <h1>AdaptLearn PRE</h1>
        <p>Penerapan Rangkaian Elektronika</p>
        <span class="badge">SMK Negeri Bansari</span>
    </div>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>NIS (Nomor Induk Siswa)</label>
            <input type="text" name="nis" placeholder="Masukkan NIS kamu"
                   value="<?= htmlspecialchars($_POST['nis'] ?? '') ?>" required autofocus>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="Masukkan password" required>
        </div>
        <button type="submit" class="btn">Masuk →</button>
    </form>
    <p class="hint">Hubungi guru jika belum memiliki akun.</p>
</div>
</body>
</html>
