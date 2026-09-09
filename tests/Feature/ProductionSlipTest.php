<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Metric;
use App\Models\Slip;
use App\Models\DailyScoringTier;
use App\Models\PeriodTarget;
use App\Models\ExternalApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Carbon\Carbon;

class ProductionSlipTest extends TestCase
{
    use RefreshDatabase;

    protected User $productionUser;
    protected Role $productionRole;
    protected Metric $boxMetric;
    protected Metric $ncMetric;
    protected Metric $enamelMetric;
    protected Metric $mixingMetric;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles
        $this->productionRole = Role::firstOrCreate(['name' => 'Production']);
        Role::firstOrCreate(['name' => 'admin']);

        // 2. Metrics
        $this->boxMetric = Metric::firstOrCreate(
            ['key' => 'production'],
            [
                'label'           => 'Box Production',
                'unit'            => 'Boxes',
                'value_type'      => 'absolute',
                'scoring_type'    => '10_20_30_days',
                'comparison_type' => 'gte',
                'is_active'       => true,
            ]
        );

        $this->ncMetric = Metric::firstOrCreate(
            ['key' => 'nc_thinner_mixing'],
            [
                'label'           => 'NC Thinner Mixing',
                'unit'            => 'Ltr',
                'value_type'      => 'absolute',
                'scoring_type'    => '10_20_30_days',
                'comparison_type' => 'gte',
                'is_active'       => true,
            ]
        );

        $this->enamelMetric = Metric::firstOrCreate(
            ['key' => 'enamel_thinner_mixing'],
            [
                'label'           => 'Enamel Thinner Mixing',
                'unit'            => 'Ltr',
                'value_type'      => 'absolute',
                'scoring_type'    => '10_20_30_days',
                'comparison_type' => 'gte',
                'is_active'       => true,
            ]
        );

        $this->mixingMetric = Metric::firstOrCreate(
            ['key' => 'mixing_thinners'],
            [
                'label'           => 'Mixing Thinners',
                'unit'            => 'Ltr',
                'value_type'      => 'absolute',
                'scoring_type'    => '10_20_30_days',
                'comparison_type' => 'gte',
                'is_active'       => true,
            ]
        );

        // Link metrics to role
        $this->boxMetric->roles()->syncWithoutDetaching([$this->productionRole->id]);
        $this->ncMetric->roles()->syncWithoutDetaching([$this->productionRole->id]);
        $this->enamelMetric->roles()->syncWithoutDetaching([$this->productionRole->id]);
        $this->mixingMetric->roles()->syncWithoutDetaching([$this->productionRole->id]);

        // Clear existing tiers for clean test state
        DailyScoringTier::whereIn('metric_id', [$this->boxMetric->id, $this->mixingMetric->id])
            ->where('role_id', $this->productionRole->id)
            ->delete();

        // Daily Scoring Tiers for Box
        DailyScoringTier::create(['metric_id' => $this->boxMetric->id, 'role_id' => $this->productionRole->id, 'tier_label' => 'green', 'min_value' => 15, 'daily_points' => 0.66]);
        DailyScoringTier::create(['metric_id' => $this->boxMetric->id, 'role_id' => $this->productionRole->id, 'tier_label' => 'yellow', 'min_value' => 12, 'daily_points' => 0.46]);
        DailyScoringTier::create(['metric_id' => $this->boxMetric->id, 'role_id' => $this->productionRole->id, 'tier_label' => 'red', 'min_value' => 10, 'daily_points' => 0.33]);
        DailyScoringTier::create(['metric_id' => $this->boxMetric->id, 'role_id' => $this->productionRole->id, 'tier_label' => 'grey', 'min_value' => 7, 'daily_points' => 0.20]);

        // Daily Scoring Tiers for Mixing Thinners
        DailyScoringTier::create(['metric_id' => $this->mixingMetric->id, 'role_id' => $this->productionRole->id, 'tier_label' => 'green', 'min_value' => 300, 'daily_points' => 0.66]);
        DailyScoringTier::create(['metric_id' => $this->mixingMetric->id, 'role_id' => $this->productionRole->id, 'tier_label' => 'yellow', 'min_value' => 220, 'daily_points' => 0.46]);
        DailyScoringTier::create(['metric_id' => $this->mixingMetric->id, 'role_id' => $this->productionRole->id, 'tier_label' => 'red', 'min_value' => 180, 'daily_points' => 0.33]);
        DailyScoringTier::create(['metric_id' => $this->mixingMetric->id, 'role_id' => $this->productionRole->id, 'tier_label' => 'grey', 'min_value' => 140, 'daily_points' => 0.20]);

