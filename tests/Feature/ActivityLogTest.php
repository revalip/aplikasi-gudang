<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }

    private function user(array $permissions = []): User
    {
        return User::factory()->create(['role' => 'user', 'permissions' => $permissions]);
    }

    private function so(string $status = 'draft'): SalesOrder
    {
        return SalesOrder::create([
            'so_number' => SalesOrder::nextNumber(),
            'customer_id' => Customer::firstOrCreate(['name' => 'PT Contoh'])->id,
            'order_date' => '2026-10-04', 'status' => $status,
        ]);
    }

    public function test_login_and_logout_are_logged_with_user(): void
    {
        $u = $this->user();

        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertRedirect();
        $this->assertDatabaseHas('activity_logs', ['event' => 'login', 'user_id' => $u->id, 'user_name' => $u->name]);

        $this->actingAs($u)->post('/logout');
        $this->assertDatabaseHas('activity_logs', ['event' => 'logout', 'user_id' => $u->id]);
    }

    public function test_failed_login_is_logged_without_password(): void
    {
        $this->post('/login', ['email' => 'orang@luar.com', 'password' => 'RAHASIA-SALAH']);

        $log = ActivityLog::where('event', 'login_failed')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('orang@luar.com', $log->description);
        $this->assertStringNotContainsString('RAHASIA-SALAH', json_encode($log->toArray()));
    }

    public function test_create_update_and_status_change_are_logged_with_before_after(): void
    {
        $u = $this->user(['sales']);
        $this->actingAs($u);

        $so = $this->so();
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'created', 'subject_type' => 'Sales Order', 'subject_label' => $so->so_number, 'user_id' => $u->id,
        ]);

        $so->update(['status' => 'submitted']);
        $log = ActivityLog::where('event', 'status_changed')->first();
        $this->assertSame('draft', $log->properties['old']['status']);
        $this->assertSame('submitted', $log->properties['new']['status']);
        $this->assertStringContainsString($so->so_number, $log->description);

        $so->update(['notes' => 'catatan baru']);
        $upd = ActivityLog::where('event', 'updated')->latest('id')->first();
        $this->assertNull($upd->properties['old']['notes']);
        $this->assertSame('catatan baru', $upd->properties['new']['notes']);
    }

    public function test_delete_is_logged(): void
    {
        $this->actingAs($this->user());
        $type = ItemType::create(['name' => 'Router']);
        $type->delete();

        $this->assertDatabaseHas('activity_logs', ['event' => 'deleted', 'subject_type' => 'Item Type', 'subject_label' => 'Router']);
    }

    public function test_passwords_never_appear_in_logs(): void
    {
        $this->actingAs($this->superadmin());
        $u = User::create(['name' => 'Budi', 'email' => 'budi@mjg.com', 'password' => 'rahasia12345', 'role' => 'user']);
        $u->update(['password' => 'ganti-password-baru']);

        $dump = json_encode(ActivityLog::all()->toArray());
        $this->assertStringNotContainsString('rahasia12345', $dump);
        $this->assertStringNotContainsString('ganti-password-baru', $dump);
        $this->assertStringNotContainsString('"password"', $dump);
        $this->assertSame(0, ActivityLog::where('event', 'updated')->where('subject_label', 'Budi')->count()); // cuma ganti password = gak ada yang dicatat
    }

    public function test_print_and_export_are_logged(): void
    {
        $u = $this->user(['sales', 'items.manage']);
        $type = ItemType::create(['name' => 'Router']);
        $so = $this->so('approved');
        $so->items()->create(['item_name' => 'RB951', 'type_id' => $type->id, 'qty' => 1, 'unit_price' => 1000]);

        $this->actingAs($u)->get("/sales-orders/{$so->id}/print")->assertOk();
        $this->assertDatabaseHas('activity_logs', ['event' => 'print', 'subject_label' => $so->so_number, 'user_id' => $u->id]);

        $this->actingAs($u)->get('/items-export')->assertOk();
        $this->assertDatabaseHas('activity_logs', ['event' => 'export', 'user_id' => $u->id]);
    }

    public function test_scan_serial_number_module_is_logged(): void
    {
        $u = $this->user(['serial-scans']);

        $this->actingAs($u)->post('/serial-scans', ['serial_number' => 'SN-LOG-1', 'direction' => 'in']);

        $this->assertDatabaseHas('activity_logs', ['event' => 'created', 'subject_type' => 'Scan SN', 'subject_label' => 'SN-LOG-1', 'user_id' => $u->id]);
    }

    public function test_import_writes_one_summary_instead_of_one_log_per_item(): void
    {
        Location::create(['name' => 'Lantai 1']);
        ItemType::create(['name' => 'Router']);
        $body = "Item ID,Item Name,Type,Location\nA-1,Satu,Router,Lantai 1\nA-2,Dua,Router,Lantai 1\nA-3,Tiga,Router,Lantai 1\n";

        $this->actingAs($this->superadmin())
            ->post('/items-import', ['file' => UploadedFile::fake()->createWithContent('barang.csv', $body), 'mode' => 'skip'])
            ->assertRedirect();

        $this->assertSame(3, Item::count());
        $this->assertSame(0, ActivityLog::where('subject_type', 'Item')->count());
        $this->assertSame(1, ActivityLog::where('event', 'import')->count());
        $this->assertStringContainsString('3 added', ActivityLog::where('event', 'import')->first()->description);
    }

    public function test_only_superadmin_can_open_log_page(): void
    {
        foreach ([$this->user(), $this->user(['items.manage', 'sales', 'purchasing', 'delivery', 'work-orders'])] as $u) {
            $this->actingAs($u)->get('/activity-logs')->assertForbidden();
            $this->actingAs($u)->get('/activity-logs/export')->assertForbidden();
        }

        $this->actingAs($this->superadmin())->get('/activity-logs')->assertOk()->assertSee('Activity Log');
    }

    public function test_log_page_filters_by_event_user_and_search(): void
    {
        $admin = $this->superadmin();
        $other = $this->user();
        ActivityLog::create(['user_id' => $other->id, 'user_name' => $other->name, 'event' => 'login_failed', 'description' => 'Gagal login dengan email salah@x.com']);
        ActivityLog::create(['user_id' => $admin->id, 'user_name' => $admin->name, 'event' => 'print', 'description' => 'Cetak SO (PDF) SO-2026-99999']);

        $this->actingAs($admin)->get('/activity-logs?event=login_failed')
            ->assertSee('salah@x.com')->assertDontSee('SO-2026-99999');

        $this->actingAs($admin)->get('/activity-logs?q=SO-2026-99999')
            ->assertSee('SO-2026-99999')->assertDontSee('salah@x.com');

        $this->actingAs($admin)->get('/activity-logs?user_id=' . $other->id)
            ->assertSee('salah@x.com')->assertDontSee('SO-2026-99999');
    }

    public function test_log_export_csv_downloads_and_is_itself_logged(): void
    {
        $admin = $this->superadmin();
        ActivityLog::create(['user_id' => $admin->id, 'user_name' => $admin->name, 'event' => 'print', 'description' => 'Cetak SO (PDF) SO-2026-77777']);

        $res = $this->actingAs($admin)->get('/activity-logs/export');
        $res->assertOk();
        $this->assertStringContainsString('SO-2026-77777', $res->streamedContent());
        $this->assertDatabaseHas('activity_logs', ['event' => 'export', 'user_id' => $admin->id]);
    }

    public function test_failure_to_write_log_never_breaks_the_real_action(): void
    {
        Schema::drop('activity_logs');

        $so = $this->so();

        $this->assertDatabaseHas('sales_orders', ['id' => $so->id]);
    }
}
