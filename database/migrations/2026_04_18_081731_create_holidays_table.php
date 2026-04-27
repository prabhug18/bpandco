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
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('reason');
            $table->enum('type', ['global_holiday', 'sick_leave', 'weekly_off']);
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete(); // null if global
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // User ID + Date must be unique to prevent multi-holiday conflicts
            // We use a composite unique key, but note mysql handles unique with null differently, 
            // so we might need a workaround for global holidays if we want strict uniqueness.
            // For now, the application logic will handle checking for global holidays.
            $table->unique(['user_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
