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
        Schema::table('attendances', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('image_path');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->boolean('is_within_geofence')->default(false)->after('status');
            $table->decimal('distance_from_center', 10, 2)->nullable()->after('is_within_geofence');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'is_within_geofence', 'distance_from_center']);
        });
    }
};
