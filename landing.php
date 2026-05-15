<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AdaptLearn PRE — SMK Negeri Bansari</title>
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
.wrapper { width: 100%; max-width: 820px; }

/* HEADER */
.header { text-align: center; margin-bottom: 48px; color: #fff; }
.header h1 { font-size: 36px; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 8px; }
.header h1 span { color: #64b5f6; }
.header p { font-size: 16px; opacity: 0.75; margin-bottom: 16px; }
.badge { display: inline-block; background: rgba(255,255,255,0.15); color: #fff; font-size: 12px; font-weight: 600; padding: 5px 16px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.2); }

/* CARDS */
.cards { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 32px; }
.card {
    background: #fff;
    border-radius: 20px;
    padding: 36px 32px;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    transition: transform 0.2s;
}
.card:hover { transform: translateY(-4px); }
.card-icon { font-size: 48px; margin-bottom: 16px; }
.card h2 { font-size: 20px; color: #1a1a2e; margin-bottom: 8px; }
.card p { font-size: 13px; color: #777; line-height: 1.6; margin-bottom: 24px; }
.card ul { text-align: left; font-size: 13px; color: #555; margin-bottom: 28px; padding-left: 0; list-style: none; }
.card ul li { padding: 5px 0; border-bottom: 1px solid #f0f0f0; }
.card ul li:last-child { border-bottom: none; }
.card ul li::before { content: '✓ '; color: #27ae60; font-weight: 700; }
.btn {
    display: block;
    padding: 14px;
    border-radius: 12px;
    font-size: 15px;
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

    <div class="footer">
        AdaptLearn PRE · SMK Negeri Bansari · 2026
    </div>

</div>
</body>
</html>
