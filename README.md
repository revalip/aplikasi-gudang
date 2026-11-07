# Sistem Manajemen Pergudangan & Logistik (WMS)
**Tugas Proyek Magang: Warehouse & Logistics Management System**

Aplikasi Warehouse Management System (WMS) berbasis web yang dibangun menggunakan Laravel untuk membantu pengelolaan inventaris barang, pencatatan serial number, hierarki lokasi rak & shelf, penerimaan barang masuk, serta pengeluaran barang keluar.

---

## 📚 Dokumentasi Proyek & Tugas Magang

Berikut berkas dokumentasi lengkap sesuai arahan dan ketentuan dokumen magang:

- [📊 Entity Relationship Diagram (ERD) & Struktur Database](docs/ERD_DAN_ARSITEKTUR_DATABASE.md)
- [🔄 Flowchart Alur Kerja & Siklus Status Barang](docs/FLOWCHART_DAN_PROSES_BISNIS.md)
- [📋 Catatan Progres, Penyesuaian & Pengujian Bug Modul Warehouse](docs/PROGRESS_DAN_PENYESUAIAN_WAREHOUSE.md)
- [🛡️ Daftar Role & Matriks Hak Akses Pengguna](docs/ROLE_DAN_PERMISSION.md)

---

## 🚀 Fitur Utama Sistem

- **Manajemen Gudang & Lokasi Penyimpanan**: Struktur lokasi fisik bertingkat: *Gudang / Lantai $\to$ Rak $\to$ Shelf / Bin*.
- **Katalog Master Barang (Part Number)**: Pengelompokan data berdasarkan tipe perangkat, part number resmi, merek pabrikan, dan masa garansi.
- **Dukungan Dua Jenis Barang**:
  - *Barang Serialised*: Setiap unit punya serial number unik (anti-duplikat) dan riwayat pelacakan tersendiri.
  - *Barang Quantity-Based*: Barang tanpa serial number (seperti kabel, konektor) dicatat berdasarkan saldo kuantitas.
- **Pengadaan & Purchase Order (PO)**: Pembuatan Purchase Order, proses approval PO, penerimaan barang fisik langsung ke rak gudang, serta cetak dokumen PO (PDF).
- **Penerimaan Barang (Barang Masuk / Inbound)**: Pencatatan barang masuk dari PO atau input langsung dengan validasi serial number dan penempatan rak/shelf.
- **Laporan & Ekspor Data**: Ekspor data inventaris fisik ke format Excel (XLSX) dengan filter periode dan part number.
- **Pembagian Role Pengguna (RBAC)**: Pengaturan izin akses untuk Superadmin, Warehouse, dan Purchasing.

---

## 🛠️ Teknologi yang Digunakan

- **Backend**: Laravel 11 (PHP 8.2+)
- **Database**: MySQL / MariaDB
- **Frontend**: Blade Templating + Tailwind CSS
- **Ikon**: Lucide Icons
- **Ekspor Dokumen**: OpenSpout (Excel XLSX) & DomPDF

---

## 💻 Panduan Instalasi & Menjalankan Aplikasi

Langkah-langkah untuk menjalankan aplikasi di komputer lokal:

1. **Clone repository ini:**
   ```bash
   git clone https://github.com/revalip/aplikasi-gudang.git
   cd aplikasi-gudang
   ```

2. **Install dependensi PHP & aset frontend:**
   ```bash
   composer install
   npm install && npm run build
   ```

3. **Pengaturan file environment (`.env`):**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Sesuaikan konfigurasi database di file `.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=nama_database_kamu
   DB_USERNAME=root
   DB_PASSWORD=
   ```

4. **Jalankan migrasi database dan seeder data awal:**
   ```bash
   php artisan migrate --seed
   ```

5. **Jalankan server lokal:**
   ```bash
   php artisan serve
   ```
   Buka browser dan akses alamat `http://127.0.0.1:8000`.

---

## 🔑 Akun Default untuk Login Pengujian

Setelah menjalankan `php artisan migrate --seed`, akun bawaan yang bisa dipakai login:
- **Email**: `admin@mjg.com`
- **Password**: `password`
- **Role**: Superadmin

---

## 📄 Catatan Tambahan

Proyek ini dibuat dan dikembangkan sebagai bagian dari tugas magang. Seluruh data transaksi, part number, dan riwayat yang ada dalam seeder digunakan untuk keperluan pengujian dan simulasi operasional gudang.
