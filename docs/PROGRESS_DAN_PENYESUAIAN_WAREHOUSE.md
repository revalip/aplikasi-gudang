# 📋 Catatan Progres Pengerjaan & Pengujian Modul Warehouse
**Project Magang: Warehouse & Logistics Management System (WMS)**
**Disusun Oleh**: Mahasiswa Magang (ALIP)
**Pembimbing / Supervisor**: Pak Bagir
**Tanggal Update**: 30 Oktober 2026

---

## 1. Ringkasan Pengerjaan

Sesuai arahan dan alur penugasan magang, pengerjaan difokuskan secara bertahap dimulai dari modul pertama, yaitu **Modul Warehouse (Inventory Management)**.

Fokus utama pada pengerjaan modul warehouse ini meliputi:
1. Menyesuaikan status barang di sistem agar sejalan dengan ketentuan panduan magang (Bab 3.5 & Bab 7).
2. Menambahkan tingkat penyimpanan rak dan shelf/bin agar penataan fisik barang di gudang lebih presisi (Bab 3.1).
3. Memperbaiki bug pada perhitungan stok `Available` dan angka `Used` saat barang terjual / keluar.

---

## 2. Rincian Penyesuaian Fitur yang Sudah Dikerjakan

### A. Standarisasi 7 Status Siklus Barang (Bab 3.5 & Bab 7)
* **Kondisi Awal**: Status barang di sistem sebelumnya masih campur aduk antara istilah lama, seperti `In Stock`, `Borrow`, `Instalation`, `Broken`, `Has not been tested`, `Retur device to supplier`, dan `Sold Out`.
* **Yang Disesuaikan**:
  1. Status barang distandarkan menjadi 7 status resmi sesuai buku panduan magang:
     - `Available`: Barang siap pakai dan tersedia di rak gudang.
     - `Reserved`: Barang sudah dialokasikan/disiapkan untuk order tertentu.
     - `In Transit`: Barang sedang dalam proses pindah antar-gudang.
     - `Rented`: Barang sedang disewa oleh pelanggan/klien.
     - `Under Service`: Barang sedang diservis, perbaikan, atau pengecekan teknisi.
     - `Damaged`: Barang rusak fisik / mati total.
     - `Lost`: Barang hilang atau selisih saat stok opname.
  2. **Backward Compatibility**: Disediakan kamus pemetaan (`legacy_statuses`) di `config/item_options.php` agar data lama atau file impor Excel dari vendor lama otomatis terkonversi ke status baru tanpa bikin aplikasi error.
  3. Dibuatkan file migrasi database (`database/migrations/2026_10_26_000001_standardize_item_statuses.php`) agar data di server langsung ter-update otomatis saat `php artisan migrate`.

### B. Hierarki Lokasi Penyimpanan: Gudang $\to$ Rak $\to$ Shelf/Bin (Bab 3.1 & 3.5)
* **Kondisi Awal**: Lokasi baru mencatat gudang/lantai dan nama rak secara umum, belum ada catatan tingkat shelf (ambal) atau kotak bin.
* **Yang Disesuaikan**:
  1. Menambahkan kolom `shelf` di tabel `items` lewat migrasi `database/migrations/2026_10_26_000002_add_shelf_to_items_table.php`.
  2. Menambahkan helper format lokasi di model `Item.php` (`$item->formatted_location`) yang otomatis menghasilkan format rapi:  
     $$\text{Warehouse / RACK-xx / SHELF-xx}$$  
     Contohnya: `Lantai 1 / RACK-A01 / SHELF-01`.
  3. Form input & edit barang (`resources/views/items/form.blade.php`) diubah menjadi 3 kolom: **Warehouse**, **Rack**, dan **Shelf / Bin Location**.
  4. Halaman daftar rincian unit per part number (`resources/views/items/part.blade.php`) sekarang sudah menampilkan kolom Shelf / Bin, dan di tampilan HP muncul penanda lokasi lengkapnya.
  5. Fitur **Export Excel** dan **Import Excel** disesuaikan agar kolom Shelf/Bin ikut terisi.

### C. Filter Gudang & Filter Status di Halaman Utama
* Di halaman utama Warehouse (`resources/views/items/index.blade.php`), ditambahkan dropdown filter **Warehouse** dan **Status**.
* Kartu angka ringkasan di atas tabel (`Available`, `Under Service`, `Damaged`, `Rented`) sekarang bisa diklik langsung untuk memfilter tabel dengan cepat (misal sekali klik untuk melihat semua barang yang sedang rusak).

---

