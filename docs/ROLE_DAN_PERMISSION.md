# 🛡️ Daftar Role & Matriks Hak Akses Pengguna
**Project Magang: Warehouse & Logistics Management System (WMS)**

Dokumen ini menjelaskan pembagian role dan hak akses pengguna (Role-Based Access Control / RBAC) yang diterapkan pada aplikasi WMS ini.

---

## 1. Daftar Role Pengguna

Sistem mendukung peran-peran utama berikut:

| Role | Kode Sistem | Deskripsi Peran |
|---|---|---|
| **Superadmin / Administrator** | `superadmin` | Memiliki akses penuh ke seluruh modul, pengaturan sistem, audit trail, serta manajemen user dan hak akses. |
| **Warehouse Manager / Admin** | `warehouse-admin` | Bertanggung jawab penuh terhadap operasional fisik gudang: master part number, penempatan rak/shelf, penerimaan barang, pengeluaran DO, dan audit stok opname. |
| **Warehouse Staff** | `user` *(diberi izin operasional)* | Melakukan tugas operasional harian: scan serial number barang masuk/keluar, pelabelan rak, dan picking barang. Tidak berhak menghapus data master. |
| **Purchasing** | `purchasing` | Mengelola data supplier, mencatat referensi Purchase Order, dan memantau status barang masuk dari PO. |
| **Sales / Requester** | `sales` | Membuat referensi Work Order (WO) dan memantau status ketersediaan barang untuk pelanggan. |

---

## 2. Matriks Hak Akses Modul (Permission Matrix)

Keterangan:
- ✅ : Memiliki Hak Akses Penuh
- 👁️ : Hanya Melihat (View / Read-Only)
- ❌ : Tidak Memiliki Akses

| Modul & Fitur | Permission Key | Superadmin | Warehouse Admin | Warehouse Staff | Purchasing | Sales |
|---|---|:---:|:---:|:---:|:---:|:---:|
| **Dashboard Utama** | *(Umum)* | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Warehouse Items (View)** | `warehouse.view` | ✅ | ✅ | ✅ | 👁️ | 👁️ |
| **Warehouse Items (Create/Import)** | `warehouse.create` | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Warehouse Items (Edit/Update)** | `warehouse.update` | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Warehouse Items (Delete)** | `warehouse.delete` | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Master Racks & Shelves** | `racks.view`, `racks.create`, `racks.delete` | ✅ | ✅ | 👁️ | ❌ | ❌ |
| **Scan Serial Number** | `serial-scans` | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Master Part Number (Inventory)** | `inventory.view`, `inventory.create`, etc. | ✅ | ✅ | 👁️ | 👁️ | 👁️ |
| **Master Item Types** | `item-types.view`, `item-types.create`, etc. | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Purchase Orders & Suppliers** | `purchasing`, `suppliers.*` | ✅ | 👁️ | ❌ | ✅ | ❌ |
| **Delivery Orders (Barang Keluar)** | `delivery` | ✅ | ✅ | ✅ | ❌ | 👁️ |
| **Stock Opname (Audit Stok)** | `warehouse.view`, `warehouse.update` | ✅ | ✅ | 👁️ | ❌ | ❌ |
| **User & Role Management** | `users.manage` | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Activity Log / Audit Trail** | `activity-logs` | ✅ | ❌ | ❌ | ❌ | ❌ |

---

## 3. Implementasi Keamanan Backend

1. **Route Middleware Guard**: Setiap rute dilindungi oleh middleware `auth` dan gate `can:<permission>`, contoh:
   ```php
   Route::middleware(['can:warehouse.create'])->group(function () {
       Route::get('/items/create', [ItemController::class, 'create'])->name('items.create');
       Route::post('/items', [ItemController::class, 'store'])->name('items.store');
   });
   ```
2. **UI Blade Conditional Rendering**: Tombol aksi (seperti Add Item, Import, Edit, dan Delete) diatur tampilannya secara dinamis menggunakan direktif `@can`:
   ```blade
   @can('warehouse.update')
       <a href="{{ route('items.edit', $item) }}">Edit</a>
   @endcan
   ```
3. **URL Manipulation Defense**: Jika pengguna mencoba mengakses URL yang tidak diizinkan secara langsung melalui browser bar, sistem secara otomatis merespons dengan HTTP Status Code **403 Forbidden**.

