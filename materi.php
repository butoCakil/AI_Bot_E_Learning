<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/syarat_konten.php';

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
$topik_list = array_filter(get_topik_list(), function ($slug) {
    return empty(get_sub_topik($slug));   // buang parent yang punya sub-topik
}, ARRAY_FILTER_USE_KEY);

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

// Gate sekuensial: konten hanya bisa dibuka bila semua konten sebelumnya sudah selesai
// (konten yang sudah pernah diselesaikan tetap boleh dibuka ulang)
if ($konten_aktif) {
    $prasyarat_ev = cek_prasyarat_evaluasi(
        $user_id, $profil_gabungan, $topik_aktif, (int) $konten_id_aktif
    );
    if (!$prasyarat_ev['boleh']) {
        $konten_tujuan = (int) $prasyarat_ev['kurang'][0];
        $_SESSION['flash_gate'] = 'Selesaikan materi sebelumnya dulu ('
            . $prasyarat_ev['selesai'] . '/' . $prasyarat_ev['total']
            . ') sebelum membuka materi ini.';
        header('Location: materi.php?topik=' . urlencode($topik_aktif) . '&konten=' . $konten_tujuan);
        exit;
    }
}

// Log aktivitas buka materi
if ($konten_aktif) {
    log_aktivitas($user_id, 'buka_materi', $konten_aktif['id'], $topik_aktif, [
        'judul'  => $konten_aktif['judul'],
        'profil' => $profil_gabungan,
    ]);
}

