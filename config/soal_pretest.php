<?php
// Kunci jawaban 12 soal pengetahuan
// Urutan: D0, D1, D2, D3, T1, T2, T3, T4, C0, C1, C2, C3
define('KUNCI_JAWABAN', ['B','B','A','C','B','B','B','B','B','C','B','B']);

// Data soal lengkap
define('SOAL_PENGETAHUAN', [
    // DIODA
    [
        'kode' => 'D0', 'topik' => 'dioda', 'level_kognitif' => 'C1',
        'soal' => 'Simbol dioda pada rangkaian elektronika ditunjukkan oleh arah arus yang mengalir dari...',
        'opsi' => ['A' => 'Katoda ke anoda', 'B' => 'Anoda ke katoda', 'C' => 'Emitor ke kolektor', 'D' => 'Source ke drain']
    ],
    [
        'kode' => 'D1', 'topik' => 'dioda', 'level_kognitif' => 'C2',
        'soal' => 'Dioda akan mengalirkan arus listrik ketika...',
        'opsi' => ['A' => 'Anoda dihubungkan ke kutub negatif dan katoda ke kutub positif', 'B' => 'Anoda dihubungkan ke kutub positif dan katoda ke kutub negatif', 'C' => 'Tegangan pada anoda dan katoda sama besar', 'D' => 'Dioda dipasang seri dengan resistor']
    ],
    [
        'kode' => 'D2', 'topik' => 'dioda', 'level_kognitif' => 'C3',
        'soal' => 'Sebuah dioda silikon dipasang seri dengan resistor 1 kΩ dan sumber tegangan 5,7V. Jika tegangan maju dioda adalah 0,7V, maka arus yang mengalir adalah...',
        'opsi' => ['A' => '5 mA', 'B' => '4,3 mA', 'C' => '6,4 mA', 'D' => '0,7 mA']
    ],
    [
        'kode' => 'D3', 'topik' => 'dioda', 'level_kognitif' => 'C4',
        'soal' => 'Pada rangkaian bridge rectifier, jika salah satu dioda putus (open circuit), maka yang terjadi adalah...',
        'opsi' => ['A' => 'Tidak ada arus sama sekali', 'B' => 'Tegangan output menjadi nol', 'C' => 'Rangkaian berubah menjadi penyearah setengah gelombang', 'D' => 'Tegangan output meningkat dua kali lipat']
    ],
    // TRANSISTOR
    [
        'kode' => 'T1', 'topik' => 'transistor', 'level_kognitif' => 'C1',
        'soal' => 'Transistor BJT tipe NPN memiliki tiga terminal yaitu...',
        'opsi' => ['A' => 'Source, Gate, Drain', 'B' => 'Basis, Kolektor, Emitor', 'C' => 'Anoda, Katoda, Gate', 'D' => 'Input, Output, Ground']
    ],
    [
        'kode' => 'T2', 'topik' => 'transistor', 'level_kognitif' => 'C2',
        'soal' => 'Transistor bekerja sebagai saklar (switch) ketika berada di daerah...',
        'opsi' => ['A' => 'Aktif (active region)', 'B' => 'Cut-off dan saturasi', 'C' => 'Linear', 'D' => 'Breakdown']
    ],
    [
        'kode' => 'T3', 'topik' => 'transistor', 'level_kognitif' => 'C3',
        'soal' => 'Agar LED menyala pada rangkaian transistor NPN sebagai saklar, kondisi transistor yang diperlukan adalah...',
        'opsi' => ['A' => 'Transistor di daerah cut-off, VCE ≈ VCC', 'B' => 'Transistor di daerah saturasi, VCE ≈ 0V', 'C' => 'Transistor di daerah aktif, IB = 0', 'D' => 'Transistor di daerah breakdown, VCE > VCEO']
    ],
    [
        'kode' => 'T4', 'topik' => 'transistor', 'level_kognitif' => 'C4',
        'soal' => 'Pada rangkaian penguat common emitor, jika nilai resistor basis (RB) diperbesar, maka yang terjadi pada titik kerja (Q-point) adalah...',
        'opsi' => ['A' => 'Arus basis naik, transistor mendekati saturasi', 'B' => 'Arus basis turun, titik kerja bergeser ke arah cut-off', 'C' => 'Tegangan VCE naik, arus kolektor tetap', 'D' => 'Transistor keluar dari daerah aktif menuju breakdown']
    ],
    // CATU DAYA
    [
        'kode' => 'C0', 'topik' => 'catu_daya', 'level_kognitif' => 'C1',
        'soal' => 'Urutan blok rangkaian catu daya yang benar dari input ke output adalah...',
        'opsi' => ['A' => 'Filter → Transformator → Penyearah → Regulator', 'B' => 'Transformator → Penyearah → Filter → Regulator', 'C' => 'Penyearah → Transformator → Regulator → Filter', 'D' => 'Regulator → Filter → Penyearah → Transformator']
    ],
    [
        'kode' => 'C1', 'topik' => 'catu_daya', 'level_kognitif' => 'C2',
        'soal' => 'Fungsi kapasitor filter dalam rangkaian catu daya adalah...',
        'opsi' => ['A' => 'Menaikkan tegangan DC keluaran', 'B' => 'Mengubah tegangan AC menjadi DC', 'C' => 'Meratakan tegangan DC yang masih beriak (ripple)', 'D' => 'Melindungi dioda dari tegangan balik']
    ],
    [
        'kode' => 'C2', 'topik' => 'catu_daya', 'level_kognitif' => 'C3',
        'soal' => 'Sebuah transformator step-down menghasilkan tegangan sekunder 12V AC (RMS). Tegangan puncak (peak) dari sinyal AC tersebut mendekati...',
        'opsi' => ['A' => '12V', 'B' => '17V', 'C' => '24V', 'D' => '8,5V']
    ],
    [
        'kode' => 'C3', 'topik' => 'catu_daya', 'level_kognitif' => 'C4',
        'soal' => 'Rangkaian catu daya menggunakan IC regulator 7805 dengan tegangan input 9V dan arus beban 500mA. Yang perlu diperhatikan adalah...',
        'opsi' => ['A' => 'Tegangan output akan menjadi 9V', 'B' => 'IC regulator menghasilkan panas 2W dan perlu heatsink', 'C' => 'Kapasitor filter tidak diperlukan', 'D' => 'Arus output maksimal 1A']
    ],
]);

