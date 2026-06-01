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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama'] ?? '');
        $urutan = (int)($_POST['urutan'] ?? 1);
        $aktif  = isset($_POST['aktif']) ? 1 : 0;
        if ($id && $nama) {
            $pdo->prepare("UPDATE topik SET nama=?, urutan=?, aktif=? WHERE id=?")->execute([$nama, $urutan, $aktif, $id]);
            $pesan = '✓ Topik berhasil diperbarui.';
        }
    } elseif ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            // Cek apakah ada konten di topik ini
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM content WHERE topik = (SELECT slug FROM topik WHERE id=?)");
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() > 0) {
                $pesan = '✗ Tidak bisa hapus — masih ada konten di topik ini.';
            } else {
                $pdo->prepare("DELETE FROM topik WHERE id=?")->execute([$id]);
                $pesan = '✓ Topik berhasil dihapus.';
            }
        }
    } elseif ($aksi === 'tambah') {
        $nama      = trim($_POST['nama'] ?? '');
        $slug      = trim($_POST['slug'] ?? '');
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $urutan    = (int)($_POST['urutan'] ?? 1);
        if ($nama && $slug && preg_match('/^[a-z0-9_]+$/', $slug)) {
            $cek = $pdo->prepare("SELECT id FROM topik WHERE slug=?");
            $cek->execute([$slug]);
            if ($cek->fetch()) {
                $pesan = '✗ Slug sudah digunakan.';
            } else {
                $pdo->prepare("INSERT INTO topik (slug, nama, parent_id, urutan, aktif) VALUES (?,?,?,?,1)")->execute([$slug, $nama, $parent_id, $urutan]);
                $pesan = '✓ Topik berhasil ditambahkan.';
            }
        } else {
            $pesan = '✗ Nama dan slug wajib diisi. Slug hanya huruf kecil, angka, underscore.';
        }
    }
}

$topik_tree = get_topik_tree();
$topik_utama = get_topik_utama();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Topik — AdaptLearn PRE</title>
<style>
* { -webkit-text-size-adjust: 100%; text-size-adjust: 100%; }

* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; min-height: 100vh; }
.topbar { background: #0f3460; color: #fff; padding: 0 24px; height: 56px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
.topbar-brand { font-weight: 700; font-size: 16px; }
.topbar-brand span { font-weight: 300; opacity: 0.6; font-size: 13px; margin-left: 8px; }
.topbar-right { display: flex; align-items: center; gap: 16px; font-size: 13px; }
.topbar-right a { color: rgba(255,255,255,0.7); text-decoration: none; }
.topbar-right a:hover { color: #fff; }
.container { max-width: 900px; margin: 24px auto; padding: 0 16px; }
.card { background: #fff; border-radius: 12px; padding: 20px 24px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); margin-bottom: 20px; }
.card-title { font-size: 15px; font-weight: 700; color: #333; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0; }
.alert { padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
.alert-success { background: #e8f8ef; color: #1e8449; border: 1px solid #a9dfbf; }
.alert-danger { background: #fdf0ef; color: #c0392b; border: 1px solid #f5b7b1; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
thead tr { background: #0f3460; color: #fff; }
th { padding: 10px 14px; text-align: left; font-size: 12px; }
td { padding: 10px 14px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #fafbff; }
.badge-parent { display: inline-block; background: #e8f0fe; color: #0f3460; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; }
.badge-sub { display: inline-block; background: #e8f8ef; color: #1e8449; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 5px; }
.form-group input, .form-group select { width: 100%; padding: 9px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 13px; outline: none; }
.form-group input:focus, .form-group select:focus { border-color: #0f3460; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
.btn { padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; display: inline-block; transition: all 0.2s; }
.btn-primary { background: #0f3460; color: #fff; }
.btn-primary:hover { background: #16213e; }
.btn-danger { background: #e74c3c; color: #fff; }
.btn-outline { background: transparent; border: 2px solid #0f3460; color: #0f3460; }
.btn-sm { padding: 4px 10px; font-size: 12px; }
.hint { font-size: 11px; color: #aaa; margin-top: 4px; }
.checkbox-row { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #555; cursor: pointer; }
</style>
</head>
<body>

<div class="topbar">
    <div class="topbar-brand">Kelola Topik <span>AdaptLearn PRE</span></div>
    <div class="topbar-right">
        <a href="dashboard_guru.php">← Dashboard</a>
        <a href="kelola_konten.php">Kelola Konten</a>
        <a href="logout.php">Keluar</a>
    </div>
</div>

<div class="container">

    <?php if ($pesan): ?>
    <div class="alert <?= str_starts_with($pesan,'✓') ? 'alert-success' : 'alert-danger' ?>">
        <?= htmlspecialchars($pesan) ?>
    </div>
    <?php endif; ?>

    <!-- DAFTAR TOPIK -->
    <div class="card">
        <div class="card-title">Daftar Topik & Sub-Topik</div>
        <table>
            <thead><tr><th>Nama</th><th>Slug</th><th>Jenis</th><th>Urutan</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($topik_tree as $parent): ?>
            <tr>
                <td><strong><?= htmlspecialchars($parent['nama']) ?></strong></td>
                <td style="font-family:monospace;color:#888;font-size:12px"><?= $parent['slug'] ?></td>
                <td><span class="badge-parent">Parent</span></td>
                <td><?= $parent['urutan'] ?></td>
                <td><?= $parent['aktif'] ? '<span style="color:#27ae60">✓ Aktif</span>' : '<span style="color:#e74c3c">✗ Nonaktif</span>' ?></td>
                <td>
                    <button class="btn btn-sm btn-outline" onclick="bukaEdit(<?= htmlspecialchars(json_encode($parent)) ?>)">Edit</button>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Hapus topik <?= htmlspecialchars($parent['nama']) ?>?')">
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="id" value="<?= $parent['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                    </form>
                </td>
            </tr>
            <?php foreach ($parent['children'] as $child): ?>
            <tr style="background:#fafff8">
                <td style="padding-left:28px">↳ <?= htmlspecialchars($child['nama']) ?></td>
                <td style="font-family:monospace;color:#888;font-size:12px"><?= $child['slug'] ?></td>
                <td><span class="badge-sub">Sub-Topik</span></td>
                <td><?= $child['urutan'] ?></td>
                <td><?= $child['aktif'] ? '<span style="color:#27ae60">✓ Aktif</span>' : '<span style="color:#e74c3c">✗ Nonaktif</span>' ?></td>
                <td>
                    <button class="btn btn-sm btn-outline" onclick="bukaEdit(<?= htmlspecialchars(json_encode($child)) ?>)">Edit</button>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Hapus sub-topik <?= htmlspecialchars($child['nama']) ?>?')">
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="id" value="<?= $child['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- FORM EDIT (hidden by default) -->
    <div class="card" id="form-edit" style="display:none;border-left:4px solid #2980b9">
        <div class="card-title">Edit Topik</div>
        <form method="POST">
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id" id="edit-id">
            <div class="form-row">
                <div class="form-group">
                    <label>Nama Topik</label>
                    <input type="text" name="nama" id="edit-nama" required>
                </div>
                <div class="form-group">
                    <label>Slug <span style="color:#aaa;font-weight:400">(tidak bisa diubah)</span></label>
                    <input type="text" id="edit-slug" disabled style="background:#f5f5f5;color:#aaa">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Urutan</label>
                    <input type="number" name="urutan" id="edit-urutan" min="1">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:2px">
                    <label class="checkbox-row">
                        <input type="checkbox" name="aktif" id="edit-aktif"> Topik aktif
                    </label>
                </div>
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
                <button type="button" class="btn btn-outline" onclick="document.getElementById('form-edit').style.display='none'">Batal</button>
            </div>
        </form>
    </div>

    <!-- FORM TAMBAH -->
    <div class="card" style="border-left:4px solid #27ae60">
        <div class="card-title">➕ Tambah Topik Baru</div>
        <form method="POST">
            <input type="hidden" name="aksi" value="tambah">
            <div class="form-row">
                <div class="form-group">
                    <label>Nama Topik</label>
                    <input type="text" name="nama" id="nama-baru" placeholder="Contoh: Rangkaian Filter" required oninput="autoSlug()">
                </div>
                <div class="form-group">
                    <label>Slug</label>
                    <input type="text" name="slug" id="slug-baru" placeholder="otomatis dari nama" required>
                    <div class="hint">Huruf kecil, angka, underscore. Contoh: rangkaian_filter</div>
                </div>
            </div>
            <div class="form-row-3">
                <div class="form-group">
                    <label>Parent (kosongkan jika topik utama)</label>
                    <select name="parent_id">
                        <option value="">— Topik Utama (Parent) —</option>
                        <?php foreach ($topik_utama as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Urutan</label>
                    <input type="number" name="urutan" value="1" min="1">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:2px">
                    <button type="submit" class="btn btn-primary" style="width:100%">+ Tambah</button>
                </div>
            </div>
        </form>
    </div>

</div>

<script>
function autoSlug() {
    var nama = document.getElementById('nama-baru').value;
    var slug = nama.toLowerCase()
        .replace(/[^a-z0-9\s_]/g, '')
        .trim().replace(/\s+/g, '_');
    document.getElementById('slug-baru').value = slug;
}

function bukaEdit(data) {
    document.getElementById('edit-id').value    = data.id;
    document.getElementById('edit-nama').value  = data.nama;
    document.getElementById('edit-slug').value  = data.slug;
    document.getElementById('edit-urutan').value = data.urutan;
    document.getElementById('edit-aktif').checked = data.aktif == 1;
    document.getElementById('form-edit').style.display = 'block';
    document.getElementById('form-edit').scrollIntoView({behavior:'smooth'});
}
</script>

</body>
</html>
