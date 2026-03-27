<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 191)->unique();
            $table->string('station_id', 191)->index();
            $table->decimal('amount', 15, 2);
            $table->string('status', 64)->index();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_events');
    }
};
