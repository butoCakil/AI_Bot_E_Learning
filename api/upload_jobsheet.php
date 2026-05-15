<?php
session_start();
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Belum login']);
    exit;
}

$user_id    = (int) $_SESSION['user_id'];
$content_id = (int) ($_POST['content_id'] ?? 0);
$topik      = trim($_POST['topik'] ?? '');

if (!$content_id || !$topik) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Parameter tidak lengkap']);
    exit;
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'File tidak valid atau tidak diunggah']);
    exit;
}

$allowed_ext  = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
$allowed_mime = [
    'image/jpeg', 'image/png',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

$original_name = $_FILES['file']['name'];
$ext           = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
$mime          = mime_content_type($_FILES['file']['tmp_name']);
$size          = $_FILES['file']['size'];

if (!in_array($ext, $allowed_ext) || !in_array($mime, $allowed_mime)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Format file tidak diizinkan']);
    exit;
}

if ($size > 5 * 1024 * 1024) { // maks 5MB
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Ukuran file maksimal 5MB']);
    exit;
}

$upload_dir = dirname(__DIR__) . '/uploads/jobsheet/';
$filename   = 'js_' . $user_id . '_' . $content_id . '_' . time() . '.' . $ext;
$dest       = $upload_dir . $filename;

if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Gagal menyimpan file']);
    exit;
}

$pdo = db();

// Hapus file lama jika ada (re-upload)
$stmt = $pdo->prepare("SELECT file_path FROM jobsheet_submissions WHERE user_id = ? AND content_id = ?");
$stmt->execute([$user_id, $content_id]);
$lama = $stmt->fetch();
if ($lama && file_exists(dirname(__DIR__) . '/' . $lama['file_path'])) {
    unlink(dirname(__DIR__) . '/' . $lama['file_path']);
}

// Simpan atau update submission
$stmt = $pdo->prepare("
    INSERT INTO jobsheet_submissions (user_id, content_id, topik, file_path, file_original_name)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        file_path = VALUES(file_path),
        file_original_name = VALUES(file_original_name),
        nilai = NULL,
        dinilai_at = NULL,
        created_at = CURRENT_TIMESTAMP
");
$stmt->execute([
    $user_id,
    $content_id,
    $topik,
    'uploads/jobsheet/' . $filename,
    $original_name
]);

log_aktivitas($user_id, 'upload_jobsheet', $content_id, $topik);

echo json_encode(['ok' => true, 'filename' => $original_name]);
