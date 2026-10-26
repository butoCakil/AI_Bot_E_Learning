<?php
// Melayani file kiriman siswa: guru = semua, siswa = milik sendiri.
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
session_start();

$guru = !empty($_SESSION['role']) && $_SESSION['role'] === 'guru';
$uid  = (int) ($_SESSION['user_id'] ?? 0);
if (!$guru && !$uid) { http_response_code(403); exit('Akses ditolak'); }

$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare("SELECT user_id, file_path, file_original_name FROM jobsheet_submissions WHERE id = ?");
$st->execute([$id]);
$f = $st->fetch();
if (!$f || (!$guru && (int) $f['user_id'] !== $uid)) { http_response_code(404); exit('File tidak ditemukan'); }

// Pastikan file benar-benar di dalam uploads/jobsheet
$base = realpath(dirname(__DIR__) . '/uploads/jobsheet');
$path = realpath(dirname(__DIR__) . '/' . $f['file_path']);
if (!$base || !$path || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
    http_response_code(404); exit('File tidak ditemukan');
}

$ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
][$ext] ?? 'application/octet-stream';

$unduh = isset($_GET['unduh']) || !in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true);
$nama  = str_replace(['"', "\r", "\n"], '', $f['file_original_name']);

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=300');
header('Content-Disposition: ' . ($unduh ? 'attachment' : 'inline') . '; filename="' . $nama . '"; filename*=UTF-8\'\'' . rawurlencode($f['file_original_name']));
readfile($path);
