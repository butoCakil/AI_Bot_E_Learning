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

// ── Normalisasi nomor WA ─────────────────────────────────────────
function normalisasi_wa(string $nomor): string {
    $nomor = preg_replace('/[^0-9]/', '', $nomor); // hapus semua selain angka
    if (str_starts_with($nomor, '0')) {
        $nomor = '62' . substr($nomor, 1);
    }
    return $nomor;
}

// ── Buat akun siswa (oleh guru) ──────────────────────────────────
function buat_akun_siswa(string $nis, string $nama, string $kelas, string $nomor_wa, string $password): array {
    $pdo = db();

    $nomor_wa = $nomor_wa ? normalisasi_wa($nomor_wa) : '';
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
    $nomor_wa = $nomor_wa ? normalisasi_wa($nomor_wa) : '';
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
    $pdo->prepare("DELETE FROM post_test_results WHERE user_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM jobsheet_submissions WHERE user_id = ?")->execute([$id]);
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

// ── Ambil pengaturan ─────────────────────────────────────────────
function get_pengaturan(string $kunci, $default = null) {
    $pdo  = db();
    $stmt = $pdo->prepare("SELECT nilai FROM pengaturan WHERE kunci = ?");
    $stmt->execute([$kunci]);
    $row = $stmt->fetch();
    return $row ? $row['nilai'] : $default;
}

// ── Set pengaturan ───────────────────────────────────────────────
function set_pengaturan(string $kunci, $nilai): void {
    $pdo  = db();
    $stmt = $pdo->prepare("
        INSERT INTO pengaturan (kunci, nilai)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)
    ");
    $stmt->execute([$kunci, $nilai]);
}

// ── Cek apakah siswa boleh akses post-test ──────────────────────
function cek_akses_posttest(int $user_id): array {
    // 1. Cek guru sudah aktifkan
    $aktif = (int) get_pengaturan('posttest_aktif', 0);
    if (!$aktif) {
        return ['boleh' => false, 'alasan' => 'Guru belum membuka akses post-test.'];
    }

    // 2. Cek durasi waktu
    $tgl_mulai = get_pengaturan('posttest_mulai');
    $durasi    = (int) get_pengaturan('posttest_durasi_hari', 21);
    if ($tgl_mulai) {
        $selisih_hari = (int) floor((time() - strtotime($tgl_mulai)) / 86400);
        if ($selisih_hari < $durasi) {
            $sisa = $durasi - $selisih_hari;
            return ['boleh' => false, 'alasan' => "Post-test bisa diakses $sisa hari lagi (setelah $durasi hari pembelajaran)."];
        }
    }

    // 3. Cek progress materi siswa
    $min_persen = (int) get_pengaturan('min_materi_persen', 100);
    $profil     = get_profil_siswa($user_id);
    if (!$profil) {
        return ['boleh' => false, 'alasan' => 'Kamu belum mengerjakan pre-test.'];
    }

    $pdo    = db();
    $topik_list = array_keys(get_topik_list());
    $total_harus = 0;
    $total_dibuka = 0;

    foreach ($topik_list as $topik) {
        $stmt = $pdo->prepare("
            SELECT urutan_content FROM adaptation_rules
            WHERE profil_gabungan = ? AND topik = ?
        ");
        $stmt->execute([$profil['profil_gabungan'], $topik]);
        $row = $stmt->fetch();
        if ($row) {
            $ids = array_unique(json_decode($row['urutan_content'], true));
            $total_harus += count($ids);
        }

        $stmt2 = $pdo->prepare("
            SELECT COUNT(DISTINCT content_id) FROM activity_log
            WHERE user_id = ? AND tipe = 'buka_materi' AND topik = ?
        ");
        $stmt2->execute([$user_id, $topik]);
        $total_dibuka += (int) $stmt2->fetchColumn();
    }

    $persen_progress = $total_harus > 0
        ? round(($total_dibuka / $total_harus) * 100)
        : 0;

    if ($persen_progress < $min_persen) {
        return [
            'boleh'  => false,
            'alasan' => "Kamu baru menyelesaikan $persen_progress% materi. Selesaikan semua materi terlebih dahulu."
        ];
    }

    // 4. Cek sudah pernah post-test
    $stmt = $pdo->prepare("SELECT id FROM post_test_results WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    if ($stmt->fetch()) {
        return ['boleh' => false, 'alasan' => 'Kamu sudah mengerjakan post-test.', 'sudah_selesai' => true];
    }

    return ['boleh' => true, 'alasan' => ''];
}

// ── Cek prasyarat evaluasi (opsi A: konten sebelum evaluasi di urutan profil) ──
function cek_prasyarat_evaluasi(int $user_id, string $profil_gabungan, string $topik, int $content_id): array {
    $pdo  = db();
    $stmt = $pdo->prepare("SELECT urutan_content FROM adaptation_rules WHERE profil_gabungan = ? AND topik = ?");
    $stmt->execute([$profil_gabungan, $topik]);
    $urutan = array_values(array_unique(json_decode($stmt->fetchColumn() ?: '[]', true) ?? []));

    $pos = array_search($content_id, $urutan, true);
    // Tidak ada di jalur profil ini, atau posisi pertama → tidak ada prasyarat
    if ($pos === false || $pos === 0) {
        return ['boleh' => true, 'total' => 0, 'selesai' => 0, 'kurang' => []];
    }

    // Boleh mundur: konten yang sudah pernah diselesaikan selalu bisa dibuka ulang
    $stmt_self = $pdo->prepare("
        SELECT 1 FROM activity_log
        WHERE user_id = ? AND tipe = 'selesai_materi' AND content_id = ? LIMIT 1
    ");
    $stmt_self->execute([$user_id, $content_id]);
    if ($stmt_self->fetchColumn()) {
        return ['boleh' => true, 'total' => $pos, 'selesai' => $pos, 'kurang' => []];
    }

    $prasyarat = array_slice($urutan, 0, $pos);
    $ph    = implode(',', array_fill(0, count($prasyarat), '?'));
    $stmt2 = $pdo->prepare("
        SELECT DISTINCT content_id FROM activity_log
        WHERE user_id = ? AND tipe = 'selesai_materi' AND content_id IN ($ph)
    ");
    $stmt2->execute(array_merge([$user_id], $prasyarat));
    $selesai = array_map('intval', array_column($stmt2->fetchAll(), 'content_id'));

    $kurang = array_values(array_diff($prasyarat, $selesai));
    return [
        'boleh'   => empty($kurang),
        'total'   => count($prasyarat),
        'selesai' => count($prasyarat) - count($kurang),
        'kurang'  => $kurang,
    ];
}

// ── Hitung N-Gain ────────────────────────────────────────────────
function hitung_ngain(int $skor_pre, int $skor_post, int $skor_maks = 12): array {
    if ($skor_maks - $skor_pre === 0) {
        return ['ngain' => 1.0, 'kategori' => 'Tinggi', 'warna' => '#27ae60'];
    }
    $ngain = ($skor_post - $skor_pre) / ($skor_maks - $skor_pre);
    $ngain = round($ngain, 4);

    if ($ngain > 0.7)       { $kategori = 'Tinggi'; $warna = '#27ae60'; }
    elseif ($ngain >= 0.3)  { $kategori = 'Sedang'; $warna = '#e67e22'; }
    else                    { $kategori = 'Rendah'; $warna = '#e74c3c'; }

    return ['ngain' => $ngain, 'kategori' => $kategori, 'warna' => $warna];
}

// ── Topik Functions ──────────────────────────────────────

/**
 * Ambil semua topik aktif sebagai flat array [slug => nama]
 * Untuk kompatibilitas dengan kode lama yang pakai $topik_list
 */
function get_topik_list(): array {
    $pdo = db();
    $stmt = $pdo->query("SELECT slug, nama FROM topik WHERE aktif = 1 ORDER BY urutan, id");
    return array_column($stmt->fetchAll(), 'nama', 'slug');
}

/**
 * Ambil topik sebagai tree hierarki
 * Return: array of parent topik, masing-masing punya key 'children'
 */
function get_topik_tree(): array {
    $pdo = db();
    $all = $pdo->query("SELECT * FROM topik WHERE aktif = 1 ORDER BY urutan, id")->fetchAll();

    $tree    = [];
    $indexed = [];

    foreach ($all as $t) {
        $t['children'] = [];
        $indexed[$t['id']] = $t;
    }

    foreach ($indexed as $id => $t) {
        if ($t['parent_id'] === null) {
            $tree[] = &$indexed[$id];
        } else {
            $indexed[$t['parent_id']]['children'][] = &$indexed[$id];
        }
    }

    return $tree;
}

/**
 * Ambil hanya topik level atas (parent_id NULL)
 */
function get_topik_utama(): array {
    $pdo = db();
    $stmt = $pdo->query("SELECT * FROM topik WHERE parent_id IS NULL AND aktif = 1 ORDER BY urutan, id");
    return $stmt->fetchAll();
}

/**
 * Ambil sub-topik dari sebuah parent slug
 */
function get_sub_topik(string $parent_slug): array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT t.* FROM topik t
        JOIN topik p ON t.parent_id = p.id
        WHERE p.slug = ? AND t.aktif = 1
        ORDER BY t.urutan, t.id
    ");
    $stmt->execute([$parent_slug]);
    return $stmt->fetchAll();
}

/**
 * Ambil topik by slug
 */
function get_topik_by_slug(string $slug): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM topik WHERE slug = ? AND aktif = 1 LIMIT 1");
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

/**
 * Cek apakah slug adalah sub-topik
 */
function is_sub_topik(string $slug): bool {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT parent_id FROM topik WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row && $row['parent_id'] !== null;
}

/**
 * Ambil parent topik dari sebuah slug sub-topik
 */
function get_parent_topik(string $slug): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT p.* FROM topik t
        JOIN topik p ON t.parent_id = p.id
        WHERE t.slug = ? LIMIT 1
    ");
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

// ── Cabut content_id dari semua adaptation_rules ────────────────
// Dipanggil saat konten dihapus atau pindah topik, agar tidak ada ID yatim.
function cabut_konten_dari_rules(int $content_id, ?string $hanya_topik = null): int {
    $pdo = db();
    $sql = "SELECT id, topik, urutan_content, konten_wajib FROM adaptation_rules";
    $par = [];
    if ($hanya_topik !== null) {
        $sql .= " WHERE topik = ?";
        $par[] = $hanya_topik;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($par);

    $terubah = 0;
    foreach ($stmt->fetchAll() as $r) {
        $urutan = json_decode($r['urutan_content'], true) ?? [];
        $wajib  = json_decode($r['konten_wajib'], true) ?? [];

        $urutan_baru = array_values(array_diff($urutan, [$content_id]));
        $wajib_baru  = array_values(array_diff($wajib,  [$content_id]));

        if ($urutan_baru === $urutan && $wajib_baru === $wajib) continue;

        $up = $pdo->prepare("UPDATE adaptation_rules SET urutan_content = ?, konten_wajib = ? WHERE id = ?");
        $up->execute([
            json_encode($urutan_baru),
            json_encode($wajib_baru),
            $r['id'],
        ]);
        $terubah++;
    }
    return $terubah;
}

// ── Geser urutan_default agar tidak bertabrakan ─────────────────
// Dipakai saat menyisipkan konten pada posisi yang sudah terisi.
// Semua konten di topik itu dengan urutan >= $mulai digeser +1,
// kecuali $kecuali_id (konten yang sedang disimpan).
function geser_urutan_konten(string $topik, int $mulai, ?int $kecuali_id = null): int {
    $pdo = db();
    $sql = "UPDATE content SET urutan_default = urutan_default + 1
            WHERE topik = ? AND urutan_default >= ?";
    $par = [$topik, $mulai];
    if ($kecuali_id !== null) {
        $sql .= " AND id <> ?";
        $par[] = $kecuali_id;
    }
    $sql .= " ORDER BY urutan_default DESC";   // dari besar ke kecil agar tidak bentrok
    $stmt = $pdo->prepare($sql);
    $stmt->execute($par);
    return $stmt->rowCount();
}