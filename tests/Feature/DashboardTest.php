<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $perms = []): User
    {
        return User::factory()->create(['role' => 'user', 'permissions' => $perms]);
    }

    private function item(string $id, string $status, Location $loc, ItemType $type): Item
    {
        return Item::create(['item_id' => $id, 'item_name' => 'RB951', 'type_id' => $type->id, 'status' => $status, 'condition' => 'Baru', 'location_id' => $loc->id]);
    }

    private function so(string $status): SalesOrder
    {
        return SalesOrder::create([
            'so_number' => SalesOrder::nextNumber(), 'customer_id' => Customer::firstOrCreate(['name' => 'PT Contoh'])->id,
            'order_date' => '2026-10-04', 'status' => $status,
        ]);
    }

    public function test_stat_cards_link_to_filtered_pages(): void
    {
        $this->item('A-1', 'In Stock', $l1 = Location::create(['name' => 'Lantai 1']), ItemType::create(['name' => 'Router']));

        $html = $this->actingAs($this->user())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString(route('items.search', ['status' => 'In Stock']), $html);
        $this->assertStringContainsString(route('items.search', ['status' => 'Instalation']), $html);
        $this->assertStringContainsString(route('items.index', ['location' => $l1->id]), $html);          // donut / legend lokasi
        $this->assertStringContainsString(e(route('items.index', ['location' => $l1->id, 'q' => 'A-1'])), $html); // barang terbaru (& jadi &amp; di HTML)
    }

    public function test_header_location_filter_scopes_numbers_and_links(): void
    {
        $type = ItemType::create(['name' => 'Router']);
        $l1 = Location::create(['name' => 'Lantai 1']);
        $l2 = Location::create(['name' => 'Lantai 2']);
        $this->item('A-1', 'In Stock', $l1, $type);
        $this->item('A-2', 'In Stock', $l1, $type);
        $this->item('B-1', 'In Stock', $l2, $type);
        $this->item('B-2', 'Broken', $l2, $type);

        $res = $this->actingAs($this->user())->get('/dashboard?location=' . $l2->id)->assertOk();
        $res->assertSee('Total at Lantai 2')->assertSee('Broken');
        $res->assertViewHas('stats', fn ($stats) => $stats[0]['value'] === 2 && $stats[1]['value'] === 1 && $stats[3]['value'] === 1);
        $this->assertStringContainsString(e(route('items.index', ['location' => $l2->id, 'status' => 'In Stock'])), $res->getContent());
        $res->assertViewHas('recentItems', fn ($items) => $items->pluck('item_id')->sort()->values()->all() === ['B-1', 'B-2']);

        // Invalid location is ignored (shows all)
        $this->actingAs($this->user())->get('/dashboard?location=9999')->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats[0]['value'] === 4);
    }

    public function test_pending_action_cards_count_and_link(): void
    {
        $this->so('submitted');
        $this->so('submitted');
        $approved = $this->so('approved');
        $this->so('processing');
        WorkOrder::create(['wo_number' => 'WO-1', 'status' => 'issued', 'wo_date' => '2026-10-04', 'subject' => 'x', 'sales_order_id' => $approved->id]);
        WorkOrder::create(['wo_number' => 'WO-2', 'status' => 'accepted', 'wo_date' => '2026-10-04', 'subject' => 'x']);
        DeliveryOrder::create(['do_number' => 'DO-1', 'sales_order_id' => $approved->id, 'status' => 'picking']);
        PurchaseRequest::create(['pr_number' => 'PR-1', 'sales_order_id' => $approved->id, 'status' => 'open']);
        $sup = Supplier::create(['name' => 'PT Sup']);
        PurchaseOrder::create(['po_number' => 'PO-1', 'supplier_id' => $sup->id, 'order_date' => '2026-10-04', 'status' => 'ordered']);
        PurchaseOrder::create(['po_number' => 'PO-2', 'supplier_id' => $sup->id, 'order_date' => '2026-10-04', 'status' => 'partial']);
        PurchaseOrder::create(['po_number' => 'PO-3', 'supplier_id' => $sup->id, 'order_date' => '2026-10-04', 'status' => 'received']);

        $admin = User::factory()->create(['role' => 'superadmin']);
        $res = $this->actingAs($admin)->get('/dashboard')->assertOk();

        $res->assertViewHas('actions', function ($cards) {
            $by = collect($cards)->keyBy('label');

            return $by['SO pending approval']['count'] === 2
                && $by['WO pending acceptance']['count'] === 1
                && $by['SO approved, pending delivery']['count'] === 2
                && $by['DO in picking']['count'] === 1
                && $by['PR pending purchasing']['count'] === 1
                && $by['PO pending goods receipt']['count'] === 2;
        });

        $html = $res->getContent();
        $this->assertStringContainsString(route('sales-orders.index', ['status' => 'submitted']), $html);
        $this->assertStringContainsString(route('work-orders.index', ['status' => 'issued']), $html);
        $this->assertStringContainsString(route('purchasing.index', ['status' => 'ordered,partial']), $html);
    }

    public function test_action_cards_only_show_for_permitted_modules(): void
    {
        $this->actingAs($this->user())->get('/dashboard')->assertOk()
            ->assertViewHas('actions', fn ($c) => $c === [])->assertDontSee('Action Required');

        $this->actingAs($this->user(['delivery']))->get('/dashboard')
            ->assertViewHas('actions', fn ($c) => collect($c)->pluck('label')->all() === ['DO in picking']);
    }

    public function test_target_pages_accept_the_dashboard_filters(): void
    {
        $type = ItemType::create(['name' => 'Router']);
        $loc = Location::create(['name' => 'Lantai 1']);
        $this->item('OK-1', 'In Stock', $loc, $type);
        $this->item('BRK-1', 'Broken', $loc, $type);
        $this->item('INS-1', 'Instalation', $loc, $type);

        $u = $this->user(['sales', 'work-orders', 'purchasing']);

        $this->actingAs($u)->get('/items?location=' . $loc->id . '&status=In Stock')->assertSee('OK-1')->assertDontSee('BRK-1')->assertSee('Clear filter');
        $this->actingAs($u)->get('/items?location=' . $loc->id . '&status=Broken,Instalation')->assertSee('BRK-1')->assertSee('INS-1')->assertDontSee('OK-1');

        $a = $this->so('approved');
        $p = $this->so('processing');
        $d = $this->so('draft');
        $this->actingAs($u)->get('/sales-orders?status=approved,processing')
            ->assertSee($a->so_number)->assertSee($p->so_number)->assertDontSee($d->so_number);

        WorkOrder::create(['wo_number' => 'WO-ISSUED', 'status' => 'issued', 'wo_date' => '2026-10-04', 'subject' => 'x']);
        WorkOrder::create(['wo_number' => 'WO-ACCEPTED', 'status' => 'accepted', 'wo_date' => '2026-10-04', 'subject' => 'x']);
        $this->actingAs($u)->get('/work-orders?status=issued')->assertSee('WO-ISSUED')->assertDontSee('WO-ACCEPTED');
        $this->actingAs($u)->get('/work-orders')->assertSee('WO-ISSUED')->assertSee('WO-ACCEPTED');

        $sup = Supplier::create(['name' => 'PT Sup']);
        PurchaseOrder::create(['po_number' => 'PO-ORD', 'supplier_id' => $sup->id, 'order_date' => '2026-10-04', 'status' => 'ordered']);
        PurchaseOrder::create(['po_number' => 'PO-RCV', 'supplier_id' => $sup->id, 'order_date' => '2026-10-04', 'status' => 'received']);
        $this->actingAs($u)->get('/purchasing?status=ordered,partial')->assertSee('PO-ORD')->assertDontSee('PO-RCV');
    }
}
