<?php
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    header('Location: login_guru.php');
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare("
    SELECT js.id, js.file_path, js.file_original_name, js.created_at, u.nama, u.nis, u.kelas, c.judul
    FROM jobsheet_submissions js
    JOIN users u   ON u.id = js.user_id
    JOIN content c ON c.id = js.content_id
    WHERE js.id = ?
");
$st->execute([$id]);
$f = $st->fetch();
if (!$f) { http_response_code(404); exit('Kiriman tidak ditemukan'); }

$ext = strtolower(pathinfo($f['file_path'], PATHINFO_EXTENSION));
$src = 'api/file_kiriman.php?id=' . (int) $f['id'];
function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kiriman — <?= h($f['nama']) ?></title>
<style>
    body { margin:0; font-family:'Plus Jakarta Sans', system-ui, sans-serif; background:#F1F5F9; color:#0F172A; }
    .bar { position:sticky; top:0; z-index:2; background:#fff; border-bottom:1px solid #E2E8F0; padding:10px 16px; display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
    .bar b { font-size:14px; } .bar small { color:#64748B; font-size:12.5px; }
    .bar a { margin-left:auto; background:#0D9488; color:#fff; text-decoration:none; font-weight:700; font-size:13px; padding:8px 14px; border-radius:8px; }
    .wadah { padding:16px; max-width:1100px; margin:0 auto; }
    iframe { width:100%; height:calc(100vh - 100px); border:0; background:#fff; border-radius:8px; }
    img { max-width:100%; display:block; margin:0 auto; background:#fff; border-radius:8px; }
    .pesan { background:#fff; padding:30px; border-radius:12px; text-align:center; color:#475569; font-size:14px; }
    #docx { background:#fff; border-radius:8px; overflow:auto; }
</style>
</head>
<body>
<div class="bar">
    <div>
        <b><?= h($f['nama']) ?></b> <small><?= h($f['nis']) ?> · <?= h($f['kelas'] ?: '-') ?></small><br>
        <small><?= h($f['judul']) ?> · <?= h($f['file_original_name']) ?> · <?= h($f['created_at']) ?></small>
    </div>
    <a href="<?= h($src) ?>&amp;unduh=1">Unduh file</a>
</div>
<div class="wadah">
<?php if (in_array($ext, ['jpg', 'jpeg', 'png'], true)): ?>
    <img src="<?= h($src) ?>" alt="<?= h($f['file_original_name']) ?>">
<?php elseif ($ext === 'pdf'): ?>
    <iframe src="<?= h($src) ?>" title="<?= h($f['file_original_name']) ?>"></iframe>
<?php elseif ($ext === 'docx'): ?>
    <div id="docx"><div class="pesan">Memuat pratinjau dokumen…</div></div>
    <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/docx-preview@0.3/dist/docx-preview.min.js"></script>
    <script>
    fetch(<?= json_encode($src) ?>)
        .then(function (r) { if (!r.ok) throw new Error(); return r.blob(); })
        .then(function (b) { var w = document.getElementById('docx'); w.innerHTML = ''; return docx.renderAsync(b, w); })
        .catch(function () {
            document.getElementById('docx').innerHTML = '<div class="pesan">Pratinjau gagal dimuat. Gunakan tombol Unduh file.</div>';
        });
    </script>
<?php else: ?>
    <div class="pesan">Format .<?= h($ext) ?> tidak bisa dipratinjau di browser. Gunakan tombol <b>Unduh file</b>.</div>
<?php endif; ?>
</div>
</body>
</html>
