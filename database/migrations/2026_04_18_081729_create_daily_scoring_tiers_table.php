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
        Schema::create('daily_scoring_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metric_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_value', 15, 2);
            $table->decimal('daily_points', 8, 2);
            $table->integer('sort_order')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_scoring_tiers');
    }
};
