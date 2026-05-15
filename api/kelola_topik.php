<?php
session_start();
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Akses ditolak']);
    exit;
}

header('Content-Type: application/json');
$pdo = db();
$aksi = $_POST['aksi'] ?? '';

if ($aksi === 'tambah_topik') {
    $nama      = trim($_POST['nama'] ?? '');
    $slug      = trim($_POST['slug'] ?? '');
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    if (!$nama || !$slug) {
        echo json_encode(['ok' => false, 'msg' => 'Nama dan slug wajib diisi']);
        exit;
    }

    // Validasi slug: hanya huruf kecil, angka, underscore
    if (!preg_match('/^[a-z0-9_]+$/', $slug)) {
        echo json_encode(['ok' => false, 'msg' => 'Slug hanya boleh huruf kecil, angka, dan underscore']);
        exit;
    }

    // Cek slug sudah ada
    $cek = $pdo->prepare("SELECT id FROM topik WHERE slug = ?");
    $cek->execute([$slug]);
    if ($cek->fetch()) {
        echo json_encode(['ok' => false, 'msg' => 'Slug sudah digunakan, gunakan nama lain']);
        exit;
    }

    // Hitung urutan
    if ($parent_id) {
        $urutan = $pdo->prepare("SELECT COALESCE(MAX(urutan),0)+1 FROM topik WHERE parent_id = ?");
        $urutan->execute([$parent_id]);
    } else {
        $urutan = $pdo->query("SELECT COALESCE(MAX(urutan),0)+1 FROM topik WHERE parent_id IS NULL");
    }
    $urutan_val = (int)$urutan->fetchColumn();

    $stmt = $pdo->prepare("INSERT INTO topik (slug, nama, parent_id, urutan, aktif) VALUES (?, ?, ?, ?, 1)");
    $stmt->execute([$slug, $nama, $parent_id, $urutan_val]);

    echo json_encode(['ok' => true, 'slug' => $slug, 'nama' => $nama]);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Aksi tidak dikenal']);
