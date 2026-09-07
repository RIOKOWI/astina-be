<?php

namespace Tests\Feature;

use App\Models\DueBill;
use App\Models\Letter;
use App\Models\Payment;
use App\Models\Resident;
use App\Models\SosAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_expected_tables_exist(): void
    {
        $rawTables = DB::select('SHOW TABLES');
        $tables = array_map(fn ($t) => array_values((array) $t)[0], $rawTables);
        $expectedTables = [
            'sessions',
            'roles', 'residents', 'households', 'resident_households',
            'users', 'role_user',
            'device_tokens', 'notifications',
            'activities', 'activity_attachments', 'activity_reads',
            'letter_types', 'letter_fields', 'letters', 'letter_field_values',
            'letter_approvals', 'letter_documents',
            'dues', 'due_bills', 'payments', 'payment_proofs', 'financial_transactions',
            'complaints', 'complaint_attachments', 'complaint_comments',
            'sos_alerts', 'sos_responses',
            'assets', 'asset_movements',
            'media',
        ];

        foreach ($expectedTables as $table) {
            $this->assertContains($table, $tables, "Table '{$table}' should exist");
        }
    }

    public function test_all_models_can_be_instantiated(): void
    {
        $models = [
            'App\Models\Role',
            'App\Models\Resident',
            'App\Models\Household',
            'App\Models\ResidentHousehold',
            'App\Models\User',
            'App\Models\DeviceToken',
            'App\Models\Notification',
            'App\Models\Activity',
            'App\Models\ActivityAttachment',
            'App\Models\ActivityRead',
            'App\Models\LetterType',
            'App\Models\LetterField',
            'App\Models\Letter',
            'App\Models\LetterFieldValue',
            'App\Models\LetterApproval',
            'App\Models\LetterDocument',
            'App\Models\Due',
            'App\Models\DueBill',
            'App\Models\Payment',
            'App\Models\PaymentProof',
            'App\Models\FinancialTransaction',
            'App\Models\Complaint',
            'App\Models\ComplaintAttachment',
            'App\Models\ComplaintComment',
            'App\Models\SosAlert',
            'App\Models\SosResponse',
            'App\Models\Asset',
            'App\Models\AssetMovement',
            'App\Models\Media',
        ];

        foreach ($models as $model) {
            $this->assertTrue(
                class_exists($model),
                "Model class '{$model}' should exist"
            );
        }
    }

    public function test_user_resident_relationship(): void
    {
        $user = new User;
        $this->assertTrue(method_exists($user, 'resident'));
        $this->assertTrue(method_exists($user, 'roles'));
    }

    public function test_resident_household_relationship(): void
    {
        $resident = new Resident;
        $this->assertTrue(method_exists($resident, 'households'));
    }

    public function test_letter_relationships(): void
    {
        $letter = new Letter;
        $this->assertTrue(method_exists($letter, 'resident'));
        $this->assertTrue(method_exists($letter, 'submitter'));
        $this->assertTrue(method_exists($letter, 'letterType'));
        $this->assertTrue(method_exists($letter, 'approvals'));
    }

    public function test_finance_relationships(): void
    {
        $payment = new Payment;
        $this->assertTrue(method_exists($payment, 'dueBill'));
        $this->assertTrue(method_exists($payment, 'resident'));
        $this->assertTrue(method_exists($payment, 'approver'));
        $this->assertTrue(method_exists($payment, 'proofs'));

        $dueBill = new DueBill;
        $this->assertTrue(method_exists($dueBill, 'due'));
        $this->assertTrue(method_exists($dueBill, 'payments'));
    }

    public function test_sos_relationships(): void
    {
        $alert = new SosAlert;
        $this->assertTrue(method_exists($alert, 'triggerer'));
        $this->assertTrue(method_exists($alert, 'resolver'));
        $this->assertTrue(method_exists($alert, 'responses'));
    }

    public function test_users_resident_id_is_unique(): void
    {
        $indexes = DB::select('SHOW INDEX FROM users WHERE Column_name = "resident_id"');
        $uniqueIndexes = array_filter($indexes, fn ($idx) => (int) $idx->Non_unique === 0);
        $this->assertNotEmpty($uniqueIndexes, 'users.resident_id should have a UNIQUE constraint');
    }

    public function test_activity_reads_unique_constraint(): void
    {
        $columns = Schema::getColumnListing('activity_reads');
        $this->assertContains('id', $columns);
    }
}
