<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    header('Location: login_guru.php');
    exit;
}

$pdo   = db();
$pesan = '';
$pesan_ok = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'edit') {
        $id     = (int)($_POST['id'] ?? 0);
        $nama   = trim($_POST['nama'] ?? '');
        $urutan = (int)($_POST['urutan'] ?? 1);
        $aktif  = isset($_POST['aktif']) ? 1 : 0;
        if ($id && $nama) {
            $pdo->prepare("UPDATE topik SET nama=?, urutan=?, aktif=? WHERE id=?")->execute([$nama, $urutan, $aktif, $id]);
            $pesan = 'Topik berhasil diperbarui.';
        }
    } elseif ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM content WHERE topik = (SELECT slug FROM topik WHERE id=?)");
            $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() > 0) {
                $pesan = 'Tidak bisa hapus — masih ada konten di topik ini.';
                $pesan_ok = false;
            } else {
                $pdo->prepare("DELETE FROM topik WHERE id=?")->execute([$id]);
                $pesan = 'Topik berhasil dihapus.';
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
                $pesan = 'Slug sudah digunakan.';
                $pesan_ok = false;
            } else {
                $pdo->prepare("INSERT INTO topik (slug, nama, parent_id, urutan, aktif) VALUES (?,?,?,?,1)")->execute([$slug, $nama, $parent_id, $urutan]);
                $pesan = 'Topik berhasil ditambahkan.';
            }
        } else {
            $pesan = 'Nama dan slug wajib diisi. Slug hanya huruf kecil, angka, underscore.';
            $pesan_ok = false;
        }
    }
}

$topik_tree  = get_topik_tree();
$topik_utama = get_topik_utama();

$page_title = 'Kelola Topik — AdaptLearn PRE';
$guru_aktif = 'topik';
include __DIR__ . '/includes/topbar_guru.php';
?>

<div class="crumb">
    <a href="dashboard_guru.php">Dashboard</a>
    <span class="sep">›</span>
    <span class="now">Kelola Topik</span>
</div>

