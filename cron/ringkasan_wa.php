<?php
// ── Ringkasan feedback guru via WhatsApp ──────────────────────────
// Dijalankan cron tiap 5 menit. Kirim hanya bila: aktif & sudah lewat jam kirim
// & belum dikirim hari ini, ATAU guru menekan "Kirim sekarang" (wa_ringkasan_paksa).
// Uji tanpa kirim:  php cron/ringkasan_wa.php --uji
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/wa.php';

function catat(string $t): void { echo '[' . date('Y-m-d H:i:s') . '] ' . $t . PHP_EOL; }

$uji   = in_array('--uji', $argv ?? [], true);
$kunci = fopen(sys_get_temp_dir() . '/adaptlearn_ringkasan_wa.lock', 'c');
if (!$kunci || !flock($kunci, LOCK_EX | LOCK_NB)) { if ($uji) catat('Proses lain sedang berjalan.'); exit(0); }

$aktif    = get_pengaturan('wa_ringkasan_aktif', '0') === '1';
$jam      = (string) get_pengaturan('wa_ringkasan_jam', '15:30');
$jeda     = max(10, min(300, (int) get_pengaturan('wa_ringkasan_jeda_detik', '30')));
$terakhir = (string) get_pengaturan('wa_ringkasan_terakhir', '');
$paksa    = get_pengaturan('wa_ringkasan_paksa', '0') === '1';
$hari_ini = date('Y-m-d');
if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jam)) $jam = '15:30';

$jadwal = $aktif && date('H:i') >= $jam && $terakhir !== $hari_ini;
if (!$uji && !$jadwal && !$paksa) exit(0);

if (!$uji) {
    if ($paksa)  set_pengaturan('wa_ringkasan_paksa', '0');
    if ($jadwal) set_pengaturan('wa_ringkasan_terakhir', $hari_ini);
}

$rows = db()->query("
    SELECT f.id, f.user_id, f.topik, f.minta_revisi, u.nama, u.nomor_wa
    FROM feedback_guru f
    JOIN users u ON u.id = f.user_id
    WHERE f.wa_dikirim_at IS NULL
      AND f.dinilai_at >= NOW() - INTERVAL 7 DAY
      AND (f.dibaca_at IS NULL OR f.minta_revisi = 1)
      AND u.nomor_wa IS NOT NULL AND u.nomor_wa <> ''
    ORDER BY u.id, f.topik
")->fetchAll();

// Kelompokkan per siswa → per topik
$per = [];
foreach ($rows as $r) {
    $uid = (int) $r['user_id'];
    if (!isset($per[$uid])) $per[$uid] = ['nama' => $r['nama'], 'nomor' => $r['nomor_wa'], 'ids' => [], 'topik' => []];
    $per[$uid]['ids'][] = (int) $r['id'];
    $t = $r['topik'];
    if (!isset($per[$uid]['topik'][$t])) $per[$uid]['topik'][$t] = ['dinilai' => 0, 'revisi' => 0];
    if ((int) $r['minta_revisi'] === 1) $per[$uid]['topik'][$t]['revisi']++;
    else                                $per[$uid]['topik'][$t]['dinilai']++;
}

$total = count($per);
catat(($uji ? '[UJI] ' : '') . "Mulai: {$total} siswa, jeda {$jeda} dtk" . ($paksa ? ' (kirim sekarang)' : ''));
if ($total === 0) {
    if (!$uji) set_pengaturan('wa_ringkasan_log', date('d-m-Y H:i') . ' · tidak ada feedback baru untuk dikirim');
    exit(0);
}

$nama_topik = get_topik_list();
$app_url    = defined('APP_URL') ? APP_URL : '';
$ok = $gagal = $n = 0;

foreach ($per as $uid => $g) {
    $baris = [];
    foreach ($g['topik'] as $t => $c) {
        $bagian = [];
        if ($c['dinilai'] > 0) $bagian[] = $c['dinilai'] . ' tugas dinilai';
        if ($c['revisi'] > 0)  $bagian[] = $c['revisi'] . ' perlu diperbaiki';
        $baris[] = '• ' . ($nama_topik[$t] ?? $t) . ': ' . implode(', ', $bagian);
    }
    $pesan = "*AdaptLearn PRE* — Halo {$g['nama']}, ada feedback baru dari guru:\n\n"
           . implode("\n", $baris)
           . "\n\nBuka aplikasi untuk detailnya:\n{$app_url}/home.php";

    $nomor = normalisasi_wa((string) $g['nomor']);
    if ($uji) {
        catat("[UJI] ke {$nomor}:\n{$pesan}\n");
        continue;
    }

    if ($n++ > 0) sleep($jeda + random_int(0, intdiv($jeda, 3)));
    if (kirim_wa($nomor, $pesan)) {
        $ok++;
        $ph = implode(',', array_fill(0, count($g['ids']), '?'));
        db()->prepare("UPDATE feedback_guru SET wa_dikirim_at = NOW() WHERE id IN ($ph)")->execute($g['ids']);
        catat("OK    {$nomor} ({$g['nama']})");
    } else {
        $gagal++;
        catat("GAGAL {$nomor} ({$g['nama']}) — dicoba lagi di jadwal berikutnya");
    }
}

if (!$uji) {
    set_pengaturan('wa_ringkasan_log', date('d-m-Y H:i') . " · {$ok} terkirim, {$gagal} gagal, dari {$total} siswa");
    catat("Selesai: {$ok} terkirim, {$gagal} gagal");
}
