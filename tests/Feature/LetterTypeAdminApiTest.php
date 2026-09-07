<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterField;
use App\Models\LetterFieldValue;
use App\Models\LetterType;
use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LetterTypeAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private User $rtUser;

    private User $wargaUser;

    protected function setUp(): void
    {
        parent::setUp();

        $rtRole = Role::create(['name' => 'RT', 'code' => 'rt']);
        $wargaRole = Role::create(['name' => 'Warga', 'code' => 'warga']);

        $this->rtUser = User::factory()->create();
        $this->rtUser->roles()->attach($rtRole);

        $resident = Resident::factory()->create();
        $this->wargaUser = User::factory()->create(['resident_id' => $resident->id]);
        $this->wargaUser->roles()->attach($wargaRole);
    }

    // ========== LETTER TYPE CRUD ==========

    public function test_rt_can_create_letter_type(): void
    {
        $response = $this->actingAs($this->rtUser)->postJson('/api/v1/letter-types', [
            'code' => 'SKU',
            'name' => 'Surat Keterangan Usaha',
            'description' => 'Surat keterangan usaha',
            'fields' => [
                ['field_key' => 'nama_usaha', 'label' => 'Nama Usaha', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1],
                ['field_key' => 'jenis_usaha', 'label' => 'Jenis Usaha', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 2],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'SKU')
            ->assertJsonPath('data.name', 'Surat Keterangan Usaha')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseCount('letter_fields', 2);
        $this->assertDatabaseHas('letter_fields', ['field_key' => 'nama_usaha']);
    }

    public function test_rt_can_create_letter_type_without_fields(): void
    {
        $response = $this->actingAs($this->rtUser)->postJson('/api/v1/letter-types', [
            'code' => 'SKU',
            'name' => 'Surat Keterangan Usaha',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('letter_fields', 0);
    }

    public function test_warga_cannot_create_letter_type(): void
    {
        $response = $this->actingAs($this->wargaUser)->postJson('/api/v1/letter-types', [
            'code' => 'SKU',
            'name' => 'Surat Keterangan Usaha',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_unauthenticated_cannot_create_letter_type(): void
    {
        $response = $this->postJson('/api/v1/letter-types', [
            'code' => 'SKU',
            'name' => 'Surat Keterangan Usaha',
        ]);

        $response->assertStatus(401);
    }

    public function test_create_letter_type_validates_code_unique(): void
    {
        LetterType::factory()->create(['code' => 'SKU']);

        $response = $this->actingAs($this->rtUser)->postJson('/api/v1/letter-types', [
            'code' => 'SKU',
            'name' => 'Surat Keterangan Usaha',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_create_letter_type_validates_field_key_format(): void
    {
        $response = $this->actingAs($this->rtUser)->postJson('/api/v1/letter-types', [
            'code' => 'SKU',
            'name' => 'Surat Keterangan Usaha',
            'fields' => [
                ['field_key' => 'NamaUsaha', 'label' => 'Nama Usaha', 'field_type' => 'text', 'is_required' => true],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fields.0.field_key']);
    }

    public function test_rt_can_list_all_letter_types(): void
    {
        LetterType::factory()->count(2)->create(['is_active' => true]);
        LetterType::factory()->count(3)->create(['is_active' => false]);

        $response = $this->actingAs($this->rtUser)->getJson('/api/v1/letter-types?include_inactive=1');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['data' => [['id', 'code', 'name', 'is_active', 'fields']]],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $this->assertEquals(5, $response->json('meta.total'));
    }

    public function test_rt_can_filter_active_only(): void
    {
        LetterType::factory()->count(2)->create(['is_active' => true]);
        LetterType::factory()->count(3)->create(['is_active' => false]);

        $response = $this->actingAs($this->rtUser)->getJson('/api/v1/letter-types?include_inactive=0');

        $this->assertEquals(2, $response->json('meta.total'));
    }

    public function test_warga_list_returns_only_active(): void
    {
        LetterType::factory()->count(2)->create(['is_active' => true]);
        LetterType::factory()->count(3)->create(['is_active' => false]);

        $response = $this->actingAs($this->wargaUser)->getJson('/api/v1/letter-types');

        $response->assertOk()
            ->assertJsonPath('success', true);

        // warga gets non-paginated collection
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_rt_can_update_letter_type(): void
    {
        $letterType = LetterType::factory()->create(['code' => 'SKU', 'name' => 'Old Name']);

        $response = $this->actingAs($this->rtUser)->putJson("/api/v1/letter-types/{$letterType->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.code', 'SKU');
    }

    public function test_rt_can_update_letter_type_with_fields(): void
    {
        $letterType = LetterType::factory()->create(['code' => 'SKU']);
        LetterField::factory()->create([
            'letter_type_id' => $letterType->id,
            'field_key' => 'old_field',
            'label' => 'Old Field',
        ]);

        $response = $this->actingAs($this->rtUser)->putJson("/api/v1/letter-types/{$letterType->id}", [
            'fields' => [
                ['field_key' => 'new_field', 'label' => 'New Field', 'field_type' => 'text', 'is_required' => true],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('letter_fields', 1);
        $this->assertDatabaseHas('letter_fields', ['field_key' => 'new_field']);
        $this->assertDatabaseMissing('letter_fields', ['field_key' => 'old_field']);
    }

    public function test_warga_cannot_update_letter_type(): void
    {
        $letterType = LetterType::factory()->create();

        $response = $this->actingAs($this->wargaUser)->putJson("/api/v1/letter-types/{$letterType->id}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_rt_can_deactivate_letter_type(): void
    {
        $letterType = LetterType::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->rtUser)->deleteJson("/api/v1/letter-types/{$letterType->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('letter_types', ['id' => $letterType->id, 'is_active' => false]);
    }

    public function test_warga_cannot_deactivate_letter_type(): void
    {
        $letterType = LetterType::factory()->create();

        $response = $this->actingAs($this->wargaUser)->deleteJson("/api/v1/letter-types/{$letterType->id}");

        $response->assertStatus(403);
    }

    // ========== LETTER FIELD CRUD ==========

    public function test_rt_can_add_field_to_letter_type(): void
    {
        $letterType = LetterType::factory()->create();

        $response = $this->actingAs($this->rtUser)->postJson("/api/v1/letter-types/{$letterType->id}/fields", [
            'field_key' => 'nama_usaha',
            'label' => 'Nama Usaha',
            'field_type' => 'text',
            'is_required' => true,
            'sort_order' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.field_key', 'nama_usaha')
            ->assertJsonPath('data.label', 'Nama Usaha');
    }

    public function test_rt_cannot_add_duplicate_field_key_to_same_letter_type(): void
    {
        $letterType = LetterType::factory()->create();
        LetterField::factory()->create([
            'letter_type_id' => $letterType->id,
            'field_key' => 'nama_usaha',
        ]);

        $response = $this->actingAs($this->rtUser)->postJson("/api/v1/letter-types/{$letterType->id}/fields", [
            'field_key' => 'nama_usaha',
            'label' => 'Nama Usaha Lagi',
            'field_type' => 'text',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['field_key']);
    }

    public function test_rt_can_add_same_field_key_to_different_letter_type(): void
    {
        $type1 = LetterType::factory()->create();
        $type2 = LetterType::factory()->create();
        LetterField::factory()->create([
            'letter_type_id' => $type1->id,
            'field_key' => 'nama_usaha',
        ]);

        $response = $this->actingAs($this->rtUser)->postJson("/api/v1/letter-types/{$type2->id}/fields", [
            'field_key' => 'nama_usaha',
            'label' => 'Nama Usaha',
            'field_type' => 'text',
        ]);

        $response->assertStatus(201);
    }

    public function test_warga_cannot_add_field(): void
    {
        $letterType = LetterType::factory()->create();

        $response = $this->actingAs($this->wargaUser)->postJson("/api/v1/letter-types/{$letterType->id}/fields", [
            'field_key' => 'hack',
            'label' => 'Hack',
            'field_type' => 'text',
        ]);

        $response->assertStatus(403);
    }

    public function test_rt_can_update_field(): void
    {
        $letterType = LetterType::factory()->create();
        $field = LetterField::factory()->create([
            'letter_type_id' => $letterType->id,
            'field_key' => 'old_key',
            'label' => 'Old Label',
        ]);

        $response = $this->actingAs($this->rtUser)->putJson("/api/v1/letter-types/{$letterType->id}/fields/{$field->id}", [
            'label' => 'Updated Label',
            'is_required' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.label', 'Updated Label')
            ->assertJsonPath('data.is_required', false)
            ->assertJsonPath('data.field_key', 'old_key');
    }

    public function test_rt_cannot_update_field_of_different_letter_type(): void
    {
        $type1 = LetterType::factory()->create();
        $type2 = LetterType::factory()->create();
        $field = LetterField::factory()->create(['letter_type_id' => $type1->id]);

        // Try to update field from type1 using type2 route
        $response = $this->actingAs($this->rtUser)->putJson("/api/v1/letter-types/{$type2->id}/fields/{$field->id}", [
            'label' => 'Hacked',
        ]);

        $response->assertStatus(404);
    }

    public function test_warga_cannot_update_field(): void
    {
        $letterType = LetterType::factory()->create();
        $field = LetterField::factory()->create(['letter_type_id' => $letterType->id]);

        $response = $this->actingAs($this->wargaUser)->putJson("/api/v1/letter-types/{$letterType->id}/fields/{$field->id}", [
            'label' => 'Hacked',
        ]);

        $response->assertStatus(403);
    }

    public function test_rt_can_delete_field(): void
    {
        $letterType = LetterType::factory()->create();
        $field = LetterField::factory()->create(['letter_type_id' => $letterType->id]);

        $response = $this->actingAs($this->rtUser)->deleteJson("/api/v1/letter-types/{$letterType->id}/fields/{$field->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('letter_fields', ['id' => $field->id]);
    }

    public function test_rt_cannot_delete_field_with_values(): void
    {
        $letterType = LetterType::factory()->create();
        $letter = Letter::factory()->create(['letter_type_id' => $letterType->id]);
        $field = LetterField::factory()->create(['letter_type_id' => $letterType->id]);
        LetterFieldValue::factory()->create([
            'letter_id' => $letter->id,
            'letter_field_id' => $field->id,
        ]);

        $response = $this->actingAs($this->rtUser)->deleteJson("/api/v1/letter-types/{$letterType->id}/fields/{$field->id}");

        $response->assertStatus(409);

        $this->assertDatabaseHas('letter_fields', ['id' => $field->id]);
    }

    public function test_warga_cannot_delete_field(): void
    {
        $letterType = LetterType::factory()->create();
        $field = LetterField::factory()->create(['letter_type_id' => $letterType->id]);

        $response = $this->actingAs($this->wargaUser)->deleteJson("/api/v1/letter-types/{$letterType->id}/fields/{$field->id}");

        $response->assertStatus(403);
    }

    public function test_warga_cannot_view_inactive_letter_type(): void
    {
        $letterType = LetterType::factory()->create(['is_active' => false]);

        $response = $this->actingAs($this->wargaUser)->getJson("/api/v1/letter-types/{$letterType->id}");

        $response->assertStatus(404);
    }

    public function test_rt_show_letter_type_includes_created_at(): void
    {
        $letterType = LetterType::factory()->create();

        $response = $this->actingAs($this->rtUser)->getJson('/api/v1/letter-types');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['data' => [['created_at']]],
            ]);
    }
}
