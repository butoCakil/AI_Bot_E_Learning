<?php
// ── Rekap tugas & progres siswa (tab Penilaian guru) ─────────────
// Wajib di-include SETELAH config/config.php, includes/functions.php,
// includes/syarat_konten.php, includes/feedback.php
// "Tugas" = konten di jalur siswa yang wajib upload dan/atau punya tabel input.

function rt_konten(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    foreach (db()->query("SELECT id, judul, tipe, topik, perlu_upload, isi FROM content WHERE aktif = 1")->fetchAll() as $c) {
        $bukan_eval = $c['tipe'] !== 'evaluasi';
        $cache[(int) $c['id']] = [
            'id'           => (int) $c['id'],
            'judul'        => $c['judul'],
            'tipe'         => $c['tipe'],
            'topik'        => $c['topik'],
            'perlu_upload' => $bukan_eval && !empty($c['perlu_upload']),
            'ada_tabel'    => $bukan_eval && ti_daftar_sel($c['isi'] ?? '') !== [],
        ];
    }
    return $cache;
}

function rt_jalur(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $urut = array_flip(array_keys(get_topik_list()));
    $rows = db()->query("SELECT profil_gabungan, topik, urutan_content FROM adaptation_rules")->fetchAll();
    usort($rows, function ($a, $b) use ($urut) {
        return ($urut[$a['topik']] ?? 999) <=> ($urut[$b['topik']] ?? 999);
    });
    $cache = [];
    foreach ($rows as $r) {
        if (!isset($urut[$r['topik']])) continue;
        $ids = array_values(array_unique(array_map('intval', json_decode($r['urutan_content'], true) ?: [])));
        $cache[$r['profil_gabungan']][$r['topik']] = $ids;
    }
    return $cache;
}

function rt_profil(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    $sql = "SELECT p.user_id, p.profil_gabungan FROM pre_test_results p
            JOIN (SELECT user_id, MAX(id) AS mid FROM pre_test_results GROUP BY user_id) x ON x.mid = p.id";
    foreach (db()->query($sql)->fetchAll() as $r) $cache[(int) $r['user_id']] = $r['profil_gabungan'];
    return $cache;
}

function rt_data_kerja(): array {
    $pdo = db();
    $d = ['selesai' => [], 'upload' => [], 'tabel' => [], 'eval' => [], 'fb' => fb_semua()];
    foreach ($pdo->query("SELECT DISTINCT user_id, content_id FROM activity_log WHERE tipe = 'selesai_materi'")->fetchAll() as $r)
        $d['selesai'][(int) $r['user_id']][(int) $r['content_id']] = true;
    foreach ($pdo->query("SELECT id, user_id, content_id, file_path, file_original_name, nilai, created_at FROM jobsheet_submissions")->fetchAll() as $r)
        $d['upload'][(int) $r['user_id']][(int) $r['content_id']] = $r;
    foreach ($pdo->query("SELECT user_id, content_id, lengkap, updated_at FROM tabel_input_jawaban")->fetchAll() as $r)
        $d['tabel'][(int) $r['user_id']][(int) $r['content_id']] = $r;
    foreach ($pdo->query("SELECT user_id, content_id, persentase FROM evaluasi_results")->fetchAll() as $r)
        $d['eval'][(int) $r['user_id']][(int) $r['content_id']] = (int) $r['persentase'];
    return $d;
}

// Status satu tugas: belum_kumpul | sebagian | perlu_dinilai | menunggu_revisi | dinilai
function rt_status_tugas(array $k, ?array $up, ?array $tb, ?array $fb): array {
    $up_ok     = !$k['perlu_upload'] || $up;
    $tb_ok     = !$k['ada_tabel'] || ($tb && (int) $tb['lengkap'] === 1);
    $terkumpul = $up_ok && $tb_ok;
    $fs        = fb_status($fb);

    if     ($fs === 'menunggu_revisi')                  $st = 'menunggu_revisi';
    elseif ($fs === 'revisi_masuk')                     $st = 'perlu_dinilai';
    elseif ($fs === 'selesai')                          $st = 'dinilai';
    elseif ($up && $up['nilai'] !== null)               $st = 'dinilai';        // nilai lama tanpa feedback
    elseif (($k['perlu_upload'] && $up) || (!$k['perlu_upload'] && $terkumpul)) $st = 'perlu_dinilai';
    elseif ($up || $tb)                                 $st = 'sebagian';
    else                                                $st = 'belum_kumpul';

    return ['terkumpul' => $terkumpul, 'status' => $st];
}

