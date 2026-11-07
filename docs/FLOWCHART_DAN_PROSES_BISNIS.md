# 🔄 Flowchart Alur Kerja & Siklus Status Barang
**Project Magang: Warehouse & Logistics Management System (WMS)**

Dokumen ini menggambarkan alur kerja sistem gudang yang dibangun, mulai dari alur barang masuk (inbound), penataan lokasi rak/shelf, siklus 7 status barang, alur pengeluaran barang (outbound), hingga proses stok opname.

---

## 1. Flowchart Barang Masuk (Inbound / Penerimaan Barang)

Alur penerimaan barang dari vendor / supplier atau return perangkat ke dalam gudang:

```mermaid
flowchart TD
    Start([Mulai Penerimaan Barang]) --> SourceType{Jalur Masuk}
    
    SourceType -->|Dari Purchase Order| PO_Input[Pilih / Buka PO Terdaftar]
    SourceType -->|Input Langsung / Return| Direct_Input[Buka Form Add Item / Import Excel]
    
    PO_Input --> Check_Type{Jenis Barang?}
    Direct_Input --> Check_Type
    
    Check_Type -->|Serialized Item| Input_SN[Input Daftar Serial Number / Scan Barcode]
    Check_Type -->|Quantity-based| Input_Qty[Masukkan Jumlah Kuantitas & Satuan UOM]
    
    Input_SN --> Validate_SN{Validasi SN Duplikat}
    Validate_SN -->|Ditemukan SN Kembar| Reject_SN[Tolak Input & Tampilkan Pesan Error]
    Reject_SN --> Input_SN
    
    Validate_SN -->|SN Valid & Unik| Select_Loc[Pilih Warehouse Tujuan]
    Input_Qty --> Select_Loc
    
    Select_Loc --> Select_Storage[Pilih Rack & Isi Shelf / Bin Level]
    Select_Storage --> Set_Status[Status Otomatis: <b>Available</b><br>Kondisi: <b>New / Normal</b>]
    
    Set_Status --> DB_Insert[(Simpan ke Database Items)]
    DB_Insert --> Log_Scan[(Catat ke Riwayat Scan & Activity Log)]
    Log_Scan --> Finish_Inbound([Barang Siap di Gudang & Menambah Available Qty])
```

---

## 2. Flowchart Hierarki Penyimpanan Fisik (Warehouse Hierarchy)

Sistem menerapkan hierarki 3 tingkat lokasi fisik untuk memastikan barang mudah dicari oleh staff lapangan:

```mermaid
flowchart LR
    subgraph Level_1 [Tingkat 1: Gedung / Lantai]
        WH1["Warehouse 1 (Lantai 1)"]
        WH2["Warehouse 2 (Lantai 2)"]
    end

    subgraph Level_2 [Tingkat 2: Rak Penyimpanan]
        WH1 --> RACK_A["RACK-A01 (Switch/Router)"]
        WH1 --> RACK_B["RACK-B01 (Access Point)"]
        WH2 --> RACK_C["RACK-C01 (Kabel & Aksesoris)"]
    end

    subgraph Level_3 [Tingkat 3: Shelf / Bin Compartment]
        RACK_A --> SH_1["SHELF-01 (Tingkat Bawah)"]
        RACK_A --> SH_2["SHELF-02 (Tingkat Tengah)"]
        RACK_B --> BIN_A["BIN-A (Kotak Sparepart)"]
        RACK_C --> SH_3["SHELF-01 (Gulungan Kabel)"]
    end

    subgraph Unit [Penyimpanan Unit Barang]
        SH_1 --> Unit_1["Unit: Router RB951Ui<br>SN: 8A4406ABC123<br>Format: <b>Lantai 1 / RACK-A01 / SHELF-01</b>"]
    end
```

---

## 3. Flowchart Siklus Hidup & Status Barang (7 Status Dokumen Magang)

Siklus transisi status unit barang dari awal masuk gudang hingga keluar / dioperasikan:

```mermaid
stateDiagram-v2
    [*] --> Available : Inbound Receipt / PO Receive / Import
    
    state "STOK TERSEDIA (Available Qty)" as Avail_Group {
        Available --> Reserved : Terpilih dalam Picking / Disiapkan untuk Order
        Reserved --> Available : Order Dibatalkan / Transaksi Batal
    }

    state "STOK TIDAK TERSEDIA (Non-Available)" as Non_Avail_Group {
        Reserved --> In_Transit : Barang Dikirim Antar-Gudang
        In_Transit --> Available : Tiba di Gudang Tujuan & Dikonfirmasi
        
        Reserved --> Rented : DO Selesai (Jenis: Rental Perangkat)
        Rented --> Under_Service : Unit Kembali dengan Gangguan
        Rented --> Available : Unit Kembali dalam Kondisi Normal
        
        Available --> Under_Service : Ditemukan Kerusakan Saat Audit / Maintenance
        Under_Service --> Available : Selesai Diperbaiki & Lolos Uji
        Under_Service --> Damaged : Unit Dinyatakan Rusak Total (Mati Total)
        
        Available --> Damaged : Rusak Fisik di Rak
        Available --> Lost : Hilang / Discrepancy Stock Opname
        
        Reserved --> Sold_Out : DO Selesai (Jenis: Sold / Terjual)
    }

    Damaged --> [*] : Disposal / Scrap
    Lost --> [*] : Write-Off
    Sold_Out --> [*] : Keluar Permanen ke Pelanggan
```

