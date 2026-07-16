# Dokumentasi REST API TCKomputer v1

Dokumentasi ini ditujukan bagi Developer atau AI Agent untuk berinteraksi dengan data e-commerce TCKomputer secara eksternal.

## 1. Lokasi & Pengambilan API Key
Untuk menjaga keamanan data, setiap request ke API memerlukan autentikasi. Kunci API (API Key) disimpan di dalam file konfigurasi lingkungan (Environment File) di root folder proyek:

- **File**: `[root_folder]/.env` (misal: `d:\laragon\www\TCKomputer\.env`)
- **Key**: `API_KEY`
- **Contoh Key**: `tckomputer_api_key_secure_7d2f9a1c8b3e`

Jika Anda ingin mengubah API Key atau menggunakannya, silakan baca langsung dari file tersebut atau tanyakan pada server administrator.

---

## 2. Autentikasi (Authentication)
Semua request ke API wajib menyertakan header HTTP berikut:
```http
Authorization: Bearer <API_KEY>
```
Apabila header tersebut tidak disertakan atau nilai API Key salah, server akan merespon dengan status **401 Unauthorized**:
```json
{
  "status": "error",
  "message": "Unauthorized"
}
```

---

## 3. Format Response Global
Setiap response dari API ini dibungkus dengan format JSON yang konsisten:
- **Sukses**:
  ```json
  {
    "status": "success",
    "data": { ... } // berisi objek atau array data
  }
  ```
- **Gagal**:
  ```json
  {
    "status": "error",
    "message": "Pesan deskripsi kesalahan"
  }
  ```

---

## 4. Rate Limiting (Batasan Request)
API menerapkan Rate Limiting per IP Address untuk menghindari spamming:
- Batas maksimal: **120 requests per menit**.
- Jika melampaui batas, server akan mengirim status **429 Too Many Requests** dengan header `Retry-After`.

---

## 5. Daftar Endpoint API

### A. Produk (Products) - `/api/v1/products.php`
Mengakses data produk e-commerce.

* **Mengambil Daftar Produk (GET)**
  - **Parameter Opsional**:
    - `search` (string): Cari berdasarkan nama, brand, deskripsi, atau SKU.
    - `category_id` (int): Filter berdasarkan ID kategori.
    - `status` (string): Status stok (`ready`, `po`, `habis`).
    - `featured` (int): Filter produk unggulan (`1` atau `0`).
    - `promo` (int): Filter produk promo aktif (`1` atau `0`).
    - `is_active` (int): `1` (aktif, default), `0` (tidak aktif), atau `-1` (semua).
    - `sort` (string): Urutan data (`newest`, `oldest`, `cheapest`, `expensive`, `name_asc`, `name_desc`). Default: `newest`.
    - `page` (int): Halaman ke-berapa (Default: 1).
    - `per_page` (int): Jumlah item per halaman (Batas maks: 100, Default: 20).
  - **Contoh Request**:
    `GET /api/v1/products.php?search=Asus&sort=cheapest&page=1`

* **Mengambil Detail Produk Tunggal (GET)**
  - **Parameter Wajib**: `id` (int)
  - **Contoh Request**:
    `GET /api/v1/products.php?id=12`
  - **Keterangan**: Mengembalikan detail produk lengkap termasuk array daftar gambar tambahan (`images`). Field harga beli (`purchase_price`) disembunyikan demi alasan privasi.

---

### B. Kategori (Categories) - `/api/v1/categories.php`
Mengakses kategori produk.

* **Mengambil Daftar Kategori (GET)**
  - **Parameter Opsional**:
    - `is_active` (int): `1` (aktif, default), `0` (tidak aktif), atau `-1` (semua).
  - **Keterangan**: Mengembalikan semua kategori terdaftar beserta total produk aktif di dalam kategori tersebut (`product_count`).
  - **Contoh Request**:
    `GET /api/v1/categories.php`

* **Mengambil Detail Kategori Tunggal (GET)**
  - **Parameter Wajib**: `id` (int)
  - **Contoh Request**:
    `GET /api/v1/categories.php?id=3`

---

### C. Pesanan (Orders) - `/api/v1/orders.php`
Mengakses dan memperbarui data pesanan (read & write).

* **Mengambil Daftar Pesanan (GET)**
  - **Parameter Opsional**:
    - `status` (string): Status pesanan (`menunggu_konfirmasi`, `diproses`, `siap_diantar`, `dikirim`, `selesai`, `dibatalkan`).
    - `search` (string): Cari kode pesanan, nama pembeli, atau nomor HP pembeli.
    - `page` (int) & `per_page` (int)
  - **Contoh Request**:
    `GET /api/v1/orders.php?status=menunggu_konfirmasi`

