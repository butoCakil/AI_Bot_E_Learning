<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

session_start();

// Wajib login guru SEBELUM handler aksi apa pun dijalankan
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    header('Location: login_guru.php');
    exit;
}

$pdo = db();

// ── Handler aksi (POST ke halaman sendiri) ───────────────────
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
    } elseif ($_POST['aksi'] === 'hapus' && !empty($_POST['hapus_id'])) {
        hapus_akun_siswa((int) $_POST['hapus_id']);
        $pesan_akun = "✓ Akun siswa berhasil dihapus.";
    } elseif ($_POST['aksi'] === 'edit_siswa' && !empty($_POST['edit_id'])) {
        $id    = (int) $_POST['edit_id'];
        $nama  = trim($_POST['edit_nama'] ?? '');
        $nis   = trim($_POST['edit_nis'] ?? '');
        $kelas = trim($_POST['edit_kelas'] ?? '');
        $wa    = normalisasi_wa(trim($_POST['edit_wa'] ?? ''));
        if ($nama && $nis) {
            $pdo->prepare("UPDATE users SET nama=?, nis=?, kelas=?, nomor_wa=? WHERE id=? AND role='siswa'")
                ->execute([$nama, $nis, $kelas, $wa, $id]);
            $pesan_akun = '✓ Data siswa berhasil diperbarui.';
        } else {
            $pesan_akun = '✗ Nama dan NIS tidak boleh kosong.';
        }
    } elseif ($_POST['aksi'] === 'setting_posttest') {
        $aktif  = isset($_POST['posttest_aktif']) ? '1' : '0';
        $durasi = max(1, (int) $_POST['durasi_hari']);
        $mulai  = $_POST['tgl_mulai'] ?: date('Y-m-d');
        $min    = max(1, min(100, (int) $_POST['min_persen']));

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
    } elseif ($_POST['aksi'] === 'setting_ringkasan_wa') {
        $jam_r = trim($_POST['wa_ringkasan_jam'] ?? '15:30');
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jam_r)) $jam_r = '15:30';
        set_pengaturan('wa_ringkasan_aktif', isset($_POST['wa_ringkasan_aktif']) ? '1' : '0');
        set_pengaturan('wa_ringkasan_jam', $jam_r);
        set_pengaturan('wa_ringkasan_jeda_detik', (string) max(10, min(300, (int) ($_POST['wa_ringkasan_jeda_detik'] ?? 30))));
        $pesan_akun = '✓ Pengaturan ringkasan WA disimpan.';
    } elseif ($_POST['aksi'] === 'paksa_ringkasan_wa') {
        set_pengaturan('wa_ringkasan_paksa', '1');
        $pesan_akun = '✓ Ringkasan WA dijadwalkan — dikirim dalam ±5 menit, satu per satu.';
    } elseif ($_POST['aksi'] === 'setting_wa_gateway') {
        set_pengaturan('wa_bot_nomor', trim($_POST['wa_bot_nomor'] ?? ''));
        set_pengaturan('wa_gateway',   trim($_POST['wa_gateway']   ?? 'fonnte'));
        $pesan_akun = '✓ Konfigurasi WA Gateway berhasil disimpan.';
    }
}

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    header('Location: login_guru.php');
    exit;
}

// ── Data ringkasan ───────────────────────────────────────────
$total_siswa     = $pdo->query("SELECT COUNT(*) FROM users WHERE role='siswa'")->fetchColumn();
$total_pretest   = $pdo->query("SELECT COUNT(*) FROM pre_test_results")->fetchColumn();
$total_aktivitas = $pdo->query("SELECT COUNT(*) FROM activity_log")->fetchColumn();
$avg_skor        = $pdo->query("SELECT ROUND(AVG(skor_pengetahuan),1) FROM pre_test_results")->fetchColumn();

// ── Distribusi profil & level ────────────────────────────────
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

// ── Daftar siswa dengan profil terbaru ───────────────────────
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
    ORDER BY u.nama
")->fetchAll();

