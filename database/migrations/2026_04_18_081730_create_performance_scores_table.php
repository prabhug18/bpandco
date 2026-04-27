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
        Schema::create('performance_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('metric_id')->constrained()->cascadeOnDelete();
            $table->string('period_type'); // '10_days', '20_days', '30_days', 'monthly'
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('cumulative_value', 15, 2)->default(0);
            $table->decimal('daily_points_sum', 8, 2)->default(0);
            $table->decimal('period_points_earned', 8, 2)->default(0);
            $table->enum('traffic_light', ['green', 'yellow', 'red', 'grey']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_scores');
    }
};
