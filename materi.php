<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

require_login();

$user_id = $_SESSION['user_id'];
$profil  = get_profil_siswa($user_id);

if (!$profil) {
    header('Location: pretest.php');
    exit;
}

$profil_gabungan = $profil['profil_gabungan'];
$level           = $profil['level_kemampuan'];
$profil_learning = $profil['profil_learning'];
$skor            = $profil['skor_pengetahuan'];

$label_profil = [
    'guided_step'       => 'Guided-Step Learner',
    'conceptual'        => 'Conceptual Learner',
    'practice_oriented' => 'Practice-Oriented Learner',
];
$label_level = [
    'beginner'     => 'Pemula',
    'intermediate' => 'Menengah',
    'advanced'     => 'Mahir',
];

// Ikon per tipe konten
$ikon_tipe = [
    'teori'     => 'icon-book-open',
    'langkah'   => 'icon-list-ordered',
    'evaluasi'  => 'icon-clipboard-list',
    'jobsheet'  => 'icon-paperclip',
    'tantangan' => 'icon-flame',
];

// Topik dari database
$topik_list = get_topik_list();

// Topik aktif
$topik_aktif = $_GET['topik'] ?? array_key_first($topik_list);
if (!array_key_exists($topik_aktif, $topik_list)) {
    $topik_aktif = array_key_first($topik_list);
}

