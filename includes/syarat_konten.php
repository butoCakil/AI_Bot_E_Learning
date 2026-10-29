<?php
// ── Syarat tuntas per konten ─────────────────────────────────────
// Dipakai oleh: materi.php, api/selesai_materi.php, api/upload_jobsheet.php, api/simpan_tabel.php
// Wajib di-include SETELAH config/config.php dan includes/functions.php (butuh db() & log_aktivitas()).

if (!defined('DURASI_BACA')) define('DURASI_BACA', 35);

// Ambil satu konten aktif
function ambil_konten(int $content_id): ?array {
    $st = db()->prepare("SELECT * FROM content WHERE id = ? AND aktif = 1 LIMIT 1");
    $st->execute([$content_id]);
    $r = $st->fetch();
    return $r ?: null;
}

// Detail sel input: "t{tabel}-r{baris}-c{kolom}" => ['pilihan' => [...] | null]
// Sel .ti-pilih: teks sel berisi opsi dipisah "|" (mis. "Nyala | Mati").
// Urutan indeks HARUS sama dengan JS di materi.php (document order).
function ti_detail_sel(?string $html): array {
    if (!$html || stripos($html, 'tabel-input') === false) return [];
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $xp = new DOMXPath($dom);

    $out    = [];
    $tables = $xp->query('//table[contains(concat(" ", normalize-space(@class), " "), " tabel-input ")]');
    foreach ($tables as $ti => $tb) {
        foreach ($xp->query('.//tr', $tb) as $ri => $tr) {
            $ci = 0;
            foreach ($tr->childNodes as $cell) {
                if (!($cell instanceof DOMElement)) continue;
                $tag = strtolower($cell->nodeName);
                if ($tag !== 'td' && $tag !== 'th') continue;
                $cls = $cell->getAttribute('class');
                if (preg_match('/(^|\s)ti-isi(\s|$)/', $cls)) {
                    $pil = null;
                    if (preg_match('/(^|\s)ti-pilih(\s|$)/', $cls)) {
                        $teks = str_replace("\xC2\xA0", ' ', $cell->textContent);
                        $pil  = array_values(array_filter(array_map('trim', explode('|', $teks)), 'strlen'));
                        if (count($pil) < 2) $pil = null;
                    }
                    $out["t{$ti}-r{$ri}-c{$ci}"] = ['pilihan' => $pil];
                }
                $ci++;
            }
        }
    }
    return $out;
}

// Daftar kunci sel input (dipakai syarat, simpan, rekap)
function ti_daftar_sel(?string $html): array {
    return array_keys(ti_detail_sel($html));
}

// Jawaban tabel milik siswa untuk satu konten
function ti_ambil_jawaban(int $user_id, int $content_id): ?array {
    $st = db()->prepare("SELECT jawaban, lengkap, nilai, updated_at FROM tabel_input_jawaban WHERE user_id = ? AND content_id = ?");
    $st->execute([$user_id, $content_id]);
    $r = $st->fetch();
    if (!$r) return null;
    $r['jawaban'] = json_decode($r['jawaban'], true) ?: [];
    return $r;
}

function sudah_selesai_materi(int $user_id, int $content_id): bool {
    $st = db()->prepare("SELECT 1 FROM activity_log WHERE user_id = ? AND content_id = ? AND tipe = 'selesai_materi' LIMIT 1");
    $st->execute([$user_id, $content_id]);
    return (bool) $st->fetchColumn();
}

