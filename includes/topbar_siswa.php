<?php
// Komponen topbar siswa — include di setiap halaman siswa
// Variabel yang dibutuhkan: $_SESSION['nama'], $topbar_aktif (opsional)
// $topbar_aktif: 'beranda' | 'materi' | 'profil'
$topbar_aktif = $topbar_aktif ?? '';
?>
<div style="background:#0f3460;color:#fff;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 8px rgba(0,0,0,0.2)">
    <a href="home.php" style="color:#fff;text-decoration:none;font-weight:700;font-size:16px">
        AdaptLearn PRE
        <span style="font-weight:300;opacity:0.6;font-size:13px;margin-left:8px">Penerapan Rangkaian Elektronika</span>
    </a>
    <nav style="display:flex;align-items:center;gap:4px">
        <a href="home.php" style="color:<?= $topbar_aktif==='beranda'?'#fff':'rgba(255,255,255,0.7)' ?>;text-decoration:none;font-size:13px;font-weight:<?= $topbar_aktif==='beranda'?'700':'400' ?>;padding:6px 12px;border-radius:6px;background:<?= $topbar_aktif==='beranda'?'rgba(255,255,255,0.15)':'transparent' ?>">🏠 Beranda</a>
        <a href="materi.php" style="color:<?= $topbar_aktif==='materi'?'#fff':'rgba(255,255,255,0.7)' ?>;text-decoration:none;font-size:13px;font-weight:<?= $topbar_aktif==='materi'?'700':'400' ?>;padding:6px 12px;border-radius:6px;background:<?= $topbar_aktif==='materi'?'rgba(255,255,255,0.15)':'transparent' ?>">📚 Materi</a>
        <a href="profil.php" style="color:<?= $topbar_aktif==='profil'?'#fff':'rgba(255,255,255,0.7)' ?>;text-decoration:none;font-size:13px;font-weight:<?= $topbar_aktif==='profil'?'700':'400' ?>;padding:6px 12px;border-radius:6px;background:<?= $topbar_aktif==='profil'?'rgba(255,255,255,0.15)':'transparent' ?>">👤 <?= htmlspecialchars(explode(' ', $_SESSION['nama'])[0]) ?></a>
        <a href="logout.php" style="color:rgba(255,255,255,0.5);text-decoration:none;font-size:12px;padding:6px 12px">Keluar</a>
    </nav>
</div>
