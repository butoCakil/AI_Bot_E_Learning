<?php
require_once dirname(__DIR__) . '/config/config.php';

$token = $_POST['token'] ?? '';
if ($token !== 'smkbansari2024') {
    http_response_code(403);
    header('Location: /dashboard_guru.php?token=smkbansari2024');
    exit;
}

$content_id = (int) ($_POST['content_id'] ?? 0);
$nilai      = (int) ($_POST['nilai'] ?? 0);

if (!$content_id) {
    header('Location: /dashboard_guru.php?token=smkbansari2024');
    exit;
}

$pdo = db();
$stmt = $pdo->prepare("UPDATE content SET perlu_upload = ? WHERE id = ? AND tipe = 'jobsheet'");
$stmt->execute([$nilai ? 1 : 0, $content_id]);

header('Location: /dashboard_guru.php?token=smkbansari2024');
exit;
