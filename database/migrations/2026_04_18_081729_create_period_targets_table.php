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
        Schema::create('period_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metric_id')->constrained()->cascadeOnDelete();
            $table->string('period_type'); // '10_days', '20_days', '30_days', 'monthly'
            $table->enum('tier_label', ['green', 'yellow', 'red', 'grey']);
            $table->decimal('min_value', 15, 2);
            $table->decimal('points_awarded', 8, 2);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('period_targets');
    }
};
