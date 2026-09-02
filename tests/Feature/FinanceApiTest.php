<?php

namespace Tests\Feature;

use App\Models\Due;
use App\Models\DueBill;
use App\Models\FinancialTransaction;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinanceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $rtUser;

    private User $bendaharaUser;

    private User $wargaUser;

    private Resident $resident;

    private Role $rtRole;

    private Role $bendaharaRole;

    private Role $wargaRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rtRole = Role::create(['name' => 'RT', 'code' => 'rt']);
        $this->bendaharaRole = Role::create(['name' => 'Bendahara', 'code' => 'bendahara']);
        $this->wargaRole = Role::create(['name' => 'Warga', 'code' => 'warga']);

        $this->rtUser = User::factory()->create();
        $this->rtUser->roles()->attach($this->rtRole);

        $this->bendaharaUser = User::factory()->create();
        $this->bendaharaUser->roles()->attach($this->bendaharaRole);

        $this->wargaUser = User::factory()->create();
        $this->wargaUser->roles()->attach($this->wargaRole);

        $this->resident = Resident::factory()->create();
        $this->wargaUser->resident_id = $this->resident->id;
        $this->wargaUser->save();
    }

    // ========== DUE TESTS ==========

    public function test_rt_can_list_dues(): void
    {
        Due::factory()->count(3)->create();

        $response = $this->actingAs($this->rtUser)->getJson('/api/v1/dues');

        $response->assertOk()
            ->assertJsonPath('data.data.0.id', 1)
            ->assertJsonPath('data.data.0.amount', 50000);
    }

    public function test_warga_cannot_list_dues(): void
    {
        Due::factory()->create();

        $response = $this->actingAs($this->wargaUser)->getJson('/api/v1/dues');

        $response->assertStatus(403);
    }

    public function test_rt_can_create_due(): void
    {
        $data = [
            'name' => 'Iuran Bulanan',
            'description' => 'Iuran bulanan RT 05',
            'amount' => 50000,
            'frequency' => 'monthly',
        ];

        $response = $this->actingAs($this->rtUser)->postJson('/api/v1/dues', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Iuran Bulanan')
            ->assertJsonPath('data.amount', 50000)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('dues', ['name' => 'Iuran Bulanan', 'amount' => 50000]);
    }

    public function test_rt_can_update_due(): void
    {
        $due = Due::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->rtUser)->putJson("/api/v1/dues/{$due->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_rt_can_deactivate_due(): void
    {
        $due = Due::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->rtUser)->deleteJson("/api/v1/dues/{$due->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_generate_monthly_due_bills(): void
    {
        $due = Due::factory()->create([
            'is_active' => true,
            'frequency' => 'monthly',
            'amount' => 50000,
        ]);

        Resident::factory()->count(2)->create(['status' => 'active']);

        $response = $this->actingAs($this->rtUser)->postJson('/api/v1/dues/generate-bills', [
            'year' => 2026,
            'month' => 1,
        ]);

        $response->assertOk();

        $this->assertEquals(3, DueBill::count());
        $this->assertEquals(50000, DueBill::first()->amount);
    }

    public function test_generate_due_bills_idempotent(): void
    {
        $due = Due::factory()->create(['is_active' => true, 'frequency' => 'monthly']);

        $this->actingAs($this->rtUser)->postJson('/api/v1/dues/generate-bills', [
            'year' => 2026,
            'month' => 1,
        ]);

        $response = $this->actingAs($this->rtUser)->postJson('/api/v1/dues/generate-bills', [
            'year' => 2026,
            'month' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.created', 0);
    }

    public function test_my_due_bills(): void
    {
        $due = Due::factory()->create(['is_active' => true, 'frequency' => 'monthly', 'amount' => 50000]);
        DueBill::factory()->create([
            'due_id' => $due->id,
            'resident_id' => $this->resident->id,
            'amount' => 50000,
        ]);

        $response = $this->actingAs($this->wargaUser)->getJson('/api/v1/my/due-bills');

        $response->assertOk();
        $this->assertEquals(50000, $response->json('data.data.0.amount'));
    }

    // ========== PAYMENT TESTS ==========

    public function test_warga_can_create_payment(): void
    {
        $due = Due::factory()->create(['is_active' => true, 'amount' => 50000]);
        $dueBill = DueBill::factory()->create([
            'due_id' => $due->id,
            'resident_id' => $this->resident->id,
            'amount' => 50000,
        ]);

        $response = $this->actingAs($this->wargaUser)->postJson('/api/v1/payments', [
            'due_bill_id' => $dueBill->id,
            'amount' => 50000,
            'method' => 'transfer',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount', 50000);
    }

    public function test_payment_amount_must_match_due_bill(): void
    {
        $due = Due::factory()->create(['amount' => 50000]);
        $dueBill = DueBill::factory()->create([
            'due_id' => $due->id,
            'resident_id' => $this->resident->id,
            'amount' => 50000,
        ]);

        $response = $this->actingAs($this->wargaUser)->postJson('/api/v1/payments', [
            'due_bill_id' => $dueBill->id,
            'amount' => 10000,
        ]);

        $response->assertStatus(422);
    }

    public function test_no_duplicate_pending_payment(): void
    {
        $due = Due::factory()->create(['amount' => 50000]);
        $dueBill = DueBill::factory()->create([
            'due_id' => $due->id,
            'resident_id' => $this->resident->id,
            'amount' => 50000,
        ]);

        Payment::factory()->create([
            'due_bill_id' => $dueBill->id,
            'resident_id' => $this->resident->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->wargaUser)->postJson('/api/v1/payments', [
            'due_bill_id' => $dueBill->id,
            'amount' => 50000,
        ]);

        $response->assertStatus(409);
    }

    public function test_upload_payment_proof(): void
    {
        Storage::fake('public');

        $payment = Payment::factory()->create([
            'resident_id' => $this->resident->id,
            'status' => 'pending',
        ]);

        $file = UploadedFile::fake()->image('bukti.jpg', 800, 600);

        Queue::fake();

        $response = $this->actingAs($this->wargaUser)->postJson("/api/v1/payments/{$payment->id}/proof", [
            'file' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.file_name', 'bukti.jpg');

        $this->assertDatabaseHas('payment_proofs', ['payment_id' => $payment->id]);
    }

    public function test_cannot_upload_proof_for_other_payment(): void
    {
        Storage::fake('public');

        $otherResident = Resident::factory()->create();
        $payment = Payment::factory()->create([
            'resident_id' => $otherResident->id,
            'status' => 'pending',
        ]);

        $file = UploadedFile::fake()->image('bukti.jpg');

        $response = $this->actingAs($this->wargaUser)->postJson("/api/v1/payments/{$payment->id}/proof", [
            'file' => $file,
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_upload_proof_for_non_pending_payment(): void
    {
        Storage::fake('public');

        $payment = Payment::factory()->create([
            'resident_id' => $this->resident->id,
            'status' => 'approved',
        ]);

        $file = UploadedFile::fake()->image('bukti.jpg');

        $response = $this->actingAs($this->wargaUser)->postJson("/api/v1/payments/{$payment->id}/proof", [
            'file' => $file,
        ]);

        $response->assertStatus(409);
    }

    // ========== PAYMENT APPROVAL TESTS ==========

    public function test_bendahara_can_approve_payment(): void
    {
        $payment = Payment::factory()->create([
            'resident_id' => $this->resident->id,
            'status' => 'pending',
            'amount' => 50000,
        ]);

        PaymentProof::factory()->create(['payment_id' => $payment->id]);

        Queue::fake();

        $response = $this->actingAs($this->bendaharaUser)->postJson("/api/v1/payments/{$payment->id}/approve");

        $response->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('financial_transactions', [
            'payment_id' => $payment->id,
            'type' => 'income',
            'amount' => 50000,
        ]);
    }

    public function test_bendahara_can_reject_payment(): void
    {
        $payment = Payment::factory()->create([
            'resident_id' => $this->resident->id,
            'status' => 'pending',
        ]);

        Queue::fake();

        $response = $this->actingAs($this->bendaharaUser)->postJson("/api/v1/payments/{$payment->id}/reject", [
            'reason' => 'Bukti tidak jelas',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Bukti tidak jelas');
    }

    public function test_cannot_approve_without_proof(): void
    {
        $payment = Payment::factory()->create([
            'resident_id' => $this->resident->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->bendaharaUser)->postJson("/api/v1/payments/{$payment->id}/approve");

        $response->assertStatus(422);
    }

    public function test_cannot_approve_already_approved(): void
    {
        $payment = Payment::factory()->create([
            'resident_id' => $this->resident->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->bendaharaUser)->postJson("/api/v1/payments/{$payment->id}/approve");

        $response->assertStatus(409);
    }

    public function test_warga_cannot_approve_payment(): void
    {
        $payment = Payment::factory()->create(['resident_id' => $this->resident->id, 'status' => 'pending']);

        $response = $this->actingAs($this->wargaUser)->postJson("/api/v1/payments/{$payment->id}/approve");

        $response->assertStatus(403);
    }

    public function test_pending_payments_list(): void
    {
        $p1 = Payment::factory()->create(['status' => 'pending']);
        $p2 = Payment::factory()->create(['status' => 'pending']);
        Payment::factory()->create(['status' => 'approved']);
        PaymentProof::factory()->create(['payment_id' => $p1->id]);
        PaymentProof::factory()->create(['payment_id' => $p2->id]);

        $response = $this->actingAs($this->bendaharaUser)->getJson('/api/v1/payments/pending');

        $response->assertOk();
        $this->assertEquals(2, $response->json('meta.total'));
    }

    public function test_no_duplicate_financial_transaction_for_payment(): void
    {
        $payment = Payment::factory()->create([
            'resident_id' => $this->resident->id,
            'status' => 'approved',
            'amount' => 50000,
        ]);

        PaymentProof::factory()->create(['payment_id' => $payment->id]);

        FinancialTransaction::factory()->create([
            'payment_id' => $payment->id,
            'type' => 'income',
        ]);

        $response = $this->actingAs($this->bendaharaUser)->postJson("/api/v1/payments/{$payment->id}/approve");

        $response->assertStatus(409);
    }

    // ========== FINANCE TRANSACTION TESTS ==========

    public function test_get_finance_summary(): void
    {
        FinancialTransaction::factory()->create(['type' => 'income', 'amount' => 100000]);
        FinancialTransaction::factory()->create(['type' => 'income', 'amount' => 50000]);
        FinancialTransaction::factory()->create(['type' => 'expense', 'amount' => 30000]);

        $response = $this->actingAs($this->wargaUser)->getJson('/api/v1/finance/summary');

        $response->assertOk()
            ->assertJsonPath('data.balance', 120000)
            ->assertJsonPath('data.total_income', 150000)
            ->assertJsonPath('data.total_expense', 30000);
    }

    public function test_get_finance_transactions(): void
    {
        FinancialTransaction::factory()->count(5)->create();

        $response = $this->actingAs($this->wargaUser)->getJson('/api/v1/finance/transactions');

        $response->assertOk()
            ->assertJsonStructure(['success', 'message', 'data' => ['data'], 'meta']);
    }

    public function test_filter_finance_transactions_by_type(): void
    {
        FinancialTransaction::factory()->count(3)->create(['type' => 'income']);
        FinancialTransaction::factory()->count(2)->create(['type' => 'expense']);

        $response = $this->actingAs($this->wargaUser)->getJson('/api/v1/finance/transactions?type=income');

        $response->assertOk();
        $this->assertCount(3, $response->json('data.data'));
    }

    public function test_create_expense_transaction(): void
    {
        FinancialTransaction::factory()->create(['type' => 'income', 'amount' => 1000000]);

        $response = $this->actingAs($this->bendaharaUser)->postJson('/api/v1/finance/transactions', [
            'type' => 'expense',
            'amount' => 50000,
            'category' => 'operational',
            'description' => 'Pengeluaran operasional',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'expense')
            ->assertJsonPath('data.amount', 50000);
    }

    public function test_cannot_create_expense_exceeding_balance(): void
    {
        FinancialTransaction::factory()->create(['type' => 'income', 'amount' => 10000]);

        $response = $this->actingAs($this->bendaharaUser)->postJson('/api/v1/finance/transactions', [
            'type' => 'expense',
            'amount' => 100000,
            'category' => 'operational',
        ]);

        $response->assertStatus(409);
    }

    public function test_warga_cannot_create_transaction(): void
    {
        $response = $this->actingAs($this->wargaUser)->postJson('/api/v1/finance/transactions', [
            'type' => 'expense',
            'amount' => 50000,
            'category' => 'operational',
        ]);

        $response->assertStatus(403);
    }

    public function test_rt_can_create_transaction(): void
    {
        $response = $this->actingAs($this->rtUser)->postJson('/api/v1/finance/transactions', [
            'type' => 'income',
            'amount' => 50000,
            'category' => 'donasi',
            'description' => 'Donasi warga',
        ]);

        $response->assertStatus(201);
    }

    public function test_my_payments_list(): void
    {
        Payment::factory()->count(3)->create(['resident_id' => $this->resident->id]);

        $response = $this->actingAs($this->wargaUser)->getJson('/api/v1/my/payments');

        $response->assertOk();
        $this->assertEquals(3, $response->json('meta.total'));
    }

    public function test_monthly_reminder_command(): void
    {
        Queue::fake();

        $due = Due::factory()->create(['is_active' => true, 'frequency' => 'monthly', 'amount' => 50000]);
        $dueBill = DueBill::factory()->create([
            'due_id' => $due->id,
            'resident_id' => $this->resident->id,
            'amount' => 50000,
            'status' => 'unpaid',
        ]);

        $this->artisan('finance:send-monthly-reminder', ['year' => 2026, 'month' => 9])
            ->assertExitCode(0);
    }

    public function test_monthly_reminder_command_no_unpaid(): void
    {
        Queue::fake();

        $due = Due::factory()->create(['is_active' => true, 'frequency' => 'monthly']);
        $dueBill = DueBill::factory()->create([
            'due_id' => $due->id,
            'resident_id' => $this->resident->id,
            'status' => 'paid',
        ]);

        $this->artisan('finance:send-monthly-reminder', ['year' => 2026, 'month' => 9])
            ->expectsOutput('Tidak ada tagihan unpaid.')
            ->assertExitCode(0);
    }

    public function test_validation_create_due(): void
    {
        $response = $this->actingAs($this->rtUser)->postJson('/api/v1/dues', [
            'amount' => 50000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_validation_amount_min(): void
    {
        $response = $this->actingAs($this->rtUser)->postJson('/api/v1/dues', [
            'name' => 'Test',
            'amount' => 50,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_validation_payment_amount_min(): void
    {
        $due = Due::factory()->create(['amount' => 50000]);
        $dueBill = DueBill::factory()->create([
            'due_id' => $due->id,
            'resident_id' => $this->resident->id,
            'amount' => 50000,
        ]);

        $response = $this->actingAs($this->wargaUser)->postJson('/api/v1/payments', [
            'due_bill_id' => $dueBill->id,
            'amount' => 50,
        ]);

        $response->assertStatus(422);
    }

    public function test_validation_reject_requires_reason(): void
    {
        $payment = Payment::factory()->create(['resident_id' => $this->resident->id, 'status' => 'pending']);

        $response = $this->actingAs($this->bendaharaUser)->postJson("/api/v1/payments/{$payment->id}/reject");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }
}