// Ambil adaptation rule untuk profil + topik ini
$pdo  = db();
$stmt = $pdo->prepare("
    SELECT * FROM adaptation_rules
    WHERE profil_gabungan = ? AND topik = ?
    LIMIT 1
");
$stmt->execute([$profil_gabungan, $topik_aktif]);
$rule = $stmt->fetch();

// Ambil konten sesuai urutan dari rule
$konten_list = [];
if ($rule) {
    $urutan = json_decode($rule['urutan_content'], true);
    $wajib  = json_decode($rule['konten_wajib'], true) ?? [];
    $urutan_unik = array_unique($urutan);
    if (count($urutan_unik) > 0) {
        $placeholders = implode(',', array_fill(0, count($urutan_unik), '?'));
        $stmt2 = $pdo->prepare("SELECT * FROM `content` WHERE id IN ($placeholders)");
        $stmt2->execute($urutan_unik);
        $rows = $stmt2->fetchAll();
        $rows_by_id = array_column($rows, null, 'id');
        foreach ($urutan_unik as $id) {
            if (isset($rows_by_id[$id])) {
                $item = $rows_by_id[$id];
                $item['wajib'] = in_array($id, $wajib);
                $konten_list[] = $item;
            }
        }
    }
}

// Konten aktif yang sedang dibuka
$konten_id_aktif = isset($_GET['konten']) ? (int) $_GET['konten'] : ($konten_list[0]['id'] ?? 0);
$konten_aktif = null;
foreach ($konten_list as $k) {
    if ($k['id'] == $konten_id_aktif) {
        $konten_aktif = $k;
        break;
    }
}

// Log aktivitas buka materi
if ($konten_aktif) {
    log_aktivitas($user_id, 'buka_materi', $konten_aktif['id'], $topik_aktif, [
        'judul'  => $konten_aktif['judul'],
        'profil' => $profil_gabungan,
    ]);
}

// Hitung progress topik ini
$total_konten = count($konten_list);
$konten_no = 0;
foreach ($konten_list as $i => $k) {
    if ($k['id'] == $konten_id_aktif) {
        $konten_no = $i + 1;
        break;
    }
}
$progress = $total_konten > 0 ? round(($konten_no / $total_konten) * 100) : 0;

// Konten sebelum dan sesudah
$prev_id = null;
$next_id = null;
foreach ($konten_list as $i => $k) {
    if ($k['id'] == $konten_id_aktif) {
        $prev_id = $konten_list[$i - 1]['id'] ?? null;
        $next_id = $konten_list[$i + 1]['id'] ?? null;
        break;
    }
}

// Apakah konten tipe evaluasi?
$is_evaluasi   = $konten_aktif && $konten_aktif['tipe'] === 'evaluasi';
$soal_evaluasi = [];
if ($is_evaluasi && $konten_aktif) {
    $soal_evaluasi = json_decode($konten_aktif['isi'], true) ?? [];
}

// Halaman finish
$is_finish = isset($_GET['finish']);

// Konten yang sudah selesai dibaca
$stmt_dibuka = $pdo->prepare("
    SELECT DISTINCT content_id
    FROM activity_log
    WHERE user_id = ? AND tipe = 'selesai_materi'
");
$stmt_dibuka->execute([$user_id]);
$konten_dibuka = array_column($stmt_dibuka->fetchAll(), 'content_id');

// Evaluasi yang sudah dikerjakan
$stmt_done = $pdo->prepare("
    SELECT DISTINCT content_id
    FROM activity_log
    WHERE user_id = ? AND tipe = 'jawab_quiz'
");
$stmt_done->execute([$user_id]);
$evaluasi_selesai = array_column($stmt_done->fetchAll(), 'content_id');

// Timer: aktif jika konten biasa dan belum pernah diselesaikan
$pakai_timer = false;
if ($konten_aktif && !$is_evaluasi) {
    $pakai_timer = !in_array($konten_id_aktif, $konten_dibuka);
}
$durasi_timer = 35;

// Cek jobsheet submission
$is_jobsheet = $konten_aktif && $konten_aktif['tipe'] === 'jobsheet' && $konten_aktif['perlu_upload'];
$jobsheet_uploaded = false;
if ($is_jobsheet) {
    $stmt_js = $pdo->prepare("SELECT id, file_original_name, nilai FROM jobsheet_submissions WHERE user_id = ? AND content_id = ?");
    $stmt_js->execute([$user_id, $konten_id_aktif]);
    $jobsheet_submission = $stmt_js->fetch();
    $jobsheet_uploaded   = (bool) $jobsheet_submission;
}

// Topik berikutnya
$topik_keys = array_keys($topik_list);
$topik_idx  = array_search($topik_aktif, $topik_keys);
$next_topik = $topik_keys[$topik_idx + 1] ?? null;

$page_title   = ($konten_aktif['judul'] ?? 'Materi') . ' — AdaptLearn PRE';
$topbar_aktif = 'materi';
include __DIR__ . '/includes/topbar_siswa.php';
?>

<div class="crumb">
    <a href="home.php">Beranda</a>
    <span class="sep">›</span>
    <a href="materi.php?topik=<?= urlencode($topik_aktif) ?>"><?= htmlspecialchars($topik_list[$topik_aktif] ?? $topik_aktif) ?></a>
    <?php if ($konten_aktif && !$is_finish): ?>
        <span class="sep">›</span>
        <span class="now"><?= htmlspecialchars($konten_aktif['judul']) ?></span>
    <?php endif; ?>
</div>

<div class="layout">

    <!-- SIDEBAR -->
    <aside class="side">

        <!-- Navigasi topik -->
        <div class="side-card">
            <div class="side-h"><i class="icon-library"></i> Topik</div>
            <nav class="side-nav">
                <?php foreach (get_topik_tree() as $parent): ?>
                    <?php if (!empty($parent['children'])): ?>
                        <div class="grup"><?= htmlspecialchars($parent['nama']) ?></div>
                    <?php else: ?>
                        <a href="materi.php?topik=<?= urlencode($parent['slug']) ?>" class="<?= $parent['slug'] === $topik_aktif ? 'on' : '' ?>">
                            <?= htmlspecialchars($parent['nama']) ?>
                        </a>
                    <?php endif; ?>
                    <?php foreach ($parent['children'] as $child): ?>
                        <a href="materi.php?topik=<?= urlencode($child['slug']) ?>" class="anak <?= $child['slug'] === $topik_aktif ? 'on' : '' ?>">
                            <?= htmlspecialchars($child['nama']) ?>
                        </a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </nav>
        </div>

        <!-- Progress topik aktif -->
        <div class="side-card">
            <div class="side-h"><i class="icon-chart-line"></i> Progress topik</div>
            <div class="side-pad">
                <div style="display:flex;justify-content:space-between;font-size:11.5px;color:var(--abu-muda);font-weight:700;margin-bottom:7px">
                    <span><?= $konten_no ?> / <?= $total_konten ?> materi</span>
                    <span style="color:var(--biru)"><?= $progress ?>%</span>
                </div>
                <div class="bar tipis"><i style="width:<?= $progress ?>%"></i></div>
            </div>
        </div>

        <!-- Daftar konten adaptif -->
        <div class="side-card">
            <div class="side-h"><i class="icon-list-ordered"></i> Urutan materi</div>
            <nav class="side-nav">
                <?php foreach ($konten_list as $k): ?>
                    <?php
                    $sudah = $k['tipe'] === 'evaluasi'
                        ? in_array($k['id'], $evaluasi_selesai)
                        : in_array($k['id'], $konten_dibuka);
                    ?>
                    <a href="materi.php?topik=<?= urlencode($topik_aktif) ?>&konten=<?= $k['id'] ?>"
                       class="<?= $k['id'] == $konten_id_aktif ? 'on' : '' ?>">
                        <span class="tipe tipe-<?= $k['tipe'] ?>"><?= strtoupper($k['tipe']) ?></span>
                        <span style="flex:1;min-width:0"><?= htmlspecialchars($k['judul']) ?></span>
                        <?php if ($sudah): ?>
                            <i class="icon-circle-check tick" title="Sudah selesai"></i>
                        <?php elseif ($k['wajib']): ?>
                            <i class="icon-asterisk wajib" title="Wajib"></i>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

    </aside>

    <!-- KONTEN UTAMA -->
    <main class="main">

        <?php if ($is_finish): ?>

            <div class="card tengah" style="padding:40px 24px">
                <div style="width:72px;height:72px;border-radius:50%;background:var(--teal-muda);color:var(--teal);
                            display:grid;place-items:center;font-size:32px;margin:0 auto 18px">
                    <i class="icon-party-popper"></i>
                </div>
                <h2 style="font-size:20px;font-weight:800;letter-spacing:-.4px;margin-bottom:10px">Semua materi selesai</h2>
                <p style="font-size:13.5px;color:var(--abu);line-height:1.6;margin-bottom:24px">
                    Kamu sudah menyelesaikan seluruh materi pembelajaran.<br>
                    Langkah berikutnya: mengerjakan post-test.
                </p>
                <a href="posttest.php" class="btn btn-3" style="display:inline-flex">
                    Kerjakan post-test <i class="icon-arrow-right"></i>
                </a>
                <div style="margin-top:14px">
                    <a href="profil.php" style="color:var(--abu-muda);font-size:12.5px;font-weight:600;text-decoration:none">
                        Lihat profil &amp; progress saya
                    </a>
                </div>
            </div>

        <?php elseif ($konten_aktif): ?>

            <div class="card">
                <div class="rata" style="margin-bottom:14px;flex-wrap:wrap">
                    <span class="tipe tipe-<?= $konten_aktif['tipe'] ?>"><?= strtoupper($konten_aktif['tipe']) ?></span>
                    <span style="font-size:12px;color:var(--abu-muda);font-weight:600">
                        <?= htmlspecialchars($topik_list[$topik_aktif]) ?>
                    </span>
                    <span style="font-size:12px;color:var(--abu-muda);font-weight:600;margin-left:auto">
                        <?= $konten_no ?>/<?= $total_konten ?>
                    </span>
                </div>

                <h1 class="judul-konten"><?= htmlspecialchars($konten_aktif['judul']) ?></h1>

                <?php if ($is_evaluasi && $soal_evaluasi): ?>

                    <form method="POST" action="api/evaluasi.php">
                        <input type="hidden" name="user_id"         value="<?= $user_id ?>">
                        <input type="hidden" name="content_id"      value="<?= $konten_id_aktif ?>">
                        <input type="hidden" name="topik"           value="<?= htmlspecialchars($topik_aktif) ?>">
                        <input type="hidden" name="profil_gabungan" value="<?= htmlspecialchars($profil_gabungan) ?>">

                        <?php foreach ($soal_evaluasi as $i => $soal): ?>
                            <div class="soal">
                                <div class="soal-tx"><?= ($i + 1) ?>. <?= htmlspecialchars($soal['soal']) ?></div>
                                <ul class="opsi">
                                    <?php foreach ($soal['opsi'] as $huruf => $teks): ?>
                                        <li>
                                            <label>
                                                <input type="radio" name="jawaban[<?= $i ?>]" value="<?= $huruf ?>" required>
                                                <span><b><?= $huruf ?>.</b> <?= htmlspecialchars($teks) ?></span>
                                            </label>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>

                        <button type="submit" class="btn btn-1 btn-full">
                            <i class="icon-send"></i> Kirim jawaban
                        </button>
                    </form>

                <?php else: ?>

                    <div class="isi">
                        <?php if (!empty($konten_aktif['file_path'])): ?>
                            <?php $ext = strtolower(pathinfo($konten_aktif['file_path'], PATHINFO_EXTENSION)); ?>
                            <?php if ($ext === 'pdf'): ?>
                                <embed src="/<?= htmlspecialchars($konten_aktif['file_path']) ?>" type="application/pdf"
                                       style="width:100%;height:70vh;min-height:420px;border:1px solid var(--garis);border-radius:var(--r-sm)">
                                <p style="margin-top:12px;font-size:12.5px;color:var(--abu-muda)">
                                    Tidak bisa tampil?
                                    <a href="/<?= htmlspecialchars($konten_aktif['file_path']) ?>" target="_blank" style="color:var(--biru);font-weight:700">Buka PDF di tab baru</a>
                                </p>
                            <?php else: ?>
                                <a href="/<?= htmlspecialchars($konten_aktif['file_path']) ?>" target="_blank" class="btn btn-1" style="display:inline-flex">
                                    <i class="icon-download"></i> Unduh: <?= htmlspecialchars(basename($konten_aktif['file_path'])) ?>
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <?= $konten_aktif['isi'] ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_jobsheet): ?>
                        <div id="upload-box" style="background:var(--biru-muda);border:2px dashed var(--biru-200);
                                                    border-radius:var(--r-md);padding:18px;margin-top:22px">
                            <div style="font-size:13.5px;font-weight:800;margin-bottom:10px;display:flex;align-items:center;gap:7px">
                                <i class="icon-upload" style="color:var(--biru)"></i> Upload hasil pengukuran
                            </div>

                            <?php if ($jobsheet_uploaded): ?>
                                <div class="tags" id="upload-status">
                                    <span class="tag ok"><i class="icon-circle-check"></i> <?= htmlspecialchars($jobsheet_submission['file_original_name']) ?></span>
                                    <?php if ($jobsheet_submission['nilai'] !== null): ?>
                                        <span class="tag ok"><i class="icon-award"></i> Nilai: <?= $jobsheet_submission['nilai'] ?></span>
                                    <?php endif; ?>
                                </div>
                                <p style="font-size:12px;color:var(--abu-muda);margin-top:8px">Upload ulang jika ingin mengganti file.</p>
                            <?php else: ?>
                                <div class="tags" id="upload-status">
                                    <span class="tag" style="background:var(--amber-muda);color:#B45309">
                                        <i class="icon-triangle-alert"></i> Belum upload — jobsheet belum selesai
                                    </span>
                                </div>
                            <?php endif; ?>

                            <div class="rata" style="margin-top:12px;flex-wrap:wrap">
                                <input type="file" id="file-jobsheet" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" style="display:none">
                                <button type="button" class="btn btn-2 btn-sm" onclick="document.getElementById('file-jobsheet').click()">
                                    <i class="icon-folder-open"></i> Pilih file
                                </button>
                                <button type="button" class="btn btn-1 btn-sm" id="btn-upload" style="display:none" onclick="uploadJobsheet()">
                                    <i class="icon-upload"></i> Upload sekarang
                                </button>
                                <span id="file-label" style="font-size:12px;color:var(--abu);font-weight:600"></span>
                            </div>
                            <div id="upload-msg" style="margin-top:8px;font-size:12.5px;font-weight:600"></div>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>

            <!-- Navigasi prev/next -->
            <div class="nav-btn">
                <?php if ($prev_id): ?>
                    <a href="materi.php?topik=<?= urlencode($topik_aktif) ?>&konten=<?= $prev_id ?>" class="btn btn-2">
                        <i class="icon-arrow-left"></i> Sebelumnya
                    </a>
                <?php else: ?>
                    <span class="btn btn-off"><i class="icon-arrow-left"></i> Sebelumnya</span>
                <?php endif; ?>

                <?php
                if ($next_id) {
                    $href_next  = 'materi.php?topik=' . urlencode($topik_aktif) . '&konten=' . $next_id;
                    $label_next = 'Selanjutnya';
                    $kelas_next = 'btn-1';
                } elseif ($next_topik) {
                    $href_next  = 'materi.php?topik=' . urlencode($next_topik);
                    $label_next = 'Topik berikutnya';
                    $kelas_next = 'btn-1';
                } else {
                    $href_next  = 'materi.php?finish=1';
                    $label_next = 'Selesai semua materi';
                    $kelas_next = 'btn-3';
                }
                ?>

                <?php if ($pakai_timer): ?>
                    <a href="<?= $href_next ?>" class="btn btn-off" id="btn-next"
                       data-href="<?= $href_next ?>"
                       data-label="<?= htmlspecialchars($label_next) ?>"
                       data-kelas="<?= $kelas_next ?>">
                        <span id="btn-next-tx"><?= htmlspecialchars($label_next) ?> (<?= $durasi_timer ?>s)</span>
                    </a>
                <?php else: ?>
                    <a href="<?= $href_next ?>" class="btn <?= $kelas_next ?>">
                        <?= htmlspecialchars($label_next) ?> <i class="icon-arrow-right"></i>
                    </a>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <div class="card">
                <div class="kosong">
                    <i class="icon-file-question"></i>
                    <b>Konten tidak ditemukan</b>
                    <p>Materi yang kamu cari tidak ada di jalur belajarmu.</p>
                    <a href="materi.php" class="btn btn-1 btn-sm" style="margin-top:16px">
                        <i class="icon-book-open"></i> Kembali ke materi
                    </a>
                </div>
            </div>

        <?php endif; ?>

    </main>

</div>

<?php if ($is_jobsheet): ?>
<script>
function uploadJobsheet() {
    var fileInput = document.getElementById('file-jobsheet');
    var file = fileInput.files[0];
    if (!file) return;

    var btn = document.getElementById('btn-upload');
    var msg = document.getElementById('upload-msg');
    btn.disabled = true;
    btn.innerHTML = 'Mengupload…';
    msg.textContent = '';

    var fd = new FormData();
    fd.append('file', file);
    fd.append('content_id', <?= $konten_id_aktif ?>);
    fd.append('topik', '<?= htmlspecialchars($topik_aktif, ENT_QUOTES) ?>');

    fetch('/api/upload_jobsheet.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                document.getElementById('upload-status').innerHTML =
                    '<span class="tag ok"><i class="icon-circle-check"></i> ' + data.filename + '</span>';
                msg.style.color = 'var(--teal)';
                msg.textContent = 'Upload berhasil.';
                btn.innerHTML = '<i class="icon-check"></i> Terupload';
            } else {
                msg.style.color = 'var(--coral)';
                msg.textContent = data.msg || 'Upload gagal.';
                btn.disabled = false;
                btn.innerHTML = '<i class="icon-upload"></i> Upload sekarang';
            }
        })
        .catch(function() {
            msg.style.color = 'var(--coral)';
            msg.textContent = 'Koneksi gagal, coba lagi.';
            btn.disabled = false;
            btn.innerHTML = '<i class="icon-upload"></i> Upload sekarang';
        });
}

