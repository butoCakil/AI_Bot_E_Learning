<?php
/* ============================================================
   Komponen Bottom Nav Siswa — AdaptLearn PRE
   ------------------------------------------------------------
   Include TEPAT sebelum </body> di setiap halaman siswa.
   Hanya tampil di layar <= 768px (diatur via style.css).

   Variabel opsional (biasanya sudah di-set oleh topbar_siswa.php):
     $topbar_aktif : 'beranda' | 'materi' | 'evaluasi' | 'profil'
     $tanpa_nav    : true -> tidak tampilkan bottom nav
   ============================================================ */

$topbar_aktif = $topbar_aktif ?? '';
$tanpa_nav    = $tanpa_nav    ?? false;

if (!$tanpa_nav):
?>
<nav class="bnav">
    <a href="home.php"     class="<?= $topbar_aktif === 'beranda'  ? 'on' : '' ?>"><i class="icon-house"></i>Beranda</a>
    <a href="materi.php"   class="<?= $topbar_aktif === 'materi'   ? 'on' : '' ?>"><i class="icon-book-open"></i>Materi</a>
    <a href="evaluasi.php" class="<?= $topbar_aktif === 'evaluasi' ? 'on' : '' ?>"><i class="icon-clipboard-list"></i>Evaluasi</a>
    <a href="profil.php"   class="<?= $topbar_aktif === 'profil'   ? 'on' : '' ?>"><i class="icon-user"></i>Profil</a>
</nav>
<?php endif; ?>