// ── Aktivitas terbaru (30) ───────────────────────────────────
$aktivitas_terbaru = $pdo->query("
    SELECT a.tipe, a.topik, a.created_at, a.detail, u.nama, u.kelas
    FROM activity_log a
    JOIN users u ON u.id = a.user_id
    WHERE u.role = 'siswa'
    ORDER BY a.created_at DESC
    LIMIT 30
")->fetchAll();

// ── Rekap skor evaluasi per topik ────────────────────────────
$rekap_evaluasi = $pdo->query("
    SELECT topik,
           COUNT(*) as jumlah_quiz,
           ROUND(AVG(JSON_EXTRACT(detail, '$.persentase')), 1) as rata_persen
    FROM activity_log
    WHERE tipe = 'jawab_quiz'
    GROUP BY topik
")->fetchAll();

// ── Label & warna ────────────────────────────────────────────
$label_profil = [
    'guided_step'       => 'Guided-Step',
    'conceptual'        => 'Conceptual',
    'practice_oriented' => 'Practice-Oriented',
];
$label_level = [
    'beginner'     => 'Pemula',
    'intermediate' => 'Menengah',
    'advanced'     => 'Mahir',
];
$warna_profil = [
    'guided_step'       => '#2563EB',
    'conceptual'        => '#7C3AED',
    'practice_oriented' => '#0EA5A4',
];
$warna_level = [
    'beginner'     => '#94A3B8',
    'intermediate' => '#F59E0B',
    'advanced'     => '#0EA5A4',
];

$page_title = 'Dashboard Guru — AdaptLearn PRE';
$guru_aktif = 'dashboard';
include __DIR__ . '/includes/topbar_guru.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

<div class="crumb">
    <span class="now">Dashboard Guru</span>
    <span class="sep">·</span>
    <span>Halo, <?= htmlspecialchars($_SESSION['guru_nama'] ?? 'Guru') ?></span>
</div>

<?php if ($pesan_akun): ?>
<div class="wrap" style="padding-bottom:0">
    <div class="pt <?= str_starts_with($pesan_akun, '✓') ? 'buka' : 'kunci' ?>" style="padding:13px 16px">
        <div class="pt-ic" style="width:32px;height:32px;font-size:15px">
            <i class="<?= str_starts_with($pesan_akun, '✓') ? 'icon-circle-check' : 'icon-circle-alert' ?>"></i>
        </div>
        <div><b style="margin:0"><?= htmlspecialchars(ltrim($pesan_akun, '✓✗ ')) ?></b></div>
    </div>
</div>
<?php endif; ?>

<!-- TAB NAV -->
<div class="dtab-wrap">
    <div class="dtabs">
        <button class="tab-btn aktif" onclick="bukaTab('ringkasan', this)"><i class="icon-layout-dashboard"></i> Ringkasan</button>
        <button class="tab-btn" onclick="bukaTab('siswa', this)"><i class="icon-users"></i> Siswa</button>
        <button class="tab-btn" onclick="bukaTab('penilaian', this)"><i class="icon-clipboard-check"></i> Penilaian</button>
        <a href="rekap_nilai.php" class="tab-btn" style="text-decoration:none"><i class="icon-clipboard-list"></i> Rekap Nilai</a>
        <button class="tab-btn" onclick="bukaTab('materi', this)"><i class="icon-folder-tree"></i> Materi</button>
        <button class="tab-btn" onclick="bukaTab('pengaturan', this)"><i class="icon-target"></i> Post-Test</button>
        <button class="tab-btn" onclick="bukaTab('aktivitas', this)"><i class="icon-activity"></i> Aktivitas</button>
        <button class="tab-btn" onclick="bukaTab('wabot', this)"><i class="icon-bot"></i> WA Bot &amp; AI</button>
    </div>
</div>

<div class="dwrap">

<!-- ═══════════ TAB 1: RINGKASAN ═══════════ -->
<div id="tab-ringkasan" class="tab-panel aktif">

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-ic" style="background:var(--biru-muda);color:var(--biru)"><i class="icon-users"></i></div>
            <div><div class="stat-num"><?= $total_siswa ?></div><div class="stat-lbl">Total siswa</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-ic" style="background:var(--teal-muda);color:var(--teal)"><i class="icon-clipboard-check"></i></div>
            <div><div class="stat-num"><?= $total_pretest ?></div><div class="stat-lbl">Pre-test terisi</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-ic" style="background:var(--amber-muda);color:var(--amber)"><i class="icon-activity"></i></div>
            <div><div class="stat-num"><?= $total_aktivitas ?></div><div class="stat-lbl">Total aktivitas</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-ic" style="background:var(--ungu-muda);color:var(--ungu)"><i class="icon-star"></i></div>
            <div><div class="stat-num"><?= $avg_skor !== null ? $avg_skor : '—' ?></div><div class="stat-lbl">Rata-rata skor pre</div></div>
        </div>
    </div>

    <div class="dgrid2">
        <div class="card">
            <div class="card-h" style="margin-bottom:14px"><h3><i class="icon-chart-pie"></i> Distribusi profil belajar</h3></div>
            <?php if ($distribusi_profil): ?>
                <?php $maks_p = max(array_map(fn($d)=>(int)$d['jumlah'], $distribusi_profil)); ?>
                <div style="display:flex;flex-direction:column;gap:11px">
                    <?php foreach ($distribusi_profil as $d): $pct = $maks_p>0 ? round($d['jumlah']/$maks_p*100) : 0; ?>
                        <div>
                            <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:600;margin-bottom:5px">
                                <span><?= $label_profil[$d['profil_learning']] ?? $d['profil_learning'] ?></span>
                                <span style="color:var(--biru);font-weight:800"><?= $d['jumlah'] ?> siswa</span>
                            </div>
                            <div class="bar tipis"><i style="width:<?= $pct ?>%;background:<?= $warna_profil[$d['profil_learning']] ?? 'var(--biru)' ?>"></i></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="kosong" style="padding:24px"><i class="icon-chart-pie"></i><p>Belum ada data pre-test.</p></div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-h" style="margin-bottom:14px"><h3><i class="icon-signal"></i> Distribusi level kemampuan</h3></div>
            <?php if ($distribusi_level): ?>
                <?php $maks_l = max(array_map(fn($d)=>(int)$d['jumlah'], $distribusi_level)); ?>
                <div style="display:flex;flex-direction:column;gap:11px">
                    <?php foreach ($distribusi_level as $d): $pct = $maks_l>0 ? round($d['jumlah']/$maks_l*100) : 0; ?>
                        <div>
                            <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:600;margin-bottom:5px">
                                <span><?= $label_level[$d['level_kemampuan']] ?? $d['level_kemampuan'] ?></span>
                                <span style="color:var(--teal);font-weight:800"><?= $d['jumlah'] ?> siswa</span>
                            </div>
                            <div class="bar tipis"><i class="done" style="width:<?= $pct ?>%;background:<?= $warna_level[$d['level_kemampuan']] ?? 'var(--teal)' ?>"></i></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="kosong" style="padding:24px"><i class="icon-signal"></i><p>Belum ada data level.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- REKAP SKOR EVALUASI PER TOPIK -->
    <div class="card">
        <div class="card-h" style="margin-bottom:14px"><h3><i class="icon-clipboard-list"></i> Rekap skor evaluasi per topik</h3></div>
        <?php if ($rekap_evaluasi): ?>
        <div style="overflow-x:auto">
            <table class="gtable">
                <thead><tr><th>Topik</th><th style="text-align:center">Jumlah Quiz</th><th style="text-align:center">Rata-rata Skor</th></tr></thead>
                <tbody>
                <?php foreach ($rekap_evaluasi as $r): ?>
                    <?php $pct = (float)($r['rata_persen'] ?? 0); ?>
                    <tr>
                        <td><strong><?= htmlspecialchars(ucwords(str_replace('_',' ',$r['topik'] ?? '-'))) ?></strong></td>
                        <td style="text-align:center"><?= (int)$r['jumlah_quiz'] ?> kali</td>
                        <td style="text-align:center">
                            <span class="dbadge" style="background:<?= $pct>=80?'var(--teal)':($pct>=50?'var(--amber)':'var(--coral)') ?>"><?= $pct ?>%</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="kosong" style="padding:24px"><i class="icon-clipboard-list"></i><p>Belum ada evaluasi yang dikerjakan siswa.</p></div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════ TAB 2: SISWA ═══════════ -->
<div id="tab-siswa" class="tab-panel">

    <!-- TAMBAH SISWA -->
    <div class="card" style="margin-bottom:16px">
        <div class="card-h" style="margin-bottom:14px"><h3><i class="icon-user-plus"></i> Tambah akun siswa</h3></div>
        <form method="POST">
            <input type="hidden" name="aksi" value="tambah">
            <div class="dform-grid">
                <div class="fg"><label>NIS</label><input type="text" name="nis_baru" placeholder="NIS siswa" required></div>
                <div class="fg"><label>Nama Lengkap</label><input type="text" name="nama_baru" placeholder="Nama lengkap" required></div>
                <div class="fg">
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
                <div class="fg"><label>Nomor WA</label><input type="text" name="wa_baru" placeholder="628xxx"></div>
                <div class="fg"><label>Password Awal</label><input type="text" name="pass_baru" placeholder="Password awal" required></div>
                <div class="fg" style="display:flex;align-items:flex-end">
                    <button type="submit" class="btn btn-3 btn-full"><i class="icon-user-plus"></i> Tambah</button>
                </div>
            </div>
        </form>
    </div>

    <!-- IMPORT EXCEL -->
    <div class="card" style="margin-bottom:16px">
        <div class="card-h" style="margin-bottom:14px"><h3><i class="icon-file-spreadsheet"></i> Import siswa dari Excel</h3></div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
            <a href="/api/template_siswa.php" target="_blank" class="btn btn-2 btn-sm"><i class="icon-download"></i> Download template</a>
            <span style="font-size:12px;color:var(--abu-muda)">Download → isi data → upload di bawah</span>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <input type="file" id="file_import" accept=".xlsx,.xls"
                   style="flex:1;min-width:200px;padding:9px 12px;border:2px solid var(--garis);border-radius:var(--r-sm);font-size:13px;font-family:inherit">
            <button onclick="importSiswa()" class="btn btn-1 btn-sm" style="white-space:nowrap"><i class="icon-upload"></i> Upload &amp; Import</button>
        </div>
        <div id="hasil_import" style="margin-top:14px;display:none"></div>
    </div>

    <!-- DAFTAR SISWA -->
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px">
            <h3 style="font-size:14.5px;font-weight:800;display:flex;align-items:center;gap:7px;margin:0"><i class="icon-users" style="color:var(--teal)"></i> Daftar siswa (<?= count($siswa_list) ?>)</h3>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <select id="filter_kelas" class="dfilter">
                    <option value="">Semua Kelas</option>
                    <?php foreach(['XI TE 1','XI TE 2','XI TE 3','XI TE 4','XII TE 1','XII TE 2','XII TE 3','XII TE 4'] as $k): ?>
                    <option value="<?= $k ?>"><?= $k ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filter_profil" class="dfilter">
                    <option value="">Semua Profil</option>
                    <option value="guided">Guided-Step</option>
                    <option value="conceptual">Conceptual</option>
                    <option value="practice">Practice-Oriented</option>
                </select>
                <a href="api/ekspor_ngain.php" class="btn btn-3 btn-sm" style="white-space:nowrap"><i class="icon-download"></i> Ekspor CSV</a>
            </div>
        </div>
        <div style="overflow-x:auto">
        <table id="tabel_siswa" style="width:100%" class="gtable">
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
                <td><code><?= htmlspecialchars($s['nis'] ?? '-') ?></code></td>
                <td><strong><?= htmlspecialchars($s['nama']) ?></strong></td>
                <td><?= htmlspecialchars($s['kelas'] ?? '-') ?></td>
                <td>
                    <?php if ($s['profil_learning']): ?>
                    <span class="dbadge" style="background:<?= $warna_profil[$s['profil_learning']] ?? '#888' ?>">
                        <?= $label_profil[$s['profil_learning']] ?? $s['profil_learning'] ?>
                    </span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                    <?php if ($s['level_kemampuan']): ?>
                    <span class="dbadge" style="background:<?= $warna_level[$s['level_kemampuan']] ?? '#888' ?>">
                        <?= $label_level[$s['level_kemampuan']] ?? $s['level_kemampuan'] ?>
                    </span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td><?= $s['skor_pengetahuan'] !== null ? $s['skor_pengetahuan'].'/12' : '-' ?></td>
                <td><?= $s['skor_post'] !== null ? $s['skor_post'].'/12' : '-' ?></td>
                <td>
                    <?php if ($ngain): ?>
                    <span class="dbadge" style="background:<?= $ngain['warna'] ?>;white-space:nowrap">
                        <?= number_format($ngain['ngain'],2) ?> — <?= $ngain['kategori'] ?>
                    </span>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td style="font-size:12px;color:var(--abu-muda);white-space:nowrap"><?= $s['tgl_pretest'] ? date('d/m/Y', strtotime($s['tgl_pretest'])) : '-' ?></td>
                <td>
                    <div style="display:flex;flex-direction:column;gap:4px;min-width:150px">
                        <button type="button" class="btn btn-sm" style="background:var(--biru);color:#fff"
                            onclick="bukaModalEdit(<?= $s['id'] ?>, '<?= htmlspecialchars($s['nama'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['nis'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['kelas'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['nomor_wa'] ?? '', ENT_QUOTES) ?>')">
                            <i class="icon-pencil"></i> Edit
                        </button>
                        <form method="POST" style="display:flex;gap:4px">
                            <input type="hidden" name="aksi" value="reset_password">
                            <input type="hidden" name="reset_id" value="<?= $s['id'] ?>">
                            <input type="text" name="new_password" placeholder="Pass baru" style="width:82px;padding:5px 7px;font-size:11px;border:1px solid var(--garis);border-radius:6px;font-family:inherit">
                            <button type="submit" class="btn btn-2 btn-sm" style="padding:5px 10px">Reset</button>
                        </form>
                        <form method="POST" onsubmit="return confirm('Hapus akun <?= htmlspecialchars($s['nama'], ENT_QUOTES) ?>?')">
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="hapus_id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn btn-sm" style="background:var(--coral-muda);color:var(--coral);width:100%"><i class="icon-trash-2"></i> Hapus</button>
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

<!-- ═══════════ TAB 3: PENILAIAN JOBSHEET ═══════════ -->
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

    // ── Data rekap nilai seluruh item (evaluasi + jobsheet) ──
    // Catatan: tidak JOIN ke tabel topik karena collation kolom berbeda.
    $nama_topik_map = get_topik_list();
    $item_nilai = $pdo->query("
        SELECT c.id, c.judul, c.tipe, c.topik
        FROM content c
        WHERE c.aktif = 1 AND c.tipe IN ('evaluasi','jobsheet')
        ORDER BY c.topik, c.urutan_default, c.id
    ")->fetchAll();
    foreach ($item_nilai as $i => $it) {
        $item_nilai[$i]['topik_nama'] = $nama_topik_map[$it['topik']] ?? $it['topik'];
    }

    $nilai_eval = [];
    foreach ($pdo->query("SELECT user_id, content_id, persentase FROM evaluasi_results")->fetchAll() as $r) {
        $nilai_eval[$r['user_id']][$r['content_id']] = (int) $r['persentase'];
    }

    $job_ada = [];
    $nilai_job = [];
    foreach ($pdo->query("SELECT user_id, content_id, nilai FROM jobsheet_submissions")->fetchAll() as $r) {
        $job_ada[$r['user_id']][$r['content_id']] = true;
        if ($r['nilai'] !== null) $nilai_job[$r['user_id']][$r['content_id']] = (float) $r['nilai'];
    }

    $rekap_siswa = $pdo->query("
        SELECT u.id, u.nama, u.nis, u.kelas,
               p.skor_pengetahuan AS skor_pre,
               pt.skor_pengetahuan AS skor_post
        FROM users u
        LEFT JOIN pre_test_results p ON p.id = (
            SELECT id FROM pre_test_results WHERE user_id = u.id ORDER BY id DESC LIMIT 1)
        LEFT JOIN post_test_results pt ON pt.id = (
            SELECT id FROM post_test_results WHERE user_id = u.id ORDER BY id DESC LIMIT 1)
        WHERE u.role = 'siswa'
        ORDER BY u.kelas, u.nama
    ")->fetchAll();
    ?>

    <div class="cta" style="margin-bottom:16px">
        <a href="rekap_nilai.php" class="btn btn-3"><i class="icon-clipboard-list"></i> Lihat Rekap Nilai Seluruh Item</a>
    </div>

    <?php
    // ── Penilaian per siswa (ringkasan kelas + daftar + modal) ──
    require_once __DIR__ . '/includes/syarat_konten.php';
    require_once __DIR__ . '/includes/feedback.php';
    require_once __DIR__ . '/includes/rekap_tugas.php';
    if (!function_exists('pn_persen')) {
        function pn_persen($a, $b) { return $b > 0 ? (int) round($a / $b * 100) : 0; }
    }
    if (!function_exists('pn_badges')) {
        function pn_badges(array $r): string {
            $b = function ($cls, $t) { return '<span class="pn-badge ' . $cls . '">' . htmlspecialchars($t) . '</span>'; };
            if (!$r['profil'])              return $b('pn-w-abu', 'Belum pre-test');
            if (!$r['tugas_total'])         return $b('pn-w-abu', 'Tidak ada tugas');
            if ($r['status'] === 'lengkap') return $b('pn-w-teal', 'Lengkap');
            $h = '';
            if ($r['perlu_nilai']) $h .= $b('pn-w-amber', $r['perlu_nilai'] . ' perlu dinilai');
            if ($r['revisi'])      $h .= $b('pn-w-ungu', $r['revisi'] . ' menunggu revisi');
            if ($r['belum'])       $h .= $b('pn-w-abu', $r['belum'] . ' belum dikumpulkan');
            if ($r['dinilai'])     $h .= $b('pn-w-teal', $r['dinilai'] . ' dinilai');
            return $h;
        }
    }
    $pn_siswa = $pdo->query("SELECT id, nama, nis, kelas FROM users WHERE role = 'siswa' ORDER BY kelas, nama")->fetchAll();
    $pn_rekap = rt_rekap($pn_siswa);
    $pn_kelas = [];
    foreach ($pn_siswa as $s) {
        $r = $pn_rekap[(int) $s['id']];
        foreach ([($s['kelas'] ?: '-'), '__semua'] as $kk) {
            $a = $pn_kelas[$kk] ?? ['siswa' => 0, 'pretest' => 0, 'm_s' => 0, 'm_t' => 0, 'k' => 0, 't' => 0, 'n' => 0, 'p' => 0, 'r' => 0];
            $a['siswa']++;
            if ($r['profil']) $a['pretest']++;
            $a['m_s'] += $r['materi_selesai']; $a['m_t'] += $r['materi_total'];
            $a['k']   += $r['terkumpul'];      $a['t']   += $r['tugas_total'];
            $a['n']   += $r['dinilai'];        $a['p']   += $r['perlu_nilai'];
            $a['r']   += $r['revisi'];
            $pn_kelas[$kk] = $a;
        }
    }
    $pn_semua = $pn_kelas['__semua'] ?? null;
    unset($pn_kelas['__semua']);
    ksort($pn_kelas);
    $pn_kelas_awal = array_key_first($pn_kelas);
    $pn_kartu = $pn_semua ? (['Semua kelas' => $pn_semua] + $pn_kelas) : $pn_kelas;
    $pn_warna = ['perlu_dinilai' => 'pn-w-amber', 'menunggu_revisi' => 'pn-w-ungu', 'belum_lengkap' => 'pn-w-biru', 'lengkap' => 'pn-w-teal'];
    ?>

    <div class="pn-ring">
        <?php foreach ($pn_kartu as $nm => $a):
            $semua = ($nm === 'Semua kelas' && $pn_semua === $a);
            $pm = pn_persen($a['m_s'], $a['m_t']);
            $pk = pn_persen($a['k'], $a['t']);
            $pd = pn_persen($a['n'], $a['n'] + $a['p']);
        ?>
            <<?= $semua ? 'div class="pn-kartu semua"' : 'button type="button" class="pn-kartu" data-kelas="' . htmlspecialchars($nm) . '" onclick="pnPilihKelas(this.dataset.kelas)"' ?>>
                <h4><?= htmlspecialchars($nm) ?></h4>
                <div class="pn-muted"><?= $a['siswa'] ?> siswa<?= $a['siswa'] - $a['pretest'] > 0 ? ' · ' . ($a['siswa'] - $a['pretest']) . ' belum pre-test' : '' ?></div>
                <div class="pn-bar"><span>Materi</span><i><em style="width:<?= $pm ?>%"></em></i><b><?= $pm ?>%</b></div>
                <div class="pn-bar"><span>Pengumpulan</span><i><em style="width:<?= $pk ?>%"></em></i><b><?= $pk ?>%</b></div>
                <div class="pn-bar"><span>Dinilai</span><i><em style="width:<?= $pd ?>%"></em></i><b><?= $pd ?>%</b></div>
                <?php if ($a['p'] > 0 || $a['r'] > 0): ?>
                    <div style="margin-top:6px;display:flex;gap:4px;flex-wrap:wrap">
                        <?php if ($a['p'] > 0): ?><span class="pn-badge pn-w-amber"><?= $a['p'] ?> perlu dinilai</span><?php endif; ?>
                        <?php if ($a['r'] > 0): ?><span class="pn-badge pn-w-ungu"><?= $a['r'] ?> menunggu revisi</span><?php endif; ?>
                    </div>
                <?php endif; ?>
            </<?= $semua ? 'div' : 'button' ?>>
        <?php endforeach; ?>
    </div>

    <div class="card" id="pn-daftar">
        <div class="card-h" style="margin-bottom:12px"><h3><i class="icon-clipboard-check"></i> Penilaian per siswa</h3></div>
        <div class="pn-filter">
            <select id="pn-kelas">
                <?php foreach (array_keys($pn_kelas) as $kl): ?>
                    <option value="<?= htmlspecialchars($kl) ?>" <?= $kl === $pn_kelas_awal ? 'selected' : '' ?>><?= htmlspecialchars($kl) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="pn-status">
                <option value="">Semua status</option>
                <option value="perlu_dinilai" selected>Perlu dinilai</option>
                <option value="menunggu_revisi">Menunggu revisi</option>
                <option value="belum_kumpul">Ada yang belum dikumpulkan</option>
                <option value="dinilai">Ada yang sudah dinilai</option>
                <option value="lengkap">Lengkap semua</option>
                <option value="belum_pretest">Belum pre-test</option>
            </select>
            <input id="pn-cari" type="search" placeholder="Cari nama atau NIS" style="flex:1;min-width:160px">
        </div>
        <div style="overflow-x:auto">
            <table class="gtable">
                <thead><tr><th>Siswa</th><th>Materi</th><th>Dikumpul</th><th>Dinilai</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($pn_siswa as $s):
                    $uid = (int) $s['id'];
                    $r   = $pn_rekap[$uid];
                    $jn  = $r['dinilai'] + $r['perlu_nilai'];
                    $wr  = $pn_warna[$r['status']] ?? 'pn-w-abu';
                ?>
                    <tr class="pn-baris" data-id="<?= $uid ?>" data-kelas="<?= htmlspecialchars($s['kelas'] ?: '-') ?>"
                        data-tag="<?= htmlspecialchars(implode(' ', $r['tag'])) ?>" data-cari="<?= htmlspecialchars(mb_strtolower($s['nama'] . ' ' . $s['nis'])) ?>"
                        onclick="pnBuka(<?= $uid ?>)">
                        <td><strong><?= htmlspecialchars($s['nama']) ?></strong><br><small class="pn-muted"><?= htmlspecialchars($s['nis']) ?></small></td>
                        <td id="pn-m-<?= $uid ?>"><?= $r['materi_total'] ? $r['materi_selesai'] . '/' . $r['materi_total'] . ' <small class="pn-muted">(' . pn_persen($r['materi_selesai'], $r['materi_total']) . '%)</small>' : '–' ?></td>
                        <td id="pn-k-<?= $uid ?>"><?= $r['tugas_total'] ? $r['terkumpul'] . '/' . $r['tugas_total'] : '–' ?></td>
                        <td id="pn-n-<?= $uid ?>"><?= $jn ? $r['dinilai'] . '/' . $jn : '–' ?></td>
                        <td id="pn-s-<?= $uid ?>"><?= pn_badges($r) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="pn-kosong" class="kosong" style="padding:26px;display:none">
            <b>Tidak ada siswa dengan status ini</b>
            <p><a href="javascript:void(0)" onclick="pnSemuaStatus()" class="pn-link">Tampilkan semua status</a></p>
        </div>
    </div>

    <div id="pn-modal" class="pn-bg" onclick="if (event.target === this) pnTutup()">
        <div class="pn-kotak" role="dialog" aria-modal="true">
            <div class="pn-h">
                <div><b id="pn-nama" style="font-size:15.5px"></b> <small id="pn-sub" class="pn-muted"></small></div>
                <div class="pn-nav">
                    <button type="button" id="pn-prev" title="Siswa sebelumnya">&lsaquo;</button>
                    <button type="button" id="pn-next" title="Siswa berikutnya">&rsaquo;</button>
                    <button type="button" onclick="pnTutup()" title="Tutup">&times;</button>
                </div>
            </div>
            <div id="pn-ringkas" class="pn-ringkas"></div>
            <div id="pn-isi"></div>
        </div>
    </div>

    <style>
        .pn-ring { display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); gap:10px; margin-bottom:14px; }
        .pn-kartu { text-align:left; background:#fff; border:2px solid var(--garis, #E5E7EB); border-radius:12px; padding:12px 14px; font:inherit; cursor:pointer; }
        .pn-kartu.semua { cursor:default; background:var(--biru-muda, #EFF6FF); }
        .pn-kartu.on { border-color:var(--teal, #0D9488); }
        .pn-kartu h4 { margin:0 0 2px; font-size:13.5px; }
        .pn-bar { display:grid; grid-template-columns:84px 1fr 38px; align-items:center; gap:6px; font-size:11.5px; margin-top:6px; }
        .pn-bar i { display:block; height:6px; background:var(--garis, #E5E7EB); border-radius:3px; overflow:hidden; }
        .pn-bar em { display:block; height:100%; background:var(--teal, #0D9488); }
        .pn-bar b { text-align:right; }
        .pn-filter { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px; }
        .pn-filter select, .pn-filter input { padding:7px 10px; font-size:13px; border:2px solid var(--garis, #E5E7EB); border-radius:8px; font-family:inherit; }
        .pn-baris { cursor:pointer; }
        .pn-baris:hover td { background:var(--biru-muda, #EFF6FF); }
        .pn-badge { display:inline-block; font-size:11.5px; font-weight:700; padding:2px 9px; border-radius:999px; white-space:nowrap; }
        .pn-w-amber { background:#FEF3C7; color:#B45309; } .pn-w-teal { background:#CCFBF1; color:#0F766E; }
        .pn-w-biru { background:#DBEAFE; color:#1D4ED8; }  .pn-w-abu { background:#F1F5F9; color:#475569; }
        .pn-w-ungu { background:#EDE9FE; color:#6D28D9; }
        .pn-baris td .pn-badge, .pn-ringkas .pn-badge { margin:2px 4px 2px 0; }
        .pn-muted { color:var(--abu-muda, #94A3B8); font-size:12.5px; }
        .pn-link { color:var(--biru, #2563EB); font-weight:700; }
        .pn-bg { display:none; position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:999; align-items:flex-start; justify-content:center; padding:4vh 12px; overflow-y:auto; }
        .pn-bg.on { display:flex; }
        .pn-kotak { background:#fff; border-radius:14px; width:100%; max-width:760px; padding:18px 20px; }
        .pn-h { display:flex; align-items:center; gap:10px; }
        .pn-nav { margin-left:auto; display:flex; gap:6px; }
        .pn-nav button { width:34px; height:34px; border:2px solid var(--garis, #E5E7EB); background:#fff; border-radius:8px; cursor:pointer; font-size:18px; line-height:1; }
        .pn-ringkas { display:flex; flex-wrap:wrap; gap:6px 14px; align-items:center; font-size:12.5px; padding:10px 0 12px; border-bottom:1px solid var(--garis, #E5E7EB); margin-bottom:12px; }
        .pn-topik { margin-bottom:14px; }
        .pn-topik-h { display:flex; flex-wrap:wrap; gap:8px; align-items:center; font-size:13.5px; margin-bottom:6px; }
        .pn-eval { font-size:11.5px; color:#475569; background:#F1F5F9; padding:2px 8px; border-radius:999px; }
        .pn-tugas { border:1px solid var(--garis, #E5E7EB); border-radius:10px; padding:9px 12px; margin-bottom:6px; }
        .pn-tugas summary { cursor:pointer; font-size:13px; }
        .pn-tugas summary .pn-badge { margin-left:8px; }
        .pn-tugas-isi { margin-top:10px; font-size:13px; }
        .pn-tabel-isi { overflow-x:auto; margin-top:8px; }
        .pn-tabel-isi table { font-size:12.5px; }
        .pn-tabel-isi th, .pn-tabel-isi td { border:1px solid var(--garis, #E5E7EB); padding:5px 8px; text-align:left; }
        .pn-tabel-isi th { background:#EFF6FF; }
        .pn-fb { margin-top:12px; padding-top:10px; border-top:1px dashed var(--garis, #E5E7EB); }
        .pn-pred { display:flex; align-items:center; gap:4px; flex-wrap:wrap; margin-bottom:8px; }
        .pn-star { border:0; background:none; font-size:24px; line-height:1; color:#CBD5E1; cursor:pointer; padding:0 2px; }
        .pn-star.on { color:#F59E0B; }
        .pn-pred-lbl { margin-left:8px; font-size:12.5px; font-weight:700; }
        .pn-pill { border:2px solid var(--garis, #E5E7EB); background:#fff; border-radius:999px; padding:5px 12px; font:inherit; font-size:12.5px; cursor:pointer; }
        .pn-pill.on { border-color:var(--teal, #0D9488); background:#CCFBF1; color:#0F766E; font-weight:700; }
        .pn-kom { width:100%; box-sizing:border-box; padding:8px 10px; border:2px solid var(--garis, #E5E7EB); border-radius:8px; font:inherit; font-size:13px; resize:vertical; }
        .pn-cek { display:flex; align-items:center; gap:6px; font-size:12.5px; margin:6px 0; cursor:pointer; }
        .pn-aksi { display:flex; gap:6px; align-items:center; flex-wrap:wrap; margin-top:6px; }
        .pn-aksi input[type=number] { width:84px; padding:6px 8px; border:2px solid var(--garis, #E5E7EB); border-radius:6px; font-family:inherit; }
        .pn-catatan { font-size:12.5px; padding:6px 10px; border-radius:8px; margin-bottom:8px; }
        .pn-cek input:disabled { cursor:not-allowed; }
        .pn-cek.pn-cek-on { color:#6D28D9; font-weight:700; }
    </style>

    <script>
    (function () {
        var LABEL_TUGAS = { belum_kumpul: 'Belum dikumpulkan', sebagian: 'Sebagian', perlu_dinilai: 'Perlu dinilai',
                            menunggu_revisi: 'Menunggu revisi', dinilai: 'Dinilai' };
        var WARNA = { perlu_dinilai: 'pn-w-amber', menunggu_revisi: 'pn-w-ungu', belum_lengkap: 'pn-w-biru',
                      lengkap: 'pn-w-teal', sebagian: 'pn-w-biru', dinilai: 'pn-w-teal' };
        var SKALA = {
            bintang:    ['Perlu diulang', 'Perlu perbaikan', 'Cukup', 'Baik', 'Sangat baik'],
            kesesuaian: ['Belum sesuai', 'Hampir sesuai', 'Sesuai']
        };
        var selK = document.getElementById('pn-kelas');
        var selS = document.getElementById('pn-status');
        var cari = document.getElementById('pn-cari');
        var modal = document.getElementById('pn-modal');
        var isi = document.getElementById('pn-isi');
        var ringkas = document.getElementById('pn-ringkas');
        var aktifId = null;

        function el(tag, cls, txt) {
            var e = document.createElement(tag);
            if (cls) e.className = cls;
            if (txt !== undefined && txt !== null) e.textContent = txt;
            return e;
        }
        function badge(kode, teks) { return el('span', 'pn-badge ' + (WARNA[kode] || 'pn-w-abu'), teks); }
        function fmt(n) { return String(Math.round(n * 10) / 10).replace('.', ','); }
        function persen(a, b) { return b > 0 ? Math.round(a / b * 100) : 0; }
        function badgesSiswa(r) {
            if (!r.profil)              return [badge('abu', 'Belum pre-test')];
            if (!r.tugas_total)         return [badge('abu', 'Tidak ada tugas')];
            if (r.status === 'lengkap') return [badge('lengkap', 'Lengkap')];
            var out = [];
            if (r.perlu_nilai) out.push(badge('perlu_dinilai', r.perlu_nilai + ' perlu dinilai'));
            if (r.revisi)      out.push(badge('menunggu_revisi', r.revisi + ' menunggu revisi'));
            if (r.belum)       out.push(badge('abu', r.belum + ' belum dikumpulkan'));
            if (r.dinilai)     out.push(badge('dinilai', r.dinilai + ' dinilai'));
            return out;
        }
        function baris() { return Array.prototype.slice.call(document.querySelectorAll('.pn-baris')); }
        function terlihat() { return baris().filter(function (tr) { return tr.style.display !== 'none'; }); }

        function saring() {
            var k = selK.value, st = selS.value, q = cari.value.trim().toLowerCase(), n = 0;
            baris().forEach(function (tr) {
                var ok = tr.dataset.kelas === k && (!st || (' ' + tr.dataset.tag + ' ').indexOf(' ' + st + ' ') >= 0) && (!q || tr.dataset.cari.indexOf(q) >= 0);
                tr.style.display = ok ? '' : 'none';
                if (ok) n++;
            });
            document.getElementById('pn-kosong').style.display = n ? 'none' : '';
            document.querySelectorAll('.pn-kartu[data-kelas]').forEach(function (c) {
                c.classList.toggle('on', c.dataset.kelas === k);
            });
        }
        window.pnPilihKelas = function (k) {
            selK.value = k;
            saring();
            document.getElementById('pn-daftar').scrollIntoView({ behavior: 'smooth', block: 'start' });
        };
        window.pnSemuaStatus = function () { selS.value = ''; saring(); };
        selK.addEventListener('change', saring);
        selS.addEventListener('change', saring);
        cari.addEventListener('input', saring);

        window.pnBuka = function (id) {
            aktifId = id;
            modal.classList.add('on');
            document.body.style.overflow = 'hidden';
            muat(id);
        };
        window.pnTutup = function () {
            modal.classList.remove('on');
            document.body.style.overflow = '';
            aktifId = null;
        };
        function geser(d) {
            var v = terlihat();
            var i = v.findIndex(function (tr) { return +tr.dataset.id === aktifId; });
            var t = v[i + d];
            if (t) pnBuka(+t.dataset.id);
        }
        document.getElementById('pn-prev').addEventListener('click', function () { geser(-1); });
        document.getElementById('pn-next').addEventListener('click', function () { geser(1); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('on')) pnTutup();
        });

        function muat(id) {
            isi.innerHTML = '';
            isi.appendChild(el('p', 'pn-muted', 'Memuat…'));
            fetch('/api/penilaian_siswa.php?id=' + id)
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (id !== aktifId) return;
                    if (!d.ok) { isi.textContent = d.msg || 'Gagal memuat data.'; return; }
                    render(d);
                    perbaruiBaris(d.siswa.id, d.rekap);
                })
                .catch(function () { if (id === aktifId) isi.textContent = 'Koneksi gagal, coba lagi.'; });
        }

        function perbaruiBaris(id, r) {
            var tr = document.querySelector('.pn-baris[data-id="' + id + '"]');
            if (!tr) return;
            var jn = r.dinilai + r.perlu_nilai;
            tr.dataset.tag = (r.tag || []).join(' ');
            document.getElementById('pn-k-' + id).textContent = r.tugas_total ? r.terkumpul + '/' + r.tugas_total : '–';
            document.getElementById('pn-n-' + id).textContent = jn ? r.dinilai + '/' + jn : '–';
            var s = document.getElementById('pn-s-' + id);
            s.innerHTML = '';
            badgesSiswa(r).forEach(function (x) { s.appendChild(x); });
        }

        function labelTugas(tg) {
            var fb = tg.feedback;
            if (tg.status === 'dinilai') {
                var t = fb ? fb.label : 'Dinilai';
                if (tg.upload && tg.upload.nilai !== null) t += ' · ' + fmt(tg.upload.nilai);
                return t;
            }
            if (tg.status === 'perlu_dinilai' && fb && fb.revisi_ke > 0) return 'Revisi masuk';
            return LABEL_TUGAS[tg.status] || tg.status;
        }

        function formFeedback(tg, uid) {
            var fb      = tg.feedback;
            var lbl     = SKALA[tg.skala];
            var bintang = tg.skala === 'bintang';
            var pakaiNilai = bintang && !!tg.upload;
            var pilih   = (fb && fb.skala === tg.skala) ? fb.predikat : 0;
            var wrap    = el('div', 'pn-fb');

            if (tg.status === 'menunggu_revisi') {
                wrap.appendChild(el('div', 'pn-catatan pn-w-ungu', 'Menunggu siswa mengirim revisi. Feedback masih bisa diubah.'));
            } else if (tg.status === 'perlu_dinilai' && fb && fb.revisi_ke > 0) {
                wrap.appendChild(el('div', 'pn-catatan pn-w-amber', 'Revisi ke-' + fb.revisi_ke + ' masuk — beri penilaian ulang.'));
            }

            // Baris predikat
            var pred = el('div', 'pn-pred');
            var lblEl = el('span', 'pn-pred-lbl');
            var tombol = [];
            lbl.forEach(function (teks, i) {
                var n = i + 1;
                var t = el('button', bintang ? 'pn-star' : 'pn-pill', bintang ? '★' : teks);
                t.type = 'button';
                t.title = teks;
                t.addEventListener('click', function () { pilih = n; segar(); });
                tombol.push(t);
                pred.appendChild(t);
            });
            if (bintang) pred.appendChild(lblEl);
            wrap.appendChild(pred);

            // Detail: muncul setelah predikat dipilih
            var detail = el('div');
            var ta = el('textarea', 'pn-kom');
            ta.rows = 2;
            ta.maxLength = 1000;
            ta.value = fb && fb.komentar ? fb.komentar : '';
            detail.appendChild(ta);

            var lab = el('label', 'pn-cek');
            var cb = el('input');
            cb.type = 'checkbox';
            cb.disabled = true;               // ditentukan predikat, tidak bisa diubah manual
            var cbTeks = el('span');
            lab.appendChild(cb);
            lab.appendChild(cbTeks);
            detail.appendChild(lab);

            var aksi = el('div', 'pn-aksi');
            var kNilai = el('span', 'pn-aksi');
            var inpN = null;
            if (pakaiNilai) {
                kNilai.appendChild(el('span', 'pn-muted', 'Nilai'));
                inpN = el('input');
                inpN.type = 'number'; inpN.min = '0'; inpN.max = '100'; inpN.step = '0.5'; inpN.placeholder = '0–100';
                inpN.value = tg.upload.nilai === null ? '' : tg.upload.nilai;
                kNilai.appendChild(inpN);
                aksi.appendChild(kNilai);
            }
            var btn = el('button', 'btn btn-3 btn-sm', 'Simpan penilaian');
            btn.type = 'button';
            var msg = el('small', 'pn-muted');
            aksi.appendChild(btn);
            aksi.appendChild(msg);
            detail.appendChild(aksi);
            wrap.appendChild(detail);

            function segar() {
                tombol.forEach(function (t, i) {
                    var n = i + 1;
                    t.classList.toggle('on', bintang ? n <= pilih : n === pilih);
                });
                lblEl.textContent = pilih ? lbl[pilih - 1] : 'Pilih predikat';
                detail.style.display = pilih ? '' : 'none';
                var revisi = pilih > 0 && pilih <= 2;
                cb.checked = revisi;
                lab.classList.toggle('pn-cek-on', revisi);
                cbTeks.textContent = revisi ? 'Minta revisi — kiriman siswa dibuka kembali'
                                            : 'Tidak perlu revisi — kiriman siswa dikunci';
                ta.placeholder = revisi ? 'Komentar wajib: jelaskan apa yang harus diperbaiki siswa'
                                        : 'Komentar untuk siswa (opsional)';
                if (pakaiNilai) kNilai.style.display = (pilih >= 3) ? '' : 'none';
                msg.textContent = '';
            }

            btn.addEventListener('click', function () {
                var revisi = pilih <= 2;
                var nilai  = (pakaiNilai && !revisi) ? inpN.value.trim() : '';
                if (pakaiNilai && !revisi && nilai === '') {
                    msg.textContent = 'Isi nilai 0–100 dulu';
                    msg.style.color = 'var(--coral, #DC2626)';
                    return;
                }
                simpanFeedback({
                    user_id: uid, content_id: tg.content_id, predikat: pilih,
                    komentar: ta.value.trim(), minta_revisi: revisi ? 1 : 0, nilai: nilai
                }, btn, msg);
            });

            segar();
            return wrap;
        }

        function render(d) {
            var r = d.rekap;
            document.getElementById('pn-nama').textContent = d.siswa.nama;
            document.getElementById('pn-sub').textContent = d.siswa.nis + ' · ' + (d.siswa.kelas || '-');

            ringkas.innerHTML = '';
            ringkas.appendChild(el('span', null, 'Materi ' + r.materi_selesai + '/' + r.materi_total + ' (' + persen(r.materi_selesai, r.materi_total) + '%)'));
            ringkas.appendChild(el('span', null, 'Dikumpul ' + r.terkumpul + '/' + r.tugas_total));
            ringkas.appendChild(el('span', null, 'Dinilai ' + r.dinilai + '/' + (r.dinilai + r.perlu_nilai)));
            badgesSiswa(r).forEach(function (x) { ringkas.appendChild(x); });

            isi.innerHTML = '';
            if (!r.profil) { isi.appendChild(el('p', 'pn-muted', 'Siswa ini belum mengerjakan pre-test, jadi belum punya jalur materi.')); return; }
            if (!d.topik.length) { isi.appendChild(el('p', 'pn-muted', 'Tidak ada tugas di jalur siswa ini.')); return; }

            d.topik.forEach(function (t) {
                var sec = el('div', 'pn-topik');
                var h = el('div', 'pn-topik-h');
                h.appendChild(el('b', null, t.nama));
                t.evaluasi.forEach(function (ev) {
                    var e = el('span', 'pn-eval', 'Evaluasi: ' + (ev.persen === null ? 'belum dikerjakan' : ev.persen + '%'));
                    e.title = ev.judul;
                    h.appendChild(e);
                });
                sec.appendChild(h);
                if (!t.tugas.length) sec.appendChild(el('p', 'pn-muted', 'Tidak ada tugas di topik ini.'));

                t.tugas.forEach(function (tg) {
                    var det = el('details', 'pn-tugas');
                    if (tg.status === 'perlu_dinilai') det.open = true;
                    var sm = el('summary');
                    sm.appendChild(el('span', null, tg.judul));
                    sm.appendChild(el('small', 'pn-muted', ' (' + tg.tipe + ')'));
                    sm.appendChild(badge(tg.status, labelTugas(tg)));
                    det.appendChild(sm);

                    var body = el('div', 'pn-tugas-isi');
                    if (tg.perlu_upload) {
                        if (tg.upload) {
                            var row = el('div');
                            row.appendChild(el('span', 'pn-muted', 'File: '));
                            var a = el('a', 'pn-link', tg.upload.nama);
                            a.href = '/lihat_kiriman.php?id=' + tg.upload.id;
                            a.target = '_blank';
                            row.appendChild(a);
                            row.appendChild(el('small', 'pn-muted', ' · ' + tg.upload.waktu));
                            body.appendChild(row);
                        } else {
                            body.appendChild(el('p', 'pn-muted', 'File belum diupload.'));
                        }
                    }
                    if (tg.ada_tabel) {
                        if (tg.tabel) {
                            var w = el('div', 'pn-tabel-isi');
                            if (tg.tabel.html) w.innerHTML = tg.tabel.html;
                            else w.appendChild(el('em', 'pn-muted', 'Tabel isian di konten ini sudah diubah/dihapus.'));
                            body.appendChild(w);
                            body.appendChild(el('small', 'pn-muted', 'Tabel disimpan ' + tg.tabel.waktu + (tg.tabel.lengkap ? '' : ' · belum lengkap')));
                        } else {
                            body.appendChild(el('p', 'pn-muted', 'Tabel belum diisi.'));
                        }
                    }
                    if (tg.upload || tg.tabel) body.appendChild(formFeedback(tg, d.siswa.id));
                    det.appendChild(body);
                    sec.appendChild(det);
                });
                isi.appendChild(sec);
            });
        }

        function simpanFeedback(data, b, msg) {
            function salah(t) { msg.textContent = t; msg.style.color = 'var(--coral, #DC2626)'; }
            if (!data.predikat) return salah('Pilih predikat dulu');
            if ((data.predikat <= 2 || data.minta_revisi) && data.komentar === '') return salah('Tulis komentar untuk siswa');
            if (data.nilai !== '' && (isNaN(Number(data.nilai)) || Number(data.nilai) < 0 || Number(data.nilai) > 100)) return salah('Nilai harus 0–100');

            b.disabled = true;
            msg.textContent = 'Menyimpan…';
            msg.style.color = '';
            var fd = new FormData();
            Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
            fetch('/api/simpan_feedback.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    b.disabled = false;
                    if (d.ok) {
                        msg.textContent = 'Tersimpan';
                        msg.style.color = 'var(--teal, #0D9488)';
                        setTimeout(function () { if (aktifId) muat(aktifId); }, 500);
                    } else {
                        salah(d.msg || 'Gagal menyimpan');
                    }
                })
                .catch(function () { b.disabled = false; salah('Koneksi gagal, coba lagi.'); });
        }

        saring();
    })();
    </script>
</div>

<!-- ═══════════ TAB 4: PENGATURAN MATERI ═══════════ -->
<div id="tab-materi" class="tab-panel">
    <?php
    $topik_tree_dash    = get_topik_tree();
    $total_konten_semua = $pdo->query("SELECT COUNT(*) FROM content WHERE aktif=1")->fetchColumn();
    $total_topik_semua  = $pdo->query("SELECT COUNT(*) FROM topik WHERE aktif=1")->fetchColumn();
    if (!function_exists('hitungKonten')) {
        function hitungKonten($pdo, $slug) {
            $stmt = $pdo->prepare("SELECT tipe, COUNT(*) as jml FROM content WHERE topik=? AND aktif=1 GROUP BY tipe");
            $stmt->execute([$slug]);
            $result = ['teori'=>0,'langkah'=>0,'jobsheet'=>0,'evaluasi'=>0,'tantangan'=>0];
            foreach ($stmt->fetchAll() as $r) if (isset($result[$r['tipe']])) $result[$r['tipe']] = $r['jml'];
            $result['total'] = array_sum($result);
            return $result;
        }
    }
    ?>
    <div class="cta" style="margin-bottom:16px">
        <a href="kelola_konten.php" class="btn btn-3"><i class="icon-file-text"></i> Kelola Konten Materi</a>
        <a href="kelola_topik.php" class="btn btn-2"><i class="icon-folder-tree"></i> Kelola Topik &amp; Sub-Topik</a>
    </div>

    <div class="dgrid2" style="margin-bottom:16px">
        <div class="card" style="border-left:4px solid var(--biru)">
            <div class="stat-lbl">Total topik aktif</div>
            <div class="stat-num" style="margin-top:4px"><?= $total_topik_semua ?></div>
            <div style="font-size:11.5px;color:var(--abu-muda)">termasuk sub-topik</div>
        </div>
        <div class="card" style="border-left:4px solid var(--teal)">
            <div class="stat-lbl">Total konten aktif</div>
            <div class="stat-num" style="margin-top:4px"><?= $total_konten_semua ?></div>
            <div style="font-size:11.5px;color:var(--abu-muda)">di semua topik</div>
        </div>
    </div>

    <div class="card">
        <div class="card-h" style="margin-bottom:14px"><h3><i class="icon-list-checks"></i> Ringkasan konten per topik</h3></div>
        <div style="overflow-x:auto">
            <table class="gtable">
                <thead><tr><th>Topik</th><th style="text-align:center">Teori</th><th style="text-align:center">Langkah</th><th style="text-align:center">Jobsheet</th><th style="text-align:center">Evaluasi</th><th style="text-align:center">Tantangan</th><th style="text-align:center">Total</th><th style="text-align:center">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($topik_tree_dash as $parent): ?>
                    <?php if (empty($parent['children'])): $k = hitungKonten($pdo, $parent['slug']); ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($parent['nama']) ?></strong></td>
                        <?php foreach (['teori','langkah','jobsheet','evaluasi','tantangan'] as $tipe): ?>
                        <td style="text-align:center"><?= $k[$tipe] > 0 ? '<b style="color:var(--teal)">'.$k[$tipe].'</b>' : '<span style="color:var(--garis)">–</span>' ?></td>
                        <?php endforeach; ?>
                        <td style="text-align:center"><strong><?= $k['total'] ?></strong></td>
                        <td style="text-align:center"><a href="kelola_konten.php" class="btn btn-2 btn-sm">Edit</a></td>
                    </tr>
                    <?php else: ?>
                    <tr style="background:var(--kanvas)"><td colspan="8" style="font-size:11px;font-weight:800;color:var(--abu-muda);text-transform:uppercase;letter-spacing:.8px;padding:8px 12px"><?= htmlspecialchars($parent['nama']) ?></td></tr>
                    <?php foreach ($parent['children'] as $child): $k = hitungKonten($pdo, $child['slug']); ?>
                    <tr>
                        <td style="padding-left:26px"><i class="icon-corner-down-right" style="color:var(--abu-muda);font-size:13px"></i> <?= htmlspecialchars($child['nama']) ?></td>
                        <?php foreach (['teori','langkah','jobsheet','evaluasi','tantangan'] as $tipe): ?>
                        <td style="text-align:center"><?= $k[$tipe] > 0 ? '<b style="color:var(--teal)">'.$k[$tipe].'</b>' : '<span style="color:var(--garis)">–</span>' ?></td>
                        <?php endforeach; ?>
                        <td style="text-align:center"><strong><?= $k['total'] ?></strong></td>
                        <td style="text-align:center"><a href="kelola_konten.php" class="btn btn-2 btn-sm">Edit</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══════════ TAB 5: POST-TEST ═══════════ -->
<div id="tab-pengaturan" class="tab-panel">
    <?php
    $pt_aktif  = get_pengaturan('posttest_aktif', '0');
    $pt_mulai  = get_pengaturan('posttest_mulai');
    $pt_durasi = get_pengaturan('posttest_durasi_hari', 21);
    $pt_min    = get_pengaturan('min_materi_persen', 100);
    ?>
    <div class="card">
        <div class="card-h" style="margin-bottom:16px"><h3><i class="icon-target"></i> Pengaturan post-test</h3></div>
        <form method="POST">
            <input type="hidden" name="aksi" value="setting_posttest">
            <div class="dform-grid">
                <div class="fg">
                    <label>Status post-test</label>
                    <label class="cbx" style="padding:9px 0"><input type="checkbox" name="posttest_aktif" value="1" <?= $pt_aktif === '1' ? 'checked' : '' ?>> Aktifkan akses post-test</label>
                </div>
                <div class="fg"><label>Tanggal mulai pembelajaran</label><input type="date" name="tgl_mulai" value="<?= htmlspecialchars($pt_mulai ?? date('Y-m-d')) ?>"></div>
                <div class="fg"><label>Durasi pembelajaran (hari)</label><input type="number" name="durasi_hari" value="<?= htmlspecialchars($pt_durasi) ?>" min="1" max="180"></div>
                <div class="fg"><label>Min. progress materi (%)</label><input type="number" name="min_persen" value="<?= htmlspecialchars($pt_min) ?>" min="1" max="100"></div>
                <div class="fg" style="display:flex;align-items:flex-end"><button type="submit" class="btn btn-3 btn-full"><i class="icon-save"></i> Simpan pengaturan</button></div>
            </div>
        </form>
        <div class="dstatus">
            <strong>Status saat ini:</strong>
            Post-test <b style="color:<?= $pt_aktif==='1'?'var(--teal)':'var(--coral)' ?>"><?= $pt_aktif === '1' ? '✓ AKTIF' : '✗ NONAKTIF' ?></b>
            · Mulai: <b><?= $pt_mulai ? date('d/m/Y', strtotime($pt_mulai)) : '-' ?></b>
            · Durasi: <b><?= $pt_durasi ?> hari</b>
            · Min. progress: <b><?= $pt_min ?>%</b>
        </div>
    </div>
</div>

<!-- ═══════════ TAB 6: AKTIVITAS ═══════════ -->
<div id="tab-aktivitas" class="tab-panel">
    <div class="card">
        <div class="card-h" style="margin-bottom:14px"><h3><i class="icon-activity"></i> Activity log terbaru (30)</h3></div>
        <?php if ($aktivitas_terbaru): ?>
        <div style="overflow-x:auto">
            <table class="gtable">
                <thead><tr><th>Waktu</th><th>Siswa</th><th>Kelas</th><th>Aktivitas</th><th>Topik</th><th>Detail</th></tr></thead>
                <tbody>
                <?php foreach ($aktivitas_terbaru as $a):
                    $detail = json_decode($a['detail'] ?? '{}', true) ?? [];
                    $warna_tipe = [
                        'buka_materi'=>'#2563EB','selesai_materi'=>'#0EA5A4','jawab_quiz'=>'#7C3AED',
                        'upload_jobsheet'=>'#F59E0B','login'=>'#94A3B8','pretest'=>'#F43F5E','tanya_bot'=>'#2563EB',
                    ];
                    $wt = $warna_tipe[$a['tipe']] ?? '#888';
                ?>
                <tr>
                    <td style="font-size:12px;color:var(--abu-muda);white-space:nowrap"><?= date('d/m H:i', strtotime($a['created_at'])) ?></td>
                    <td><strong><?= htmlspecialchars($a['nama']) ?></strong></td>
                    <td><?= htmlspecialchars($a['kelas'] ?? '-') ?></td>
                    <td><span class="dbadge" style="background:<?= $wt ?>"><?= strtoupper(str_replace('_',' ',$a['tipe'])) ?></span></td>
                    <td style="font-size:12.5px"><?= htmlspecialchars(ucwords(str_replace('_',' ',$a['topik'] ?? '-'))) ?></td>
                    <td style="font-size:12px;color:var(--abu-muda)">
                        <?php if (isset($detail['judul'])): ?><?= htmlspecialchars(mb_strimwidth($detail['judul'],0,36,'…')) ?>
                        <?php elseif (isset($detail['skor'])): ?>Skor: <?= $detail['skor'] ?>/<?= $detail['total'] ?? '?' ?> (<?= $detail['persentase'] ?? '?' ?>%)
                        <?php elseif (isset($detail['profil_gabungan'])): ?><?= htmlspecialchars($detail['profil_gabungan']) ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="kosong" style="padding:30px"><i class="icon-activity"></i><b>Belum ada aktivitas</b></div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════ TAB 7: WA BOT & AI ═══════════ -->
<div id="tab-wabot" class="tab-panel">

    <?php
    // ── Ringkasan feedback harian (WhatsApp) ──
    $rw_aktif = get_pengaturan('wa_ringkasan_aktif', '0') === '1';
    $rw_jam   = get_pengaturan('wa_ringkasan_jam', '15:30');
    $rw_jeda  = (int) get_pengaturan('wa_ringkasan_jeda_detik', '30');
    $rw_log   = get_pengaturan('wa_ringkasan_log', '');
    $rw_paksa = get_pengaturan('wa_ringkasan_paksa', '0') === '1';
    $rw_antre = (int) $pdo->query("
        SELECT COUNT(DISTINCT f.user_id) FROM feedback_guru f JOIN users u ON u.id = f.user_id
        WHERE f.wa_dikirim_at IS NULL AND f.dinilai_at >= NOW() - INTERVAL 7 DAY
          AND (f.dibaca_at IS NULL OR f.minta_revisi = 1)
          AND u.nomor_wa IS NOT NULL AND u.nomor_wa <> ''
    ")->fetchColumn();
    $rw_wa    = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'siswa' AND nomor_wa IS NOT NULL AND nomor_wa <> ''")->fetchColumn();
    $rw_siswa = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'siswa'")->fetchColumn();
    ?>
    <div class="card" style="margin-bottom:16px">
        <div class="card-h" style="margin-bottom:10px"><h3><i class="icon-bot"></i> Ringkasan feedback harian (WhatsApp)</h3></div>
        <p style="font-size:12.5px;color:var(--abu);line-height:1.6;margin-bottom:14px">
            Satu pesan per siswa per hari, berisi feedback baru yang belum dibaca di aplikasi, dikelompokkan per topik.
            Dikirim satu per satu dengan jeda agar nomor bot aman.
        </p>
        <div class="stat-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px">
            <div class="card" style="margin:0;border-left:4px solid var(--teal)">
                <div class="stat-lbl">Siswa terhubung WA</div>
                <div style="font-weight:800;font-size:15px;margin-top:4px"><?= $rw_wa ?> / <?= $rw_siswa ?></div>
            </div>
            <div class="card" style="margin:0;border-left:4px solid var(--amber)">
                <div class="stat-lbl">Menunggu dikirim</div>
                <div style="font-weight:800;font-size:15px;margin-top:4px"><?= $rw_antre ?> siswa</div>
                <?php if ($rw_antre > 1): ?><div style="font-size:11px;color:var(--abu-muda)">± <?= (int) ceil($rw_antre * $rw_jeda / 60) ?> menit</div><?php endif; ?>
            </div>
            <div class="card" style="margin:0;border-left:4px solid var(--biru)">
                <div class="stat-lbl">Pengiriman terakhir</div>
                <div style="font-weight:700;font-size:12.5px;margin-top:4px"><?= htmlspecialchars($rw_log !== '' ? $rw_log : 'Belum pernah') ?></div>
            </div>
        </div>

        <form method="POST" style="display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end">
            <input type="hidden" name="aksi" value="setting_ringkasan_wa">
            <label class="cbx" style="margin-bottom:8px"><input type="checkbox" name="wa_ringkasan_aktif" <?= $rw_aktif ? 'checked' : '' ?>> Aktifkan pengiriman harian</label>
            <div class="fg" style="margin:0"><label>Jam kirim (WIB)</label><input type="time" name="wa_ringkasan_jam" value="<?= htmlspecialchars($rw_jam) ?>" required></div>
            <div class="fg" style="margin:0"><label>Jeda antarpesan (detik)</label><input type="number" name="wa_ringkasan_jeda_detik" min="10" max="300" value="<?= $rw_jeda ?>" style="width:110px"></div>
            <button type="submit" class="btn btn-3 btn-sm"><i class="icon-save"></i> Simpan</button>
        </form>

        <form method="POST" style="margin-top:14px;display:flex;flex-wrap:wrap;gap:10px;align-items:center"
              onsubmit="return confirm('Kirim ringkasan sekarang ke <?= $rw_antre ?> siswa? Pesan dikirim satu per satu.')">
            <input type="hidden" name="aksi" value="paksa_ringkasan_wa">
            <button type="submit" class="btn btn-2 btn-sm" <?= $rw_antre > 0 && !$rw_paksa ? '' : 'disabled' ?>><i class="icon-send"></i> Kirim ringkasan sekarang</button>
            <?php if ($rw_paksa): ?>
                <span style="font-size:12.5px;color:var(--abu)">Sudah dijadwalkan — berjalan dalam ±5 menit.</span>
            <?php endif; ?>
        </form>
        <p style="font-size:11.5px;color:var(--abu-muda);margin-top:10px;line-height:1.6">
            Siswa menghubungkan nomornya sendiri lewat bot dengan mengirim <b>daftar NIS PASSWORD</b> ke nomor bot (terverifikasi dari nomor pengirim).
        </p>
    </div>

    <!-- STATUS & KONFIGURASI GATEWAY -->
    <div class="card" style="margin-bottom:16px">
        <div class="card-h" style="margin-bottom:16px"><h3><i class="icon-bot"></i> Status &amp; konfigurasi WA Bot</h3></div>

        <!-- Status ringkas -->
        <div class="stat-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px">
            <div class="card" style="margin:0;border-left:4px solid var(--teal)">
                <div class="stat-lbl">Nomor Bot</div>
                <div style="font-weight:800;font-size:15px;margin-top:4px"><?= htmlspecialchars(get_pengaturan('wa_bot_nomor', '-')) ?></div>
            </div>
            <div class="card" style="margin:0;border-left:4px solid var(--biru)">
                <div class="stat-lbl">Model AI Aktif</div>
                <div style="font-weight:800;font-size:13px;margin-top:4px;word-break:break-all"><?= htmlspecialchars(get_pengaturan('wa_ai_model', 'llama-3.1-8b-instant')) ?></div>
            </div>
            <div class="card" style="margin:0;border-left:4px solid var(--amber)">
                <div class="stat-lbl">Provider</div>
                <div style="font-weight:800;font-size:15px;margin-top:4px"><?= strtoupper(htmlspecialchars(get_pengaturan('wa_ai_provider', 'groq'))) ?></div>
            </div>
        </div>

        <!-- Webhook URL -->
        <div class="fg">
            <label><i class="icon-link"></i> URL Webhook Fonnte</label>
            <div style="display:flex;gap:8px;align-items:center">
                <input type="text" id="webhook_url" readonly value="<?= APP_URL ?>/api/wa_webhook.php" style="flex:1;font-family:monospace;background:var(--kanvas)">
                <button onclick="copyWebhook()" id="btn_copy_webhook" class="btn btn-1 btn-sm" style="white-space:nowrap"><i class="icon-copy"></i> Copy</button>
            </div>
            <div class="fhint" style="font-size:11px;color:var(--abu-muda);margin-top:4px">Paste URL ini di pengaturan Webhook Fonnte → Device → Edit</div>
        </div>

        <!-- Form gateway -->
        <form method="POST">
            <input type="hidden" name="aksi" value="setting_wa_gateway">
            <?php $gw_aktif = get_pengaturan('wa_gateway', 'fonnte'); ?>

            <div class="fg">
                <label>Gateway aktif</label>
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <label class="dradio <?= $gw_aktif==='fonnte' ? 'on' : '' ?>">
                        <input type="radio" name="wa_gateway" value="fonnte" <?= $gw_aktif==='fonnte' ? 'checked' : '' ?>> 📱 Fonnte
                    </label>
                    <label class="dradio <?= $gw_aktif==='whacenter' ? 'on' : '' ?>">
                        <input type="radio" name="wa_gateway" value="whacenter" <?= $gw_aktif==='whacenter' ? 'checked' : '' ?>> 💬 Whacenter
                    </label>
                    <label class="dradio <?= $gw_aktif==='wagateway' ? 'on' : '' ?>">
                        <input type="radio" name="wa_gateway" value="wagateway" <?= $gw_aktif==='wagateway' ? 'checked' : '' ?>> 🖥️ benGateway
                    </label>
                </div>
            </div>

            <div class="fg">
                <label>Nomor Bot (format: 628xxx)</label>
                <input type="text" name="wa_bot_nomor" value="<?= htmlspecialchars(get_pengaturan('wa_bot_nomor', '')) ?>" placeholder="6285111308087">
                <div class="fhint" style="font-size:11px;color:var(--abu-muda);margin-top:3px">Nomor WA yang terhubung ke gateway aktif</div>
            </div>

            <!-- Info token Fonnte -->
            <div class="dinfo" style="background:var(--biru-muda);border-color:var(--biru-200)">
                <div style="font-size:12.5px;font-weight:700;color:var(--biru-tua);margin-bottom:8px">📱 Fonnte — Token Aktif</div>
                <?php
                $tok = defined('FONNTE_TOKEN') ? FONNTE_TOKEN : '';
                $tok_sensor = $tok ? (substr($tok,0,4) . str_repeat('*', max(0,strlen($tok)-8)) . substr($tok,-4)) : '-';
                ?>
                <div class="fg" style="margin-bottom:6px"><input type="text" value="<?= htmlspecialchars($tok_sensor) ?>" style="font-family:monospace"></div>
                <div style="font-size:11px;color:var(--abu-muda)">Tersensor. Ganti via SSH:</div>
                <div style="display:flex;gap:8px;align-items:center;margin-top:4px">
                    <code id="ssh_cmd" class="dcode">sudo sed -i "s|define('FONNTE_TOKEN', '.*');|define('FONNTE_TOKEN', 'TOKEN_BARU');|" /var/www/html/aibotlms/config/config.php</code>
                    <button type="button" onclick="copySSH()" id="btn_copy_ssh" class="btn btn-2 btn-sm" style="white-space:nowrap"><i class="icon-copy"></i></button>
                </div>
            </div>

            <!-- Info Device ID Whacenter -->
            <div class="dinfo" style="background:var(--teal-muda);border-color:#99E6E5">
                <div style="font-size:12.5px;font-weight:700;color:#0C807F;margin-bottom:8px">💬 Whacenter — Device ID Aktif</div>
                <?php
                $wc = defined('WHACENTER_DEVICE_ID') ? WHACENTER_DEVICE_ID : '';
                $wc_sensor = $wc ? (substr($wc,0,8) . str_repeat('*', max(0,strlen($wc)-16)) . substr($wc,-8)) : '-';
                ?>
                <div class="fg" style="margin-bottom:6px"><input type="text" value="<?= htmlspecialchars($wc_sensor) ?>" style="font-family:monospace"></div>
                <div style="font-size:11px;color:var(--abu-muda)">Tersensor. Ganti via SSH:</div>
                <div style="display:flex;gap:8px;align-items:center;margin-top:4px">
                    <code id="ssh_cmd_wc" class="dcode">sudo sed -i "s|define('WHACENTER_DEVICE_ID', '.*');|define('WHACENTER_DEVICE_ID', 'DEVICE_ID_BARU');|" /var/www/html/aibotlms/config/config.php</code>
                    <button type="button" onclick="copySSHWC()" id="btn_copy_ssh_wc" class="btn btn-2 btn-sm" style="white-space:nowrap"><i class="icon-copy"></i></button>
                </div>
            
            </div>
            <!-- Info benGateway (X200MA) -->
            <div class="dinfo" style="background:#F3F0FF;border-color:#D6CCFF">
                <div style="font-size:12.5px;font-weight:700;color:#5B3FD9;margin-bottom:8px">🖥️ benGateway — Konfigurasi Aktif</div>
                <?php
                $bg_url = get_pengaturan('wa_gateway_send_url', '');
                $bg_tok = get_pengaturan('wa_gateway_send_token', '');
                $bg_tok_sensor = $bg_tok ? (substr($bg_tok,0,6) . str_repeat('*', max(0,strlen($bg_tok)-12)) . substr($bg_tok,-6)) : '-';
                ?>
                <div class="fg" style="margin-bottom:10px">
                    <label style="font-weight:400;color:var(--abu-muda);font-size:11px">URL</label>
                    <input type="text" value="<?= htmlspecialchars($bg_url ?: '(belum diatur)') ?>" style="font-family:monospace">
                </div>
                <div class="fg" style="margin-bottom:6px">
                    <label style="font-weight:400;color:var(--abu-muda);font-size:11px">Token</label>
                    <input type="text" value="<?= htmlspecialchars($bg_tok_sensor) ?>" style="font-family:monospace">
                </div>
                <div style="font-size:11px;color:var(--abu-muda)">Gateway internal pribadi (ben-X200MA). URL & token tersimpan di tabel <code>pengaturan</code> — <b>bukan</b> di config.php, jadi tidak bisa diubah lewat perintah <code>sed</code> seperti dua gateway di atas. Ganti lewat SSH:</div>
                <div style="display:flex;gap:8px;align-items:center;margin-top:4px">
                    <code id="ssh_cmd_bg" class="dcode">mysql -u elearning_user -p elearning_adaptif -e "UPDATE pengaturan SET nilai='TOKEN_BARU' WHERE kunci='wa_gateway_send_token';"</code>
                    <button type="button" onclick="copySSHBG()" id="btn_copy_ssh_bg" class="btn btn-2 btn-sm" style="white-space:nowrap"><i class="icon-copy"></i></button>
                </div>
            </div>

            <button type="submit" class="btn btn-3"><i class="icon-save"></i> Simpan konfigurasi gateway</button>
        </form>
    </div>

    <!-- PENGATURAN MODEL AI -->
    <div class="card">
        <div class="card-h" style="margin-bottom:16px"><h3><i class="icon-sparkles"></i> Pilih model AI</h3></div>

        <?php
        $model_list = [
            'groq' => [
                'label' => 'Groq',
                'models' => [
                    ['id' => 'llama-3.1-8b-instant',                     'nama' => 'Llama 3.1 8B Instant',    'gratis' => true,  'desc' => 'Cepat, ringan, cocok untuk pertanyaan sederhana'],
                    ['id' => 'llama-3.3-70b-versatile',                  'nama' => 'Llama 3.3 70B Versatile', 'gratis' => true,  'desc' => 'Lebih pintar, jawaban lebih detail'],
                    ['id' => 'meta-llama/llama-4-scout-17b-16e-instruct','nama' => 'Llama 4 Scout 17B',       'gratis' => true,  'desc' => 'Model terbaru Meta, seimbang'],
                    ['id' => 'qwen/qwen3-32b',                           'nama' => 'Qwen3 32B',               'gratis' => true,  'desc' => 'Model Alibaba, kuat untuk sains & teknik'],
                    ['id' => 'groq/compound',                            'nama' => 'Groq Compound',           'gratis' => false, 'desc' => 'Model compound Groq, berbayar'],
                ],
            ],
            'gemini' => [
                'label' => 'Google Gemini',
                'models' => [
                    ['id' => 'gemini-2.0-flash',      'nama' => 'Gemini 2.0 Flash',      'gratis' => false, 'desc' => 'Google Gemini terbaru, berbayar'],
                    ['id' => 'gemini-2.0-flash-lite', 'nama' => 'Gemini 2.0 Flash Lite', 'gratis' => false, 'desc' => 'Lebih hemat, cocok untuk volume tinggi'],
                ],
            ],
        ];
        $provider_aktif = get_pengaturan('wa_ai_provider', 'groq');
        $model_aktif    = get_pengaturan('wa_ai_model', 'llama-3.1-8b-instant');
        ?>

        <form method="POST" id="form_ai_setting">
            <input type="hidden" name="aksi" value="setting_ai">

            <div class="fg">
                <label>Provider AI</label>
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <?php foreach ($model_list as $pkey => $pdata): ?>
                    <label class="dradio <?= $provider_aktif === $pkey ? 'on' : '' ?>">
                        <input type="radio" name="wa_ai_provider" value="<?= $pkey ?>" <?= $provider_aktif === $pkey ? 'checked' : '' ?> onchange="gantiProvider('<?= $pkey ?>')">
                        <?= $pdata['label'] ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php foreach ($model_list as $pkey => $pdata): ?>
            <div id="models_<?= $pkey ?>" style="display:<?= $provider_aktif === $pkey ? 'block' : 'none' ?>;margin-bottom:14px">
                <label style="font-size:12px;font-weight:700;color:var(--tinta);margin-bottom:8px;display:block">Model</label>
                <div style="display:flex;flex-direction:column;gap:8px">
                    <?php foreach ($pdata['models'] as $m): ?>
                    <label class="dmodel <?= $model_aktif === $m['id'] ? 'on' : '' ?>">
                        <input type="radio" name="wa_ai_model" value="<?= $m['id'] ?>" <?= $model_aktif === $m['id'] ? 'checked' : '' ?>>
                        <div style="flex:1;min-width:0">
                            <div style="font-size:13px;font-weight:700">
                                <?= $m['nama'] ?>
                                <?php if ($m['gratis']): ?>
                                <span class="dtag-gratis">GRATIS</span>
                                <?php else: ?>
                                <span class="dtag-bayar">BERBAYAR</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:11px;color:var(--abu-muda);margin-top:2px"><?= $m['desc'] ?> · <code style="font-size:10px"><?= $m['id'] ?></code></div>
                        </div>
                        <button type="button" onclick="cekModel('<?= $pkey ?>', '<?= $m['id'] ?>', this)" class="btn btn-2 btn-sm" style="white-space:nowrap">🔍 Cek</button>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="fg">
                <label>System prompt AI</label>
                <textarea name="wa_ai_prompt" rows="5" style="font-family:monospace" placeholder="Kamu adalah asisten belajar…"><?= htmlspecialchars(get_pengaturan('wa_ai_prompt', '')) ?></textarea>
                <div class="fhint" style="font-size:11px;color:var(--abu-muda);margin-top:4px">Kosongkan untuk menggunakan prompt default sistem.</div>
            </div>

            <button type="submit" class="btn btn-3"><i class="icon-save"></i> Simpan pengaturan AI</button>
        </form>

        <div id="hasil_cek_model" style="margin-top:16px;display:none"></div>
    </div>
</div>

</div><!-- /.dwrap -->

<!-- MODAL EDIT SISWA -->
<div id="modalEditSiswa" class="dmodal">
    <div class="dmodal-box">
        <h3 style="font-size:16px;font-weight:800;margin-bottom:18px;display:flex;align-items:center;gap:8px"><i class="icon-pencil" style="color:var(--biru)"></i> Edit data siswa</h3>
        <form method="POST" id="formEditSiswa">
            <input type="hidden" name="aksi" value="edit_siswa">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="fg"><label>Nama Lengkap <span style="color:var(--coral)">*</span></label><input type="text" name="edit_nama" id="edit_nama" required></div>
            <div class="fg"><label>NIS <span style="color:var(--coral)">*</span></label><input type="text" name="edit_nis" id="edit_nis" required></div>
            <div class="fg"><label>Kelas</label><input type="text" name="edit_kelas" id="edit_kelas"></div>
            <div class="fg"><label>Nomor WA</label><input type="text" name="edit_wa" id="edit_wa"></div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:6px">
                <button type="button" onclick="tutupModalEdit()" class="btn btn-2 btn-sm">Batal</button>
                <button type="submit" class="btn btn-3 btn-sm"><i class="icon-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<style>
.dwrap { max-width:1200px; margin:0 auto; padding:18px 16px 40px; }
.tab-panel { display:none; }
.tab-panel.aktif { display:block; }

.stat-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:18px; }
@media (max-width:768px){ .stat-grid { grid-template-columns:1fr 1fr; } }
.stat-card { background:var(--kartu); border:1px solid var(--garis); border-radius:var(--r-md); padding:18px; display:flex; align-items:center; gap:14px; }
.stat-ic { width:46px; height:46px; border-radius:13px; display:grid; place-items:center; font-size:22px; flex-shrink:0; }
.stat-num { font-size:26px; font-weight:800; letter-spacing:-1px; line-height:1; }
.stat-lbl { font-size:11.5px; color:var(--abu-muda); font-weight:600; margin-top:3px; }

.dgrid2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
@media (max-width:768px){ .dgrid2 { grid-template-columns:1fr; } }

.gtable { width:100%; border-collapse:collapse; font-size:13px; }
.gtable thead tr { background:var(--kanvas); }
.gtable th { padding:10px 12px; text-align:left; font-size:11px; font-weight:800; color:var(--abu-muda); text-transform:uppercase; letter-spacing:.5px; border-bottom:1px solid var(--garis); }
.gtable td { padding:11px 12px; border-bottom:1px solid var(--garis); vertical-align:middle; }
.gtable code { font-size:11.5px; color:var(--abu); background:var(--kanvas); padding:2px 7px; border-radius:6px; }

.dbadge { display:inline-block; color:#fff; font-size:10.5px; font-weight:700; padding:3px 9px; border-radius:99px; }
.dfilter { padding:7px 10px; border:1.5px solid var(--garis); border-radius:var(--r-sm); font-size:13px; font-family:inherit; }
.dlink { color:var(--biru); text-decoration:none; font-weight:600; font-size:12px; display:inline-flex; align-items:center; gap:4px; }

.fg { margin-bottom:14px; }
.fg label { display:block; font-size:12px; font-weight:700; color:var(--tinta); margin-bottom:5px; }
.fg input, .fg select, .fg textarea { width:100%; padding:10px 12px; border:2px solid var(--garis); border-radius:var(--r-sm); font-size:13px; font-family:inherit; outline:none; transition:.15s; color:var(--tinta); background:#fff; }
.fg input:focus, .fg select:focus, .fg textarea:focus { border-color:var(--teal); background:var(--teal-muda); }
.dform-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
@media (max-width:768px){ .dform-grid { grid-template-columns:1fr; } }
.cbx { display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; color:var(--abu); cursor:pointer; }
.cbx input { width:auto; accent-color:var(--teal); }

.dstatus { margin-top:16px; background:var(--kanvas); border-radius:var(--r-sm); padding:12px 14px; font-size:12.5px; color:var(--abu); line-height:1.7; }

.dmodal { display:none; position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9999; align-items:center; justify-content:center; padding:20px; }
.dmodal-box { background:#fff; border-radius:var(--r-lg); padding:26px; width:100%; max-width:420px; box-shadow:0 20px 60px rgba(15,23,42,.3); }
</style>

<!-- jQuery + DataTables (dipertahankan) -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script>
// ── Tab switching (identik dgn asli) ─────────────────────────
function bukaTab(id, el) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('aktif'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('aktif'));
    document.getElementById('tab-' + id).classList.add('aktif');
    el.classList.add('aktif');
}
window.addEventListener('DOMContentLoaded', function() {
    var hash = window.location.hash.replace('#','');
    if (hash) {
        var btn = document.querySelector('[onclick*="' + hash + '"]');
        if (btn) btn.click();
    }
});
</script>

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
            { orderable: false, targets: [10] },
            { width: '30px', targets: [0] },
            { width: '80px', targets: [1] },
            { width: '70px', targets: [6,7] },
        ],
        order: [[2, 'asc']],
        rowCallback: function(row, data, displayIndex, displayIndexFull) {
            $('td:first', row).html(displayIndexFull + 1);
        }
    });

    // Filter kelas & profil pakai data-attribute (custom search)
    $('#filter_kelas, #filter_profil').on('change', function() {
        table.draw();
    });

    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        var row      = table.row(dataIndex).node();
        var fKelas   = $('#filter_kelas').val();
        var fProfil  = $('#filter_profil').val();
        var dKelas   = $(row).data('kelas') || '';
        var dProfil  = $(row).data('profil') || '';

        if (fKelas && dKelas !== fKelas) return false;
        if (fProfil && dProfil.indexOf(fProfil) === -1) return false;
        return true;
    });
});

// ── Modal edit siswa ─────────────────────────────────────────
function bukaModalEdit(id, nama, nis, kelas, wa) {
    document.getElementById('edit_id').value    = id;
    document.getElementById('edit_nama').value   = nama;
    document.getElementById('edit_nis').value    = nis;
    document.getElementById('edit_kelas').value  = kelas;
    document.getElementById('edit_wa').value     = wa;
    document.getElementById('modalEditSiswa').style.display = 'flex';
}
function tutupModalEdit() {
    document.getElementById('modalEditSiswa').style.display = 'none';
}
document.getElementById('modalEditSiswa').addEventListener('click', function(e) {
    if (e.target === this) tutupModalEdit();
});

// ── Normalisasi input WA ─────────────────────────────────────
function attachWaInputHandler(el) {
    if (!el) return;
    el.addEventListener('keypress', function(e) {
        if (!/[0-9]/.test(e.key)) e.preventDefault();
    });
    el.addEventListener('input', function() {
        var pos = this.selectionStart;
        var clean = this.value.replace(/[^0-9]/g, '');
        if (clean.length >= 2 && clean.startsWith('6') && !clean.startsWith('62')) clean = '';
        if (clean.length >= 1 && !['0','6'].includes(clean[0])) clean = '';
        this.value = clean;
        this.setSelectionRange(pos, pos);
    });
    el.addEventListener('paste', function(e) {
        e.preventDefault();
        var pasted = (e.clipboardData || window.clipboardData).getData('text');
        var clean  = pasted.replace(/[^0-9]/g, '');
        if (clean.startsWith('0')) clean = '62' + clean.substring(1);
        else if (clean.startsWith('+62')) clean = '62' + clean.substring(3);
        if (!clean.startsWith('08') && !clean.startsWith('628')) clean = '';
        this.value = clean;
    });
}
attachWaInputHandler(document.getElementById('edit_wa'));
attachWaInputHandler(document.querySelector('input[name="wa_baru"]'));

// ── Import Excel 2-langkah (identik dgn asli) ────────────────
let importData = [];
let importOffset = 0;
const BATCH_SIZE = 20;

function importSiswa() {
    const fileInput = document.getElementById('file_import');
    const hasilDiv  = document.getElementById('hasil_import');

    if (!fileInput.files.length) {
        hasilDiv.style.display = 'block';
        hasilDiv.innerHTML = '<div class="dimsg err">⚠ Pilih file Excel terlebih dahulu.</div>';
        return;
    }

    hasilDiv.style.display = 'block';
    hasilDiv.innerHTML = '<div class="dimsg info">⏳ Membaca file Excel…</div>';

    const formData = new FormData();
    formData.append('file_excel', fileInput.files[0]);
    formData.append('aksi', 'parse');

    fetch('/api/import_siswa.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'error') {
            hasilDiv.innerHTML = `<div class="dimsg err">✗ ${data.pesan}</div>`;
            return;
        }
        importData   = data.rows;
        importOffset = 0;
        prosesImport(hasilDiv, data.rows.length);
    })
    .catch(() => {
        hasilDiv.innerHTML = '<div class="dimsg err">✗ Gagal membaca file. Coba lagi.</div>';
    });
}

function prosesImport(hasilDiv, total) {
    const batch = importData.slice(importOffset, importOffset + BATCH_SIZE);
    if (batch.length === 0) return;

    const pctNow = Math.round((importOffset / total) * 100);
    hasilDiv.innerHTML = `<div class="dimsg info">⏳ Mengimpor… ${importOffset}/${total} (${pctNow}%)</div>
        <div class="bar" style="margin-top:8px"><i style="width:${pctNow}%"></i></div>`;

    const formData = new FormData();
    formData.append('aksi', 'batch');
    formData.append('rows', JSON.stringify(batch));

    fetch('/api/import_siswa.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        importOffset += BATCH_SIZE;
        if (importOffset < total) {
            prosesImport(hasilDiv, total);
        } else {
            hasilDiv.innerHTML = `<div class="dimsg ok">✓ Import selesai — <strong>${data.total_berhasil ?? total} siswa berhasil</strong>${data.total_gagal ? `, <strong>${data.total_gagal} dilewati</strong>` : ''}.</div>`;
            if (data.detail_gagal && data.detail_gagal.length) {
                hasilDiv.innerHTML += `<div class="dimsg warn" style="margin-top:8px"><strong>Detail dilewati:</strong><br>${data.detail_gagal.map(s=>'• '+s).join('<br>')}</div>`;
            }
            setTimeout(() => location.reload(), 3000);
        }
    })
    .catch(() => {
        hasilDiv.innerHTML += '<div class="dimsg err" style="margin-top:8px">✗ Error pada batch, coba lagi.</div>';
    });
}
</script>

<style>
.dimsg { padding:10px 14px; border-radius:var(--r-sm); font-size:13px; font-weight:500; }
.dimsg.info { background:var(--biru-muda); color:var(--biru-tua); }
.dimsg.ok   { background:var(--teal-muda); color:var(--teal); border:1px solid #99E6E5; }
.dimsg.err  { background:var(--coral-muda); color:var(--coral); border:1px solid #FCA5A5; }
.dimsg.warn { background:var(--amber-muda); color:#B45309; border:1px solid #FCD34D; }

.dradio { display:flex; align-items:center; gap:8px; padding:10px 18px; border:2px solid var(--garis); border-radius:var(--r-sm); cursor:pointer; font-size:13px; font-weight:700; color:var(--abu); }
.dradio.on { border-color:var(--teal); color:var(--teal); background:var(--teal-muda); }
.dradio input { accent-color:var(--teal); }
.dmodel { display:flex; align-items:center; gap:12px; padding:12px 16px; border:2px solid var(--garis); border-radius:var(--r-sm); cursor:pointer; }
.dmodel.on { border-color:var(--teal); background:var(--teal-muda); }
.dmodel input { accent-color:var(--teal); flex-shrink:0; }
.dinfo { margin:14px 0; padding:14px 16px; border-radius:var(--r-sm); border:1px solid; }
.dcode { flex:1; padding:6px 10px; background:#1e1e2e; color:#a6e3a1; border-radius:6px; font-size:10px; display:block; overflow-x:auto; white-space:nowrap; }
.dtag-gratis { background:var(--teal-muda); color:var(--teal); font-size:10px; padding:2px 8px; border-radius:20px; font-weight:700; margin-left:6px; }
.dtag-bayar  { background:var(--coral-muda); color:var(--coral); font-size:10px; padding:2px 8px; border-radius:20px; font-weight:700; margin-left:6px; }
</style>

<script>
// ── WA Bot: copy & cek model (identik fungsi asli) ───────────
function copyWebhook() {
    const url = document.getElementById('webhook_url').value;
    navigator.clipboard.writeText(url).then(() => {
        const btn = document.getElementById('btn_copy_webhook');
        btn.innerHTML = '✅ Tersalin!';
        setTimeout(() => { btn.innerHTML = '<i class="icon-copy"></i> Copy'; }, 2000);
    });
}
function copySSH() {
    const cmd = document.getElementById('ssh_cmd').textContent;
    navigator.clipboard.writeText(cmd).then(() => {
        const btn = document.getElementById('btn_copy_ssh');
        btn.innerHTML = '✅';
        setTimeout(() => { btn.innerHTML = '<i class="icon-copy"></i>'; }, 2000);
    });
}
function copySSHWC() {
    const cmd = document.getElementById('ssh_cmd_wc').textContent;
    navigator.clipboard.writeText(cmd).then(() => {
        const btn = document.getElementById('btn_copy_ssh_wc');
        btn.innerHTML = '✅';
        setTimeout(() => { btn.innerHTML = '<i class="icon-copy"></i>'; }, 2000);
    });
}
function copySSHBG() {
    const cmd = document.getElementById('ssh_cmd_bg').textContent;
    navigator.clipboard.writeText(cmd).then(() => {
        const btn = document.getElementById('btn_copy_ssh_bg');
        btn.innerHTML = '✅';
        setTimeout(() => { btn.innerHTML = '<i class="icon-copy"></i>'; }, 2000);
    });
}
function gantiProvider(provider) {
    document.querySelectorAll('[id^="models_"]').forEach(el => el.style.display = 'none');
    document.getElementById('models_' + provider).style.display = 'block';
}
function cekModel(provider, modelId, btn) {
    const hasil = document.getElementById('hasil_cek_model');
    hasil.style.display = 'block';
    hasil.innerHTML = '<div class="dimsg info">🔍 Mengecek model <code>' + modelId + '</code>…</div>';
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
            hasil.innerHTML = '<div class="dimsg ok">✅ Model <strong>' + modelId + '</strong> tersedia dan merespons normal.<br><small style="color:var(--abu-muda)">Respons: ' + data.preview + '</small></div>';
        } else {
            hasil.innerHTML = '<div class="dimsg err">❌ Model tidak tersedia: ' + data.pesan + '</div>';
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = '🔍 Cek';
        hasil.innerHTML = '<div class="dimsg err">❌ Gagal menghubungi server.</div>';
    });
}
</script>

</body>
</html>
