<?php
require_once 'config/config.php';

$pdo = db();

function get_id(PDO $pdo, string $topik, string $tipe): ?int {
    $stmt = $pdo->prepare("SELECT id FROM `content` WHERE topik=? AND tipe=? ORDER BY urutan_default ASC LIMIT 1");
    $stmt->execute([$topik, $tipe]);
    $row = $stmt->fetch();
    return $row ? (int)$row['id'] : null;
}

function get_all_ids(PDO $pdo, string $topik, string $tipe): array {
    $stmt = $pdo->prepare("SELECT id FROM `content` WHERE topik=? AND tipe=? ORDER BY urutan_default ASC");
    $stmt->execute([$topik, $tipe]);
    return array_column($stmt->fetchAll(), 'id');
}

$topik_list = ['dioda', 'transistor', 'catu_daya'];
$rules = [];

foreach ($topik_list as $topik) {
    $teori_ids = get_all_ids($pdo, $topik, 'teori');
    $t  = $teori_ids[0] ?? null;
    $t2 = $teori_ids[1] ?? $teori_ids[0] ?? null;
    $l  = get_id($pdo, $topik, 'langkah');
    $e  = get_id($pdo, $topik, 'evaluasi');
    $j  = get_id($pdo, $topik, 'jobsheet');
    $tn = get_id($pdo, $topik, 'tantangan');

    $rules[] = ['profil_gabungan' => "beginner_guided_step",           'topik' => $topik, 'urutan_content' => json_encode([$t, $l, $e, $j]),        'konten_wajib' => json_encode([$t, $l]), 'tipe_evaluasi' => 'konfirmasi'];
    $rules[] = ['profil_gabungan' => "beginner_conceptual",            'topik' => $topik, 'urutan_content' => json_encode([$t, $t2, $e, $l, $j]),   'konten_wajib' => json_encode([$t]),     'tipe_evaluasi' => 'analisis'];
    $rules[] = ['profil_gabungan' => "beginner_practice_oriented",     'topik' => $topik, 'urutan_content' => json_encode([$j, $l, $e, $t]),        'konten_wajib' => json_encode([$t, $l]), 'tipe_evaluasi' => 'konfirmasi'];
    $rules[] = ['profil_gabungan' => "intermediate_guided_step",       'topik' => $topik, 'urutan_content' => json_encode([$t, $l, $e, $j]),        'konten_wajib' => json_encode([$t]),     'tipe_evaluasi' => 'konfirmasi'];
    $rules[] = ['profil_gabungan' => "intermediate_conceptual",        'topik' => $topik, 'urutan_content' => json_encode([$t, $t2, $e, $l, $j]),   'konten_wajib' => json_encode([]),       'tipe_evaluasi' => 'analisis'];
    $rules[] = ['profil_gabungan' => "intermediate_practice_oriented", 'topik' => $topik, 'urutan_content' => json_encode([$j, $tn, $l, $t]),       'konten_wajib' => json_encode([]),       'tipe_evaluasi' => 'tantangan'];
    $rules[] = ['profil_gabungan' => "advanced_guided_step",           'topik' => $topik, 'urutan_content' => json_encode([$t, $l, $e, $j, $tn]),   'konten_wajib' => json_encode([]),       'tipe_evaluasi' => 'konfirmasi'];
    $rules[] = ['profil_gabungan' => "advanced_conceptual",            'topik' => $topik, 'urutan_content' => json_encode([$t, $e, $j, $tn]),       'konten_wajib' => json_encode([]),       'tipe_evaluasi' => 'analisis'];
    $rules[] = ['profil_gabungan' => "advanced_practice_oriented",     'topik' => $topik, 'urutan_content' => json_encode([$tn, $j, $e, $t]),       'konten_wajib' => json_encode([]),       'tipe_evaluasi' => 'tantangan'];
}

$stmt = $pdo->prepare("
    INSERT INTO adaptation_rules (profil_gabungan, topik, urutan_content, konten_wajib, tipe_evaluasi)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
    urutan_content=VALUES(urutan_content),
    konten_wajib=VALUES(konten_wajib),
    tipe_evaluasi=VALUES(tipe_evaluasi)
");

$count = 0;
foreach ($rules as $rule) {
    $stmt->execute([$rule['profil_gabungan'], $rule['topik'], $rule['urutan_content'], $rule['konten_wajib'], $rule['tipe_evaluasi']]);
    $count++;
    echo "✓ [{$rule['topik']}] {$rule['profil_gabungan']}<br>";
}

echo "<br><strong>Total: $count rules berhasil dimasukkan.</strong>";
?>