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
use App\Services\DocxTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class DocumentPrintingTest extends TestCase
{
    use RefreshDatabase;

    public function test_docx_template_service_fills_fields_and_clones_rows(): void
    {
        $path = resource_path('doc-templates/wo_template.docx');
        $this->assertFileExists($path);

        $out = (new DocxTemplateService())->render($path, [
            'wo_number' => 'WO-2026-00099', 'subject' => 'Tes Unit & <khusus>',
        ], [
            ['no' => 1, 'type' => 'DELIVERY', 'part' => 'Router A', 'qty' => 2, 'sow' => 'Delivery only'],
            ['no' => 2, 'type' => 'DELIVERY', 'part' => 'Switch B', 'qty' => 5, 'sow' => 'Delivery only'],
        ]);

        $this->assertFileExists($out);
        $zip = new ZipArchive();
        $zip->open($out);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($out);

        $this->assertStringContainsString('WO-2026-00099', $xml);
        $this->assertStringContainsString('Tes Unit &amp; &lt;khusus&gt;', $xml); // escaped, bukan reproduksi mentah
        $this->assertStringContainsString('Router A', $xml);
        $this->assertStringContainsString('Switch B', $xml);
        $this->assertStringNotContainsString('{{item.no}}', $xml);
        $this->assertStringNotContainsString('##ROWSTART##', $xml);
    }

    public function test_user_without_permission_cannot_open_work_orders(): void
    {
        $u = User::factory()->create(['role' => 'user', 'permissions' => []]);

        $this->actingAs($u)->get('/work-orders')->assertForbidden();
        $this->actingAs($u)->get('/work-orders/create')->assertForbidden();
    }

    public function test_create_work_order_prefills_from_sales_order_and_saves(): void
    {
        $wo_user = User::factory()->create(['role' => 'user', 'permissions' => ['work-orders'], 'name' => 'Septian']);
        $customer = Customer::create(['name' => 'Pak Ali', 'phone' => '0812', 'address' => 'Jl. Contoh']);
        $so = SalesOrder::create(['so_number' => SalesOrder::nextNumber(), 'customer_id' => $customer->id, 'order_date' => '2026-09-01', 'status' => 'approved']);

        $this->actingAs($wo_user)->get("/work-orders/create?so={$so->id}")
            ->assertOk()->assertSee('Pak Ali')->assertSee('Septian');

        $this->actingAs($wo_user)->post('/work-orders', [
            'sales_order_id' => $so->id, 'wo_date' => '2026-09-03', 'subject' => 'Pengadaan project STA -DPG',
            'ref_number' => 'STA/PO/2026/14/08/071', 'ref_company' => 'STA',
            'cust_name' => 'Pak Ali', 'user_name' => 'Septian', 'sales_name' => 'Julius', 'approver_name' => 'Achmad Bagir',
            'items' => [['type_name' => 'DELIVERY', 'part_number' => 'RB951Ui-2ND', 'qty' => 18, 'sow_explanation' => 'Delivery only']],
        ])->assertRedirect();

        $this->assertDatabaseHas('work_orders', ['subject' => 'Pengadaan project STA -DPG', 'sales_order_id' => $so->id]);
        $this->assertMatchesRegularExpression('/^WO-\d{4}-00001$/', WorkOrder::first()->wo_number);
        $this->assertDatabaseHas('work_order_items', ['part_number' => 'RB951Ui-2ND', 'qty' => 18]);
    }

    public function test_work_order_print_downloads_docx(): void
    {
        $u = User::factory()->create(['role' => 'user', 'permissions' => ['work-orders']]);
        $this->actingAs($u)->post('/work-orders', [
            'wo_date' => '2026-09-03', 'subject' => 'Tes cetak',
            'items' => [['type_name' => 'DELIVERY', 'qty' => 1]],
        ]);
        $wo = WorkOrder::first();

        $this->actingAs($u)->get("/work-orders/{$wo->id}/print")
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=' . $wo->wo_number . '.docx');
    }

    public function test_delivery_order_print_downloads_docx_with_permission(): void
    {
        $type = ItemType::create(['name' => 'Router']);
        $loc = Location::create(['name' => 'Lantai 1']);
        Item::create(['item_id' => 'RTR-1', 'item_name' => 'RB951', 'type_id' => $type->id, 'serial_number' => 'SN1', 'status' => 'In Stock', 'condition' => 'Baru', 'location_id' => $loc->id]);
        $so = SalesOrder::create(['so_number' => SalesOrder::nextNumber(), 'customer_id' => Customer::create(['name' => 'PT Contoh'])->id, 'order_date' => '2026-09-01', 'status' => 'approved']);
        $so->items()->create(['item_name' => 'RB951', 'type_id' => $type->id, 'qty' => 1, 'unit_price' => 1000]);
        $wo = WorkOrder::create(['wo_number' => WorkOrder::nextNumber(), 'sales_order_id' => $so->id, 'status' => 'accepted', 'wo_date' => now()->toDateString(), 'subject' => 'Tes', 'accepted_at' => now()]);
        $u = User::factory()->create(['role' => 'user', 'permissions' => ['delivery', 'sales']]);
        $this->actingAs($u)->post("/sales-orders/{$so->id}/delivery-orders");
        $do = DeliveryOrder::first();

        $this->actingAs($u)->get("/delivery-orders/{$do->id}/print")
            ->assertOk()->assertHeader('content-disposition', 'attachment; filename=' . $do->do_number . '.docx');

        $noPerm = User::factory()->create(['role' => 'user', 'permissions' => []]);
        $this->actingAs($noPerm)->get("/delivery-orders/{$do->id}/print")->assertForbidden();
    }

    public function test_pdf_routes_respond_ok_for_so_wo_do(): void
    {
        $type = ItemType::create(['name' => 'Router']);
        $loc = Location::create(['name' => 'Lantai 1']);
        Item::create(['item_id' => 'RTR-1', 'item_name' => 'RB951', 'type_id' => $type->id, 'serial_number' => 'SN1', 'status' => 'In Stock', 'condition' => 'Baru', 'location_id' => $loc->id]);
        $so = SalesOrder::create(['so_number' => SalesOrder::nextNumber(), 'customer_id' => Customer::create(['name' => 'PT Contoh'])->id, 'order_date' => '2026-09-01', 'status' => 'approved']);
        $so->items()->create(['item_name' => 'RB951', 'type_id' => $type->id, 'qty' => 1, 'unit_price' => 1000]);
        $wo = WorkOrder::create(['wo_number' => WorkOrder::nextNumber(), 'sales_order_id' => $so->id, 'status' => 'accepted', 'wo_date' => now()->toDateString(), 'subject' => 'Tes', 'accepted_at' => now()]);
        $u = User::factory()->create(['role' => 'user', 'permissions' => ['delivery', 'sales', 'work-orders']]);
        $this->actingAs($u)->post("/sales-orders/{$so->id}/delivery-orders");
        $do = DeliveryOrder::first();

        $this->actingAs($u)->get("/sales-orders/{$so->id}/print")->assertOk();
        $this->actingAs($u)->get("/sales-orders/{$so->id}/print-pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($u)->get("/work-orders/{$wo->id}/print-pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($u)->get("/delivery-orders/{$do->id}/print-pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_purchase_order_print_downloads_docx_and_pdf(): void
    {
        $u = User::factory()->create(['role' => 'user', 'permissions' => ['purchasing']]);
        $type = ItemType::create(['name' => 'Router']);
        $supplier = \App\Models\Supplier::create(['name' => 'PT Suplier Jaya', 'phone' => '0812', 'address' => 'Jakarta']);

        $this->actingAs($u)->post('/purchasing', [
            'po_number' => 'PO-TES-1', 'supplier_id' => $supplier->id, 'order_date' => '2026-09-29',
            'items' => [['item_name' => 'RB951', 'type_id' => $type->id, 'merek' => 'Mikrotik', 'qty_ordered' => 5, 'unit_price' => 600000]],
        ]);
        $po = \App\Models\PurchaseOrder::where('po_number', 'PO-TES-1')->first();

        $this->actingAs($u)->get("/purchasing/{$po->id}/print")
            ->assertOk()->assertHeader('content-disposition', 'attachment; filename=PO-TES-1.docx');

        $this->actingAs($u)->get("/purchasing/{$po->id}/print-pdf")
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $noPerm = User::factory()->create(['role' => 'user', 'permissions' => []]);
        $this->actingAs($noPerm)->get("/purchasing/{$po->id}/print")->assertForbidden();
    }

    public function test_delivery_order_cannot_be_created_without_accepted_work_order(): void
    {
        $type = ItemType::create(['name' => 'Router']);
        $loc = Location::create(['name' => 'Lantai 1']);
        Item::create(['item_id' => 'RTR-1', 'item_name' => 'RB951', 'type_id' => $type->id, 'status' => 'In Stock', 'condition' => 'Baru', 'location_id' => $loc->id]);
        $so = SalesOrder::create(['so_number' => SalesOrder::nextNumber(), 'customer_id' => Customer::create(['name' => 'PT Contoh'])->id, 'order_date' => '2026-09-01', 'status' => 'approved']);
        $so->items()->create(['item_name' => 'RB951', 'type_id' => $type->id, 'qty' => 1, 'unit_price' => 1000]);
        $u = User::factory()->create(['role' => 'user', 'permissions' => ['delivery', 'sales']]);

        // Belum ada WO sama sekali -> ditolak
        $this->actingAs($u)->post("/sales-orders/{$so->id}/delivery-orders")->assertSessionHas('error');
        $this->assertDatabaseCount('delivery_orders', 0);

        // WO ada tapi belum diterima (status issued) -> masih ditolak
        WorkOrder::create(['wo_number' => WorkOrder::nextNumber(), 'sales_order_id' => $so->id, 'status' => 'issued', 'wo_date' => now()->toDateString(), 'subject' => 'Tes']);
        $this->actingAs($u)->post("/sales-orders/{$so->id}/delivery-orders")->assertSessionHas('error');
        $this->assertDatabaseCount('delivery_orders', 0);
    }

    public function test_accepting_work_order_notifies_creator_and_unlocks_delivery(): void
    {
        $sales = User::factory()->create(['role' => 'user', 'permissions' => ['work-orders', 'sales', 'delivery'], 'name' => 'Budi Sales']);
        $gudang = User::factory()->create(['role' => 'user', 'permissions' => ['work-orders'], 'name' => 'Agus Gudang']);

        $type = ItemType::create(['name' => 'Router']);
        $loc = Location::create(['name' => 'Lantai 1']);
        Item::create(['item_id' => 'RTR-1', 'item_name' => 'RB951', 'type_id' => $type->id, 'status' => 'In Stock', 'condition' => 'Baru', 'location_id' => $loc->id]);
        $so = SalesOrder::create(['so_number' => SalesOrder::nextNumber(), 'customer_id' => Customer::create(['name' => 'PT Contoh'])->id, 'order_date' => '2026-09-01', 'status' => 'approved']);
        $so->items()->create(['item_name' => 'RB951', 'type_id' => $type->id, 'qty' => 1, 'unit_price' => 1000]);

        $this->actingAs($sales)->post('/work-orders', [
            'sales_order_id' => $so->id, 'wo_date' => '2026-09-01', 'subject' => 'Tes notif',
            'items' => [['type_name' => 'DELIVERY', 'qty' => 1]],
        ]);
        $wo = WorkOrder::first();
        $this->assertSame('issued', $wo->status);

        // Dibuat -> yang punya akses work-orders (selain pembuat) dapet notif
        $this->assertDatabaseHas('app_notifications', ['user_id' => $gudang->id]);
        $this->assertDatabaseMissing('app_notifications', ['user_id' => $sales->id]);

        // Belum diterima -> DO masih diblokir
        $this->actingAs($sales)->post("/sales-orders/{$so->id}/delivery-orders")->assertSessionHas('error');

        $this->actingAs($gudang)->patch("/work-orders/{$wo->id}/accept")->assertSessionHas('success');
        $this->assertSame('accepted', $wo->fresh()->status);
        $this->assertSame($gudang->id, $wo->fresh()->accepted_by);

        // Diterima -> yang bikin WO dapet notif balik
        $this->assertDatabaseHas('app_notifications', ['user_id' => $sales->id]);

        // Diterima -> DO sekarang boleh dibuat
        $this->actingAs($sales)->post("/sales-orders/{$so->id}/delivery-orders")->assertSessionHas('success');
        $this->assertDatabaseCount('delivery_orders', 1);

        // Gak bisa diterima dua kali
        $this->actingAs($gudang)->patch("/work-orders/{$wo->id}/accept")->assertSessionHas('error');
    }

    public function test_notification_bell_shows_unread_and_mark_all_read(): void
    {
        $u = User::factory()->create(['role' => 'user', 'permissions' => ['work-orders']]);
        \App\Models\AppNotification::create(['user_id' => $u->id, 'title' => 'Tes 1']);
        \App\Models\AppNotification::create(['user_id' => $u->id, 'title' => 'Tes 2']);

        $this->actingAs($u)->get('/dashboard')->assertOk()->assertSee('Tes 1');
        $this->assertSame(2, $u->notifications()->whereNull('read_at')->count());

        $this->actingAs($u)->post('/notifications/read-all')->assertRedirect();
        $this->assertSame(0, $u->notifications()->whereNull('read_at')->count());
    }
}