### Aturan Bisnis Perhitungan Stok:
- **Tersedia (Available)**: Hanya unit berstatus `Available` yang dihitung dalam *Available Qty*.
- **Terpakai / Keluar (Used)**: Unit yang berstatus `Sold Out`, `Lost`, `Rented`, keluar lewat DO (`stuff_out`), atau unit bekas pakai (`condition != New`) terhitung dalam metrik *Used*.
- **Non-Available**: Unit berstatus `Rented`, `Under Service`, `Damaged`, dan `Lost` **TIDAK PERNAH** dihitung sebagai stok tersedia.

---

## 4. Flowchart Barang Keluar (Outbound / Delivery Order)

Alur pengeluaran barang dari gudang berdasarkan Work Order (WO) atau permintaan penjualan:

```mermaid
flowchart TD
    Start_Out([Permintaan Pengeluaran / WO]) --> Check_Stock{Cek Stok Tersedia<br>Part Number di Gudang}
    
    Check_Stock -->|Stok Kurang| Reject_Order[Tolak Pembuatan DO / Buat Purchase Order Baru]
    Check_Stock -->|Stok Cukup| Create_DO[Buat Dokumen DO - Status: Draft]
    
    Create_DO --> Picking_Step[Proses Picking & Preparation]
    Picking_Step --> Reserve_Stock[Kunci Unit Barang: Status <b>Reserved</b><br><i>Stok tidak bisa diambil order lain</i>]
    
    Reserve_Stock --> Physical_Pick[Staff Mengambil Fisik dari Rak & Shelf]
    Physical_Pick --> Scan_Verify{Scan Verifikasi Serial Number}
    
    Scan_Verify -->|SN Tidak Cocok| Alert_Mismatch[Notifikasi: SN Tidak Sesuai Daftar Picking]
    Alert_Mismatch --> Physical_Pick
    
    Scan_Verify -->|SN Terverifikasi Cocok| Set_Ready[Status DO: <b>Ready to Ship</b>]
    
    Set_Ready --> Select_Type{Pilih Jenis Pengeluaran DO}
    
    Select_Type -->|1. Sold (Penjualan)| Dispatch_Sold[Status Unit: <b>Sold Out</b><br>Kuantitas Fisik Berkurang]
    Select_Type -->|2. Rental (Sewa)| Dispatch_Rent[Status Unit: <b>Rented</b><br>Tetap Aset Perusahaan]
    Select_Type -->|3. Service (Perbaikan)| Dispatch_Serv[Status Unit: <b>Under Service</b><br>Kirim ke Vendor/Teknisi]
    Select_Type -->|4. Return (Retur Vendor)| Dispatch_Ret[Kirim Balik ke Supplier]
    Select_Type -->|5. Damaged / Lost| Dispatch_Loss[Status Unit: <b>Damaged / Lost</b>]
    
    Dispatch_Sold --> Finalize_DO[Finalisasi DO - Status: <b>Delivered</b>]
    Dispatch_Rent --> Finalize_DO
    Dispatch_Serv --> Finalize_DO
    Dispatch_Ret --> Finalize_DO
    Dispatch_Loss --> Finalize_DO
    
    Finalize_DO --> Print_DO[Cetak Surat Jalan / Bukti Serah Terima DO]
    Print_DO --> End_Out([Selesai: Audit Trail & Riwayat Scan Tercatat])
```

---

## 5. Flowchart Stock Opname (Audit Fisik Gudang)

Proses verifikasi berkala antara saldo stok di sistem dan fisik di rak:

```mermaid
flowchart TD
    Start_SO([Inisiasi Stock Opname]) --> Select_WH[Pilih Gudang / Lantai Audit]
    Select_WH --> Freeze[Cetak Blank Lembar Perhitungan Opname]
    
    Freeze --> Physical_Count[Auditor / Staff Menghitung Fisik di Setiap Rak & Shelf]
    Physical_Count --> Input_Physical[Input Kuantitas Fisik Hasil Audit ke Sistem]
    
    Input_Physical --> Compare_Diff{Bandingkan System Qty vs Physical Qty}
    
    Compare_Diff -->|Sesuai / Selisih 0| SO_Matched[Hasil Match: Kondisi Stok Akurat]
    Compare_Diff -->|Ada Selisih / Discrepancy| Flag_Discrepancy[Tandai Selisih: Surplus atau Shortage]
    
    Flag_Discrepancy --> Audit_Review[Review Manager / Supervisor]
    Audit_Review --> Adjust_Stock[Eksekusi Penyesuaian Status / Koreksi Stok]
    
    SO_Matched --> Finalize_SO[Finalisasi Laporan Stock Opname]
    Adjust_Stock --> Finalize_SO
    
    Finalize_SO --> Export_SO_Excel[Export Laporan Stock Opname ke Excel]
    Export_SO_Excel --> End_SO([Selesai: Rekonsiliasi Tercatat Lengkap])
```

