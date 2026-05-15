<?php
session_start();
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    http_response_code(403);
    header('Location: /login_guru.php');
    exit;
}

$pdo = db();

$siswa_list = $pdo->query("
    SELECT u.nama, u.nis, u.kelas,
           p.profil_learning, p.level_kemampuan,
           p.skor_pengetahuan as skor_pre,
           p.created_at as tgl_pretest,
           pt.skor_pengetahuan as skor_post,
           pt.created_at as tgl_posttest
    FROM users u
    LEFT JOIN pre_test_results p ON p.id = (
        SELECT id FROM pre_test_results WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1
    )
    LEFT JOIN post_test_results pt ON pt.id = (
        SELECT id FROM post_test_results WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1
    )
    WHERE u.role = 'siswa'
    ORDER BY u.kelas, u.nama
")->fetchAll();

$label_profil = [
    'guided_step'       => 'Guided-Step Learner',
    'conceptual'        => 'Conceptual Learner',
    'practice_oriented' => 'Practice-Oriented Learner',
];
$label_level = [
    'beginner'     => 'Pemula',
    'intermediate' => 'Menengah',
    'advanced'     => 'Mahir',
];

// Header HTTP untuk download CSV
$filename = 'ngain_adaptlearn_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// BOM untuk Excel agar UTF-8 terbaca dengan benar
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

// Header kolom
fputcsv($out, [
    'No', 'Nama', 'NIS', 'Kelas',
    'Profil Belajar', 'Level Kemampuan',
    'Skor Pre-Test', 'Skor Post-Test', 'Skor Maks',
    'N-Gain', 'Kategori N-Gain',
    'Tgl Pre-Test', 'Tgl Post-Test'
]);

$no = 1;
foreach ($siswa_list as $s) {
    $skor_pre  = $s['skor_pre'] !== null ? (int)$s['skor_pre'] : null;
    $skor_post = $s['skor_post'] !== null ? (int)$s['skor_post'] : null;

    $ngain_val = '-';
    $kategori  = '-';

    if ($skor_pre !== null && $skor_post !== null) {
        $hasil    = hitung_ngain($skor_pre, $skor_post, 12);
        $ngain_val = number_format($hasil['ngain'], 4);
        $kategori  = $hasil['kategori'];
    }

    fputcsv($out, [
        $no++,
        $s['nama'],
        $s['nis'] ?? '-',
        $s['kelas'] ?? '-',
        $label_profil[$s['profil_learning']] ?? ($s['profil_learning'] ?? '-'),
        $label_level[$s['level_kemampuan']] ?? ($s['level_kemampuan'] ?? '-'),
        $skor_pre ?? '-',
        $skor_post ?? '-',
        12,
        $ngain_val,
        $kategori,
        $s['tgl_pretest'] ? date('d/m/Y H:i', strtotime($s['tgl_pretest'])) : '-',
        $s['tgl_posttest'] ? date('d/m/Y H:i', strtotime($s['tgl_posttest'])) : '-',
    ]);
}

fclose($out);
exit;
