<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// ── Validasi token ────────────────────────────────────────────
// Endpoint ini terbuka di internet, jadi wajib diproteksi. Token disimpan di
// tabel `pengaturan` dengan kunci 'wa_webhook_token' dan disertakan sebagai
// ?token=... pada URL oleh elearning-bridge.
$expected_token = get_pengaturan('wa_webhook_token', '');
$given_token    = $_GET['token'] ?? '';
if ($expected_token === '' || !hash_equals($expected_token, $given_token)) {
    http_response_code(401);
    exit('unauthorized');
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

// ── Deteksi gateway & normalisasi payload ─────────────────────
$source = strtoupper($data['source'] ?? '');
$is_whacenter = ($source === 'WHACENTER') || isset($data['from']);

if ($is_whacenter) {
    // Format Whacenter
    $nomor_raw     = preg_replace('/[^0-9]/', '', $data['from'] ?? '');
    $nomor         = preg_match('/^0/', $nomor_raw) ? '62' . substr($nomor_raw, 1) : $nomor_raw;
    $device_number = preg_replace('/[^0-9]/', '', $data['to']   ?? '');
    $pesan         = trim($data['message'] ?? '');
    $nama          = $data['pushName'] ?? 'Siswa';
    $is_group      = !empty($data['is_group']);
    $type          = $data['message_type'] ?? 'text';
} else {
    // Format Fonnte
    $nomor         = preg_replace('/[^0-9]/', '', $data['sender'] ?? '');
    $device_number = preg_replace('/[^0-9]/', '', $data['device'] ?? '');
    $pesan         = trim($data['pesan'] ?? $data['message'] ?? '');
    $nama          = $data['name'] ?? $data['pushname'] ?? 'Siswa';
    $is_group      = !empty($data['isgroup']);
    $type          = $data['type'] ?? 'text';
}

// Abaikan: pesan kosong, dari grup, dari bot sendiri, bukan text
if (empty($nomor) || empty($pesan) || $is_group || $nomor === $device_number || $type !== 'text') {
    http_response_code(200);
    echo 'ok';
    exit;
}

$pdo = db();

// ── Fungsi kirim WA (dual gateway) ────────────────────────────
function kirim_wa(string $nomor, string $pesan): void {
    $gateway = get_pengaturan('wa_gateway', 'fonnte');

    // ── WA Gateway sendiri (via elearning-bridge di X200MA lewat Tailscale) ──
    if ($gateway === 'wagateway') {
        $url   = get_pengaturan('wa_gateway_send_url', '');
        $token = get_pengaturan('wa_gateway_send_token', '');
        if ($url === '' || $token === '') {
            error_log('[kirim_wa] wa_gateway_send_url/token belum diset');
            return;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['target' => $nomor, 'message' => $pesan]),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $resp = curl_exec($ch);
        if ($resp === false) error_log('[kirim_wa] gateway CURL: ' . curl_error($ch));
        curl_close($ch);
        return;
    }

    if ($gateway === 'whacenter') {
        $ch = curl_init(WHACENTER_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'device_id' => WHACENTER_DEVICE_ID,
                'number'    => $nomor,
                'message'   => $pesan,
            ],
        ]);
    } else {
        // Default: Fonnte
        $ch = curl_init(FONNTE_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Authorization: ' . FONNTE_TOKEN],
            CURLOPT_POSTFIELDS     => ['target' => $nomor, 'message' => $pesan],
        ]);
    }
    curl_exec($ch);
    curl_close($ch);
}

// ── Fungsi get/set/hapus session ───────────────────────────────
function get_wa_session(PDO $pdo, string $nomor): ?array {
    $stmt = $pdo->prepare("SELECT * FROM wa_sessions WHERE nomor_wa = ?");
    $stmt->execute([$nomor]);
    return $stmt->fetch() ?: null;
}

