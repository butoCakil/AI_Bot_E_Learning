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
    $mode       = $_POST['mode'] ?? 'editor';

    if ($aksi === 'simpan' && $judul && $topik && $tipe) {
        $isi       = '';
        $file_path = null;

        if ($content_id) {
            $stmt = $pdo->prepare("SELECT file_path FROM content WHERE id = ?");
            $stmt->execute([$content_id]);
            $existing = $stmt->fetch();
            $file_path = $existing['file_path'] ?? null;
        }

        if ($mode === 'upload' && !empty($_FILES['file_konten']['name'])) {
            $allowed_ext  = ['pdf', 'doc', 'docx'];
            $allowed_mime = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];
            $ext  = strtolower(pathinfo($_FILES['file_konten']['name'], PATHINFO_EXTENSION));
            $mime = mime_content_type($_FILES['file_konten']['tmp_name']);

            if (in_array($ext, $allowed_ext) && in_array($mime, $allowed_mime)) {
                if ($file_path && file_exists(__DIR__ . '/' . $file_path)) {
                    unlink(__DIR__ . '/' . $file_path);
                }
                $filename = 'konten_' . ($content_id ?: time()) . '_' . time() . '.' . $ext;
                $dest     = __DIR__ . '/uploads/konten/' . $filename;
                if (move_uploaded_file($_FILES['file_konten']['tmp_name'], $dest)) {
                    $file_path = 'uploads/konten/' . $filename;
                    $isi = '';
                } else {
                    $pesan = 'Gagal menyimpan file.'; $pesan_ok = false;
                }
            } else {
                $pesan = 'Format file tidak diizinkan. Gunakan PDF, DOC, atau DOCX.'; $pesan_ok = false;
            }
        } elseif ($mode === 'editor') {
            $isi = $_POST['isi'] ?? '';
            if ($file_path && file_exists(__DIR__ . '/' . $file_path)) {
                unlink(__DIR__ . '/' . $file_path);
            }
            $file_path = null;
        } elseif ($tipe === 'evaluasi') {
            $soal_list = [];
            $soal_teks = $_POST['soal_teks'] ?? [];
            $soal_opsi = $_POST['soal_opsi'] ?? [];
            $soal_jwb  = $_POST['soal_jawaban'] ?? [];
            foreach ($soal_teks as $i => $teks) {
                if (!trim($teks)) continue;
                $soal_list[] = [
                    'soal' => trim($teks),
                    'opsi' => [
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
                // Deteksi pindah topik: cabut dari aturan topik lama
                $cek_lama = $pdo->prepare("SELECT topik, urutan_default FROM content WHERE id=?");
                $cek_lama->execute([$content_id]);
                $lama       = $cek_lama->fetch();
                $topik_lama = $lama['topik'] ?? null;

                // Geser bila posisi tujuan sudah dipakai konten lain
                $n_geser = 0;
                if ($topik_lama !== $topik || (int) $lama['urutan_default'] !== $urutan) {
                    $bentrok = $pdo->prepare("SELECT COUNT(*) FROM content WHERE topik=? AND urutan_default=? AND id<>?");
                    $bentrok->execute([$topik, $urutan, $content_id]);
                    if ($bentrok->fetchColumn() > 0) {
                        $n_geser = geser_urutan_konten($topik, $urutan, (int) $content_id);
                    }
                }

                $stmt = $pdo->prepare("UPDATE content SET judul=?, topik=?, tipe=?, isi=?, file_path=?, urutan_default=?, aktif=?, perlu_upload=? WHERE id=?");
                $stmt->execute([$judul, $topik, $tipe, $isi, $file_path, $urutan, $aktif, $perlu_upload, $content_id]);

                $pesan = 'Konten berhasil disimpan.';
                if ($n_geser > 0) {
                    $pesan .= " Disisipkan di posisi {$urutan} — {$n_geser} konten lain digeser ke bawah.";
                }
                if ($topik_lama && $topik_lama !== $topik) {
                    $n = cabut_konten_dari_rules((int) $content_id, $topik_lama);
                    $pesan .= " Topik berubah — dicabut dari {$n} aturan topik lama."
                            . ' Masukkan konten ini ke jalur belajar topik baru.';
                }
            } else {
                // Geser bila posisi yang diminta sudah dipakai
                $bentrok = $pdo->prepare("SELECT COUNT(*) FROM content WHERE topik=? AND urutan_default=?");
                $bentrok->execute([$topik, $urutan]);
                $n_geser = $bentrok->fetchColumn() > 0
                         ? geser_urutan_konten($topik, $urutan)
                         : 0;

                $stmt = $pdo->prepare("INSERT INTO content (judul, topik, tipe, isi, file_path, urutan_default, aktif, perlu_upload) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$judul, $topik, $tipe, $isi, $file_path, $urutan, $aktif, $perlu_upload]);
                $content_id = $pdo->lastInsertId();

                $pesan = 'Konten berhasil disimpan.';
                if ($n_geser > 0) {
                    $pesan .= " Disisipkan di posisi {$urutan} — {$n_geser} konten lain digeser ke bawah.";
                }
                $pesan .= ' Catatan: konten baru belum masuk jalur belajar profil mana pun — siswa belum bisa melihatnya.';
            }
        }
    } elseif ($aksi === 'hapus' && $content_id) {
        $stmt = $pdo->prepare("SELECT file_path FROM content WHERE id=?");
        $stmt->execute([$content_id]);
        $row = $stmt->fetch();
        if ($row['file_path'] && file_exists(__DIR__ . '/' . $row['file_path'])) {
            unlink(__DIR__ . '/' . $row['file_path']);
        }
        // Cabut dulu dari semua aturan adaptif agar tidak meninggalkan ID yatim
        $n_rules = cabut_konten_dari_rules((int) $content_id);
        $pdo->prepare("DELETE FROM content WHERE id=?")->execute([$content_id]);
        $pesan = 'Konten berhasil dihapus.'
               . ($n_rules > 0 ? " Dicabut juga dari {$n_rules} aturan jalur belajar." : '');
        $content_id = 0;
    }
}

$konten_list = $pdo->query("SELECT * FROM content ORDER BY topik, urutan_default, id")->fetchAll();

$edit = null;
$edit_id = (int)($_GET['edit'] ?? $content_id ?? 0);
if ($edit_id) {
    $stmt = $pdo->prepare("SELECT * FROM content WHERE id=?");
    $stmt->execute([$edit_id]);
    $edit = $stmt->fetch();
}

$topik_list = get_topik_list();

// Urutan berikutnya per topik — untuk isi otomatis field urutan pada konten baru
$urutan_next = [];
foreach (array_keys($topik_list) as $slug_t) {
    $q = $pdo->prepare("SELECT COALESCE(MAX(urutan_default), 0) + 1 FROM content WHERE topik = ?");
    $q->execute([$slug_t]);
    $urutan_next[$slug_t] = (int) $q->fetchColumn();
}

$tipe_list  = ['teori' => 'Teori', 'langkah' => 'Langkah Kerja', 'jobsheet' => 'Jobsheet', 'evaluasi' => 'Evaluasi', 'tantangan' => 'Tantangan'];

$page_title = 'Kelola Konten — AdaptLearn PRE';
$guru_aktif = 'konten';
include __DIR__ . '/includes/topbar_guru.php';
?>
<script src="https://cdn.tiny.cloud/1/vowvs5ciyejykt64n2v6bwrwrj8ftsh3tg9ubh494qcf9ri0/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
    selector: '#editor-isi',
    plugins: 'lists table link image code',
    toolbar: 'undo redo | formatselect | bold italic underline | alignleft aligncenter alignright | bullist numlist | table | link image | code',
    height: 400,
    language: 'id',
    content_style: "body { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; line-height: 1.7; }",
    table_default_attributes: { border: '1' },
    table_default_styles: { 'border-collapse': 'collapse', 'width': '100%' },
});
</script>