// ── Syarat tuntas konten aktif (timer, evaluasi, upload, tabel) ──
// Dihitung di server; selesai_materi dicatat otomatis bila semua terpenuhi.
$syarat = ['selesai' => false, 'semua_ok' => true, 'item' => []];
if ($konten_aktif) {
    $syarat = tandai_selesai_jika_lengkap((int) $user_id, $konten_aktif, $topik_aktif);
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

// ── Data tampilan syarat ─────────────────────────────────────────
$durasi_timer = DURASI_BACA;
$sisa_timer   = (int) ($syarat['item']['timer']['sisa'] ?? 0);
$perlu_upload = isset($syarat['item']['upload']);
$ada_tabel    = isset($syarat['item']['tabel']);

// Upload (berlaku untuk tipe apa pun bila perlu_upload dicentang)
$jobsheet_submission = null;
if ($perlu_upload) {
    $stmt_js = $pdo->prepare("SELECT id, file_original_name, nilai FROM jobsheet_submissions WHERE user_id = ? AND content_id = ?");
    $stmt_js->execute([$user_id, $konten_id_aktif]);
    $jobsheet_submission = $stmt_js->fetch() ?: null;
}
$jobsheet_uploaded = (bool) $jobsheet_submission;

// Isian tabel yang sudah tersimpan
$jawaban_tabel = [];
if ($ada_tabel) {
    $jawaban_tabel = ti_ambil_jawaban((int) $user_id, (int) $konten_id_aktif)['jawaban'] ?? [];
}

// Evaluasi yang sudah dikirim → tampilkan jawaban, form dikunci
$hasil_eval = null;
if ($is_evaluasi) {
    $stmt_he = $pdo->prepare("SELECT hasil_soal, skor, total, persentase, created_at FROM evaluasi_results WHERE user_id = ? AND content_id = ? LIMIT 1");
    $stmt_he->execute([$user_id, $konten_id_aktif]);
    $hasil_eval = $stmt_he->fetch() ?: null;
    if ($hasil_eval) {
        $hasil_eval['hasil_soal'] = json_decode($hasil_eval['hasil_soal'], true) ?: [];
    }
}

$jsf = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;

// Topik berikutnya
$topik_keys = array_keys($topik_list);
$topik_idx  = array_search($topik_aktif, $topik_keys);
$next_topik = $topik_keys[$topik_idx + 1] ?? null;

$page_title   = ($konten_aktif['judul'] ?? 'Materi') . ' — AdaptLearn PRE';
$topbar_aktif = 'materi';
include __DIR__ . '/includes/topbar_siswa.php';
?>

<?php if (!empty($_SESSION['flash_gate'])): ?>
    <div class="wrap" style="padding-top:12px">
        <div class="alert warn">
            <i class="icon-lock"></i> <?= htmlspecialchars($_SESSION['flash_gate']) ?>
        </div>
    </div>
    <?php unset($_SESSION['flash_gate']); ?>
<?php endif; ?>

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
                    $boleh = cek_prasyarat_evaluasi(
                        $user_id, $profil_gabungan, $topik_aktif, (int) $k['id']
                    )['boleh'];
                    ?>
                    <?php if ($boleh): ?>
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
                    <?php else: ?>
                        <span class="terkunci" title="Selesaikan materi sebelumnya dulu"
                              style="opacity:.45;cursor:not-allowed;display:flex;align-items:center;gap:8px;padding:10px 12px">
                            <span class="tipe tipe-<?= $k['tipe'] ?>"><?= strtoupper($k['tipe']) ?></span>
                            <span style="flex:1;min-width:0"><?= htmlspecialchars($k['judul']) ?></span>
                            <i class="icon-lock"></i>
                        </span>
                    <?php endif; ?>
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

                <?php if ($is_evaluasi && $hasil_eval): ?>

                    <!-- Evaluasi sudah dikirim: tampilkan jawaban, tidak bisa diulang -->
                    <div style="background:var(--teal-muda);border-radius:var(--r-md);padding:14px 16px;margin-bottom:18px">
                        <div style="font-size:13.5px;font-weight:800;display:flex;align-items:center;gap:7px">
                            <i class="icon-circle-check" style="color:var(--teal)"></i> Evaluasi sudah kamu kirim
                        </div>
                        <div style="font-size:12.5px;color:var(--abu);margin-top:6px;line-height:1.6">
                            Dikirim <?= htmlspecialchars(date('d-m-Y H:i', strtotime($hasil_eval['created_at']))) ?> ·
                            Skor <b><?= (int) $hasil_eval['skor'] ?>/<?= (int) $hasil_eval['total'] ?></b>
                            (<?= (int) $hasil_eval['persentase'] ?>%).
                            Evaluasi hanya bisa dikerjakan satu kali.
                        </div>
                        <a href="hasil_evaluasi.php?konten=<?= (int) $konten_id_aktif ?>"
                           style="display:inline-block;margin-top:8px;color:var(--biru);font-size:12.5px;font-weight:700;text-decoration:none">
                            Lihat halaman hasil evaluasi <i class="icon-arrow-right"></i>
                        </a>
                    </div>

                    <?php foreach ($hasil_eval['hasil_soal'] as $i => $hs): ?>
                        <div class="soal">
                            <div class="soal-tx">
                                <?= ($i + 1) ?>. <?= htmlspecialchars($hs['soal'] ?? '') ?>
                                <?php if (!empty($hs['benar'])): ?>
                                    <span class="tag ok" style="margin-left:6px"><i class="icon-circle-check"></i> Benar</span>
                                <?php else: ?>
                                    <span class="tag" style="margin-left:6px;background:#FEE2E2;color:#B91C1C"><i class="icon-circle-alert"></i> Salah</span>
                                <?php endif; ?>
                            </div>
                            <ul class="opsi">
                                <?php foreach (($hs['opsi'] ?? []) as $huruf => $teks):
                                    $dipilih = (string) ($hs['jawaban'] ?? '') === (string) $huruf; ?>
                                    <li>
                                        <label style="cursor:default;<?= $dipilih ? 'background:var(--biru-muda);border-color:var(--biru)' : 'opacity:.65' ?>">
                                            <input type="radio" disabled <?= $dipilih ? 'checked' : '' ?>>
                                            <span><b><?= htmlspecialchars($huruf) ?>.</b> <?= htmlspecialchars($teks) ?><?= $dipilih ? ' <i style="color:var(--biru)">— jawabanmu</i>' : '' ?></span>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>

                <?php elseif ($is_evaluasi && $soal_evaluasi): ?>

                    <form method="POST" action="api/evaluasi.php"
                          onsubmit="var b=this.querySelector('button[type=submit]'); b.disabled=true; b.innerHTML='Mengirim…';">
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

                        <p style="font-size:12px;color:var(--abu-muda);margin:4px 0 10px">
                            Jawab semua soal. Evaluasi hanya bisa dikirim <b>satu kali</b>.
                        </p>
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

                    <?php if ($ada_tabel): ?>
                        <div class="rata" style="margin-top:14px;flex-wrap:wrap;gap:10px">
                            <button type="button" class="btn btn-1 btn-sm" id="ti-simpan">
                                <i class="icon-check"></i> Simpan isian tabel
                            </button>
                            <span id="ti-status" style="font-size:12.5px;font-weight:600;color:var(--abu)"></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($perlu_upload): ?>
                        <div id="upload-box" style="background:var(--biru-muda);border:2px dashed var(--biru-200);
                                                    border-radius:var(--r-md);padding:18px;margin-top:22px">
                            <div style="font-size:13.5px;font-weight:800;margin-bottom:10px;display:flex;align-items:center;gap:7px">
                                <i class="icon-upload" style="color:var(--biru)"></i>
                                <?= $konten_aktif['tipe'] === 'jobsheet' ? 'Upload hasil pengukuran' : 'Upload hasil kerja' ?>
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
                                        <i class="icon-triangle-alert"></i> Belum upload — materi ini belum tuntas
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

            <?php if (!empty($syarat['item']) && !$syarat['semua_ok']): ?>
                <!-- Penanda syarat (diisi JS) -->
                <div id="syarat-box" style="display:flex;flex-wrap:wrap;gap:8px;margin:14px 2px 0"></div>
            <?php endif; ?>

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
                $boleh_lanjut = $syarat['semua_ok'];
                ?>

                <!-- Terkunci = href "#": tidak bisa lolos walau JS gagal -->
                <a href="<?= $boleh_lanjut ? htmlspecialchars($href_next) : '#' ?>" id="btn-next"
                   class="btn <?= $boleh_lanjut ? $kelas_next : 'btn-off' ?>"
                   aria-disabled="<?= $boleh_lanjut ? 'false' : 'true' ?>"
                   onclick="return klikNext(event)">
                    <span id="btn-next-tx"><?= htmlspecialchars($label_next) ?><?= (!$boleh_lanjut && $sisa_timer > 0) ? ' (' . $sisa_timer . 's)' : '' ?></span>
                    <i class="icon-arrow-right"></i>
                </a>
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

<?php if ($konten_aktif && !$is_finish): ?>

<style>
    .isi table.tabel-input td.ti-isi, .isi table.tabel-input th.ti-isi { padding: 4px 6px; }
    .ti-in {
        width: 100%; min-width: 70px; padding: 7px 9px; font: inherit; font-size: 13px;
        border: 1.5px solid var(--biru-200); border-radius: 8px; background: #fff;
    }
    .ti-in:focus { outline: none; border-color: var(--biru); }
</style>

<div id="modalSyarat" class="modal-bg" onclick="if(event.target===this) tutupModalSyarat()">
    <div class="modal-kotak">
        <div class="modal-ikon"><i class="icon-lock"></i></div>
        <h3>Belum bisa lanjut</h3>
        <p>Selesaikan dulu:</p>
        <ul id="modal-syarat-daftar" style="text-align:left;margin:8px 0 16px 20px;font-size:13.5px;line-height:1.8"></ul>
        <button type="button" class="btn btn-1" onclick="tutupModalSyarat()">Mengerti</button>
    </div>
</div>

<script>
(function () {
    var SYARAT = <?= json_encode((object) $syarat['item'], $jsf) ?>;
    var CID    = <?= (int) $konten_id_aktif ?>;
    var TOPIK  = <?= json_encode($topik_aktif, $jsf) ?>;
    var LABEL  = <?= json_encode($label_next, $jsf) ?>;
    var HREF   = <?= json_encode($href_next, $jsf) ?>;
    var KELAS  = <?= json_encode($kelas_next, $jsf) ?>;
    var IKON   = { timer: 'icon-hourglass', evaluasi: 'icon-clipboard-list', upload: 'icon-upload', tabel: 'icon-clipboard-check' };

    var btn   = document.getElementById('btn-next');
    var btnTx = document.getElementById('btn-next-tx');
    var box   = document.getElementById('syarat-box');

    function semuaOk() {
        for (var k in SYARAT) { if (!SYARAT[k].ok) return false; }
        return true;
    }
    function teks(k, it) {
        if (k === 'timer' && !it.ok) return 'Baca materi (' + it.sisa + ' detik lagi)';
        if (k === 'tabel' && !it.ok) return 'Isi tabel (' + it.kosong + ' sel kosong)';
        return it.label;
    }
    function render() {
        var ok = semuaOk();
        if (box) {
            var h = '';
            for (var k in SYARAT) {
                var it = SYARAT[k];
                h += '<span class="tag' + (it.ok ? ' ok' : '') + '"'
                   + (it.ok ? '' : ' style="background:var(--amber-muda);color:#B45309"') + '>'
                   + '<i class="' + (it.ok ? 'icon-circle-check' : (IKON[k] || 'icon-circle-alert')) + '"></i> '
                   + teks(k, it) + '</span>';
            }
            box.innerHTML = h;
        }
        if (!btn) return;
        if (ok) {
            btn.setAttribute('href', HREF);
            btn.className = 'btn ' + KELAS;
            btn.setAttribute('aria-disabled', 'false');
            btnTx.textContent = LABEL;
        } else {
            btn.setAttribute('href', '#');
            btn.className = 'btn btn-off';
            btn.setAttribute('aria-disabled', 'true');
            btnTx.textContent = (SYARAT.timer && !SYARAT.timer.ok) ? LABEL + ' (' + SYARAT.timer.sisa + 's)' : LABEL;
        }
    }

    window.terapkanSyarat = function (item) {
        if (item) { SYARAT = item; render(); jalankanTimer(); }
    };
    window.klikNext = function (e) {
        if (semuaOk()) return true;
        e.preventDefault();
        var daftar = '';
        for (var k in SYARAT) { if (!SYARAT[k].ok) daftar += '<li>' + teks(k, SYARAT[k]) + '</li>'; }
        document.getElementById('modal-syarat-daftar').innerHTML = daftar;
        document.getElementById('modalSyarat').classList.add('on');
        return false;
    };
    window.tutupModalSyarat = function () {
        document.getElementById('modalSyarat').classList.remove('on');
    };
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') tutupModalSyarat();
    });

    // ── Timer baca (hitungan tampilan; keputusan tetap di server) ──
    var ivTimer = null;
    function laporSelesai() {
        var fd = new FormData();
        fd.append('content_id', CID);
        fd.append('topik', TOPIK);
        fetch('/api/selesai_materi.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (d.ok) terapkanSyarat(d.item); })
            .catch(function () { setTimeout(laporSelesai, 3000); });
    }
    function jalankanTimer() {
        if (ivTimer || !SYARAT.timer || SYARAT.timer.ok) return;
        ivTimer = setInterval(function () {
            SYARAT.timer.sisa--;
            if (SYARAT.timer.sisa <= 0) {
                clearInterval(ivTimer);
                ivTimer = null;
                SYARAT.timer.sisa = 0;
                laporSelesai();
            } else {
                render();
            }
        }, 1000);
    }

    // ── Tabel input: sel .ti-isi → kotak isian ──
    var tables = document.querySelectorAll('.isi table.tabel-input');
    if (tables.length) {
        var SIMPAN = <?= json_encode((object) $jawaban_tabel, $jsf) ?>;
        var inputs = [];
        Array.prototype.forEach.call(tables, function (tb, ti) {
            Array.prototype.forEach.call(tb.querySelectorAll('tr'), function (tr, ri) {
                var ci = 0;
                Array.prototype.forEach.call(tr.children, function (cell) {
                    if (cell.tagName !== 'TD' && cell.tagName !== 'TH') return;
                    if (cell.classList.contains('ti-isi')) {
                        var key = 't' + ti + '-r' + ri + '-c' + ci;
                        var ph  = cell.textContent.replace(/\u00a0/g, ' ').trim();
                        var inp = document.createElement('input');
                        inp.type = 'text';
                        inp.className = 'ti-in';
                        inp.maxLength = 200;
                        inp.setAttribute('data-key', key);
                        inp.placeholder = ph || 'isi di sini';
                        inp.value = SIMPAN[key] || '';
                        cell.textContent = '';
                        cell.appendChild(inp);
                        inputs.push(inp);
                    }
                    ci++;
                });
            });
        });

        var stat = document.getElementById('ti-status');
        var tSimpan = null, kotor = false;
        function tulis(t, warna) {
            if (stat) { stat.textContent = t; stat.style.color = warna || 'var(--abu)'; }
        }
        function jumlahKosong() {
            var n = 0;
            inputs.forEach(function (i) { if (i.value.trim() === '') n++; });
            return n;
        }
        function simpan() {
            clearTimeout(tSimpan);
            var data = {};
            inputs.forEach(function (i) { data[i.getAttribute('data-key')] = i.value; });
            var fd = new FormData();
            fd.append('content_id', CID);
            fd.append('topik', TOPIK);
            fd.append('jawaban', JSON.stringify(data));
            tulis('Menyimpan…');
            fetch('/api/simpan_tabel.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.ok) {
                        kotor = false;
                        tulis(d.lengkap ? 'Tersimpan — semua sel terisi' : 'Tersimpan — ' + d.kosong + ' sel masih kosong',
                              d.lengkap ? 'var(--teal)' : '#B45309');
                        terapkanSyarat(d.item);
                    } else {
                        tulis(d.msg || 'Gagal menyimpan', 'var(--coral)');
                    }
                })
                .catch(function () { tulis('Koneksi gagal, coba lagi.', 'var(--coral)'); });
        }
        inputs.forEach(function (i) {
            i.addEventListener('input', function () { kotor = true; tulis('Belum disimpan…', '#B45309'); });
            i.addEventListener('change', function () { clearTimeout(tSimpan); tSimpan = setTimeout(simpan, 400); });
        });
        var bs = document.getElementById('ti-simpan');
        if (bs) bs.addEventListener('click', simpan);
        window.addEventListener('beforeunload', function (e) {
            if (kotor) { e.preventDefault(); e.returnValue = ''; }
        });
        var awal = jumlahKosong();
        tulis(awal === 0 ? 'Semua sel sudah terisi' : awal + ' sel belum diisi',
              awal === 0 ? 'var(--teal)' : 'var(--abu)');
    }

    render();
    jalankanTimer();
})();
</script>

