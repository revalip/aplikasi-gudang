<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderTest extends TestCase
{
    use RefreshDatabase;

    private function sales(array $extra = []): User
    {
        return User::factory()->create(['role' => 'user', 'permissions' => array_merge(['sales'], $extra)]);
    }

    private function seedStock(int $ready, int $other = 0): ItemType
    {
        $type = ItemType::create(['name' => 'Router']);
        $loc = Location::create(['name' => 'Lantai 1']);
        for ($i = 1; $i <= $ready + $other; $i++) {
            Item::create([
                'item_id' => "RTR-{$i}", 'item_name' => 'RB951', 'type_id' => $type->id, 'merek' => 'Mikrotik',
                'status' => $i <= $ready ? 'In Stock' : 'Borrow', 'condition' => 'Baru', 'location_id' => $loc->id,
            ]);
        }

        return $type;
    }

    private function payload(ItemType $type, int $qty, array $extra = []): array
    {
        return array_merge([
            'customer_id' => Customer::firstOrCreate(['name' => 'PT Contoh'])->id,
            'order_date' => '2026-09-29',
            'items' => [['item_name' => 'RB951', 'type_id' => $type->id, 'merek' => 'Mikrotik', 'qty' => $qty, 'unit_price' => 500000]],
        ], $extra);
    }

    public function test_user_without_sales_permission_is_blocked(): void
    {
        $viewer = User::factory()->create(['role' => 'user', 'permissions' => []]);

        foreach (['/sales-orders', '/sales-orders/create', '/customers'] as $url) {
            $this->actingAs($viewer)->get($url)->assertForbidden();
        }
    }

    public function test_create_so_gets_automatic_sequential_number(): void
    {
        $type = $this->seedStock(5);
        $user = $this->sales();

        $this->actingAs($user)->post('/sales-orders', $this->payload($type, 2))->assertRedirect();
        $this->actingAs($user)->post('/sales-orders', $this->payload($type, 1))->assertRedirect();

        $year = now()->format('Y');
        $this->assertDatabaseHas('sales_orders', ['so_number' => "SO-{$year}-00001", 'status' => 'draft']);
        $this->assertDatabaseHas('sales_orders', ['so_number' => "SO-{$year}-00002"]);
        $this->assertDatabaseCount('sales_order_items', 2);
    }

    public function test_save_and_submit_sets_submitted(): void
    {
        $type = $this->seedStock(5);

        $this->actingAs($this->sales())->post('/sales-orders', $this->payload($type, 2, ['submit_now' => '1']));

        $so = SalesOrder::first();
        $this->assertSame('submitted', $so->status);
        $this->assertNotNull($so->submitted_at);
    }

    public function test_validation_requires_customer_and_items(): void
    {
        $this->actingAs($this->sales())
            ->post('/sales-orders', ['order_date' => '2026-09-29'])
            ->assertSessionHasErrors(['customer_id', 'items']);
    }

    public function test_stock_check_counts_only_in_stock_units(): void
    {
        $type = $this->seedStock(ready: 3, other: 2); // 3 In Stock + 2 Borrow
        $user = $this->sales();

        $this->actingAs($user)->post('/sales-orders', $this->payload($type, 5, ['submit_now' => '1']));
        $line = SalesOrder::first()->items->first();

        $this->assertSame(3, $line->available);
        $this->assertSame(2, $line->shortage);
        $this->actingAs($user)->get('/sales-orders/' . SalesOrder::first()->id)->assertOk()->assertSee('Shortage 2');
    }

    public function test_draft_submit_then_approve_records_who_and_when(): void
    {
        $type = $this->seedStock(5);
        $seller = $this->sales();
        $manager = $this->sales(['sales.approve']);

        $this->actingAs($seller)->post('/sales-orders', $this->payload($type, 2));
        $so = SalesOrder::first();

        $this->actingAs($seller)->patch("/sales-orders/{$so->id}/submit")->assertSessionHas('success');
        $this->assertSame('submitted', $so->fresh()->status);

        $this->actingAs($manager)->patch("/sales-orders/{$so->id}/approve", ['note' => 'Ok'])->assertSessionHas('success');
        $so->refresh();
        $this->assertSame('approved', $so->status);
        $this->assertSame($manager->id, $so->approved_by);
        $this->assertNotNull($so->approved_at);
    }

    public function test_cannot_approve_or_reject_own_so_even_with_approve_permission(): void
    {
        $type = $this->seedStock(5);
        $managerWhoAlsoSells = $this->sales(['sales.approve']);

        $this->actingAs($managerWhoAlsoSells)->post('/sales-orders', $this->payload($type, 1, ['submit_now' => '1']));
        $so = SalesOrder::first();

        $this->actingAs($managerWhoAlsoSells)->patch("/sales-orders/{$so->id}/approve")->assertSessionHas('error');
        $this->assertSame('submitted', $so->fresh()->status);

        $this->actingAs($managerWhoAlsoSells)->patch("/sales-orders/{$so->id}/reject", ['note' => 'x'])->assertSessionHas('error');
        $this->assertSame('submitted', $so->fresh()->status);

        $this->actingAs($managerWhoAlsoSells)->get("/sales-orders/{$so->id}")
            ->assertOk()->assertDontSee('name="note"', false);
    }

    public function test_seller_without_approve_permission_cannot_approve(): void
    {
        $type = $this->seedStock(5);
        $seller = $this->sales();

        $this->actingAs($seller)->post('/sales-orders', $this->payload($type, 1, ['submit_now' => '1']));
        $so = SalesOrder::first();

        $this->actingAs($seller)->patch("/sales-orders/{$so->id}/approve")->assertForbidden();
        $this->assertSame('submitted', $so->fresh()->status);
    }

    public function test_reject_requires_reason(): void
    {
        $type = $this->seedStock(5);
        $seller = $this->sales();
        $manager = $this->sales(['sales.approve']);

        $this->actingAs($seller)->post('/sales-orders', $this->payload($type, 1, ['submit_now' => '1']));
        $so = SalesOrder::first();

        $this->actingAs($manager)->patch("/sales-orders/{$so->id}/reject")->assertSessionHasErrors('note');
        $this->assertSame('submitted', $so->fresh()->status);

        $this->actingAs($manager)->patch("/sales-orders/{$so->id}/reject", ['note' => 'Harga belum sesuai']);
        $this->assertSame('rejected', $so->fresh()->status);
    }

    public function test_cannot_cancel_or_reapprove_after_decision(): void
    {
        $type = $this->seedStock(5);
        $seller = $this->sales();
        $manager = $this->sales(['sales.approve']);

        $this->actingAs($seller)->post('/sales-orders', $this->payload($type, 1, ['submit_now' => '1']));
        $so = SalesOrder::first();
        $this->actingAs($manager)->patch("/sales-orders/{$so->id}/approve");

        $this->actingAs($manager)->patch("/sales-orders/{$so->id}/cancel")->assertSessionHas('error');
        $this->actingAs($manager)->patch("/sales-orders/{$so->id}/approve")->assertSessionHas('error');
        $this->assertSame('approved', $so->fresh()->status);
    }

    public function test_customer_with_orders_cannot_be_deleted(): void
    {
        $type = $this->seedStock(1);
        $user = $this->sales(['customers']);

        $this->actingAs($user)->post('/sales-orders', $this->payload($type, 1));
        $customer = Customer::first();

        $this->actingAs($user)->delete("/customers/{$customer->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_customer_can_be_added(): void
    {
        $this->actingAs($this->sales(['customers']))
            ->post('/customers', ['name' => 'PT Baru', 'phone' => '0812'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('customers', ['name' => 'PT Baru']);
    }

    public function test_superadmin_can_approve_own_sales_order(): void
    {
        $type = $this->seedStock(5);
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($superadmin)->post('/sales-orders', $this->payload($type, 1, ['submit_now' => '1']));
        $so = SalesOrder::first();

        $this->actingAs($superadmin)->get("/sales-orders/{$so->id}")
            ->assertOk()->assertSee('Approve');

        $this->actingAs($superadmin)->patch("/sales-orders/{$so->id}/approve")->assertSessionHas('success');
        $this->assertSame('approved', $so->fresh()->status);
    }
}
