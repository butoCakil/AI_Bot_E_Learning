<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

// Akses sederhana via token di URL
// Contoh akses: http://103.67.78.4/dashboard_guru.php?token=smkbansari2024
define('GURU_TOKEN', 'smkbansari2024');

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
    }
}

if (($_GET['token'] ?? '') !== GURU_TOKEN) {
    http_response_code(403);
    die('<h2 style="font-family:sans-serif;text-align:center;margin-top:100px">Akses ditolak.</h2>');
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
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f2f5;
            color: #333;
        }

        .topbar {
            background: #0f3460;
            color: #fff;
            padding: 14px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .topbar h1 {
            font-size: 18px;
            font-weight: 700;
        }

        .topbar span {
            font-size: 13px;
            opacity: 0.7;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 28px 20px;
        }

        /* ── STAT CARDS ── */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border-left: 4px solid #0f3460;
        }

        .stat-card.green {
            border-color: #27ae60;
        }

        .stat-card.orange {
            border-color: #e67e22;
        }

        .stat-card.purple {
            border-color: #8e44ad;
        }

        .stat-label {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a2e;
        }

        .stat-sub {
            font-size: 12px;
            color: #aaa;
            margin-top: 4px;
        }

        /* ── SECTION ── */
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f3460;
            margin-bottom: 14px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e8f0fb;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            margin-bottom: 24px;
        }

        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        @media (max-width: 700px) {
            .two-col {
                grid-template-columns: 1fr;
            }
        }

        /* ── DISTRIBUSI ── */
        .dist-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .dist-label {
            width: 130px;
            font-size: 13px;
            flex-shrink: 0;
        }

        .dist-bar-wrap {
            flex: 1;
            background: #f0f0f0;
            border-radius: 6px;
            height: 20px;
            overflow: hidden;
        }

        .dist-bar {
            height: 100%;
            border-radius: 6px;
            transition: width 0.5s ease;
        }

        .dist-count {
            font-size: 13px;
            font-weight: 700;
            width: 30px;
            text-align: right;
            flex-shrink: 0;
        }

        /* ── TABEL ── */
        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        thead th {
            background: #0f3460;
            color: #fff;
            padding: 10px 14px;
            text-align: left;
            font-weight: 600;
            white-space: nowrap;
        }

        tbody td {
            padding: 10px 14px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* ── BADGES ── */
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            color: #fff;
            white-space: nowrap;
        }

        .tipe-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
        }

        .tipe-login {
            background: #e8f4fd;
            color: #0f3460;
        }

        .tipe-pretest {
            background: #fff3e0;
            color: #e67e22;
        }

        .tipe-buka_materi {
            background: #e8f8e8;
            color: #1a7a1a;
        }

        .tipe-jawab_quiz {
            background: #f3e8ff;
            color: #7b2cbf;
        }

        .tipe-selesai_materi {
            background: #d4edda;
            color: #155724;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #aaa;
            font-size: 14px;
        }

        .refresh-btn {
            float: right;
            padding: 6px 14px;
            background: #0f3460;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
        }

        .refresh-btn:hover {
            background: #16213e;
        }
    </style>
</head>