## 3. Penemuan & Perbaikan Bug: Logika Hitungan Available & Used

### Gejala Masalah:
Saat mencoba mengubah status unit barang menjadi **`Sold Out`** (barang laku/terjual), angka `Used` di tabel warehouse malah tidak bertambah. Tampilan yang keluar justru:
```text
0
(New 0, Used 0)
```
Seolah-olah barangnya tidak pernah ada, padahal unit tersebut tercatat di sistem dan statusnya sudah terjual.

### Penyebab Masalah:
Setelah dicek di controller (`ItemController.php`), query perhitungan sebelumnya mengunci hitungan `used_qty` dengan syarat:
```sql
sum(case when status in ('Available', 'In Stock') and (`condition` != 'New' or `condition` is null) then 1 else 0 end) as used_qty
```
Karena syaratnya wajib `status in ('Available', 'In Stock')`:
- Sistem hanya menghitung barang yang masih berstatus ada di gudang.
- Begitu status barang diganti ke `Sold Out`, statusnya bukan lagi `Available`.
- Akibatnya unit itu hilang dari hitungan query SQL, sehingga `in_stock = 0`, `new_qty = 0`, dan `used_qty = 0`.

### Solusi & Perbaikan Kode:
Query di `ItemController.php` diperbaiki agar `used_qty` juga menghitung barang-barang yang sudah keluar/terpakai:
```sql
sum(case when status in ('Available', 'In Stock') then 1 else 0 end) as in_stock,
sum(case when status in ('Available', 'In Stock') and `condition` = 'New' then 1 else 0 end) as new_qty,
sum(case
    when status in ('Sold Out', 'Lost') or stuff_out is not null then 1
    when status in ('Rented', 'Borrow', 'Instalation') then 1
    when status in ('Available', 'In Stock') and (`condition` != 'New' or `condition` is null) then 1
    else 0
end) as used_qty
```

### Hasil Setelah Diperbaiki:
- Jika semua unit sudah terjual (`Sold Out`): Tampilan menjadi `0 (New 0, Used 1)` $\to$ Stok tersedia 0, dan tercatat 1 unit terpakai/terjual.
- Jika ada kombinasi: Misal ada 3 unit baru di gudang dan 2 unit sudah terjual, tampilan menjadi `3 (New 3, Used 2)`.
- Format tampilan tetap konsisten sesuai arahan Pak Bagir: `Stok Tersedia (New X, Used Y)`.

---

## 4. Hasil Pengujian Skenario (Testing Bab 10)

Berikut rangkuman hasil pengujian pada modul warehouse:

| Skenario Uji | Apa yang Dites? | Hasil di Sistem | Status |
|---|---|---|---|
| **Skenario 1** | Input barang ber-serial number (Router) dengan SN unik. | Barang berhasil masuk, kuantitas bertambah sesuai jumlah SN yang diinput. | ✅ Berhasil |
| **Skenario 2** | Coba input serial number yang sama/duplikat. | Sistem menolak dan menampilkan pesan error bahwa SN sudah terdaftar. | ✅ Berhasil |
| **Skenario 3** | Barang diubah ke status `Sold Out` atau dikeluarkan lewat DO. | Stok tersedia (`Available`) berkurang dan angka `Used` bertambah. | ✅ Berhasil |
| **Skenario 4** | Cek unit berstatus `Rented`, `Under Service`, `Damaged`, atau `Lost`. | Unit otomatis tidak dimasukkan ke dalam stok Available sesuai aturan Bab 7. | ✅ Berhasil |
| **Skenario 5** | Pengecekan penempatan barang (Warehouse $\to$ Rack $\to$ Shelf). | Detail unit menampilkan lokasi lengkap dan bisa dicari lewat search bar. | ✅ Berhasil |
| **Skenario 6** | Coba hapus rak atau gudang yang masih ada barangnya. | Sistem memblokir aksi hapus untuk menjaga keamanan data inventaris. | ✅ Berhasil |

---

## 5. Rencana Pengerjaan Selanjutnya

Setelah Modul Warehouse ini di-review dan disetujui oleh pembimbing:
1. Melanjutkan ke **Modul Goods Receipt (Inbound)**: Pembuatan nomor bukti penerimaan barang masuk dan konfirmasi penerimaan dari PO.
2. Melanjutkan ke **Modul Picking & Reservasi**: Mengunci unit barang saat persiapan sebelum dikirim.
3. Melanjutkan ke **Modul Delivery Order (Outbound)**: Pengelompokan jenis pengeluaran barang (Sold, Rental, Retur, Service, Damaged, Lost).
