<?php
session_start();
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    header('Location: /login_guru.php');
    exit;
}

$pdo = db();

// ── Item dinilai, urut sesuai urutan topik ──
$nama_topik_map = get_topik_list();
$urut_topik     = array_flip(array_keys($nama_topik_map));

$item = $pdo->query("
    SELECT id, judul, tipe, topik, urutan_default
    FROM content
    WHERE aktif = 1 AND tipe IN ('evaluasi','jobsheet')
")->fetchAll();

usort($item, function ($a, $b) use ($urut_topik) {
    $ta = $urut_topik[$a['topik']] ?? 999;
    $tb = $urut_topik[$b['topik']] ?? 999;
    if ($ta !== $tb) return $ta <=> $tb;
    if ($a['urutan_default'] !== $b['urutan_default']) return $a['urutan_default'] <=> $b['urutan_default'];
    return $a['id'] <=> $b['id'];
});

$eval = [];
foreach ($pdo->query("SELECT user_id, content_id, persentase FROM evaluasi_results")->fetchAll() as $r) {
    $eval[$r['user_id']][$r['content_id']] = (int) $r['persentase'];
}
$job_ada = $job = [];
foreach ($pdo->query("SELECT user_id, content_id, nilai FROM jobsheet_submissions")->fetchAll() as $r) {
    $job_ada[$r['user_id']][$r['content_id']] = true;
    if ($r['nilai'] !== null) $job[$r['user_id']][$r['content_id']] = (float) $r['nilai'];
}

$siswa = $pdo->query("
    SELECT u.id, u.nama, u.nis, u.kelas,
           p.profil_gabungan,
           p.skor_pengetahuan AS skor_pre,
           pt.skor_pengetahuan AS skor_post
    FROM users u
    LEFT JOIN pre_test_results p ON p.id = (
        SELECT id FROM pre_test_results WHERE user_id = u.id ORDER BY id DESC LIMIT 1)
    LEFT JOIN post_test_results pt ON pt.id = (
        SELECT id FROM post_test_results WHERE user_id = u.id ORDER BY id DESC LIMIT 1)
    WHERE u.role = 'siswa'
    ORDER BY u.kelas, u.nama
")->fetchAll();

// ── Susun spreadsheet ──
$ss    = new Spreadsheet();
$sheet = $ss->getActiveSheet();
$sheet->setTitle('Rekap Nilai');

$header = ['NIS', 'Nama Lengkap', 'Kelas', 'Profil'];
foreach ($item as $it) {
    $header[] = ($it['tipe'] === 'evaluasi' ? 'Eval: ' : 'Job: ')
              . ($nama_topik_map[$it['topik']] ?? $it['topik']);
}
$header = array_merge($header, ['Pre-Test', 'Post-Test', 'N-Gain', 'Kategori']);

$sheet->fromArray($header, null, 'A1');

$kol_terakhir = $sheet->getHighestColumn();
$sheet->getStyle('A1:' . $kol_terakhir . '1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0EA5A4']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
]);
$sheet->getRowDimension(1)->setRowHeight(34);

$baris = 2;
foreach ($siswa as $s) {
    $data = [
        $s['nis'],
        $s['nama'],
        $s['kelas'] ?? '',
        $s['profil_gabungan'] ?? '',
    ];
    foreach ($item as $it) {
        $cid = $it['id'];
        if ($it['tipe'] === 'evaluasi') {
            $data[] = $eval[$s['id']][$cid] ?? '';
        } elseif (isset($job[$s['id']][$cid])) {
            $data[] = $job[$s['id']][$cid];
        } elseif (isset($job_ada[$s['id']][$cid])) {
            $data[] = 'belum dinilai';
        } else {
            $data[] = '';
        }
    }
    if ($s['skor_pre'] !== null && $s['skor_post'] !== null) {
        $ng = hitung_ngain((int) $s['skor_pre'], (int) $s['skor_post']);
        $data = array_merge($data, [
            (int) $s['skor_pre'], (int) $s['skor_post'],
            round($ng['ngain'], 3), $ng['kategori'],
        ]);
    } else {
        $data = array_merge($data, [
            $s['skor_pre']  !== null ? (int) $s['skor_pre']  : '',
            $s['skor_post'] !== null ? (int) $s['skor_post'] : '',
            '', '',
        ]);
    }
    $sheet->fromArray($data, null, 'A' . $baris);
    $baris++;
}

$baris_akhir = $baris - 1;
$sheet->getStyle('A1:' . $kol_terakhir . $baris_akhir)->applyFromArray([
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D0D0D0']]],
]);
$sheet->getStyle('E2:' . $kol_terakhir . $baris_akhir)
      ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

foreach (range('A', $kol_terakhir) as $c) {
    $sheet->getColumnDimension($c)->setAutoSize(true);
}
$sheet->freezePane('C2');

$nama_file = 'Rekap_Nilai_AdaptLearn_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nama_file . '"');
header('Cache-Control: max-age=0');

(new Xlsx($ss))->save('php://output');
exit;