* **Mengambil Detail Pesanan Tunggal (GET)**
  - **Parameter Wajib**: `id` (int)
  - **Contoh Request**:
    `GET /api/v1/orders.php?id=45`
  - **Keterangan**: Menampilkan rincian pesanan lengkap dengan array item produk yang dipesan (`items`).

* **Memperbarui Status / Catatan Pesanan (PATCH)**
  - **Parameter Wajib**: `id` (int) lewat URL.
  - **Body (JSON)**:
    ```json
    {
      "order_status": "diproses",
      "admin_notes": "Pesanan divalidasi oleh AI Agent"
    }
    ```
  - **Keterangan**: Mendukung perubahan status pesanan secara bertahap dan pengeditan catatan internal admin (`admin_notes`). Validasi alur transisi status diterapkan di backend agar status tidak melompat sembarangan.

---

### D. Percakapan Live Chat - `/api/v1/chat.php`
Interaksi AI Agent untuk membaca dan menjawab chat pelanggan.

* **Mengambil Daftar Sesi Chat (GET)**
  - **Parameter Opsional**:
    - `status` (string): Status chat (`active` [default], `closed`, atau `""` [semua]).
    - `unread` (int): `1` untuk mengambil hanya chat yang belum dibaca admin/AI.
  - **Contoh Request**:
    `GET /api/v1/chat.php?unread=1`
  - **Struktur Respon**: Setiap objek sesi di dalam array mengandung properti status seperti `unread_admin` (jumlah pesan belum dibaca admin) dan `unread_user` (jumlah pesan belum dibaca pelanggan).

* **Mengambil Log Chat / Isi Pesan (GET)**
  - **Parameter Wajib**: `session_id` (int)
  - **Parameter Opsional**: `after_id` (int) - hanya mengambil pesan setelah ID pesan tertentu (berguna untuk polling real-time).
  - **Contoh Request**:
    `GET /api/v1/chat.php?session_id=8&after_id=142`
  - **Keterangan**: Secara otomatis mereset hitungan belum dibaca admin (`unread_admin`) menjadi `0` begitu dibaca oleh AI.
  - **Struktur Respon**: Objek pesan mengandung properti `"is_read": 1` (jika sudah dibaca penerima) atau `"is_read": 0` (jika belum dibaca/hanya terkirim). Ini mencakup status centang pesan untuk admin maupun pembeli.

* **Membalas Pesan Chat (POST)**
  - **Body (JSON)**:
    ```json
    {
      "session_id": 8,
      "message": "Halo, ada yang bisa kami bantu?"
    }
    ```
  - **Keterangan**: Mengirimkan balasan sebagai 'AI Assistant'. Mengubah hitungan pesan belum dibaca di sisi user (`unread_user`).

---

### E. Manajemen Pengguna (Users) - `/api/v1/users.php`
Membantu AI Agent untuk mendaftarkan akun pelanggan.

* **Memeriksa Status Registrasi HP (GET)**
  - **Parameter Wajib**: `phone` (string)
  - **Contoh Request**:
    `GET /api/v1/users.php?phone=081234567890`
  - **Keterangan**: Memeriksa apakah nomor HP tersebut sudah terdaftar ke akun pelanggan.

* **Mendaftarkan Akun Pelanggan Baru (POST)**
  - **Body (JSON)**:
    ```json
    {
      "username": "budi_gaming",
      "phone": "081234567890",
      "name": "Budi Setiawan",
      "email": "budi@domain.com",
      "address": "Jl. Kakatua No. 25, Makassar",
      "password": "password_aman_123",
      "shipping_area_id": 2,
      "chat_session_id": 8
    }
    ```
  - **Keterangan**: Mendaftarkan pelanggan baru. Jika `chat_session_id` disertakan, sesi chat anonim tersebut akan secara otomatis dikaitkan ke akun baru yang terdaftar ini sehingga admin dapat melacak riwayat chatnya di kemudian hari.

---

### F. Statistik Panel (Stats) - `/api/v1/stats.php`
* **Mengambil Ringkasan Statistik (GET)**
  - **Contoh Request**:
    `GET /api/v1/stats.php`
  - **Keterangan**: Mengembalikan ringkasan cepat untuk dashboard (total produk, pesanan tertunda, pendapatan bersih, jumlah akun user, dan peringatan produk dengan stok tipis).
