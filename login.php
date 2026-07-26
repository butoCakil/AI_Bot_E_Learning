<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'siswa') {
    header('Location: home.php');
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
            header('Location: home.php');
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
<meta name="theme-color" content="#2563EB">
<title>Login Siswa — AdaptLearn PRE</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/lucide-static@1.25.0/font/lucide.css" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css">
<style>
.au-bg { min-height:100vh; background:linear-gradient(160deg,var(--biru) 0%,var(--biru-tua) 100%);
    display:flex; align-items:center; justify-content:center; padding:24px 16px; }
.au-card { background:#fff; border-radius:var(--r-lg); padding:32px; width:100%; max-width:400px;
    box-shadow:0 18px 50px rgba(15,23,42,.25); }
.au-head { text-align:center; margin-bottom:24px; }
.au-mark { width:52px; height:52px; border-radius:16px; background:var(--biru-muda); color:var(--biru);
    display:grid; place-items:center; font-size:25px; margin:0 auto 12px; }
.au-head h1 { font-size:19px; font-weight:800; letter-spacing:-.4px; }
.au-head p { font-size:12.5px; color:var(--abu-muda); margin-top:3px; }
.au-role { display:inline-flex; align-items:center; gap:6px; background:var(--biru-muda); color:var(--biru-tua);
    font-size:11.5px; font-weight:700; padding:6px 14px; border-radius:99px; margin-top:12px; }
.fg { margin-bottom:16px; }
.fg label { display:block; font-size:12.5px; font-weight:700; color:var(--tinta); margin-bottom:6px; }
.fg input { width:100%; padding:12px 14px; border:2px solid var(--garis); border-radius:var(--r-sm);
    font-size:14px; font-family:inherit; outline:none; transition:.15s; color:var(--tinta); }
.fg input:focus { border-color:var(--biru); background:var(--biru-muda); }
.au-err { display:flex; align-items:center; gap:8px; background:var(--coral-muda); color:var(--coral);
    padding:11px 14px; border-radius:var(--r-sm); font-size:12.5px; font-weight:600; margin-bottom:16px; }
.au-hint { font-size:12px; color:var(--abu-muda); text-align:center; margin-top:16px; line-height:1.7; }
.au-hint a { color:var(--biru); text-decoration:none; font-weight:600; }
</style>
</head>
<body>
<div class="au-bg">
    <div class="au-card">
        <div class="au-head">
            <div class="au-mark"><i class="icon-cpu"></i></div>
            <h1>AdaptLearn PRE</h1>
            <p>Penerapan Rangkaian Elektronika</p>
            <span class="au-role"><i class="icon-graduation-cap"></i> Login Siswa</span>
        </div>

        <?php if ($error): ?>
            <div class="au-err"><i class="icon-circle-alert"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="fg">
                <label>NIS (Nomor Induk Siswa)</label>
                <input type="text" name="nis" placeholder="Masukkan NIS kamu"
                       value="<?= htmlspecialchars($_POST['nis'] ?? '') ?>" required autofocus>
            </div>
            <div class="fg">
                <label>Password</label>
                <input type="password" name="password" placeholder="Masukkan password" required>
            </div>
            <button type="submit" class="btn btn-1 btn-full" style="padding:14px">
                Masuk <i class="icon-arrow-right"></i>
            </button>
        </form>

        <p class="au-hint">
            Hubungi guru jika belum memiliki akun.<br>
            <a href="landing.php"><i class="icon-arrow-left"></i> Kembali ke halaman utama</a>
        </p>
    </div>
</div>
</body>
</html>