<div class="crumb">
    <a href="dashboard_guru.php">Dashboard</a>
    <span class="sep">›</span>
    <span class="now">Kelola Konten</span>
</div>

<div class="klayout">

    <!-- SIDEBAR: DAFTAR KONTEN -->
    <aside>
        <div class="card" style="padding:16px">
            <div class="card-h" style="margin-bottom:12px">
                <h3><i class="icon-files"></i> Daftar konten</h3>
                <a href="kelola_konten.php?edit=0" class="btn btn-3 btn-sm"><i class="icon-plus"></i> Baru</a>
            </div>

            <?php if ($pesan): ?>
                <div class="pt <?= $pesan_ok ? 'buka' : 'kunci' ?>" style="padding:11px 13px;margin-bottom:12px">
                    <div class="pt-ic" style="width:28px;height:28px;font-size:14px">
                        <i class="<?= $pesan_ok ? 'icon-circle-check' : 'icon-circle-alert' ?>"></i>
                    </div>
                    <div><b style="margin:0;font-size:12.5px"><?= htmlspecialchars($pesan) ?></b></div>
                </div>
            <?php endif; ?>

            <?php foreach (get_topik_tree() as $parent):
                $slugs = [];
                if (empty($parent['children'])) { $slugs[] = $parent['slug']; }
                else { foreach ($parent['children'] as $child) $slugs[] = $child['slug']; }
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
                        <div class="kgroup">
                            <div class="kgroup-t"><?= htmlspecialchars($parent['nama']) ?> › <?= htmlspecialchars($child['nama']) ?></div>
                            <?php foreach ($child_konten as $k): ?>
                                <a href="kelola_konten.php?edit=<?= $k['id'] ?>" class="kitem <?= $edit_id == $k['id'] ? 'on' : '' ?>">
                                    <span style="min-width:18px;font-size:11px;font-weight:800;color:var(--abu-muda);text-align:right"><?= (int) $k['urutan_default'] ?></span>
                                    <span class="tipe tipe-<?= $k['tipe'] ?>"><?= strtoupper($k['tipe']) ?></span>
                                    <span class="kitem-j"><?= htmlspecialchars(mb_strimwidth($k['judul'],0,28,'…')) ?></span>
                                    <?php if (!$k['aktif']): ?><i class="icon-eye-off" style="color:var(--abu-muda);font-size:13px"></i><?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php elseif ($ada_konten): ?>
                    <div class="kgroup">
                        <div class="kgroup-t"><?= htmlspecialchars($parent['nama']) ?></div>
                        <?php foreach ($konten_list as $k): ?>
                            <?php if ($k['topik'] !== $parent['slug']) continue; ?>
                            <a href="kelola_konten.php?edit=<?= $k['id'] ?>" class="kitem <?= $edit_id == $k['id'] ? 'on' : '' ?>">
                                <span style="min-width:18px;font-size:11px;font-weight:800;color:var(--abu-muda);text-align:right"><?= (int) $k['urutan_default'] ?></span>
                                <span class="tipe tipe-<?= $k['tipe'] ?>"><?= strtoupper($k['tipe']) ?></span>
                                <span class="kitem-j"><?= htmlspecialchars(mb_strimwidth($k['judul'],0,28,'…')) ?></span>
                                <?php if (!$k['aktif']): ?><i class="icon-eye-off" style="color:var(--abu-muda);font-size:13px"></i><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </aside>

    <!-- MAIN: FORM EDIT -->
    <div>
        <?php if ($edit !== null || isset($_GET['edit'])): ?>
        <div class="card">
            <div class="card-h" style="margin-bottom:16px">
                <h3><i class="icon-file-pen"></i> <?= $edit ? htmlspecialchars(mb_strimwidth($edit['judul'],0,36,'…')) : 'Tambah konten baru' ?></h3>
                <?php if ($edit): ?>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Hapus konten ini?')">
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="content_id" value="<?= $edit['id'] ?>">
                        <button type="submit" class="btn btn-sm" style="background:var(--coral-muda);color:var(--coral)"><i class="icon-trash-2"></i> Hapus</button>
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

                <div class="fg">
                    <label>Judul konten</label>
                    <input type="text" name="judul" value="<?= htmlspecialchars($edit['judul'] ?? '') ?>" placeholder="Judul konten…" required>
                </div>

                <div class="grow2">
                    <div class="fg">
                        <label>Topik</label>
                        <?php $topik_tree = get_topik_tree(); ?>
                        <select name="topik" id="select-topik" onchange="handleTopikChange(this.value)" required>
                            <option value="">— Pilih topik —</option>
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
                            <option value="__baru_parent__">+ Tambah topik baru (parent)</option>
                            <option value="__baru_sub__">+ Tambah sub-topik baru</option>
                        </select>

                        <div id="wrap-topik-baru-parent" class="kbox" style="display:none">
                            <div class="kbox-t"><i class="icon-plus"></i> Topik baru (parent)</div>
                            <input type="text" id="nama-topik-baru" placeholder="Nama topik (contoh: Catu Daya Lanjutan)">
                            <input type="text" id="slug-topik-baru" placeholder="Slug otomatis…" readonly style="color:var(--abu-muda)">
                            <div class="rata">
                                <button type="button" class="btn btn-3 btn-sm" onclick="simpanTopikBaru('parent')">Simpan &amp; pilih</button>
                                <button type="button" class="btn btn-2 btn-sm" onclick="batalTopikBaru()">Batal</button>
                            </div>
                            <div id="msg-topik-baru-parent" style="font-size:12px;margin-top:8px"></div>
                        </div>

                        <div id="wrap-topik-baru-sub" class="kbox" style="display:none">
                            <div class="kbox-t"><i class="icon-plus"></i> Sub-topik baru</div>
                            <div style="font-size:12px;color:var(--abu);margin-bottom:6px">Parent (topik utama):</div>
                            <select id="parent-sub-baru">
                                <?php foreach ($topik_tree as $parent): ?>
                                    <option value="<?= $parent['id'] ?>"><?= htmlspecialchars($parent['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" id="nama-sub-baru" placeholder="Nama sub-topik (contoh: Pengukuran Dioda)">
                            <input type="text" id="slug-sub-baru" placeholder="Slug otomatis…" readonly style="color:var(--abu-muda)">
                            <div class="rata">
                                <button type="button" class="btn btn-3 btn-sm" onclick="simpanTopikBaru('sub')">Simpan &amp; pilih</button>
                                <button type="button" class="btn btn-2 btn-sm" onclick="batalTopikBaru()">Batal</button>
                            </div>
                            <div id="msg-topik-baru-sub" style="font-size:12px;margin-top:8px"></div>
                        </div>
                    </div>

                    <div class="fg">
                        <label>Tipe</label>
                        <select name="tipe" id="select-tipe" onchange="handleTipeChange(this.value)" required>
                            <?php foreach ($tipe_list as $s => $n): ?>
                                <option value="<?= $s ?>" <?= ($edit['tipe'] ?? '') === $s ? 'selected' : '' ?>><?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grow3">
                    <div class="fg">
                        <label>Urutan tampil
                            <span style="font-weight:500;color:var(--abu-muda);font-size:11.5px">
                                — angka lebih kecil tampil lebih dulu
                            </span>
                        </label>
                        <input type="number" id="input-urutan" name="urutan_default"
                               value="<?= $edit['urutan_default'] ?? 1 ?>" min="1">
                        <?php if (!$edit): ?>
                            <div style="font-size:11.5px;color:var(--abu-muda);margin-top:4px">
                                Terisi otomatis saat topik dipilih. Ubah hanya jika ingin menyisipkan di posisi tertentu.
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="fg" style="display:flex;align-items:flex-end;padding-bottom:10px">
                        <label class="cbx"><input type="checkbox" name="aktif" <?= ($edit['aktif'] ?? 1) ? 'checked' : '' ?>> Konten aktif</label>
                    </div>
                    <div class="fg" style="display:flex;align-items:flex-end;padding-bottom:10px" id="wrap-perlu-upload">
                        <label class="cbx"><input type="checkbox" name="perlu_upload" <?= ($edit['perlu_upload'] ?? 0) ? 'checked' : '' ?>> Perlu upload siswa</label>
                    </div>
                </div>

                <!-- MODE TOGGLE -->
                <div id="wrap-mode-toggle" <?= $is_evaluasi ? 'style="display:none"' : '' ?>>
                    <label style="font-size:12px;font-weight:700;color:var(--tinta);margin-bottom:8px;display:block">Mode input konten</label>
                    <div class="mtoggle">
                        <div class="mbtn <?= !$has_file ? 'on' : '' ?>" onclick="setMode('editor')" id="btn-mode-editor"><i class="icon-pencil"></i> Ketik di editor</div>
                        <div class="mbtn <?= $has_file ? 'on' : '' ?>" onclick="setMode('upload')" id="btn-mode-upload"><i class="icon-paperclip"></i> Upload file</div>
                    </div>
                </div>

                <!-- MODE EDITOR -->
                <div id="wrap-editor" <?= $has_file ? 'style="display:none"' : '' ?>>
                    <div class="fg">
                        <label>Isi konten</label>
                        <textarea id="editor-isi" name="isi"><?= htmlspecialchars($edit['isi'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- MODE UPLOAD -->
                <div id="wrap-upload" <?= !$has_file ? 'style="display:none"' : '' ?>>
                    <div class="fg">
                        <label>Upload file (PDF, DOC, DOCX — maks 10MB)</label>
                        <input type="file" name="file_konten" accept=".pdf,.doc,.docx" onchange="previewFile(this)">
                        <?php if ($has_file): ?>
                            <div class="kupload" id="file-preview">
                                <i class="icon-file-check" style="color:var(--teal)"></i> File saat ini:
                                <a href="/<?= htmlspecialchars($edit['file_path']) ?>" target="_blank" style="color:var(--biru);font-weight:700"><?= basename($edit['file_path']) ?></a>
                                <div style="font-size:11px;color:var(--abu-muda);margin-top:4px">Upload file baru untuk mengganti</div>
                            </div>
                        <?php else: ?>
                            <div class="kupload" id="file-preview" style="display:none"></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- MODE EVALUASI -->
                <div id="wrap-evaluasi" <?= !$is_evaluasi ? 'style="display:none"' : '' ?>>
                    <div class="rata" style="justify-content:space-between;margin-bottom:12px">
                        <label style="font-size:12px;font-weight:700;color:var(--tinta)">Daftar soal</label>
                        <button type="button" class="btn btn-2 btn-sm" onclick="tambahSoal()"><i class="icon-plus"></i> Tambah soal</button>
                    </div>
                    <div id="soal-container">
                        <?php foreach ($soal_list as $i => $soal): ?>
                            <div class="ksoal" id="soal-<?= $i ?>">
                                <div class="ksoal-h">
                                    <span>Soal <?= $i+1 ?></span>
                                    <button type="button" class="btn btn-sm" style="background:var(--coral-muda);color:var(--coral)" onclick="hapusSoal(<?= $i ?>)"><i class="icon-x"></i></button>
                                </div>
                                <div class="fg">
                                    <label>Teks soal</label>
                                    <textarea name="soal_teks[<?= $i ?>]" rows="2"><?= htmlspecialchars($soal['soal']) ?></textarea>
                                </div>
                                <?php foreach (['A','B','C','D'] as $huruf): ?>
                                    <div class="ogrid">
                                        <div class="olabel"><?= $huruf ?></div>
                                        <input type="text" name="soal_opsi[<?= $i ?>][<?= $huruf ?>]" value="<?= htmlspecialchars($soal['opsi'][$huruf] ?? '') ?>" placeholder="Opsi <?= $huruf ?>">
                                    </div>
                                <?php endforeach; ?>
                                <div class="fg" style="margin-top:8px">
                                    <label>Jawaban benar</label>
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

                <div class="rata" style="margin-top:20px">
                    <button type="submit" class="btn btn-3" onclick="return submitForm()"><i class="icon-save"></i> Simpan konten</button>
                    <a href="kelola_konten.php" class="btn btn-2">Batal</a>
                </div>
            </form>
        </div>
        <?php else: ?>
            <div class="card">
                <div class="kosong">
                    <i class="icon-file-pen"></i>
                    <b>Belum ada konten dipilih</b>
                    <p>Pilih konten dari daftar untuk diedit, atau klik <b>+ Baru</b> untuk menambah.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>

<style>
.klayout { display:grid; grid-template-columns:320px 1fr; gap:20px; max-width:1200px; margin:0 auto; padding:18px 16px 28px; }
@media (max-width:768px){ .klayout { grid-template-columns:1fr; } }

.kgroup { margin-bottom:14px; }
.kgroup-t { font-size:10.5px; font-weight:800; text-transform:uppercase; letter-spacing:.6px; color:var(--abu-muda); margin-bottom:7px; }
.kitem { display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:var(--r-sm); cursor:pointer;
    border:1px solid transparent; margin-bottom:4px; text-decoration:none; color:var(--tinta); transition:.15s; }
.kitem:hover { background:var(--kanvas); }
.kitem.on { background:var(--teal-muda); border-color:#99E6E5; }
.kitem-j { font-size:12.5px; flex:1; min-width:0; }

.fg { margin-bottom:14px; }
.fg label { display:block; font-size:12px; font-weight:700; color:var(--tinta); margin-bottom:5px; }
.fg input, .fg select, .fg textarea { width:100%; padding:10px 12px; border:2px solid var(--garis); border-radius:var(--r-sm);
    font-size:13px; font-family:inherit; outline:none; transition:.15s; color:var(--tinta); background:#fff; }
.fg input:focus, .fg select:focus, .fg textarea:focus { border-color:var(--teal); background:var(--teal-muda); }
.grow2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.grow3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
@media (max-width:600px){ .grow2,.grow3 { grid-template-columns:1fr; } }
.cbx { display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; color:var(--abu); cursor:pointer; }
.cbx input { width:auto; accent-color:var(--teal); }

.kbox { margin-top:10px; background:var(--teal-muda); border:2px solid #99E6E5; border-radius:var(--r-sm); padding:14px; }
.kbox-t { font-size:12px; font-weight:800; color:#0C807F; margin-bottom:10px; display:flex; align-items:center; gap:6px; }
.kbox input, .kbox select { margin-bottom:8px; }

.mtoggle { display:flex; gap:8px; margin-bottom:16px; }
.mbtn { flex:1; padding:11px; border:2px solid var(--garis); border-radius:var(--r-sm); background:#fff;
    font-size:12.5px; font-weight:700; cursor:pointer; color:var(--abu-muda); transition:.15s; text-align:center;
    display:flex; align-items:center; justify-content:center; gap:6px; }
.mbtn.on { border-color:var(--teal); color:var(--teal); background:var(--teal-muda); }

.kupload { background:var(--kanvas); border:2px dashed var(--garis); border-radius:var(--r-sm); padding:14px; font-size:12.5px; color:var(--abu); margin-top:8px; }

.ksoal { background:var(--kanvas); border-radius:var(--r-md); padding:16px; margin-bottom:12px; border:1px solid var(--garis); }
.ksoal-h { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; font-size:13px; font-weight:800; color:var(--teal); }
.ogrid { display:grid; grid-template-columns:28px 1fr; gap:6px; align-items:center; margin-bottom:6px; }
.olabel { font-size:12px; font-weight:800; color:var(--abu); text-align:center; }
.ogrid input { width:100%; padding:8px 10px; border:2px solid var(--garis); border-radius:var(--r-sm); font-size:13px; font-family:inherit; outline:none; }
.ogrid input:focus { border-color:var(--teal); }
</style>

<script>
var soalCount = <?= count($soal_list) ?>;

function setMode(mode) {
    document.getElementById('input-mode').value = mode;
    document.getElementById('wrap-editor').style.display = mode === 'editor' ? '' : 'none';
    document.getElementById('wrap-upload').style.display = mode === 'upload' ? '' : 'none';
    document.getElementById('btn-mode-editor').classList.toggle('on', mode === 'editor');
    document.getElementById('btn-mode-upload').classList.toggle('on', mode === 'upload');
}

function handleTipeChange(tipe) {
    var isEval = tipe === 'evaluasi';
    document.getElementById('wrap-mode-toggle').style.display = isEval ? 'none' : '';
    document.getElementById('wrap-evaluasi').style.display    = isEval ? '' : 'none';
    document.getElementById('wrap-editor').style.display       = isEval ? 'none' : (document.getElementById('input-mode').value === 'editor' ? '' : 'none');
    if (isEval) document.getElementById('input-mode').value = 'evaluasi';
}

function tambahSoal() {
    var i = soalCount++;
    var html = `
    <div class="ksoal" id="soal-${i}">
        <div class="ksoal-h"><span>Soal ${i+1}</span> <button type="button" class="btn btn-sm" style="background:var(--coral-muda);color:var(--coral)" onclick="hapusSoal(${i})"><i class="icon-x"></i></button></div>
        <div class="fg">
            <label>Teks soal</label>
            <textarea name="soal_teks[${i}]" rows="2"></textarea>
        </div>
        ${['A','B','C','D'].map(h => `
        <div class="ogrid">
            <div class="olabel">${h}</div>
            <input type="text" name="soal_opsi[${i}][${h}]" placeholder="Opsi ${h}">
        </div>`).join('')}
        <div class="fg" style="margin-top:8px">
            <label>Jawaban benar</label>
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
        preview.innerHTML = '<i class="icon-file-check" style="color:var(--teal)"></i> File dipilih: <b>' + input.files[0].name + '</b> (' + (input.files[0].size / 1024).toFixed(0) + ' KB)';
    }
}

function submitForm() {
    var mode = document.getElementById('input-mode').value;
    if (mode === 'editor' && typeof tinymce !== 'undefined') {
        tinymce.triggerSave();
    }
    return true;
}

handleTipeChange(document.getElementById('select-tipe')?.value || 'teori');

const URUTAN_NEXT = <?= json_encode($urutan_next) ?>;
const MODE_EDIT   = <?= $edit ? 'true' : 'false' ?>;

function handleTopikChange(val) {
    document.getElementById('wrap-topik-baru-parent').style.display = 'none';
    document.getElementById('wrap-topik-baru-sub').style.display = 'none';
    if (val === '__baru_parent__') {
        document.getElementById('wrap-topik-baru-parent').style.display = 'block';
        document.getElementById('select-topik').value = '';
    } else if (val === '__baru_sub__') {
        document.getElementById('wrap-topik-baru-sub').style.display = 'block';
        document.getElementById('select-topik').value = '';
    } else if (!MODE_EDIT && val) {
        // Konten baru: isi urutan otomatis = urutan terakhir di topik itu + 1
        var f = document.getElementById('input-urutan');
        if (f && URUTAN_NEXT[val]) f.value = URUTAN_NEXT[val];
    }
}

function toSlug(str) {
    return str.toLowerCase().replace(/[^a-z0-9\s_]/g, '').trim().replace(/\s+/g, '_');
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
        nama = document.getElementById('nama-topik-baru').value.trim();
        slug = document.getElementById('slug-topik-baru').value.trim();
        parentId = null;
        msgEl = document.getElementById('msg-topik-baru-parent');
    } else {
        nama = document.getElementById('nama-sub-baru').value.trim();
        slug = document.getElementById('slug-sub-baru').value.trim();
        parentId = document.getElementById('parent-sub-baru').value;
        msgEl = document.getElementById('msg-topik-baru-sub');
    }
    if (!nama || !slug) {
        msgEl.style.color = 'var(--coral)';
        msgEl.textContent = 'Nama dan slug wajib diisi.';
        return;
    }
    msgEl.style.color = 'var(--abu-muda)';
    msgEl.textContent = 'Menyimpan…';

    var fd = new FormData();
    fd.append('aksi', 'tambah_topik');
    fd.append('nama', nama);
    fd.append('slug', slug);
    if (parentId) fd.append('parent_id', parentId);

    fetch('api/kelola_topik.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                var sel = document.getElementById('select-topik');
                var opt = document.createElement('option');
                opt.value = data.slug;
                opt.text  = data.nama;
                var idxParent = Array.from(sel.options).findIndex(o => o.value === '__baru_parent__');
                sel.insertBefore(opt, sel.options[idxParent]);
                sel.value = data.slug;
                document.getElementById('wrap-topik-baru-parent').style.display = 'none';
                document.getElementById('wrap-topik-baru-sub').style.display = 'none';
                msgEl.textContent = '';
            } else {
                msgEl.style.color = 'var(--coral)';
                msgEl.textContent = data.msg || 'Gagal menyimpan topik.';
            }
        })
        .catch(() => {
            msgEl.style.color = 'var(--coral)';
            msgEl.textContent = 'Koneksi gagal.';
        });
}
</script>

</body>
</html>
