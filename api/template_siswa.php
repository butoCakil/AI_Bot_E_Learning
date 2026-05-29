<?php
require_once '../config/config.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data Siswa');

// Header
$headers = ['NIS', 'Nama Lengkap', 'Kelas', 'Nomor WA'];
foreach ($headers as $i => $h) {
    $col = chr(65 + $i);
    $sheet->setCellValue($col . '1', $h);
}

// Style header
$sheet->getStyle('A1:D1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F3460']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
]);

// Lebar kolom
$sheet->getColumnDimension('A')->setWidth(20); // NIS
$sheet->getColumnDimension('B')->setWidth(35); // Nama
$sheet->getColumnDimension('C')->setWidth(15); // Kelas
$sheet->getColumnDimension('D')->setWidth(20); // WA

// Contoh baris (baris 2)
$sheet->setCellValue('A2', '1234567890');
$sheet->setCellValue('B2', 'Contoh Nama Siswa');
$sheet->setCellValue('C2', 'XI TE 1');
$sheet->setCellValue('D2', '628123456789');

// Style baris contoh (abu-abu muda)
$sheet->getStyle('A2:D2')->applyFromArray([
    'font' => ['italic' => true, 'color' => ['rgb' => '999999']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F5F5']],
]);

// Catatan di baris 3
$sheet->setCellValue('A3', '← Hapus baris contoh ini sebelum upload');
$sheet->getStyle('A3:D3')->applyFromArray([
    'font' => ['italic' => true, 'color' => ['rgb' => 'CC0000']],
]);
$sheet->mergeCells('A3:D3');

// Sheet kedua: daftar kelas valid
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Kelas Valid');
$sheet2->setCellValue('A1', 'Pilihan Kelas');
$sheet2->getStyle('A1')->getFont()->setBold(true);
$kelas_list = ['XI TE 1','XI TE 2','XI TE 3','XI TE 4','XII TE 1','XII TE 2','XII TE 3','XII TE 4'];
foreach ($kelas_list as $i => $k) {
    $sheet2->setCellValue('A' . ($i + 2), $k);
}

$spreadsheet->setActiveSheetIndex(0);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="template_import_siswa.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;