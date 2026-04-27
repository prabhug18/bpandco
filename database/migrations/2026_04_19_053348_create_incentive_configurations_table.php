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
        Schema::create('incentive_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->nullable()->constrained('roles')->cascadeOnDelete(); // null = applies to all roles
            $table->integer('consecutive_green_months'); // e.g., 2 months green
            $table->decimal('min_score', 8, 2);          // e.g., 70
            $table->decimal('max_score', 8, 2)->nullable(); // e.g., 85 (null = no upper cap)
            $table->decimal('incentive_amount', 10, 2);  // e.g., 4000 or 6000
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incentive_configurations');
    }
};
