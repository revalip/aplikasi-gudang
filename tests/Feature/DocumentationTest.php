<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Document;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private SalesOrder $so;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->user = User::factory()->create(['role' => 'user', 'permissions' => ['sales', 'documents']]);
        $this->so = SalesOrder::create([
            'so_number' => 'SO-2026-00007', 'customer_id' => Customer::create(['name' => 'PT Contoh'])->id,
            'order_date' => '2026-10-17', 'status' => 'approved', 'created_by' => $this->user->id,
        ]);
    }

    public function test_printing_word_and_pdf_archives_a_copy(): void
    {
        $this->actingAs($this->user)->get("/sales-orders/{$this->so->id}/print")->assertOk();
        $this->actingAs($this->user)->get("/sales-orders/{$this->so->id}/print-pdf")->assertOk();

        $this->assertSame(2, Document::count());
        $word = Document::where('format', 'docx')->first();
        $this->assertSame('Sales Order', $word->category);
        $this->assertSame('SO-2026-00007', $word->reference);
        $this->assertSame('SO-2026-00007.docx', $word->file_name);
        $this->assertSame($this->user->id, $word->created_by);
        $this->assertTrue($word->documentable->is($this->so));
        Storage::disk('local')->assertExists($word->path);
        Storage::disk('local')->assertExists(Document::where('format', 'pdf')->value('path'));
    }

    public function test_excel_export_is_archived_as_report(): void
    {
        $this->actingAs($this->user)->get('/items-export')->assertOk();

        $doc = Document::first();
        $this->assertSame('Laporan', $doc->category);
        $this->assertSame('xlsx', $doc->format);
        $this->assertStringStartsWith('data-barang-', $doc->file_name);
    }

    public function test_index_search_filter_and_download(): void
    {
        $this->actingAs($this->user)->get("/sales-orders/{$this->so->id}/print");
        $this->actingAs($this->user)->get('/items-export');

        $this->actingAs($this->user)->get('/documents')->assertOk()->assertSee('SO-2026-00007')->assertSee('data-barang-');
        $this->actingAs($this->user)->get('/documents?q=SO-2026')->assertOk()->assertSee('SO-2026-00007')->assertDontSee('data-barang-');
        $this->actingAs($this->user)->get('/documents?category=Laporan')->assertOk()->assertDontSee('SO-2026-00007');
        $this->actingAs($this->user)->get('/documents?format=pdf')->assertOk()->assertSee('No documents yet');

        $doc = Document::where('format', 'docx')->first();
        $this->actingAs($this->user)->get("/documents/{$doc->id}/download")->assertOk()->assertDownload('SO-2026-00007.docx');
    }

    public function test_report_export_downloads_excel(): void
    {
        $this->actingAs($this->user)->get("/sales-orders/{$this->so->id}/print");

        $this->actingAs($this->user)->get('/documents/export?category=Sales+Order')->assertOk()->assertDownload();
    }

    public function test_user_without_permission_is_blocked(): void
    {
        $u = User::factory()->create(['role' => 'user', 'permissions' => ['sales']]);

        $this->actingAs($u)->get('/documents')->assertForbidden();
    }
}