// Item SJT
define('SOAL_SJT', [
    ['kode' => 'SJT1', 'soal' => 'Besok kamu mulai belajar topik baru: cara kerja Dioda. Kamu lebih suka kalau gurumu...', 'opsi' => ['A' => 'Menjelaskan dari awal secara urut — pengertian dulu, lalu simbol, lalu cara kerja, satu per satu', 'B' => 'Menjelaskan gambaran besar cara kerja dioda dulu, baru nanti detail-detailnya kamu pelajari sendiri', 'C' => 'Langsung kasih rangkaian yang menggunakan dioda, penjelasan teori belakangan kalau kamu butuh']],
    ['kode' => 'SJT2', 'soal' => 'Kamu sedang belajar rangkaian Catu Daya tapi bingung kenapa dioda dipasang seperti itu. Yang kamu lakukan adalah...', 'opsi' => ['A' => 'Minta penjelasan ulang dari awal, pelan-pelan, sampai benar-benar paham setiap langkahnya', 'B' => 'Baca ulang teori prinsip kerja dioda sampai konsepnya masuk akal di kepala kamu', 'C' => 'Coba langsung variasi posisi diodanya di rangkaian, lihat apa yang berubah']],
    ['kode' => 'SJT3', 'soal' => 'Kamu dapat tugas menganalisis transistor sebagai saklar. Kamu mulai dengan...', 'opsi' => ['A' => 'Buka jobsheet, ikuti langkah-langkahnya satu per satu dari nomor 1', 'B' => 'Baca dulu teori prinsip kerja transistor sebagai saklar, baru mulai mengerjakan', 'C' => 'Langsung pasang rangkaiannya, cek teori hanya kalau ada yang tidak sesuai']],
    ['kode' => 'SJT4', 'soal' => 'Kamu merasa paling paham suatu materi elektronika kalau...', 'opsi' => ['A' => 'Ada contoh yang dikerjakan bersama guru langkah demi langkah sebelum kamu mencoba sendiri', 'B' => 'Ada penjelasan konsep yang lengkap dan bisa kamu baca ulang kapan saja', 'C' => 'Langsung praktik merangkai dan mengukur sendiri, paham dari pengalaman langsung']],
    ['kode' => 'SJT5', 'soal' => 'Kamu diminta belajar mandiri tentang Transistor Penguat sebelum pertemuan berikutnya. Kamu akan...', 'opsi' => ['A' => 'Ikuti urutan materi yang sudah disediakan, dari bagian pertama sampai selesai, tidak melompat', 'B' => 'Baca overview-nya dulu untuk dapat gambaran besar, baru pilih bagian yang paling ingin kamu dalami', 'C' => 'Langsung cari contoh rangkaian transistor penguat, coba pahami dari situ']],
    ['kode' => 'SJT6', 'soal' => 'Kamu menemukan soal analisis rangkaian yang sulit saat belajar. Yang kamu lakukan adalah...', 'opsi' => ['A' => 'Kembali ke materi dasar, baca ulang dari awal sampai menemukan bagian yang kamu lewati', 'B' => 'Cari dulu prinsip atau rumus yang relevan, pahami logikanya, baru coba soal itu lagi', 'C' => 'Coba kerjakan soal itu dengan berbagai cara sampai ketemu jawaban yang masuk akal']],
    ['kode' => 'SJT7', 'soal' => 'Format materi yang paling membantu kamu belajar adalah...', 'opsi' => ['A' => 'Panduan langkah demi langkah yang lengkap dan urut, disertai contoh di setiap langkah', 'B' => 'Penjelasan konsep yang mendalam disertai diagram dan hubungan antar konsep', 'C' => 'Proyek atau tantangan nyata yang harus diselesaikan, materi teori sebagai referensi saja']],
    ['kode' => 'SJT8', 'soal' => 'Kalau kamu jujur menilai diri sendiri, kamu belajar paling efektif ketika...', 'opsi' => ['A' => 'Ada panduan jelas yang bisa kamu ikuti — kamu tidak suka kalau harus menebak langkah berikutnya', 'B' => 'Kamu paham mengapa sesuatu bekerja seperti itu — kamu tidak puas hanya hafal tanpa mengerti', 'C' => 'Kamu bisa langsung mencoba dan bereksperimen — membaca terlalu banyak sebelum praktik terasa membuang waktu']],
]);
?>