function set_wa_session(PDO $pdo, string $nomor, string $state, array $context = [], ?int $user_id = null): void {
    $ctx  = json_encode($context);
    $stmt = $pdo->prepare("
        INSERT INTO wa_sessions (nomor_wa, user_id, state, context)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            state    = VALUES(state),
            context  = VALUES(context),
            user_id  = COALESCE(VALUES(user_id), user_id),
            updated_at = NOW()
    ");
    $stmt->execute([$nomor, $user_id, $state, $ctx]);
}

function hapus_wa_session(PDO $pdo, string $nomor): void {
    $pdo->prepare("DELETE FROM wa_sessions WHERE nomor_wa = ?")->execute([$nomor]);
}

// ── Fungsi tanya Gemini ────────────────────────────────────────
function tanya_ai(string $pertanyaan, array $konteks, PDO $pdo): string {
    $provider = get_pengaturan('wa_ai_provider', 'groq');
    $model    = get_pengaturan('wa_ai_model',    'llama-3.1-8b-instant');
    $custom   = get_pengaturan('wa_ai_prompt',   '');

    // Prompt dasar dari pengaturan (bisa diedit guru), ATAU bawaan bila kosong.
    $dasar = $custom ?: (
        "Kamu adalah asisten belajar AdaptLearn PRE untuk mata pelajaran Penerapan Rangkaian Elektronika (PRE) di SMK. " .
        "Jawab dengan bahasa formal tapi santai, mudah dipahami siswa SMK. " .
        "Fokus pada materi teknik elektronika. " .
        "Jawaban maksimal 3 paragraf pendek. Gunakan emoji secukupnya."
    );

    // Konteks siswa SELALU disertakan, terlepas dari prompt mana yang dipakai.
    // Sebelumnya konteks ini hilang saat pengaturan wa_ai_prompt terisi, sehingga
    // jawaban tidak lagi menyesuaikan profil & level siswa.
    $system = $dasar . "\n\nDATA SISWA YANG SEDANG BERTANYA:\n"
        . "- Nama: "  . ($konteks['nama']   ?? '-') . "\n"
        . "- Kelas: " . ($konteks['kelas']  ?? '-') . "\n"
        . "- Profil belajar: " . ($konteks['profil'] ?? '-') . "\n"
        . "- Level kemampuan: " . ($konteks['level'] ?? '-') . "\n"
        . "Sapa siswa dengan namanya, dan sesuaikan kedalaman penjelasan dengan levelnya.";

    // Materi kelas sebagai acuan utama, bila tersedia.
    if (!empty($konteks['materi'])) {
        $system .= "\n\n=== MATERI KELAS UNTUK TOPIK: " . ($konteks['topik'] ?? '-') . " ===\n"
            . $konteks['materi']
            . "\n=== AKHIR MATERI ===\n"
            . "Jadikan materi di atas acuan utama. Boleh menambah wawasan di luar materi, "
            . "tapi tandai bahwa itu tambahan.";
    }

    if ($provider === 'groq') {
        $payload = json_encode([
            'model'       => $model,
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $pertanyaan],
            ],
            'max_tokens'  => 900,
            'temperature' => 0.7,
        ]);
        $ch = curl_init(GROQ_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . GROQ_API_KEY,
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT    => 15,
        ]);
        $resp   = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($resp, true);
        return $result['choices'][0]['message']['content']
               ?? 'Maaf, asisten AI sedang tidak tersedia. Coba lagi ya! 🙏';

    } elseif ($provider === 'gemini') {
        $payload = json_encode([
            'contents' => [['parts' => [['text' => $system . "\n\nPertanyaan: " . $pertanyaan]]]]
        ]);
        $ch = curl_init(GEMINI_API_URL . '?key=' . GEMINI_API_KEY);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $resp   = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($resp, true);
        return $result['candidates'][0]['content']['parts'][0]['text']
               ?? 'Maaf, asisten AI sedang tidak tersedia. Coba lagi ya! 🙏';
    }

    return 'Maaf, provider AI tidak dikenali. Hubungi gurumu ya! 🙏';
}

// ── Label profil & level ───────────────────────────────────────
$label_profil = [
    'guided'      => 'Guided-Step Learner',
    'conceptual'  => 'Conceptual Learner',
    'practice'    => 'Practice-Oriented Learner',
];
$label_level = [
    'beginner'     => 'Pemula',
    'intermediate' => 'Menengah',
    'advanced'     => 'Mahir',
];

