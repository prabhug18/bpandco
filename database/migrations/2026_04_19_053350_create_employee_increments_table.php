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
        Schema::create('employee_increments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('year'); // e.g., 2026 (April–March cycle)
            $table->integer('green_months_achieved');
            $table->decimal('increment_percentage', 5, 2)->default(0);
            $table->decimal('current_salary', 12, 2)->nullable();
            $table->decimal('incremented_salary', 12, 2)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_increments');
    }
};
