<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::unprepared("
        DROP PROCEDURE IF EXISTS refresh_station_summary;

        CREATE PROCEDURE refresh_station_summary(IN p_station_id VARCHAR(50))
        BEGIN

            INSERT INTO station_summaries (station_id, total_approved_amount, events_count, created_at, updated_at)
            SELECT
                p_station_id as station_id,
                COALESCE(SUM(CASE WHEN te.status = 'approved' THEN te.amount ELSE 0 END), 0) as total_approved_amount,
                COUNT(te.id) as events_count,
                NOW() as created_at,
                NOW() as updated_at
            FROM (SELECT 1) dummy
            LEFT JOIN transfer_events te
                ON te.station_id COLLATE utf8mb4_unicode_ci = p_station_id COLLATE utf8mb4_unicode_ci

            ON DUPLICATE KEY UPDATE
                events_count = VALUES(events_count),
                total_approved_amount = VALUES(total_approved_amount),
                updated_at = NOW();

        END
    ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::unprepared("DROP PROCEDURE IF EXISTS refresh_station_summary;");
    }
};
