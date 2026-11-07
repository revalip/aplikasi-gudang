# 🗄️ Entity Relationship Diagram (ERD) & Struktur Database
**Project Magang: Warehouse & Logistics Management System (WMS)**

Dokumen ini memuat rancangan diagram relasi database (ERD), relasi antar-tabel, dan penjelasan struktur data yang digunakan pada aplikasi WMS ini.

---

## 1. Diagram ERD (Mermaid)

```mermaid
erDiagram
    LOCATIONS ||--o{ RACKS : "has"
    LOCATIONS ||--o{ ITEMS : "stores"
    RACKS ||--o{ ITEMS : "houses"
    ITEM_TYPES ||--o{ ITEMS : "categorizes"
    ITEM_TYPES ||--o{ INVENTORIES : "classifies"
    INVENTORIES ||--o{ ITEMS : "defines"
    
    SUPPLIERS ||--o{ PURCHASE_ORDERS : "supplies"
    PURCHASE_ORDERS ||--o{ PURCHASE_ORDER_ITEMS : "contains"
    INVENTORIES ||--o{ PURCHASE_ORDER_ITEMS : "references"
    
    ITEMS ||--o{ SERIAL_SCANS : "tracked_by"
    LOCATIONS ||--o{ SERIAL_SCANS : "scan_location"
    USERS ||--o{ SERIAL_SCANS : "scanned_by"
    
    CUSTOMERS ||--o{ DELIVERY_ORDERS : "receives"
    DELIVERY_ORDERS ||--o{ DELIVERY_ORDER_ITEMS : "contains"
    ITEMS ||--o{ DELIVERY_ORDER_ITEMS : "fulfilled_by"
    
    LOCATIONS ||--o{ STOCK_OPNAMES : "audited_in"
    STOCK_OPNAMES ||--o{ STOCK_OPNAME_ITEMS : "contains"
    ITEMS ||--o{ STOCK_OPNAME_ITEMS : "audits"
    
    ROLES ||--o{ USERS : "assigned_to"
    USERS ||--o{ ACTIVITY_LOGS : "logs"

    LOCATIONS {
        bigint id PK
        string name "Lantai 1, Lantai 2, etc."
        timestamp created_at
        timestamp updated_at
    }

    RACKS {
        bigint id PK
        bigint location_id FK
        string name "A-01, RAK-CORE, etc."
        timestamp created_at
        timestamp updated_at
    }

    ITEM_TYPES {
        bigint id PK
        string name "Router, Switch, AP, etc."
        timestamp created_at
        timestamp updated_at
    }

    INVENTORIES {
        bigint id PK
        string part_number UK
        string item_name
        string brand
        bigint type_id FK
        string default_warranty
        integer min_stock
        text description
        timestamp created_at
        timestamp updated_at
    }

    ITEMS {
        bigint id PK
        string item_id UK "MJG-RTR-0001"
        string item_name
        bigint inventory_id FK
        string part_number
        bigint type_id FK
        string serial_number UK
        string merek
        string status "Available, Reserved, Rented, etc."
        string condition "New, Used, Damaged, etc."
        string unit "pcs, meter, roll"
        date stuff_in
        date stuff_out
        string no_wo
        string no_po_spr
        string warranty
        text notes
        bigint location_id FK
        string rack "Nama rak fisik"
        string shelf "Shelf / Bin level"
        string batch_code
        bigint reserved_sales_order_item_id
        bigint reserved_work_order_item_id
        timestamp reserved_at
        timestamp created_at
        timestamp updated_at
    }

    SERIAL_SCANS {
        bigint id PK
        bigint item_id FK
        string serial_number
        string direction "in, out, transfer"
        bigint location_id FK
        bigint scanned_by FK
        text notes
        timestamp scanned_at
        timestamp created_at
        timestamp updated_at
    }

    SUPPLIERS {
        bigint id PK
        string name
        string contact_person
        string phone
        string email
        text address
        timestamp created_at
        timestamp updated_at
    }

    PURCHASE_ORDERS {
        bigint id PK
        string po_number UK
        date order_date
        date expected_delivery_date
        bigint supplier_id FK
        string status "draft, approved, received, etc."
        text notes
        timestamp created_at
        timestamp updated_at
    }

    PURCHASE_ORDER_ITEMS {
        bigint id PK
        bigint purchase_order_id FK
        string item_name
        bigint inventory_id FK
        string part_number
        bigint type_id FK
        string merek
        string warranty
        string unit
        integer quantity
        integer received_quantity
        text description
        timestamp created_at
        timestamp updated_at
    }

    CUSTOMERS {
        bigint id PK
        string name
        string contact_person
        string phone
        string email
        text address
        timestamp created_at
        timestamp updated_at
    }

    DELIVERY_ORDERS {
        bigint id PK
        string do_number UK
        bigint sales_order_id FK
        bigint work_order_id FK
        bigint customer_id FK
        string status "draft, picking, shipped, delivered, cancelled"
        date delivery_date
        timestamp delivered_at
        bigint delivered_by FK
        text notes
        timestamp created_at
        timestamp updated_at
    }

    DELIVERY_ORDER_ITEMS {
        bigint id PK
        bigint delivery_order_id FK
        bigint item_id FK
        bigint sales_order_item_id FK
        bigint work_order_item_id FK
        timestamp scanned_at
        bigint scanned_by FK
        timestamp created_at
        timestamp updated_at
    }

    STOCK_OPNAMES {
        bigint id PK
        string opname_number UK
        bigint location_id FK
        string status "draft, in_progress, completed"
        string conducted_by
        date opname_date
        text notes
        timestamp created_at
        timestamp updated_at
    }

    STOCK_OPNAME_ITEMS {
        bigint id PK
        bigint stock_opname_id FK
        bigint item_id FK
        string part_number
        integer system_qty
        integer physical_qty
        integer discrepancy
        text notes
        timestamp created_at
        timestamp updated_at
    }

    USERS {
        bigint id PK
        string name
        string email UK
        string password
        string role "superadmin, warehouse-admin, staff, etc."
        json permissions
        string phone
        string position
        string department
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    ROLES {
        bigint id PK
        string name UK "superadmin, user, etc."
        string label
        string description
        json permissions
        boolean is_system
        timestamp created_at
        timestamp updated_at
    }

    ACTIVITY_LOGS {
        bigint id PK
        bigint user_id FK
        string user_name
        string event "created, updated, deleted, import, scan"
        text description
        string subject_type
        bigint subject_id
        json properties
        string ip_address
        timestamp created_at
        timestamp updated_at
    }
```

