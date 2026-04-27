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
        Schema::create('increment_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->nullable()->constrained('roles')->cascadeOnDelete();
            $table->integer('green_months_required'); // e.g., 9, 6, 3
            $table->integer('total_months')->default(12); // out of 12
            $table->decimal('increment_percentage', 5, 2); // e.g., 15.00, 10.00, 5.00
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('increment_configurations');
    }
};
