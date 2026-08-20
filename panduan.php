<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
$judul = 'Panduan Penggunaan AdaptLearn PRE';
// Nomor bot dibaca dari pengaturan, bukan ditulis manual — kalau nomor berubah,
// seluruh tautan di halaman ini ikut benar tanpa disunting.
$botNomor = preg_replace('/\D/', '', get_pengaturan('wa_bot_nomor', ''));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panduan AdaptLearn PRE — SMK Negeri Bansari</title>
<style>
* { -webkit-text-size-adjust: 100%; text-size-adjust: 100%; }

:root{--navy:#0f3460;--green:#27ae60;--bg:#f4f6fb;--card:#fff;--text:#2c3e50;--muted:#7f8c8d;--border:#e0e6ed;--shadow:0 2px 12px rgba(15,52,96,.09);}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',sans-serif;background:var(--bg);color:var(--text);font-size:15px;line-height:1.7;}
.topbar{background:var(--navy);color:#fff;padding:14px 32px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 8px rgba(0,0,0,.2);}
.topbar-title{font-size:17px;font-weight:700;}
.topbar-sub{font-size:12px;opacity:.75;margin-top:2px;}
.btn-back{padding:8px 18px;background:rgba(255,255,255,.15);color:#fff;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;}
.btn-back:hover{background:rgba(255,255,255,.25);}
.hero{background:linear-gradient(135deg,var(--navy) 0%,#1a4a8a 100%);color:#fff;padding:40px 32px;text-align:center;}
.hero h1{font-size:26px;font-weight:800;margin-bottom:8px;}
.hero p{font-size:14px;opacity:.85;max-width:600px;margin:0 auto;}
.hero-badges{display:flex;gap:10px;justify-content:center;margin-top:16px;flex-wrap:wrap;}
.hero-badge{padding:5px 14px;background:rgba(255,255,255,.15);border-radius:20px;font-size:12px;font-weight:600;}
.layout{display:flex;max-width:1100px;margin:0 auto;padding:32px 20px;gap:28px;}
.sidebar{width:240px;flex-shrink:0;position:sticky;top:72px;height:fit-content;}
.sidebar-card{background:var(--card);border-radius:12px;box-shadow:var(--shadow);padding:20px 0;border:1px solid var(--border);}
.sidebar-label{font-size:10px;font-weight:700;letter-spacing:1px;color:var(--muted);text-transform:uppercase;padding:0 20px 8px;}
.sidebar-link{display:block;padding:9px 20px;color:var(--text);text-decoration:none;font-size:13px;border-left:3px solid transparent;transition:all .2s;}
.sidebar-link:hover,.sidebar-link.active{background:#f0f4ff;color:var(--navy);border-left-color:var(--navy);font-weight:600;}
.sidebar-divider{height:1px;background:var(--border);margin:8px 0;}
.content{flex:1;min-width:0;}
.section{background:var(--card);border-radius:14px;box-shadow:var(--shadow);border:1px solid var(--border);margin-bottom:24px;overflow:hidden;}
.section-header{padding:20px 28px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;}
.section-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.section-icon.blue{background:#e8f0fe;}.section-icon.green{background:#e8f8f0;}.section-icon.orange{background:#fff3e0;}.section-icon.red{background:#fdecea;}
.section-title{font-size:17px;font-weight:700;color:var(--navy);}
.section-subtitle{font-size:12px;color:var(--muted);margin-top:2px;}
.section-body{padding:24px 28px;}
.steps{display:flex;flex-direction:column;gap:16px;}
.step{display:flex;gap:16px;align-items:flex-start;padding:16px;background:#f9fbfd;border-radius:10px;border:1px solid var(--border);}
.step-num{width:32px;height:32px;border-radius:50%;background:var(--navy);color:#fff;font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.step-num.green{background:var(--green);}
.step-content h4{font-size:14px;font-weight:600;color:var(--navy);margin-bottom:4px;}
.step-content p{font-size:13px;color:var(--muted);}
.info-box{padding:14px 18px;border-radius:10px;margin-bottom:16px;font-size:13px;display:flex;gap:10px;align-items:flex-start;}
.info-box.blue{background:#e8f0fe;border:1px solid #b8daff;color:#0f3460;}
.info-box.green{background:#e8f8f0;border:1px solid #c3e6cb;color:#155724;}
.info-box.yellow{background:#fff8e1;border:1px solid #ffe082;color:#7d5a00;}
.info-box.red{background:#fdecea;border:1px solid #f5c6cb;color:#721c24;}
.tbl{width:100%;border-collapse:collapse;font-size:13px;margin-bottom:16px;}
.tbl th{background:var(--navy);color:#fff;padding:10px 14px;text-align:left;font-weight:600;}
.tbl td{padding:10px 14px;border-bottom:1px solid var(--border);vertical-align:top;}
.tbl tr:last-child td{border-bottom:none;}
.tbl tr:nth-child(even) td{background:#f9fbfd;}
.badge{display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;}
.badge-blue{background:#e8f0fe;color:var(--navy);}.badge-green{background:#e8f8f0;color:#155724;}.badge-orange{background:#fff3e0;color:#7d3c00;}
.wa-msg{background:#e2ffc7;border-radius:10px 10px 10px 2px;padding:12px 16px;font-size:13px;margin:8px 0;display:inline-block;max-width:80%;box-shadow:0 1px 3px rgba(0,0,0,.1);}
.wa-reply{background:#fff;border-radius:10px 10px 2px 10px;padding:12px 16px;font-size:13px;margin:8px 0 8px auto;display:block;max-width:85%;box-shadow:0 1px 3px rgba(0,0,0,.1);border:1px solid var(--border);}
.wa-label{font-size:10px;color:var(--muted);margin-bottom:4px;font-weight:600;}
@media(max-width:768px){.layout{flex-direction:column;padding:16px;}.sidebar{width:100%;position:static;}.section-body{padding:16px;}.topbar{padding:12px 16px;}}
</style>
</head>
<body>
<div class="topbar">
  <div><div class="topbar-title">📘 Panduan Penggunaan AdaptLearn PRE</div><div class="topbar-sub">SMK Negeri Bansari — Teknik Elektronika</div></div>
  <a href="landing.php" class="btn-back">← Kembali</a>
</div>
<div class="hero">
  <h1>Panduan Lengkap AdaptLearn PRE</h1>
  <p>Sistem e-learning adaptif berbasis kecerdasan buatan untuk mata pelajaran Penerapan Rangkaian Elektronika</p>
  <div class="hero-badges"><span class="hero-badge">👨‍🎓 Panduan Siswa</span><span class="hero-badge">👨‍🏫 Panduan Guru</span><span class="hero-badge">💬 Panduan WA Bot</span></div>
</div>
<div class="layout">
<div class="sidebar">
  <div class="sidebar-card">
    <div class="sidebar-label">Panduan Siswa</div>
    <a href="#login-siswa" class="sidebar-link">🔐 Login Siswa</a>
    <a href="#pretest" class="sidebar-link">📝 Pre-Test</a>
    <a href="#materi" class="sidebar-link">📚 Materi Adaptif</a>
    <a href="#evaluasi" class="sidebar-link">✅ Evaluasi</a>
    <a href="#jobsheet" class="sidebar-link">📎 Upload Jobsheet</a>
    <a href="#posttest" class="sidebar-link">🎯 Post-Test</a>
    <a href="#profil" class="sidebar-link">👤 Profil & Progress</a>
    <div class="sidebar-divider"></div>
    <div class="sidebar-label">Panduan Guru</div>
    <a href="#login-guru" class="sidebar-link">🔑 Login Guru</a>
    <a href="#kelola-siswa" class="sidebar-link">👥 Kelola Siswa</a>
    <a href="#kelola-konten" class="sidebar-link">⚙️ Kelola Konten</a>
    <a href="#penilaian" class="sidebar-link">📊 Penilaian</a>
    <a href="#pengaturan" class="sidebar-link">🎛️ Pengaturan</a>
    <div class="sidebar-divider"></div>
    <div class="sidebar-label">Panduan WA Bot</div>
    <a href="#wa-registrasi" class="sidebar-link">📱 Registrasi WA</a>
    <a href="#wa-menu" class="sidebar-link">📋 Menu WA Bot</a>
    <a href="#wa-ai" class="sidebar-link">🤖 Tanya AI</a>
  </div>
</div>
<div class="content">

<!-- SISWA: LOGIN -->
<div class="section" id="login-siswa">
  <div class="section-header"><div class="section-icon blue">🔐</div><div><div class="section-title">1.1 Login Siswa</div><div class="section-subtitle">Cara masuk ke platform AdaptLearn PRE</div></div></div>
  <div class="section-body">
    <div class="info-box blue"><span>ℹ️</span><span>Akun siswa dibuat oleh guru. Pastikan kamu sudah mendapatkan NIS dan password dari gurumu sebelum login.</span></div>
    <div class="steps">
      <div class="step"><div class="step-num">1</div><div class="step-content"><h4>Buka halaman utama</h4><p>Akses <strong>http://103.67.78.4</strong> dari browser. Klik tombol <strong>"Masuk sebagai Siswa"</strong>.</p></div></div>
      <div class="step"><div class="step-num">2</div><div class="step-content"><h4>Masukkan NIS dan Password</h4><p>Isi kolom NIS dan password yang diberikan guru. Klik <strong>"Masuk"</strong>.</p></div></div>
      <div class="step"><div class="step-num">3</div><div class="step-content"><h4>Format password awal</h4><p>Password awal: <strong>NIS + 4 huruf pertama nama</strong> (huruf kecil). Contoh: NIS <code>3445</code>, nama <code>Ahmad Fauzi</code> → password: <code>3445ahma</code></p></div></div>
    </div>
  </div>
</div>

<!-- SISWA: PRETEST -->
<div class="section" id="pretest">
  <div class="section-header"><div class="section-icon orange">📝</div><div><div class="section-title">1.2 Pre-Test</div><div class="section-subtitle">Tes awal untuk menentukan profil belajar dan level kemampuan</div></div></div>
  <div class="section-body">
    <div class="info-box yellow"><span>⚠️</span><span>Pre-test <strong>wajib dikerjakan</strong> sebelum bisa mengakses materi. Hanya perlu dikerjakan satu kali.</span></div>
    <p style="margin-bottom:16px">Pre-test terdiri dari <strong>20 soal</strong>:</p>
    <table class="tbl">
      <tr><th>Bagian</th><th>Jumlah Soal</th><th>Keterangan</th></tr>
      <tr><td><span class="badge badge-blue">Bagian A</span></td><td>12 soal</td><td>Soal pengetahuan tentang Dioda, Transistor, dan Catu Daya</td></tr>
      <tr><td><span class="badge badge-orange">Bagian B</span></td><td>8 soal</td><td>Skenario situasional untuk menentukan profil belajar</td></tr>
    </table>
    <div class="steps">
      <div class="step"><div class="step-num">1</div><div class="step-content"><h4>Mulai pre-test</h4><p>Setelah login, klik tombol <strong>"Mulai Pre-Test"</strong> di halaman beranda.</p></div></div>
      <div class="step"><div class="step-num">2</div><div class="step-content"><h4>Jawab soal satu per satu</h4><p>Setiap soal ditampilkan satu per satu. Pilih jawaban yang paling sesuai, lalu klik <strong>"Lanjut"</strong>.</p></div></div>
      <div class="step"><div class="step-num">3</div><div class="step-content"><h4>Lihat hasil klasifikasi</h4><p>Setelah selesai, sistem AI mengklasifikasikan profil belajar dan level kemampuanmu.</p></div></div>
    </div>
    <div class="info-box green" style="margin-top:16px"><span>✅</span><div><strong>Hasil klasifikasi:</strong><br>Profil: <em>Guided-Step Learner</em>, <em>Conceptual Learner</em>, atau <em>Practice-Oriented Learner</em><br>Level: <em>Pemula</em>, <em>Menengah</em>, atau <em>Mahir</em></div></div>
  </div>
</div>

<!-- SISWA: MATERI -->
<div class="section" id="materi">
  <div class="section-header"><div class="section-icon blue">📚</div><div><div class="section-title">1.3 Materi Adaptif</div><div class="section-subtitle">Materi yang disesuaikan dengan profil dan level kemampuanmu</div></div></div>
  <div class="section-body">
    <table class="tbl">
      <tr><th>Topik</th><th>Keterangan</th></tr>
      <tr><td>Dioda</td><td>Pengertian, prinsip kerja, jenis-jenis, rangkaian penyearah</td></tr>
      <tr><td>Pengertian Transistor</td><td>Struktur BJT, daerah kerja, rangkaian dasar</td></tr>
      <tr><td>Pengukuran Transistor</td><td>Prosedur pengukuran, analisis karakteristik</td></tr>
      <tr><td>Catu Daya</td><td>Blok diagram, IC regulator, perancangan catu daya</td></tr>
    </table>
    <div class="steps">
      <div class="step"><div class="step-num">1</div><div class="step-content"><h4>Buka halaman materi</h4><p>Klik <strong>"Mulai Belajar"</strong> di beranda. Materi terbuka sesuai urutan profil belajarmu.</p></div></div>
      <div class="step"><div class="step-num">2</div><div class="step-content"><h4>Baca materi dengan seksama</h4><p>Setiap konten memiliki <strong>timer 35 detik</strong> — tombol "Lanjut" aktif setelah 35 detik. Tanda ✓ di sidebar menunjukkan materi yang sudah selesai.</p></div></div>
      <div class="step"><div class="step-num">3</div><div class="step-content"><h4>Navigasi materi</h4><p>Gunakan tombol <strong>"← Sebelumnya"</strong> dan <strong>"Selanjutnya →"</strong>. Sidebar kiri menampilkan semua materi per topik.</p></div></div>
    </div>
  </div>
</div>

<!-- SISWA: EVALUASI -->
<div class="section" id="evaluasi">
  <div class="section-header"><div class="section-icon green">✅</div><div><div class="section-title">1.4 Evaluasi Materi</div><div class="section-subtitle">Uji pemahaman setiap topik</div></div></div>
  <div class="section-body">
    <div class="steps">
      <div class="step"><div class="step-num">1</div><div class="step-content"><h4>Kerjakan soal evaluasi</h4><p>Setelah membaca materi satu topik, kerjakan soal evaluasi yang tersedia.</p></div></div>
      <div class="step"><div class="step-num">2</div><div class="step-content"><h4>Lihat hasil dan pembahasan</h4><p>Setelah submit, kamu bisa melihat <strong>jawaban benar/salah per soal</strong> beserta pembahasan.</p></div></div>
    </div>
  </div>
</div>

<!-- SISWA: JOBSHEET -->
<div class="section" id="jobsheet">
  <div class="section-header"><div class="section-icon orange">📎</div><div><div class="section-title">1.5 Upload Jobsheet</div><div class="section-subtitle">Kumpulkan hasil praktik dalam bentuk file</div></div></div>
  <div class="section-body">
    <div class="info-box yellow"><span>📌</span><span>Format yang diterima: PDF, DOC, DOCX, JPG, PNG (maks. 10MB).</span></div>
    <div class="steps">
      <div class="step"><div class="step-num">1</div><div class="step-content"><h4>Buka konten jobsheet</h4><p>Navigasi ke konten bertipe <strong>Jobsheet</strong> di sidebar materi.</p></div></div>
      <div class="step"><div class="step-num">2</div><div class="step-content"><h4>Upload file</h4><p>Klik <strong>"Pilih File"</strong>, pilih file hasil jobsheetmu, lalu klik <strong>"Upload"</strong>.</p></div></div>
      <div class="step"><div class="step-num">3</div><div class="step-content"><h4>Tunggu penilaian</h4><p>Guru akan memberikan nilai. Nilai akan muncul di halaman profilmu.</p></div></div>
    </div>
  </div>
</div>

<!-- SISWA: POSTTEST -->
<div class="section" id="posttest">
  <div class="section-header"><div class="section-icon red">🎯</div><div><div class="section-title">1.6 Post-Test</div><div class="section-subtitle">Tes akhir untuk mengukur peningkatan hasil belajar (N-Gain)</div></div></div>
  <div class="section-body">
    <div class="info-box red"><span>⚠️</span><span>Post-test hanya bisa diakses jika guru sudah mengaktifkan dan progressmu sudah mencapai batas minimum.</span></div>
    <div class="steps">
      <div class="step"><div class="step-num">1</div><div class="step-content"><h4>Cek ketersediaan post-test</h4><p>Buka halaman <strong>Profil</strong>. Jika aktif, tombol <strong>"Mulai Post-Test"</strong> akan muncul.</p></div></div>
      <div class="step"><div class="step-num">2</div><div class="step-content"><h4>Kerjakan 12 soal pengetahuan</h4><p>Kerjakan dengan serius — hasilnya digunakan untuk menghitung N-Gain.</p></div></div>
      <div class="step"><div class="step-num">3</div><div class="step-content"><h4>Lihat hasil N-Gain</h4><p>Sistem menghitung N-Gain berdasarkan skor pre-test dan post-test. Kategori: <em>Rendah (&lt;0.3)</em>, <em>Sedang (0.3–0.7)</em>, <em>Tinggi (&gt;0.7)</em>.</p></div></div>
    </div>
  </div>
</div>

<!-- SISWA: PROFIL -->
<div class="section" id="profil">
  <div class="section-header"><div class="section-icon blue">👤</div><div><div class="section-title">1.7 Profil & Progress</div><div class="section-subtitle">Pantau perkembangan belajarmu</div></div></div>
  <div class="section-body">
    <table class="tbl">
      <tr><th>Informasi</th><th>Keterangan</th></tr>
      <tr><td>Profil Belajar</td><td>Hasil klasifikasi AI dari pre-test</td></tr>
      <tr><td>Level Kemampuan</td><td>Pemula / Menengah / Mahir</td></tr>
      <tr><td>Skor Pre-Test</td><td>Nilai dari 12 soal pengetahuan awal</td></tr>
      <tr><td>Progress per Topik</td><td>Persentase materi yang sudah diselesaikan</td></tr>
      <tr><td>Skor Post-Test</td><td>Nilai akhir setelah pembelajaran</td></tr>
      <tr><td>N-Gain</td><td>Indeks peningkatan hasil belajar (Hake, 1999)</td></tr>
    </table>
  </div>
</div>

<!-- GURU: LOGIN -->
<div class="section" id="login-guru">
  <div class="section-header"><div class="section-icon green">🔑</div><div><div class="section-title">2.1 Login Guru</div><div class="section-subtitle">Cara mengakses dashboard guru</div></div></div>
  <div class="section-body">
    <div class="steps">
      <div class="step"><div class="step-num green">1</div><div class="step-content"><h4>Buka halaman utama</h4><p>Akses <strong>http://103.67.78.4</strong> lalu klik <strong>"Masuk sebagai Guru"</strong>.</p></div></div>
      <div class="step"><div class="step-num green">2</div><div class="step-content"><h4>Masukkan email dan password</h4><p>Isi email dan password akun guru.</p></div></div>
      <div class="step"><div class="step-num green">3</div><div class="step-content"><h4>Dashboard guru</h4><p>Dashboard terbuka dengan <strong>7 tab</strong>: Ringkasan, Siswa, Penilaian Jobsheet, Pengaturan Materi, Post-Test, Aktivitas, dan WA Bot & AI.</p></div></div>
    </div>
  </div>
</div>

<!-- GURU: KELOLA SISWA -->
<div class="section" id="kelola-siswa">
  <div class="section-header"><div class="section-icon green">👥</div><div><div class="section-title">2.2 Kelola Akun Siswa</div><div class="section-subtitle">Tambah, import, reset password, dan hapus akun siswa</div></div></div>
  <div class="section-body">
    <h4 style="margin-bottom:10px;color:var(--navy)">Import Siswa dari Excel</h4>
    <div class="steps" style="margin-bottom:20px">
      <div class="step"><div class="step-num green">1</div><div class="step-content"><h4>Download template Excel</h4><p>Klik <strong>"⬇ Download Template Excel"</strong>. Isi kolom NIS, Nama, Kelas, dan Nomor WA.</p></div></div>
      <div class="step"><div class="step-num green">2</div><div class="step-content"><h4>Upload file Excel</h4><p>Klik <strong>"Pilih File"</strong> → pilih file → klik <strong>"Upload & Import"</strong>. Progress import ditampilkan real-time.</p></div></div>
      <div class="step"><div class="step-num green">3</div><div class="step-content"><h4>Cek hasil import</h4><p>Sistem menampilkan jumlah berhasil dan dilewati (duplikat NIS).</p></div></div>
    </div>
    <div class="info-box yellow"><span>💡</span><div><strong>Password otomatis:</strong> NIS + 4 huruf pertama nama (huruf kecil).<br>Contoh: NIS <code>3445</code>, nama <code>Ahmad Fauzi</code> → <code>3445ahma</code></div></div>
  </div>
</div>

<!-- GURU: KELOLA KONTEN -->
<div class="section" id="kelola-konten">
  <div class="section-header"><div class="section-icon green">⚙️</div><div><div class="section-title">2.3 Kelola Konten Materi</div><div class="section-subtitle">Tambah, edit, dan hapus konten materi</div></div></div>
  <div class="section-body">
    <div class="info-box blue"><span>ℹ️</span><span>Akses dari Tab <strong>"Pengaturan Materi"</strong> → klik <strong>"Kelola Konten"</strong>.</span></div>
    <table class="tbl" style="margin-top:16px">
      <tr><th>Tipe Konten</th><th>Keterangan</th></tr>
      <tr><td><span class="badge badge-blue">Teori</span></td><td>Penjelasan konsep dan teori dasar</td></tr>
      <tr><td><span class="badge badge-green">Langkah</span></td><td>Langkah kerja praktikum</td></tr>
      <tr><td><span class="badge badge-orange">Jobsheet</span></td><td>Lembar kerja siswa (bisa upload PDF)</td></tr>
      <tr><td><span class="badge" style="background:#fdecea;color:#721c24">Evaluasi</span></td><td>Soal evaluasi per topik</td></tr>
      <tr><td><span class="badge" style="background:#f3e5f5;color:#4a148c">Tantangan</span></td><td>Soal pengayaan tambahan</td></tr>
    </table>
  </div>
</div>

<!-- GURU: PENILAIAN -->
<div class="section" id="penilaian">
  <div class="section-header"><div class="section-icon green">📊</div><div><div class="section-title">2.4 Penilaian Jobsheet</div><div class="section-subtitle">Nilai hasil upload jobsheet siswa</div></div></div>
  <div class="section-body">
    <div class="steps">
      <div class="step"><div class="step-num green">1</div><div class="step-content"><h4>Buka tab "Penilaian Jobsheet"</h4><p>Semua submission ditampilkan diurutkan dari terbaru.</p></div></div>
      <div class="step"><div class="step-num green">2</div><div class="step-content"><h4>Unduh dan review file</h4><p>Klik nama file untuk mengunduh hasil kerja siswa.</p></div></div>
      <div class="step"><div class="step-num green">3</div><div class="step-content"><h4>Berikan nilai</h4><p>Isi nilai (0–100) lalu klik <strong>"Simpan Nilai"</strong>.</p></div></div>
    </div>
  </div>
</div>

<!-- GURU: PENGATURAN -->
<div class="section" id="pengaturan">
  <div class="section-header"><div class="section-icon green">🎛️</div><div><div class="section-title">2.5 Pengaturan Sistem</div><div class="section-subtitle">Atur post-test, WA Bot, dan model AI</div></div></div>
  <div class="section-body">
    <h4 style="margin-bottom:10px;color:var(--navy)">Pengaturan Post-Test (Tab "Post-Test")</h4>
    <table class="tbl" style="margin-bottom:20px">
      <tr><th>Pengaturan</th><th>Keterangan</th></tr>
      <tr><td>Status Post-Test</td><td>Aktifkan/nonaktifkan akses post-test untuk semua siswa</td></tr>
      <tr><td>Tanggal Mulai</td><td>Tanggal dimulainya periode pembelajaran</td></tr>
      <tr><td>Durasi (hari)</td><td>Berapa hari siswa boleh mengakses post-test</td></tr>
      <tr><td>Min. Progress (%)</td><td>Persentase materi minimum sebelum post-test</td></tr>
    </table>
    <h4 style="margin-bottom:10px;color:var(--navy)">Pengaturan WA Bot & AI (Tab "WA Bot & AI")</h4>
    <table class="tbl">
      <tr><th>Pengaturan</th><th>Keterangan</th></tr>
      <tr><td>Gateway Aktif</td><td>Pilih antara <strong>Fonnte</strong> atau <strong>Whacenter</strong></td></tr>
      <tr><td>Nomor Bot</td><td>Nomor WA yang digunakan sebagai bot (format: 628xxx)</td></tr>
      <tr><td>Model AI</td><td>Pilih model AI untuk menjawab pertanyaan siswa</td></tr>
      <tr><td>System Prompt</td><td>Instruksi khusus untuk mengatur perilaku AI</td></tr>
    </table>
  </div>
</div>

<!-- WA BOT: REGISTRASI -->
<div class="section" id="wa-registrasi">
  <div class="section-header"><div class="section-icon blue">📱</div><div><div class="section-title">3.1 Registrasi WA Bot</div><div class="section-subtitle">Hubungkan nomor WA-mu ke akun AdaptLearn PRE</div></div></div>
  <div class="section-body">
    <?php if ($botNomor): ?>
    <div style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:10px;padding:16px;margin-bottom:16px">
      <div style="font-weight:700;margin-bottom:6px">Langkah 1 — buka chat bot</div>
      <div style="font-size:20px;font-weight:800;margin-bottom:10px"><?= htmlspecialchars($botNomor) ?></div>
      <a href="https://wa.me/<?= $botNomor ?>?text=halo" target="_blank" rel="noopener"
        style="display:inline-block;padding:10px 20px;background:#25D366;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;font-size:14px">
        💬 Buka chat &amp; kirim "halo"
      </a>
      <div style="font-size:13px;color:#555;margin-top:10px">
        Kirim pesan ini dulu, baru lanjut ke langkah berikutnya.
      </div>
    </div>
    <?php endif; ?>
    <div class="info-box yellow"><span>📌</span><span>Registrasi hanya diperlukan jika nomor WA-mu belum terdaftar di sistem.</span></div>
    <p style="margin-bottom:16px">Kirim pesan ke nomor bot dengan format:</p>
    <div style="background:#f9fbfd;border-radius:10px;padding:16px;border:1px solid var(--border)">
      <div class="wa-label">PESAN YANG DIKIRIM:</div>
      <div class="wa-msg">daftar 3445 3445ahma</div>
      <div style="font-size:12px;color:var(--muted);margin-top:4px">Format: <code>daftar [NIS] [PASSWORD]</code></div>
      <div class="wa-label" style="margin-top:12px">BALASAN BOT:</div>
      <div class="wa-reply">✅ Berhasil! Halo <strong>Ahmad Fauzi</strong>! 👋<br>Nomor WA kamu sudah terdaftar.<br><br>Ketik <strong>menu</strong> untuk mulai belajar!</div>
    </div>
  </div>
</div>
 
<!-- WA BOT: MENU -->
<div class="section" id="wa-menu">
  <div class="section-header"><div class="section-icon blue">📋</div><div><div class="section-title">3.2 Menu WA Bot</div><div class="section-subtitle">Fitur-fitur yang tersedia melalui WhatsApp</div></div></div>
  <div class="section-body">
    <?php $botNomor = preg_replace('/\D/', '', get_pengaturan('wa_bot_nomor', '')); ?>
    <?php if ($botNomor): ?>
    <div style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:10px;padding:16px;margin-bottom:16px">
      <div style="font-weight:700;margin-bottom:6px">Nomor bot AdaptLearn PRE</div>
      <div style="font-size:20px;font-weight:800;letter-spacing:.5px;margin-bottom:10px"><?= htmlspecialchars($botNomor) ?></div>
      <a href="https://wa.me/<?= $botNomor ?>?text=menu" target="_blank" rel="noopener"
         style="display:inline-block;padding:10px 20px;background:#25D366;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;font-size:14px">
        💬 Mulai chat sekarang 
      </a>
    </div>
    <?php endif; ?>
    <p style="margin-bottom:16px">Kirim <strong>"halo"</strong> atau <strong>"menu"</strong> untuk membuka menu utama.</p>
    <div style="background:#f9fbfd;border-radius:10px;padding:16px;border:1px solid var(--border);margin-bottom:20px">
      <div class="wa-reply">Halo <strong>Ahmad</strong>! 👋 Selamat datang di <strong>AdaptLearn PRE</strong>.<br><br>📚 <strong>MENU UTAMA</strong><br>━━━━━━━━━━━━━━━━━━━━<br>1️⃣ Lanjut Belajar<br>2️⃣ Cek Progress<br>3️⃣ Tanya Materi (AI)<br>4️⃣ Ulangi Pre-Test<br>━━━━━━━━━━━━━━━━━━━━<br>Balas dengan angka pilihanmu ya! 😊</div>
    </div>
    <table class="tbl">
      <tr><th>Perintah</th><th>Fungsi</th></tr>
      <tr><td><strong>1</strong></td><td>Lanjut belajar — tampilkan link materi terakhir</td></tr>
      <tr><td><strong>2</strong></td><td>Cek progress per topik dan skor</td></tr>
      <tr><td><strong>3</strong></td><td>Aktifkan asisten AI untuk menjawab pertanyaan</td></tr>
      <tr><td><strong>4</strong></td><td>Ulangi pre-test via WhatsApp</td></tr>
      <tr><td><strong>menu</strong></td><td>Kembali ke menu utama dari mana saja</td></tr>
      <tr><td><strong>batal</strong></td><td>Batalkan aksi saat ini</td></tr>
    </table>
  </div>
</div>

<!-- WA BOT: AI -->
<div class="section" id="wa-ai">
  <div class="section-header"><div class="section-icon blue">🤖</div><div><div class="section-title">3.3 Tanya Materi via AI</div><div class="section-subtitle">Bertanya tentang materi PRE langsung dari WhatsApp</div></div></div>
  <div class="section-body">
    <div class="steps">
      <div class="step"><div class="step-num">1</div><div class="step-content"><h4>Pilih menu "3"</h4><p>Bot masuk ke mode tanya AI.</p></div></div>
      <div class="step"><div class="step-num">2</div><div class="step-content"><h4>Ketik pertanyaanmu</h4><p>Tanyakan materi PRE (Dioda, Transistor, Catu Daya). AI menjawab sesuai profil belajarmu.</p></div></div>
      <div class="step"><div class="step-num">3</div><div class="step-content"><h4>Lanjutkan atau kembali</h4><p>Langsung ketik pertanyaan berikutnya, atau ketik <strong>"batal"</strong> untuk kembali ke menu.</p></div></div>
    </div>
    <div class="info-box green" style="margin-top:16px"><span>✅</span><span>AI menyesuaikan penjelasan dengan profil belajar dan level kemampuanmu secara otomatis.</span></div>
  </div>
</div>

<!-- FAQ -->
<div class="section">
  <div class="section-header"><div class="section-icon orange">❓</div><div><div class="section-title">Pertanyaan yang Sering Diajukan (FAQ)</div></div></div>
  <div class="section-body">
    <table class="tbl">
      <tr><th>Pertanyaan</th><th>Jawaban</th></tr>
      <tr><td>Lupa password?</td><td>Hubungi guru untuk reset password.</td></tr>
      <tr><td>Tombol "Lanjut" tidak aktif?</td><td>Tunggu 35 detik — timer membaca sedang berjalan.</td></tr>
      <tr><td>Post-test tidak bisa diakses?</td><td>Pastikan progress cukup dan guru sudah mengaktifkan post-test.</td></tr>
      <tr><td>WA Bot tidak merespons?</td><td>Pastikan nomor WA terdaftar. Jika belum, kirim: <code>daftar NIS PASSWORD</code>.</td></tr>
      <tr><td>Bisa ulangi pre-test?</td><td>Ya, ketik <strong>"4"</strong> di menu WA Bot atau klik "Ulangi Pre-Test" di profil.</td></tr>
      <tr><td>Hasil evaluasi bisa dilihat lagi?</td><td>Ya, tersimpan dan bisa dilihat kembali dari halaman materi.</td></tr>
    </table>
  </div>
</div>

<div style="text-align:center;padding:20px 0;color:var(--muted);font-size:13px">
  AdaptLearn PRE · SMK Negeri Bansari · 2026<br>
  <small>Dikembangkan sebagai bagian dari penelitian tesis Program Pascasarjana PTEI UNY</small>
</div>

</div></div>
<script>
const links = document.querySelectorAll('.sidebar-link');
const sections = document.querySelectorAll('.section[id]');
window.addEventListener('scroll', () => {
  let current = '';
  sections.forEach(s => { if (window.scrollY >= s.offsetTop - 100) current = s.id; });
  links.forEach(l => { l.classList.remove('active'); if (l.getAttribute('href') === '#' + current) l.classList.add('active'); });
});
links.forEach(l => { l.addEventListener('click', e => { e.preventDefault(); const t = document.querySelector(l.getAttribute('href')); if(t) t.scrollIntoView({behavior:'smooth',block:'start'}); }); });
</script>
</body></html>
