<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_summaries', function (Blueprint $table) {
            $table->string('station_id', 191)->primary();
            $table->decimal('total_approved_amount', 15, 2)->default(0);
            $table->unsignedInteger('events_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_summaries');
    }
};
