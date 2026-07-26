<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#2563EB">
<title>AdaptLearn PRE — SMK Negeri Bansari</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/lucide-static@1.25.0/font/lucide.css" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css">
<style>
.lg-bg {
    min-height: 100vh;
    background: linear-gradient(160deg, var(--biru) 0%, var(--biru-tua) 100%);
    display: flex; align-items: center; justify-content: center;
    padding: 24px 16px; position: relative; overflow: hidden;
}
.lg-bg::before, .lg-bg::after {
    content: ''; position: absolute; border-radius: 50%;
    background: rgba(255,255,255,.06);
}
.lg-bg::before { width: 340px; height: 340px; top: -120px; right: -80px; }
.lg-bg::after  { width: 260px; height: 260px; bottom: -100px; left: -60px; }
.lg-wrap { width: 100%; max-width: 760px; position: relative; z-index: 1; }
.lg-head { text-align: center; color: #fff; margin-bottom: 24px; }
.lg-mark {
    width: 56px; height: 56px; border-radius: 18px; background: rgba(255,255,255,.15);
    display: grid; place-items: center; font-size: 28px; margin: 0 auto 14px;
    border: 1px solid rgba(255,255,255,.2);
}
.lg-head h1 { font-size: 30px; font-weight: 800; letter-spacing: -.8px; }
.lg-head p { font-size: 13.5px; opacity: .8; margin: 6px 0 12px; }
.lg-chip {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(255,255,255,.15); color: #fff; font-size: 11.5px; font-weight: 700;
    padding: 6px 14px; border-radius: 99px; border: 1px solid rgba(255,255,255,.2);
}
.lg-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.lg-card {
    background: #fff; border-radius: var(--r-lg); padding: 24px; text-align: center;
    box-shadow: 0 18px 50px rgba(15,23,42,.22); transition: transform .2s;
    display: flex; flex-direction: column;
}
.lg-card:hover { transform: translateY(-4px); }
.lg-ic { width: 54px; height: 54px; border-radius: 16px; display: grid; place-items: center; font-size: 26px; margin: 0 auto 14px; }
.lg-ic.s { background: var(--biru-muda); color: var(--biru); }
.lg-ic.g { background: var(--teal-muda); color: var(--teal); }
.lg-card h2 { font-size: 17px; font-weight: 800; margin-bottom: 6px; }
.lg-card > p { font-size: 12.5px; color: var(--abu); line-height: 1.55; margin-bottom: 14px; }
.lg-list { list-style: none; text-align: left; margin-bottom: 18px; flex: 1; }
.lg-list li { font-size: 12.5px; color: var(--abu); padding: 6px 0; display: flex; align-items: center; gap: 8px; }
.lg-list li i { color: var(--teal); font-size: 15px; flex-shrink: 0; }
.lg-foot { text-align: center; color: rgba(255,255,255,.55); font-size: 12px; margin-top: 18px; }
.lg-foot a { color: rgba(255,255,255,.9); }
@media (max-width: 600px) { .lg-cards { grid-template-columns: 1fr; } .lg-head h1 { font-size: 26px; } }
</style>
</head>
<body>
<div class="lg-bg">
    <div class="lg-wrap">

        <div class="lg-head">
            <div class="lg-mark"><i class="icon-cpu"></i></div>
            <h1>AdaptLearn PRE</h1>
            <p>Platform E-Learning Adaptif — Penerapan Rangkaian Elektronika</p>
            <span class="lg-chip"><i class="icon-school"></i> SMK Negeri Bansari</span>
        </div>

        <div class="lg-cards">
            <div class="lg-card">
                <div class="lg-ic s"><i class="icon-graduation-cap"></i></div>
                <h2>Saya Siswa</h2>
                <p>Akses materi pembelajaran yang disesuaikan dengan profil dan kemampuan belajarmu.</p>
                <ul class="lg-list">
                    <li><i class="icon-check"></i> Pre-test klasifikasi profil belajar</li>
                    <li><i class="icon-check"></i> Materi adaptif per topik</li>
                    <li><i class="icon-check"></i> Evaluasi dan jobsheet</li>
                    <li><i class="icon-check"></i> Post-test dan N-Gain</li>
                </ul>
                <a href="login.php" class="btn btn-1 btn-full">Masuk sebagai Siswa <i class="icon-arrow-right"></i></a>
            </div>

            <div class="lg-card">
                <div class="lg-ic g"><i class="icon-presentation"></i></div>
                <h2>Saya Guru</h2>
                <p>Pantau perkembangan siswa, kelola akun, dan atur pengaturan pembelajaran.</p>
                <ul class="lg-list">
                    <li><i class="icon-check"></i> Monitor progress siswa</li>
                    <li><i class="icon-check"></i> Kelola akun dan kelas</li>
                    <li><i class="icon-check"></i> Nilai jobsheet siswa</li>
                    <li><i class="icon-check"></i> Atur akses post-test</li>
                </ul>
                <a href="login_guru.php" class="btn btn-3 btn-full">Masuk sebagai Guru <i class="icon-arrow-right"></i></a>
            </div>
        </div>

        <div style="text-align:center">
            <a href="panduan.php" target="_blank" class="lg-chip" style="text-decoration:none;cursor:pointer">
                <i class="icon-book-open"></i> Panduan Penggunaan
            </a>
        </div>

        <div class="lg-foot">
            AdaptLearn PRE · SMK Negeri Bansari · 2026
        </div>

    </div>
</div>
</body>
</html>
