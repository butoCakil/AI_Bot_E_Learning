<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

session_start();

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
    ?>
    <div class="card">
        <div class="card-h" style="margin-bottom:14px"><h3><i class="icon-paperclip"></i> Submission jobsheet siswa (<?= count($submissions) ?>)</h3></div>
        <?php if ($submissions): ?>
        <div style="overflow-x:auto">
            <table class="gtable">
                <thead><tr><th>Siswa</th><th>Kelas</th><th>Jobsheet</th><th>File</th><th>Tgl Upload</th><th>Nilai</th></tr></thead>
                <tbody>
                <?php foreach ($submissions as $sub): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($sub['nama']) ?></strong><br><small style="color:var(--abu-muda)"><?= htmlspecialchars($sub['nis']) ?></small></td>
                    <td><?= htmlspecialchars($sub['kelas'] ?? '-') ?></td>
                    <td style="font-size:12.5px"><?= htmlspecialchars(mb_strimwidth($sub['judul'] ?? '-',0,32,'…')) ?></td>
                    <td>
                        <?php if ($sub['file_path']): ?>
                            <a href="/<?= htmlspecialchars($sub['file_path']) ?>" target="_blank" class="dlink"><i class="icon-external-link"></i> <?= htmlspecialchars(mb_strimwidth($sub['file_original_name'] ?? 'file',0,20,'…')) ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:var(--abu-muda);white-space:nowrap"><?= date('d/m/y H:i', strtotime($sub['created_at'])) ?></td>
                    <td>
                        <form method="POST" action="api/nilai_jobsheet.php" style="display:flex;gap:5px;align-items:center">
                            <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">
                            <input type="number" name="nilai" min="0" max="100" value="<?= $sub['nilai'] !== null ? (int)$sub['nilai'] : '' ?>" placeholder="0-100"
                                   style="width:64px;padding:6px 8px;font-size:12px;border:2px solid var(--garis);border-radius:6px;font-family:inherit">
                            <button type="submit" class="btn btn-3 btn-sm" style="padding:6px 12px"><i class="icon-save"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="kosong" style="padding:30px"><i class="icon-paperclip"></i><b>Belum ada submission</b><p>Belum ada siswa yang mengupload jobsheet.</p></div>
        <?php endif; ?>
    </div>
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
    $wa_provider = get_pengaturan('wa_ai_provider', 'groq');
    $wa_model    = get_pengaturan('wa_ai_model', 'llama-3.1-8b-instant');
    $wa_prompt   = get_pengaturan('wa_ai_prompt', '');
    $wa_nomor    = get_pengaturan('wa_bot_nomor', '');
    $wa_gateway  = get_pengaturan('wa_gateway', 'fonnte');
    ?>

    <div class="dgrid2">
        <!-- Konfigurasi Gateway -->
        <div class="card">
            <div class="card-h" style="margin-bottom:16px"><h3><i class="icon-bot"></i> Konfigurasi WA Gateway</h3></div>
            <form method="POST">
                <input type="hidden" name="aksi" value="setting_wa_gateway">
                <div class="fg">
                    <label>Nomor WA Bot</label>
                    <input type="text" name="wa_bot_nomor" value="<?= htmlspecialchars($wa_nomor) ?>" placeholder="628xxx">
                </div>
                <div class="fg">
                    <label>Gateway</label>
                    <select name="wa_gateway">
                        <option value="fonnte"    <?= $wa_gateway === 'fonnte'    ? 'selected' : '' ?>>Fonnte</option>
                        <option value="whacenter" <?= $wa_gateway === 'whacenter' ? 'selected' : '' ?>>Whacenter</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-3 btn-full"><i class="icon-save"></i> Simpan gateway</button>
            </form>
        </div>

        <!-- Konfigurasi AI -->
        <div class="card">
            <div class="card-h" style="margin-bottom:16px"><h3><i class="icon-sparkles"></i> Konfigurasi AI</h3></div>
            <form method="POST">
                <input type="hidden" name="aksi" value="setting_ai">
                <div class="fg">
                    <label>Provider AI</label>
                    <select name="wa_ai_provider">
                        <option value="groq"   <?= $wa_provider === 'groq'   ? 'selected' : '' ?>>Groq</option>
                        <option value="gemini" <?= $wa_provider === 'gemini' ? 'selected' : '' ?>>Gemini</option>
                    </select>
                </div>
                <div class="fg">
                    <label>Model</label>
                    <input type="text" name="wa_ai_model" value="<?= htmlspecialchars($wa_model) ?>" placeholder="llama-3.1-8b-instant">
                </div>
                <div class="fg">
                    <label>System prompt</label>
                    <textarea name="wa_ai_prompt" rows="5" placeholder="Instruksi untuk AI bot…"><?= htmlspecialchars($wa_prompt) ?></textarea>
                </div>
                <button type="submit" class="btn btn-3 btn-full"><i class="icon-save"></i> Simpan konfigurasi AI</button>
            </form>
        </div>
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
.dtab-wrap { background:var(--kartu); border-bottom:1px solid var(--garis); position:sticky; top:var(--topbar-h); z-index:40; }
.dtabs { max-width:1200px; margin:0 auto; padding:0 16px; display:flex; gap:4px; overflow-x:auto; }
.tab-btn { border:0; background:transparent; font-family:inherit; font-size:13px; font-weight:600; color:var(--abu);
    padding:14px 16px; cursor:pointer; white-space:nowrap; border-bottom:3px solid transparent; transition:.15s;
    display:flex; align-items:center; gap:6px; }
.tab-btn:hover { color:var(--tinta); }
.tab-btn.aktif { color:var(--teal); border-bottom-color:var(--teal); }

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

    const is_last = (importOffset + BATCH_SIZE) >= total;
    const pctNow  = Math.round((importOffset / total) * 100);

    hasilDiv.innerHTML = `<div class="dimsg info">⏳ Mengimpor… ${importOffset}/${total} (${pctNow}%)</div>
        <div class="bar" style="margin-top:8px"><i style="width:${pctNow}%"></i></div>`;

    fetch('/api/import_siswa.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ aksi: 'batch', rows: batch, is_last: is_last })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'error') {
            hasilDiv.innerHTML = `<div class="dimsg err">✗ ${data.pesan}</div>`;
            return;
        }
        importOffset += BATCH_SIZE;
        if (importOffset < total) {
            prosesImport(hasilDiv, total);
        } else {
            let html = `<div class="dimsg ok">✓ Import selesai — <strong>${data.total_berhasil} siswa berhasil</strong>${data.total_gagal ? `, <strong>${data.total_gagal} dilewati</strong>` : ''}.</div>`;
            if (data.detail_gagal && data.detail_gagal.length) {
                html += `<div class="dimsg warn" style="margin-top:8px"><strong>Detail dilewati:</strong><br>${data.detail_gagal.map(s=>'• '+s).join('<br>')}</div>`;
            }
            hasilDiv.innerHTML = html;
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
</style>

</body>
</html>
