<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

require_login();

$user_id = $_SESSION['user_id'];
$profil = get_profil_siswa($user_id);

if (!$profil) {
    header('Location: pretest.php');
    exit;
}

$profil_gabungan = $profil['profil_gabungan'];
$level = $profil['level_kemampuan'];
$profil_learning = $profil['profil_learning'];
$skor = $profil['skor_pengetahuan'];

$label_profil = [
    'guided_step' => 'Guided-Step Learner',
    'conceptual' => 'Conceptual Learner',
    'practice_oriented' => 'Practice-Oriented Learner',
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

// Topik yang tersedia
$topik_list = [
    'dioda' => 'Dioda',
    'transistor' => 'Transistor',
    'catu_daya' => 'Catu Daya',
];

// Topik aktif
$topik_aktif = $_GET['topik'] ?? 'dioda';
if (!array_key_exists($topik_aktif, $topik_list)) {
    $topik_aktif = 'dioda';
}

// Ambil adaptation rule untuk profil + topik ini
$pdo = db();
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
    $wajib = json_decode($rule['konten_wajib'], true) ?? [];
    // Hapus duplikat tapi pertahankan urutan
    $urutan_unik = array_unique($urutan);
    if (count($urutan_unik) > 0) {
        $placeholders = implode(',', array_fill(0, count($urutan_unik), '?'));
        $stmt2 = $pdo->prepare("SELECT * FROM `content` WHERE id IN ($placeholders)");
        $stmt2->execute($urutan_unik);
        $rows = $stmt2->fetchAll();
        // Susun ulang sesuai urutan
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
        'judul' => $konten_aktif['judul'],
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
$is_evaluasi = $konten_aktif && $konten_aktif['tipe'] === 'evaluasi';
$soal_evaluasi = [];
if ($is_evaluasi && $konten_aktif) {
    $soal_evaluasi = json_decode($konten_aktif['isi'], true) ?? [];
}

// Halaman finish
$is_finish = isset($_GET['finish']);

// Konten yang sudah selesai dibaca (tombol next diklik)
$stmt_dibuka = $pdo->prepare("
    SELECT DISTINCT content_id 
    FROM activity_log 
    WHERE user_id = ? AND tipe = 'selesai_materi'
");
$stmt_dibuka->execute([$user_id]);
$konten_dibuka = array_column($stmt_dibuka->fetchAll(), 'content_id');

// Evaluasi yang sudah dikerjakan (kirim jawaban)
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
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($konten_aktif['judul'] ?? 'Materi') ?> — AdaptLearn PRE</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }

        /* ── TOP BAR ── */
        .topbar {
            background: #0f3460;
            color: #fff;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .topbar-left {
            font-weight: 700;
            font-size: 16px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
        }

        .badge-profil {
            background: rgba(255, 255, 255, 0.15);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
        }

        .badge-level {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            background:
                <?= $warna_level[$level] ?? '#666' ?>
            ;
        }

        /* ── LAYOUT ── */
        .layout {
            display: flex;
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px 16px;
            gap: 24px;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 260px;
            flex-shrink: 0;
        }

        .sidebar-card {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            margin-bottom: 16px;
        }

        .sidebar-header {
            background: #0f3460;
            color: #fff;
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .topik-nav a {
            display: block;
            padding: 12px 16px;
            font-size: 14px;
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.15s;
        }

        .topik-nav a:hover {
            background: #f0f7ff;
        }

        .topik-nav a.aktif {
            background: #e8f4fd;
            color: #0f3460;
            font-weight: 600;
            border-left: 3px solid #0f3460;
        }

        .konten-nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            font-size: 13px;
            color: #555;
            text-decoration: none;
            border-bottom: 1px solid #f5f5f5;
            transition: background 0.15s;
        }

        .konten-nav a:hover {
            background: #f8f8f8;
        }

        .konten-nav a.aktif {
            background: #e8f4fd;
            color: #0f3460;
            font-weight: 600;
        }

        .tipe-badge {
            font-size: 10px;
            padding: 2px 7px;
            border-radius: 10px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .tipe-teori {
            background: #e8f4fd;
            color: #0f3460;
        }

        .tipe-langkah {
            background: #e8f8e8;
            color: #1a7a1a;
        }

        .tipe-evaluasi {
            background: #fff3e0;
            color: #e67e22;
        }

        .tipe-jobsheet {
            background: #f3e8ff;
            color: #7b2cbf;
        }

        .tipe-tantangan {
            background: #fde8e8;
            color: #c0392b;
        }

        .wajib-icon {
            color: #e74c3c;
            font-size: 10px;
        }

        /* ── PROGRESS ── */
        .progress-wrap {
            padding: 12px 16px;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #888;
            margin-bottom: 6px;
        }

        .progress-bar {
            height: 6px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #4ecdc4;
            border-radius: 10px;
            width: <?= $progress ?>%;
            transition: width 0.4s ease;
        }

        /* ── MAIN CONTENT ── */
        .main {
            flex: 1;
            min-width: 0;
        }

        .content-card {
            background: #fff;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            margin-bottom: 20px;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .content-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .content-title {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 24px;
            line-height: 1.4;
        }

        .content-body {
            font-size: 15px;
            line-height: 1.8;
            color: #333;
        }

        .content-body h3 {
            font-size: 17px;
            color: #0f3460;
            margin: 20px 0 10px;
        }

        .content-body p {
            margin-bottom: 12px;
        }

        .content-body ul,
        .content-body ol {
            padding-left: 24px;
            margin-bottom: 12px;
        }

        .content-body li {
            margin-bottom: 6px;
        }

        .content-body table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
            font-size: 14px;
        }

        .content-body table th {
            background: #0f3460;
            color: #fff;
            padding: 10px 12px;
            text-align: left;
        }

        .content-body table td {
            padding: 9px 12px;
            border-bottom: 1px solid #eee;
        }

        .content-body table tr:nth-child(even) td {
            background: #f8f8f8;
        }

        .content-body strong {
            color: #0f3460;
        }

        /* ── EVALUASI ── */
        .evaluasi-soal {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #e67e22;
        }

        .evaluasi-soal .soal-teks {
            font-weight: 600;
            margin-bottom: 14px;
            color: #1a1a2e;
        }

        .opsi-list {
            list-style: none;
        }

        .opsi-list li {
            margin-bottom: 8px;
        }

        .opsi-list label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }

        .opsi-list label:hover {
            border-color: #e67e22;
            background: #fff8f0;
        }

        .opsi-list input[type="radio"] {
            accent-color: #e67e22;
        }

        /* ── NAVIGATION ── */
        .nav-buttons {
            display: flex;
            gap: 12px;
            justify-content: space-between;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background: #0f3460;
            color: #fff;
        }

        .btn-primary:hover {
            background: #16213e;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #0f3460;
            color: #0f3460;
        }

        .btn-outline:hover {
            background: #f0f7ff;
        }

        .btn-disabled {
            background: #e0e0e0;
            color: #999;
            cursor: not-allowed;
        }

        .btn-success {
            background: #27ae60;
            color: #fff;
        }
        .btn-success:hover {
            background: #219a52;
        }

        @media (max-width: 768px) {
            .layout {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
            }
        }
    </style>
</head>

<body>

    <!-- TOP BAR -->
    <div class="topbar">
        <div class="topbar-left">AdaptLearn PRE</div>
        <div class="topbar-right" style="display:flex;align-items:center;gap:12px">
            <span
                class="badge-profil"><?= htmlspecialchars($label_profil[$profil_learning] ?? $profil_learning) ?></span>
            <span class="badge-level"><?= htmlspecialchars($label_level[$level] ?? $level) ?></span>
            <a href="profil.php" style="color:rgba(255,255,255,0.85);font-size:13px;text-decoration:none">
                <?= htmlspecialchars($_SESSION['nama']) ?>
            </a>
        </div>
    </div>

    <div class="layout">

        <!-- SIDEBAR -->
        <div class="sidebar">

            <!-- Navigasi topik -->
            <div class="sidebar-card">
                <div class="sidebar-header">Topik</div>
                <nav class="topik-nav">
                    <?php foreach ($topik_list as $slug => $label): ?>
                        <a href="materi.php?topik=<?= $slug ?>" class="<?= $slug === $topik_aktif ? 'aktif' : '' ?>">
                            <?= htmlspecialchars($label) ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <!-- Progress topik aktif -->
            <div class="sidebar-card">
                <div class="sidebar-header">Progress — <?= htmlspecialchars($topik_list[$topik_aktif]) ?></div>
                <div class="progress-wrap">
                    <div class="progress-label">
                        <span><?= $konten_no ?> / <?= $total_konten ?> materi</span>
                        <span><?= $progress ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                </div>
            </div>

            <!-- Daftar konten adaptif -->
            <div class="sidebar-card">
                <div class="sidebar-header">Urutan Materi</div>
                <nav class="konten-nav">
                    <?php foreach ($konten_list as $i => $k): ?>
                        <a href="materi.php?topik=<?= $topik_aktif ?>&konten=<?= $k['id'] ?>"
                            class="<?= $k['id'] == $konten_id_aktif ? 'aktif' : '' ?>">
                            <span class="tipe-badge tipe-<?= $k['tipe'] ?>"><?= strtoupper($k['tipe']) ?></span>
                            <span style="flex:1"><?= htmlspecialchars($k['judul']) ?></span>
                            <?php if ($k['tipe'] === 'evaluasi' && in_array($k['id'], $evaluasi_selesai)): ?>
                                <span title="Sudah dikerjakan" style="color:#27ae60;font-size:13px;font-weight:700">✓</span>
                            <?php elseif ($k['tipe'] !== 'evaluasi' && in_array($k['id'], $konten_dibuka)): ?>
                                <span title="Sudah dibaca" style="color:#27ae60;font-size:13px;font-weight:700">✓</span>
                            <?php elseif ($k['wajib']): ?>
                                <span class="wajib-icon" title="Wajib dibaca">●</span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>

        </div>

        <!-- MAIN CONTENT -->
        <div class="main">
            <?php if ($is_finish): ?>
                <div class="content-card" style="text-align:center;padding:48px 32px">
                    <div style="font-size:56px;margin-bottom:16px">🎉</div>
                    <h2 style="color:#0f3460;margin-bottom:12px">Selamat! Semua Materi Selesai</h2>
                    <p style="color:#555;font-size:15px;margin-bottom:32px">
                        Kamu telah menyelesaikan seluruh materi pembelajaran.<br>
                        Langkah berikutnya adalah mengerjakan <strong>Post-Test</strong>.
                    </p>
                    <a href="/posttest.php" class="btn btn-success" style="font-size:16px;padding:14px 36px">
                        Kerjakan Post-Test →
                    </a>
                    <div style="margin-top:16px">
                        <a href="/profil.php" style="color:#888;font-size:13px">Lihat profil & progress saya</a>
                    </div>
                </div>
            <?php elseif ($konten_aktif): ?>

                <div class="content-card">
                    <div class="content-meta">
                        <span
                            class="tipe-badge tipe-<?= $konten_aktif['tipe'] ?>"><?= strtoupper($konten_aktif['tipe']) ?></span>
                        <span style="font-size:13px;color:#888"><?= htmlspecialchars($topik_list[$topik_aktif]) ?></span>
                    </div>
                    <h1 class="content-title"><?= htmlspecialchars($konten_aktif['judul']) ?></h1>

                    <?php if ($is_evaluasi && $soal_evaluasi): ?>
                        <!-- Tampilan evaluasi -->
                        <form method="POST" action="api/evaluasi.php">
                            <input type="hidden" name="user_id" value="<?= $user_id ?>">
                            <input type="hidden" name="topik" value="<?= $topik_aktif ?>">
                            <input type="hidden" name="profil_gabungan" value="<?= $profil_gabungan ?>">
                            <?php foreach ($soal_evaluasi as $i => $soal): ?>
                                <div class="evaluasi-soal">
                                    <div class="soal-teks"><?= ($i + 1) ?>. <?= htmlspecialchars($soal['soal']) ?></div>
                                    <ul class="opsi-list">
                                        <?php foreach ($soal['opsi'] as $huruf => $teks): ?>
                                            <li>
                                                <label>
                                                    <input type="radio" name="jawaban[<?= $i ?>]" value="<?= $huruf ?>" required>
                                                    <strong><?= $huruf ?>.</strong> <?= htmlspecialchars($teks) ?>
                                                </label>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-primary">Kirim Jawaban →</button>
                        </form>

                    <?php else: ?>
                        <!-- Tampilan konten biasa -->
                        <div class="content-body">
                            <?= $konten_aktif['isi'] ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Navigasi prev/next -->
                <?php
                $topik_keys = array_keys($topik_list);
                $topik_idx  = array_search($topik_aktif, $topik_keys);
                $next_topik = $topik_keys[$topik_idx + 1] ?? null;
                ?>
                <div class="nav-buttons">
                    <?php if ($prev_id): ?>
                        <a href="materi.php?topik=<?= $topik_aktif ?>&konten=<?= $prev_id ?>" class="btn btn-outline">← Sebelumnya</a>
                    <?php else: ?>
                        <span class="btn btn-disabled">← Sebelumnya</span>
                    <?php endif; ?>

                    <?php if ($next_id): ?>
                        <?php if ($pakai_timer): ?>
                            <a href="materi.php?topik=<?= $topik_aktif ?>&konten=<?= $next_id ?>"
                                class="btn btn-disabled" id="btn-next" data-href="materi.php?topik=<?= $topik_aktif ?>&konten=<?= $next_id ?>">
                                Selanjutnya (45s) →</a>
                        <?php else: ?>
                            <a href="materi.php?topik=<?= $topik_aktif ?>&konten=<?= $next_id ?>"
                                class="btn btn-primary">Selanjutnya →</a>
                        <?php endif; ?>
                    <?php elseif ($next_topik): ?>
                        <?php if ($pakai_timer): ?>
                            <a href="materi.php?topik=<?= urlencode($next_topik) ?>"
                                class="btn btn-disabled" id="btn-next" data-href="materi.php?topik=<?= urlencode($next_topik) ?>">
                                Topik Berikutnya (45s) →</a>
                        <?php else: ?>
                            <a href="materi.php?topik=<?= urlencode($next_topik) ?>"
                                class="btn btn-primary">Topik Berikutnya →</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($pakai_timer): ?>
                            <a href="materi.php?finish=1"
                                class="btn btn-disabled" id="btn-next" data-href="materi.php?finish=1">
                                🏁 Selesai (45s)</a>
                        <?php else: ?>
                            <a href="materi.php?finish=1" class="btn btn-success">🏁 Selesai Semua Materi</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="content-card">
                    <p>Konten tidak ditemukan.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <?php if ($pakai_timer): ?>
    <script>
    (function() {
        var btn = document.getElementById('btn-next');
        if (!btn) return;

        var durasi     = 45;
        var sisa       = durasi;
        var href       = btn.getAttribute('data-href');
        var label      = btn.textContent.trim().replace(/\s*\(\d+s\)\s*→?\s*/, '').trim();
        var btnClass   = '<?= ($next_id === null && $next_topik === null) ? "btn-success" : "btn-primary" ?>';
        var contentId  = <?= $konten_id_aktif ?>;
        var topik      = '<?= $topik_aktif ?>';

        var interval = setInterval(function() {
            sisa--;
            if (sisa <= 0) {
                clearInterval(interval);

                // Catat selesai_materi via AJAX
                var fd = new FormData();
                fd.append('content_id', contentId);
                fd.append('topik', topik);
                fetch('/api/selesai_materi.php', { method: 'POST', body: fd });

                // Aktifkan tombol
                btn.textContent = label + ' →';
                btn.classList.remove('btn-disabled');
                btn.classList.add(btnClass);
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.location.href = href;
                });
            } else {
                btn.textContent = label + ' (' + sisa + 's) →';
            }
        }, 1000);
    })();
    </script>
    <?php endif; ?>
</body>

</html>