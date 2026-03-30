<?php
session_start();
require_once 'config/config.php';
require_once 'config/soal_pretest.php';
require_once 'includes/functions.php';

// Guard — harus sudah registrasi
require_login();

// Inisialisasi sesi pretest
if (empty($_SESSION['pretest_mulai'])) {
    $_SESSION['pretest_mulai']    = true;
    $_SESSION['soal_no']          = 0;
    $_SESSION['jawaban_pngt']     = [];
    $_SESSION['jawaban_sjt']      = [];
}

// Gabung semua soal: 12 pengetahuan + 8 SJT
$semua_soal = [];
foreach (SOAL_PENGETAHUAN as $s) {
    $semua_soal[] = array_merge($s, ['bagian' => 'pengetahuan']);
}
foreach (SOAL_SJT as $s) {
    $semua_soal[] = array_merge($s, ['bagian' => 'sjt']);
}
$total = count($semua_soal); // 20

// Proses jawaban
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jawaban'])) {
    $no      = (int) $_POST['soal_no'];
    $jawaban = strtoupper(trim($_POST['jawaban']));
    $soal    = $semua_soal[$no];

    if ($soal['bagian'] === 'pengetahuan') {
        $_SESSION['jawaban_pngt'][] = $jawaban;
    } else {
        $_SESSION['jawaban_sjt'][] = $jawaban;
    }

    $_SESSION['soal_no'] = $no + 1;

    // Semua soal selesai — kirim ke API
    if ($_SESSION['soal_no'] >= $total) {
        $ch = curl_init('http://localhost/api/pretest.php');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'user_id'              => $_SESSION['user_id'],
                'jawaban_pengetahuan'  => $_SESSION['jawaban_pngt'],
                'jawaban_sjt'          => $_SESSION['jawaban_sjt'],
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        $_SESSION['hasil_pretest'] = $result;

        // Reset sesi pretest
        unset($_SESSION['pretest_mulai'], $_SESSION['soal_no'],
              $_SESSION['jawaban_pngt'], $_SESSION['jawaban_sjt']);

        header('Location: hasil.php');
        exit;
    }

    header('Location: pretest.php');
    exit;
}

$no_sekarang = $_SESSION['soal_no'];
$soal        = $semua_soal[$no_sekarang];
$progress    = round(($no_sekarang / $total) * 100);
$bagian_label = $soal['bagian'] === 'pengetahuan'
    ? 'Bagian A — Pengetahuan'
    : 'Bagian B — Skenario Belajar';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pre-Test — AdaptLearn PRE</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }
  .header {
    width: 100%;
    max-width: 600px;
    margin-bottom: 16px;
  }
  .header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
  }
  .header-top span {
    color: #fff;
    font-size: 13px;
    opacity: 0.8;
  }
  .progress-bar {
    width: 100%;
    height: 6px;
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
    overflow: hidden;
  }
  .progress-fill {
    height: 100%;
    background: #4ecdc4;
    border-radius: 10px;
    transition: width 0.4s ease;
    width: <?= $progress ?>%;
  }
  .card {
    background: #fff;
    border-radius: 16px;
    padding: 36px;
    width: 100%;
    max-width: 600px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: fadeIn 0.3s ease;
  }
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .bagian-label {
    font-size: 11px;
    font-weight: 700;
    color: #0f3460;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 8px;
  }
  .nomor-soal {
    font-size: 13px;
    color: #999;
    margin-bottom: 16px;
  }
  .pertanyaan {
    font-size: 16px;
    font-weight: 600;
    color: #1a1a2e;
    line-height: 1.6;
    margin-bottom: 28px;
  }
  .opsi-list {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .opsi-item label {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    border: 2px solid #e8e8e8;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
    color: #333;
    line-height: 1.5;
  }
  .opsi-item input[type="radio"] { display: none; }
  .opsi-item label:hover {
    border-color: #0f3460;
    background: #f0f7ff;
  }
  .opsi-item input[type="radio"]:checked + label {
    border-color: #0f3460;
    background: #e8f4fd;
    font-weight: 600;
  }
  .huruf {
    min-width: 28px;
    height: 28px;
    background: #f0f0f0;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 13px;
    color: #666;
    flex-shrink: 0;
  }
  .opsi-item input[type="radio"]:checked + label .huruf {
    background: #0f3460;
    color: #fff;
  }
  .btn-next {
    width: 100%;
    margin-top: 24px;
    padding: 14px;
    background: #0f3460;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
  }
  .btn-next:hover { background: #16213e; }
  .btn-next:active { transform: scale(0.98); }
  .btn-next:disabled {
    background: #ccc;
    cursor: not-allowed;
  }
  .nama-siswa {
    color: rgba(255,255,255,0.7);
    font-size: 13px;
  }
</style>
</head>
<body>

<div class="header">
  <div class="header-top">
    <span><?= htmlspecialchars($_SESSION['nama']) ?> — <?= htmlspecialchars($_SESSION['kelas']) ?></span>
    <span>Soal <?= $no_sekarang + 1 ?> / <?= $total ?></span>
  </div>
  <div class="progress-bar">
    <div class="progress-fill"></div>
  </div>
</div>

<div class="card">
  <div class="bagian-label"><?= $bagian_label ?></div>
  <div class="nomor-soal">Soal <?= $no_sekarang + 1 ?> dari <?= $total ?></div>
  <div class="pertanyaan"><?= htmlspecialchars($soal['soal']) ?></div>

  <form method="POST" id="form-soal">
    <input type="hidden" name="soal_no" value="<?= $no_sekarang ?>">
    <ul class="opsi-list">
      <?php foreach ($soal['opsi'] as $huruf => $teks): ?>
      <li class="opsi-item">
        <input type="radio" name="jawaban" id="opsi_<?= $huruf ?>"
               value="<?= $huruf ?>" onchange="document.getElementById('btn-next').disabled=false">
        <label for="opsi_<?= $huruf ?>">
          <span class="huruf"><?= $huruf ?></span>
          <?= htmlspecialchars($teks) ?>
        </label>
      </li>
      <?php endforeach; ?>
    </ul>
    <button type="submit" class="btn-next" id="btn-next" disabled>
      <?= $no_sekarang + 1 < $total ? 'Lanjut →' : 'Selesai & Lihat Hasil →' ?>
    </button>
  </form>
</div>

</body>
</html>
