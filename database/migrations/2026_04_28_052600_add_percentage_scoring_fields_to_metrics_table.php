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
        Schema::table('metrics', function (Blueprint $table) {
            $table->string('value_type')->default('absolute')->after('unit'); // absolute, percentage
            $table->foreignId('reference_metric_id')->nullable()->after('value_type')->constrained('metrics')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metrics', function (Blueprint $table) {
            $table->dropForeign(['reference_metric_id']);
            $table->dropColumn(['value_type', 'reference_metric_id']);
        });
    }
};
