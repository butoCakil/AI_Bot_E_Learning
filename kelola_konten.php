<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    header('Location: login_guru.php');
    exit;
}

$pdo = db();
$pesan = '';

// Handle POST — simpan konten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi       = $_POST['aksi'] ?? '';
    $content_id = (int)($_POST['content_id'] ?? 0);
    $judul      = trim($_POST['judul'] ?? '');
    $topik      = trim($_POST['topik'] ?? '');
    $tipe       = trim($_POST['tipe'] ?? '');
    $urutan     = (int)($_POST['urutan_default'] ?? 0);
    $aktif      = isset($_POST['aktif']) ? 1 : 0;
    $perlu_upload = isset($_POST['perlu_upload']) ? 1 : 0;
    $mode       = $_POST['mode'] ?? 'editor'; // 'editor' atau 'upload'

    if ($aksi === 'simpan' && $judul && $topik && $tipe) {
        $isi       = '';
        $file_path = null;

        // Ambil file_path lama kalau ada
        if ($content_id) {
            $stmt = $pdo->prepare("SELECT file_path FROM content WHERE id = ?");
            $stmt->execute([$content_id]);
            $existing = $stmt->fetch();
            $file_path = $existing['file_path'] ?? null;
        }

        if ($mode === 'upload' && !empty($_FILES['file_konten']['name'])) {
            // Mode upload file
            $allowed_ext  = ['pdf', 'doc', 'docx'];
            $allowed_mime = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];
            $ext  = strtolower(pathinfo($_FILES['file_konten']['name'], PATHINFO_EXTENSION));
            $mime = mime_content_type($_FILES['file_konten']['tmp_name']);

            if (in_array($ext, $allowed_ext) && in_array($mime, $allowed_mime)) {
                // Hapus file lama
                if ($file_path && file_exists(__DIR__ . '/' . $file_path)) {
                    unlink(__DIR__ . '/' . $file_path);
                }
                $filename  = 'konten_' . ($content_id ?: time()) . '_' . time() . '.' . $ext;
                $dest      = __DIR__ . '/uploads/konten/' . $filename;
                if (move_uploaded_file($_FILES['file_konten']['tmp_name'], $dest)) {
                    $file_path = 'uploads/konten/' . $filename;
                    $isi = ''; // kosongkan isi HTML kalau pakai file
                } else {
                    $pesan = '✗ Gagal menyimpan file.';
                }
            } else {
                $pesan = '✗ Format file tidak diizinkan. Gunakan PDF, DOC, atau DOCX.';
            }
        } elseif ($mode === 'editor') {
            // Mode editor — ambil dari TinyMCE
            $isi = $_POST['isi'] ?? '';
            // Hapus file lama kalau switch ke editor
            if ($file_path && file_exists(__DIR__ . '/' . $file_path)) {
                unlink(__DIR__ . '/' . $file_path);
            }
            $file_path = null;
        } elseif ($tipe === 'evaluasi') {
            // Mode evaluasi — build JSON dari form soal
            $soal_list = [];
            $soal_teks = $_POST['soal_teks'] ?? [];
            $soal_opsi = $_POST['soal_opsi'] ?? [];
            $soal_jwb  = $_POST['soal_jawaban'] ?? [];
            foreach ($soal_teks as $i => $teks) {
                if (!trim($teks)) continue;
                $soal_list[] = [
                    'soal'    => trim($teks),
                    'opsi'    => [
                        'A' => trim($soal_opsi[$i]['A'] ?? ''),
                        'B' => trim($soal_opsi[$i]['B'] ?? ''),
                        'C' => trim($soal_opsi[$i]['C'] ?? ''),
                        'D' => trim($soal_opsi[$i]['D'] ?? ''),
                    ],
                    'jawaban' => $soal_jwb[$i] ?? 'A',
                ];
            }
            $isi = json_encode($soal_list, JSON_UNESCAPED_UNICODE);
            $file_path = null;
        }

        if (!$pesan) {
            if ($content_id) {
                $stmt = $pdo->prepare("UPDATE content SET judul=?, topik=?, tipe=?, isi=?, file_path=?, urutan_default=?, aktif=?, perlu_upload=? WHERE id=?");
                $stmt->execute([$judul, $topik, $tipe, $isi, $file_path, $urutan, $aktif, $perlu_upload, $content_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO content (judul, topik, tipe, isi, file_path, urutan_default, aktif, perlu_upload) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$judul, $topik, $tipe, $isi, $file_path, $urutan, $aktif, $perlu_upload]);
                $content_id = $pdo->lastInsertId();
            }
            $pesan = '✓ Konten berhasil disimpan.';
        }
    } elseif ($aksi === 'hapus' && $content_id) {
        // Hapus file jika ada
        $stmt = $pdo->prepare("SELECT file_path FROM content WHERE id=?");
        $stmt->execute([$content_id]);
        $row = $stmt->fetch();
        if ($row['file_path'] && file_exists(__DIR__ . '/' . $row['file_path'])) {
            unlink(__DIR__ . '/' . $row['file_path']);
        }
        $pdo->prepare("DELETE FROM content WHERE id=?")->execute([$content_id]);
        $pesan = '✓ Konten berhasil dihapus.';
        $content_id = 0;
    }
}