function rt_status_siswa(array $r): string {
    if (!$r['profil'])                                 return 'belum_pretest';
    if ($r['tugas_total'] === 0)                       return 'tanpa_tugas';
    if ($r['perlu_nilai'] > 0)                         return 'perlu_dinilai';
    if ($r['revisi'] > 0)                              return 'menunggu_revisi';
    if ($r['terkumpul'] === 0 && $r['dinilai'] === 0)  return 'belum_kumpul';
    if ($r['terkumpul'] < $r['tugas_total'])           return 'belum_lengkap';
    return 'lengkap';
}

function rt_label_status(string $kode): string {
    $map = [
        'belum_pretest'   => 'Belum pre-test',
        'tanpa_tugas'     => 'Tidak ada tugas',
        'perlu_dinilai'   => 'Perlu dinilai',
        'menunggu_revisi' => 'Menunggu revisi',
        'belum_kumpul'    => 'Belum mengumpulkan',
        'belum_lengkap'   => 'Tugas belum lengkap',
        'lengkap'         => 'Lengkap',
    ];
    return $map[$kode] ?? $kode;
}

function rt_rekap(array $siswa, ?array $d = null): array {
    $konten = rt_konten();
    $jalur  = rt_jalur();
    $profil = rt_profil();
    $d      = $d ?? rt_data_kerja();
    $out    = [];

    foreach ($siswa as $s) {
        $uid = (int) $s['id'];
        $pg  = $profil[$uid] ?? null;
        $r = ['profil' => $pg, 'materi_total' => 0, 'materi_selesai' => 0, 'tugas_total' => 0,
              'terkumpul' => 0, 'perlu_nilai' => 0, 'dinilai' => 0, 'revisi' => 0, 'belum' => 0];

        if ($pg && isset($jalur[$pg])) {
            foreach ($jalur[$pg] as $ids) {
                foreach ($ids as $cid) {
                    if (!isset($konten[$cid])) continue;
                    $k = $konten[$cid];
                    $r['materi_total']++;
                    if (isset($d['selesai'][$uid][$cid])) $r['materi_selesai']++;
                    if (!$k['perlu_upload'] && !$k['ada_tabel']) continue;

                    $r['tugas_total']++;
                    $t = rt_status_tugas($k, $d['upload'][$uid][$cid] ?? null, $d['tabel'][$uid][$cid] ?? null, $d['fb'][$uid][$cid] ?? null);
                    if ($t['terkumpul'])                   $r['terkumpul']++;
                    if ($t['status'] === 'perlu_dinilai')   $r['perlu_nilai']++;
                    if ($t['status'] === 'dinilai')         $r['dinilai']++;
                    if ($t['status'] === 'menunggu_revisi') $r['revisi']++;
                    if ($t['status'] === 'belum_kumpul' || $t['status'] === 'sebagian') $r['belum']++;
                }
            }
        }
        $r['status'] = rt_status_siswa($r);
        $r['label']  = rt_label_status($r['status']);

        // Semua kondisi yang dimiliki siswa (untuk filter & label ganda)
        $tag = [];
        if (!$r['profil'])              $tag[] = 'belum_pretest';
        if ($r['perlu_nilai'] > 0)      $tag[] = 'perlu_dinilai';
        if ($r['revisi'] > 0)           $tag[] = 'menunggu_revisi';
        if ($r['belum'] > 0)            $tag[] = 'belum_kumpul';
        if ($r['dinilai'] > 0)          $tag[] = 'dinilai';
        if ($r['status'] === 'lengkap') $tag[] = 'lengkap';
        $r['tag'] = $tag;
        $out[$uid]   = $r;
    }
    return $out;
}

