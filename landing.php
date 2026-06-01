<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AdaptLearn PRE — SMK Negeri Bansari</title>
<style>
* { -webkit-text-size-adjust: 100%; text-size-adjust: 100%; }

* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { overflow: hidden; height: 100%; }
body {
    font-family: 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 12px;
}
.wrapper { width: 100%; max-width: 780px; }

/* HEADER */
.header { text-align: center; margin-bottom: 20px; color: #fff; }
.header h1 { font-size: 28px; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 6px; }
.header h1 span { color: #64b5f6; }
.header p { font-size: 13px; opacity: 0.75; margin-bottom: 10px; }
.badge { display: inline-block; background: rgba(255,255,255,0.15); color: #fff; font-size: 11px; font-weight: 600; padding: 4px 14px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.2); }

/* CARDS */
.cards { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
.card {
    background: #fff;
    border-radius: 16px;
    padding: 20px 24px;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    transition: transform 0.2s;
}
.card:hover { transform: translateY(-4px); }
.card-icon { font-size: 36px; margin-bottom: 10px; }
.card h2 { font-size: 17px; color: #1a1a2e; margin-bottom: 6px; }
.card p { font-size: 12px; color: #777; line-height: 1.5; margin-bottom: 14px; }
.card ul { text-align: left; font-size: 12px; color: #555; margin-bottom: 16px; padding-left: 0; list-style: none; }
.card ul li { padding: 4px 0; border-bottom: 1px solid #f0f0f0; }
.card ul li:last-child { border-bottom: none; }
.card ul li::before { content: '✓ '; color: #27ae60; font-weight: 700; }
.btn {
    display: block;
    padding: 11px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-siswa { background: #0f3460; color: #fff; }
.btn-siswa:hover { background: #16213e; }
.btn-guru { background: #27ae60; color: #fff; }
.btn-guru:hover { background: #219150; }

/* FOOTER */
.footer { text-align: center; color: rgba(255,255,255,0.4); font-size: 12px; }

@media (max-width: 600px) {
    .cards { grid-template-columns: 1fr; }
    .header h1 { font-size: 26px; }
}
</style>
</head>
<body>
<div class="wrapper">

    <div class="header">
        <h1>AdaptLearn <span>PRE</span></h1>
        <p>Platform E-Learning Adaptif — Penerapan Rangkaian Elektronika</p>
        <span class="badge">🏫 SMK Negeri Bansari</span>
    </div>

    <div class="cards">
        <!-- SISWA -->
        <div class="card">
            <div class="card-icon">🎓</div>
            <h2>Saya Siswa</h2>
            <p>Akses materi pembelajaran yang disesuaikan dengan profil dan kemampuan belajarmu.</p>
            <ul>
                <li>Pre-test klasifikasi profil belajar</li>
                <li>Materi adaptif per topik</li>
                <li>Evaluasi dan jobsheet</li>
                <li>Post-test dan N-Gain</li>
            </ul>
            <a href="login.php" class="btn btn-siswa">Masuk sebagai Siswa →</a>
        </div>

        <!-- GURU -->
        <div class="card">
            <div class="card-icon">👨‍🏫</div>
            <h2>Saya Guru</h2>
            <p>Pantau perkembangan siswa, kelola akun, dan atur pengaturan pembelajaran.</p>
            <ul>
                <li>Monitor progress siswa</li>
                <li>Kelola akun dan kelas</li>
                <li>Nilai jobsheet siswa</li>
                <li>Atur akses post-test</li>
            </ul>
            <a href="login_guru.php" class="btn btn-guru">Masuk sebagai Guru →</a>
        </div>
    </div>
    <div style="text-align:center;margin-bottom:20px">
        <a href="panduan.php" target="_blank" style="display:inline-block;padding:10px 24px;background:rgba(255,255,255,.15);color:#fff;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;border:1px solid rgba(255,255,255,.3)">📘 Panduan Penggunaan</a>
    </div>
    <div class="footer">
        AdaptLearn PRE · SMK Negeri Bansari · 2026
    </div>

</div>
</body>
</html>