---

## 2. Struktur Detail Entitas Modul Warehouse (Core Inventory)

### A. Tabel `locations` (Warehouse / Storage Area)
Menyimpan daftar gudang utama atau lantai penyimpanan:
- `id` (PK, BigInt Auto Increment)
- `name` (VarChar 255): Nama gudang/lantai (contoh: *Warehouse 1 - Lantai 1*, *Warehouse 2 - Lantai 2*).
- `timestamps`

### B. Tabel `racks` (Storage Racks)
Menyimpan data rak penyimpanan yang berada di bawah lokasi gudang tertentu:
- `id` (PK, BigInt Auto Increment)
- `location_id` (FK $\to$ `locations.id`, Cascade On Delete)
- `name` (VarChar 255): Kode/nama rak (contoh: *RACK-A01*, *RACK-B02*).
- **Index**: Unique (`location_id`, `name`) — mencegah duplikasi rak dalam satu gudang.

### C. Tabel `items` (Physical Stock Units)
Menyimpan setiap unit fisik barang, baik barang serialised maupun quantity-based:
- `id` (PK, BigInt Auto Increment)
- `item_id` (VarChar 255, Unique): Nomor identifikasi unit sistem (contoh: `MJG-RTR-0042`).
- `item_name` (VarChar 255): Nama model/nama barang.
- `inventory_id` (FK $\to$ `inventories.id`, Nullable): Relasi ke master katalog part number.
- `part_number` (VarChar 255, Indexed): Part Number pabrikan barang.
- `type_id` (FK $\to$ `item_types.id`): Kategori perangkat (Router, Switch, Access Point, dll).
- `serial_number` (VarChar 255, Nullable, Indexed): Serial Number unik / MAC Address barang serialised.
- `merek` (VarChar 255, Nullable): Brand / Pabrikan (Mikrotik, Cisco, Ruijie, dll).
- `status` (VarChar 50): 7 Status standar magang (`Available`, `Reserved`, `In Transit`, `Rented`, `Under Service`, `Damaged`, `Lost`).
- `condition` (VarChar 50): Kondisi fisik barang (`New`, `Used`, `Normal`, `Damaged`).
- `unit` (VarChar 20): Satuan barang (`pcs`, `meter`, `roll`, `set`).
- `location_id` (FK $\to$ `locations.id`): Lokasi gudang penyimpanan saat ini.
- `rack` (VarChar 255, Nullable): Nama rak fisik tempat barang ditaruh.
- `shelf` (VarChar 255, Nullable): Tingkat rak / kompartemen / nomor bin (`SHELF-01`, `Bin A`, `Level 2`).
- `stuff_in` (Date, Nullable): Tanggal barang diterima ke gudang.
- `stuff_out` (Date, Nullable): Tanggal barang keluar dari gudang (saat pengiriman / sold / rental).
- `no_po_spr` (VarChar 255, Nullable): Nomor referensi PO / SPR penerimaan asal.
- `no_wo` (VarChar 255, Nullable): Nomor referensi Work Order saat pengeluaran.
- `warranty` (VarChar 50, Nullable): Durasi garansi dalam satuan bulan.
- `batch_code` (UUID, Nullable): UUID pengelompokan unit yang masuk dalam 1 batch transaksi penerimaan.
- `reserved_sales_order_item_id` / `reserved_work_order_item_id` (FK, Nullable): Penanda alokasi reservasi stok saat proses picking.
- `reserved_at` (Timestamp, Nullable): Waktu barang di-reserve.
- `notes` (Text, Nullable): Catatan riwayat spesifik unit.

