<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('summary_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('period_type'); // '10_days', '20_days', '30_days', 'monthly'
            $table->string('year_month'); // e.g., '2026-04'
            // Store a JSON payload of metric points for dynamic scaling
            $table->json('metric_points'); 
            $table->decimal('total_mark', 8, 2)->default(0);
            $table->enum('overall_traffic_light', ['green', 'yellow', 'red', 'grey'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('summary_reports');
    }
};