<body>

    <div class="topbar">
        <h1>Dashboard Guru — AdaptLearn PRE</h1>
        <span>SMK Negeri Bansari | Penerapan Rangkaian Elektronika</span>
    </div>

    <div class="container">

        <!-- STAT CARDS -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-label">Total Siswa</div>
                <div class="stat-value"><?= $total_siswa ?></div>
                <div class="stat-sub">terdaftar di sistem</div>
            </div>
            <div class="stat-card green">
                <div class="stat-label">Pre-Test Selesai</div>
                <div class="stat-value"><?= $total_pretest ?></div>
                <div class="stat-sub">sesi pre-test</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-label">Rata-rata Skor</div>
                <div class="stat-value"><?= $avg_skor ?? '-' ?></div>
                <div class="stat-sub">dari 12 soal</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-label">Total Aktivitas</div>
                <div class="stat-value"><?= $total_aktivitas ?></div>
                <div class="stat-sub">interaksi tercatat</div>
            </div>
        </div>

        <!-- DISTRIBUSI -->
        <div class="two-col">
            <div class="card">
                <div class="section-title">Distribusi Profil Belajar</div>
                <?php if ($distribusi_profil): ?>
                    <?php
                    $max_profil = max(array_column($distribusi_profil, 'jumlah')) ?: 1;
                    foreach ($distribusi_profil as $d):
                        $pct = round(($d['jumlah'] / $max_profil) * 100);
                        $warna = $warna_profil[$d['profil_learning']] ?? '#888';
                        ?>
                        <div class="dist-item">
                            <span class="dist-label"><?= $label_profil[$d['profil_learning']] ?? $d['profil_learning'] ?></span>
                            <div class="dist-bar-wrap">
                                <div class="dist-bar" style="width:<?= $pct ?>%;background:<?= $warna ?>"></div>
                            </div>
                            <span class="dist-count"><?= $d['jumlah'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">Belum ada data pre-test</div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="section-title">Distribusi Level Kemampuan</div>
                <?php if ($distribusi_level): ?>
                    <?php
                    $max_level = max(array_column($distribusi_level, 'jumlah')) ?: 1;
                    foreach ($distribusi_level as $d):
                        $pct = round(($d['jumlah'] / $max_level) * 100);
                        $warna = $warna_level[$d['level_kemampuan']] ?? '#888';
                        ?>
                        <div class="dist-item">
                            <span class="dist-label"><?= $label_level[$d['level_kemampuan']] ?? $d['level_kemampuan'] ?></span>
                            <div class="dist-bar-wrap">
                                <div class="dist-bar" style="width:<?= $pct ?>%;background:<?= $warna ?>"></div>
                            </div>
                            <span class="dist-count"><?= $d['jumlah'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">Belum ada data pre-test</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- PANEL PENGATURAN POST-TEST -->
        <?php
        $pt_aktif = get_pengaturan('posttest_aktif', '0');
        $pt_mulai = get_pengaturan('posttest_mulai', date('Y-m-d'));
        $pt_durasi = get_pengaturan('posttest_durasi_hari', '21');
        $pt_min = get_pengaturan('min_materi_persen', '100');
        ?>
        <div class="card" style="margin-bottom:24px;border-left:4px solid #27ae60">
            <div class="section-title">Pengaturan Post-Test</div>
            <form method="POST"
                style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;align-items:end">
                <input type="hidden" name="aksi" value="setting_posttest">

                <div>
                    <label style="font-size:12px;font-weight:600;color:#444;display:block;margin-bottom:8px">
                        Status Post-Test
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px">
                        <input type="checkbox" name="posttest_aktif" value="1" <?= $pt_aktif === '1' ? 'checked' : '' ?>>
                        Aktifkan akses post-test
                    </label>
                </div>

                <div>
                    <label style="font-size:12px;font-weight:600;color:#444;display:block;margin-bottom:4px">
                        Tanggal Mulai Pembelajaran
                    </label>
                    <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($pt_mulai ?? date('Y-m-d')) ?>"
                        style="width:100%;padding:9px 12px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;outline:none">
                </div>

                <div>
                    <label style="font-size:12px;font-weight:600;color:#444;display:block;margin-bottom:4px">
                        Durasi Pembelajaran (hari)
                    </label>
                    <input type="number" name="durasi_hari" value="<?= htmlspecialchars($pt_durasi) ?>" min="1"
                        max="180"
                        style="width:100%;padding:9px 12px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;outline:none">
                </div>

                <div>
                    <label style="font-size:12px;font-weight:600;color:#444;display:block;margin-bottom:4px">
                        Min. Progress Materi (%)
                    </label>
                    <input type="number" name="min_persen" value="<?= htmlspecialchars($pt_min) ?>" min="1" max="100"
                        style="width:100%;padding:9px 12px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;outline:none">
                </div>

                <div>
                    <button type="submit"
                        style="width:100%;padding:10px;background:#27ae60;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
                        Simpan Pengaturan
                    </button>
                </div>
            </form>

            <div
                style="margin-top:14px;padding:12px;background:#f8f9fa;border-radius:8px;font-size:12px;color:#666;line-height:1.8">
                <strong>Status saat ini:</strong>
                Post-test <strong>
                    <?= $pt_aktif === '1' ? '✓ AKTIF' : '✗ NONAKTIF' ?>
                </strong>
                · Mulai: <strong>
                    <?= $pt_mulai ? date('d/m/Y', strtotime($pt_mulai)) : '-' ?>
                </strong>
                · Durasi: <strong>
                    <?= $pt_durasi ?> hari
                </strong>
                · Min. progress: <strong>
                    <?= $pt_min ?>%
                </strong>
            </div>
        </div>

        <!-- FORM TAMBAH SISWA -->
        <div class="card" style="margin-bottom:24px">
            <div class="section-title">Tambah Akun Siswa</div>
            <?php if ($pesan_akun): ?>
                <div style="padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:13px;
            background:<?= str_starts_with($pesan_akun, '✓') ? '#e8f8f0' : '#fff0f0' ?>;
            color:<?= str_starts_with($pesan_akun, '✓') ? '#155724' : '#cc0000' ?>;
            border:1px solid <?= str_starts_with($pesan_akun, '✓') ? '#c3e6cb' : '#ffcccc' ?>">
                    <?= htmlspecialchars($pesan_akun) ?>
                </div>
            <?php endif; ?>
            <form method="POST"
                style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;align-items:end">
                <input type="hidden" name="aksi" value="tambah">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#444;display:block;margin-bottom:4px">NIS</label>
                    <input type="text" name="nis_baru" placeholder="NIS siswa" required
                        style="width:100%;padding:9px 12px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;outline:none">
                </div>
                <div>
                    <label
                        style="font-size:12px;font-weight:600;color:#444;display:block;margin-bottom:4px">Nama</label>
                    <input type="text" name="nama_baru" placeholder="Nama lengkap" required
                        style="width:100%;padding:9px 12px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;outline:none">
                </div>
                <div>
                    <label
                        style="font-size:12px;font-weight:600;color:#444;display:block;margin-bottom:4px">Kelas</label>
                    <select name="kelas_baru" required
                        style="width:100%;padding:9px 12px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;outline:none">
                        <option value="">— Pilih —</option>
                        <option value="XI TEI">XI TEI</option>
                        <option value="XII TEI">XII TEI</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#444;display:block;margin-bottom:4px">Nomor
                        WA</label>
                    <input type="text" name="wa_baru" placeholder="628xxx" required
                        style="width:100%;padding:9px 12px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;outline:none">
                </div>
                <div>
                    <label
                        style="font-size:12px;font-weight:600;color:#444;display:block;margin-bottom:4px">Password</label>
                    <input type="text" name="pass_baru" placeholder="Password awal" required
                        style="width:100%;padding:9px 12px;border:2px solid #e0e0e0;border-radius:8px;font-size:13px;outline:none">
                </div>
                <div>
                    <button type="submit"
                        style="width:100%;padding:10px;background:#0f3460;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
                        + Tambah Siswa
                    </button>
                </div>
            </form>
        </div>

        <!-- DAFTAR SISWA -->
        <div class="card">
            <div class="section-title">
                Daftar Siswa
                <a href="dashboard_guru.php?token=<?= GURU_TOKEN ?>" class="refresh-btn">Refresh</a>
            </div>
            <?php if ($siswa_list): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>NIS</th>
                                <th>Nama</th>
                                <th>Kelas</th>
                                <th>No. WA</th>
                                <th>Profil Belajar</th>
                                <th>Level</th>
                                <th>Skor</th>
                                <th>Skor Post</th>
                                <th>N-Gain</th>
                                <th>Tgl Pre-Test</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($siswa_list as $i => $s): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars($s['nis'] ?? '-') ?></td>
                                    <td><strong><?= htmlspecialchars($s['nama']) ?></strong></td>
                                    <td><?= htmlspecialchars($s['kelas'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($s['nomor_wa'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($s['profil_learning']): ?>
                                            <span class="badge"
                                                style="background:<?= $warna_profil[$s['profil_learning']] ?? '#888' ?>">
                                                <?= $label_profil[$s['profil_learning']] ?? $s['profil_learning'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#aaa;font-size:12px">Belum pre-test</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($s['level_kemampuan']): ?>
                                            <span class="badge"
                                                style="background:<?= $warna_level[$s['level_kemampuan']] ?? '#888' ?>">
                                                <?= $label_level[$s['level_kemampuan']] ?? $s['level_kemampuan'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#aaa;font-size:12px">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($s['skor_pengetahuan'] !== null): ?>
                                            <strong><?= $s['skor_pengetahuan'] ?></strong>/12
                                        <?php else: ?>
                                            <span style="color:#aaa">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($s['skor_post'] !== null): ?>
                                            <strong>
                                                <?= $s['skor_post'] ?>
                                            </strong>/12
                                        <?php else: ?>
                                            <span style="color:#aaa;font-size:12px">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($s['skor_post'] !== null && $s['skor_pengetahuan'] !== null):
                                            $ng = hitung_ngain((int) $s['skor_pengetahuan'], (int) $s['skor_post']);
                                            $c = $ng['ngain'] > 0.7 ? '#27ae60' : ($ng['ngain'] >= 0.3 ? '#2980b9' : '#e74c3c');
                                            ?>
                                            <span class="badge" style="background:<?= $c ?>">
                                                <?= number_format($ng['ngain'], 2) ?> —
                                                <?= $ng['kategori'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#aaa;font-size:12px">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:12px;color:#888">
                                        <?= $s['tgl_pretest'] ? date('d/m/Y H:i', strtotime($s['tgl_pretest'])) : '-' ?>
                                    </td>
                                    <td style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                                        <form method="POST" style="display:flex;gap:4px;align-items:center">
                                            <input type="hidden" name="aksi" value="reset_password">
                                            <input type="hidden" name="reset_id" value="<?= $s['id'] ?>">
                                            <input type="text" name="new_password" placeholder="Password baru"
                                                style="padding:4px 8px;border:1px solid #ddd;border-radius:6px;font-size:11px;width:100px">
                                            <button type="submit"
                                                style="padding:4px 8px;background:#2980b9;color:#fff;border:none;border-radius:6px;font-size:11px;cursor:pointer">
                                                Reset
                                            </button>
                                        </form>
                                        <form method="POST"
                                            onsubmit="return confirm('Hapus akun <?= htmlspecialchars(addslashes($s['nama'])) ?>?')">
                                            <input type="hidden" name="aksi" value="hapus">
                                            <input type="hidden" name="hapus_id" value="<?= $s['id'] ?>">
                                            <button type="submit"
                                                style="padding:4px 10px;background:#e74c3c;color:#fff;border:none;border-radius:6px;font-size:11px;cursor:pointer">
                                                Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">Belum ada siswa terdaftar</div>
            <?php endif; ?>
        </div>

        <!-- REKAP EVALUASI -->
        <?php if ($rekap_evaluasi): ?>
            <div class="card">
                <div class="section-title">Rekap Skor Evaluasi per Topik</div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Topik</th>
                                <th>Jumlah Quiz Dikerjakan</th>
                                <th>Rata-rata Skor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rekap_evaluasi as $r): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $r['topik']))) ?></strong>
                                    </td>
                                    <td><?= $r['jumlah_quiz'] ?> kali</td>
                                    <td>
                                        <?php
                                        $pct = (float) $r['rata_persen'];
                                        $c = $pct >= 80 ? '#27ae60' : ($pct >= 60 ? '#2980b9' : '#e74c3c');
                                        ?>
                                        <span class="badge" style="background:<?= $c ?>"><?= $pct ?>%</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- ACTIVITY LOG -->
        <div class="card">
            <div class="section-title">Activity Log Terbaru (30 terakhir)</div>
            <?php if ($aktivitas_terbaru): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Siswa</th>
                                <th>Kelas</th>
                                <th>Aktivitas</th>
                                <th>Topik</th>
                                <th>Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aktivitas_terbaru as $a):
                                $detail = json_decode($a['detail'], true) ?? [];
                                ?>
                                <tr>
                                    <td style="font-size:12px;color:#888;white-space:nowrap">
                                        <?= date('d/m H:i', strtotime($a['created_at'])) ?>
                                    </td>
                                    <td><strong><?= htmlspecialchars($a['nama']) ?></strong></td>
                                    <td><?= htmlspecialchars($a['kelas'] ?? '-') ?></td>
                                    <td>
                                        <span class="tipe-badge tipe-<?= $a['tipe'] ?>">
                                            <?= str_replace('_', ' ', strtoupper($a['tipe'])) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $a['topik'] ?? '-'))) ?></td>
                                    <td style="font-size:12px;color:#666">
                                        <?php if (isset($detail['judul'])): ?>
                                            <?= htmlspecialchars(mb_strimwidth($detail['judul'], 0, 40, '...')) ?>
                                        <?php elseif (isset($detail['skor'])): ?>
                                            Skor: <?= $detail['skor'] ?>/<?= $detail['total'] ?? '?' ?>
                                            (<?= $detail['persentase'] ?? '?' ?>%)
                                        <?php elseif (isset($detail['profil_gabungan'])): ?>
                                            <?= htmlspecialchars($detail['profil_gabungan']) ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">Belum ada aktivitas tercatat</div>
            <?php endif; ?>
        </div>

    </div>
</body>

</html>