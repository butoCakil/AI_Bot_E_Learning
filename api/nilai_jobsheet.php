<?php
require_once dirname(__DIR__) . '/config/config.php';

$token = $_POST['token'] ?? '';
if ($token !== 'smkbansari2024') {
    http_response_code(403);
    header('Location: /dashboard_guru.php?token=smkbansari2024');
    exit;
}

$submission_id = (int) ($_POST['submission_id'] ?? 0);
$nilai         = $_POST['nilai'] ?? '';

if (!$submission_id || $nilai === '') {
    header('Location: /dashboard_guru.php?token=smkbansari2024');
    exit;
}

$nilai = max(0, min(100, (float) $nilai));

$pdo = db();
$stmt = $pdo->prepare("UPDATE jobsheet_submissions SET nilai = ?, dinilai_at = NOW() WHERE id = ?");
$stmt->execute([$nilai, $submission_id]);

header('Location: /dashboard_guru.php?token=smkbansari2024');
exit;