// Ambil semua konten
$konten_list = $pdo->query("SELECT * FROM content ORDER BY topik, urutan_default, id")->fetchAll();

// Konten yang sedang diedit
$edit = null;
$edit_id = (int)($_GET['edit'] ?? $content_id ?? 0);
if ($edit_id) {
    $stmt = $pdo->prepare("SELECT * FROM content WHERE id=?");
    $stmt->execute([$edit_id]);
    $edit = $stmt->fetch();
}

$topik_list = get_topik_list();
$tipe_list  = ['teori' => 'Teori', 'langkah' => 'Langkah Kerja', 'jobsheet' => 'Jobsheet', 'evaluasi' => 'Evaluasi', 'tantangan' => 'Tantangan'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Konten — AdaptLearn PRE</title>
<script src="https://cdn.tiny.cloud/1/vowvs5ciyejykt64n2v6bwrwrj8ftsh3tg9ubh494qcf9ri0/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
    selector: '#editor-isi',
    plugins: 'lists table link image code',
    toolbar: 'undo redo | formatselect | bold italic underline | alignleft aligncenter alignright | bullist numlist | table | link image | code',
    height: 400,
    language: 'id',
    content_style: 'body { font-family: Segoe UI, sans-serif; font-size: 14px; line-height: 1.6; }',
    table_default_attributes: { border: '1' },
    table_default_styles: { 'border-collapse': 'collapse', 'width': '100%' },
});
</script>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; min-height: 100vh; }
.topbar { background: #0f3460; color: #fff; padding: 0 24px; height: 56px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
.topbar-brand { font-weight: 700; font-size: 16px; }
.topbar-brand span { font-weight: 300; opacity: 0.6; font-size: 13px; margin-left: 8px; }
.topbar-right { display: flex; align-items: center; gap: 16px; font-size: 13px; }
.topbar-right a { color: rgba(255,255,255,0.7); text-decoration: none; }
.topbar-right a:hover { color: #fff; }
.layout { display: grid; grid-template-columns: 320px 1fr; gap: 20px; max-width: 1200px; margin: 24px auto; padding: 0 16px; }
.card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.card-title { font-size: 14px; font-weight: 700; color: #333; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
/* KONTEN LIST */
.konten-group { margin-bottom: 16px; }
.konten-group-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #888; margin-bottom: 8px; }
.konten-item { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 8px; cursor: pointer; transition: background 0.15s; border: 1px solid transparent; margin-bottom: 4px; }
.konten-item:hover { background: #f0f7ff; }
.konten-item.aktif { background: #e8f0fe; border-color: #0f3460; }
.tipe-badge { font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; flex-shrink: 0; }
.tipe-teori { background: #e3f2fd; color: #1565c0; }
.tipe-langkah { background: #e8f5e9; color: #2e7d32; }
.tipe-jobsheet { background: #fff3e0; color: #e65100; }
.tipe-evaluasi { background: #f3e5f5; color: #6a1b9a; }
.tipe-tantangan { background: #fce4ec; color: #880e4f; }
.konten-item-judul { font-size: 13px; color: #333; flex: 1; }
.konten-item-status { font-size: 11px; color: #aaa; }
/* FORM */
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 6px; }
.form-group input, .form-group select, .form-group textarea {
    width: 100%; padding: 9px 12px; border: 2px solid #e0e0e0; border-radius: 8px;
    font-size: 13px; outline: none; transition: border-color 0.2s; font-family: inherit;
}
.form-group input:focus, .form-group select:focus { border-color: #0f3460; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
/* MODE TOGGLE */
.mode-toggle { display: flex; gap: 8px; margin-bottom: 16px; }
.mode-btn { flex: 1; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; background: #fff; font-size: 13px; font-weight: 600; cursor: pointer; color: #888; transition: all 0.2s; text-align: center; }
.mode-btn.aktif { border-color: #0f3460; color: #0f3460; background: #e8f0fe; }
/* EVALUASI FORM */
.soal-item { background: #f8f9ff; border-radius: 8px; padding: 16px; margin-bottom: 12px; border: 1px solid #e0e8ff; }
.soal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 13px; font-weight: 700; color: #0f3460; }
.opsi-grid { display: grid; grid-template-columns: 30px 1fr; gap: 6px; align-items: center; margin-bottom: 6px; }
.opsi-label { font-size: 12px; font-weight: 700; color: #555; }
/* BUTTONS */
.btn { padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; display: inline-block; transition: all 0.2s; }
.btn-primary { background: #0f3460; color: #fff; }
.btn-primary:hover { background: #16213e; }
.btn-success { background: #27ae60; color: #fff; }
.btn-danger { background: #e74c3c; color: #fff; font-size: 12px; padding: 5px 10px; }
.btn-outline { background: transparent; border: 2px solid #0f3460; color: #0f3460; }
.btn-sm { padding: 5px 12px; font-size: 12px; }
.alert { padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
.alert-success { background: #e8f8ef; color: #1e8449; border: 1px solid #a9dfbf; }
.alert-danger { background: #fdf0ef; color: #c0392b; border: 1px solid #f5b7b1; }
.upload-preview { background: #f8f9ff; border: 2px dashed #c0c8e8; border-radius: 8px; padding: 16px; text-align: center; margin-top: 8px; }
.checkbox-row { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #555; }
@media (max-width: 768px) { .layout { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<div class="topbar">
    <div class="topbar-brand">Kelola Konten <span>AdaptLearn PRE</span></div>
    <div class="topbar-right">
        <a href="dashboard_guru.php">← Dashboard</a>
        <a href="logout.php">Keluar</a>
    </div>
</div>

<div class="layout">

    <!-- SIDEBAR: DAFTAR KONTEN -->
    <div>
        <div class="card">
            <div class="card-title">
                Daftar Konten
                <a href="kelola_konten.php?edit=0" class="btn btn-success btn-sm">+ Baru</a>
            </div>

            <?php if ($pesan): ?>
            <div class="alert <?= str_starts_with($pesan,'✓') ? 'alert-success' : 'alert-danger' ?>">
                <?= htmlspecialchars($pesan) ?>
            </div>
            <?php endif; ?>

            <?php foreach (get_topik_tree() as $parent):
                // Kumpulkan semua slug yang perlu ditampilkan untuk parent ini
                $slugs = [];
                if (empty($parent['children'])) {
                    $slugs[] = $parent['slug'];
                } else {
                    foreach ($parent['children'] as $child) $slugs[] = $child['slug'];
                }
                // Cek apakah ada konten di slug-slug ini
                $ada_konten = false;
                foreach ($konten_list as $k) {
                    if (in_array($k['topik'], $slugs)) { $ada_konten = true; break; }
                }
            ?>
            <?php if (!empty($parent['children'])): ?>
                <?php foreach ($parent['children'] as $child):
                    $child_konten = array_filter($konten_list, fn($k) => $k['topik'] === $child['slug']);
                    if (empty($child_konten)) continue;
                ?>
                <div class="konten-group">
                    <div class="konten-group-title"><?= htmlspecialchars($parent['nama']) ?> › <?= htmlspecialchars($child['nama']) ?></div>
                    <?php foreach ($child_konten as $k): ?>
                    <a href="kelola_konten.php?edit=<?= $k['id'] ?>" class="konten-item <?= $edit_id == $k['id'] ? 'aktif' : '' ?>" style="text-decoration:none">
                        <span class="tipe-badge tipe-<?= $k['tipe'] ?>"><?= strtoupper($k['tipe']) ?></span>
                        <span class="konten-item-judul"><?= htmlspecialchars(mb_strimwidth($k['judul'],0,30,'...')) ?></span>
                        <span class="konten-item-status"><?= $k['aktif'] ? '' : '⊘' ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            <?php elseif ($ada_konten): ?>
                <div class="konten-group">
                    <div class="konten-group-title"><?= htmlspecialchars($parent['nama']) ?></div>
                    <?php foreach ($konten_list as $k): ?>
                    <?php if ($k['topik'] !== $parent['slug']) continue; ?>
                    <a href="kelola_konten.php?edit=<?= $k['id'] ?>" class="konten-item <?= $edit_id == $k['id'] ? 'aktif' : '' ?>" style="text-decoration:none">
                        <span class="tipe-badge tipe-<?= $k['tipe'] ?>"><?= strtoupper($k['tipe']) ?></span>
                        <span class="konten-item-judul"><?= htmlspecialchars(mb_strimwidth($k['judul'],0,30,'...')) ?></span>
                        <span class="konten-item-status"><?= $k['aktif'] ? '' : '⊘' ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- MAIN: FORM EDIT -->
    <div>
        <?php if ($edit !== null || isset($_GET['edit'])): ?>
        <div class="card">
            <div class="card-title">
                <?= $edit ? 'Edit Konten: ' . htmlspecialchars(mb_strimwidth($edit['judul'],0,40,'...')) : 'Tambah Konten Baru' ?>
                <?php if ($edit): ?>
                <form method="POST" style="display:inline" onsubmit="return confirm('Hapus konten ini?')">
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="content_id" value="<?= $edit['id'] ?>">
                    <button type="submit" class="btn btn-danger">🗑 Hapus</button>
                </form>
                <?php endif; ?>
            </div>

            <?php
            $is_evaluasi = $edit && $edit['tipe'] === 'evaluasi';
            $has_file    = $edit && !empty($edit['file_path']);
            $soal_list   = [];
            if ($is_evaluasi && $edit['isi']) {
                $soal_list = json_decode($edit['isi'], true) ?? [];
            }
            ?>

            <form method="POST" enctype="multipart/form-data" id="form-konten">
                <input type="hidden" name="aksi" value="simpan">
                <input type="hidden" name="content_id" value="<?= $edit['id'] ?? 0 ?>">
                <input type="hidden" name="mode" id="input-mode" value="<?= $has_file ? 'upload' : 'editor' ?>">

                <!-- JUDUL + TOPIK + TIPE -->
                <div class="form-group">
                    <label>Judul Konten</label>
                    <input type="text" name="judul" value="<?= htmlspecialchars($edit['judul'] ?? '') ?>" placeholder="Judul konten..." required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Topik</label>
                        <?php $topik_tree = get_topik_tree(); ?>
                        <select name="topik" id="select-topik" onchange="handleTopikChange(this.value)" required>
                            <option value="">— Pilih Topik —</option>
                            <?php foreach ($topik_tree as $parent): ?>
                                <?php if (empty($parent['children'])): ?>
                                    <option value="<?= $parent['slug'] ?>" <?= ($edit['topik'] ?? '') === $parent['slug'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($parent['nama']) ?>
                                    </option>
                                <?php else: ?>
                                    <optgroup label="<?= htmlspecialchars($parent['nama']) ?>">
                                        <?php foreach ($parent['children'] as $child): ?>
                                        <option value="<?= $child['slug'] ?>" <?= ($edit['topik'] ?? '') === $child['slug'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($child['nama']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <option value="__baru_parent__">➕ Tambah Topik Baru (Parent)</option>
                            <option value="__baru_sub__">➕ Tambah Sub-Topik Baru</option>
                        </select>

                        <!-- Form tambah topik baru (parent) -->
                        <div id="wrap-topik-baru-parent" style="display:none;margin-top:10px;background:#f8f9ff;border:2px solid #c0c8e8;border-radius:8px;padding:14px">
                            <div style="font-size:12px;font-weight:700;color:#0f3460;margin-bottom:8px">➕ Topik Baru (Parent)</div>
                            <input type="text" id="nama-topik-baru" placeholder="Nama topik (contoh: Catu Daya Lanjutan)"
                                style="width:100%;padding:8px 10px;border:2px solid #e0e0e0;border-radius:6px;font-size:13px;margin-bottom:8px">
                            <input type="text" id="slug-topik-baru" placeholder="Slug otomatis dari nama..."
                                style="width:100%;padding:8px 10px;border:2px solid #e0e0e0;border-radius:6px;font-size:13px;margin-bottom:8px;color:#888" readonly>
                            <button type="button" class="btn btn-primary btn-sm" onclick="simpanTopikBaru('parent')">Simpan & Pilih</button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="batalTopikBaru()" style="margin-left:6px">Batal</button>
                            <div id="msg-topik-baru-parent" style="font-size:12px;margin-top:6px"></div>
                        </div>

                        <!-- Form tambah sub-topik baru -->
                        <div id="wrap-topik-baru-sub" style="display:none;margin-top:10px;background:#f8f9ff;border:2px solid #c0c8e8;border-radius:8px;padding:14px">
                            <div style="font-size:12px;font-weight:700;color:#0f3460;margin-bottom:8px">➕ Sub-Topik Baru</div>
                            <div style="font-size:12px;color:#555;margin-bottom:6px">Parent (topik utama):</div>
                            <select id="parent-sub-baru" style="width:100%;padding:8px 10px;border:2px solid #e0e0e0;border-radius:6px;font-size:13px;margin-bottom:8px">
                                <?php foreach ($topik_tree as $parent): ?>
                                <option value="<?= $parent['id'] ?>"><?= htmlspecialchars($parent['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" id="nama-sub-baru" placeholder="Nama sub-topik (contoh: Pengukuran Dioda)"
                                style="width:100%;padding:8px 10px;border:2px solid #e0e0e0;border-radius:6px;font-size:13px;margin-bottom:8px">
                            <input type="text" id="slug-sub-baru" placeholder="Slug otomatis dari nama..."
                                style="width:100%;padding:8px 10px;border:2px solid #e0e0e0;border-radius:6px;font-size:13px;margin-bottom:8px;color:#888" readonly>
                            <button type="button" class="btn btn-primary btn-sm" onclick="simpanTopikBaru('sub')">Simpan & Pilih</button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="batalTopikBaru()" style="margin-left:6px">Batal</button>
                            <div id="msg-topik-baru-sub" style="font-size:12px;margin-top:6px"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Tipe</label>
                        <select name="tipe" id="select-tipe" onchange="handleTipeChange(this.value)" required>
                            <?php foreach ($tipe_list as $s => $n): ?>
                            <option value="<?= $s ?>" <?= ($edit['tipe'] ?? '') === $s ? 'selected' : '' ?>><?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label>Urutan Default</label>
                        <input type="number" name="urutan_default" value="<?= $edit['urutan_default'] ?? 1 ?>" min="1">
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:2px">
                        <label class="checkbox-row">
                            <input type="checkbox" name="aktif" <?= ($edit['aktif'] ?? 1) ? 'checked' : '' ?>>
                            Konten aktif
                        </label>
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:2px" id="wrap-perlu-upload">
                        <label class="checkbox-row">
                            <input type="checkbox" name="perlu_upload" <?= ($edit['perlu_upload'] ?? 0) ? 'checked' : '' ?>>
                            Perlu upload siswa
                        </label>
                    </div>
                </div>

                <!-- MODE TOGGLE (sembunyikan untuk evaluasi) -->
                <div id="wrap-mode-toggle" <?= $is_evaluasi ? 'style="display:none"' : '' ?>>
                    <label style="font-size:12px;font-weight:600;color:#555;margin-bottom:8px;display:block">Mode Input Konten</label>
                    <div class="mode-toggle">
                        <div class="mode-btn <?= !$has_file ? 'aktif' : '' ?>" onclick="setMode('editor')" id="btn-mode-editor">✏️ Ketik di Editor</div>
                        <div class="mode-btn <?= $has_file ? 'aktif' : '' ?>" onclick="setMode('upload')" id="btn-mode-upload">📎 Upload File (PDF/DOC)</div>
                    </div>
                </div>

                <!-- MODE EDITOR -->
                <div id="wrap-editor" <?= $has_file ? 'style="display:none"' : '' ?>>
                    <div class="form-group">
                        <label>Isi Konten</label>
                        <textarea id="editor-isi" name="isi"><?= htmlspecialchars($edit['isi'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- MODE UPLOAD -->
                <div id="wrap-upload" <?= !$has_file ? 'style="display:none"' : '' ?>>
                    <div class="form-group">
                        <label>Upload File (PDF, DOC, DOCX — maks 10MB)</label>
                        <input type="file" name="file_konten" accept=".pdf,.doc,.docx" onchange="previewFile(this)">
                        <?php if ($has_file): ?>
                        <div class="upload-preview" id="file-preview">
                            📄 File saat ini: <a href="/<?= htmlspecialchars($edit['file_path']) ?>" target="_blank"><strong><?= basename($edit['file_path']) ?></strong></a>
                            <br><small style="color:#888">Upload file baru untuk mengganti</small>
                        </div>
                        <?php else: ?>
                        <div class="upload-preview" id="file-preview" style="display:none"></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- MODE EVALUASI -->
                <div id="wrap-evaluasi" <?= !$is_evaluasi ? 'style="display:none"' : '' ?>>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                        <label style="font-size:12px;font-weight:600;color:#555">Daftar Soal</label>
                        <button type="button" class="btn btn-outline btn-sm" onclick="tambahSoal()">+ Tambah Soal</button>
                    </div>
                    <div id="soal-container">
                        <?php foreach ($soal_list as $i => $soal): ?>
                        <div class="soal-item" id="soal-<?= $i ?>">
                            <div class="soal-header">
                                Soal <?= $i+1 ?>
                                <button type="button" class="btn btn-danger btn-sm" onclick="hapusSoal(<?= $i ?>)">× Hapus</button>
                            </div>
                            <div class="form-group">
                                <label>Teks Soal</label>
                                <textarea name="soal_teks[<?= $i ?>]" rows="2" style="width:100%;padding:8px;border:2px solid #e0e0e0;border-radius:6px;font-size:13px"><?= htmlspecialchars($soal['soal']) ?></textarea>
                            </div>
                            <?php foreach (['A','B','C','D'] as $huruf): ?>
                            <div class="opsi-grid">
                                <div class="opsi-label"><?= $huruf ?></div>
                                <input type="text" name="soal_opsi[<?= $i ?>][<?= $huruf ?>]" value="<?= htmlspecialchars($soal['opsi'][$huruf] ?? '') ?>" placeholder="Opsi <?= $huruf ?>">
                            </div>
                            <?php endforeach; ?>
                            <div class="form-group" style="margin-top:8px">
                                <label>Jawaban Benar</label>
                                <select name="soal_jawaban[<?= $i ?>]">
                                    <?php foreach (['A','B','C','D'] as $h): ?>
                                    <option value="<?= $h ?>" <?= ($soal['jawaban'] ?? 'A') === $h ? 'selected' : '' ?>><?= $h ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="display:flex;gap:12px;margin-top:20px">
                    <button type="submit" class="btn btn-primary" onclick="return submitForm()">💾 Simpan Konten</button>
                    <a href="kelola_konten.php" class="btn btn-outline">Batal</a>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="card" style="text-align:center;padding:60px;color:#aaa">
            <div style="font-size:48px;margin-bottom:16px">📝</div>
            <p>Pilih konten dari daftar untuk diedit,<br>atau klik <strong>+ Baru</strong> untuk menambah konten.</p>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
var soalCount = <?= count($soal_list) ?>;

function setMode(mode) {
    document.getElementById('input-mode').value = mode;
    document.getElementById('wrap-editor').style.display  = mode === 'editor' ? '' : 'none';
    document.getElementById('wrap-upload').style.display  = mode === 'upload' ? '' : 'none';
    document.getElementById('btn-mode-editor').classList.toggle('aktif', mode === 'editor');
    document.getElementById('btn-mode-upload').classList.toggle('aktif', mode === 'upload');
}

function handleTipeChange(tipe) {
    var isEval = tipe === 'evaluasi';
    var isJob  = tipe === 'jobsheet';
    document.getElementById('wrap-mode-toggle').style.display = isEval ? 'none' : '';
    document.getElementById('wrap-evaluasi').style.display    = isEval ? '' : 'none';
    document.getElementById('wrap-editor').style.display      = isEval ? 'none' : (document.getElementById('input-mode').value === 'editor' ? '' : 'none');
    if (isEval) {
        document.getElementById('input-mode').value = 'evaluasi';
    }
}

function tambahSoal() {
    var i = soalCount++;
    var html = `
    <div class="soal-item" id="soal-${i}">
        <div class="soal-header">Soal ${i+1} <button type="button" class="btn btn-danger btn-sm" onclick="hapusSoal(${i})">× Hapus</button></div>
        <div class="form-group">
            <label>Teks Soal</label>
            <textarea name="soal_teks[${i}]" rows="2" style="width:100%;padding:8px;border:2px solid #e0e0e0;border-radius:6px;font-size:13px"></textarea>
        </div>
        ${['A','B','C','D'].map(h => `
        <div class="opsi-grid">
            <div class="opsi-label">${h}</div>
            <input type="text" name="soal_opsi[${i}][${h}]" placeholder="Opsi ${h}">
        </div>`).join('')}
        <div class="form-group" style="margin-top:8px">
            <label>Jawaban Benar</label>
            <select name="soal_jawaban[${i}]">
                ${['A','B','C','D'].map(h => `<option value="${h}">${h}</option>`).join('')}
            </select>
        </div>
    </div>`;
    document.getElementById('soal-container').insertAdjacentHTML('beforeend', html);
}

function hapusSoal(i) {
    var el = document.getElementById('soal-' + i);
    if (el) el.remove();
}

function previewFile(input) {
    var preview = document.getElementById('file-preview');
    if (input.files && input.files[0]) {
        preview.style.display = '';
        preview.innerHTML = '📄 File dipilih: <strong>' + input.files[0].name + '</strong> (' + (input.files[0].size / 1024).toFixed(0) + ' KB)';
    }
}

function submitForm() {
    var mode = document.getElementById('input-mode').value;
    if (mode === 'editor' && typeof tinymce !== 'undefined') {
        tinymce.triggerSave();
    }
    return true;
}

// Init tipe change untuk perlu_upload visibility
handleTipeChange(document.getElementById('select-tipe')?.value || 'teori');
function handleTopikChange(val) {
    document.getElementById('wrap-topik-baru-parent').style.display = 'none';
    document.getElementById('wrap-topik-baru-sub').style.display = 'none';
    if (val === '__baru_parent__') {
        document.getElementById('wrap-topik-baru-parent').style.display = 'block';
        document.getElementById('select-topik').value = '';
    } else if (val === '__baru_sub__') {
        document.getElementById('wrap-topik-baru-sub').style.display = 'block';
        document.getElementById('select-topik').value = '';
    }
}

function toSlug(str) {
    return str.toLowerCase()
        .replace(/[^a-z0-9\s_]/g, '')
        .trim()
        .replace(/\s+/g, '_');
}

document.addEventListener('DOMContentLoaded', function() {
    var namaParent = document.getElementById('nama-topik-baru');
    if (namaParent) namaParent.addEventListener('input', function() {
        document.getElementById('slug-topik-baru').value = toSlug(this.value);
    });
    var namaSub = document.getElementById('nama-sub-baru');
    if (namaSub) namaSub.addEventListener('input', function() {
        document.getElementById('slug-sub-baru').value = toSlug(this.value);
    });
});

function batalTopikBaru() {
    document.getElementById('wrap-topik-baru-parent').style.display = 'none';
    document.getElementById('wrap-topik-baru-sub').style.display = 'none';
    document.getElementById('select-topik').value = '';
}

function simpanTopikBaru(mode) {
    var nama, slug, parentId, msgEl;
    if (mode === 'parent') {
        nama   = document.getElementById('nama-topik-baru').value.trim();
        slug   = document.getElementById('slug-topik-baru').value.trim();
        parentId = null;
        msgEl  = document.getElementById('msg-topik-baru-parent');
    } else {
        nama     = document.getElementById('nama-sub-baru').value.trim();
        slug     = document.getElementById('slug-sub-baru').value.trim();
        parentId = document.getElementById('parent-sub-baru').value;
        msgEl    = document.getElementById('msg-topik-baru-sub');
    }
    if (!nama || !slug) {
        msgEl.style.color = '#e74c3c';
        msgEl.textContent = 'Nama dan slug wajib diisi.';
        return;
    }
    msgEl.style.color = '#888';
    msgEl.textContent = 'Menyimpan...';

    var fd = new FormData();
    fd.append('aksi', 'tambah_topik');
    fd.append('nama', nama);
    fd.append('slug', slug);
    if (parentId) fd.append('parent_id', parentId);

    fetch('api/kelola_topik.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                // Tambah option baru ke select
                var sel = document.getElementById('select-topik');
                var opt = document.createElement('option');
                opt.value = data.slug;
                opt.text  = data.nama;
                // Sisipkan sebelum opsi "Tambah..."
                var idxParent = Array.from(sel.options).findIndex(o => o.value === '__baru_parent__');
                sel.insertBefore(opt, sel.options[idxParent]);
                sel.value = data.slug;
                // Sembunyikan form
                document.getElementById('wrap-topik-baru-parent').style.display = 'none';
                document.getElementById('wrap-topik-baru-sub').style.display = 'none';
                msgEl.textContent = '';
            } else {
                msgEl.style.color = '#e74c3c';
                msgEl.textContent = data.msg || 'Gagal menyimpan topik.';
            }
        })
        .catch(() => {
            msgEl.style.color = '#e74c3c';
            msgEl.textContent = 'Koneksi gagal.';
        });
}

</script>

</body>
</html>
