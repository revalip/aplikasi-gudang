<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }

    /** User biasa dengan centang modul tertentu. */
    private function user(array $permissions = []): User
    {
        return User::factory()->create(['role' => 'user', 'permissions' => $permissions]);
    }

    private function item(): Item
    {
        return Item::create([
            'item_id' => 'TES-0001', 'item_name' => 'Router Tes',
            'type_id' => ItemType::create(['name' => 'Router'])->id,
            'status' => 'In Stock', 'condition' => 'Baru',
            'location_id' => Location::create(['name' => 'Lantai 1'])->id,
        ]);
    }

    public function test_everyone_can_view_stock_pages(): void
    {
        foreach ([$this->superadmin(), $this->user(), $this->user(['items.manage'])] as $u) {
            foreach (['/dashboard', '/items', '/items/search', '/items-export'] as $url) {
                $this->actingAs($u)->get($url)->assertOk();
            }
        }
    }

    public function test_user_without_modules_is_read_only(): void
    {
        $viewer = $this->user();
        $item = $this->item();

        foreach (['/items/create', "/items/{$item->id}/edit", '/item-types', '/serial-scans', '/purchasing', '/suppliers', '/users'] as $url) {
            $this->actingAs($viewer)->get($url)->assertForbidden();
        }
        $this->actingAs($viewer)->delete("/items/{$item->id}")->assertForbidden();
        $this->assertDatabaseHas('items', ['id' => $item->id]);

        $this->actingAs($viewer)->get('/items')
            ->assertDontSee('Tambah Barang')
            ->assertDontSee('Purchase Order')
            ->assertDontSee('Kelola User');
    }

    public function test_only_checked_modules_are_accessible(): void
    {
        $u = $this->user(['items.manage', 'serial-scans']);
        $item = $this->item();

        foreach (['/items/create', "/items/{$item->id}/edit", '/serial-scans'] as $url) {
            $this->actingAs($u)->get($url)->assertOk();
        }
        foreach (['/item-types', '/purchasing', '/suppliers', '/users'] as $url) {
            $this->actingAs($u)->get($url)->assertForbidden();
        }
    }

    public function test_purchasing_and_suppliers_are_separate_modules(): void
    {
        $po = $this->user(['purchasing']);
        $this->actingAs($po)->get('/purchasing')->assertOk();
        $this->actingAs($po)->get('/suppliers')->assertForbidden();

        $sup = $this->user(['suppliers']);
        $this->actingAs($sup)->get('/suppliers')->assertOk();
        $this->actingAs($sup)->get('/purchasing')->assertForbidden();
    }

    public function test_superadmin_has_full_access(): void
    {
        $admin = $this->superadmin();
        $item = $this->item();

        foreach (['/items/create', "/items/{$item->id}/edit", '/item-types', '/serial-scans', '/purchasing', '/suppliers', '/users'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_superadmin_creates_user_with_custom_modules(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->get('/users/create')->assertOk()->assertSee('Module Access Permissions');

        $this->actingAs($admin)->post('/users', [
            'name' => 'Budi Gudang', 'email' => 'budi@mjg.com',
            'permissions' => ['items.manage', 'item-types.manage'],
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertRedirect('/users');

        $budi = User::where('email', 'budi@mjg.com')->first();
        $this->assertSame('user', $budi->role);
        $this->assertSame(['items.manage', 'item-types.manage'], $budi->permissions);

        $this->post('/logout');
        $this->post('/login', ['email' => 'budi@mjg.com', 'password' => 'rahasia123'])->assertRedirect('/dashboard');
        $this->actingAs($budi)->get('/item-types')->assertOk();
        $this->actingAs($budi)->get('/purchasing')->assertForbidden();
    }

    public function test_superadmin_checkbox_makes_full_access_and_ignores_modules(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Bos Baru', 'email' => 'bos@mjg.com', 'is_superadmin' => '1',
            'permissions' => ['items.manage'],
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertRedirect('/users');

        $bos = User::where('email', 'bos@mjg.com')->first();
        $this->assertSame('superadmin', $bos->role);
        $this->assertSame([], $bos->permissions);
        $this->actingAs($bos)->get('/users')->assertOk();
    }

    public function test_user_form_validation(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->post('/users', [
            'name' => '', 'email' => $admin->email, 'permissions' => ['users.manage', 'ngawur'],
            'password' => '123', 'password_confirmation' => '321',
        ])->assertSessionHasErrors(['name', 'email', 'permissions.0', 'permissions.1', 'password']);
    }

    public function test_edit_without_password_keeps_old_password_and_updates_modules(): void
    {
        $admin = $this->superadmin();
        $u = $this->user();
        $oldHash = $u->password;

        $this->actingAs($admin)->put("/users/{$u->id}", [
            'name' => 'User Baru', 'email' => $u->email, 'permissions' => ['purchasing'], 'password' => '',
        ])->assertRedirect('/users');

        $u->refresh();
        $this->assertSame(['purchasing'], $u->permissions);
        $this->assertSame($oldHash, $u->password);
    }

    public function test_cannot_lock_out_last_superadmin_or_delete_self(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->put("/users/{$admin->id}", [
            'name' => $admin->name, 'email' => $admin->email,
        ])->assertSessionHas('error');
        $this->assertSame('superadmin', $admin->fresh()->role);

        $this->actingAs($admin)->delete("/users/{$admin->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);

        $second = $this->superadmin();
        $this->actingAs($second)->put("/users/{$admin->id}", [
            'name' => $admin->name, 'email' => $admin->email,
        ])->assertRedirect('/users');
        $this->assertSame('user', $admin->fresh()->role);
    }
}
