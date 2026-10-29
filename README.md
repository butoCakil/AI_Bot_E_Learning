# AI Bot E-Learning

## Konfigurasi server (tidak dilacak git)

Bagian ini mencatat pengaturan yang **tidak tersimpan di repositori** tetapi wajib ada saat aplikasi dipasang ulang.

### 1. Zona waktu WIB — `config/config.php`

`config/config.php` tidak dilacak git (berisi kredensial). Server & MySQL berjalan di UTC; aplikasi memakai WIB. Pastikan dua hal ini ada:

~~php
// Sebelum function db()
date_default_timezone_set('Asia/Jakarta');

// Di dalam opsi new PDO(...)
PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+07:00'",
~~

Kolom waktu bertipe `TIMESTAMP`, jadi data lama otomatis tampil dalam WIB.

### 2. Migrasi database (dijalankan manual)

~~sql
CREATE TABLE tabel_input_jawaban (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  content_id INT NOT NULL,
  topik VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  jawaban JSON NOT NULL,
  lengkap TINYINT(1) NOT NULL DEFAULT 0,
  nilai DECIMAL(5,2) DEFAULT NULL,
  dinilai_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY unique_tabel (user_id, content_id),
  CONSTRAINT tabel_input_jawaban_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE feedback_guru (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  content_id INT NOT NULL,
  topik VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  skala ENUM('bintang','kesesuaian') COLLATE utf8mb4_unicode_ci NOT NULL,
  predikat TINYINT NOT NULL,
  komentar TEXT COLLATE utf8mb4_unicode_ci NULL,
  minta_revisi TINYINT(1) NOT NULL DEFAULT 0,
  revisi_ke INT NOT NULL DEFAULT 0,
  guru_id INT NULL,
  dinilai_at TIMESTAMP NULL DEFAULT NULL,
  revisi_dikirim_at TIMESTAMP NULL DEFAULT NULL,
  dibaca_at TIMESTAMP NULL DEFAULT NULL,
  wa_dikirim_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY unique_feedback (user_id, content_id),
  KEY idx_wa (wa_dikirim_at),
  CONSTRAINT feedback_guru_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE activity_log MODIFY tipe
  ENUM('buka_materi','selesai_materi','jawab_quiz','tanya_bot','login','pretest','upload_jobsheet','isi_tabel')
  COLLATE utf8mb4_unicode_ci DEFAULT NULL;
~~

### 3. Nginx — folder upload

Di blok `server` aplikasi (setelah `location ^~ /_dev`):

~~nginx
# Folder upload: hanya file statis, PHP tidak pernah dieksekusi di sini
location ^~ /uploads/ {
    location ~ \.php$ { deny all; }
    # Kiriman siswa hanya lewat api/file_kiriman.php (cek login)
    location ^~ /uploads/jobsheet/ { deny all; return 404; }
}
~~

Lalu jalankan `sudo nginx -t && sudo systemctl reload nginx`.

### 4. Cron — ringkasan feedback WA harian

Di crontab user aplikasi (`crontab -e`):

~~
*/5 * * * * /usr/bin/php /var/www/html/aibotlms/cron/ringkasan_wa.php >> $HOME/log/ringkasan_wa.log 2>&1
~~

Buat folder log dulu: `mkdir -p $HOME/log`. Pengiriman baru berjalan setelah diaktifkan di Dashboard guru → tab **WA Bot & AI**. Uji tanpa mengirim: `php cron/ringkasan_wa.php --uji`.

### 5. Ekstensi PHP

Wajib aktif: `dom`, `xml`, `mbstring`, `json` (cek dengan `php -m`).

### 6. Penilaian (ringkas)

- Nilai topik = rata-rata komponen yang sudah dinilai (evaluasi %, nilai tugas upload, tabel: Sesuai 85 / Hampir sesuai 70 / Belum sesuai 55).
- Nilai akhir = rata-rata nilai topik. **KKM 70.**
- Post-test tidak dicampur ke nilai akhir — dipakai untuk N-Gain (Hake, 1998).
