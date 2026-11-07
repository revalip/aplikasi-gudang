<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Customer;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $sales;
    private User $buyer;
    private User $approver;
    private ItemType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sales = User::factory()->create(['role' => 'user', 'permissions' => ['sales']]);
        $this->buyer = User::factory()->create(['role' => 'user', 'permissions' => ['purchasing']]);
        $this->approver = User::factory()->create(['role' => 'user', 'permissions' => ['purchasing', 'purchasing.approve']]);
        $this->type = ItemType::create(['name' => 'Router']);
        Location::create(['name' => 'Stuff In']);
    }

    private function pr(): PurchaseRequest
    {
        $so = SalesOrder::create([
            'so_number' => SalesOrder::nextNumber(), 'customer_id' => Customer::create(['name' => 'PT Contoh'])->id,
            'order_date' => '2026-10-17', 'status' => 'approved',
        ]);
        $so->items()->create(['item_name' => 'RB951', 'type_id' => $this->type->id, 'merek' => 'Mikrotik', 'qty' => 2, 'unit_price' => 1]);
        $this->actingAs($this->sales)->post("/sales-orders/{$so->id}/purchase-requests");

        return PurchaseRequest::first();
    }

    private function po(?PurchaseRequest $pr = null): PurchaseOrder
    {
        $this->actingAs($this->buyer)->post('/purchasing', [
            'po_number' => 'PO-APV-1', 'purchase_request_id' => $pr?->id, 'supplier_id' => Supplier::create(['name' => 'PT Suplier'])->id,
            'order_date' => '2026-10-17',
            'items' => [['item_name' => 'RB951', 'type_id' => $this->type->id, 'merek' => 'Mikrotik', 'qty_ordered' => 2]],
        ]);

        return PurchaseOrder::where('po_number', 'PO-APV-1')->first();
    }

    public function test_new_pr_waits_for_approval_and_notifies_approvers(): void
    {
        $pr = $this->pr();

        $this->assertSame('submitted', $pr->status);
        $this->assertTrue(AppNotification::where('user_id', $this->approver->id)->where('title', 'like', "%{$pr->pr_number}%")->exists());
        $this->actingAs($this->buyer)->get("/purchasing/create?pr={$pr->id}")->assertOk()->assertDontSee('This PO is created from');
    }

    public function test_approving_pr_opens_it_for_purchasing_with_history(): void
    {
        $pr = $this->pr();

        $this->actingAs($this->approver)->patch("/purchase-requests/{$pr->id}/approve", ['note' => 'OK'])->assertSessionHas('success');

        $pr->refresh();
        $this->assertSame('open', $pr->status);
        $this->assertSame($this->approver->id, $pr->approved_by);
        $this->assertNotNull($pr->approved_at);
        $this->assertTrue(AppNotification::where('user_id', $this->sales->id)->where('title', "PR {$pr->pr_number} approved")->exists());
        $this->assertTrue(AppNotification::where('user_id', $this->buyer->id)->where('title', "PR {$pr->pr_number} approved")->exists());
    }

    public function test_rejecting_pr_needs_a_reason(): void
    {
        $pr = $this->pr();

        $this->actingAs($this->approver)->patch("/purchase-requests/{$pr->id}/reject")->assertSessionHasErrors('note');
        $this->assertSame('submitted', $pr->fresh()->status);

        $this->actingAs($this->approver)->patch("/purchase-requests/{$pr->id}/reject", ['note' => 'Pakai stok cabang'])->assertSessionHas('success');
        $this->assertSame('rejected', $pr->fresh()->status);
        $this->assertSame('Pakai stok cabang', $pr->fresh()->approval_note);
    }

    public function test_user_without_approval_permission_is_blocked(): void
    {
        $pr = $this->pr();

        $this->actingAs($this->buyer)->patch("/purchase-requests/{$pr->id}/approve")->assertForbidden();
        $this->assertSame('submitted', $pr->fresh()->status);
    }

    public function test_cannot_approve_own_po(): void
    {
        $boss = User::factory()->create(['role' => 'user', 'permissions' => ['purchasing', 'purchasing.approve']]);
        $this->buyer = $boss;
        $po = $this->po();

        $this->actingAs($boss)->patch("/purchasing/{$po->id}/approve")->assertSessionHas('error');
        $this->assertSame('draft', $po->fresh()->status);
    }

    public function test_po_must_be_approved_before_receiving(): void
    {
        $po = $this->po();
        $line = $po->items()->first();
        $loc = Location::first();
        $receive = fn () => $this->actingAs($this->buyer)->patch("/purchasing/{$po->id}/receive", [
            'receive' => [$line->id => 1], 'location_id' => $loc->id,
        ]);

        $this->assertSame('draft', $po->status);
        $receive()->assertSessionHas('error');
        $this->assertSame(0, $line->fresh()->qty_received);

        $this->actingAs($this->approver)->patch("/purchasing/{$po->id}/approve")->assertSessionHas('success');
        $this->assertSame('ordered', $po->fresh()->status);
        $this->assertTrue(AppNotification::where('user_id', $this->buyer->id)->where('title', 'PO PO-APV-1 approved')->exists());

        $receive()->assertSessionHas('success');
        $this->assertSame(1, $line->fresh()->qty_received);
    }

    public function test_rejecting_po_reopens_its_pr(): void
    {
        $pr = $this->pr();
        $this->actingAs($this->approver)->patch("/purchase-requests/{$pr->id}/approve");
        $po = $this->po($pr->fresh());
        $this->assertSame('ordered', $pr->fresh()->status);

        $this->actingAs($this->approver)->patch("/purchasing/{$po->id}/reject", ['note' => 'Harga kemahalan'])->assertSessionHas('success');

        $this->assertSame('rejected', $po->fresh()->status);
        $this->assertSame('open', $pr->fresh()->status);
    }

    public function test_pages_show_approval_panel_and_dashboard_cards(): void
    {
        $pr = $this->pr();
        $po = $this->po();

        $this->actingAs($this->approver)->get("/purchase-requests/{$pr->id}")->assertOk()->assertSee('Approve');
        $this->actingAs($this->approver)->get("/purchasing/{$po->id}")->assertOk()->assertSee('Approve')->assertSee('Pending Approval');
        $this->actingAs($this->buyer)->get("/purchasing/{$po->id}")->assertOk()->assertDontSee('Approve')->assertSee('Item receiving will open after this PO is approved');
        $this->actingAs($this->approver)->get('/dashboard')->assertOk()->assertSee('PR pending approval')->assertSee('PO pending approval');
        $this->actingAs($this->buyer)->get('/dashboard')->assertOk()->assertDontSee('PO pending approval');
    }

    public function test_superadmin_can_approve_own_po_and_pr(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $this->buyer = $superadmin;
        $po = $this->po();

        $this->actingAs($superadmin)->get("/purchasing/{$po->id}")
            ->assertOk()->assertSee('Approve');

        $this->actingAs($superadmin)->patch("/purchasing/{$po->id}/approve")->assertSessionHas('success');
        $this->assertSame('ordered', $po->fresh()->status);
    }
}
