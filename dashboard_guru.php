<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

session_start();

$pdo = db();

// Handler tambah siswa
$pesan_akun = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    if ($_POST['aksi'] === 'tambah') {
        $result = buat_akun_siswa(
            trim($_POST['nis_baru']),
            trim($_POST['nama_baru']),
            trim($_POST['kelas_baru']),
            trim($_POST['wa_baru']),
            trim($_POST['pass_baru'])
        );
        $pesan_akun = $result['status'] === 'ok'
            ? "✓ Akun berhasil dibuat."
            : "✗ " . $result['message'];
    } elseif ($_POST['aksi'] === 'reset_password' && !empty($_POST['reset_id'])) {
        $id = (int) $_POST['reset_id'];
        $new_password = trim($_POST['new_password'] ?? '');
        if ($new_password) {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $id]);
            $pesan_akun = '✓ Password berhasil direset.';
        } else {
            $pesan_akun = '✗ Password baru tidak boleh kosong.';
        }
    } elseif ($_POST['aksi'] === 'reset_password' && !empty($_POST['reset_id'])) {
        $id = (int) $_POST['reset_id'];
        $new_password = trim($_POST['new_password'] ?? '');
        if ($new_password) {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $id]);
            $pesan_akun = '✓ Password berhasil direset.';
        } else {
            $pesan_akun = '✗ Password baru tidak boleh kosong.';
        }
    } elseif ($_POST['aksi'] === 'hapus' && !empty($_POST['hapus_id'])) {
        hapus_akun_siswa((int) $_POST['hapus_id']);
        $pesan_akun = "✓ Akun siswa berhasil dihapus.";
    } elseif ($_POST['aksi'] === 'edit_siswa' && !empty($_POST['edit_id'])) {
        $id   = (int) $_POST['edit_id'];
        $nama = trim($_POST['edit_nama'] ?? '');
        $nis  = trim($_POST['edit_nis'] ?? '');
        $kelas = trim($_POST['edit_kelas'] ?? '');
        $wa   = normalisasi_wa(trim($_POST['edit_wa'] ?? ''));
        if ($nama && $nis) {
            $pdo->prepare("UPDATE users SET nama=?, nis=?, kelas=?, nomor_wa=? WHERE id=? AND role='siswa'")
                ->execute([$nama, $nis, $kelas, $wa, $id]);
            $pesan_akun = '✓ Data siswa berhasil diperbarui.';
        } else {
            $pesan_akun = '✗ Nama dan NIS tidak boleh kosong.';
        }
    } elseif ($_POST['aksi'] === 'setting_posttest') {
        $aktif = isset($_POST['posttest_aktif']) ? '1' : '0';
        $durasi = max(1, (int) $_POST['durasi_hari']);
        $mulai = $_POST['tgl_mulai'] ?: date('Y-m-d');
        $min = max(1, min(100, (int) $_POST['min_persen']));

        set_pengaturan('posttest_aktif', $aktif);
        set_pengaturan('posttest_mulai', $mulai);
        set_pengaturan('posttest_durasi_hari', $durasi);
        set_pengaturan('min_materi_persen', $min);
        $pesan_akun = '✓ Pengaturan post-test berhasil disimpan.';
    } elseif ($_POST['aksi'] === 'setting_ai') {
        set_pengaturan('wa_ai_provider', trim($_POST['wa_ai_provider'] ?? 'groq'));
        set_pengaturan('wa_ai_model',    trim($_POST['wa_ai_model']    ?? 'llama-3.1-8b-instant'));
        set_pengaturan('wa_ai_prompt',   trim($_POST['wa_ai_prompt']   ?? ''));
        $pesan_akun = '✓ Pengaturan AI berhasil disimpan.';
    } elseif ($_POST['aksi'] === 'setting_wa_gateway') {
        set_pengaturan('wa_bot_nomor',    trim($_POST['wa_bot_nomor']    ?? ''));
        set_pengaturan('wa_gateway',   trim($_POST['wa_gateway']   ?? 'fonnte'));
        $pesan_akun = '✓ Konfigurasi WA Gateway berhasil disimpan.';
    }
}

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    header('Location: login_guru.php');
    exit;
}

// $pdo = db();

// ── Data ringkasan ──────────────────────────────────────
$total_siswa = $pdo->query("SELECT COUNT(*) FROM users WHERE role='siswa'")->fetchColumn();
$total_pretest = $pdo->query("SELECT COUNT(*) FROM pre_test_results")->fetchColumn();
$total_aktivitas = $pdo->query("SELECT COUNT(*) FROM activity_log")->fetchColumn();
$avg_skor = $pdo->query("SELECT ROUND(AVG(skor_pengetahuan),1) FROM pre_test_results")->fetchColumn();

