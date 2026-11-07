<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $perms = ['items.manage']): User
    {
        return User::factory()->create(['role' => 'user', 'permissions' => $perms]);
    }

    public function test_can_view_inventory_list(): void
    {
        $user = $this->user();
        $type = ItemType::create(['name' => 'Switch']);

        Inventory::create([
            'part_number' => 'WS-C2960-24TT-L',
            'brand' => 'Cisco',
            'type_id' => $type->id,
            'warranty' => '1 Year',
            'description' => '24 Port 10/100 + 2 1000BT',
        ]);

        $res = $this->actingAs($user)->get(route('inventories.index'));
        $res->assertOk();
        $res->assertSee('WS-C2960-24TT-L');
        $res->assertSee('Cisco');
        $res->assertSee('1 Year');
    }

    public function test_can_create_inventory_with_free_text_type_and_warranty(): void
    {
        $user = $this->user(['items.manage']);

        $res = $this->actingAs($user)->post(route('inventories.store'), [
            'part_number' => 'CCR2004-1G-12S+2XS',
            'brand' => 'MikroTik',
            'type_name' => 'Router Core',
            'warranty' => '2 Years',
            'description' => '16 Core Cloud Core Router',
        ]);

        $res->assertRedirect(route('inventories.index'));
        $res->assertSessionHas('success');

        $this->assertDatabaseHas('inventories', [
            'part_number' => 'CCR2004-1G-12S+2XS',
            'brand' => 'MikroTik',
            'warranty' => '2 Years',
            'description' => '16 Core Cloud Core Router',
        ]);

        $this->assertDatabaseHas('item_types', ['name' => 'Router Core']);
    }

    public function test_can_fetch_inventory_by_part_number_json(): void
    {
        $user = $this->user();
        $type = ItemType::create(['name' => 'SFP']);

        Inventory::create([
            'part_number' => 'S-85DLC05D',
            'brand' => 'MikroTik',
            'type_id' => $type->id,
            'warranty' => '6 Months',
            'description' => '1.25G Multi Mode SFP Transceiver',
        ]);

        $res = $this->actingAs($user)->get(route('inventories.by-part', 'S-85DLC05D'));
        $res->assertOk();
        $res->assertJsonFragment([
            'part_number' => 'S-85DLC05D',
            'brand' => 'MikroTik',
            'type_name' => 'SFP',
            'warranty' => '6 Months',
        ]);
    }

    public function test_can_update_inventory(): void
    {
        $user = $this->user(['items.manage']);
        $inv = Inventory::create([
            'part_number' => 'OLD-PN-001',
            'brand' => 'HP',
            'warranty' => '1 Year',
        ]);

        $res = $this->actingAs($user)->put(route('inventories.update', $inv), [
            'part_number' => 'NEW-PN-001',
            'brand' => 'HPE Aruba',
            'warranty' => '3 Years',
            'description' => 'Updated Description',
        ]);

        $res->assertRedirect(route('inventories.index'));
        $this->assertDatabaseHas('inventories', [
            'id' => $inv->id,
            'part_number' => 'NEW-PN-001',
            'brand' => 'HPE Aruba',
            'warranty' => '3 Years',
        ]);
    }

    public function test_can_add_multiple_items_by_scanning_multiple_serial_numbers(): void
    {
        $user = $this->user(['items.manage']);
        $type = ItemType::create(['name' => 'Switch']);
        $loc = Location::create(['name' => 'Lantai 2']);
        $supplier = Supplier::create(['name' => 'PT Supplier Utama']);
        $po = PurchaseOrder::create([
            'po_number' => 'PO-2026-0001',
            'supplier_id' => $supplier->id,
            'order_date' => '2026-10-23',
            'status' => 'Approved',
        ]);

        $inv = Inventory::create([
            'part_number' => 'WS-C2960-24TT-L',
            'brand' => 'Cisco',
            'type_id' => $type->id,
            'description' => '24 Port Switch',
        ]);

        // Input 3 serial numbers in textarea (3 rows = 3 units)
        $snsText = "SN-CISCO-001\nSN-CISCO-002\nSN-CISCO-003";

        $res = $this->actingAs($user)->post(route('items.store'), [
            'item_name' => 'Cisco Catalyst 2960',
            'inventory_id' => $inv->id,
            'part_number' => $inv->part_number,
            'type_id' => $type->id,
            'merek' => 'Cisco',
            'status' => 'In Stock',
            'condition' => 'Baru',
            'location_id' => $loc->id,
            'rack' => 'Rak A-01',
            'no_po_spr' => $po->po_number,
            'serial_numbers_text' => $snsText,
        ]);

        $res->assertRedirect(route('items.index', ['location' => $loc->id]));
        $res->assertSessionHas('success');

        // Verify 3 distinct items created
        $this->assertEquals(3, Item::where('part_number', 'WS-C2960-24TT-L')->count());
        $this->assertDatabaseHas('items', ['serial_number' => 'SN-CISCO-001', 'rack' => 'Rak A-01', 'no_po_spr' => 'PO-2026-0001']);
        $this->assertDatabaseHas('items', ['serial_number' => 'SN-CISCO-002', 'rack' => 'Rak A-01', 'no_po_spr' => 'PO-2026-0001']);
        $this->assertDatabaseHas('items', ['serial_number' => 'SN-CISCO-003', 'rack' => 'Rak A-01', 'no_po_spr' => 'PO-2026-0001']);
    }

    public function test_rejects_duplicate_serial_number_in_batch_scan(): void
    {
        $user = $this->user(['items.manage']);
        $type = ItemType::create(['name' => 'Switch']);
        $loc = Location::create(['name' => 'Lantai 2']);

        // Input duplicate serial numbers
        $snsText = "SN-DUPLICATE-001\nSN-DUPLICATE-001";

        $res = $this->actingAs($user)->post(route('items.store'), [
            'item_name' => 'Cisco Catalyst 2960',
            'type_id' => $type->id,
            'status' => 'In Stock',
            'condition' => 'Baru',
            'location_id' => $loc->id,
            'serial_numbers_text' => $snsText,
        ]);

        $res->assertSessionHasErrors('serial_numbers_text');
        $this->assertEquals(0, Item::count());
    }
}

