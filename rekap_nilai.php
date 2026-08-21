<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    header('Location: login_guru.php');
    exit;
}

$pdo = db();

// ── Item yang dinilai: evaluasi + jobsheet, urut sesuai urutan topik ──
$nama_topik_map = get_topik_list();          // slug => nama, sudah terurut
$urut_topik     = array_flip(array_keys($nama_topik_map));

$item_nilai = $pdo->query("
    SELECT id, judul, tipe, topik, urutan_default
    FROM content
    WHERE aktif = 1 AND tipe IN ('evaluasi','jobsheet')
")->fetchAll();

usort($item_nilai, function ($a, $b) use ($urut_topik) {
    $ta = $urut_topik[$a['topik']] ?? 999;
    $tb = $urut_topik[$b['topik']] ?? 999;
    if ($ta !== $tb) return $ta <=> $tb;
    if ($a['urutan_default'] !== $b['urutan_default']) return $a['urutan_default'] <=> $b['urutan_default'];
    return $a['id'] <=> $b['id'];
});
foreach ($item_nilai as $i => $it) {
    $item_nilai[$i]['topik_nama'] = $nama_topik_map[$it['topik']] ?? $it['topik'];
}

// ── Nilai evaluasi & jobsheet ──
$nilai_eval = [];
foreach ($pdo->query("SELECT user_id, content_id, persentase FROM evaluasi_results")->fetchAll() as $r) {
    $nilai_eval[$r['user_id']][$r['content_id']] = (int) $r['persentase'];
}
$job_ada = $nilai_job = [];
foreach ($pdo->query("SELECT user_id, content_id, nilai FROM jobsheet_submissions")->fetchAll() as $r) {
    $job_ada[$r['user_id']][$r['content_id']] = true;
    if ($r['nilai'] !== null) $nilai_job[$r['user_id']][$r['content_id']] = (float) $r['nilai'];
}

// ── Daftar siswa + pre/post test ──
$rekap_siswa = $pdo->query("
    SELECT u.id, u.nama, u.nis, u.kelas,
           p.profil_learning,
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

$kelas_list = ['XI TE 1','XI TE 2','XI TE 3','XI TE 4','XII TE 1','XII TE 2','XII TE 3','XII TE 4'];

$page_title = 'Rekap Nilai — AdaptLearn PRE';
$guru_aktif = 'dashboard';
include __DIR__ . '/includes/topbar_guru.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

<div class="crumb">
    <a href="dashboard_guru.php">Dashboard</a>
    <span class="sep">›</span>
    <span class="now">Rekap Nilai</span>
</div>

<div class="dtab-wrap">
    <div class="dtabs">
        <a href="dashboard_guru.php#ringkasan"  class="tab-btn"><i class="icon-layout-dashboard"></i> Ringkasan</a>
        <a href="dashboard_guru.php#siswa"      class="tab-btn"><i class="icon-users"></i> Siswa</a>
        <a href="dashboard_guru.php#penilaian"  class="tab-btn"><i class="icon-clipboard-check"></i> Penilaian</a>
        <a href="rekap_nilai.php"               class="tab-btn aktif"><i class="icon-clipboard-list"></i> Rekap Nilai</a>
        <a href="dashboard_guru.php#materi"     class="tab-btn"><i class="icon-folder-tree"></i> Materi</a>
        <a href="dashboard_guru.php#pengaturan" class="tab-btn"><i class="icon-target"></i> Post-Test</a>
        <a href="dashboard_guru.php#aktivitas"  class="tab-btn"><i class="icon-activity"></i> Aktivitas</a>
        <a href="dashboard_guru.php#wabot"      class="tab-btn"><i class="icon-bot"></i> WA Bot &amp; AI</a>
    </div>
</div>

<div class="rwrap">
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px">
            <h3 style="font-size:14.5px;font-weight:800;display:flex;align-items:center;gap:7px;margin:0">
                <i class="icon-clipboard-list" style="color:var(--teal)"></i>
                Rekap nilai seluruh item (<?= count($rekap_siswa) ?> siswa)
            </h3>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <select id="filter_kelas_rekap" class="rfilter">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($kelas_list as $k): ?>
                        <option value="<?= $k ?>"><?= $k ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="rcbx"><input type="checkbox" id="hanya_terisi"> Hanya yang sudah ada nilai</label>
                <a href="api/ekspor_rekap.php" class="btn btn-3 btn-sm" style="white-space:nowrap">
                    <i class="icon-download"></i> Ekspor Excel
                </a>
            </div>
        </div>

        <p style="font-size:11.5px;color:var(--abu-muda);margin-bottom:12px">
            Nilai evaluasi dalam persen (0–100), nilai jobsheet dari penilaian guru.
            <b>–</b> belum dikerjakan · <b style="color:#B45309">&#8987;</b> terunggah, belum dinilai.
        </p>

        <div style="overflow-x:auto">
            <table id="tabel_rekap" class="gtable" style="width:100%;font-size:12px">
                <thead>
                    <tr>
                        <th>NIS</th>
                        <th>Nama</th>
                        <th>Kelas</th>
                        <?php foreach ($item_nilai as $it): ?>
                            <th style="text-align:center" title="<?= htmlspecialchars($it['topik_nama'] . ' — ' . $it['judul']) ?>">
                                <span style="font-size:9px;display:block;color:var(--abu-muda)"><?= $it['tipe'] === 'evaluasi' ? 'EVAL' : 'JOB' ?></span>
                                <?= htmlspecialchars(mb_strimwidth($it['topik_nama'], 0, 13, '…')) ?>
                            </th>
                        <?php endforeach; ?>
                        <th style="text-align:center">Pre</th>
                        <th style="text-align:center">Post</th>
                        <th style="text-align:center">N-Gain</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rekap_siswa as $s):
                    $uid = $s['id'];
                    $ada_nilai = isset($nilai_eval[$uid]) || isset($job_ada[$uid])
                              || $s['skor_pre'] !== null || $s['skor_post'] !== null;
                ?>
                    <tr data-kelas="<?= htmlspecialchars($s['kelas'] ?? '') ?>" data-terisi="<?= $ada_nilai ? '1' : '0' ?>">
                        <td><code><?= htmlspecialchars($s['nis'] ?? '-') ?></code></td>
                        <td><strong><?= htmlspecialchars($s['nama']) ?></strong></td>
                        <td><?= htmlspecialchars($s['kelas'] ?? '-') ?></td>
                        <?php foreach ($item_nilai as $it): $cid = $it['id']; ?>
                            <td style="text-align:center">
                            <?php
                            if ($it['tipe'] === 'evaluasi') {
                                echo isset($nilai_eval[$uid][$cid]) ? $nilai_eval[$uid][$cid] : '<span style="color:var(--garis)">–</span>';
                            } elseif (isset($nilai_job[$uid][$cid])) {
                                echo (int) $nilai_job[$uid][$cid];
                            } elseif (isset($job_ada[$uid][$cid])) {
                                echo '<span style="color:#B45309" title="Terunggah, belum dinilai">&#8987;</span>';
                            } else {
                                echo '<span style="color:var(--garis)">–</span>';
                            }
                            ?>
                            </td>
                        <?php endforeach; ?>
                        <td style="text-align:center"><?= $s['skor_pre']  !== null ? (int) $s['skor_pre']  : '<span style="color:var(--garis)">–</span>' ?></td>
                        <td style="text-align:center"><?= $s['skor_post'] !== null ? (int) $s['skor_post'] : '<span style="color:var(--garis)">–</span>' ?></td>
                        <td style="text-align:center">
                        <?php
                        if ($s['skor_pre'] !== null && $s['skor_post'] !== null) {
                            $ng = hitung_ngain((int) $s['skor_pre'], (int) $s['skor_post']);
                            echo '<b style="color:' . $ng['warna'] . '">' . number_format($ng['ngain'], 2) . '</b>';
                        } else {
                            echo '<span style="color:var(--garis)">–</span>';
                        }
                        ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.rwrap { max-width:1400px; margin:0 auto; padding:18px 16px 40px; }
.rfilter { padding:7px 10px; border:1.5px solid var(--garis); border-radius:var(--r-sm); font-size:13px; font-family:inherit; }
.rcbx { display:flex; align-items:center; gap:7px; font-size:12.5px; font-weight:600; color:var(--abu); cursor:pointer; }
.rcbx input { accent-color:var(--teal); }
.gtable { width:100%; border-collapse:collapse; }
.gtable thead tr { background:var(--kanvas); }
.gtable th { padding:9px 10px; text-align:left; font-size:10.5px; font-weight:800; color:var(--abu-muda); text-transform:uppercase; letter-spacing:.4px; border-bottom:1px solid var(--garis); }
.gtable td { padding:9px 10px; border-bottom:1px solid var(--garis); vertical-align:middle; }
.gtable code { font-size:11px; color:var(--abu); background:var(--kanvas); padding:2px 6px; border-radius:5px; }
</style>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    var table = $('#tabel_rekap').DataTable({
        pageLength: 25,
        order: [[1, 'asc']],
        language: {
            search: "Cari:",
            lengthMenu: "Tampilkan _MENU_ siswa",
            info: "Menampilkan _START_–_END_ dari _TOTAL_ siswa",
            infoEmpty: "Tidak ada data",
            zeroRecords: "Tidak ada siswa yang cocok",
            paginate: { previous: "‹ Prev", next: "Next ›" }
        }
    });

    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        var row     = table.row(dataIndex).node();
        var fKelas  = $('#filter_kelas_rekap').val();
        var hanya   = $('#hanya_terisi').is(':checked');
        if (fKelas && $(row).data('kelas') !== fKelas) return false;
        if (hanya && String($(row).data('terisi')) !== '1') return false;
        return true;
    });

    $('#filter_kelas_rekap, #hanya_terisi').on('change', function() { table.draw(); });
});
</script>

</body>
</html>