// ── Fungsi tampilkan menu ──────────────────────────────────────
function tampilkan_menu(array $user, string $nomor, array $lp, array $ll): void {
    $sudah = !empty($user['profil_learning']);
    $nama  = $user['nama'];
    $msg   = "Halo *{$nama}*! 👋 Selamat datang di *AdaptLearn PRE*.\n\n";
    $msg  .= "📚 *MENU UTAMA*\n";
    $msg  .= "━━━━━━━━━━━━━━━━━━━━\n";
    if ($sudah) {
        $profil = $lp[$user['profil_learning']] ?? $user['profil_learning'];
        $level  = $ll[$user['level_kemampuan']] ?? $user['level_kemampuan'];
        $msg   .= "👤 Profil: *{$profil}*\n";
        $msg   .= "📊 Level: *{$level}*\n\n";
        $msg   .= "1️⃣ Lanjut Belajar\n";
        $msg   .= "2️⃣ Cek Progress\n";
        $msg   .= "3️⃣ Tanya Materi (AI)\n";
        $msg   .= "4️⃣ Ulangi Pre-Test\n";
    } else {
        $msg .= "⚠️ Kamu belum mengerjakan pre-test.\n\n";
        $msg .= "1️⃣ Mulai Pre-Test\n";
        $msg .= "3️⃣ Tanya Materi (AI)\n";
    }
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "Balas dengan angka pilihanmu ya! 😊";
    kirim_wa($nomor, $msg);
}

// ── Fungsi kirim soal pre-test ─────────────────────────────────
function kirim_soal_pretest(PDO $pdo, string $nomor, int $index): void {
    require_once dirname(__DIR__) . '/config/soal_pretest.php';

    $soal_pengetahuan = defined('SOAL_PENGETAHUAN') ? SOAL_PENGETAHUAN : [];
    $soal_sjt         = defined('SOAL_SJT')         ? SOAL_SJT         : [];
    $semua_soal       = array_merge(
        array_map(fn($s) => array_merge($s, ['bagian' => 'pengetahuan']), $soal_pengetahuan),
        array_map(fn($s) => array_merge($s, ['bagian' => 'sjt']),         $soal_sjt)
    );

    if (!isset($semua_soal[$index])) {
        kirim_wa($nomor, "Soal ke-" . ($index + 1) . " tidak ditemukan. Coba kerjakan di web ya!\n" . APP_URL . "/pretest.php");
        return;
    }

    $soal = $semua_soal[$index];
    $no   = $index + 1;
    $bag  = $soal['bagian'] === 'pengetahuan' ? 'A — Pengetahuan' : 'B — Skenario Belajar';
    $msg  = "📝 *Soal {$no}/20* (Bagian {$bag})\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    $msg .= ($soal['soal'] ?? '(soal tidak tersedia)') . "\n\n";
    $msg .= "🅐 " . $soal['opsi']['A'] . "\n";
    $msg .= "🅑 " . $soal['opsi']['B'] . "\n";
    $msg .= "🅒 " . $soal['opsi']['C'] . "\n";
    $msg .= "🅓 " . $soal['opsi']['D'] . "\n\n";
    $msg .= "_Balas A, B, C, atau D_ 👆\n_Ketik *batal* untuk keluar (jawaban tidak disimpan)_";
    kirim_wa($nomor, $msg);
}

// ══════════════════════════════════════════════════════════════
// FORMAT REGISTRASI: "daftar NIS PASSWORD"
// ══════════════════════════════════════════════════════════════
if (preg_match('/^daftar\s+(\S+)\s+(\S+)$/i', $pesan, $m)) {
    $nis_input  = trim($m[1]);
    $pass_input = trim($m[2]);

    $cek = $pdo->prepare("SELECT * FROM users WHERE nis = ? AND role = 'siswa'");
    $cek->execute([$nis_input]);
    $calon = $cek->fetch();

    if (!$calon) {
        kirim_wa($nomor, "NIS *{$nis_input}* tidak ditemukan. Hubungi gurumu ya! 🙏");
        exit;
    }
    if (!password_verify($pass_input, $calon['password_hash'])) {
        kirim_wa($nomor, "Password salah. Coba lagi:\n\n*daftar NIS PASSWORD*");
        exit;
    }
    if ($calon['nomor_wa'] && $calon['nomor_wa'] !== $nomor) {
        kirim_wa($nomor, "Akun ini sudah terhubung ke nomor WA lain. Hubungi gurumu jika ada masalah. 🙏");
        exit;
    }

    $pdo->prepare("UPDATE users SET nomor_wa = ? WHERE id = ?")->execute([$nomor, $calon['id']]);
    kirim_wa($nomor, "Berhasil! Halo *{$calon['nama']}*! 👋\nNomor WA kamu sudah terdaftar.\n\nKetik *menu* untuk mulai belajar!");
    set_wa_session($pdo, $nomor, 'menu', [], $calon['id']);
    exit;
}

