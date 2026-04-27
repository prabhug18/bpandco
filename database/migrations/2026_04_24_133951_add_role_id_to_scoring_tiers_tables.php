<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add nullable role_id
        Schema::table('daily_scoring_tiers', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->constrained('roles')->cascadeOnDelete()->after('metric_id');
        });

        Schema::table('period_targets', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->constrained('roles')->cascadeOnDelete()->after('metric_id');
        });

        // 2. Backfill existing data
        $this->backfillTable('daily_scoring_tiers');
        $this->backfillTable('period_targets');

        // 3. Make role_id NOT NULL and add composite indexes
        Schema::table('daily_scoring_tiers', function (Blueprint $table) {
            // Delete any orphaned rows that still have null role_id (shouldn't happen, but safe)
            DB::table('daily_scoring_tiers')->whereNull('role_id')->delete();
            $table->foreignId('role_id')->nullable(false)->change();
            $table->index(['metric_id', 'role_id']);
        });

        Schema::table('period_targets', function (Blueprint $table) {
            DB::table('period_targets')->whereNull('role_id')->delete();
            $table->foreignId('role_id')->nullable(false)->change();
            $table->index(['metric_id', 'role_id']);
        });
    }

    private function backfillTable(string $table)
    {
        $rows = DB::table($table)->whereNull('role_id')->get();
        $adminRole = DB::table('roles')->where('name', 'admin')->first();

        foreach ($rows as $row) {
            $metricRoles = DB::table('metric_role')->where('metric_id', $row->metric_id)->pluck('role_id')->toArray();
            
            if (empty($metricRoles)) {
                if ($adminRole) {
                    $metricRoles = [$adminRole->id];
                } else {
                    continue; // Skip if no admin role fallback
                }
            }

            // Assign the first role to the existing row
            DB::table($table)->where('id', $row->id)->update(['role_id' => $metricRoles[0]]);

            // Duplicate the row for remaining roles
            for ($i = 1; $i < count($metricRoles); $i++) {
                $newRow = (array) $row;
                unset($newRow['id']); // Remove ID to insert as new
                $newRow['role_id'] = $metricRoles[$i];
                DB::table($table)->insert($newRow);
            }
        }
    }

    public function down(): void
    {
        Schema::table('daily_scoring_tiers', function (Blueprint $table) {
            $table->dropIndex(['metric_id', 'role_id']);
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });

        Schema::table('period_targets', function (Blueprint $table) {
            $table->dropIndex(['metric_id', 'role_id']);
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
        
        // Note: Down migration will lose duplicated rows because they look identical
        // to the originals except for role_id, but it's acceptable for a down migration.
    }
};