<div class="wrap">

    <?php if ($pesan): ?>
        <div class="pt <?= $pesan_ok ? 'buka' : 'kunci' ?>" style="padding:14px 16px">
            <div class="pt-ic" style="width:32px;height:32px;font-size:15px">
                <i class="<?= $pesan_ok ? 'icon-circle-check' : 'icon-circle-alert' ?>"></i>
            </div>
            <div><b style="margin:0"><?= htmlspecialchars($pesan) ?></b></div>
        </div>
    <?php endif; ?>

    <!-- DAFTAR TOPIK -->
    <div class="card">
        <div class="card-h" style="margin-bottom:14px">
            <h3><i class="icon-folder-tree"></i> Daftar topik &amp; sub-topik</h3>
        </div>

        <div style="overflow-x:auto">
            <table class="gtable">
                <thead>
                    <tr><th>Nama</th><th>Slug</th><th>Jenis</th><th>Urutan</th><th>Status</th><th style="text-align:right">Aksi</th></tr>
                </thead>
                <tbody>
                <?php foreach ($topik_tree as $parent): ?>
                    <tr>
                        <td><b><?= htmlspecialchars($parent['nama']) ?></b></td>
                        <td><code><?= htmlspecialchars($parent['slug']) ?></code></td>
                        <td><span class="tag" style="background:var(--biru-muda);color:var(--biru-tua)">Parent</span></td>
                        <td><?= $parent['urutan'] ?></td>
                        <td>
                            <?php if ($parent['aktif']): ?>
                                <span class="tag ok"><i class="icon-check"></i> Aktif</span>
                            <?php else: ?>
                                <span class="tag" style="background:var(--coral-muda);color:var(--coral)"><i class="icon-x"></i> Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;white-space:nowrap">
                            <button class="btn btn-2 btn-sm" onclick='bukaEdit(<?= json_encode($parent, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="icon-pencil"></i></button>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Hapus topik <?= htmlspecialchars($parent['nama'], ENT_QUOTES) ?>?')">
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id" value="<?= $parent['id'] ?>">
                                <button type="submit" class="btn btn-sm" style="background:var(--coral-muda);color:var(--coral)"><i class="icon-trash-2"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php foreach ($parent['children'] as $child): ?>
                        <tr style="background:var(--kanvas)">
                            <td style="padding-left:26px"><i class="icon-corner-down-right" style="color:var(--abu-muda);font-size:13px"></i> <?= htmlspecialchars($child['nama']) ?></td>
                            <td><code><?= htmlspecialchars($child['slug']) ?></code></td>
                            <td><span class="tag ok">Sub-topik</span></td>
                            <td><?= $child['urutan'] ?></td>
                            <td>
                                <?php if ($child['aktif']): ?>
                                    <span class="tag ok"><i class="icon-check"></i> Aktif</span>
                                <?php else: ?>
                                    <span class="tag" style="background:var(--coral-muda);color:var(--coral)"><i class="icon-x"></i> Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;white-space:nowrap">
                                <button class="btn btn-2 btn-sm" onclick='bukaEdit(<?= json_encode($child, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="icon-pencil"></i></button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Hapus sub-topik <?= htmlspecialchars($child['nama'], ENT_QUOTES) ?>?')">
                                    <input type="hidden" name="aksi" value="hapus">
                                    <input type="hidden" name="id" value="<?= $child['id'] ?>">
                                    <button type="submit" class="btn btn-sm" style="background:var(--coral-muda);color:var(--coral)"><i class="icon-trash-2"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FORM EDIT -->
    <div class="card" id="form-edit" style="display:none;border-left:4px solid var(--biru)">
        <div class="card-h" style="margin-bottom:14px"><h3><i class="icon-pencil"></i> Edit topik</h3></div>
        <form method="POST">
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id" id="edit-id">
            <div class="grow2">
                <div class="fg">
                    <label>Nama topik</label>
                    <input type="text" name="nama" id="edit-nama" required>
                </div>
                <div class="fg">
                    <label>Slug <span style="color:var(--abu-muda);font-weight:500">(tidak bisa diubah)</span></label>
                    <input type="text" id="edit-slug" disabled style="background:var(--kanvas);color:var(--abu-muda)">
                </div>
            </div>
            <div class="grow2">
                <div class="fg">
                    <label>Urutan</label>
                    <input type="number" name="urutan" id="edit-urutan" min="1">
                </div>
                <div class="fg" style="display:flex;align-items:flex-end;padding-bottom:10px">
                    <label class="cbx"><input type="checkbox" name="aktif" id="edit-aktif"> Topik aktif</label>
                </div>
            </div>
            <div class="rata">
                <button type="submit" class="btn btn-3 btn-sm"><i class="icon-save"></i> Simpan perubahan</button>
                <button type="button" class="btn btn-2 btn-sm" onclick="document.getElementById('form-edit').style.display='none'">Batal</button>
            </div>
        </form>
    </div>

    <!-- FORM TAMBAH -->
    <div class="card" style="border-left:4px solid var(--teal)">
        <div class="card-h" style="margin-bottom:14px"><h3><i class="icon-plus"></i> Tambah topik baru</h3></div>
        <form method="POST">
            <input type="hidden" name="aksi" value="tambah">
            <div class="grow2">
                <div class="fg">
                    <label>Nama topik</label>
                    <input type="text" name="nama" id="nama-baru" placeholder="Contoh: Rangkaian Filter" required oninput="autoSlug()">
                </div>
                <div class="fg">
                    <label>Slug</label>
                    <input type="text" name="slug" id="slug-baru" placeholder="otomatis dari nama" required>
                    <div class="fhint">Huruf kecil, angka, underscore. Contoh: rangkaian_filter</div>
                </div>
            </div>
            <div class="grow3">
                <div class="fg">
                    <label>Parent</label>
                    <select name="parent_id">
                        <option value="">— Topik utama (parent) —</option>
                        <?php foreach ($topik_utama as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fg">
                    <label>Urutan</label>
                    <input type="number" name="urutan" value="1" min="1">
                </div>
                <div class="fg" style="display:flex;align-items:flex-end;padding-bottom:2px">
                    <button type="submit" class="btn btn-3 btn-full"><i class="icon-plus"></i> Tambah</button>
                </div>
            </div>
        </form>
    </div>

</div>

<style>
/* Tabel & form khusus area guru — dipakai kelola_topik/konten */
.gtable { width:100%; border-collapse:collapse; font-size:13px; }
.gtable thead tr { background:var(--kanvas); }
.gtable th { padding:10px 12px; text-align:left; font-size:11px; font-weight:800; color:var(--abu-muda);
    text-transform:uppercase; letter-spacing:.5px; border-bottom:1px solid var(--garis); }
.gtable td { padding:11px 12px; border-bottom:1px solid var(--garis); vertical-align:middle; }
.gtable tr:last-child td { border-bottom:none; }
.gtable code { font-size:11.5px; color:var(--abu); background:var(--kanvas); padding:2px 7px; border-radius:6px; }
.fg { margin-bottom:14px; }
.fg label { display:block; font-size:12px; font-weight:700; color:var(--tinta); margin-bottom:5px; }
.fg input, .fg select { width:100%; padding:10px 12px; border:2px solid var(--garis); border-radius:var(--r-sm);
    font-size:13px; font-family:inherit; outline:none; transition:.15s; color:var(--tinta); background:#fff; }
.fg input:focus, .fg select:focus { border-color:var(--teal); background:var(--teal-muda); }
.fhint { font-size:11px; color:var(--abu-muda); margin-top:4px; }
.grow2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.grow3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
.cbx { display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; color:var(--abu); cursor:pointer; }
.cbx input { width:auto; accent-color:var(--teal); }
@media (max-width:600px){ .grow2,.grow3 { grid-template-columns:1fr; } }
</style>

<script>
function autoSlug() {
    var nama = document.getElementById('nama-baru').value;
    document.getElementById('slug-baru').value = nama.toLowerCase()
        .replace(/[^a-z0-9\s_]/g, '').trim().replace(/\s+/g, '_');
}
function bukaEdit(data) {
    document.getElementById('edit-id').value      = data.id;
    document.getElementById('edit-nama').value    = data.nama;
    document.getElementById('edit-slug').value    = data.slug;
    document.getElementById('edit-urutan').value  = data.urutan;
    document.getElementById('edit-aktif').checked = data.aktif == 1;
    var f = document.getElementById('form-edit');
    f.style.display = 'block';
    f.scrollIntoView({behavior:'smooth'});
}
</script>

</body>
</html>
