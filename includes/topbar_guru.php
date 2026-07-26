<?php
/* ============================================================
   Komponen Topbar Guru — AdaptLearn PRE
   ------------------------------------------------------------
   Include di setiap halaman guru, TEPAT setelah logika PHP.
   Mencetak <head> (font, css, icon) + topbar.

   Variabel opsional (set SEBELUM include):
     $guru_aktif  : 'dashboard' | 'topik' | 'konten'
     $page_title  : judul tab browser

   Butuh: $_SESSION['guru_nama']
   ============================================================ */

$guru_aktif = $guru_aktif ?? '';
$page_title = $page_title ?? 'AdaptLearn PRE — Guru';

$nama_guru = $_SESSION['guru_nama'] ?? 'Guru';
$pecah     = preg_split('/\s+/', trim($nama_guru));
$inisial   = strtoupper(mb_substr($pecah[0], 0, 1));
if (count($pecah) > 1) $inisial .= strtoupper(mb_substr(end($pecah), 0, 1));
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
<style>
/* Aksen guru = teal. Override sebagian token khusus area guru. */
.g-nav a.on { background: var(--teal-muda); color: #0C807F; }
.g-ava { background: var(--teal-muda); color: var(--teal); border-color: #99E6E5; }
.g-mark { background: var(--teal) !important; }
</style>
</head>
<body>

<header class="topbar">
    <a href="dashboard_guru.php" class="brand">
        <span class="brand-mark g-mark"><i class="icon-presentation"></i></span>
        <span class="brand-txt">
            <b>AdaptLearn PRE</b>
            <span>Panel Guru · SMK N Bansari</span>
        </span>
    </a>

    <nav class="nav-desk g-nav">
        <a href="dashboard_guru.php" class="<?= $guru_aktif === 'dashboard' ? 'on' : '' ?>"><i class="icon-layout-dashboard"></i> Dashboard</a>
        <a href="kelola_topik.php"   class="<?= $guru_aktif === 'topik'     ? 'on' : '' ?>"><i class="icon-folder-tree"></i> Topik</a>
        <a href="kelola_konten.php"  class="<?= $guru_aktif === 'konten'    ? 'on' : '' ?>"><i class="icon-file-text"></i> Konten</a>
        <a href="logout.php" title="Keluar"><i class="icon-log-out"></i></a>
    </nav>
    <span class="ava g-ava" title="<?= htmlspecialchars($nama_guru) ?>"><?= htmlspecialchars($inisial) ?></span>
</header>