document.getElementById('file-jobsheet').addEventListener('change', function() {
    if (this.files[0]) {
        document.getElementById('file-label').textContent = this.files[0].name;
        document.getElementById('btn-upload').style.display = 'inline-flex';
    }
});
</script>
<?php endif; ?>

<?php if ($pakai_timer): ?>
<script>
(function() {
    var btn = document.getElementById('btn-next');
    if (!btn) return;

    var tx        = document.getElementById('btn-next-tx');
    var sisa      = <?= $durasi_timer ?>;
    var href      = btn.getAttribute('data-href');
    var label     = btn.getAttribute('data-label');
    var kelas     = btn.getAttribute('data-kelas');
    var contentId = <?= $konten_id_aktif ?>;
    var topik     = '<?= htmlspecialchars($topik_aktif, ENT_QUOTES) ?>';

    var interval = setInterval(function() {
        sisa--;
        if (sisa <= 0) {
            clearInterval(interval);

            var fd = new FormData();
            fd.append('content_id', contentId);
            fd.append('topik', topik);
            fetch('/api/selesai_materi.php', { method: 'POST', body: fd });

            tx.innerHTML = label + ' <i class="icon-arrow-right"></i>';
            btn.classList.remove('btn-off');
            btn.classList.add(kelas);
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = href;
            });
        } else {
            tx.textContent = label + ' (' + sisa + 's)';
        }
    }, 1000);
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/bottomnav_siswa.php'; ?>
</body>
</html>
