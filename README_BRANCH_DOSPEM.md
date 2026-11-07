# Daftar Perubahan: Branch `kkp-dospem` (scope disetujui dosen pembimbing)

Tujuan: aplikasi hanya menampilkan dan menjalankan scope dospem:
Login, Dashboard, Data Barang (Part Number), Kategori (Item Types), Supplier, Warehouse/Add Item (barang masuk), Rack, Purchase Order (buat, approve, terima, cetak), Export Reports (filter periode).
Modul lain (WO, DO, SO, PR, Customers, Opname, Movements, Docs, Activity Log) disembunyikan DAN route-nya dinonaktifkan.

Langkah awal:
```bash
git checkout -b kkp-dospem
```
Branch `main` dibiarkan sebagai versi lengkap (perusahaan). Jangan share `main` ke dospem.

## 1. Menu samping (`resources/views/layouts/app.blade.php`, array `$menu`, baris ~10-30)

| Menu (label sekarang) | Tindakan | Label baru |
|---|---|---|
| Dashboard | Tetap | - |
| Inventories (`items.index`) | Tetap | Barang Masuk / Warehouse (opsional) |
| Delivery Order | **Hapus baris** | - |
| Stock Movements | **Hapus baris** | - |
| Stock Opname | **Hapus baris** | - |
| Export Reports | Tetap | Laporan |
| Part Number | Tetap | Data Barang |
| Rack | Tetap | - |
| Item Types | Tetap | Kategori |
| Purchase Order | Tetap | - |
| Purchase Request | **Hapus baris** | - |
| Suppliers | Tetap | - |
| Sales Order | **Hapus baris** | - |
| Work Order | **Hapus baris** | - |
| Customers | **Hapus baris** | - |
| Documentation | **Hapus baris** | - |
| Activity Log | **Hapus baris** | - |
| Users / Roles / Permissions | Tetap tersembunyi | - |

Cek juga bagian `$salesItems` (sekitar baris 309) supaya grup "Sales" kosong tidak tampil.

## 2. Route (`routes/web.php`)

Comment atau hapus grup berikut (cegah akses lewat URL langsung):
- `Route::middleware('can:delivery')` (delivery-orders)
- `Route::middleware('can:work-orders')` (work-orders)
- `Route::middleware('can:sales')` dan `can:sales.approve` (sales-orders)
- Route `purchase-requests.*` (baris dalam grup `can:purchasing`; PR approve/reject juga)
- `Route::middleware('can:serial-scans')` (stock movements)
- Route `stock-opnames.*`
- `customers.*`, `documents.*`, `activity-logs.*`
- Hapus juga `use` controller yang tidak terpakai di bagian atas file.

Biarkan: auth, dashboard, inventories, items, racks, locations, item-types, suppliers, `purchasing.*` (index, create, store, show, receive, cancel, print, print-pdf, approve, reject), `items.export`.

## 3. Dashboard (`app/Http/Controllers/DashboardController.php`, `resources/views/dashboard.blade.php`)
- Method `pendingActions()`: hapus bagian yang membaca PR, SO, WO, DO. Sisakan PO Pending Approval.
- Hapus kartu/blok di view yang menyebut WO, DO, SO.

## 4. Controller dan view yang bisa dibiarkan (tidak dipakai)
Cukup tidak ada route-nya. Tidak perlu dihapus supaya perubahan kecil:
`WorkOrderController`, `DeliveryOrderController`, `SalesOrderController`, `PurchaseRequestController`, `CustomerController`, `StockOpnameController`, `SerialScanController`, `DocumentController`, `ActivityLogController`.
Jika ingin repo bersih, hapus bersama folder view: `work-orders`, `delivery-orders`, `sales-orders`, `purchase-requests`, `customers`, `stock-opnames`, `serial-scans`, `documents`, `activity-logs`, serta `resources/views/pdf/{work-order,delivery-order,sales-order,stock-opname}.blade.php`.

## 5. Permission / role (`config/roles.php`, tabel `roles`)
- Role aktif hanya: `superadmin`, `warehouse`, `sales`.
- `warehouse`: inventory.*, warehouse.*, racks.*, item-types.*, suppliers.*, purchasing (receive).
- `sales`: purchasing (buat dan cetak PO).
- `superadmin`: semua, termasuk `purchasing.approve`.
- Hapus/abaikan permission: `sales`, `sales.approve`, `delivery`, `work-orders`, `customers.*`, `documents`, `serial-scans`.

## 6. Item (unit barang)
- Hapus opsi status yang hanya relevan untuk WO/DO bila mengganggu (mis. "Sold Out").
- Form Warehouse: sembunyikan field `no_wo`.

## 7. Data demo (wajib sebelum screenshot)
- Pakai `php artisan wms:reset-data` lalu isi: 4-5 kategori, 5+ part number, 2 supplier, 2-3 PO (status Pending Approval, Ordered, Received dengan 2-3 item), beberapa unit di Rack berbeda.
- Pakai satu tema (dark atau light) untuk semua screenshot.

## 8. Daftar screenshot untuk laporan dospem
1. Login
2. Dashboard (tanpa kartu WO/DO)
3. Warehouse (daftar unit)
4. Add Item (Serialized dan Non-Serialized)
5. Part Number (daftar) dan Add Part Number
6. Kategori / Item Types (daftar dan tambah)
7. Supplier (daftar dan tambah)
8. Create PO (2-3 item)
9. Daftar PO (3 status) dan Detail PO + Approve
10. Form terima barang PO (SN dan lokasi)
11. Cetak PO (PDF)
12. Export Reports (filter terisi) dan hasil file Excel
13. Pesan error: SN duplikat, validasi form
14. Detail unit (Warehouse, Rack, Shelf)
15. Menu samping tiap role (superadmin, warehouse, sales)

## 9. Cek akhir
- Buka URL langsung `/work-orders`, `/delivery-orders`, `/sales-orders` -> harus 404.
- Cari kata `WO`, `DO`, `Work Order`, `Delivery` di view yang tersisa.
- Cek `.env` tidak ikut commit; cek riwayat git sebelum repo dishare.
- Cocokkan screenshot dan listing lampiran laporan dengan branch ini.
