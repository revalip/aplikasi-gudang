<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemImportController;
use App\Http\Controllers\ItemTypeController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\RackController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/dashboard'));

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1')->name('login.attempt');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    // Semua peran
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/users/{user}/photo', [UserController::class, 'photo'])->whereNumber('user')->name('users.photo');

    // Data Barang (Part Number)
    Route::middleware('can:inventory.view')->group(function () {
        Route::get('/inventories', [InventoryController::class, 'index'])->name('inventories.index');
        Route::get('/inventories/data/{part_number}', [InventoryController::class, 'getByPartNumber'])->name('inventories.by-part');
    });
    Route::middleware('can:inventory.create')->group(function () {
        Route::get('/inventories/import/template', [InventoryController::class, 'importTemplate'])->name('inventories.import.template');
        Route::post('/inventories/import', [InventoryController::class, 'import'])->name('inventories.import');
        Route::get('/inventories/create', [InventoryController::class, 'create'])->name('inventories.create');
        Route::post('/inventories', [InventoryController::class, 'store'])->name('inventories.store');
    });
    Route::middleware('can:inventory.update')->group(function () {
        Route::get('/inventories/{inventory}/edit', [InventoryController::class, 'edit'])->name('inventories.edit');
        Route::put('/inventories/{inventory}', [InventoryController::class, 'update'])->name('inventories.update');
    });
    Route::delete('/inventories/{inventory}', [InventoryController::class, 'destroy'])->middleware('can:inventory.delete')->name('inventories.destroy');

    // Warehouse & Add Item (Barang Masuk)
    Route::middleware('can:warehouse.view')->group(function () {
        Route::get('/items', [ItemController::class, 'index'])->name('items.index');
        Route::get('/items/search', fn (\Illuminate\Http\Request $request) => redirect()->route('items.part', $request->all()))->name('items.search');
        Route::get('/items/part', [ItemController::class, 'part'])->name('items.part');
        Route::get('/items/{item}', [ItemController::class, 'show'])->whereNumber('item')->name('items.show');
        Route::get('/items-export', [ItemController::class, 'export'])->name('items.export');
    });
    Route::middleware('can:warehouse.create')->group(function () {
        Route::get('/items-import', [ItemImportController::class, 'form'])->name('items.import');
        Route::get('/items-import/template', [ItemImportController::class, 'template'])->name('items.import.template');
        Route::post('/items-import', [ItemImportController::class, 'store'])->name('items.import.store');
        Route::get('/items/create', [ItemController::class, 'create'])->name('items.create');
        Route::post('/items', [ItemController::class, 'store'])->name('items.store');
    });
    Route::middleware('can:warehouse.update')->group(function () {
        Route::get('/items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');
        Route::put('/items/{item}', [ItemController::class, 'update'])->name('items.update');
    });
    Route::delete('/items/{item}', [ItemController::class, 'destroy'])->middleware('can:warehouse.delete')->name('items.destroy');

    // Rack & Warehouse Locations
    Route::get('/racks', [RackController::class, 'index'])->middleware('can:racks.view')->name('racks.index');
    Route::post('/racks', [RackController::class, 'store'])->middleware('can:racks.create')->name('racks.store');
    Route::delete('/racks/{rack}', [RackController::class, 'destroy'])->middleware('can:racks.delete')->name('racks.destroy');
    Route::post('/locations', [LocationController::class, 'store'])->middleware('can:racks.create')->name('locations.store');
    Route::delete('/locations/{location}', [LocationController::class, 'destroy'])->middleware('can:racks.delete')->name('locations.destroy');

    // Kategori (Item Types)
    Route::get('/item-types', [ItemTypeController::class, 'index'])->middleware('can:item-types.view')->name('item-types.index');
    Route::post('/item-types', [ItemTypeController::class, 'store'])->middleware('can:item-types.create')->name('item-types.store');
    Route::delete('/item-types/{item_type}', [ItemTypeController::class, 'destroy'])->middleware('can:item-types.delete')->name('item-types.destroy');

    // Purchase Order (PO)
    Route::middleware('can:purchasing')->group(function () {
        Route::get('/purchasing', [PurchaseOrderController::class, 'index'])->name('purchasing.index');
        Route::get('/purchasing/create', [PurchaseOrderController::class, 'create'])->name('purchasing.create');
        Route::post('/purchasing', [PurchaseOrderController::class, 'store'])->name('purchasing.store');
        Route::get('/purchasing/{purchasing}', [PurchaseOrderController::class, 'show'])->name('purchasing.show');
        Route::patch('/purchasing/{purchasing}/receive', [PurchaseOrderController::class, 'receive'])->name('purchasing.receive');
        Route::patch('/purchasing/{purchasing}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchasing.cancel');
        Route::get('/purchasing/{purchasing}/print', [PurchaseOrderController::class, 'print'])->name('purchasing.print');
        Route::get('/purchasing/{purchasing}/print-pdf', [PurchaseOrderController::class, 'printPdf'])->name('purchasing.print-pdf');
    });

    // Approval PO
    Route::middleware(['can:purchasing', 'can:purchasing.approve'])->group(function () {
        Route::patch('/purchasing/{purchasing}/approve', [PurchaseOrderController::class, 'approve'])->name('purchasing.approve');
        Route::patch('/purchasing/{purchasing}/reject', [PurchaseOrderController::class, 'reject'])->name('purchasing.reject');
    });

    // Suppliers
    Route::get('/suppliers', [SupplierController::class, 'index'])->middleware('can:suppliers.view')->name('suppliers.index');
    Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('can:suppliers.create')->name('suppliers.store');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('can:suppliers.delete')->name('suppliers.delete');
});
