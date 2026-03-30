<?php
require_once 'config/config.php';

$pdo = db();

// Ambil ID konten per topik per tipe
function get_id(PDO $pdo, string $topik, string $tipe): ?int {
    $stmt = $pdo->prepare("SELECT id FROM `content` WHERE topik=? AND tipe=? LIMIT 1");
    $stmt->execute([$topik, $tipe]);
    $row = $stmt->fetch();
    return $row ? (int)$row['id'] : null;
}

$topik_list = ['dioda', 'transistor', 'catu_daya'];

// Ambil semua ID per topik
$ids = [];
foreach ($topik_list as $topik) {
    $ids[$topik] = [
        'teori'     => get_id($pdo, $topik, 'teori'),
        'langkah'   => get_id($pdo, $topik, 'langkah'),
        'evaluasi'  => get_id($pdo, $topik, 'evaluasi'),
        'jobsheet'  => get_id($pdo, $topik, 'jobsheet'),
        'tantangan' => get_id($pdo, $topik, 'tantangan'),
    ];
}

/*
  DECISION TREE ADAPTATION MODEL
  ─────────────────────────────────────────────────────
  Profil Gabungan = level + profil_learning
  9 kombinasi total

  Urutan konten per profil:
  ┌─────────────────────┬──────────────────────────────────────────────┐
  │ Guided-Step         │ teori → langkah → evaluasi → jobsheet        │
  │ Conceptual          │ teori(2x) → evaluasi → langkah → jobsheet    │
  │ Practice-Oriented   │ jobsheet → tantangan → langkah → teori       │
  └─────────────────────┴──────────────────────────────────────────────┘

  Konten wajib (harus dibuka sebelum lanjut):
  ┌─────────────────────┬──────────────────────────────────────────────┐
  │ Beginner            │ teori + langkah wajib                        │
  │ Intermediate        │ teori wajib, langkah opsional                │
  │ Advanced            │ tidak ada yang wajib                         │
  └─────────────────────┴──────────────────────────────────────────────┘
*/

$rules = [];

foreach ($topik_list as $topik) {
    $t  = $ids[$topik]['teori'];
    $l  = $ids[$topik]['langkah'];
    $e  = $ids[$topik]['evaluasi'];
    $j  = $ids[$topik]['jobsheet'];
    $tn = $ids[$topik]['tantangan'];

    // ── BEGINNER ──────────────────────────────────────────
    $rules[] = [
        'profil_gabungan' => "beginner_guided_step",
        'topik'           => $topik,
        'urutan_content'  => json_encode([$t, $l, $e, $j]),
        'konten_wajib'    => json_encode([$t, $l]),
        'tipe_evaluasi'   => 'konfirmasi',
    ];
    $rules[] = [
        'profil_gabungan' => "beginner_conceptual",
        'topik'           => $topik,
        'urutan_content'  => json_encode([$t, $t, $e, $l, $j]),
        'konten_wajib'    => json_encode([$t]),
        'tipe_evaluasi'   => 'analisis',
    ];
    $rules[] = [
        'profil_gabungan' => "beginner_practice_oriented",
        'topik'           => $topik,
        'urutan_content'  => json_encode([$j, $l, $e, $t]),
        'konten_wajib'    => json_encode([$t, $l]),
        'tipe_evaluasi'   => 'konfirmasi',
    ];

    // ── INTERMEDIATE ───────────────────────────────────────
    $rules[] = [
        'profil_gabungan' => "intermediate_guided_step",
        'topik'           => $topik,
        'urutan_content'  => json_encode([$t, $l, $e, $j]),
        'konten_wajib'    => json_encode([$t]),
        'tipe_evaluasi'   => 'konfirmasi',
    ];
    $rules[] = [
        'profil_gabungan' => "intermediate_conceptual",
        'topik'           => $topik,
        'urutan_content'  => json_encode([$t, $t, $e, $l, $j]),
        'konten_wajib'    => json_encode([]),
        'tipe_evaluasi'   => 'analisis',
    ];
    $rules[] = [
        'profil_gabungan' => "intermediate_practice_oriented",
        'topik'           => $topik,
        'urutan_content'  => json_encode([$j, $tn, $l, $t]),
        'konten_wajib'    => json_encode([]),
        'tipe_evaluasi'   => 'tantangan',
    ];

    // ── ADVANCED ───────────────────────────────────────────
    $rules[] = [
        'profil_gabungan' => "advanced_guided_step",
        'topik'           => $topik,
        'urutan_content'  => json_encode([$t, $l, $e, $j, $tn]),
        'konten_wajib'    => json_encode([]),
        'tipe_evaluasi'   => 'konfirmasi',
    ];
    $rules[] = [
        'profil_gabungan' => "advanced_conceptual",
        'topik'           => $topik,
        'urutan_content'  => json_encode([$t, $e, $j, $tn]),
        'konten_wajib'    => json_encode([]),
        'tipe_evaluasi'   => 'analisis',
    ];
    $rules[] = [
        'profil_gabungan' => "advanced_practice_oriented",
        'topik'           => $topik,
        'urutan_content'  => json_encode([$tn, $j, $e, $t]),
        'konten_wajib'    => json_encode([]),
        'tipe_evaluasi'   => 'tantangan',
    ];
}

// Insert ke database
$stmt = $pdo->prepare("
    INSERT INTO adaptation_rules 
    (profil_gabungan, topik, urutan_content, konten_wajib, tipe_evaluasi)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
    urutan_content=VALUES(urutan_content),
    konten_wajib=VALUES(konten_wajib),
    tipe_evaluasi=VALUES(tipe_evaluasi)
");

$count = 0;
foreach ($rules as $rule) {
    $stmt->execute([
        $rule['profil_gabungan'],
        $rule['topik'],
        $rule['urutan_content'],
        $rule['konten_wajib'],
        $rule['tipe_evaluasi'],
    ]);
    $count++;
    echo "✓ [{$rule['topik']}] {$rule['profil_gabungan']}<br>";
}

echo "<br><strong>Total: $count rules berhasil dimasukkan.</strong>";
echo "<br><small>Decision Tree adaptation model siap.</small>";
?>
