<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // Skip SQLite
            return;
        }

        if ($driver === 'mysql') {
            DB::unprepared("
                DROP PROCEDURE IF EXISTS refresh_station_summary;

                CREATE PROCEDURE refresh_station_summary(IN p_station_id VARCHAR(50))
                BEGIN
                    INSERT INTO station_summaries (station_id, total_approved_amount, events_count, created_at, updated_at)
                    SELECT
                        p_station_id AS station_id,
                        COALESCE(SUM(CASE WHEN te.status = 'approved' THEN te.amount ELSE 0 END), 0) AS total_approved_amount,
                        COUNT(te.id) AS events_count,
                        NOW() AS created_at,
                        NOW() AS updated_at
                    FROM (SELECT 1) dummy
                    LEFT JOIN transfer_events te
                        ON te.station_id = p_station_id
                    ON DUPLICATE KEY UPDATE
                        events_count = VALUES(events_count),
                        total_approved_amount = VALUES(total_approved_amount),
                        updated_at = NOW();
                END;
            ");
        }

        if ($driver === 'pgsql') {
            // PostgreSQL function with ON CONFLICT
            DB::unprepared("
                CREATE OR REPLACE FUNCTION refresh_station_summary(p_station_id VARCHAR)
                RETURNS void AS $$
                BEGIN
                    INSERT INTO station_summaries (station_id, total_approved_amount, events_count, created_at, updated_at)
                    SELECT
                        p_station_id AS station_id,
                        COALESCE(SUM(CASE WHEN te.status = 'approved' THEN te.amount ELSE 0 END), 0) AS total_approved_amount,
                        COUNT(te.id) AS events_count,
                        NOW() AS created_at,
                        NOW() AS updated_at
                    FROM transfer_events te
                    WHERE te.station_id = p_station_id
                    GROUP BY p_station_id
                    ON CONFLICT (station_id) DO UPDATE
                    SET
                        total_approved_amount = EXCLUDED.total_approved_amount,
                        events_count = EXCLUDED.events_count,
                        updated_at = NOW();
                END;
                $$ LANGUAGE plpgsql;
            ");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'mysql') {
            DB::unprepared("DROP PROCEDURE IF EXISTS refresh_station_summary;");
        }

        if ($driver === 'pgsql') {
            DB::unprepared("DROP FUNCTION IF EXISTS refresh_station_summary(VARCHAR);");
        }
    }
};
