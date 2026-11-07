<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ItemImportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }

    private function csv(string $body, string $name = 'barang.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $body);
    }

    private function seedRefs(): void
    {
        Location::create(['name' => 'Lantai 1']);
        Location::create(['name' => 'Lantai 2']);
        ItemType::create(['name' => 'Router']);
    }

    public function test_viewer_cannot_open_import(): void
    {
        $viewer = User::factory()->create(['role' => 'user', 'permissions' => []]);

        $this->actingAs($viewer)->get('/items-import')->assertForbidden();
        $this->actingAs($viewer)->post('/items-import', [])->assertForbidden();
    }

    public function test_import_csv_creates_items_and_new_type(): void
    {
        $this->seedRefs();
        $body = "Item ID,Item Name,Type,Serial Number,Merek,Status,Condition,Stuff In,Location\n"
            . "RTR-1,RB951,router,SN001,Mikrotik,in stock,Baru,25/09/2026,lantai 1\n"
            . "SW-1,Switch 8p,Switch,SN002,TP-Link,,,,Lantai 2\n";

        $this->actingAs($this->admin())
            ->post('/items-import', ['file' => $this->csv($body), 'mode' => 'skip'])
            ->assertRedirect('/items-import');

        $this->assertDatabaseCount('items', 2);
        $rtr = Item::where('item_id', 'RTR-1')->first();
        $this->assertSame('In Stock', $rtr->status);
        $this->assertSame('2026-09-25', substr((string) $rtr->stuff_in, 0, 10));
        $this->assertSame(ItemType::where('name', 'Router')->value('id'), $rtr->type_id); // cocok tanpa beda huruf
        $this->assertDatabaseHas('item_types', ['name' => 'Switch']);
        $this->assertSame('Normal', Item::where('item_id', 'SW-1')->value('condition')); // default kalau kosong
    }

    public function test_semicolon_delimiter_is_detected(): void
    {
        $this->seedRefs();
        $body = "Item ID;Item Name;Type;Location\nRTR-9;Router X;Router;Lantai 1\n";

        $this->actingAs($this->admin())->post('/items-import', ['file' => $this->csv($body), 'mode' => 'skip']);

        $this->assertDatabaseHas('items', ['item_id' => 'RTR-9']);
    }

    public function test_bad_rows_are_reported_and_good_rows_still_saved(): void
    {
        $this->seedRefs();
        $body = "Item ID,Item Name,Type,Status,Location\n"
            . "OK-1,Barang OK,Router,In Stock,Lantai 1\n"
            . "BAD-1,Lokasi ngawur,Router,In Stock,Lantai 99\n"
            . "BAD-2,Status ngawur,Router,Rusak parah,Lantai 1\n";

        $res = $this->actingAs($this->admin())->post('/items-import', ['file' => $this->csv($body), 'mode' => 'skip']);

        $this->assertDatabaseHas('items', ['item_id' => 'OK-1']);
        $this->assertDatabaseMissing('items', ['item_id' => 'BAD-1']);
        $this->assertDatabaseMissing('items', ['item_id' => 'BAD-2']);
        $result = $res->getSession()->get('import_result');
        $this->assertSame(1, $result['created']);
        $this->assertCount(2, $result['errors']);
        $this->assertSame(3, $result['errors'][0]['row']); // nomor baris sesuai di Excel
    }

    public function test_existing_item_skip_vs_update(): void
    {
        $this->seedRefs();
        $body = "Item ID,Item Name,Type,Location\nRTR-1,Nama Baru,Router,Lantai 1\n";
        Item::create([
            'item_id' => 'RTR-1', 'item_name' => 'Nama Lama', 'type_id' => ItemType::first()->id,
            'status' => 'In Stock', 'condition' => 'Baru', 'location_id' => Location::first()->id,
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/items-import', ['file' => $this->csv($body), 'mode' => 'skip']);
        $this->assertSame('Nama Lama', Item::where('item_id', 'RTR-1')->value('item_name'));

        $this->actingAs($admin)->post('/items-import', ['file' => $this->csv($body), 'mode' => 'update']);
        $this->assertSame('Nama Baru', Item::where('item_id', 'RTR-1')->value('item_name'));
        $this->assertDatabaseCount('items', 1);
    }

    public function test_missing_required_header_is_rejected(): void
    {
        $this->seedRefs();
        $body = "Item ID,Item Name\nX-1,Tanpa type\n";

        $this->actingAs($this->admin())
            ->post('/items-import', ['file' => $this->csv($body), 'mode' => 'skip'])
            ->assertSessionHas('import_fatal');

        $this->assertDatabaseCount('items', 0);
    }

    public function test_wrong_file_type_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/items-import', ['file' => UploadedFile::fake()->createWithContent('x.pdf', 'abc'), 'mode' => 'skip'])
            ->assertSessionHasErrors('file');
    }

    public function test_template_downloads(): void
    {
        $this->actingAs($this->admin())->get('/items-import/template')->assertOk();
    }
}
