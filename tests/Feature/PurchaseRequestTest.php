<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\PurchaseRequest;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseRequestTest extends TestCase
{
    use RefreshDatabase;

    private function sales(): User
    {
        return User::factory()->create(['role' => 'user', 'permissions' => ['sales']]);
    }

    private function purchasing(): User
    {
        return User::factory()->create(['role' => 'user', 'permissions' => ['purchasing']]);
    }

    private function approver(): User
    {
        return User::factory()->create(['role' => 'user', 'permissions' => ['purchasing', 'purchasing.approve']]);
    }

    /** PR dari SO lalu disetujui, jadi siap dibuatkan PO. */
    private function approvedPr(SalesOrder $so): PurchaseRequest
    {
        $this->actingAs($this->sales())->post("/sales-orders/{$so->id}/purchase-requests");
        $pr = PurchaseRequest::first();
        $this->actingAs($this->approver())->patch("/purchase-requests/{$pr->id}/approve");

        return $pr->fresh();
    }

    private function stock(int $ready): ItemType
    {
        $type = ItemType::create(['name' => 'Router']);
        $loc = Location::create(['name' => 'Lantai 1']);
        for ($i = 1; $i <= $ready; $i++) {
            Item::create([
                'item_id' => "RTR-{$i}", 'item_name' => 'RB951', 'type_id' => $type->id, 'merek' => 'Mikrotik',
                'status' => 'In Stock', 'condition' => 'Baru', 'location_id' => $loc->id,
            ]);
        }

        return $type;
    }

    private function approvedSo(ItemType $type, int $qty): SalesOrder
    {
        $so = SalesOrder::create([
            'so_number' => SalesOrder::nextNumber(),
            'customer_id' => Customer::create(['name' => 'PT Contoh'])->id,
            'order_date' => '2026-09-29', 'status' => 'approved',
        ]);
        $so->items()->create(['item_name' => 'RB951', 'type_id' => $type->id, 'merek' => 'Mikrotik', 'qty' => $qty, 'unit_price' => 500000]);

        return $so;
    }

    public function test_user_without_purchasing_permission_is_blocked(): void
    {
        $u = User::factory()->create(['role' => 'user', 'permissions' => []]);

        $this->actingAs($u)->get('/purchase-requests')->assertForbidden();
    }

    public function test_pr_created_from_shortage_with_sequential_number(): void
    {
        $type = $this->stock(2);
        $so = $this->approvedSo($type, 5); // kurang 3

        $this->actingAs($this->sales())->post("/sales-orders/{$so->id}/purchase-requests")->assertSessionHas('success');

        $year = now()->format('Y');
        $this->assertDatabaseHas('purchase_requests', ['pr_number' => "PR-{$year}-00001", 'status' => 'submitted', 'sales_order_id' => $so->id]);
        $this->assertDatabaseHas('purchase_request_items', ['item_name' => 'RB951', 'qty' => 3]);
    }

    public function test_cannot_create_pr_when_stock_is_sufficient(): void
    {
        $type = $this->stock(5);
        $so = $this->approvedSo($type, 3); // cukup

        $this->actingAs($this->sales())->post("/sales-orders/{$so->id}/purchase-requests")->assertSessionHas('error');
        $this->assertDatabaseCount('purchase_requests', 0);
    }

    public function test_cannot_create_second_active_pr_for_same_so(): void
    {
        $type = $this->stock(0);
        $so = $this->approvedSo($type, 3);
        $u = $this->sales();

        $this->actingAs($u)->post("/sales-orders/{$so->id}/purchase-requests");
        $this->actingAs($u)->post("/sales-orders/{$so->id}/purchase-requests")->assertSessionHas('error');

        $this->assertDatabaseCount('purchase_requests', 1);
    }

    public function test_creating_po_from_pr_prefills_items_and_marks_pr_ordered(): void
    {
        $type = $this->stock(0);
        $so = $this->approvedSo($type, 4);
        $pr = $this->approvedPr($so);
        $supplier = Supplier::create(['name' => 'PT Suplier']);
        $buyer = $this->purchasing();

        $this->actingAs($buyer)->get("/purchasing/create?pr={$pr->id}")->assertOk()->assertSee($pr->pr_number);

        $this->actingAs($buyer)->post('/purchasing', [
            'po_number' => 'PO-TEST-1', 'purchase_request_id' => $pr->id, 'supplier_id' => $supplier->id,
            'order_date' => '2026-09-29',
            'items' => [['item_name' => 'RB951', 'type_id' => $type->id, 'merek' => 'Mikrotik', 'qty_ordered' => 4, 'unit_price' => 600000]],
        ])->assertRedirect();

        $this->assertSame('ordered', $pr->fresh()->status);
        $this->assertDatabaseHas('purchase_orders', ['po_number' => 'PO-TEST-1', 'purchase_request_id' => $pr->id]);
    }

    public function test_cancelling_the_only_po_reopens_the_pr(): void
    {
        $type = $this->stock(0);
        $so = $this->approvedSo($type, 2);
        $pr = $this->approvedPr($so);
        $supplier = Supplier::create(['name' => 'PT Suplier']);
        $buyer = $this->purchasing();

        $this->actingAs($buyer)->post('/purchasing', [
            'po_number' => 'PO-TEST-2', 'purchase_request_id' => $pr->id, 'supplier_id' => $supplier->id,
            'order_date' => '2026-09-29',
            'items' => [['item_name' => 'RB951', 'type_id' => $type->id, 'qty_ordered' => 2]],
        ]);
        $po = \App\Models\PurchaseOrder::where('po_number', 'PO-TEST-2')->first();

        $this->actingAs($buyer)->patch("/purchasing/{$po->id}/cancel")->assertSessionHas('success');

        $this->assertSame('open', $pr->fresh()->status);
    }

    public function test_open_pr_can_be_cancelled_directly(): void
    {
        $type = $this->stock(0);
        $so = $this->approvedSo($type, 1);
        $this->actingAs($this->sales())->post("/sales-orders/{$so->id}/purchase-requests");
        $pr = PurchaseRequest::first();

        $this->actingAs($this->purchasing())->patch("/purchase-requests/{$pr->id}/cancel")->assertSessionHas('success');
        $this->assertSame('cancelled', $pr->fresh()->status);
    }

    public function test_pages_render(): void
    {
        $type = $this->stock(0);
        $so = $this->approvedSo($type, 1);
        $this->actingAs($this->sales())->post("/sales-orders/{$so->id}/purchase-requests");
        $pr = PurchaseRequest::first();
        $u = $this->purchasing();

        $this->actingAs($u)->get('/purchase-requests')->assertOk()->assertSee($pr->pr_number);
        $this->actingAs($u)->get("/purchase-requests/{$pr->id}")->assertOk()->assertSee($so->so_number);
        $this->actingAs($this->sales())->get("/sales-orders/{$so->id}")->assertOk()->assertSee($pr->pr_number);
    }
}
