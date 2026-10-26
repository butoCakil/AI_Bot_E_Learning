<?php
// ── Feedback guru per siswa per konten ───────────────────────────
// Wajib di-include SETELAH config/config.php dan includes/functions.php

function fb_skala_label(): array {
    return [
        'bintang'    => [1 => 'Perlu diulang', 2 => 'Perlu perbaikan', 3 => 'Cukup', 4 => 'Baik', 5 => 'Sangat baik'],
        'kesesuaian' => [1 => 'Belum sesuai', 2 => 'Hampir sesuai', 3 => 'Sesuai'],
    ];
}
function fb_label(string $skala, int $p): string { return fb_skala_label()[$skala][$p] ?? '-'; }
function fb_maks(string $skala): int { return $skala === 'bintang' ? 5 : 3; }

// Skala ditentukan server: wajib upload → bintang; hanya tabel → kesesuaian
function fb_skala_konten(array $konten): string {
    return (!empty($konten['perlu_upload']) && $konten['tipe'] !== 'evaluasi') ? 'bintang' : 'kesesuaian';
}

function fb_ambil(int $uid, int $cid): ?array {
    $st = db()->prepare("SELECT * FROM feedback_guru WHERE user_id = ? AND content_id = ?");
    $st->execute([$uid, $cid]);
    return $st->fetch() ?: null;
}

// Semua feedback: [user_id][content_id] => baris (untuk rekap guru)
function fb_semua(): array {
    $out = [];
    foreach (db()->query("SELECT * FROM feedback_guru")->fetchAll() as $r) {
        $out[(int) $r['user_id']][(int) $r['content_id']] = $r;
    }
    return $out;
}

// menunggu_revisi | revisi_masuk | selesai | null (belum ada feedback)
function fb_status(?array $fb): ?string {
    if (!$fb) return null;
    if ((int) $fb['minta_revisi'] === 1) return 'menunggu_revisi';
    if ($fb['revisi_dikirim_at'] && (!$fb['dinilai_at'] || strtotime($fb['revisi_dikirim_at']) > strtotime($fb['dinilai_at']))) {
        return 'revisi_masuk';
    }
    return 'selesai';
}

// Kiriman terkunci setelah dinilai/diberi feedback; terbuka hanya saat minta revisi
function fb_terkunci(int $uid, int $cid): bool {
    $fb = fb_ambil($uid, $cid);
    if ($fb) return (int) $fb['minta_revisi'] !== 1;
    $st = db()->prepare("SELECT nilai FROM jobsheet_submissions WHERE user_id = ? AND content_id = ?");
    $st->execute([$uid, $cid]);
    $n = $st->fetchColumn();
    return $n !== false && $n !== null;
}

// Guru menyimpan / memperbarui feedback (notif & WA di-reset agar dianggap baru)
function fb_simpan(int $uid, array $konten, int $predikat, string $komentar, bool $minta_revisi, ?int $guru_id): void {
    $st = db()->prepare("
        INSERT INTO feedback_guru (user_id, content_id, topik, skala, predikat, komentar, minta_revisi, guru_id, dinilai_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            topik = VALUES(topik), skala = VALUES(skala), predikat = VALUES(predikat),
            komentar = VALUES(komentar), minta_revisi = VALUES(minta_revisi), guru_id = VALUES(guru_id),
            dinilai_at = NOW(), dibaca_at = NULL, wa_dikirim_at = NULL
    ");
    $st->execute([
        $uid, (int) $konten['id'], $konten['topik'], fb_skala_konten($konten),
        $predikat, $komentar !== '' ? $komentar : null, $minta_revisi ? 1 : 0, $guru_id,
    ]);
}

// Siswa membuka konten: feedback non-revisi dianggap terbaca
function fb_tandai_dibaca(int $uid, int $cid): void {
    db()->prepare("UPDATE feedback_guru SET dibaca_at = NOW()
                   WHERE user_id = ? AND content_id = ? AND dibaca_at IS NULL AND minta_revisi = 0")
        ->execute([$uid, $cid]);
}

// Siswa mengirim revisi: kunci lagi, masuk antrean guru
function fb_kirim_revisi(int $uid, int $cid): bool {
    $st = db()->prepare("UPDATE feedback_guru
                         SET minta_revisi = 0, revisi_ke = revisi_ke + 1, revisi_dikirim_at = NOW(), dibaca_at = NOW()
                         WHERE user_id = ? AND content_id = ? AND minta_revisi = 1");
    $st->execute([$uid, $cid]);
    return $st->rowCount() > 0;
}

// Notifikasi siswa per topik: feedback belum dibaca + revisi yang masih diminta
function fb_notif_siswa(int $uid): array {
    $st = db()->prepare("
        SELECT f.content_id, f.topik, f.skala, f.predikat, f.minta_revisi, c.judul
        FROM feedback_guru f JOIN content c ON c.id = f.content_id
        WHERE f.user_id = ? AND (f.minta_revisi = 1 OR f.dibaca_at IS NULL)
        ORDER BY f.dinilai_at DESC
    ");
    $st->execute([$uid]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $t = $r['topik'];
        if (!isset($out[$t])) $out[$t] = ['baru' => 0, 'revisi' => 0, 'item' => []];
        if ((int) $r['minta_revisi'] === 1) $out[$t]['revisi']++; else $out[$t]['baru']++;
        $r['label'] = fb_label($r['skala'], (int) $r['predikat']);
        $out[$t]['item'][] = $r;
    }
    return $out;
}
