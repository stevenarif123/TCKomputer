<?php
/**
 * Recovery Script: Rename .env.bak back to .env
 * Runs independently of the database connection.
 */

$bakPath = __DIR__ . '/.env.bak';
$envPath = __DIR__ . '/.env';

if (file_exists($bakPath)) {
    if (rename($bakPath, $envPath)) {
        echo "SUCCESS: Berhasil mengembalikan .env.bak menjadi .env! Koneksi database Anda seharusnya sudah pulih sekarang. Silakan coba buka halaman utama website Anda.";
    } else {
        echo "ERROR: Gagal mengubah nama file .env.bak menjadi .env. Silakan periksa izin akses file (file permissions) di server Anda.";
    }
} else {
    if (file_exists($envPath)) {
        echo "NOTE: File .env sudah aktif dan .env.bak tidak ditemukan. Jika koneksi database masih bermasalah, silakan periksa kredensial di dalam file .env Anda.";
    } else {
        echo "ERROR: Baik .env maupun .env.bak tidak ditemukan di server. Silakan unggah kembali file .env Anda secara manual.";
    }
}
