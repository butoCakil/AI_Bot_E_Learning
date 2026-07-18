<?php
/* ============================================================
   Post-Test Locked — AdaptLearn PRE
   ------------------------------------------------------------
   Di-include oleh posttest.php SEBELUM topbar dicetak, lalu exit.
   Karena itu file ini mencetak halaman lengkap sendiri.

   Variabel dari posttest.php:
     $sudah_selesai (bool), $akses['alasan'] (string)
   ============================================================ */
$sudah_selesai = $sudah_selesai ?? false;
$alasan        = $akses['alasan'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#FFFFFF">
<title>Post-Test — AdaptLearn PRE</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/lucide-static@1.25.0/font/lucide.css" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css?v=<?= @filemtime(__DIR__ . '/../assets/style.css') ?: '1' ?>">
</head>
<body>

<div class="wrap" style="max-width:460px;min-height:100vh;justify-content:center">
    <div class="card tengah">
        <div style="width:64px;height:64px;border-radius:50%;margin:4px auto 16px;display:grid;place-items:center;font-size:28px;
                    background:<?= $sudah_selesai ? 'var(--teal-muda)' : 'var(--amber-muda)' ?>;
                    color:<?= $sudah_selesai ? 'var(--teal)' : 'var(--amber)' ?>">
            <i class="<?= $sudah_selesai ? 'icon-circle-check' : 'icon-lock' ?>"></i>
        </div>

        <h1 style="font-size:19px;font-weight:800;letter-spacing:-.4px;margin-bottom:10px">
            <?= $sudah_selesai ? 'Post-Test sudah selesai' : 'Post-Test belum bisa diakses' ?>
        </h1>

        <p style="font-size:13px;color:var(--abu);line-height:1.65;background:var(--kanvas);
                  border-radius:var(--r-md);padding:14px 16px;margin-bottom:20px">
            <?= htmlspecialchars($alasan) ?>
        </p>

        <?php if ($sudah_selesai): ?>
            <a href="hasil_posttest.php" class="btn btn-3 btn-full"><i class="icon-eye"></i> Lihat hasil</a>
            <a href="home.php" class="btn btn-2 btn-full"><i class="icon-house"></i> Ke beranda</a>
        <?php else: ?>
            <a href="materi.php" class="btn btn-1 btn-full"><i class="icon-book-open"></i> Kembali ke materi</a>
            <a href="home.php" class="btn btn-2 btn-full"><i class="icon-house"></i> Ke beranda</a>
        <?php endif; ?>
    </div>
</div>

</body>
</html>