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
        Schema::table('app_settings', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('id')->constrained('roles')->nullOnDelete();
        });

        Schema::table('metrics', function (Blueprint $table) {
            $table->string('comparison_type')->default('gte')->after('scoring_type'); // gte (higher is better), lte (lower is better)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn(['role_id']);
        });

        Schema::table('metrics', function (Blueprint $table) {
            $table->dropColumn('comparison_type');
        });
    }
};
