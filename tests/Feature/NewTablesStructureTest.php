<?php

namespace Tests\Feature;

use App\Models\DesignReport;
use App\Models\LogWhatsapp;
use App\Models\Resident;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NewTablesStructureTest extends TestCase
{
    use RefreshDatabase;

    // ========== LOG_WHATSAPP ==========

    public function test_log_whatsapp_table_has_all_required_columns(): void
    {
        $columns = Schema::getColumnListing('log_whatsapp');

        $expected = ['id', 'id_warga', 'id_message', 'message', 'incoming_time', 'replied'];
        foreach ($expected as $col) {
            $this->assertContains($col, $columns, "log_whatsapp should have column '{$col}'");
        }

        $this->assertNotContains('created_at', $columns);
        $this->assertNotContains('updated_at', $columns);
    }

    public function test_log_whatsapp_can_be_created_and_read(): void
    {
        $resident = Resident::factory()->create();

        $log = LogWhatsapp::create([
            'id_warga' => $resident->id,
            'id_message' => 'msg-001',
            'message' => 'Halo, saya ingin bertanya.',
            'incoming_time' => now(),
            'replied' => 'Balasan otomatis.',
        ]);

        $this->assertDatabaseHas('log_whatsapp', [
            'id' => $log->id,
            'id_warga' => $resident->id,
            'id_message' => 'msg-001',
        ]);
    }

    public function test_log_whatsapp_id_warga_relates_to_resident(): void
    {
        $resident = Resident::factory()->create();

        $log = LogWhatsapp::create([
            'id_warga' => $resident->id,
            'id_message' => 'msg-002',
            'message' => 'Test message',
            'incoming_time' => now(),
        ]);

        $this->assertEquals($resident->id, $log->resident->id);
    }

    public function test_log_whatsapp_resident_has_many_whatsapp_logs(): void
    {
        $resident = Resident::factory()->create();

        LogWhatsapp::create([
            'id_warga' => $resident->id,
            'id_message' => 'msg-003a',
            'message' => 'Message A',
            'incoming_time' => now(),
        ]);
        LogWhatsapp::create([
            'id_warga' => $resident->id,
            'id_message' => 'msg-003b',
            'message' => 'Message B',
            'incoming_time' => now(),
        ]);

        $this->assertCount(2, $resident->whatsappLogs);
    }

    public function test_log_whatsapp_incoming_time_is_cast_to_datetime(): void
    {
        $resident = Resident::factory()->create();
        $now = now();

        $log = LogWhatsapp::create([
            'id_warga' => $resident->id,
            'id_message' => 'msg-004',
            'message' => 'Test',
            'incoming_time' => $now,
        ]);

        $this->assertInstanceOf(Carbon::class, $log->incoming_time);
        $this->assertEquals($now->format('Y-m-d H:i'), $log->incoming_time->format('Y-m-d H:i'));
    }

    public function test_log_whatsapp_replied_can_be_null(): void
    {
        $resident = Resident::factory()->create();

        $log = LogWhatsapp::create([
            'id_warga' => $resident->id,
            'id_message' => 'msg-005',
            'message' => 'Pesan tanpa balasan',
            'incoming_time' => now(),
            'replied' => null,
        ]);

        $this->assertNull($log->replied);
        $this->assertDatabaseHas('log_whatsapp', [
            'id' => $log->id,
            'replied' => null,
        ]);
    }

    public function test_log_whatsapp_id_message_unique(): void
    {
        $resident = Resident::factory()->create();

        LogWhatsapp::create([
            'id_warga' => $resident->id,
            'id_message' => 'msg-unique-001',
            'message' => 'First',
            'incoming_time' => now(),
        ]);

        $this->expectException(QueryException::class);
        LogWhatsapp::create([
            'id_warga' => $resident->id,
            'id_message' => 'msg-unique-001',
            'message' => 'Duplicate',
            'incoming_time' => now(),
        ]);
    }

    public function test_log_whatsapp_fk_rejects_invalid_resident(): void
    {
        $this->expectException(QueryException::class);
        LogWhatsapp::create([
            'id_warga' => 99999,
            'id_message' => 'msg-orphan',
            'message' => 'Orphan message',
            'incoming_time' => now(),
        ]);
    }

    public function test_log_whatsapp_timestamps_is_false(): void
    {
        $log = new LogWhatsapp;
        $this->assertFalse($log->timestamps);
    }

    // ========== DESIGN_REPORT ==========

    public function test_design_report_table_has_all_required_columns(): void
    {
        $columns = Schema::getColumnListing('design_report');

        $expected = ['id', 'code', 'name', 'content', 'header', 'footer', 'rw_approval', 'lurah_approval', 'camat_approval', 'cdt', 'mdt'];
        foreach ($expected as $col) {
            $this->assertContains($col, $columns, "design_report should have column '{$col}'");
        }

        $this->assertNotContains('created_at', $columns);
        $this->assertNotContains('updated_at', $columns);
    }

    public function test_design_report_can_be_created_and_read(): void
    {
        $report = DesignReport::create([
            'code' => 'RPT-001',
            'name' => 'Laporan Bulanan',
            'content' => '<html><body>Content</body></html>',
            'header' => '<html><head>Header</head></html>',
            'footer' => '<html><body>Footer</body></html>',
            'rw_approval' => 'N',
            'lurah_approval' => 'N',
            'camat_approval' => 'N',
        ]);

        $this->assertDatabaseHas('design_report', [
            'id' => $report->id,
            'code' => 'RPT-001',
            'name' => 'Laporan Bulanan',
        ]);
    }

    public function test_design_report_code_is_unique_at_db_level(): void
    {
        DesignReport::create([
            'code' => 'RPT-UNIQUE-001',
            'name' => 'Report A',
            'content' => 'Content A',
            'header' => 'Header A',
            'footer' => 'Footer A',
        ]);

        $this->expectException(QueryException::class);
        DesignReport::create([
            'code' => 'RPT-UNIQUE-001',
            'name' => 'Report B',
            'content' => 'Content B',
            'header' => 'Header B',
            'footer' => 'Footer B',
        ]);
    }

    public function test_design_report_name_max_50_chars(): void
    {
        $report = DesignReport::create([
            'code' => 'RPT-050',
            'name' => str_repeat('X', 50),
            'content' => 'Content',
            'header' => 'Header',
            'footer' => 'Footer',
        ]);

        $this->assertEquals(50, strlen($report->name));
    }

    public function test_design_report_approval_defaults_to_n(): void
    {
        $report = DesignReport::create([
            'code' => 'RPT-DEF-001',
            'name' => 'Default Approval Test',
            'content' => 'Content',
            'header' => 'Header',
            'footer' => 'Footer',
        ]);

        $this->assertEquals('N', $report->rw_approval);
        $this->assertEquals('N', $report->lurah_approval);
        $this->assertEquals('N', $report->camat_approval);
    }

    public function test_design_report_approval_y_can_be_stored(): void
    {
        $report = DesignReport::create([
            'code' => 'RPT-Y-001',
            'name' => 'Approval Y Test',
            'content' => 'Content',
            'header' => 'Header',
            'footer' => 'Footer',
            'rw_approval' => 'Y',
            'lurah_approval' => 'Y',
            'camat_approval' => 'Y',
        ]);

        $this->assertEquals('Y', $report->rw_approval);
        $this->assertEquals('Y', $report->lurah_approval);
        $this->assertEquals('Y', $report->camat_approval);
    }

    public function test_design_report_cdt_and_mdt_are_populated(): void
    {
        $report = DesignReport::create([
            'code' => 'RPT-TS-001',
            'name' => 'Timestamp Test',
            'content' => 'Content',
            'header' => 'Header',
            'footer' => 'Footer',
        ]);

        $this->assertNotNull($report->cdt);
        $this->assertNotNull($report->mdt);
        $this->assertInstanceOf(Carbon::class, $report->cdt);
        $this->assertInstanceOf(Carbon::class, $report->mdt);
    }

    public function test_design_report_mdt_changes_on_update(): void
    {
        $report = DesignReport::create([
            'code' => 'RPT-UPD-001',
            'name' => 'Before Update',
            'content' => 'Content',
            'header' => 'Header',
            'footer' => 'Footer',
        ]);

        $oldMdt = $report->mdt;

        sleep(1);

        $report->update(['name' => 'After Update']);

        $report->refresh();
        $this->assertGreaterThan($oldMdt, $report->mdt);
    }

    public function test_design_report_content_is_stored_as_text(): void
    {
        $htmlContent = '<html><body><h1>Large Content</h1><p>'.str_repeat('Lorem ipsum ', 1000).'</p></body></html>';

        $report = DesignReport::create([
            'code' => 'RPT-HTML-001',
            'name' => 'HTML Content Test',
            'content' => $htmlContent,
            'header' => '<header>Header</header>',
            'footer' => '<footer>Footer</footer>',
        ]);

        $report->refresh();
        $this->assertEquals($htmlContent, $report->content);

        $type = Schema::getColumnType('design_report', 'content');
        $this->assertEquals('text', $type);
    }

    public function test_design_report_model_has_custom_timestamp_constants(): void
    {
        $this->assertEquals('cdt', DesignReport::CREATED_AT);
        $this->assertEquals('mdt', DesignReport::UPDATED_AT);
    }
}