// Hitung semua syarat konten untuk siswa
function syarat_konten(int $user_id, array $konten): array {
    $pdo     = db();
    $cid     = (int) $konten['id'];
    $selesai = sudah_selesai_materi($user_id, $cid);
    $item    = [];

    if ($konten['tipe'] === 'evaluasi') {
        $st = $pdo->prepare("SELECT 1 FROM activity_log WHERE user_id = ? AND content_id = ? AND tipe = 'jawab_quiz' LIMIT 1");
        $st->execute([$user_id, $cid]);
        $item['evaluasi'] = ['ok' => (bool) $st->fetchColumn(), 'label' => 'Kirim jawaban evaluasi'];
    } else {
        // Timer baca — dihitung dari buka_materi pertama (jam server/DB, bukan browser)
        $sisa = 0;
        if (!$selesai) {
            $st = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, MIN(created_at), NOW()) FROM activity_log
                                 WHERE user_id = ? AND content_id = ? AND tipe = 'buka_materi'");
            $st->execute([$user_id, $cid]);
            $lama = $st->fetchColumn();
            $sisa = ($lama === null || $lama === false) ? DURASI_BACA : max(0, DURASI_BACA - (int) $lama);
        }
        $item['timer'] = ['ok' => $sisa === 0, 'label' => 'Baca materi', 'sisa' => $sisa];

        // Upload — berlaku untuk tipe apa pun bila perlu_upload dicentang
        if (!empty($konten['perlu_upload'])) {
            $st = $pdo->prepare("SELECT 1 FROM jobsheet_submissions WHERE user_id = ? AND content_id = ? LIMIT 1");
            $st->execute([$user_id, $cid]);
            $item['upload'] = ['ok' => (bool) $st->fetchColumn(), 'label' => 'Upload file'];
        }

        // Tabel input — semua sel wajib terisi
        $sel = ti_daftar_sel($konten['isi'] ?? '');
        if ($sel) {
            $j      = ti_ambil_jawaban($user_id, $cid);
            $kosong = 0;
            foreach ($sel as $k) {
                if (trim((string) ($j['jawaban'][$k] ?? '')) === '') $kosong++;
            }
            $item['tabel'] = ['ok' => $kosong === 0, 'label' => 'Isi tabel', 'kosong' => $kosong, 'total' => count($sel)];
        }
    }

    $semua_ok = true;
    foreach ($item as $it) { if (!$it['ok']) { $semua_ok = false; break; } }

    return ['selesai' => $selesai, 'semua_ok' => $semua_ok, 'item' => $item];
}

// Catat selesai_materi sekali saja, hanya jika semua syarat terpenuhi
function tandai_selesai_jika_lengkap(int $user_id, array $konten, string $topik): array {
    $s = syarat_konten($user_id, $konten);
    if ($s['semua_ok'] && !$s['selesai']) {
        log_aktivitas($user_id, 'selesai_materi', (int) $konten['id'], $topik);
        $s['selesai'] = true;
    }
    return $s;
}

// Render tabel-input dari isi konten dengan jawaban siswa di dalam sel (untuk guru).
// Indeks sama persis dengan ti_daftar_sel().
function ti_render_terisi(?string $html, array $jawaban): string {
    if (!$html || stripos($html, 'tabel-input') === false) return '';
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $xp = new DOMXPath($dom);

    $out    = '';
    $tables = $xp->query('//table[contains(concat(" ", normalize-space(@class), " "), " tabel-input ")]');
    foreach ($tables as $ti => $tb) {
        foreach ($xp->query('.//tr', $tb) as $ri => $tr) {
            $ci = 0;
            foreach (iterator_to_array($tr->childNodes) as $cell) {
                if (!($cell instanceof DOMElement)) continue;
                $tag = strtolower($cell->nodeName);
                if ($tag !== 'td' && $tag !== 'th') continue;
                if (preg_match('/(^|\s)ti-isi(\s|$)/', $cell->getAttribute('class'))) {
                    $v = trim((string) ($jawaban["t{$ti}-r{$ri}-c{$ci}"] ?? ''));
                    while ($cell->firstChild) $cell->removeChild($cell->firstChild);
                    $cell->appendChild($dom->createTextNode($v === '' ? '—' : $v));
                    $cell->setAttribute('style', $v === ''
                        ? 'background:#FEF3C7;color:#B45309;font-weight:700'
                        : 'background:#ECFDF5;color:#065F46;font-weight:700');
                }
                $ci++;
            }
        }
        $tb->setAttribute('style', 'border-collapse:collapse;width:100%');
        $out .= $dom->saveHTML($tb);
    }
    return $out;
}