### D. Tabel `inventories` (Master Part Number Catalog)
Menyimpan katalog master barang sebagai basis standarisasi Part Number:
- `id` (PK, BigInt Auto Increment)
- `part_number` (VarChar 255, Unique): Kode Part Number resmi produk.
- `item_name` (VarChar 255): Nama standar barang.
- `brand` (VarChar 255, Nullable): Pabrikan utama.
- `type_id` (FK $\to$ `item_types.id`): Tipe perangkat.
- `default_warranty` (VarChar 50, Nullable): Garansi bawaan default produk.
- `min_stock` (Integer, Nullable): Batas minimum stok sebelum trigger alert *Low Stock*.
- `description` (Text, Nullable): Spesifikasi dan keterangan umum.

---

## 3. Integritas Relasi & Aturan Bisnis Basis Data

1. **Anti-Duplikasi Serial Number**: Kolom `serial_number` divalidasi ketat di level request dan query sehingga satu nomor serial aktif tidak dapat didaftarkan dua kali.
2. **Hierarki Penyimpanan Terpadu**: Kombinasi `locations.name / items.rack / items.shelf` membentuk lokasi fisik terpadu:  
   $$\text{WH-01 / RACK-A01 / SHELF-02}$$
3. **Proteksi Integritas Relasi**:
   - Gudang (`locations`) tidak dapat dihapus jika masih menampung unit barang (`items_count > 0`) atau memiliki rak aktif.
   - Rak (`racks`) tidak dapat dihapus jika masih ditempati oleh unit barang aktif.
4. **Isolasi Stok Tersedia (Available)**:
   - Barang berstatus `Rented`, `Under Service`, `Damaged`, atau `Lost` dikeluarkan dari agregasi ketersediaan stok (`Available Qty`).
   - Unit yang sedang di-reserve (`reserved_at is not null`) dikunci agar tidak dapat diambil oleh pesanan lain secara bersamaan.

