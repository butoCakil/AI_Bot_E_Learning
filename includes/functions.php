<?php
require_once dirname(__DIR__) . '/config/config.php';

// ── Hitung skor pengetahuan ──────────────────────────────────────
function hitung_skor(array $jawaban, array $kunci): int {
    $skor = 0;
    foreach ($kunci as $i => $benar) {
        if (isset($jawaban[$i]) && strtoupper($jawaban[$i]) === $benar) {
            $skor++;
        }
    }
    return $skor;
}

// ── Simpan hasil pre-test ────────────────────────────────────────
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

// ── Log aktivitas ────────────────────────────────────────────────
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

// ── Ambil profil siswa terbaru ───────────────────────────────────
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

// ── Response JSON ────────────────────────────────────────────────
function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// ── Panggil Python classifier ────────────────────────────────────
function classify_siswa(array $jawaban_sjt, int $skor): array {
    $input = escapeshellarg(json_encode([
        'sjt'  => $jawaban_sjt,
        'skor' => $skor,
    ]));
    $output = shell_exec(PYTHON_BIN . ' ' . CLASSIFY_SCRIPT . ' ' . $input . ' 2>&1');
    $result = json_decode($output, true);
    if (!$result || $result['status'] !== 'ok') {
        return ['status' => 'error', 'message' => $output];
    }
    return $result;
}

// ── LOGIN: cari user berdasarkan NIS + password ──────────────────
function login_siswa(string $nis, string $password): ?array {
    $pdo  = db();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE nis = ? AND role = 'siswa' LIMIT 1");
    $stmt->execute([trim($nis)]);
    $user = $stmt->fetch();
    if (!$user) return null;
    if (!password_verify($password, $user['password_hash'])) return null;
    return $user;
}

// ── Buat akun siswa (oleh guru) ──────────────────────────────────
function buat_akun_siswa(string $nis, string $nama, string $kelas, string $nomor_wa, string $password): array {
    $pdo = db();

    // Cek NIS sudah ada
    $stmt = $pdo->prepare("SELECT id FROM users WHERE nis = ?");
    $stmt->execute([$nis]);
    if ($stmt->fetch()) {
        return ['status' => 'error', 'message' => "NIS $nis sudah terdaftar."];
    }

    // Cek nomor WA sudah ada
    $stmt = $pdo->prepare("SELECT id FROM users WHERE nomor_wa = ?");
    $stmt->execute([$nomor_wa]);
    if ($stmt->fetch()) {
        return ['status' => 'error', 'message' => "Nomor WA $nomor_wa sudah terdaftar."];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO users (nis, nama, kelas, nomor_wa, role, password_hash)
        VALUES (?, ?, ?, ?, 'siswa', ?)
    ");
    $stmt->execute([$nis, $nama, $kelas, $nomor_wa, $hash]);

    return ['status' => 'ok', 'id' => (int) $pdo->lastInsertId()];
}

// ── Update akun siswa ────────────────────────────────────────────
function update_akun_siswa(int $id, string $nama, string $kelas, string $nomor_wa, ?string $password = null): array {
    $pdo = db();

    // Cek nomor WA tidak dipakai user lain
    $stmt = $pdo->prepare("SELECT id FROM users WHERE nomor_wa = ? AND id != ?");
    $stmt->execute([$nomor_wa, $id]);
    if ($stmt->fetch()) {
        return ['status' => 'error', 'message' => "Nomor WA sudah dipakai siswa lain."];
    }

    if ($password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET nama=?, kelas=?, nomor_wa=?, password_hash=? WHERE id=?");
        $stmt->execute([$nama, $kelas, $nomor_wa, $hash, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET nama=?, kelas=?, nomor_wa=? WHERE id=?");
        $stmt->execute([$nama, $kelas, $nomor_wa, $id]);
    }

    return ['status' => 'ok'];
}

// ── Hapus akun siswa ─────────────────────────────────────────────
function hapus_akun_siswa(int $id): void {
    $pdo = db();
    $pdo->prepare("DELETE FROM activity_log WHERE user_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM pre_test_results WHERE user_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM wa_sessions WHERE user_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
}

// ── Guard: wajib login siswa ─────────────────────────────────────
function require_login(): void {
    if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
        header('Location: /login.php');
        exit;
    }
}

// ── Set session setelah login ────────────────────────────────────
function set_session_siswa(array $user): void {
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['nama']     = $user['nama'];
    $_SESSION['nis']      = $user['nis'];
    $_SESSION['kelas']    = $user['kelas'];
    $_SESSION['nomor_wa'] = $user['nomor_wa'];
    $_SESSION['role']     = 'siswa';
}
?>