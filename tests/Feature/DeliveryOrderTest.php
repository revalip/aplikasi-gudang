<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryOrderTest extends TestCase
{
    use RefreshDatabase;

    private function gudang(): User
    {
        return User::factory()->create(['role' => 'user', 'permissions' => ['delivery', 'sales']]);
    }

    /** DO sekarang wajib ada WO yang sudah diterima buat SO itu — helper ini bikinin otomatis. */
    private function acceptWoFor(SalesOrder $so): void
    {
        $wo = WorkOrder::create([
            'wo_number' => WorkOrder::nextNumber(), 'sales_order_id' => $so->id, 'status' => 'issued',
            'wo_date' => now()->toDateString(), 'subject' => 'Tes',
        ]);
        $wo->update(['status' => 'accepted', 'accepted_at' => now()]);
    }

    private function stock(int $n, bool $withSn = true): ItemType
    {
        $type = ItemType::firstOrCreate(['name' => 'Router']);
        $loc = Location::firstOrCreate(['name' => 'Lantai 1']);
        $start = Item::count();
        for ($i = 1; $i <= $n; $i++) {
            $no = $start + $i;
            Item::create([
                'item_id' => "RTR-{$no}", 'item_name' => 'RB951', 'type_id' => $type->id, 'merek' => 'Mikrotik',
                'serial_number' => $withSn ? "SN{$no}" : null,
                'status' => 'In Stock', 'condition' => 'Baru', 'location_id' => $loc->id,
            ]);
        }

        return $type;
    }

    private function approvedSo(ItemType $type, int $qty, string $status = 'approved'): SalesOrder
    {
        $so = SalesOrder::create([
            'so_number' => SalesOrder::nextNumber(),
            'customer_id' => Customer::firstOrCreate(['name' => 'PT Contoh'])->id,
            'order_date' => '2026-09-30', 'status' => $status,
        ]);
        $so->items()->create(['item_name' => 'RB951', 'type_id' => $type->id, 'merek' => 'Mikrotik', 'qty' => $qty, 'unit_price' => 500000]);

        if (in_array($status, ['approved', 'processing'], true)) {
            $this->acceptWoFor($so);
        }

        return $so;
    }

    public function test_user_without_delivery_permission_is_blocked(): void
    {
        $u = User::factory()->create(['role' => 'user', 'permissions' => ['sales']]);
        $so = $this->approvedSo($this->stock(2), 1);

        $this->actingAs($u)->get('/delivery-orders')->assertForbidden();
        $this->actingAs($u)->post("/sales-orders/{$so->id}/delivery-orders")->assertForbidden();
    }

    public function test_create_do_reserves_units_and_sets_so_processing(): void
    {
        $so = $this->approvedSo($this->stock(5), 3);

        $this->actingAs($this->gudang())->post("/sales-orders/{$so->id}/delivery-orders")->assertRedirect();

        $do = DeliveryOrder::first();
        $this->assertMatchesRegularExpression('/^DO-\d{4}-00001$/', $do->do_number);
        $this->assertSame('picking', $do->status);
        $this->assertCount(3, $do->items);
        $this->assertSame(3, Item::whereNotNull('reserved_sales_order_item_id')->count());
        $this->assertSame('processing', $so->fresh()->status);
        $this->assertSame(2, $so->items->first()->available); // sisa 2 unit bebas
    }

    public function test_partial_do_when_stock_is_short(): void
    {
        $so = $this->approvedSo($this->stock(3), 5);

        $this->actingAs($this->gudang())->post("/sales-orders/{$so->id}/delivery-orders");

        $this->assertCount(3, DeliveryOrder::first()->items);
        $line = $so->items()->first();
        $this->assertSame(2, $line->remaining);
        $this->assertSame(2, $line->shortage);
    }

    public function test_no_stock_creates_no_do(): void
    {
        $type = $this->stock(1);
        Item::query()->update(['status' => 'Borrow']);
        $so = $this->approvedSo($type, 2);

        $this->actingAs($this->gudang())->post("/sales-orders/{$so->id}/delivery-orders")->assertSessionHas('error');

        $this->assertDatabaseCount('delivery_orders', 0);
        $this->assertSame('approved', $so->fresh()->status);
    }

    public function test_only_approved_so_can_be_processed(): void
    {
        $so = $this->approvedSo($this->stock(2), 1, 'submitted');

        $this->actingAs($this->gudang())->post("/sales-orders/{$so->id}/delivery-orders")->assertSessionHas('error');
        $this->assertDatabaseCount('delivery_orders', 0);
    }

    public function test_reserved_units_are_not_taken_by_another_so(): void
    {
        $type = $this->stock(3);
        $so1 = $this->approvedSo($type, 3);
        $so2 = $this->approvedSo($type, 2);
        $u = $this->gudang();

        $this->actingAs($u)->post("/sales-orders/{$so1->id}/delivery-orders");
        $this->actingAs($u)->post("/sales-orders/{$so2->id}/delivery-orders")->assertSessionHas('error');

        $this->assertDatabaseCount('delivery_orders', 1);
        $this->assertSame(0, $so2->items->first()->available);
    }

    public function test_scan_accepts_valid_rejects_wrong_and_duplicate(): void
    {
        $so = $this->approvedSo($this->stock(3), 2);
        $u = $this->gudang();
        $this->actingAs($u)->post("/sales-orders/{$so->id}/delivery-orders");
        $do = DeliveryOrder::first();
        $reserved = Item::whereNotNull('reserved_sales_order_item_id')->orderBy('id')->get();
        $free = Item::whereNull('reserved_sales_order_item_id')->first();

        $this->actingAs($u)->post("/delivery-orders/{$do->id}/scan", ['serial_number' => strtolower($reserved[0]->serial_number)])->assertSessionHas('success');
        $this->assertSame(1, $do->items()->whereNotNull('scanned_at')->count());

        $this->actingAs($u)->post("/delivery-orders/{$do->id}/scan", ['serial_number' => $reserved[0]->serial_number])->assertSessionHas('error');
        $this->actingAs($u)->post("/delivery-orders/{$do->id}/scan", ['serial_number' => $free->serial_number])->assertSessionHas('error');
        $this->actingAs($u)->post("/delivery-orders/{$do->id}/scan", ['serial_number' => 'NGAWUR'])->assertSessionHas('error');
        $this->assertSame(1, $do->items()->whereNotNull('scanned_at')->count());
    }

    public function test_ship_requires_all_scanned_then_removes_stock_and_logs_scan_out(): void
    {
        $so = $this->approvedSo($this->stock(2), 2);
        $u = $this->gudang();
        $this->actingAs($u)->post("/sales-orders/{$so->id}/delivery-orders");
        $do = DeliveryOrder::first();
        $units = Item::whereNotNull('reserved_sales_order_item_id')->orderBy('id')->get();

        $this->actingAs($u)->patch("/delivery-orders/{$do->id}/ship")->assertSessionHas('error');
        $this->assertSame('picking', $do->fresh()->status);

        foreach ($units as $unit) {
            $this->actingAs($u)->post("/delivery-orders/{$do->id}/scan", ['serial_number' => $unit->serial_number]);
        }
        $this->actingAs($u)->patch("/delivery-orders/{$do->id}/ship")->assertSessionHas('success');

        $this->assertSame('delivered', $do->fresh()->status);
        $this->assertSame('delivered', $so->fresh()->status);
        foreach ($units as $unit) {
            $unit->refresh();
            $this->assertSame('Sold Out', $unit->status);
            $this->assertNull($unit->reserved_sales_order_item_id);
            $this->assertNotNull($unit->stuff_out);
            $this->assertDatabaseHas('serial_scans', ['item_id' => $unit->id, 'direction' => 'out']);
        }
    }

    public function test_partial_shipment_keeps_so_processing(): void
    {
        $so = $this->approvedSo($this->stock(2), 4);
        $u = $this->gudang();
        $this->actingAs($u)->post("/sales-orders/{$so->id}/delivery-orders");
        $do = DeliveryOrder::first();
        foreach (Item::whereNotNull('reserved_sales_order_item_id')->get() as $unit) {
            $this->actingAs($u)->post("/delivery-orders/{$do->id}/scan", ['serial_number' => $unit->serial_number]);
        }
        $this->actingAs($u)->patch("/delivery-orders/{$do->id}/ship");

        $this->assertSame('processing', $so->fresh()->status);
        $this->assertSame(2, $so->items->first()->delivered_qty);
        $this->assertSame(2, $so->items->first()->remaining);
    }

    public function test_units_without_sn_are_marked_manually(): void
    {
        $so = $this->approvedSo($this->stock(1, withSn: false), 1);
        $u = $this->gudang();
        $this->actingAs($u)->post("/sales-orders/{$so->id}/delivery-orders");
        $do = DeliveryOrder::first();
        $row = $do->items->first();

        $this->actingAs($u)->patch("/delivery-orders/{$do->id}/items/{$row->id}/pick")->assertSessionHas('success');
        $this->actingAs($u)->patch("/delivery-orders/{$do->id}/ship")->assertSessionHas('success');
        $this->assertSame('Sold Out', $row->item->fresh()->status);
    }

    public function test_cancel_do_releases_units_and_restores_so_status(): void
    {
        $so = $this->approvedSo($this->stock(2), 2);
        $u = $this->gudang();
        $this->actingAs($u)->post("/sales-orders/{$so->id}/delivery-orders");
        $do = DeliveryOrder::first();

        $this->actingAs($u)->patch("/delivery-orders/{$do->id}/cancel")->assertSessionHas('success');

        $this->assertSame('cancelled', $do->fresh()->status);
        $this->assertSame(0, Item::whereNotNull('reserved_sales_order_item_id')->count());
        $this->assertSame('approved', $so->fresh()->status);
        $this->assertSame(2, $so->items->first()->available);
    }

    public function test_pages_render(): void
    {
        $so = $this->approvedSo($this->stock(2), 2);
        $u = $this->gudang();
        $this->actingAs($u)->post("/sales-orders/{$so->id}/delivery-orders");
        $do = DeliveryOrder::first();

        $this->actingAs($u)->get('/delivery-orders')->assertOk()->assertSee($do->do_number);
        $this->actingAs($u)->get("/delivery-orders/{$do->id}")->assertOk()->assertSee('Scan Serial Number');
        $this->actingAs($u)->get("/sales-orders/{$so->id}")->assertOk()->assertSee($do->do_number);
    }
}
