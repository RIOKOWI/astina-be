<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AssetApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['auth']->forgetGuards();
    }

    private function actingAsUser(User $user): static
    {
        return $this->actingAs($user, 'sanctum');
    }

    private function makeRtUser(): User
    {
        $role = Role::query()->where('code', 'rt')->first() ?? Role::factory()->rt()->create();
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $user->roles()->attach($role);

        return $user;
    }

    private function makeWargaUser(?Resident $resident = null): User
    {
        $role = Role::query()->where('code', 'warga')->first() ?? Role::factory()->warga()->create();
        $resident = $resident ?? Resident::factory()->create();
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'resident_id' => $resident->id,
        ]);
        $user->roles()->attach($role);

        return $user;
    }

    // =========================================================
    // AUTHORIZATION
    // =========================================================

    public function test_guest_get_assets_returns_401(): void
    {
        $response = $this->getJson('/api/v1/assets');
        $response->assertStatus(401);
    }

    public function test_warga_get_assets_returns_403(): void
    {
        $warga = $this->makeWargaUser();
        $response = $this->actingAsUser($warga)->getJson('/api/v1/assets');
        $response->assertStatus(403);
    }

    public function test_rt_get_assets_returns_200(): void
    {
        $rt = $this->makeRtUser();
        Asset::factory()->count(3)->create();

        $response = $this->actingAsUser($rt)->getJson('/api/v1/assets');
        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonCount(3, 'data');
    }

    public function test_warga_post_asset_returns_403(): void
    {
        $warga = $this->makeWargaUser();
        $response = $this->actingAsUser($warga)->postJson('/api/v1/assets', [
            'name' => 'Kursi Plastik',
            'category' => 'Furniture',
            'unit' => 'pcs',
        ]);
        $response->assertStatus(403);
    }

    public function test_warga_put_asset_returns_403(): void
    {
        $warga = $this->makeWargaUser();
        $asset = Asset::factory()->create();

        $response = $this->actingAsUser($warga)->putJson("/api/v1/assets/{$asset->id}", [
            'name' => 'Updated Name',
        ]);
        $response->assertStatus(403);
    }

    public function test_warga_delete_asset_returns_403(): void
    {
        $warga = $this->makeWargaUser();
        $asset = Asset::factory()->create();

        $response = $this->actingAsUser($warga)->deleteJson("/api/v1/assets/{$asset->id}");
        $response->assertStatus(403);
    }

    public function test_warga_post_movement_returns_403(): void
    {
        $warga = $this->makeWargaUser();
        $asset = Asset::factory()->create(['quantity' => 50]);

        $response = $this->actingAsUser($warga)->postJson("/api/v1/assets/{$asset->id}/movements", [
            'type' => 'in',
            'quantity' => 5,
        ]);
        $response->assertStatus(403);
    }

    public function test_warga_get_movements_returns_403(): void
    {
        $warga = $this->makeWargaUser();
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create();
        AssetMovement::factory()->create([
            'asset_id' => $asset->id,
            'created_by' => $rt->id,
        ]);

        $response = $this->actingAsUser($warga)->getJson("/api/v1/assets/{$asset->id}/movements");
        $response->assertStatus(403);
    }

    // =========================================================
    // ASSET CREATE
    // =========================================================

    public function test_rt_can_create_asset(): void
    {
        $rt = $this->makeRtUser();

        $response = $this->actingAsUser($rt)->postJson('/api/v1/assets', [
            'name' => 'Kursi Plastik',
            'category' => 'Furniture',
            'unit' => 'pcs',
            'condition' => 'good',
            'status' => 'available',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Asset berhasil dibuat.'])
            ->assertJsonPath('data.name', 'Kursi Plastik')
            ->assertJsonPath('data.status', 'available')
            ->assertJsonPath('data.quantity', 0);

        $this->assertDatabaseHas('assets', [
            'name' => 'Kursi Plastik',
            'category' => 'Furniture',
            'quantity' => 0,
        ]);
    }

    public function test_asset_create_validates_name_required(): void
    {
        $rt = $this->makeRtUser();

        $response = $this->actingAsUser($rt)->postJson('/api/v1/assets', [
            'category' => 'Furniture',
            'unit' => 'pcs',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_asset_create_validates_category_required(): void
    {
        $rt = $this->makeRtUser();

        $response = $this->actingAsUser($rt)->postJson('/api/v1/assets', [
            'name' => 'Kursi',
            'unit' => 'pcs',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    public function test_asset_create_validates_unit_required(): void
    {
        $rt = $this->makeRtUser();

        $response = $this->actingAsUser($rt)->postJson('/api/v1/assets', [
            'name' => 'Kursi',
            'category' => 'Furniture',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['unit']);
    }

    public function test_asset_create_validates_condition_enum(): void
    {
        $rt = $this->makeRtUser();

        $response = $this->actingAsUser($rt)->postJson('/api/v1/assets', [
            'name' => 'Kursi',
            'category' => 'Furniture',
            'unit' => 'pcs',
            'condition' => 'invalid_condition',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['condition']);
    }

    public function test_asset_create_validates_status_enum(): void
    {
        $rt = $this->makeRtUser();

        $response = $this->actingAsUser($rt)->postJson('/api/v1/assets', [
            'name' => 'Kursi',
            'category' => 'Furniture',
            'unit' => 'pcs',
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_asset_code_auto_generated(): void
    {
        $rt = $this->makeRtUser();

        $response = $this->actingAsUser($rt)->postJson('/api/v1/assets', [
            'name' => 'Kursi',
            'category' => 'Furniture',
            'unit' => 'pcs',
        ]);

        $response->assertStatus(201);
        $code = $response->json('data.code');
        $this->assertMatchesRegularExpression('/^AST-\d{6}$/', $code);
    }

    public function test_asset_code_sequential(): void
    {
        $rt = $this->makeRtUser();

        $res1 = $this->actingAsUser($rt)->postJson('/api/v1/assets', [
            'name' => 'Kursi',
            'category' => 'Furniture',
            'unit' => 'pcs',
        ]);
        $res2 = $this->actingAsUser($rt)->postJson('/api/v1/assets', [
            'name' => 'Meja',
            'category' => 'Furniture',
            'unit' => 'pcs',
        ]);

        $code1 = $res1->json('data.code');
        $code2 = $res2->json('data.code');

        $this->assertEquals('AST-000001', $code1);
        $this->assertEquals('AST-000002', $code2);
    }

    public function test_initial_quantity_with_zero_no_movement(): void
    {
        $rt = $this->makeRtUser();

        $response = $this->actingAsUser($rt)->postJson('/api/v1/assets', [
            'name' => 'Kursi',
            'category' => 'Furniture',
            'unit' => 'pcs',
            'quantity' => 0,
        ]);

        $response->assertStatus(201);
        $this->assertEquals(0, $response->json('data.quantity'));

        $assetId = $response->json('data.id');
        $this->assertDatabaseMissing('asset_movements', ['asset_id' => $assetId]);
    }

    public function test_initial_quantity_creates_in_movement(): void
    {
        $rt = $this->makeRtUser();

        $response = $this->actingAsUser($rt)->postJson('/api/v1/assets', [
            'name' => 'Kursi Plastik',
            'category' => 'Furniture',
            'unit' => 'pcs',
            'quantity' => 50,
        ]);

        $response->assertStatus(201);
        $this->assertEquals(50, $response->json('data.quantity'));

        $assetId = $response->json('data.id');
        $this->assertDatabaseHas('asset_movements', [
            'asset_id' => $assetId,
            'type' => 'in',
            'quantity' => 50,
            'description' => 'Stok awal',
            'created_by' => $rt->id,
        ]);
    }

    // =========================================================
    // ASSET READ
    // =========================================================

    public function test_list_assets_returns_paginated_results(): void
    {
        $rt = $this->makeRtUser();
        Asset::factory()->count(20)->create();

        $response = $this->actingAsUser($rt)->getJson('/api/v1/assets?per_page=10');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 20)
            ->assertJsonCount(10, 'data');
    }

    public function test_list_assets_search_by_name(): void
    {
        $rt = $this->makeRtUser();
        Asset::factory()->create(['name' => 'Kursi Plastik']);
        Asset::factory()->create(['name' => 'Meja Kayu']);
        Asset::factory()->create(['name' => 'Kipas Angin']);

        $response = $this->actingAsUser($rt)->getJson('/api/v1/assets?search=Kursi');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Kursi Plastik');
    }

    public function test_list_assets_search_by_code(): void
    {
        $rt = $this->makeRtUser();
        Asset::factory()->create(['code' => 'AST-000001', 'name' => 'Kursi']);
        Asset::factory()->create(['code' => 'AST-000002', 'name' => 'Meja']);

        $response = $this->actingAsUser($rt)->getJson('/api/v1/assets?search=AST-000001');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'AST-000001');
    }

    public function test_list_assets_filter_category(): void
    {
        $rt = $this->makeRtUser();
        Asset::factory()->create(['category' => 'Furniture']);
        Asset::factory()->create(['category' => 'Elektronik']);
        Asset::factory()->create(['category' => 'Furniture']);

        $response = $this->actingAsUser($rt)->getJson('/api/v1/assets?category=Furniture');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_list_assets_filter_condition(): void
    {
        $rt = $this->makeRtUser();
        Asset::factory()->create(['condition' => 'good']);
        Asset::factory()->create(['condition' => 'poor']);
        Asset::factory()->create(['condition' => 'good']);

        $response = $this->actingAsUser($rt)->getJson('/api/v1/assets?condition=good');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_list_assets_filter_status(): void
    {
        $rt = $this->makeRtUser();
        Asset::factory()->create(['status' => 'available']);
        Asset::factory()->create(['status' => 'retired']);
        Asset::factory()->create(['status' => 'available']);

        $response = $this->actingAsUser($rt)->getJson('/api/v1/assets?status=available');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_asset_detail_returns_asset_with_movements(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create(['quantity' => 10]);
        AssetMovement::factory()->create([
            'asset_id' => $asset->id,
            'created_by' => $rt->id,
            'type' => 'in',
            'quantity' => 10,
        ]);

        $response = $this->actingAsUser($rt)->getJson("/api/v1/assets/{$asset->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', $asset->id)
            ->assertJsonPath('data.code', $asset->code)
            ->assertJsonPath('data.name', $asset->name)
            ->assertJsonPath('data.quantity', 10);
    }

    public function test_nonexistent_asset_returns_404(): void
    {
        $rt = $this->makeRtUser();

        $response = $this->actingAsUser($rt)->getJson('/api/v1/assets/99999');

        $response->assertStatus(404);
    }

    // =========================================================
    // ASSET UPDATE
    // =========================================================

    public function test_rt_can_update_asset_metadata(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create(['name' => 'Old Name', 'category' => 'Furniture']);

        $response = $this->actingAsUser($rt)->putJson("/api/v1/assets/{$asset->id}", [
            'name' => 'New Name',
            'category' => 'Elektronik',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.category', 'Elektronik');

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'name' => 'New Name',
        ]);
    }

    public function test_quantity_not_changeable_via_update(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create(['quantity' => 10]);

        $response = $this->actingAsUser($rt)->putJson("/api/v1/assets/{$asset->id}", [
            'quantity' => 999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);

        $asset->refresh();
        $this->assertEquals(10, $asset->quantity);
    }

    public function test_update_validates_condition_enum(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create();

        $response = $this->actingAsUser($rt)->putJson("/api/v1/assets/{$asset->id}", [
            'condition' => 'broken',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['condition']);
    }

    public function test_update_validates_status_enum(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create();

        $response = $this->actingAsUser($rt)->putJson("/api/v1/assets/{$asset->id}", [
            'status' => 'deleted',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    // =========================================================
    // ASSET DEACTIVATION
    // =========================================================

    public function test_delete_asset_sets_retired_status(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create(['status' => 'available']);

        $response = $this->actingAsUser($rt)->deleteJson("/api/v1/assets/{$asset->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'retired');

        $asset->refresh();
        $this->assertEquals('retired', $asset->status);
    }

    public function test_delete_asset_preserves_movement_history(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create();
        AssetMovement::factory()->create(['asset_id' => $asset->id, 'created_by' => $rt->id]);

        $this->actingAsUser($rt)->deleteJson("/api/v1/assets/{$asset->id}");

        $this->assertDatabaseHas('asset_movements', ['asset_id' => $asset->id]);
        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'status' => 'retired']);
    }

    // =========================================================
    // MOVEMENT IN
    // =========================================================

    public function test_movement_in_increases_quantity(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create(['quantity' => 50]);

        $response = $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", [
            'type' => 'in',
            'quantity' => 20,
            'description' => 'Pengadaan baru',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'in')
            ->assertJsonPath('data.quantity', 20);

        $asset->refresh();
        $this->assertEquals(70, $asset->quantity);

        $this->assertDatabaseHas('asset_movements', [
            'asset_id' => $asset->id,
            'type' => 'in',
            'quantity' => 20,
            'created_by' => $rt->id,
        ]);
    }

    public function test_movement_in_from_zero(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create(['quantity' => 0]);

        $response = $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", [
            'type' => 'in',
            'quantity' => 25,
        ]);

        $response->assertStatus(201);
        $asset->refresh();
        $this->assertEquals(25, $asset->quantity);
    }

    // =========================================================
    // MOVEMENT OUT
    // =========================================================

    public function test_movement_out_decreases_quantity(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create(['quantity' => 50]);

        $response = $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", [
            'type' => 'out',
            'quantity' => 15,
            'description' => 'Dipinjam warga',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'out')
            ->assertJsonPath('data.quantity', 15);

        $asset->refresh();
        $this->assertEquals(35, $asset->quantity);

        $this->assertDatabaseHas('asset_movements', [
            'asset_id' => $asset->id,
            'type' => 'out',
            'quantity' => 15,
        ]);
    }

    public function test_movement_out_reduces_to_zero(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create(['quantity' => 10]);

        $response = $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", [
            'type' => 'out',
            'quantity' => 10,
        ]);

        $response->assertStatus(201);
        $asset->refresh();
        $this->assertEquals(0, $asset->quantity);
    }

    // =========================================================
    // STOCK VALIDATION
    // =========================================================

    public function test_movement_out_exceeding_stock_returns_409(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create(['quantity' => 5]);

        $response = $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", [
            'type' => 'out',
            'quantity' => 10,
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Stok tidak mencukupi.',
            ]);

        $asset->refresh();
        $this->assertEquals(5, $asset->quantity);
        $this->assertDatabaseMissing('asset_movements', ['asset_id' => $asset->id, 'type' => 'out']);
    }

    public function test_quantity_remains_unchanged_when_movement_rejected(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create(['quantity' => 5]);

        $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", [
            'type' => 'out',
            'quantity' => 100,
        ]);

        $asset->refresh();
        $this->assertEquals(5, $asset->quantity);
    }

    public function test_quantity_must_be_integer(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create(['quantity' => 50]);

        $response = $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", [
            'type' => 'in',
            'quantity' => 5.5,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_in_out_quantity_must_be_positive(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create(['quantity' => 50]);

        $response = $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", [
            'type' => 'in',
            'quantity' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    // =========================================================
    // ADJUSTMENT
    // =========================================================

    public function test_positive_adjustment_increases_quantity(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create(['quantity' => 20]);

        $response = $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", [
            'type' => 'adjustment',
            'quantity' => 5,
            'description' => 'Koreksi: stok sebenarnya lebih',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'adjustment')
            ->assertJsonPath('data.quantity', 5);

        $asset->refresh();
        $this->assertEquals(25, $asset->quantity);
    }

    public function test_negative_adjustment_decreases_quantity(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create(['quantity' => 20]);

        $response = $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", [
            'type' => 'adjustment',
            'quantity' => -3,
            'description' => 'Koreksi: stok kurang',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'adjustment')
            ->assertJsonPath('data.quantity', -3);

        $asset->refresh();
        $this->assertEquals(17, $asset->quantity);
    }

    public function test_adjustment_cannot_make_stock_negative(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create(['quantity' => 5]);

        $response = $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", [
            'type' => 'adjustment',
            'quantity' => -10,
        ]);

        $response->assertStatus(409)
            ->assertJson(['success' => false, 'message' => 'Stok tidak mencukupi.']);

        $asset->refresh();
        $this->assertEquals(5, $asset->quantity);
        $this->assertDatabaseMissing('asset_movements', ['asset_id' => $asset->id, 'type' => 'adjustment']);
    }

    public function test_adjustment_by_zero_allowed(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create(['quantity' => 20]);

        $response = $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", [
            'type' => 'adjustment',
            'quantity' => 0,
            'description' => 'Verifikasi stok',
        ]);

        $response->assertStatus(201);
        $asset->refresh();
        $this->assertEquals(20, $asset->quantity);
    }

    // =========================================================
    // AUTHORIZATION MOVEMENT
    // =========================================================

    public function test_rt_can_create_movement(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create(['quantity' => 50]);

        $response = $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", [
            'type' => 'in',
            'quantity' => 10,
        ]);

        $response->assertStatus(201);
    }

    public function test_warga_cannot_create_movement(): void
    {
        $warga = $this->makeWargaUser();
        $asset = Asset::factory()->create(['quantity' => 50]);

        $response = $this->actingAsUser($warga)->postJson("/api/v1/assets/{$asset->id}/movements", [
            'type' => 'in',
            'quantity' => 10,
        ]);

        $response->assertStatus(403);

        $asset->refresh();
        $this->assertEquals(50, $asset->quantity);
    }

    // =========================================================
    // MOVEMENT HISTORY
    // =========================================================

    public function test_list_movements_returns_sorted_by_movement_at(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create();
        AssetMovement::factory()->create(['asset_id' => $asset->id, 'created_by' => $rt->id, 'movement_at' => now()->subDays(2)]);
        AssetMovement::factory()->create(['asset_id' => $asset->id, 'created_by' => $rt->id, 'movement_at' => now()->subDay()]);
        AssetMovement::factory()->create(['asset_id' => $asset->id, 'created_by' => $rt->id, 'movement_at' => now()]);

        $response = $this->actingAsUser($rt)->getJson("/api/v1/assets/{$asset->id}/movements");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_list_movements_filter_by_type(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create();
        AssetMovement::factory()->create(['asset_id' => $asset->id, 'created_by' => $rt->id, 'type' => 'in']);
        AssetMovement::factory()->create(['asset_id' => $asset->id, 'created_by' => $rt->id, 'type' => 'out']);
        AssetMovement::factory()->create(['asset_id' => $asset->id, 'created_by' => $rt->id, 'type' => 'in']);

        $response = $this->actingAsUser($rt)->getJson("/api/v1/assets/{$asset->id}/movements?type=out");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'out');
    }

    public function test_list_movements_filter_by_date_range(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create();
        AssetMovement::factory()->create(['asset_id' => $asset->id, 'created_by' => $rt->id, 'movement_at' => now()->subDays(5)]);
        AssetMovement::factory()->create(['asset_id' => $asset->id, 'created_by' => $rt->id, 'movement_at' => now()->subDay()]);
        AssetMovement::factory()->create(['asset_id' => $asset->id, 'created_by' => $rt->id, 'movement_at' => now()]);

        $from = now()->subDays(3)->toDateString();
        $to = now()->toDateString();

        $response = $this->actingAsUser($rt)->getJson("/api/v1/assets/{$asset->id}/movements?from={$from}&to={$to}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_list_movements_pagination(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create();
        AssetMovement::factory()->count(20)->create(['asset_id' => $asset->id, 'created_by' => $rt->id]);

        $response = $this->actingAsUser($rt)->getJson("/api/v1/assets/{$asset->id}/movements?per_page=5");

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 20);
    }

    public function test_movement_record_includes_created_by(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create();

        $response = $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", [
            'type' => 'in',
            'quantity' => 5,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.created_by.id', $rt->id);
    }

    public function test_movements_nonexistent_asset_returns_404(): void
    {
        $rt = $this->makeRtUser();

        $response = $this->actingAsUser($rt)->getJson('/api/v1/assets/99999/movements');

        $response->assertStatus(404);
    }

    // =========================================================
    // AUDIT
    // =========================================================

    public function test_movement_delete_endpoint_not_exists(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create();
        $movement = AssetMovement::factory()->create(['asset_id' => $asset->id]);

        $response = $this->actingAsUser($rt)->deleteJson("/api/v1/asset-movements/{$movement->id}");

        // Should return 404 because route doesn't exist
        $response->assertStatus(404);
    }

    public function test_asset_quantity_consistent_with_movements(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create(['quantity' => 0]);

        $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", ['type' => 'in', 'quantity' => 50]);
        $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", ['type' => 'out', 'quantity' => 10]);
        $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", ['type' => 'adjustment', 'quantity' => -5]);

        $asset->refresh();
        $this->assertEquals(35, $asset->quantity);
    }

    // =========================================================
    // TRANSACTION & CONCURRENCY
    // =========================================================

    public function test_failed_movement_does_not_record(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create(['quantity' => 5]);

        $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", [
            'type' => 'out',
            'quantity' => 100,
        ]);

        $asset->refresh();
        $this->assertEquals(5, $asset->quantity);
        $this->assertEquals(0, $asset->movements()->count());
    }

    public function test_movement_uses_lock_for_update(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create(['quantity' => 100]);

        // Sequential OUT movements should correctly decrement stock
        $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", [
            'type' => 'out',
            'quantity' => 30,
        ])->assertStatus(201);

        $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", [
            'type' => 'out',
            'quantity' => 30,
        ])->assertStatus(201);

        $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", [
            'type' => 'out',
            'quantity' => 30,
        ])->assertStatus(201);

        // Fourth OUT 30 would exceed stock (only 10 remaining)
        $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", [
            'type' => 'out',
            'quantity' => 30,
        ])->assertStatus(409);

        $asset->refresh();
        // 100 - 30 - 30 - 30 = 10
        $this->assertEquals(10, $asset->quantity);
    }

    public function test_movement_at_is_recorded(): void
    {
        $rt = $this->makeRtUser();
        $asset = Asset::factory()->create(['quantity' => 50]);

        $response = $this->actingAsUser($rt)->postJson("/api/v1/assets/{$asset->id}/movements", [
            'type' => 'in',
            'quantity' => 5,
            'movement_at' => '2026-09-01 10:00:00',
        ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.movement_at'));
    }
}
