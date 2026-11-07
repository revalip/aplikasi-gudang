<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseReceiveTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;
    private ItemType $type;
    private Location $stuffIn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = User::factory()->create(['role' => 'user', 'permissions' => ['purchasing']]);
        $this->type = ItemType::create(['name' => 'Router']);
        Location::create(['name' => 'Lantai 1']);
        $this->stuffIn = Location::create(['name' => 'Stuff In']);
    }

    private function po(int $qty, ?int $typeId = null): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-202610-0001', 'supplier_id' => Supplier::create(['name' => 'PT Suplier'])->id,
            'order_date' => '2026-10-17', 'status' => 'ordered',
        ]);
        $po->items()->create([
            'item_name' => 'RB951', 'type_id' => $typeId ?? $this->type->id, 'merek' => 'Mikrotik', 'qty_ordered' => $qty,
        ]);

        return $po;
    }

    private function receive(PurchaseOrder $po, int $qty, string $serials = '')
    {
        $line = $po->items()->first();

        return $this->actingAs($this->buyer)->patch("/purchasing/{$po->id}/receive", [
            'receive' => [$line->id => $qty],
            'serials' => [$line->id => $serials],
            'location_id' => $this->stuffIn->id,
        ]);
    }

    public function test_received_units_are_created_in_data_master(): void
    {
        $po = $this->po(3);

        $this->receive($po, 3, "SN-A\nSN-B\nSN-C")->assertSessionHas('success');

        $this->assertSame(3, Item::where('no_po_spr', 'PO-202610-0001')->count());
        $this->assertDatabaseHas('items', [
            'item_id' => 'MJG-RTR-0001', 'item_name' => 'RB951', 'type_id' => $this->type->id, 'merek' => 'Mikrotik',
            'serial_number' => 'SN-A', 'status' => 'In Stock', 'condition' => 'Baru', 'location_id' => $this->stuffIn->id,
        ]);
        $this->assertDatabaseHas('items', ['item_id' => 'MJG-RTR-0003', 'serial_number' => 'SN-C']);
        $this->assertDatabaseHas('serial_scans', ['serial_number' => 'SN-B', 'direction' => 'in', 'location_id' => $this->stuffIn->id]);
        $this->assertSame('received', $po->fresh()->status);
    }

    public function test_item_id_continues_from_existing_numbers(): void
    {
        Item::create([
            'item_id' => 'MJG-RTR-0041', 'item_name' => 'hAP ac2', 'type_id' => $this->type->id,
            'status' => 'In Stock', 'condition' => 'Normal', 'location_id' => $this->stuffIn->id,
        ]);

        $this->receive($this->po(2), 2);

        $this->assertDatabaseHas('items', ['item_id' => 'MJG-RTR-0042']);
        $this->assertDatabaseHas('items', ['item_id' => 'MJG-RTR-0043']);
    }

    public function test_partial_receive_without_serials_creates_units_with_empty_sn(): void
    {
        $po = $this->po(5);

        $this->receive($po, 2, 'SN-ONLY')->assertSessionHas('success');

        $this->assertSame(2, $po->units()->count());
        $this->assertSame(1, $po->units()->whereNull('serial_number')->count());
        $this->assertSame('partial', $po->fresh()->status);
        $this->assertDatabaseCount('serial_scans', 1);

        // Terima sisanya: total unit = qty order, tidak lebih.
        $this->receive($po, 10);
        $this->assertSame(5, $po->units()->count());
        $this->assertSame('received', $po->fresh()->status);
    }

    public function test_received_units_count_as_free_stock_for_sales_orders(): void
    {
        $this->receive($this->po(2), 2);

        $so = \App\Models\SalesOrder::create([
            'so_number' => \App\Models\SalesOrder::nextNumber(),
            'customer_id' => \App\Models\Customer::create(['name' => 'PT Contoh'])->id,
            'order_date' => '2026-10-17', 'status' => 'approved',
        ]);
        $line = $so->items()->create(['item_name' => 'RB951', 'type_id' => $this->type->id, 'merek' => 'Mikrotik', 'qty' => 2, 'unit_price' => 1]);

        $this->assertSame(2, $line->available);
        $this->assertSame(0, $line->shortage);
    }

    public function test_more_serials_than_qty_is_rejected_and_nothing_saved(): void
    {
        $po = $this->po(3);

        $this->receive($po, 1, "SN-A\nSN-B")->assertSessionHasErrors();

        $this->assertDatabaseCount('items', 0);
        $this->assertSame(0, $po->items()->first()->qty_received);
    }

    public function test_duplicate_or_existing_serials_are_rejected(): void
    {
        $po = $this->po(3);

        $this->receive($po, 2, "SN-A\nsn-a")->assertSessionHasErrors();
        $this->assertDatabaseCount('items', 0);

        Item::create([
            'item_id' => 'X-1', 'item_name' => 'Lama', 'type_id' => $this->type->id, 'serial_number' => 'SN-LAMA',
            'status' => 'In Stock', 'condition' => 'Normal', 'location_id' => $this->stuffIn->id,
        ]);
        $this->receive($po, 1, 'SN-LAMA')->assertSessionHasErrors();
        $this->assertDatabaseCount('items', 1);
    }

    public function test_empty_receive_shows_error(): void
    {
        $this->receive($this->po(1), 0)->assertSessionHas('error');
        $this->assertDatabaseCount('items', 0);
    }

    public function test_po_line_must_have_type(): void
    {
        $this->actingAs($this->buyer)->post('/purchasing', [
            'po_number' => 'PO-NOTYPE', 'supplier_id' => Supplier::create(['name' => 'PT A'])->id, 'order_date' => '2026-10-17',
            'items' => [['item_name' => 'RB951', 'qty_ordered' => 1]],
        ])->assertSessionHasErrors('items.0.type_id');
    }

    public function test_detail_page_shows_receive_form_and_units(): void
    {
        $po = $this->po(2);
        $this->actingAs($this->buyer)->get("/purchasing/{$po->id}")->assertOk()->assertSee('Store in Location')->assertSee('Stuff In');

        $this->receive($po, 1, 'SN-SHOW');
        $this->actingAs($this->buyer)->get("/purchasing/{$po->id}")->assertOk()
            ->assertSee('Units Added to Warehouse from this PO')->assertSee('MJG-RTR-0001')->assertSee('SN-SHOW');
    }

    public function test_po_with_part_number_and_warranty_inherits_to_received_units(): void
    {
        $inv = \App\Models\Inventory::create([
            'part_number' => 'PN-TEST-99',
            'brand' => 'Mikrotik',
            'type_id' => $this->type->id,
            'warranty' => '1 Years',
            'description' => 'Router Test Specs',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-WARRANTY-1',
            'supplier_id' => Supplier::create(['name' => 'Supplier Garansi'])->id,
            'order_date' => '2026-10-17',
            'status' => 'ordered',
        ]);

        $po->items()->create([
            'inventory_id' => $inv->id,
            'part_number' => 'PN-TEST-99',
            'item_name' => 'Router Test Specs',
            'type_id' => $this->type->id,
            'merek' => 'Mikrotik',
            'warranty' => '1 Years',
            'qty_ordered' => 1,
        ]);

        $this->receive($po, 1, 'SN-PN-WAR-1')->assertSessionHas('success');

        $this->assertDatabaseHas('items', [
            'item_id' => 'MJG-RTR-0001',
            'inventory_id' => $inv->id,
            'part_number' => 'PN-TEST-99',
            'item_name' => 'Router Test Specs',
            'serial_number' => 'SN-PN-WAR-1',
            'warranty' => '1 Years',
            'no_po_spr' => 'PO-WARRANTY-1',
        ]);
    }

    public function test_user_can_create_po_with_part_number_and_warranty_and_custom_supplier(): void
    {
        $inv = \App\Models\Inventory::create([
            'part_number' => 'PN-STORE-01',
            'brand' => 'Cisco',
            'type_id' => $this->type->id,
            'warranty' => '2 Years',
            'description' => 'Cisco Catalyst Switch',
        ]);

        $response = $this->actingAs($this->buyer)->post('/purchasing', [
            'po_number' => 'PO-NEW-101',
            'supplier_name' => 'Supplier Baru Harco',
            'order_date' => '2026-10-23',
            'items' => [
                [
                    'part_number' => 'PN-STORE-01',
                    'item_name' => 'PN',
                    'description' => 'Cisco Catalyst Switch 24 Port',
                    'type_name' => 'Router',
                    'merek' => 'Cisco',
                    'warranty' => '2 Years',
                    'qty_ordered' => 2,
                    'unit_price' => 1500000,
                ],
            ],
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('suppliers', ['name' => 'Supplier Baru Harco']);
        $this->assertDatabaseHas('purchase_orders', ['po_number' => 'PO-NEW-101']);
        $this->assertDatabaseHas('purchase_order_items', [
            'part_number' => 'PN-STORE-01',
            'item_name' => 'PN',
            'description' => 'Cisco Catalyst Switch 24 Port',
            'inventory_id' => $inv->id,
            'warranty' => '2 Years',
            'qty_ordered' => 2,
        ]);
    }

    public function test_purchasing_create_page_renders_with_multiline_inventory(): void
    {
        \App\Models\Inventory::create([
            'part_number' => 'PN-MULTILINE-TEST',
            'brand' => 'Mikrotik',
            'type_id' => $this->type->id,
            'description' => "Spesifikasi:\n- 128MB RAM\n- 5 Port Gigabit\n\"Quotes\" & <tags>",
        ]);

        $response = $this->actingAs($this->buyer)->get('/purchasing/create');
        $response->assertOk();
        $response->assertSee('PN-MULTILINE-TEST');
        $response->assertSee('Ordered Items');
    }
}