// ── Distribusi profil ───────────────────────────────────
$distribusi_profil = $pdo->query("
    SELECT profil_learning, COUNT(*) as jumlah
    FROM pre_test_results
    GROUP BY profil_learning
    ORDER BY jumlah DESC
")->fetchAll();

$distribusi_level = $pdo->query("
    SELECT level_kemampuan, COUNT(*) as jumlah
    FROM pre_test_results
    GROUP BY level_kemampuan
    ORDER BY FIELD(level_kemampuan,'beginner','intermediate','advanced')
")->fetchAll();

// ── Daftar siswa dengan profil terbaru ─────────────────
$siswa_list = $pdo->query("
    SELECT u.id, u.nis, u.nama, u.kelas, u.nomor_wa, u.created_at,
           p.profil_gabungan, p.profil_learning, p.level_kemampuan,
           p.skor_pengetahuan, p.created_at as tgl_pretest,
           pt.skor_pengetahuan as skor_post, pt.created_at as tgl_posttest
    FROM users u
    LEFT JOIN pre_test_results p ON p.id = (
        SELECT id FROM pre_test_results WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1
    )
    LEFT JOIN post_test_results pt ON pt.id = (
        SELECT id FROM post_test_results WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1
    )
    WHERE u.role = 'siswa'
    ORDER BY u.created_at DESC
")->fetchAll();

// ── Activity log terbaru ────────────────────────────────
$aktivitas_terbaru = $pdo->query("
    SELECT a.*, u.nama, u.kelas
    FROM activity_log a
    JOIN users u ON u.id = a.user_id
    ORDER BY a.created_at DESC
    LIMIT 30
")->fetchAll();

// ── Rekap skor evaluasi per topik ──────────────────────
$rekap_evaluasi = $pdo->query("
    SELECT topik,
           COUNT(*) as jumlah_quiz,
           ROUND(AVG(JSON_EXTRACT(detail, '$.persentase')), 1) as rata_persen
    FROM activity_log
    WHERE tipe = 'jawab_quiz'
    GROUP BY topik
")->fetchAll();

$label_profil = [
    'guided_step' => 'Guided-Step',
    'conceptual' => 'Conceptual',
    'practice_oriented' => 'Practice-Oriented',
];
$label_level = [
    'beginner' => 'Pemula',
    'intermediate' => 'Menengah',
    'advanced' => 'Mahir',
];
$warna_level = [
    'beginner' => '#e67e22',
    'intermediate' => '#2980b9',
    'advanced' => '#27ae60',
];
$warna_profil = [
    'guided_step' => '#8e44ad',
    'conceptual' => '#2980b9',
    'practice_oriented' => '#27ae60',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Guru — AdaptLearn PRE</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; min-height: 100vh; }

/* TOPBAR */
.topbar {
    background: #0f3460;
    color: #fff;
    padding: 0 24px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.topbar-brand { font-weight: 700; font-size: 16px; }
.topbar-brand span { font-weight: 300; opacity: 0.6; font-size: 13px; margin-left: 8px; }
.topbar-right { font-size: 13px; opacity: 0.8; }

/* TAB NAV */
.tab-nav {
    background: #fff;
    border-bottom: 2px solid #e8e8e8;
    display: flex;
    padding: 0 24px;
    gap: 0;
    overflow-x: auto;
    position: sticky;
    top: 56px;
    z-index: 99;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.tab-btn {
    padding: 14px 20px;
    font-size: 13px;
    font-weight: 600;
    color: #888;
    border: none;
    border-bottom: 3px solid transparent;
    background: none;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s;
    margin-bottom: -2px;
}
.tab-btn:hover { color: #0f3460; }
.tab-btn.aktif { color: #0f3460; border-bottom-color: #0f3460; }

/* CONTAINER */
.container { max-width: 1100px; margin: 0 auto; padding: 24px 16px; }

/* TAB PANEL */
.tab-panel { display: none; }
.tab-panel.aktif { display: block; }

/* STAT CARDS */
.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px 24px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    border-top: 4px solid #0f3460;
}
.stat-card.green { border-top-color: #27ae60; }
.stat-card.orange { border-top-color: #e67e22; }
.stat-card.purple { border-top-color: #8e44ad; }
.stat-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.8px; color: #999; margin-bottom: 8px; }
.stat-value { font-size: 32px; font-weight: 700; color: #1a1a2e; margin-bottom: 4px; }
.stat-desc { font-size: 12px; color: #aaa; }

/* CHARTS ROW */
.charts-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
.card {
    background: #fff;
    border-radius: 12px;
    padding: 20px 24px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    margin-bottom: 16px;
}
.card-title { font-size: 14px; font-weight: 700; color: #333; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0; }

/* DISTRIBUSI BAR */
.dist-row { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
.dist-label { font-size: 13px; color: #555; width: 130px; flex-shrink: 0; }
.dist-bar-wrap { flex: 1; background: #f0f2f5; border-radius: 99px; height: 8px; overflow: hidden; }
.dist-bar-fill { height: 100%; border-radius: 99px; }
.dist-count { font-size: 13px; font-weight: 700; color: #333; width: 24px; text-align: right; }

/* TABLE */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
thead tr { background: #0f3460; color: #fff; }
th { padding: 11px 14px; text-align: left; font-weight: 600; font-size: 12px; white-space: nowrap; }
td { padding: 11px 14px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #fafbff; }

/* BADGE */
.badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 700;
    color: #fff;
    white-space: nowrap;
}
.badge-tipe {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* FORM */
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; align-items: end; }
.form-group label { display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 6px; }
.form-group input, .form-group select {
    width: 100%;
    padding: 9px 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 13px;
    outline: none;
    transition: border-color 0.2s;
}
.form-group input:focus, .form-group select:focus { border-color: #0f3460; }

/* BUTTONS */
.btn {
    padding: 9px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.2s;
}
.btn-primary { background: #0f3460; color: #fff; }
.btn-primary:hover { background: #16213e; }
.btn-success { background: #27ae60; color: #fff; }
.btn-success:hover { background: #219150; }
.btn-danger { background: #e74c3c; color: #fff; }
.btn-danger:hover { background: #c0392b; }
.btn-outline { background: transparent; border: 2px solid #0f3460; color: #0f3460; }
.btn-outline:hover { background: #f0f7ff; }
.btn-sm { padding: 5px 12px; font-size: 12px; }

/* ALERT */
.alert { padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
.alert-success { background: #e8f8ef; color: #1e8449; border: 1px solid #a9dfbf; }
.alert-danger { background: #fdf0ef; color: #c0392b; border: 1px solid #f5b7b1; }

/* POSTTEST STATUS */
.status-bar {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 12px 16px;
    font-size: 13px;
    color: #555;
    margin-top: 14px;
    line-height: 1.8;
}

/* EMPTY */
.empty-state { text-align: center; padding: 40px; color: #aaa; font-size: 14px; }

@media (max-width: 768px) {
    .stat-grid { grid-template-columns: repeat(2, 1fr); }
    .charts-row { grid-template-columns: 1fr; }
}
</style>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <div class="topbar-brand">Dashboard Guru <span>AdaptLearn PRE — SMK Negeri Bansari</span></div>
    <div class="topbar-right" style="display:flex;align-items:center;gap:16px">
        <span><?= htmlspecialchars($_SESSION['guru_nama'] ?? 'Guru') ?></span>
        <a href="logout.php" style="color:rgba(255,255,255,0.6);font-size:12px;text-decoration:none">Keluar</a>
    </div>
</div>

<!-- TAB NAVIGATION -->
<div class="tab-nav">
    <button class="tab-btn aktif" onclick="bukaTab('ringkasan', this)">📊 Ringkasan</button>
    <button class="tab-btn" onclick="bukaTab('siswa', this)">👥 Siswa</button>
    <button class="tab-btn" onclick="bukaTab('penilaian', this)">📎 Penilaian Jobsheet</button>
    <button class="tab-btn" onclick="bukaTab('materi', this)">⚙️ Pengaturan Materi</button>
    <button class="tab-btn" onclick="bukaTab('pengaturan', this)">🎯 Post-Test</button>
    <button class="tab-btn" onclick="bukaTab('aktivitas', this)">📋 Aktivitas</button>
    <button class="tab-btn" onclick="bukaTab('wabot', this)">🤖 WA Bot & AI</button>
</div>

<div class="container">

<?php if ($pesan_akun): ?>
    <div class="alert <?= str_starts_with($pesan_akun, '✓') ? 'alert-success' : 'alert-danger' ?>">
        <?= htmlspecialchars($pesan_akun) ?>
    </div>
<?php endif; ?>

<!-- ══════════════════════════════════════════
     TAB 1: RINGKASAN
══════════════════════════════════════════ -->
<div id="tab-ringkasan" class="tab-panel aktif">

    <!-- STAT CARDS -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-label">Total Siswa</div>
            <div class="stat-value"><?= $total_siswa ?></div>
            <div class="stat-desc">terdaftar di sistem</div>
        </div>
        <div class="stat-card green">
            <div class="stat-label">Pre-Test Selesai</div>
            <div class="stat-value"><?= $total_pretest ?></div>
            <div class="stat-desc">sesi pre-test</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-label">Rata-rata Skor</div>
            <div class="stat-value"><?= $avg_skor ?></div>
            <div class="stat-desc">dari 12 soal</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-label">Total Aktivitas</div>
            <div class="stat-value"><?= $total_aktivitas ?></div>
            <div class="stat-desc">interaksi tercatat</div>
        </div>
    </div>

    <!-- DISTRIBUSI -->
    <div class="charts-row">
        <div class="card">
            <div class="card-title">Distribusi Profil Belajar</div>
            <?php
            $total_p = array_sum(array_column($distribusi_profil, 'jumlah'));
            foreach ($distribusi_profil as $dp):
                $pct = $total_p > 0 ? round($dp['jumlah'] / $total_p * 100) : 0;
            ?>
            <div class="dist-row">
                <div class="dist-label"><?= $label_profil[$dp['profil_learning']] ?? $dp['profil_learning'] ?></div>
                <div class="dist-bar-wrap">
                    <div class="dist-bar-fill" style="width:<?= $pct ?>%;background:<?= $warna_profil[$dp['profil_learning']] ?? '#888' ?>"></div>
                </div>
                <div class="dist-count"><?= $dp['jumlah'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="card">
            <div class="card-title">Distribusi Level Kemampuan</div>
            <?php
            $total_l = array_sum(array_column($distribusi_level, 'jumlah'));
            foreach ($distribusi_level as $dl):
                $pct = $total_l > 0 ? round($dl['jumlah'] / $total_l * 100) : 0;
            ?>
            <div class="dist-row">
                <div class="dist-label"><?= $label_level[$dl['level_kemampuan']] ?? $dl['level_kemampuan'] ?></div>
                <div class="dist-bar-wrap">
                    <div class="dist-bar-fill" style="width:<?= $pct ?>%;background:<?= $warna_level[$dl['level_kemampuan']] ?? '#888' ?>"></div>
                </div>
                <div class="dist-count"><?= $dl['jumlah'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- REKAP EVALUASI -->
    <div class="card">
        <div class="card-title">Rekap Skor Evaluasi per Topik</div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Topik</th><th>Jumlah Quiz</th><th>Rata-rata Skor</th></tr></thead>
                <tbody>
                <?php foreach ($rekap_evaluasi as $r): ?>
                <tr>
                    <td><?= htmlspecialchars(ucwords(str_replace('_',' ',$r['topik']))) ?></td>
                    <td><?= $r['jumlah_quiz'] ?> kali</td>
                    <td>
                        <?php $pct = (float)($r['rata_persen'] ?? 0); ?>
                        <span class="badge" style="background:<?= $pct>=80?'#27ae60':($pct>=50?'#e67e22':'#e74c3c') ?>">
                            <?= $pct ?>%
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     TAB 2: SISWA
══════════════════════════════════════════ -->
<div id="tab-siswa" class="tab-panel">

    <!-- TAMBAH SISWA -->
    <div class="card">
        <div class="card-title">➕ Tambah Akun Siswa</div>
        <form method="POST">
            <input type="hidden" name="aksi" value="tambah">
            <div class="form-grid">
                <div class="form-group"><label>NIS</label><input type="text" name="nis_baru" placeholder="NIS siswa" required></div>
                <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama_baru" placeholder="Nama lengkap" required></div>
                <div class="form-group">
                    <label>Kelas</label>
                    <select name="kelas_baru">
                        <option value="">— Pilih —</option>
                        <option value="XI TE 1">XI TE 1</option>
                        <option value="XI TE 2">XI TE 2</option>
                        <option value="XI TE 3">XI TE 3</option>
                        <option value="XI TE 4">XI TE 4</option>
                        <option value="XII TE 1">XII TE 1</option>
                        <option value="XII TE 2">XII TE 2</option>
                        <option value="XII TE 3">XII TE 3</option>
                        <option value="XII TE 4">XII TE 4</option>
                    </select>
                </div>
                <div class="form-group"><label>Nomor WA</label><input type="text" name="wa_baru" placeholder="628xxx"></div>
                <div class="form-group"><label>Password Awal</label><input type="text" name="pass_baru" placeholder="Password awal" required></div>
                <div class="form-group"><label>&nbsp;</label><button type="submit" class="btn btn-primary" style="width:100%">+ Tambah Siswa</button></div>
            </div>
        </form>
    </div>

    <!-- ══════════════════════════════════════════
     SISIPKAN INI: setelah card "Tambah Akun Siswa",
     sebelum card "Daftar Siswa" di tab-siswa
    ══════════════════════════════════════════ -->

    <!-- IMPORT EXCEL -->
    <div class="card" style="margin-bottom:24px">
        <div class="section-title">📥 Import Siswa dari Excel</div>

        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:16px">
            <a href="/api/template_siswa.php" target="_blank"
            style="display:inline-block;padding:9px 18px;background:#27ae60;color:#fff;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none">
                ⬇ Download Template Excel
            </a>
            <span style="font-size:12px;color:#666">
                Download template → isi data siswa → upload di bawah
            </span>
        </div>

        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            <input type="file" id="file_import" accept=".xlsx,.xls"
                style="padding:8px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;flex:1;min-width:200px">
            <button onclick="importSiswa()"
                    style="padding:9px 20px;background:#0f3460;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap">
                📤 Upload & Import
            </button>
        </div>

        <div id="hasil_import" style="margin-top:14px;display:none"></div>
    </div>

    <!-- DAFTAR SISWA -->
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px">
            <div class="card-title" style="margin:0">👥 Daftar Siswa (<?= count($siswa_list) ?>)</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <select id="filter_kelas" style="padding:7px 10px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:13px">
                    <option value="">Semua Kelas</option>
                    <?php foreach(['XI TE 1','XI TE 2','XI TE 3','XI TE 4','XII TE 1','XII TE 2','XII TE 3','XII TE 4'] as $k): ?>
                    <option value="<?= $k ?>"><?= $k ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filter_profil" style="padding:7px 10px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:13px">
                    <option value="">Semua Profil</option>
                    <option value="guided">Guided-Step</option>
                    <option value="conceptual">Conceptual</option>
                    <option value="practice">Practice-Oriented</option>
                </select>
                <a href="api/ekspor_ngain.php" class="btn btn-success btn-sm" style="font-size:12px;padding:6px 14px">⬇ Ekspor CSV</a>
            </div>
        </div>
        <div style="overflow-x:auto">
        <table id="tabel_siswa" style="width:100%;font-size:13px">
            <thead>
                <tr>
                    <th>#</th><th>NIS</th><th>Nama</th><th>Kelas</th>
                    <th>Profil</th><th>Level</th>
                    <th>Skor Pre</th><th>Skor Post</th><th>N-Gain</th>
                    <th>Tgl Pre-Test</th><th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($siswa_list as $i => $s): ?>
            <?php
                $ngain = null;
                if ($s['skor_pengetahuan'] !== null && $s['skor_post'] !== null) {
                    $ngain = hitung_ngain((int)$s['skor_pengetahuan'], (int)$s['skor_post']);
                }
            ?>
            <tr data-kelas="<?= htmlspecialchars($s['kelas'] ?? '') ?>" data-profil="<?= htmlspecialchars($s['profil_learning'] ?? '') ?>">
                <td><?= $i+1 ?></td>
                <td><?= htmlspecialchars($s['nis'] ?? '-') ?></td>
                <td><strong><?= htmlspecialchars($s['nama']) ?></strong></td>
                <td><?= htmlspecialchars($s['kelas'] ?? '-') ?></td>
                <td>
                    <?php if ($s['profil_learning']): ?>
                    <span class="badge" style="background:<?= $warna_profil[$s['profil_learning']] ?? '#888' ?>;white-space:nowrap">
                        <?= $label_profil[$s['profil_learning']] ?? $s['profil_learning'] ?>
                    </span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                    <?php if ($s['level_kemampuan']): ?>
                    <span class="badge" style="background:<?= $warna_level[$s['level_kemampuan']] ?? '#888' ?>">
                        <?= $label_level[$s['level_kemampuan']] ?? $s['level_kemampuan'] ?>
                    </span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td><?= $s['skor_pengetahuan'] !== null ? $s['skor_pengetahuan'].'/12' : '-' ?></td>
                <td><?= $s['skor_post'] !== null ? $s['skor_post'].'/12' : '-' ?></td>
                <td>
                    <?php if ($ngain): ?>
                    <span class="badge" style="background:<?= $ngain['warna'] ?>;white-space:nowrap">
                        <?= number_format($ngain['ngain'],2) ?> — <?= $ngain['kategori'] ?>
                    </span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td style="font-size:12px;color:#888;white-space:nowrap"><?= $s['tgl_pretest'] ? date('d/m/Y', strtotime($s['tgl_pretest'])) : '-' ?></td>
                <td>
                    <div style="display:flex;flex-direction:column;gap:4px;min-width:160px">
                        <button type="button" class="btn btn-sm btn-outline"
                            style="background:#2980b9;color:#fff;border-color:#2980b9"
                            onclick="bukaModalEdit(<?= $s['id'] ?>, '<?= htmlspecialchars($s['nama'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['nis'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['kelas'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['nomor_wa'] ?? '', ENT_QUOTES) ?>')">
                            ✏️ Edit
                        </button>
                        <form method="POST" style="display:flex;gap:4px">
                            <input type="hidden" name="aksi" value="reset_password">
                            <input type="hidden" name="reset_id" value="<?= $s['id'] ?>">
                            <input type="text" name="new_password" placeholder="Password baru" style="width:90px;padding:4px 6px;font-size:11px;border:1px solid #ddd;border-radius:4px">
                            <button type="submit" class="btn btn-sm btn-outline">Reset</button>
                        </form>
                        <form method="POST" onsubmit="return confirm('Hapus akun <?= htmlspecialchars($s['nama']) ?>?')">
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="hapus_id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var table = $('#tabel_siswa').DataTable({
        pageLength: 25,
        language: {
            search: "Cari:",
            lengthMenu: "Tampilkan _MENU_ siswa",
            info: "Menampilkan _START_–_END_ dari _TOTAL_ siswa",
            infoEmpty: "Tidak ada data",
            zeroRecords: "Tidak ada siswa yang cocok",
            paginate: { previous: "‹ Prev", next: "Next ›" }
        },
        columnDefs: [
            { orderable: false, targets: [10] }, // kolom Aksi tidak sortable
            { width: '30px', targets: [0] },
            { width: '80px', targets: [1] },
            { width: '70px', targets: [6,7] },
        ],
        order: [[2, 'asc']], // default sort by nama
        rowCallback: function(row, data, displayIndex, displayIndexFull) {
            $('td:first', row).html(displayIndexFull + 1);
        }
    });

    // Filter kelas
    $('#filter_kelas').on('change', function() {
        table.column(3).search(this.value).draw();
    });

    // Filter profil
    $('#filter_profil').on('change', function() {
        table.column(4).search(this.value).draw();
    });
});

// ── Import Excel dengan progress per batch ────────────────────────
let importData = [];
let importOffset = 0;
const BATCH_SIZE = 20;

function importSiswa() {
    const fileInput = document.getElementById('file_import');
    const hasilDiv  = document.getElementById('hasil_import');

    if (!fileInput.files.length) {
        hasilDiv.style.display = 'block';
        hasilDiv.innerHTML = '<div style="padding:10px 14px;background:#fff0f0;color:#cc0000;border-radius:8px;font-size:13px;border:1px solid #ffcccc">⚠ Pilih file Excel terlebih dahulu.</div>';
        return;
    }

    hasilDiv.style.display = 'block';
    hasilDiv.innerHTML = '<div style="padding:10px 14px;background:#f0f4ff;color:#0f3460;border-radius:8px;font-size:13px">⏳ Membaca file Excel...</div>';

    // Step 1: upload file → parse → dapat total baris
    const formData = new FormData();
    formData.append('file_excel', fileInput.files[0]);
    formData.append('aksi', 'parse');

    fetch('/api/import_siswa.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'error') {
            hasilDiv.innerHTML = `<div style="padding:10px 14px;background:#fff0f0;color:#cc0000;border-radius:8px;font-size:13px;border:1px solid #ffcccc">✗ ${data.pesan}</div>`;
            return;
        }
        importData   = data.rows;
        importOffset = 0;
        prosesImport(hasilDiv, data.rows.length);
    })
    .catch(() => {
        hasilDiv.innerHTML = '<div style="padding:10px 14px;background:#fff0f0;color:#cc0000;border-radius:8px;font-size:13px">✗ Gagal membaca file.</div>';
    });
}

function prosesImport(hasilDiv, total) {
    if (importOffset >= total) {
        hasilDiv.innerHTML = hasilDiv.innerHTML; // biarkan hasil akhir tampil
        setTimeout(() => location.reload(), 2000);
        return;
    }

    const batch = importData.slice(importOffset, importOffset + BATCH_SIZE);
    const pct   = Math.min(100, Math.round((importOffset / total) * 100));

    hasilDiv.innerHTML = `
        <div style="margin-bottom:8px;font-size:13px;color:#0f3460;font-weight:600">
            Mengimpor... ${importOffset}/${total} siswa (${pct}%)
        </div>
        <div style="background:#e0e0e0;border-radius:20px;height:12px;overflow:hidden">
            <div style="width:${pct}%;background:#0f3460;height:100%;border-radius:20px;transition:width 0.3s ease"></div>
        </div>`;

    fetch('/api/import_siswa.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ aksi: 'batch', rows: batch, is_last: (importOffset + BATCH_SIZE >= importData.length) })
    })
    .then(r => r.json())
    .then(data => {
        importOffset += BATCH_SIZE;
        const pctNow = Math.min(100, Math.round((importOffset / total) * 100));

        if (importOffset >= total) {
            // Selesai
            let html = `
                <div style="margin-bottom:8px;font-size:13px;color:#155724;font-weight:600">Selesai! ${pctNow}%</div>
                <div style="background:#e0e0e0;border-radius:20px;height:12px;overflow:hidden;margin-bottom:12px">
                    <div style="width:100%;background:#27ae60;height:100%;border-radius:20px"></div>
                </div>
                <div style="padding:10px 14px;background:#e8f8f0;color:#155724;border-radius:8px;font-size:13px;border:1px solid #c3e6cb">
                    ✓ Import selesai — <strong>${data.total_berhasil} siswa berhasil</strong>, <strong>${data.total_gagal} dilewati</strong>.
                </div>`;
            if (data.detail_gagal && data.detail_gagal.length) {
                html += `<div style="margin-top:8px;padding:10px 14px;background:#fff8e1;border-radius:8px;font-size:12px;border:1px solid #ffe082">
                    <strong>Detail dilewati:</strong><br>${data.detail_gagal.map(s=>'• '+s).join('<br>')}
                </div>`;
            }
            hasilDiv.innerHTML = html;
            setTimeout(() => location.reload(), 3000);
        } else {
            prosesImport(hasilDiv, total);
        }
    })
    .catch(() => {
        hasilDiv.innerHTML += '<br><span style="color:red;font-size:12px">✗ Error pada batch, coba lagi.</span>';
    });
}
</script>
<!-- ══════════════════════════════════════════
     TAB 3: PENILAIAN JOBSHEET
══════════════════════════════════════════ -->
<div id="tab-penilaian" class="tab-panel">
    <?php
    $submissions = $pdo->query("
        SELECT js.id, u.nama, u.nis, u.kelas, c.judul, c.topik,
               js.file_path, js.file_original_name, js.nilai, js.created_at
        FROM jobsheet_submissions js
        JOIN users u ON u.id = js.user_id
        JOIN content c ON c.id = js.content_id
        ORDER BY js.created_at DESC
    ")->fetchAll();
    ?>
    <div class="card">
        <div class="card-title">📎 Submission Jobsheet Siswa (<?= count($submissions) ?>)</div>
        <?php if ($submissions): ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Siswa</th><th>Kelas</th><th>Jobsheet</th><th>File</th><th>Tgl Upload</th><th>Nilai</th></tr></thead>
                <tbody>
                <?php foreach ($submissions as $sub): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($sub['nama']) ?></strong><br><small style="color:#888"><?= $sub['nis'] ?></small></td>
                    <td><?= htmlspecialchars($sub['kelas'] ?? '-') ?></td>
                    <td>
                        <small style="color:#888"><?= ucwords(str_replace('_',' ',$sub['topik'])) ?></small><br>
                        <?= htmlspecialchars(mb_strimwidth($sub['judul'],0,40,'...')) ?>
                    </td>
                    <td><a href="/<?= htmlspecialchars($sub['file_path']) ?>" target="_blank" style="color:#0f3460;font-size:13px">📄 <?= htmlspecialchars(mb_strimwidth($sub['file_original_name'],0,25,'...')) ?></a></td>
                    <td style="font-size:12px;color:#666"><?= date('d/m/Y H:i', strtotime($sub['created_at'])) ?></td>
                    <td>
                        <form method="POST" action="api/nilai_jobsheet.php" style="display:flex;gap:6px;align-items:center">
                            
                            <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">
                            <input type="number" name="nilai" min="0" max="100" step="0.5" value="<?= $sub['nilai'] ?? '' ?>" style="width:60px;padding:4px;border:1px solid #ccc;border-radius:4px;font-size:13px">
                            <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">Belum ada submission jobsheet dari siswa.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ══════════════════════════════════════════
     TAB 4: PENGATURAN MATERI
══════════════════════════════════════════ -->
<div id="tab-materi" class="tab-panel">
    <?php
    $topik_tree_dash = get_topik_tree();
    $total_konten_semua = $pdo->query("SELECT COUNT(*) FROM content WHERE aktif=1")->fetchColumn();
    $total_topik_semua  = $pdo->query("SELECT COUNT(*) FROM topik WHERE aktif=1")->fetchColumn();
    ?>

    <!-- TOMBOL AKSI -->
    <div style="display:flex;gap:16px;margin-bottom:24px">
        <a href="kelola_konten.php" class="btn btn-primary" style="font-size:15px;padding:12px 24px">
            Kelola Konten Materi
        </a>
        <a href="kelola_topik.php" class="btn btn-outline" style="font-size:15px;padding:12px 24px">
            Kelola Topik &amp; Sub-Topik
        </a>
    </div>

    <!-- RINGKASAN STAT -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px">
        <div class="card" style="margin-bottom:0;border-left:4px solid #0f3460">
            <div class="stat-label">Total Topik Aktif</div>
            <div class="stat-value"><?= $total_topik_semua ?></div>
            <div class="stat-desc">termasuk sub-topik</div>
        </div>
        <div class="card" style="margin-bottom:0;border-left:4px solid #27ae60">
            <div class="stat-label">Total Konten Aktif</div>
            <div class="stat-value"><?= $total_konten_semua ?></div>
            <div class="stat-desc">di semua topik</div>
        </div>
    </div>

    <!-- RINGKASAN PER TOPIK -->
    <div class="card">
        <div class="card-title">Ringkasan Konten per Topik</div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Topik</th><th style="text-align:center">Teori</th><th style="text-align:center">Langkah</th><th style="text-align:center">Jobsheet</th><th style="text-align:center">Evaluasi</th><th style="text-align:center">Tantangan</th><th style="text-align:center">Total</th><th style="text-align:center">Aksi</th></tr></thead>
                <tbody>
                <?php
                function hitungKonten($pdo, $slug) {
                    $stmt = $pdo->prepare("SELECT tipe, COUNT(*) as jml FROM content WHERE topik=? AND aktif=1 GROUP BY tipe");
                    $stmt->execute([$slug]);
                    $result = ['teori'=>0,'langkah'=>0,'jobsheet'=>0,'evaluasi'=>0,'tantangan'=>0];
                    foreach ($stmt->fetchAll() as $r) if (isset($result[$r['tipe']])) $result[$r['tipe']] = $r['jml'];
                    $result['total'] = array_sum($result);
                    return $result;
                }
                foreach ($topik_tree_dash as $parent):
                    if (empty($parent['children'])):
                        $k = hitungKonten($pdo, $parent['slug']);
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($parent['nama']) ?></strong></td>
                    <?php foreach (['teori','langkah','jobsheet','evaluasi','tantangan'] as $tipe): ?>
                    <td style="text-align:center"><?= $k[$tipe] > 0 ? '<span style="color:#27ae60;font-weight:700">'.$k[$tipe].'</span>' : '<span style="color:#ddd">-</span>' ?></td>
                    <?php endforeach; ?>
                    <td style="text-align:center"><strong><?= $k['total'] ?></strong></td>
                    <td style="text-align:center"><a href="kelola_konten.php" class="btn btn-sm btn-outline">Edit</a></td>
                </tr>
                <?php else: ?>
                <tr style="background:#f8f9ff">
                    <td colspan="8" style="font-size:12px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.8px;padding:8px 14px">
                        <?= htmlspecialchars($parent['nama']) ?>
                    </td>
                </tr>
                <?php foreach ($parent['children'] as $child):
                    $k = hitungKonten($pdo, $child['slug']);
                ?>
                <tr>
                    <td style="padding-left:28px">&#8627; <?= htmlspecialchars($child['nama']) ?></td>
                    <?php foreach (['teori','langkah','jobsheet','evaluasi','tantangan'] as $tipe): ?>
                    <td style="text-align:center"><?= $k[$tipe] > 0 ? '<span style="color:#27ae60;font-weight:700">'.$k[$tipe].'</span>' : '<span style="color:#ddd">-</span>' ?></td>
                    <?php endforeach; ?>
                    <td style="text-align:center"><strong><?= $k['total'] ?></strong></td>
                    <td style="text-align:center"><a href="kelola_konten.php" class="btn btn-sm btn-outline">Edit</a></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<!-- ══════════════════════════════════════════
     TAB 5: POST-TEST
══════════════════════════════════════════ -->
<div id="tab-pengaturan" class="tab-panel">
    <?php
    $pt_aktif  = get_pengaturan('posttest_aktif', '0');
    $pt_mulai  = get_pengaturan('posttest_mulai');
    $pt_durasi = get_pengaturan('posttest_durasi_hari', 21);
    $pt_min    = get_pengaturan('min_materi_persen', 100);
    ?>
    <div class="card">
        <div class="card-title">🎯 Pengaturan Post-Test</div>
        <form method="POST">
            <input type="hidden" name="aksi" value="setting_posttest">
            <div class="form-grid">
                <div class="form-group">
                    <label>Status Post-Test</label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;padding:9px 0">
                        <input type="checkbox" name="posttest_aktif" value="1" <?= $pt_aktif === '1' ? 'checked' : '' ?>>
                        Aktifkan akses post-test
                    </label>
                </div>
                <div class="form-group">
                    <label>Tanggal Mulai Pembelajaran</label>
                    <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($pt_mulai ?? date('Y-m-d')) ?>">
                </div>
                <div class="form-group">
                    <label>Durasi Pembelajaran (hari)</label>
                    <input type="number" name="durasi_hari" value="<?= htmlspecialchars($pt_durasi) ?>" min="1" max="180">
                </div>
                <div class="form-group">
                    <label>Min. Progress Materi (%)</label>
                    <input type="number" name="min_persen" value="<?= htmlspecialchars($pt_min) ?>" min="1" max="100">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-success" style="width:100%">Simpan Pengaturan</button>
                </div>
            </div>
        </form>
        <div class="status-bar">
            <strong>Status saat ini:</strong>
            Post-test <strong><?= $pt_aktif === '1' ? '✓ AKTIF' : '✗ NONAKTIF' ?></strong>
            · Mulai: <strong><?= $pt_mulai ? date('d/m/Y', strtotime($pt_mulai)) : '-' ?></strong>
            · Durasi: <strong><?= $pt_durasi ?> hari</strong>
            · Min. progress: <strong><?= $pt_min ?>%</strong>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     TAB 6: AKTIVITAS
══════════════════════════════════════════ -->
<div id="tab-aktivitas" class="tab-panel">
    <div class="card">
        <div class="card-title">📋 Activity Log Terbaru (30 terakhir)</div>
        <?php if ($aktivitas_terbaru): ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Waktu</th><th>Siswa</th><th>Kelas</th><th>Aktivitas</th><th>Topik</th><th>Detail</th></tr></thead>
                <tbody>
                <?php foreach ($aktivitas_terbaru as $a):
                    $detail = json_decode($a['detail'] ?? '{}', true) ?? [];
                    $warna_tipe = [
                        'buka_materi'     => '#2980b9',
                        'selesai_materi'  => '#27ae60',
                        'jawab_quiz'      => '#8e44ad',
                        'upload_jobsheet' => '#e67e22',
                        'login'           => '#95a5a6',
                        'pretest'         => '#e74c3c',
                    ];
                    $wt = $warna_tipe[$a['tipe']] ?? '#888';
                ?>
                <tr>
                    <td style="font-size:12px;color:#666;white-space:nowrap"><?= date('d/m H:i', strtotime($a['created_at'])) ?></td>
                    <td><strong><?= htmlspecialchars($a['nama']) ?></strong></td>
                    <td><?= htmlspecialchars($a['kelas'] ?? '-') ?></td>
                    <td><span class="badge-tipe" style="background:<?= $wt ?>22;color:<?= $wt ?>;border:1px solid <?= $wt ?>44"><?= strtoupper(str_replace('_',' ',$a['tipe'])) ?></span></td>
                    <td><?= htmlspecialchars(ucwords(str_replace('_',' ',$a['topik'] ?? '-'))) ?></td>
                    <td style="font-size:12px;color:#666">
                        <?php if (isset($detail['judul'])): ?>
                            <?= htmlspecialchars(mb_strimwidth($detail['judul'],0,40,'...')) ?>
                        <?php elseif (isset($detail['skor'])): ?>
                            Skor: <?= $detail['skor'] ?>/<?= $detail['total'] ?? '?' ?> (<?= $detail['persentase'] ?? '?' ?>%)
                        <?php elseif (isset($detail['profil_gabungan'])): ?>
                            <?= htmlspecialchars($detail['profil_gabungan']) ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">Belum ada aktivitas tercatat.</div>
        <?php endif; ?>
    </div>
</div>

</div><!-- /container -->

<!-- ══════════════════════════════════════════
     TAB 7: WA BOT & AI
══════════════════════════════════════════ -->
<div id="tab-wabot" class="tab-panel">

    <!-- STATUS WA BOT -->
    <div class="card">
        <div class="card-title">📱 Status & Konfigurasi WA Bot</div>

        <!-- Status ringkas -->
        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px">
            <div style="padding:12px 20px;background:#e8f8f0;border-radius:10px;border:1px solid #c3e6cb;flex:1;min-width:180px">
                <div style="font-size:11px;color:#666;margin-bottom:4px">Nomor Bot</div>
                <div style="font-weight:700;color:#155724"><?= get_pengaturan('wa_bot_nomor', '-') ?></div>
            </div>
            <div style="padding:12px 20px;background:#e8f4fd;border-radius:10px;border:1px solid #b8daff;flex:1;min-width:180px">
                <div style="font-size:11px;color:#666;margin-bottom:4px">Model AI Aktif</div>
                <div style="font-weight:700;color:#0f3460"><?= get_pengaturan('wa_ai_model', 'llama-3.1-8b-instant') ?></div>
            </div>
            <div style="padding:12px 20px;background:#fff8e1;border-radius:10px;border:1px solid #ffe082;flex:1;min-width:180px">
                <div style="font-size:11px;color:#666;margin-bottom:4px">Provider</div>
                <div style="font-weight:700;color:#e67e22"><?= strtoupper(get_pengaturan('wa_ai_provider', 'groq')) ?></div>
            </div>
        </div>

        <!-- Webhook URL -->
        <div style="margin-bottom:20px">
            <div style="font-size:13px;font-weight:600;color:#444;margin-bottom:8px">🔗 URL Webhook Fonnte</div>
            <div style="display:flex;gap:8px;align-items:center">
                <input type="text" id="webhook_url" readonly value="<?= APP_URL ?>/api/wa_webhook.php"
                    style="flex:1;padding:9px 12px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;font-family:monospace;background:#f9f9f9;color:#333">
                <button onclick="copyWebhook()"
                    style="padding:9px 16px;background:#0f3460;color:#fff;border:none;border-radius:8px;font-size:13px;cursor:pointer;white-space:nowrap"
                    id="btn_copy_webhook">📋 Copy</button>
            </div>
            <div style="font-size:11px;color:#888;margin-top:4px">Paste URL ini di pengaturan Webhook Fonnte → Device → Edit</div>
        </div>

        <!-- Form konfigurasi WA Gateway -->
        <form method="POST">
            <input type="hidden" name="aksi" value="setting_wa_gateway">

            <!-- Pilih Gateway -->
            <?php $gw_aktif = get_pengaturan('wa_gateway', 'fonnte'); ?>
            <div style="margin-bottom:20px">
                <label style="font-size:13px;font-weight:600;color:#444;display:block;margin-bottom:8px">Gateway Aktif</label>
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <label style="display:flex;align-items:center;gap:8px;padding:10px 18px;border:2px solid <?= $gw_aktif==='fonnte' ? '#0f3460' : '#e0e0e0' ?>;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600">
                        <input type="radio" name="wa_gateway" value="fonnte" <?= $gw_aktif==='fonnte' ? 'checked' : '' ?>>
                        📱 Fonnte
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;padding:10px 18px;border:2px solid <?= $gw_aktif==='whacenter' ? '#0f3460' : '#e0e0e0' ?>;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600">
                        <input type="radio" name="wa_gateway" value="whacenter" <?= $gw_aktif==='whacenter' ? 'checked' : '' ?>>
                        💬 Whacenter
                    </label>
                </div>
            </div>

            <!-- Nomor Bot -->
            <div style="margin-bottom:16px">
                <label style="font-size:13px;font-weight:600;color:#444;display:block;margin-bottom:6px">Nomor Bot (format: 628xxx)</label>
                <input type="text" name="wa_bot_nomor" value="<?= htmlspecialchars(get_pengaturan('wa_bot_nomor', '')) ?>"
                    placeholder="6285111308087"
                    style="width:100%;padding:9px 12px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px">
                <div style="font-size:11px;color:#888;margin-top:3px">Nomor WA yang terhubung ke gateway aktif</div>
            </div>

            <!-- Info Fonnte -->
            <div style="margin-bottom:16px;padding:14px 16px;background:#f0f4ff;border-radius:10px;border:1px solid #b8daff">
                <div style="font-size:13px;font-weight:600;color:#0f3460;margin-bottom:8px">📱 Fonnte — Token Aktif</div>
                <?php
                $tok = defined('FONNTE_TOKEN') ? FONNTE_TOKEN : '';
                $tok_sensor = $tok ? (substr($tok,0,4) . str_repeat('*', max(0,strlen($tok)-8)) . substr($tok,-4)) : '-';
                ?>
                <input type="text" readonly value="<?= htmlspecialchars($tok_sensor) ?>"
                    style="width:100%;padding:8px 12px;border:1px solid #b8daff;border-radius:6px;font-size:12px;background:#fff;color:#666;font-family:monospace;margin-bottom:6px">
                <div style="font-size:11px;color:#888">Tersensor. Ganti via SSH:</div>
                <div style="display:flex;gap:8px;align-items:center;margin-top:4px">
                    <code id="ssh_cmd" style="flex:1;padding:6px 10px;background:#1e1e2e;color:#a6e3a1;border-radius:6px;font-size:10px;display:block;overflow-x:auto;white-space:nowrap">sudo sed -i "s|define('FONNTE_TOKEN', '.*');|define('FONNTE_TOKEN', 'TOKEN_BARU');|" /var/www/html/aibotlms/config/config.php</code>
                    <button onclick="copySSH()" id="btn_copy_ssh" style="padding:5px 10px;background:#1e1e2e;color:#a6e3a1;border:1px solid #a6e3a1;border-radius:6px;font-size:11px;cursor:pointer;white-space:nowrap">📋</button>
                </div>
            </div>

            <!-- Info Whacenter -->
            <div style="margin-bottom:20px;padding:14px 16px;background:#f0fff4;border-radius:10px;border:1px solid #c3e6cb">
                <div style="font-size:13px;font-weight:600;color:#155724;margin-bottom:8px">💬 Whacenter — Device ID Aktif</div>
                <?php
                $wc = defined('WHACENTER_DEVICE_ID') ? WHACENTER_DEVICE_ID : '';
                $wc_sensor = $wc ? (substr($wc,0,8) . str_repeat('*', max(0,strlen($wc)-16)) . substr($wc,-8)) : '-';
                ?>
                <input type="text" readonly value="<?= htmlspecialchars($wc_sensor) ?>"
                    style="width:100%;padding:8px 12px;border:1px solid #c3e6cb;border-radius:6px;font-size:12px;background:#fff;color:#666;font-family:monospace;margin-bottom:6px">
                <div style="font-size:11px;color:#888">Tersensor. Ganti via SSH:</div>
                <div style="display:flex;gap:8px;align-items:center;margin-top:4px">
                    <code id="ssh_cmd_wc" style="flex:1;padding:6px 10px;background:#1e1e2e;color:#a6e3a1;border-radius:6px;font-size:10px;display:block;overflow-x:auto;white-space:nowrap">sudo sed -i "s|define('WHACENTER_DEVICE_ID', '.*');|define('WHACENTER_DEVICE_ID', 'DEVICE_ID_BARU');|" /var/www/html/aibotlms/config/config.php</code>
                    <button onclick="copySSHWC()" id="btn_copy_ssh_wc" style="padding:5px 10px;background:#1e1e2e;color:#a6e3a1;border:1px solid #a6e3a1;border-radius:6px;font-size:11px;cursor:pointer;white-space:nowrap">📋</button>
                </div>
            </div>

            <button type="submit"
                style="padding:9px 20px;background:#27ae60;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
                💾 Simpan Konfigurasi Gateway
            </button>
        </form>

        <script>
        function copyWebhook() {
            const url = document.getElementById('webhook_url').value;
            navigator.clipboard.writeText(url).then(() => {
                const btn = document.getElementById('btn_copy_webhook');
                btn.textContent = '✅ Tersalin!';
                btn.style.background = '#27ae60';
                setTimeout(() => {
                    btn.textContent = '📋 Copy';
                    btn.style.background = '#0f3460';
                }, 2000);
            });
        }
        function copySSH() {
            const cmd = document.getElementById('ssh_cmd').textContent;
            navigator.clipboard.writeText(cmd).then(() => {
                const btn = document.getElementById('btn_copy_ssh');
                btn.textContent = '✅ Tersalin!';
                setTimeout(() => { btn.textContent = '📋 Copy'; }, 2000);
            });
        }
        function copySSHWC() {
            const cmd = document.getElementById('ssh_cmd_wc').textContent;
            navigator.clipboard.writeText(cmd).then(() => {
                const btn = document.getElementById('btn_copy_ssh_wc');
                btn.textContent = '✅';
                setTimeout(() => { btn.textContent = '📋'; }, 2000);
            });
        }
        </script>
    </div>

    <!-- PENGATURAN MODEL AI -->
    <div class="card">
        <div class="card-title">🤖 Pilih Model AI</div>

        <?php
        $model_list = [
            'groq' => [
                'label' => 'Groq',
                'models' => [
                    ['id' => 'llama-3.1-8b-instant',                    'nama' => 'Llama 3.1 8B Instant',        'gratis' => true,  'desc' => 'Cepat, ringan, cocok untuk pertanyaan sederhana'],
                    ['id' => 'llama-3.3-70b-versatile',                  'nama' => 'Llama 3.3 70B Versatile',     'gratis' => true,  'desc' => 'Lebih pintar, jawaban lebih detail'],
                    ['id' => 'meta-llama/llama-4-scout-17b-16e-instruct','nama' => 'Llama 4 Scout 17B',           'gratis' => true,  'desc' => 'Model terbaru Meta, seimbang'],
                    ['id' => 'qwen/qwen3-32b',                           'nama' => 'Qwen3 32B',                   'gratis' => true,  'desc' => 'Model Alibaba, kuat untuk sains & teknik'],
                    ['id' => 'groq/compound',                            'nama' => 'Groq Compound',               'gratis' => false, 'desc' => 'Model compound Groq, berbayar'],
                ],
            ],
            'gemini' => [
                'label' => 'Google Gemini',
                'models' => [
                    ['id' => 'gemini-2.0-flash',      'nama' => 'Gemini 2.0 Flash',      'gratis' => false, 'desc' => 'Google Gemini terbaru, berbayar'],
                    ['id' => 'gemini-2.0-flash-lite',  'nama' => 'Gemini 2.0 Flash Lite', 'gratis' => false, 'desc' => 'Lebih hemat, cocok untuk volume tinggi'],
                ],
            ],
        ];
        $provider_aktif = get_pengaturan('wa_ai_provider', 'groq');
        $model_aktif    = get_pengaturan('wa_ai_model', 'llama-3.1-8b-instant');
        ?>

        <form method="POST" id="form_ai_setting">
            <input type="hidden" name="aksi" value="setting_ai">

            <!-- Pilih Provider -->
            <div style="margin-bottom:20px">
                <label style="font-size:13px;font-weight:600;color:#444;display:block;margin-bottom:8px">Provider AI</label>
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <?php foreach ($model_list as $pkey => $pdata): ?>
                    <label style="display:flex;align-items:center;gap:8px;padding:10px 16px;border:2px solid <?= $provider_aktif === $pkey ? '#0f3460' : '#e0e0e0' ?>;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600">
                        <input type="radio" name="wa_ai_provider" value="<?= $pkey ?>" <?= $provider_aktif === $pkey ? 'checked' : '' ?> onchange="gantiProvider('<?= $pkey ?>')">
                        <?= $pdata['label'] ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Daftar Model per Provider -->
            <?php foreach ($model_list as $pkey => $pdata): ?>
            <div id="models_<?= $pkey ?>" style="display:<?= $provider_aktif === $pkey ? 'block' : 'none' ?>;margin-bottom:20px">
                <label style="font-size:13px;font-weight:600;color:#444;display:block;margin-bottom:8px">Model</label>
                <div style="display:flex;flex-direction:column;gap:8px">
                    <?php foreach ($pdata['models'] as $m): ?>
                    <label style="display:flex;align-items:center;gap:12px;padding:12px 16px;border:2px solid <?= $model_aktif === $m['id'] ? '#0f3460' : '#e0e0e0' ?>;border-radius:8px;cursor:pointer">
                        <input type="radio" name="wa_ai_model" value="<?= $m['id'] ?>" <?= $model_aktif === $m['id'] ? 'checked' : '' ?>>
                        <div style="flex:1">
                            <div style="font-size:13px;font-weight:600;color:#333">
                                <?= $m['nama'] ?>
                                <?php if ($m['gratis']): ?>
                                <span style="background:#e8f8f0;color:#155724;font-size:10px;padding:2px 8px;border-radius:20px;font-weight:600;margin-left:6px">GRATIS</span>
                                <?php else: ?>
                                <span style="background:#fff0f0;color:#cc0000;font-size:10px;padding:2px 8px;border-radius:20px;font-weight:600;margin-left:6px">BERBAYAR</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:11px;color:#888;margin-top:2px"><?= $m['desc'] ?> · <code style="font-size:10px"><?= $m['id'] ?></code></div>
                        </div>
                        <button type="button" onclick="cekModel('<?= $pkey ?>', '<?= $m['id'] ?>', this)"
                            style="padding:5px 12px;background:#f0f4ff;color:#0f3460;border:1px solid #b8daff;border-radius:6px;font-size:11px;cursor:pointer;white-space:nowrap">
                            🔍 Cek
                        </button>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- System Prompt -->
            <div style="margin-bottom:20px">
                <label style="font-size:13px;font-weight:600;color:#444;display:block;margin-bottom:8px">System Prompt AI</label>
                <textarea name="wa_ai_prompt" rows="5"
                    style="width:100%;padding:10px 12px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;font-family:monospace;resize:vertical"
                    placeholder="Kamu adalah asisten belajar..."><?= htmlspecialchars(get_pengaturan('wa_ai_prompt', '')) ?></textarea>
                <div style="font-size:11px;color:#888;margin-top:4px">Kosongkan untuk menggunakan prompt default sistem.</div>
            </div>

            <button type="submit" style="padding:10px 24px;background:#0f3460;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
                💾 Simpan Pengaturan AI
            </button>
        </form>

        <!-- Hasil cek model -->
        <div id="hasil_cek_model" style="margin-top:16px;display:none"></div>
    </div>

</div>

<script>
function gantiProvider(provider) {
    document.querySelectorAll('[id^="models_"]').forEach(el => el.style.display = 'none');
    document.getElementById('models_' + provider).style.display = 'block';
}

function cekModel(provider, modelId, btn) {
    const hasil = document.getElementById('hasil_cek_model');
    hasil.style.display = 'block';
    hasil.innerHTML = '<div style="padding:10px 14px;background:#f0f4ff;color:#0f3460;border-radius:8px;font-size:13px">🔍 Mengecek model <code>' + modelId + '</code>...</div>';
    btn.disabled = true;
    btn.textContent = '⏳';

    fetch('/api/cek_model_ai.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({provider: provider, model: modelId})
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = '🔍 Cek';
        if (data.status === 'ok') {
            hasil.innerHTML = '<div style="padding:10px 14px;background:#e8f8f0;color:#155724;border-radius:8px;font-size:13px;border:1px solid #c3e6cb">✅ Model <strong>' + modelId + '</strong> tersedia dan merespons normal.<br><small style="color:#888">Respons: ' + data.preview + '</small></div>';
        } else {
            hasil.innerHTML = '<div style="padding:10px 14px;background:#fff0f0;color:#cc0000;border-radius:8px;font-size:13px;border:1px solid #ffcccc">❌ Model tidak tersedia: ' + data.pesan + '</div>';
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = '🔍 Cek';
        hasil.innerHTML = '<div style="padding:10px 14px;background:#fff0f0;color:#cc0000;border-radius:8px;font-size:13px">❌ Gagal menghubungi server.</div>';
    });
}
</script>

<script>
function bukaTab(id, el) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('aktif'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('aktif'));
    document.getElementById('tab-' + id).classList.add('aktif');
    el.classList.add('aktif');
}

// Buka tab dari URL hash
window.addEventListener('DOMContentLoaded', function() {
    var hash = window.location.hash.replace('#','');
    if (hash) {
        var btn = document.querySelector('[onclick*="' + hash + '"]');
        if (btn) btn.click();
    }
});
</script>

<!-- Modal Edit Siswa -->
<div id="modalEditSiswa" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:10px;padding:28px 32px;width:420px;max-width:95vw;box-shadow:0 8px 32px rgba(0,0,0,0.18)">
        <h3 style="margin:0 0 20px 0;color:#1a2a4a;font-size:16px">✏️ Edit Data Siswa</h3>
        <form method="POST" id="formEditSiswa">
            <input type="hidden" name="aksi" value="edit_siswa">
            <input type="hidden" name="edit_id" id="edit_id">
            <div style="margin-bottom:14px">
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;color:#333">Nama Lengkap <span style="color:red">*</span></label>
                <input type="text" name="edit_nama" id="edit_nama" required
                    style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;box-sizing:border-box">
            </div>
            <div style="margin-bottom:14px">
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;color:#333">NIS <span style="color:red">*</span></label>
                <input type="text" name="edit_nis" id="edit_nis" required
                    style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;box-sizing:border-box">
            </div>
            <div style="margin-bottom:14px">
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;color:#333">Kelas</label>
                <input type="text" name="edit_kelas" id="edit_kelas"
                    style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;box-sizing:border-box">
            </div>
            <div style="margin-bottom:20px">
                <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;color:#333">Nomor WA</label>
                <input type="text" name="edit_wa" id="edit_wa"
                    style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;box-sizing:border-box">
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" onclick="tutupModalEdit()"
                    style="padding:8px 18px;border:1px solid #ddd;border-radius:6px;background:#f5f5f5;cursor:pointer;font-size:13px">
                    Batal
                </button>
                <button type="submit"
                    style="padding:8px 18px;border:none;border-radius:6px;background:#27ae60;color:#fff;cursor:pointer;font-size:13px;font-weight:600">
                    💾 Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function bukaModalEdit(id, nama, nis, kelas, wa) {
    document.getElementById('edit_id').value   = id;
    document.getElementById('edit_nama').value  = nama;
    document.getElementById('edit_nis').value   = nis;
    document.getElementById('edit_kelas').value = kelas;
    document.getElementById('edit_wa').value    = wa;
    var modal = document.getElementById('modalEditSiswa');
    modal.style.display = 'flex';
}
function tutupModalEdit() {
    document.getElementById('modalEditSiswa').style.display = 'none';
}
// Tutup modal jika klik di luar
document.getElementById('modalEditSiswa').addEventListener('click', function(e) {
    if (e.target === this) tutupModalEdit();
});

// Normalisasi input nomor WA di UI
function attachWaInputHandler(el) {
    if (!el) return;
    el.addEventListener('keypress', function(e) {
        if (!/[0-9]/.test(e.key)) e.preventDefault();
    });
    el.addEventListener('input', function() {
        var pos = this.selectionStart;
        var clean = this.value.replace(/[^0-9]/g, '');
        // Validasi awalan: hanya boleh 08 atau 628
        if (clean.length >= 2 && clean.startsWith('6') && !clean.startsWith('62')) {
            clean = '';
        }
        if (clean.length >= 1 && !['0','6'].includes(clean[0])) {
            clean = '';
        }
        this.value = clean;
        this.setSelectionRange(pos, pos);
    });
    el.addEventListener('paste', function(e) {
        e.preventDefault();
        var pasted = (e.clipboardData || window.clipboardData).getData('text');
        var clean  = pasted.replace(/[^0-9]/g, '');
        // Normalisasi awalan
        if (clean.startsWith('0')) {
            clean = '62' + clean.substring(1);
        } else if (clean.startsWith('+62')) {
            clean = '62' + clean.substring(3);
        }
        // Validasi awalan akhir
        if (!clean.startsWith('08') && !clean.startsWith('628')) clean = '';
        this.value = clean;
    });
}

attachWaInputHandler(document.getElementById('edit_wa'));
attachWaInputHandler(document.querySelector('input[name="wa_baru"]'));
</script>
</body>
</html>