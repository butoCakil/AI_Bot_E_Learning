<?php
/* ============================================================
   Komponen Topbar Siswa — AdaptLearn PRE
   ------------------------------------------------------------
   Include di setiap halaman siswa, TEPAT setelah <body>.
   Komponen ini sekaligus mencetak <head> (font, css, icon).

   Variabel opsional (set SEBELUM include):
     $topbar_aktif  : 'beranda' | 'materi' | 'evaluasi' | 'profil'
     $page_title    : judul tab browser
     $tanpa_nav     : true  -> sembunyikan menu (untuk pretest/posttest)

   Butuh: $_SESSION['nama']
   ============================================================ */

$topbar_aktif = $topbar_aktif ?? '';
$page_title   = $page_title   ?? 'AdaptLearn PRE';
$tanpa_nav    = $tanpa_nav    ?? false;

$nama_lengkap = $_SESSION['nama'] ?? 'Siswa';
$nama_depan   = explode(' ', trim($nama_lengkap))[0];

// Inisial untuk avatar (maks 2 huruf)
$pecah   = preg_split('/\s+/', trim($nama_lengkap));
$inisial = strtoupper(mb_substr($pecah[0], 0, 1));
if (count($pecah) > 1) {
    $inisial .= strtoupper(mb_substr(end($pecah), 0, 1));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#FFFFFF">
<title><?= htmlspecialchars($page_title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/lucide-static@1.25.0/font/lucide.css" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css?v=<?= @filemtime(__DIR__ . '/../assets/style.css') ?: '1' ?>">
</head>
<body>

<header class="topbar">
    <a href="home.php" class="brand">
        <span class="brand-mark"><i class="icon-cpu"></i></span>
        <span class="brand-txt">
            <b>AdaptLearn PRE</b>
            <span>Penerapan Rangkaian Elektronika</span>
        </span>
    </a>

    <?php if (!$tanpa_nav): ?>
    <nav class="nav-desk">
        <a href="home.php"     class="<?= $topbar_aktif === 'beranda'  ? 'on' : '' ?>"><i class="icon-house"></i> Beranda</a>
        <a href="materi.php"   class="<?= $topbar_aktif === 'materi'   ? 'on' : '' ?>"><i class="icon-book-open"></i> Materi</a>
        <a href="evaluasi.php" class="<?= $topbar_aktif === 'evaluasi' ? 'on' : '' ?>"><i class="icon-clipboard-list"></i> Evaluasi</a>
        <a href="profil.php"   class="<?= $topbar_aktif === 'profil'   ? 'on' : '' ?>"><i class="icon-user"></i> Profil</a>
        <a href="logout.php" title="Keluar"><i class="icon-log-out"></i></a>
    </nav>
    <a href="profil.php" class="ava" title="<?= htmlspecialchars($nama_lengkap) ?>"><?= htmlspecialchars($inisial) ?></a>
    <?php else: ?>
    <a href="logout.php" class="nav-desk" style="font-size:12px;font-weight:700;color:var(--abu);text-decoration:none">
        <i class="icon-log-out"></i> Keluar
    </a>
    <?php endif; ?>
</header>