// ══════════════════════════════════════════════════════════════
// CEK USER TERDAFTAR
// ══════════════════════════════════════════════════════════════
$stmt = $pdo->prepare("
    SELECT u.*, p.profil_learning, p.level_kemampuan, p.profil_gabungan, p.skor_pengetahuan
    FROM users u
    LEFT JOIN pre_test_results p
           ON p.id = (
               SELECT p2.id FROM pre_test_results p2
               WHERE p2.user_id = u.id
               ORDER BY p2.id DESC LIMIT 1
           )
    WHERE u.nomor_wa = ? AND u.role = 'siswa'
");
$stmt->execute([$nomor]);
$user = $stmt->fetch();

// Nomor tidak terdaftar & bukan format daftar → ABAIKAN
if (!$user) {
    http_response_code(200);
    echo 'ok';
    exit;
}

// ── Ambil sesi ────────────────────────────────────────────────
$sesi    = get_wa_session($pdo, $nomor);
$state   = $sesi['state'] ?? 'menu';
$context = json_decode($sesi['context'] ?? '{}', true) ?: [];

// ── Keyword global ─────────────────────────────────────────────
$pesan_lower = strtolower($pesan);
if (in_array($pesan_lower, ['menu', 'start', 'halo', 'hallo', 'hai', 'hi', 'hello', 'mulai'])) {
    tampilkan_menu($user, $nomor, $label_profil, $label_level);
    set_wa_session($pdo, $nomor, 'menu', [], $user['id']);
    exit;
}
if (in_array($pesan_lower, ['batal', 'cancel', 'keluar'])) {
    if ($state === 'pretest') {
        kirim_wa($nomor, "⚠️ *Yakin ingin keluar dari Pre-Test?*\n\nJika keluar, jawaban yang sudah kamu isi *tidak akan disimpan* dan pre-test harus diulang dari awal.\n\nKetik *ya* untuk keluar, atau *lanjut* untuk melanjutkan soal.");
        set_wa_session($pdo, $nomor, 'konfirmasi_keluar_pretest', $context, $user['id']);
    } else {
        kirim_wa($nomor, "Oke, kembali ke menu utama ya! 😊");
        tampilkan_menu($user, $nomor, $label_profil, $label_level);
        set_wa_session($pdo, $nomor, 'menu', [], $user['id']);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════
// STATE MACHINE
// ══════════════════════════════════════════════════════════════
switch ($state) {

    case 'menu':
        $sudah_pretest = !empty($user['profil_learning']);

        if ($pesan === '1') {
            if (!$sudah_pretest) {
                set_wa_session($pdo, $nomor, 'pretest', ['soal_index' => 0, 'jawaban' => []], $user['id']);
                kirim_wa($nomor, "📝 *PRE-TEST AdaptLearn PRE*\n\nPre-test terdiri dari 20 soal.\nKetik *batal* kapan saja untuk keluar.\n\nSoal pertama: 🚀");
                kirim_soal_pretest($pdo, $nomor, 0);
            } else {
                $last = $pdo->prepare("
                    SELECT a.content_id, a.topik, c.judul
                    FROM activity_log a
                    JOIN content c ON c.id = a.content_id
                    WHERE a.user_id = ? AND a.tipe = 'buka_materi'
                    ORDER BY a.id DESC LIMIT 1
                ");
                $last->execute([$user['id']]);
                $lk = $last->fetch();
                if ($lk) {
                    $url = APP_URL . "/materi.php?topik={$lk['topik']}&konten={$lk['content_id']}";
                    kirim_wa($nomor, "▶️ *Lanjut Belajar*\n\nMateri terakhir:\n📖 *{$lk['judul']}*\n\n🔗 {$url}\n\n_Buka link di atas untuk melanjutkan belajar, atau ketik:_\n*menu* — kembali ke menu utama\n*3* — tanya materi ke AI 🤖");
                } else {
                    kirim_wa($nomor, "▶️ Yuk mulai belajar!\n\n🔗 " . APP_URL . "/materi.php");
                }
                set_wa_session($pdo, $nomor, 'menu', [], $user['id']);
            }

        } elseif ($pesan === '2' && $sudah_pretest) {
            $topik_list = get_topik_list();
            $msg = "📊 *Progress Belajarmu*\n━━━━━━━━━━━━━━━━━━━━\n";
            foreach ($topik_list as $slug => $nama_topik) {
                // Lewati topik parent yang punya sub-topik (tidak punya konten sendiri)
                if (!empty(get_sub_topik($slug))) continue;

                $t = $pdo->prepare("SELECT urutan_content FROM adaptation_rules WHERE profil_gabungan = ? AND topik = ?");
                $t->execute([$user['profil_gabungan'], $slug]);
                $r = $t->fetch();
                $total = $r ? count(array_unique(json_decode($r['urutan_content'], true))) : 0;

                $b = $pdo->prepare("SELECT COUNT(DISTINCT content_id) FROM activity_log WHERE user_id = ? AND topik = ? AND tipe = 'selesai_materi'");
                $b->execute([$user['id'], $slug]);
                $dibuka = (int)$b->fetchColumn();

                $pct = $total > 0 ? min(100, (int)round(($dibuka / $total) * 100)) : 0;
                $bar = str_repeat('█', (int)($pct / 10)) . str_repeat('░', 10 - (int)($pct / 10));
                $msg .= "\n📚 *{$nama_topik}*\n{$bar} {$pct}%\n";
            }
            $msg .= "\n━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "📝 Skor Pre-Test: *{$user['skor_pengetahuan']}/12*\n";
            $post = $pdo->prepare("SELECT skor_pengetahuan FROM post_test_results WHERE user_id = ? ORDER BY id DESC LIMIT 1");
            $post->execute([$user['id']]);
            $sp = $post->fetchColumn();
            if ($sp !== false) $msg .= "🎯 Skor Post-Test: *{$sp}/12*\n";
            $msg .= "\nKetik *menu* untuk kembali 😊";
            kirim_wa($nomor, $msg);
            set_wa_session($pdo, $nomor, 'menu', [], $user['id']);

        } elseif ($pesan === '3') {
            kirim_wa($nomor, "🤖 *Tanya Materi (AI)*\n\nSilakan ketik pertanyaanmu tentang materi PRE.\nKetik *batal* untuk kembali. 😊");
            set_wa_session($pdo, $nomor, 'tanya_ai', [], $user['id']);

        } elseif ($pesan === '4') {
            set_wa_session($pdo, $nomor, 'pretest', ['soal_index' => 0, 'jawaban' => []], $user['id']);
            kirim_wa($nomor, "📝 *PRE-TEST* — Soal pertama: 🚀");
            kirim_soal_pretest($pdo, $nomor, 0);

        } else {
            kirim_wa($nomor, "Hmm, pilihan tidak dikenali 😅\nBalas dengan angka *1*, *2*, *3*, atau *4* ya!");
        }
        break;

    case 'tanya_ai':
        kirim_wa($nomor, "⏳ Sedang diproses AI, tunggu sebentar ya...");
        $konteks = [
            'nama'   => $user['nama'],
            'kelas'  => $user['kelas'] ?? '-',
            'profil' => $label_profil[$user['profil_learning']] ?? 'Umum',
            'level'  => $label_level[$user['level_kemampuan']] ?? 'Umum',
        ];

        // Sertakan materi topik yang sedang dipelajari siswa, supaya jawaban AI
        // berpijak pada bahan ajar kelas — bukan hanya pengetahuan umum model.
        // Tipe 'evaluasi' dan 'tantangan' SENGAJA dikecualikan karena berisi soal;
        // kalau ikut dikirim, AI bisa membocorkan jawabannya.
        $tq = $pdo->prepare("
            SELECT a.topik FROM activity_log a
            WHERE a.user_id = ? AND a.tipe = 'buka_materi' AND a.topik IS NOT NULL
            ORDER BY a.id DESC LIMIT 1
        ");
        $tq->execute([$user['id']]);
        $topik_aktif = $tq->fetchColumn();

        if ($topik_aktif) {
            $mq = $pdo->prepare("
                SELECT judul, tipe, isi FROM content
                WHERE topik = ? AND aktif = 1 AND tipe IN ('teori','langkah','jobsheet')
                ORDER BY urutan_default ASC, id ASC
            ");
            $mq->execute([$topik_aktif]);
            $bagian = [];
            $total  = 0;
            foreach ($mq->fetchAll() as $m) {
                $teks = trim(strip_tags($m['isi'] ?? ''));
                if ($teks === '') continue;
                // Batasi total agar prompt tidak membengkak (materi Anda ~1-2 rb char/bagian)
                if ($total + strlen($teks) > 12000) break;
                $bagian[] = "### [{$m['tipe']}] {$m['judul']}\n{$teks}";
                $total += strlen($teks);
            }
            if ($bagian) {
                $konteks['topik']  = $topik_aktif;
                $konteks['materi'] = implode("\n\n", $bagian);
            }
        }

        $jawaban = tanya_ai($pesan, $konteks, $pdo);
        kirim_wa($nomor, "🤖 *Jawaban AI:*\n\n{$jawaban}\n\n_⚠️ Jawaban AI bisa keliru — verifikasi dengan guru atau buku._\n_Punya pertanyaan lain? Langsung ketik! Atau ketik *batal* untuk kembali._ 😊");
        break;

    case 'pretest':
        $soal_index = (int)($context['soal_index'] ?? 0);
        $jawaban    = $context['jawaban'] ?? [];
        $jwb_upper  = strtoupper(trim($pesan));

        if (!in_array($jwb_upper, ['A', 'B', 'C', 'D'])) {
            kirim_wa($nomor, "Jawab dengan huruf *A*, *B*, *C*, atau *D* ya! 😊");
            break;
        }

        $jawaban[] = $jwb_upper;
        $soal_index++;

        if ($soal_index >= 20) {
            $jawaban_json = json_encode($jawaban);
            $cmd = PYTHON_BIN . ' ' . CLASSIFY_SCRIPT . ' ' . escapeshellarg($user['id']) . ' ' . escapeshellarg($jawaban_json);
            shell_exec($cmd . ' > /dev/null 2>&1 &');
            kirim_wa($nomor, "🎉 *Pre-Test Selesai!*\n\nJawaban kamu sudah diterima! 🙌\nHasil profil belajarmu sedang diproses...\n\nTunggu beberapa detik, lalu ketik *menu*. 😊");
            hapus_wa_session($pdo, $nomor);
        } else {
            set_wa_session($pdo, $nomor, 'pretest', ['soal_index' => $soal_index, 'jawaban' => $jawaban], $user['id']);
            kirim_soal_pretest($pdo, $nomor, $soal_index);
        }
        break;

    case 'konfirmasi_keluar_pretest':
        if ($pesan_lower === 'ya') {
            kirim_wa($nomor, "Oke, pre-test dibatalkan. Kembali ke menu ya! 😊");
            tampilkan_menu($user, $nomor, $label_profil, $label_level);
            set_wa_session($pdo, $nomor, 'menu', [], $user['id']);
        } elseif ($pesan_lower === 'lanjut') {
            $soal_index = (int)($context['soal_index'] ?? 0);
            kirim_wa($nomor, "Oke, lanjut! Ini soal yang tadi: 💪");
            kirim_soal_pretest($pdo, $nomor, $soal_index);
            set_wa_session($pdo, $nomor, 'pretest', $context, $user['id']);
        } else {
            kirim_wa($nomor, "Ketik *ya* untuk keluar, atau *lanjut* untuk melanjutkan soal. 😊");
        }
        break;

    case 'konfirmasi_keluar_pretest':
        if ($pesan_lower === 'ya') {
            kirim_wa($nomor, "Oke, pre-test dibatalkan. Kembali ke menu ya! 😊");
            tampilkan_menu($user, $nomor, $label_profil, $label_level);
            set_wa_session($pdo, $nomor, 'menu', [], $user['id']);
        } elseif ($pesan_lower === 'lanjut') {
            $soal_index = (int)($context['soal_index'] ?? 0);
            kirim_wa($nomor, "Oke, lanjut! Ini soal yang tadi: 💪");
            kirim_soal_pretest($pdo, $nomor, $soal_index);
            set_wa_session($pdo, $nomor, 'pretest', $context, $user['id']);
        } else {
            kirim_wa($nomor, "Ketik *ya* untuk keluar, atau *lanjut* untuk melanjutkan soal. 😊");
        }
        break;

    default:
        tampilkan_menu($user, $nomor, $label_profil, $label_level);
        set_wa_session($pdo, $nomor, 'menu', [], $user['id']);
        break;
}

http_response_code(200);
echo 'ok';
