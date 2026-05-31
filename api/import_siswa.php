<?php
require_once '../config/config.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

session_start();
if (empty($_SESSION['guru_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'pesan' => 'Akses ditolak.']);
    exit;
}

$kelas_valid = ['XI TE 1','XI TE 2','XI TE 3','XI TE 4','XII TE 1','XII TE 2','XII TE 3','XII TE 4'];
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';

// ── MODE: parse (upload file, kembalikan semua baris sebagai JSON) ──
if (strpos($content_type, 'multipart') !== false && ($_POST['aksi'] ?? '') === 'parse') {
    header('Content-Type: application/json');

    if (empty($_FILES['file_excel'])) {
        echo json_encode(['status' => 'error', 'pesan' => 'File tidak ditemukan.']);
        exit;
    }

    $file = $_FILES['file_excel'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls'])) {
        echo json_encode(['status' => 'error', 'pesan' => 'File harus .xlsx atau .xls']);
        exit;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['status' => 'error', 'pesan' => 'Ukuran file maksimal 5MB.']);
        exit;
    }

    try {
        $spreadsheet = IOFactory::load($file['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $raw   = $sheet->toArray(null, true, true, true);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'pesan' => 'Gagal membaca file: ' . $e->getMessage()]);
        exit;
    }

    $rows = [];
    foreach ($raw as $no => $row) {
        if ($no === 1) continue; // skip header
        $nis  = trim($row['A'] ?? '');
        $nama = trim($row['B'] ?? '');
        if ($nis === '' && $nama === '') continue; // skip baris kosong
        $rows[] = [
            'no'       => $no,
            'nis'      => $nis,
            'nama'     => $nama,
            'kelas'    => trim($row['C'] ?? ''),
            'nomor_wa' => (function($n) {
                $n = preg_replace('/[^0-9]/', '', $n);
                if (str_starts_with($n, '0')) $n = '62' . substr($n, 1);
                return $n ?: null;
            })(trim($row['D'] ?? '')),
        ];
    }

    echo json_encode(['status' => 'ok', 'rows' => $rows]);
    exit;
}

// ── MODE: batch (terima JSON array baris, insert ke DB) ──
if (strpos($content_type, 'application/json') !== false) {
    header('Content-Type: application/json');

    $body = json_decode(file_get_contents('php://input'), true);
    if (($_body['aksi'] ?? $body['aksi'] ?? '') !== 'batch' || empty($body['rows'])) {
        echo json_encode(['status' => 'error', 'pesan' => 'Request tidak valid.']);
        exit;
    }

    $pdo      = db();
    $berhasil = 0;
    $gagal    = [];

    // Ambil akumulasi dari session (untuk total akhir)
    if (empty($_SESSION['import_total_berhasil'])) $_SESSION['import_total_berhasil'] = 0;
    if (empty($_SESSION['import_total_gagal']))    $_SESSION['import_total_gagal']    = [];

    foreach ($body['rows'] as $row) {
        $no       = $row['no'];
        $nis      = trim($row['nis'] ?? '');
        $nama     = trim($row['nama'] ?? '');
        $kelas    = trim($row['kelas'] ?? '');
        $nomor_wa = $row['nomor_wa'] ? (function($n) {
            $n = preg_replace('/[^0-9]/', '', $n);
            if (str_starts_with($n, '0')) $n = '62' . substr($n, 1);
            return $n ?: null;
        })($row['nomor_wa']) : null;

        if ($nis === '') {
            $gagal[] = "Baris $no: NIS kosong.";
            continue;
        }
        if ($nama === '') {
            $gagal[] = "Baris $no (NIS: $nis): Nama kosong.";
            continue;
        }
        if (!in_array($kelas, $kelas_valid)) {
            $gagal[] = "Baris $no (NIS: $nis): Kelas '$kelas' tidak valid.";
            continue;
        }

        // Generate password
        $nama_bersih    = preg_replace('/[^a-zA-Z]/', '', $nama);
        $nama_prefix    = strtolower(substr($nama_bersih, 0, 4));
        $password_hash  = password_hash($nis . $nama_prefix, PASSWORD_DEFAULT);

        // Cek duplikat NIS
        $cek = $pdo->prepare("SELECT id FROM users WHERE nis = ?");
        $cek->execute([$nis]);
        if ($cek->fetch()) {
            $gagal[] = "Baris $no (NIS: $nis): NIS sudah terdaftar.";
            continue;
        }

        // Cek duplikat WA
        if ($nomor_wa !== null) {
            $cek_wa = $pdo->prepare("SELECT id FROM users WHERE nomor_wa = ?");
            $cek_wa->execute([$nomor_wa]);
            if ($cek_wa->fetch()) {
                $gagal[]  = "Baris $no (NIS: $nis): Nomor WA duplikat, WA dikosongi.";
                $nomor_wa = null;
            }
        }

        try {
            $pdo->prepare("
                INSERT INTO users (nis, nama, kelas, nomor_wa, role, password_hash)
                VALUES (?, ?, ?, ?, 'siswa', ?)
            ")->execute([$nis, $nama, $kelas, $nomor_wa, $password_hash]);
            $berhasil++;
        } catch (Exception $e) {
            $gagal[] = "Baris $no (NIS: $nis): Gagal — " . $e->getMessage();
        }
    }

    // Akumulasi ke session
    $_SESSION['import_total_berhasil'] += $berhasil;
    $_SESSION['import_total_gagal']     = array_merge($_SESSION['import_total_gagal'], $gagal);

    // Cek apakah ini batch terakhir (ditandai client dengan is_last)
    $is_last = !empty($body['is_last']);
    $response = [
        'status'         => 'ok',
        'berhasil'       => $berhasil,
        'total_berhasil' => $_SESSION['import_total_berhasil'],
        'total_gagal'    => count($_SESSION['import_total_gagal']),
        'detail_gagal'   => $is_last ? $_SESSION['import_total_gagal'] : [],
    ];

    if ($is_last) {
        unset($_SESSION['import_total_berhasil'], $_SESSION['import_total_gagal']);
    }

    echo json_encode($response);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['status' => 'error', 'pesan' => 'Method tidak dikenali.']);
