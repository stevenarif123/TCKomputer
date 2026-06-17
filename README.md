# Steven IT Shop

Aplikasi e-commerce berbasis PHP native untuk penjualan produk komputer, aksesoris laptop, aksesoris HP, kabel & converter, peripheral, storage, printer & tinta, dan peralatan servis. Dirancang dengan pendekatan mobile-first responsive design untuk pembeli yang datang dari WhatsApp dan media sosial.

## Fitur Utama

- **Browsing Produk** — Pencarian, filter kategori/status, dan sorting (terbaru, termurah, termahal) dengan paginasi
- **Keranjang Belanja** — Session-based cart dengan validasi stok real-time
- **Checkout** — Multiple metode pembayaran (COD, Transfer, Pay on Delivery) dan opsi pengiriman (Self Pickup, Local Delivery, Local Courier)
- **Tracking Pesanan** — Lacak status pesanan menggunakan kode order dan nomor HP
- **Admin Panel** — Full CRUD untuk produk, kategori, pesanan, area pengiriman, banner, dan pengaturan toko
- **Responsive Design** — Mobile-first layout yang optimal di semua ukuran layar (320px ke atas)
- **Keamanan** — CSRF protection, prepared statements (PDO), validasi upload file, output sanitization

## Persyaratan Sistem

| Komponen | Versi Minimum |
|----------|---------------|
| PHP | >= 7.4 |
| MySQL | >= 5.7 |
| Web Server | Apache (dengan mod_rewrite) atau Nginx |

### Ekstensi PHP yang Diperlukan

- PDO + pdo_mysql
- fileinfo
- mbstring
- session

## Instalasi

1. **Clone atau download** project ke document root web server:
   ```bash
   git clone <repository-url> TCKomputer
   ```

2. **Buat database MySQL** bernama `steven_it_shop`:
   ```sql
   CREATE DATABASE steven_it_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Import schema dan data awal**:
   ```bash
   mysql -u root -p steven_it_shop < database.sql
   ```

4. **Konfigurasi koneksi database** — Edit file `config/db.php` sesuai kredensial MySQL Anda:
   ```php
   $host = 'localhost';
   $dbname = 'steven_it_shop';
   $username = 'root';
   $password = '';
   ```

5. **Pastikan direktori upload writable**:
   ```bash
   chmod -R 775 uploads/
   ```
   Direktori yang harus writable:
   - `uploads/products/`
   - `uploads/categories/`
   - `uploads/banners/`
   - `uploads/logo/`

6. **Akses aplikasi** melalui browser:
   - Storefront: `http://localhost/TCKomputer/`
   - Admin Panel: `http://localhost/TCKomputer/admin/`

## Akun Admin Default

| Field | Value |
|-------|-------|
| Email | admin@stevenitshop.com |
| Password | admin123 |

> ⚠️ **Penting:** Segera ubah password default setelah instalasi pertama.

## Struktur Project

```
TCKomputer/
├── admin/                  # Halaman admin panel
│   ├── login.php           # Login admin
│   ├── index.php           # Dashboard
│   ├── products.php        # Manajemen produk
│   ├── categories.php      # Manajemen kategori
│   ├── orders.php          # Manajemen pesanan
│   ├── shipping-areas.php  # Manajemen area pengiriman
│   ├── banners.php         # Manajemen banner
│   └── settings.php        # Pengaturan toko
├── actions/                # Handler form submissions
│   ├── cart-add.php        # Tambah item ke keranjang
│   ├── cart-update.php     # Update jumlah item
│   ├── cart-remove.php     # Hapus item dari keranjang
│   └── checkout-process.php # Proses checkout
├── assets/
│   ├── css/
│   │   ├── style.css       # Stylesheet buyer (mobile-first)
│   │   └── admin.css       # Stylesheet admin panel
│   ├── images/             # Asset gambar statis
│   └── js/
│       ├── main.js         # JavaScript buyer
│       └── admin.js        # JavaScript admin
├── config/
│   ├── db.php              # Koneksi database PDO
│   ├── helpers.php         # Fungsi utilitas (format, validasi, upload)
│   └── admin-auth.php      # Guard autentikasi admin
├── includes/
│   ├── header.php          # Header & navigasi buyer
│   ├── footer.php          # Footer buyer
│   ├── admin-header.php    # Header admin panel
│   └── admin-footer.php    # Footer admin panel
├── uploads/                # File upload (writable)
│   ├── products/           # Gambar produk
│   ├── categories/         # Gambar kategori
│   ├── banners/            # Gambar banner
│   └── logo/               # Logo toko
├── index.php               # Homepage
├── products.php            # Halaman semua produk
├── product-detail.php      # Detail produk
├── category.php            # Halaman kategori
├── cart.php                # Keranjang belanja
├── checkout.php            # Form checkout
├── order-success.php       # Konfirmasi pesanan berhasil
├── track-order.php         # Lacak pesanan
├── database.sql            # Schema & seed data
└── README.md               # Dokumentasi project
```

## Technology Stack

| Layer | Teknologi |
|-------|-----------|
| Backend | PHP Native (tanpa framework) |
| Database | MySQL dengan PDO prepared statements |
| Frontend | HTML5, CSS3 (mobile-first responsive), Vanilla JavaScript |
| Keamanan | CSRF tokens, bcrypt password hashing, input validation, output sanitization |
| File Upload | Validasi MIME (finfo), ekstensi, ukuran, dan konten |

## Format Mata Uang

Semua harga ditampilkan dalam format Rupiah Indonesia:
- Format: `Rp XX.XXX` (dot sebagai pemisah ribuan, tanpa desimal)
- Contoh: `Rp 1.500.000`

## Status Produk

| Status | Keterangan |
|--------|------------|
| Ready | Tersedia, stok ada |
| PO | Pre-Order, bisa dipesan tanpa batas stok |
| Habis | Stok habis, tidak bisa dipesan |

## Alur Order

1. Buyer menambahkan produk ke keranjang
2. Buyer mengisi form checkout (nama, HP, alamat, area pengiriman, metode pembayaran)
3. Sistem membuat order dengan kode unik format `SIT-YYYYMMDD-XXXX`
4. Stok otomatis berkurang untuk produk Ready
5. Buyer menerima kode order untuk tracking
6. Admin memproses pesanan melalui admin panel

## Lisensi

Private - All rights reserved.