        // User
        $this->productionUser = User::factory()->create([
            'name'          => 'Murugan Production',
            'email'         => 'murugan@bpandco.com',
            'employee_code' => 'EMP201',
        ]);
        $this->productionUser->assignRole('Production');
    }

    public function test_production_points_preview_calculates_box_and_thinner_points(): void
    {
        $response = $this->actingAs($this->productionUser)
            ->getJson(route('slips.preview-points', [
                'production_multi'   => 1,
                'boxes'              => 15,
                'nc_thinner_ltr'     => 160,
                'enamel_thinner_ltr' => 140,
                'date'               => Carbon::today()->toDateString(),
            ]));

        $response->assertStatus(200)
            ->assertJson([
                'box_points'     => 0.66,
                'thinner_points' => 0.66,
                'total_thinners' => 300,
                'total_points'   => 1.32,
            ]);
    }

    public function test_production_multi_field_submission_creates_all_four_slips(): void
    {
        $today = Carbon::today()->toDateString();

        $response = $this->actingAs($this->productionUser)
            ->post(route('slips.store'), [
                'production_multi'   => 1,
                'date'               => $today,
                'boxes'              => 16,
                'nc_thinner_ltr'     => 150,
                'enamel_thinner_ltr' => 150,
            ]);

        $response->assertSessionHasNoErrors();

        // 1. Box slip
        $this->assertDatabaseHas('slips', [
            'user_id'             => $this->productionUser->id,
            'metric_id'           => $this->boxMetric->id,
            'date'                => $today,
            'value'               => 16,
            'daily_points_earned' => 0.66,
            'status'              => 'pending',
        ]);

        // 2. NC Thinner slip
        $this->assertDatabaseHas('slips', [
            'user_id'   => $this->productionUser->id,
            'metric_id' => $this->ncMetric->id,
            'date'      => $today,
            'value'     => 150,
            'status'    => 'pending',
        ]);

        // 3. Enamel Thinner slip
        $this->assertDatabaseHas('slips', [
            'user_id'   => $this->productionUser->id,
            'metric_id' => $this->enamelMetric->id,
            'date'      => $today,
            'value'     => 150,
            'status'    => 'pending',
        ]);

        // 4. Mixing Thinners aggregated slip
        $this->assertDatabaseHas('slips', [
            'user_id'             => $this->productionUser->id,
            'metric_id'           => $this->mixingMetric->id,
            'date'                => $today,
            'value'               => 300,
            'daily_points_earned' => 0.66,
            'status'              => 'pending',
        ]);
    }

    public function test_external_bulk_sync_handles_production_metrics_and_aggregates_thinners(): void
    {
        $tokenData = ExternalApiToken::createToken('Factory Scale', 'FACTORY_01');
        $plainToken = $tokenData['plain_text_token'];
        $today = Carbon::today()->toDateString();

        $payload = [
            'sync_date'    => $today,
            'store_code'   => 'FACTORY_01',
            'auto_approve' => true,
            'data'         => [
                [
                    'user_identifier' => 'EMP201',
                    'date'            => $today,
                    'reference_id'    => 'PROD-BATCH-001',
                    'metrics'         => [
                        ['metric_code' => 'BOX', 'value' => 15],
                        ['metric_code' => 'NC_THINNER_MIXING', 'value' => 180],
                        ['metric_code' => 'ENAMEL_THINNER_MIXING', 'value' => 120],
                    ],
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $plainToken)
            ->postJson('/api/v1/external/bulk-sync', $payload);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Box slip
        $this->assertDatabaseHas('slips', [
            'user_id'             => $this->productionUser->id,
            'metric_id'           => $this->boxMetric->id,
            'date'                => $today,
            'value'               => 15,
            'daily_points_earned' => 0.66,
            'status'              => 'approved',
        ]);

        // Mixing Thinners aggregated slip
        $this->assertDatabaseHas('slips', [
            'user_id'             => $this->productionUser->id,
            'metric_id'           => $this->mixingMetric->id,
            'date'                => $today,
            'value'               => 300,
            'daily_points_earned' => 0.66,
            'status'              => 'approved',
        ]);
    }
}
