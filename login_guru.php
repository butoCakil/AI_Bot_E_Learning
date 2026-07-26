<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

if (!empty($_SESSION['role']) && $_SESSION['role'] === 'guru') {
    header('Location: dashboard_guru.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Email dan password wajib diisi.';
    } else {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'guru' LIMIT 1");
        $stmt->execute([$email]);
        $guru = $stmt->fetch();

        if ($guru && password_verify($password, $guru['password_hash'])) {
            $_SESSION['guru_id']   = $guru['id'];
            $_SESSION['guru_nama'] = $guru['nama'];
            $_SESSION['role']      = 'guru';
            header('Location: dashboard_guru.php');
            exit;
        } else {
            $error = 'Email atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0EA5A4">
<title>Login Guru — AdaptLearn PRE</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/lucide-static@1.25.0/font/lucide.css" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css">
<style>
.au-bg { min-height:100vh; background:linear-gradient(160deg,var(--teal) 0%,#0C807F 100%);
    display:flex; align-items:center; justify-content:center; padding:24px 16px; }
.au-card { background:#fff; border-radius:var(--r-lg); padding:32px; width:100%; max-width:400px;
    box-shadow:0 18px 50px rgba(15,23,42,.25); }
.au-head { text-align:center; margin-bottom:24px; }
.au-mark { width:52px; height:52px; border-radius:16px; background:var(--teal-muda); color:var(--teal);
    display:grid; place-items:center; font-size:25px; margin:0 auto 12px; }
.au-head h1 { font-size:19px; font-weight:800; letter-spacing:-.4px; }
.au-head p { font-size:12.5px; color:var(--abu-muda); margin-top:3px; }
.au-role { display:inline-flex; align-items:center; gap:6px; background:var(--teal-muda); color:var(--teal);
    font-size:11.5px; font-weight:700; padding:6px 14px; border-radius:99px; margin-top:12px; }
.fg { margin-bottom:16px; }
.fg label { display:block; font-size:12.5px; font-weight:700; color:var(--tinta); margin-bottom:6px; }
.fg input { width:100%; padding:12px 14px; border:2px solid var(--garis); border-radius:var(--r-sm);
    font-size:14px; font-family:inherit; outline:none; transition:.15s; color:var(--tinta); }
.fg input:focus { border-color:var(--teal); background:var(--teal-muda); }
.au-err { display:flex; align-items:center; gap:8px; background:var(--coral-muda); color:var(--coral);
    padding:11px 14px; border-radius:var(--r-sm); font-size:12.5px; font-weight:600; margin-bottom:16px; }
.au-hint { font-size:12px; color:var(--abu-muda); text-align:center; margin-top:16px; }
.au-hint a { color:var(--teal); text-decoration:none; font-weight:600; }
</style>
</head>
<body>
<div class="au-bg">
    <div class="au-card">
        <div class="au-head">
            <div class="au-mark"><i class="icon-presentation"></i></div>
            <h1>AdaptLearn PRE</h1>
            <p>Penerapan Rangkaian Elektronika</p>
            <span class="au-role"><i class="icon-shield-check"></i> Login Guru</span>
        </div>

        <?php if ($error): ?>
            <div class="au-err"><i class="icon-circle-alert"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="fg">
                <label>Email</label>
                <input type="email" name="email" placeholder="Email guru"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
            </div>
            <div class="fg">
                <label>Password</label>
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-3 btn-full" style="padding:14px">
                Masuk <i class="icon-arrow-right"></i>
            </button>
        </form>

        <p class="au-hint">
            <a href="landing.php"><i class="icon-arrow-left"></i> Kembali ke halaman utama</a>
        </p>
    </div>
</div>
</body>
</html>