function rt_detail(int $uid): ?array {
    $pdo = db();
    $st  = $pdo->prepare("SELECT id, nama, nis, kelas FROM users WHERE id = ? AND role = 'siswa'");
    $st->execute([$uid]);
    $s = $st->fetch();
    if (!$s) return null;

    $konten = rt_konten();
    $jalur  = rt_jalur();
    $pg     = rt_profil()[$uid] ?? null;
    $d      = rt_data_kerja();
    $rekap  = rt_rekap([$s], $d)[$uid];
    $nama_t = get_topik_list();
    $st_isi = $pdo->prepare("SELECT isi FROM content WHERE id = ?");

    $blok = [];
    if ($pg && isset($jalur[$pg])) {
        foreach ($jalur[$pg] as $topik => $ids) {
            $eval  = [];
            $tugas = [];
            foreach ($ids as $cid) {
                if (!isset($konten[$cid])) continue;
                $k = $konten[$cid];
                if ($k['tipe'] === 'evaluasi') {
                    $eval[] = ['judul' => $k['judul'], 'persen' => $d['eval'][$uid][$cid] ?? null];
                    continue;
                }
                if (!$k['perlu_upload'] && !$k['ada_tabel']) continue;

                $up = $d['upload'][$uid][$cid] ?? null;
                $tb = $d['tabel'][$uid][$cid] ?? null;
                $fb = $d['fb'][$uid][$cid] ?? null;
                $t  = rt_status_tugas($k, $up, $tb, $fb);
                $item = [
                    'content_id'   => $cid,
                    'judul'        => $k['judul'],
                    'tipe'         => $k['tipe'],
                    'status'       => $t['status'],
                    'skala'        => fb_skala_konten($k),
                    'perlu_upload' => $k['perlu_upload'],
                    'ada_tabel'    => $k['ada_tabel'],
                    'upload'       => null,
                    'tabel'        => null,
                    'feedback'     => null,
                ];
                if ($up) {
                    $item['upload'] = [
                        'id'    => (int) $up['id'],
                        'nama'  => $up['file_original_name'],
                        'url'   => '/' . $up['file_path'],
                        'nilai' => $up['nilai'] !== null ? (float) $up['nilai'] : null,
                        'waktu' => $up['created_at'],
                    ];
                }
                if ($tb) {
                    $st_isi->execute([$cid]);
                    $j = ti_ambil_jawaban($uid, $cid);
                    $item['tabel'] = [
                        'html'    => ti_render_terisi((string) $st_isi->fetchColumn(), $j['jawaban'] ?? []),
                        'lengkap' => (int) $tb['lengkap'] === 1,
                        'waktu'   => $tb['updated_at'],
                    ];
                }
                if ($fb) {
                    $item['feedback'] = [
                        'skala'             => $fb['skala'],
                        'predikat'          => (int) $fb['predikat'],
                        'label'             => fb_label($fb['skala'], (int) $fb['predikat']),
                        'komentar'          => $fb['komentar'],
                        'minta_revisi'      => (int) $fb['minta_revisi'] === 1,
                        'revisi_ke'         => (int) $fb['revisi_ke'],
                        'dinilai_at'        => $fb['dinilai_at'],
                        'revisi_dikirim_at' => $fb['revisi_dikirim_at'],
                    ];
                }
                $tugas[] = $item;
            }
            if ($eval || $tugas) {
                $blok[] = ['topik' => $topik, 'nama' => $nama_t[$topik] ?? $topik, 'evaluasi' => $eval, 'tugas' => $tugas];
            }
        }
    }

    return [
        'siswa' => ['id' => (int) $s['id'], 'nama' => $s['nama'], 'nis' => $s['nis'], 'kelas' => $s['kelas']],
        'rekap' => $rekap,
        'topik' => $blok,
    ];
}
