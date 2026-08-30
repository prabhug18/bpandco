<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Metric;
use App\Models\Slip;
use App\Models\ExternalApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class ExternalApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'sales']);
        Role::create(['name' => 'admin']);

        // Create active metric
        Metric::create([
            'key'          => 'sales',
            'label'        => 'Sales',
            'unit'         => 'amount',
            'value_type'   => 'absolute',
            'is_active'    => true,
            'scoring_type' => '10_20_30_days',
            'comparison_type' => 'gte',
        ]);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->postJson('/api/v1/external/bulk-sync', [
            'sync_date' => '2026-08-10',
            'data'      => []
        ]);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_bulk_sync_with_valid_token_creates_slips(): void
    {
        $tokenData = ExternalApiToken::createToken('Test POS Store', 'STORE_001');
        $plainToken = $tokenData['plain_text_token'];

        $user = User::factory()->create([
            'name'          => 'Rajesh Kumar',
            'email'         => 'rajesh@example.com',
            'employee_code' => 'EMP101',
            'mobile'        => '9876543210',
        ]);
        $user->assignRole('sales');

        $payload = [
            'sync_date'    => '2026-08-10',
            'store_code'   => 'STORE_001',
            'auto_approve' => true,
            'data'         => [
                [
                    'user_identifier' => 'EMP101',
                    'date'            => '2026-08-10',
                    'reference_id'    => 'POS-BATCH-8821',
                    'metrics'         => [
                        [
                            'metric_code' => 'TOTAL_SALES_INR',
                            'value'       => 45000.50
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $plainToken)
            ->postJson('/api/v1/external/bulk-sync', $payload);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('slips', [
            'user_id'      => $user->id,
            'status'       => 'approved',
            'reference_id' => 'POS-BATCH-8821',
            'value'        => 45000.50,
        ]);
    }

    public function test_duplicate_reference_id_prevents_duplicate_slips(): void
    {
        $tokenData = ExternalApiToken::createToken('Test POS Store', 'STORE_001');
        $plainToken = $tokenData['plain_text_token'];

        $user = User::factory()->create([
            'employee_code' => 'EMP101',
        ]);
        $user->assignRole('sales');

        $payload = [
            'sync_date'    => '2026-08-10',
            'auto_approve' => true,
            'data'         => [
                [
                    'user_identifier' => 'EMP101',
                    'date'            => '2026-08-10',
                    'reference_id'    => 'POS-BATCH-8821',
                    'metrics'         => [
                        ['metric_code' => 'TOTAL_SALES_INR', 'value' => 1000]
                    ]
                ]
            ]
        ];

        // First sync
        $this->withHeader('Authorization', 'Bearer ' . $plainToken)->postJson('/api/v1/external/bulk-sync', $payload);
        
        // Second duplicate sync
        $res = $this->withHeader('Authorization', 'Bearer ' . $plainToken)->postJson('/api/v1/external/bulk-sync', $payload);
        $res->assertStatus(200);

        // Verify only 1 slip exists
        $this->assertEquals(1, Slip::where('reference_id', 'POS-BATCH-8821')->count());
    }

    public function test_fetch_metrics_and_users(): void
    {
        $tokenData = ExternalApiToken::createToken('Test POS Store', 'STORE_001');
        $plainToken = $tokenData['plain_text_token'];

        $user = User::factory()->create(['employee_code' => 'EMP101']);
        $user->assignRole('sales');

        // Test Metrics
        $mRes = $this->withHeader('Authorization', 'Bearer ' . $plainToken)
            ->getJson('/api/v1/external/metrics');
        $mRes->assertStatus(200)
            ->assertJson(['success' => true]);

        // Test Users
        $uRes = $this->withHeader('Authorization', 'Bearer ' . $plainToken)
            ->getJson('/api/v1/external/users');
        $uRes->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}
