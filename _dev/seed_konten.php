<?php
require_once 'config/config.php';

$pdo = db();

$konten = [

  // ── DIODA ──────────────────────────────────────────────────────

  [
    'topik' => 'dioda', 'tipe' => 'teori', 'urutan_default' => 1,
    'judul' => 'Pengertian dan Prinsip Kerja Dioda',
    'isi'   => '
<h3>Apa itu Dioda?</h3>
<p>Dioda adalah komponen elektronika semikonduktor yang hanya mengalirkan arus listrik dalam satu arah. Dioda terbuat dari bahan semikonduktor tipe-P dan tipe-N yang disambungkan (junction P-N).</p>
<h3>Simbol dan Terminal</h3>
<p>Dioda memiliki dua terminal:</p>
<ul>
  <li><strong>Anoda (A)</strong> — terminal positif, terhubung ke sisi P</li>
  <li><strong>Katoda (K)</strong> — terminal negatif, terhubung ke sisi N</li>
</ul>
<p>Arus hanya mengalir dari Anoda ke Katoda (forward bias). Ketika dipasang terbalik (reverse bias), dioda tidak mengalirkan arus.</p>
<h3>Tegangan Maju (Forward Voltage)</h3>
<p>Dioda silikon membutuhkan tegangan maju minimal <strong>0,7V</strong> agar dapat mengalirkan arus. Dioda germanium membutuhkan sekitar 0,3V.</p>
<h3>Kurva Karakteristik</h3>
<p>Kurva V-I dioda menunjukkan bahwa arus meningkat tajam setelah tegangan maju melampaui nilai threshold (0,7V untuk silikon). Pada kondisi reverse bias, hanya mengalir arus bocor yang sangat kecil.</p>
    '
  ],

  [
    'topik' => 'dioda', 'tipe' => 'teori', 'urutan_default' => 2,
    'judul' => 'Jenis-Jenis Dioda dan Kegunaannya',
    'isi'   => '
<h3>Jenis Dioda yang Umum Digunakan</h3>
<table border="1" cellpadding="8" style="width:100%;border-collapse:collapse;">
  <tr><th>Jenis</th><th>Kegunaan</th></tr>
  <tr><td>Dioda Penyearah</td><td>Mengubah AC menjadi DC dalam rangkaian catu daya</td></tr>
  <tr><td>Dioda Zener</td><td>Regulator tegangan, bekerja pada reverse bias</td></tr>
  <tr><td>LED</td><td>Menghasilkan cahaya saat dialiri arus maju</td></tr>
  <tr><td>Dioda Schottky</td><td>Switching cepat, tegangan maju rendah (~0,3V)</td></tr>
  <tr><td>Dioda Varaktor</td><td>Kapasitansi variabel, digunakan pada tuner frekuensi</td></tr>
</table>
<h3>Kode Komponen</h3>
<p>Dioda penyearah umum yang sering digunakan di laboratorium SMK antara lain: <strong>1N4001</strong> sampai <strong>1N4007</strong> (berbeda pada tegangan balik maksimum), dan <strong>1N5408</strong> untuk arus yang lebih besar.</p>
    '
  ],

  [
    'topik' => 'dioda', 'tipe' => 'langkah', 'urutan_default' => 3,
    'judul' => 'Langkah Kerja: Pengujian Dioda dengan Multimeter',
    'isi'   => '
<h3>Tujuan</h3>
<p>Menguji kondisi dioda (baik/rusak) dan mengidentifikasi terminal anoda dan katoda menggunakan multimeter.</p>
<h3>Alat dan Bahan</h3>
<ul>
  <li>Multimeter digital</li>
  <li>Dioda 1N4001 (atau sejenis)</li>
  <li>Breadboard</li>
</ul>
<h3>Langkah Kerja</h3>
<ol>
  <li>Atur multimeter pada mode <strong>pengujian dioda</strong> (simbol dioda).</li>
  <li>Hubungkan probe merah (+) ke Anoda dan probe hitam (−) ke Katoda.</li>
  <li>Baca nilai pada display — seharusnya menunjukkan nilai antara <strong>0,5 — 0,8V</strong>.</li>
  <li>Balik posisi probe (merah ke Katoda, hitam ke Anoda).</li>
  <li>Display seharusnya menunjukkan <strong>OL</strong> atau <strong>1</strong> (tidak ada konduksi).</li>
  <li>Catat hasil pengukuran pada tabel data.</li>
</ol>
<h3>Analisis Hasil</h3>
<ul>
  <li>Nilai 0,5–0,8V pada forward bias → dioda <strong>baik</strong></li>
  <li>Nilai 0 pada kedua arah → dioda <strong>short circuit (rusak)</strong></li>
  <li>OL pada kedua arah → dioda <strong>open circuit (rusak)</strong></li>
</ul>
    '
  ],

  [
    'topik' => 'dioda', 'tipe' => 'jobsheet', 'urutan_default' => 4,
    'judul' => 'Jobsheet: Rangkaian Penyearah Setengah Gelombang',
    'isi'   => '
<h3>Tujuan Pembelajaran</h3>
<ol>
  <li>Siswa dapat merangkai rangkaian penyearah setengah gelombang.</li>
  <li>Siswa dapat mengukur tegangan input dan output rangkaian.</li>
  <li>Siswa dapat menjelaskan prinsip kerja penyearah setengah gelombang.</li>
</ol>
<h3>Dasar Teori Singkat</h3>
<p>Penyearah setengah gelombang menggunakan satu dioda untuk meneruskan hanya setengah siklus sinyal AC (siklus positif), sedangkan siklus negatif diblokir. Tegangan DC rata-rata yang dihasilkan: <strong>Vdc = 0,318 × Vpeak</strong>.</p>
<h3>Alat dan Bahan</h3>
<ul>
  <li>Transformator step-down 12V</li>
  <li>Dioda 1N4001</li>
  <li>Resistor 1 kΩ</li>
  <li>Kapasitor 1000µF / 25V</li>
  <li>Multimeter</li>
  <li>Osiloskop (jika tersedia)</li>
  <li>Breadboard dan kabel jumper</li>
</ul>
<h3>Langkah Kerja</h3>
<ol>
  <li>Rangkai sesuai skema: Transformator → Dioda → Resistor beban → kembali ke transformator.</li>
  <li>Ukur tegangan AC pada input (sekunder transformator). Catat nilainya.</li>
  <li>Ukur tegangan DC pada output (tanpa kapasitor). Catat nilainya.</li>
  <li>Pasang kapasitor filter paralel dengan resistor beban.</li>
  <li>Ukur kembali tegangan DC output (dengan kapasitor). Catat nilainya.</li>
  <li>Amati bentuk gelombang dengan osiloskop (jika tersedia).</li>
</ol>
<h3>Tabel Data Pengukuran</h3>
<table border="1" cellpadding="8" style="width:100%;border-collapse:collapse;">
  <tr><th>Kondisi</th><th>Tegangan Terukur</th><th>Tegangan Teori</th></tr>
  <tr><td>Input AC (Vrms)</td><td>........V</td><td>12V</td></tr>
  <tr><td>Output DC tanpa filter</td><td>........V</td><td>~5,4V</td></tr>
  <tr><td>Output DC dengan filter</td><td>........V</td><td>~16V</td></tr>
</table>
<h3>Pertanyaan Analisis</h3>
<ol>
  <li>Mengapa tegangan DC output lebih kecil dari tegangan AC input?</li>
  <li>Apa pengaruh kapasitor filter terhadap tegangan output?</li>
  <li>Apa yang terjadi jika dioda dipasang terbalik?</li>
</ol>
    '
  ],

  [
    'topik' => 'dioda', 'tipe' => 'evaluasi', 'urutan_default' => 5,
    'judul' => 'Evaluasi: Pemahaman Konsep Dioda',
    'isi'   => json_encode([
      ['soal' => 'Dioda silikon mulai mengalirkan arus ketika tegangan maju mencapai...', 'opsi' => ['A' => '0,3V', 'B' => '0,7V', 'C' => '1,2V', 'D' => '5V'], 'kunci' => 'B'],
      ['soal' => 'Ketika dioda dipasang reverse bias, yang terjadi adalah...', 'opsi' => ['A' => 'Arus besar mengalir', 'B' => 'Dioda menjadi panas', 'C' => 'Hampir tidak ada arus yang mengalir', 'D' => 'Tegangan menjadi nol'], 'kunci' => 'C'],
      ['soal' => 'Pada pengujian dioda dengan multimeter, dioda dinyatakan baik jika...', 'opsi' => ['A' => 'Menunjukkan 0 pada kedua arah', 'B' => 'Menunjukkan OL pada kedua arah', 'C' => 'Forward bias ~0,7V dan reverse bias OL', 'D' => 'Forward bias OL dan reverse bias ~0,7V'], 'kunci' => 'C'],
    ])
  ],

  [
    'topik' => 'dioda', 'tipe' => 'tantangan', 'urutan_default' => 6,
    'judul' => 'Tantangan: Analisis Bridge Rectifier',
    'isi'   => '
<h3>Tantangan untuk Practice-Oriented Learner</h3>
<p>Kamu sudah memahami penyearah setengah gelombang. Sekarang tantangannya:</p>
<h3>Tugas</h3>
<ol>
  <li>Rancang rangkaian penyearah <strong>gelombang penuh</strong> menggunakan 4 dioda (bridge rectifier).</li>
  <li>Hitung tegangan output DC yang dihasilkan jika input AC adalah 12V (RMS).</li>
  <li>Bandingkan efisiensi penyearah setengah gelombang vs gelombang penuh.</li>
  <li><strong>Bonus:</strong> Apa yang terjadi jika salah satu dioda pada bridge rectifier putus? Jelaskan dan gambarkan bentuk gelombang outputnya.</li>
</ol>
<h3>Kriteria Jawaban Baik</h3>
<ul>
  <li>Skema rangkaian digambar dengan benar</li>
  <li>Perhitungan tegangan output disertai rumus</li>
  <li>Analisis kondisi dioda putus menunjukkan pemahaman mendalam</li>
</ul>
    '
  ],

  // ── TRANSISTOR ─────────────────────────────────────────────────

  [
    'topik' => 'transistor', 'tipe' => 'teori', 'urutan_default' => 1,
    'judul' => 'Pengertian dan Struktur Transistor BJT',
    'isi'   => '
<h3>Apa itu Transistor?</h3>
<p>Transistor adalah komponen semikonduktor tiga terminal yang dapat digunakan sebagai penguat sinyal atau sebagai saklar elektronik. Jenis yang paling umum dipelajari di SMK adalah <strong>BJT (Bipolar Junction Transistor)</strong>.</p>
<h3>Terminal Transistor BJT</h3>
<ul>
  <li><strong>Basis (B)</strong> — terminal pengendali, arus kecil di sini mengontrol arus besar</li>
  <li><strong>Kolektor (C)</strong> — terminal output, arus besar mengalir di sini</li>
  <li><strong>Emitor (E)</strong> — terminal referensi, arus gabungan keluar dari sini</li>
</ul>
<h3>Tipe NPN dan PNP</h3>
<p><strong>NPN:</strong> Arus mengalir dari Kolektor ke Emitor ketika ada arus basis positif. Ini yang paling umum digunakan.<br>
<strong>PNP:</strong> Kebalikan dari NPN, arus mengalir dari Emitor ke Kolektor.</p>
<h3>Persamaan Dasar</h3>
<p>IC = β × IB (arus kolektor = penguatan arus × arus basis)<br>
IE = IC + IB (arus emitor = jumlah arus kolektor dan basis)</p>
    '
  ],

  [
    'topik' => 'transistor', 'tipe' => 'teori', 'urutan_default' => 2,
    'judul' => 'Daerah Kerja Transistor',
    'isi'   => '
<h3>Tiga Daerah Kerja Transistor</h3>
<table border="1" cellpadding="8" style="width:100%;border-collapse:collapse;">
  <tr><th>Daerah</th><th>Kondisi</th><th>Kegunaan</th></tr>
  <tr><td><strong>Cut-off</strong></td><td>IB = 0, IC ≈ 0, VCE ≈ VCC</td><td>Saklar terbuka (OFF)</td></tr>
  <tr><td><strong>Aktif</strong></td><td>IB kecil, IC = β×IB</td><td>Penguat sinyal</td></tr>
  <tr><td><strong>Saturasi</strong></td><td>IB besar, VCE ≈ 0V</td><td>Saklar tertutup (ON)</td></tr>
</table>
<h3>Transistor sebagai Saklar</h3>
<p>Untuk fungsi saklar, transistor dioperasikan antara daerah <strong>cut-off</strong> (OFF) dan <strong>saturasi</strong> (ON). Ketika IB = 0, transistor OFF. Ketika IB cukup besar, transistor ON dan VCE mendekati 0V.</p>
<h3>Transistor sebagai Penguat</h3>
<p>Untuk fungsi penguat, transistor bekerja di daerah <strong>aktif</strong>. Titik kerja (Q-point) ditentukan oleh rangkaian bias agar sinyal AC tidak terpotong.</p>
    '
  ],

  [
    'topik' => 'transistor', 'tipe' => 'langkah', 'urutan_default' => 3,
    'judul' => 'Langkah Kerja: Transistor sebagai Saklar LED',
    'isi'   => '
<h3>Tujuan</h3>
<p>Membuktikan fungsi transistor NPN sebagai saklar untuk mengendalikan LED.</p>
<h3>Alat dan Bahan</h3>
<ul>
  <li>Transistor NPN BC547 (atau 2N2222)</li>
  <li>LED merah</li>
  <li>Resistor basis: 10 kΩ</li>
  <li>Resistor LED: 330 Ω</li>
  <li>Catu daya 5V</li>
  <li>Breadboard dan kabel jumper</li>
</ul>
<h3>Langkah Kerja</h3>
<ol>
  <li>Rangkai transistor: Kolektor → Resistor LED → LED → VCC (5V).</li>
  <li>Emitor transistor dihubungkan ke GND.</li>
  <li>Basis transistor dihubungkan ke resistor 10kΩ.</li>
  <li><strong>Kondisi OFF:</strong> Ujung lain resistor basis ke GND. Amati LED → harus mati.</li>
  <li>Ukur VCE → catat nilainya (seharusnya mendekati VCC = 5V).</li>
  <li><strong>Kondisi ON:</strong> Pindahkan ujung resistor basis ke VCC (5V). Amati LED → harus menyala.</li>
  <li>Ukur VCE → catat nilainya (seharusnya mendekati 0V — saturasi).</li>
</ol>
<h3>Tabel Data</h3>
<table border="1" cellpadding="8" style="width:100%;border-collapse:collapse;">
  <tr><th>Kondisi Basis</th><th>Status LED</th><th>VCE Terukur</th><th>Daerah Kerja</th></tr>
  <tr><td>Ke GND (0V)</td><td>.........</td><td>........V</td><td>.........</td></tr>
  <tr><td>Ke VCC (5V)</td><td>.........</td><td>........V</td><td>.........</td></tr>
</table>
    '
  ],

  [
    'topik' => 'transistor', 'tipe' => 'jobsheet', 'urutan_default' => 4,
    'judul' => 'Jobsheet: Rangkaian Transistor sebagai Penguat Common Emitor',
    'isi'   => '
<h3>Tujuan Pembelajaran</h3>
<ol>
  <li>Siswa dapat merangkai penguat common emitor.</li>
  <li>Siswa dapat mengukur penguatan tegangan (voltage gain) rangkaian.</li>
  <li>Siswa dapat menentukan titik kerja (Q-point) transistor.</li>
</ol>
<h3>Dasar Teori Singkat</h3>
<p>Penguat Common Emitor adalah konfigurasi penguat transistor di mana terminal emitor menjadi terminal bersama antara input dan output. Penguatan tegangan: <strong>Av = -RC / re</strong>, tanda minus menunjukkan pembalikan fasa 180°.</p>
<h3>Alat dan Bahan</h3>
<ul>
  <li>Transistor NPN BC547</li>
  <li>R1 = 33 kΩ, R2 = 10 kΩ (pembagi tegangan bias)</li>
  <li>RC = 2,2 kΩ (resistor kolektor)</li>
  <li>RE = 470 Ω (resistor emitor)</li>
  <li>CE = 100µF (kapasitor bypass emitor)</li>
  <li>Cin = Cout = 10µF (kapasitor kopling)</li>
  <li>Catu daya 12V, Function generator, Osiloskop</li>
</ul>
<h3>Langkah Kerja</h3>
<ol>
  <li>Rangkai sesuai skema penguat common emitor dengan bias pembagi tegangan.</li>
  <li>Ukur VB, VC, VE dengan multimeter (DC). Catat untuk menentukan Q-point.</li>
  <li>Berikan sinyal input AC 1 kHz, 50mV (peak-to-peak) dari function generator.</li>
  <li>Ukur tegangan output dengan osiloskop.</li>
  <li>Hitung penguatan tegangan: Av = Vout / Vin.</li>
  <li>Amati apakah terjadi pembalikan fasa antara input dan output.</li>
</ol>
<h3>Tabel Data</h3>
<table border="1" cellpadding="8" style="width:100%;border-collapse:collapse;">
  <tr><th>Parameter</th><th>Nilai Terukur</th></tr>
  <tr><td>VB (tegangan basis)</td><td>........V</td></tr>
  <tr><td>VC (tegangan kolektor)</td><td>........V</td></tr>
  <tr><td>VE (tegangan emitor)</td><td>........V</td></tr>
  <tr><td>Vin (tegangan input AC)</td><td>........mV</td></tr>
  <tr><td>Vout (tegangan output AC)</td><td>........mV</td></tr>
  <tr><td>Penguatan Av = Vout/Vin</td><td>........</td></tr>
</table>
    '
  ],

  [
    'topik' => 'transistor', 'tipe' => 'evaluasi', 'urutan_default' => 5,
    'judul' => 'Evaluasi: Pemahaman Transistor',
    'isi'   => json_encode([
      ['soal' => 'Transistor NPN bekerja di daerah saturasi ketika...', 'opsi' => ['A' => 'IB = 0 dan VCE = VCC', 'B' => 'IB cukup besar dan VCE ≈ 0V', 'C' => 'IC = 0 dan IB sangat besar', 'D' => 'VBE = 0V'], 'kunci' => 'B'],
      ['soal' => 'Pada konfigurasi common emitor, sinyal output terhadap input mengalami...', 'opsi' => ['A' => 'Penguatan tanpa pembalikan fasa', 'B' => 'Pembalikan fasa 90°', 'C' => 'Pembalikan fasa 180°', 'D' => 'Tidak ada perubahan'], 'kunci' => 'C'],
      ['soal' => 'Jika β transistor = 100 dan IB = 20µA, maka IC adalah...', 'opsi' => ['A' => '0,2 mA', 'B' => '2 mA', 'C' => '20 mA', 'D' => '200 mA'], 'kunci' => 'B'],
    ])
  ],

  [
    'topik' => 'transistor', 'tipe' => 'tantangan', 'urutan_default' => 6,
    'judul' => 'Tantangan: Desain Rangkaian Penguat',
    'isi'   => '
<h3>Tantangan untuk Practice-Oriented Learner</h3>
<p>Rancang sebuah rangkaian penguat common emitor yang memenuhi spesifikasi berikut:</p>
<ul>
  <li>Tegangan supply: VCC = 12V</li>
  <li>Transistor: BC547 (β = 200)</li>
  <li>Titik kerja: IC = 2mA, VCE = 6V</li>
  <li>Penguatan tegangan: Av ≥ 50</li>
</ul>
<h3>Yang Harus Dikerjakan</h3>
<ol>
  <li>Tentukan nilai RC dan RE yang sesuai.</li>
  <li>Tentukan nilai R1 dan R2 untuk bias pembagi tegangan.</li>
  <li>Gambar skema lengkap rangkaian.</li>
  <li>Verifikasi perhitungan dengan simulasi (Proteus/CircuitJS) jika memungkinkan.</li>
</ol>
    '
  ],

  // ── CATU DAYA ──────────────────────────────────────────────────

  [
    'topik' => 'catu_daya', 'tipe' => 'teori', 'urutan_default' => 1,
    'judul' => 'Blok Diagram dan Prinsip Kerja Catu Daya',
    'isi'   => '
<h3>Apa itu Catu Daya?</h3>
<p>Catu daya (power supply) adalah rangkaian yang mengubah tegangan AC dari PLN menjadi tegangan DC yang stabil untuk mencatu perangkat elektronik.</p>
<h3>Blok Diagram Catu Daya</h3>
<p><strong>Transformator → Penyearah → Filter → Regulator → Beban</strong></p>
<ul>
  <li><strong>Transformator:</strong> Menurunkan tegangan AC 220V menjadi tegangan AC yang lebih rendah (misal 12V)</li>
  <li><strong>Penyearah:</strong> Mengubah AC menjadi DC pulsating menggunakan dioda</li>
  <li><strong>Filter:</strong> Kapasitor meratakan ripple tegangan DC</li>
  <li><strong>Regulator:</strong> Menstabilkan tegangan output meski beban berubah</li>
</ul>
<h3>Parameter Penting Catu Daya</h3>
<ul>
  <li><strong>Tegangan output:</strong> Nilai DC yang dihasilkan</li>
  <li><strong>Arus maksimum:</strong> Kemampuan mencatu arus</li>
  <li><strong>Ripple voltage:</strong> Sisa tegangan AC pada output DC</li>
  <li><strong>Regulasi tegangan:</strong> Stabilitas tegangan saat beban berubah</li>
</ul>
    '
  ],

  [
    'topik' => 'catu_daya', 'tipe' => 'teori', 'urutan_default' => 2,
    'judul' => 'IC Regulator Tegangan — Seri 78xx',
    'isi'   => '
<h3>IC Regulator 78xx</h3>
<p>IC regulator seri 78xx adalah regulator tegangan tetap (fixed voltage regulator) yang sangat populer karena mudah digunakan. Hanya membutuhkan 2 kapasitor eksternal.</p>
<h3>Kode dan Tegangan Output</h3>
<table border="1" cellpadding="8" style="width:100%;border-collapse:collapse;">
  <tr><th>Kode IC</th><th>Tegangan Output</th><th>Arus Maks</th></tr>
  <tr><td>7805</td><td>+5V</td><td>1A</td></tr>
  <tr><td>7809</td><td>+9V</td><td>1A</td></tr>
  <tr><td>7812</td><td>+12V</td><td>1A</td></tr>
  <tr><td>7815</td><td>+15V</td><td>1A</td></tr>
</table>
<h3>Syarat Penggunaan</h3>
<ul>
  <li>Tegangan input harus <strong>minimal 2-3V lebih tinggi</strong> dari tegangan output</li>
  <li>Selisih tegangan input-output diubah menjadi <strong>panas</strong> — perlu heatsink jika arus besar</li>
  <li>Disipasi daya: P = (Vin - Vout) × Iout</li>
</ul>
<h3>Rangkaian Dasar</h3>
<p>Cin (0,1µF) dipasang antara input IC dan GND. Cout (0,1µF) dipasang antara output IC dan GND. Kedua kapasitor menjaga kestabilan regulator.</p>
    '
  ],

  [
    'topik' => 'catu_daya', 'tipe' => 'langkah', 'urutan_default' => 3,
    'judul' => 'Langkah Kerja: Merangkai Catu Daya Terregulasi 5V',
    'isi'   => '
<h3>Tujuan</h3>
<p>Merangkai catu daya DC terregulasi 5V menggunakan IC 7805 dan mengukur karakteristiknya.</p>
<h3>Alat dan Bahan</h3>
<ul>
  <li>Transformator 9V CT (step-down)</li>
  <li>4 × Dioda 1N4001 (bridge rectifier)</li>
  <li>Kapasitor filter 2200µF / 25V</li>
  <li>IC Regulator 7805</li>
  <li>Kapasitor 0,1µF (2 buah)</li>
  <li>LED + resistor 330Ω (indikator)</li>
  <li>Multimeter, PCB/breadboard</li>
</ul>
<h3>Langkah Kerja</h3>
<ol>
  <li>Rangkai bridge rectifier dari 4 dioda 1N4001.</li>
  <li>Pasang kapasitor filter 2200µF antara output bridge dan GND.</li>
  <li>Ukur tegangan DC setelah filter (sebelum regulator). Catat nilainya.</li>
  <li>Sambungkan output filter ke pin INPUT IC 7805.</li>
  <li>PIN GND IC ke GND rangkaian.</li>
  <li>Pasang kapasitor 0,1µF di input dan output IC ke GND.</li>
  <li>Ukur tegangan output IC 7805. Catat nilainya.</li>
  <li>Pasang LED indikator pada output.</li>
  <li>Hubungkan beban resistor 100Ω dan ukur ulang tegangan output — apakah stabil?</li>
</ol>
<h3>Tabel Data</h3>
<table border="1" cellpadding="8" style="width:100%;border-collapse:collapse;">
  <tr><th>Titik Pengukuran</th><th>Tegangan Terukur</th></tr>
  <tr><td>Output trafo (AC)</td><td>........V</td></tr>
  <tr><td>Setelah bridge (DC tanpa filter)</td><td>........V</td></tr>
  <tr><td>Setelah filter kapasitor</td><td>........V</td></tr>
  <tr><td>Output 7805 (tanpa beban)</td><td>........V</td></tr>
  <tr><td>Output 7805 (dengan beban 100Ω)</td><td>........V</td></tr>
</table>
    '
  ],

  [
    'topik' => 'catu_daya', 'tipe' => 'jobsheet', 'urutan_default' => 4,
    'judul' => 'Jobsheet: Perancangan Catu Daya Ganda ±12V',
    'isi'   => '
<h3>Tujuan Pembelajaran</h3>
<ol>
  <li>Siswa dapat merangkai catu daya simetris ±12V.</li>
  <li>Siswa dapat menggunakan IC 7812 dan 7912 sebagai regulator.</li>
  <li>Siswa dapat menganalisis perbedaan catu daya tunggal dan simetris.</li>
</ol>
<h3>Dasar Teori</h3>
<p>Catu daya ganda (dual power supply) menghasilkan tegangan positif dan negatif terhadap GND. Dibutuhkan pada rangkaian op-amp dan penguat audio. Menggunakan transformator CT (center tap) dan dua regulator: 7812 (+12V) dan 7912 (-12V).</p>
<h3>Alat dan Bahan</h3>
<ul>
  <li>Transformator 15V CT - 15V</li>
  <li>4 × Dioda 1N4007</li>
  <li>Kapasitor 2200µF / 35V (2 buah)</li>
  <li>IC 7812 dan IC 7912</li>
  <li>Kapasitor 0,1µF (4 buah)</li>
  <li>Multimeter</li>
</ul>
<h3>Langkah Kerja</h3>
<ol>
  <li>Identifikasi terminal CT transformator.</li>
  <li>Rangkai penyearah gelombang penuh untuk jalur positif dan negatif.</li>
  <li>Pasang kapasitor filter pada masing-masing jalur.</li>
  <li>Sambungkan IC 7812 untuk jalur +12V dan IC 7912 untuk jalur -12V.</li>
  <li>Ukur tegangan output +12V dan -12V terhadap GND.</li>
  <li>Verifikasi simestri tegangan output.</li>
</ol>
    '
  ],

  [
    'topik' => 'catu_daya', 'tipe' => 'evaluasi', 'urutan_default' => 5,
    'judul' => 'Evaluasi: Pemahaman Catu Daya',
    'isi'   => json_encode([
      ['soal' => 'IC 7805 membutuhkan tegangan input minimal...', 'opsi' => ['A' => '3V', 'B' => '5V', 'C' => '7V', 'D' => '12V'], 'kunci' => 'C'],
      ['soal' => 'Fungsi kapasitor elektrolit besar (2200µF) pada catu daya adalah...', 'opsi' => ['A' => 'Mengubah AC menjadi DC', 'B' => 'Menstabilkan tegangan output', 'C' => 'Menyaring ripple tegangan', 'D' => 'Melindungi IC regulator'], 'kunci' => 'C'],
      ['soal' => 'Jika tegangan input IC 7805 adalah 9V dan arus beban 500mA, daya yang diubah menjadi panas adalah...', 'opsi' => ['A' => '1W', 'B' => '2W', 'C' => '4,5W', 'D' => '9W'], 'kunci' => 'B'],
    ])
  ],

  [
    'topik' => 'catu_daya', 'tipe' => 'tantangan', 'urutan_default' => 6,
    'judul' => 'Tantangan: Desain Catu Daya Variable',
    'isi'   => '
<h3>Tantangan untuk Practice-Oriented Learner</h3>
<p>Rancang catu daya variable 1,25V - 15V menggunakan IC LM317.</p>
<h3>Yang Harus Dikerjakan</h3>
<ol>
  <li>Pelajari datasheet IC LM317 secara mandiri.</li>
  <li>Hitung nilai R1 dan R2 (atau potensiometer) untuk rentang 1,25V - 15V.</li>
  <li>Rancang skema lengkap termasuk proteksi arus lebih.</li>
  <li>Simulasikan rangkaian dan buktikan tegangan dapat diatur.</li>
</ol>
<p><em>Hint: Vout = 1,25 × (1 + R2/R1)</em></p>
    '
  ],
];

// Insert ke database
$stmt = $pdo->prepare("
    INSERT INTO `content` (topik, tipe, judul, isi, urutan_default, aktif)
    VALUES (?, ?, ?, ?, ?, 1)
");

$count = 0;
foreach ($konten as $item) {
    $stmt->execute([
        $item['topik'],
        $item['tipe'],
        $item['judul'],
        $item['isi'],
        $item['urutan_default'],
    ]);
    $count++;
    echo "✓ [{$item['topik']}] {$item['judul']}<br>";
}

echo "<br><strong>Total: $count konten berhasil dimasukkan.</strong>";
?>
