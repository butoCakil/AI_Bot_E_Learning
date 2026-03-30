<?php
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/config/soal_pretest.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['status' => 'error', 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

// Validasi input
if (empty($input['user_id']) || empty($input['jawaban_pengetahuan']) || empty($input['jawaban_sjt'])) {
    json_response(['status' => 'error', 'message' => 'Data tidak lengkap'], 400);
}

$user_id           = (int) $input['user_id'];
$jawaban_pngt      = $input['jawaban_pengetahuan']; // array 12 jawaban
$jawaban_sjt       = $input['jawaban_sjt'];          // array 8 jawaban

// Validasi jumlah jawaban
if (count($jawaban_pngt) !== 12 || count($jawaban_sjt) !== 8) {
    json_response(['status' => 'error', 'message' => 'Jumlah jawaban tidak sesuai'], 400);
}

// Hitung skor pengetahuan
$skor = hitung_skor($jawaban_pngt, KUNCI_JAWABAN);

// Klasifikasi via Naive Bayes
$klasifikasi = classify_siswa($jawaban_sjt, $skor);

if ($klasifikasi['status'] !== 'ok') {
    json_response(['status' => 'error', 'message' => 'Klasifikasi gagal: ' . $klasifikasi['message']], 500);
}

// Simpan ke database
$pretest_id = simpan_pretest($user_id, $jawaban_pngt, $jawaban_sjt, $skor, $klasifikasi);

// Log aktivitas
log_aktivitas($user_id, 'pretest', null, null, [
    'pretest_id'     => $pretest_id,
    'skor'           => $skor,
    'profil_gabungan' => $klasifikasi['profil_gabungan']
]);

// Response lengkap
json_response([
    'status'          => 'ok',
    'pretest_id'      => $pretest_id,
    'skor'            => $skor,
    'level'           => $klasifikasi['level'],
    'profil_learning' => $klasifikasi['profil_learning'],
    'profil_gabungan' => $klasifikasi['profil_gabungan'],
    'probabilitas'    => $klasifikasi['probabilitas'],
    'pesan'           => 'Pre-test selesai. Profil belajar kamu: ' . strtoupper($klasifikasi['profil_gabungan'])
]);
?>