<?php if ($perlu_upload): ?>
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
    fd.append('content_id', <?= (int) $konten_id_aktif ?>);
    fd.append('topik', <?= json_encode($topik_aktif, $jsf) ?>);

    fetch('/api/upload_jobsheet.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.ok) {
                var span = document.createElement('span');
                span.className = 'tag ok';
                span.innerHTML = '<i class="icon-circle-check"></i> ';
                span.appendChild(document.createTextNode(data.filename));
                var st = document.getElementById('upload-status');
                st.innerHTML = '';
                st.appendChild(span);
                msg.style.color = 'var(--teal)';
                msg.textContent = 'Upload berhasil.';
                btn.innerHTML = '<i class="icon-check"></i> Terupload';
                if (window.terapkanSyarat) terapkanSyarat(data.item);
            } else {
                msg.style.color = 'var(--coral)';
                msg.textContent = data.msg || 'Upload gagal.';
                btn.disabled = false;
                btn.innerHTML = '<i class="icon-upload"></i> Upload sekarang';
            }
        })
        .catch(function () {
            msg.style.color = 'var(--coral)';
            msg.textContent = 'Koneksi gagal, coba lagi.';
            btn.disabled = false;
            btn.innerHTML = '<i class="icon-upload"></i> Upload sekarang';
        });
}

document.getElementById('file-jobsheet').addEventListener('change', function () {
    if (this.files[0]) {
        document.getElementById('file-label').textContent = this.files[0].name;
        document.getElementById('btn-upload').style.display = 'inline-flex';
    }
});
</script>
<?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/includes/bottomnav_siswa.php'; ?>
</body>
</html>
