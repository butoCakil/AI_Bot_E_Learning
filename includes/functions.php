<?php
require_once dirname(__DIR__) . '/config/config.php';

// Hitung skor pengetahuan
function hitung_skor(array $jawaban, array $kunci): int {
    $skor = 0;
    foreach ($kunci as $i => $benar) {
        if (isset($jawaban[$i]) && strtoupper($jawaban[$i]) === $benar) {
            $skor++;
        }
    }
    return $skor;
}

// Simpan hasil pre-test ke database
function simpan_pretest(int $user_id, array $jwb_pengetahuan, array $jwb_sjt, int $skor, array $klasifikasi): int {
    $pdo = db();
    $stmt = $pdo->prepare("
        INSERT INTO pre_test_results 
        (user_id, jawaban_pengetahuan, jawaban_sjt, skor_pengetahuan, 
         level_kemampuan, profil_learning, profil_gabungan, probabilitas)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user_id,
        json_encode($jwb_pengetahuan),
        json_encode($jwb_sjt),
        $skor,
        $klasifikasi['level'],
        $klasifikasi['profil_learning'],
        $klasifikasi['profil_gabungan'],
        json_encode($klasifikasi['probabilitas'])
    ]);
    return (int) $pdo->lastInsertId();
}

// Simpan activity log
function log_aktivitas(int $user_id, string $tipe, ?int $content_id = null, ?string $topik = null, array $detail = []): void {
    $pdo = db();
    $stmt = $pdo->prepare("
        INSERT INTO activity_log (user_id, tipe, content_id, topik, detail)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user_id,
        $tipe,
        $content_id,
        $topik,
        json_encode($detail)
    ]);
}

// Ambil atau buat user berdasarkan nomor WA
function get_or_create_user(string $nomor_wa, string $nama = 'Siswa'): array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE nomor_wa = ?");
    $stmt->execute([$nomor_wa]);
    $user = $stmt->fetch();
    if ($user) return $user;

    $stmt = $pdo->prepare("INSERT INTO users (nama, nomor_wa, role) VALUES (?, ?, 'siswa')");
    $stmt->execute([$nama, $nomor_wa]);
    $id = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Ambil profil siswa terbaru
function get_profil_siswa(int $user_id): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT * FROM pre_test_results 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch() ?: null;
}

// Response JSON
function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
?>
