<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Metric;
use App\Models\Slip;
use App\Models\DailyScoringTier;
use App\Services\ScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExternalApiController extends Controller
{
    /**
     * Map metric_code alias strings to BP&Co database metric keys.
     */
    private array $metricCodeMap = [
        'TOTAL_SALES_INR' => 'sales',
        'SALES'           => 'sales',
        'BILLS_COUNT'     => 'bills',
        'BILLS'           => 'bills',
        'COLLECTION'      => 'collection',
        'COLLECTION_INR'  => 'collection',
        'COLOUR_MATCHING' => 'colour_matching',
        'CUSTOMER_HANDLING' => 'customer_handling',
        'ITEMS_SOLD'      => 'old_stock_pu',
        'PRODUCTION'      => 'production',
        'DIRECT_COLLECTION' => 'direct_collection',
        'BP_LAC_PRODUCTS' => 'bplac_products',
        'STOCK_CHECKING'  => 'stock_checking',
        'OLD_STOCK_ENAMEL' => 'old_stock_enamel',
        'OLD_STOCK_PU'    => 'old_stock_pu',
        'PANEL_COLLECTION' => 'panel_collection',
    ];

    /**
     * API 1: Bulk Data Ingestion (POST /api/v1/external/bulk-sync)
     */
    public function bulkSync(Request $request)
    {
        $request->validate([
            'sync_date'    => 'required|date_format:Y-m-d',
            'store_code'   => 'nullable|string',
            'auto_approve' => 'nullable|boolean',
            'data'         => 'required|array|min:1',
            'data.*.user_identifier' => 'required|string',
            'data.*.date'            => 'required|date_format:Y-m-d',
            'data.*.reference_id'    => 'nullable|string',
            'data.*.metrics'         => 'required|array|min:1',
            'data.*.metrics.*.metric_code' => 'required|string',
            'data.*.metrics.*.value'       => 'required|numeric',
        ]);

        $autoApprove = $request->boolean('auto_approve', true);
        $syncDate    = $request->sync_date;
        $storeCode   = $request->store_code;
        $dataEntries = $request->data;

        $results = [];
        $errors  = [];
        $slipsCreatedOrUpdated = 0;
        $successfulRecords = 0;

        foreach ($dataEntries as $entry) {
            $userIdentifier = trim($entry['user_identifier']);
            $entryDate      = $entry['date'];
            $referenceId    = $entry['reference_id'] ?? null;
            $metricsList    = $entry['metrics'];

            // Duplicate Protection Check using reference_id
            if ($referenceId) {
                $existingRefSlips = Slip::where('reference_id', $referenceId)->get();
                if ($existingRefSlips->isNotEmpty()) {
                    $results[] = [
                        'user_identifier' => $userIdentifier,
                        'date'            => $entryDate,
                        'reference_id'    => $referenceId,
                        'status'          => 'already_processed',
                        'message'         => 'Record with this reference_id has already been processed.',
                        'metrics_processed' => $existingRefSlips->map(fn($s) => [
                            'metric_code'         => $s->metric?->key ?? 'unknown',
                            'value'               => (float) $s->value,
                            'daily_points_earned' => (float) $s->daily_points_earned,
                            'slip_id'             => $s->id,
                        ])->toArray(),
                    ];
                    $successfulRecords++;
                    continue;
                }
            }

            // Resolve Employee
            $numericId = preg_match('/^(?:EMP|emp)?(\d+)$/', $userIdentifier, $m) ? (int)$m[1] : null;

            $user = User::where('employee_code', $userIdentifier)
                ->orWhere('mobile', $userIdentifier)
                ->orWhere('email', $userIdentifier)
                ->when($numericId, fn($q) => $q->orWhere('id', $numericId))
                ->first();

            if (!$user) {
                $errors[] = [
                    'user_identifier' => $userIdentifier,
                    'error_code'      => 'USER_NOT_FOUND',
                    'message'         => "Employee identifier '{$userIdentifier}' could not be resolved.",
                ];
                continue;
            }

            $userRoleId = $user->roles()->first()?->id;
            $processedMetrics = [];

            foreach ($metricsList as $mItem) {
                $codeRaw = trim($mItem['metric_code']);
                $valNum  = floatval($mItem['value']);

                // Map code string to database Metric
                $mappedKey = $this->metricCodeMap[strtoupper($codeRaw)] ?? strtolower($codeRaw);
                $metricModel = Metric::where('key', $mappedKey)->orWhere('key', strtolower($codeRaw))->first();

                if (!$metricModel) {
                    $errors[] = [
                        'user_identifier' => $userIdentifier,
                        'metric_code'     => $codeRaw,
                        'error_code'      => 'INVALID_METRIC',
                        'message'         => "Metric code '{$codeRaw}' could not be matched.",
                    ];
                    continue;
                }

                // Calculate daily points earned via DailyScoringTier
                $dailyPoints = 0;
                $tiers = DailyScoringTier::where('metric_id', $metricModel->id)
                    ->when($userRoleId, fn($q) => $q->where('role_id', $userRoleId))
                    ->orderBy('min_value', 'desc')
                    ->get();

                foreach ($tiers as $tier) {
                    if ($valNum >= (float) $tier->min_value) {
                        $dailyPoints = (float) $tier->daily_points;
                        break;
                    }
                }

                $slipStatus = $autoApprove ? 'approved' : 'pending';

                $slip = Slip::updateOrCreate(
                    [
                        'user_id'   => $user->id,
                        'metric_id' => $metricModel->id,
                        'date'      => $entryDate,
                    ],
                    [
                        'value'               => $valNum,
                        'daily_points_earned' => $dailyPoints,
                        'status'              => $slipStatus,
                        'reference_id'        => $referenceId,
                        'approved_by'         => $autoApprove ? auth()->id() : null,
                    ]
                );

                $slipsCreatedOrUpdated++;

                $processedMetrics[] = [
                    'metric_code'         => strtoupper($metricModel->key),
                    'value'               => $valNum,
                    'daily_points_earned' => $dailyPoints,
                    'slip_id'             => $slip->id,
                ];
            }

            if (!empty($processedMetrics)) {
                // Recalculate monthly scores for the employee
                ScoringService::updateMonthScores($user, Carbon::parse($entryDate)->format('Y-m'));

                $successfulRecords++;
                $results[] = [
                    'user_identifier'   => $userIdentifier,
                    'user_name'         => $user->name,
                    'date'              => $entryDate,
                    'reference_id'      => $referenceId,
                    'status'            => 'success',
                    'metrics_processed' => $processedMetrics,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulk data sync processed successfully.',
            'summary' => [
                'total_records_received'   => count($dataEntries),
                'successful_records'       => $successfulRecords,
                'slips_created_or_updated' => $slipsCreatedOrUpdated,
                'failed_entries'           => count($errors),
            ],
            'results' => $results,
            'errors'  => $errors,
        ]);
    }

    /**
     * API 2: Sync Status Check (GET /api/v1/external/sync-status/{reference_id})
     */
    public function syncStatus(string $reference_id)
    {
        $slips = Slip::where('reference_id', $reference_id)->get();

        if ($slips->isEmpty()) {
            return response()->json([
                'success'      => false,
                'reference_id' => $reference_id,
                'status'       => 'not_found',
                'message'      => 'No records found for the given reference_id.',
            ], 404);
        }

        $allApproved = $slips->every(fn($s) => $s->status === 'approved');
        $allRejected = $slips->every(fn($s) => $s->status === 'rejected');

        $statusStr = $allApproved ? 'processed' : ($allRejected ? 'failed' : 'pending');

        return response()->json([
            'success'            => true,
            'reference_id'       => $reference_id,
            'status'             => $statusStr,
            'total_records'      => $slips->count(),
            'successful_records' => $slips->where('status', 'approved')->count(),
            'failed_records'     => $slips->where('status', 'rejected')->count(),
            'processed_at'       => $slips->max('updated_at')?->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * API 3: Fetch Approved Slips (GET /api/v1/external/slips/approved)
     */
    public function approvedSlips(Request $request)
    {
        $query = Slip::with(['user.roles', 'metric'])
            ->where('status', 'approved');

        // Date filter rules (single date OR start_date + end_date range)
        if ($request->date) {
            $query->where('date', $request->date);
        } elseif ($request->start_date && $request->end_date) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        // User filter rule (employee_code, mobile, email, id, EMP{id})
        if ($request->user_identifier) {
            $uid = trim($request->user_identifier);
            $numericId = preg_match('/^(?:EMP|emp)?(\d+)$/', $uid, $m) ? (int)$m[1] : null;

            $query->whereHas('user', function ($q) use ($uid, $numericId) {
                $q->where('employee_code', $uid)
                  ->orWhere('mobile', $uid)
                  ->orWhere('email', $uid)
                  ->when($numericId, fn($sq) => $sq->orWhere('id', $numericId));
            });
        }

        // Metric filter rule
        if ($request->metric_code) {
            $codeRaw = trim($request->metric_code);
            $mappedKey = $this->metricCodeMap[strtoupper($codeRaw)] ?? strtolower($codeRaw);
            $query->whereHas('metric', function ($q) use ($mappedKey, $codeRaw) {
                $q->where('key', $mappedKey)->orWhere('key', strtolower($codeRaw));
            });
        }

        $perPage = min((int) ($request->per_page ?? 100), 250);
        $paginated = $query->orderBy('date', 'desc')->paginate($perPage);

        $transformedData = collect($paginated->items())->map(function ($s) {
            return [
                'slip_id' => $s->id,
                'user' => [
                    'id'            => $s->user?->id,
                    'employee_code' => $s->user?->employee_code ?? ('EMP' . $s->user?->id),
                    'name'          => $s->user?->name,
                    'mobile'        => $s->user?->mobile,
                    'role'          => $s->user?->roles->first()?->name ?? 'Staff',
                ],
                'metric' => [
                    'metric_code' => strtoupper($s->metric?->key ?? ''),
                    'name'        => $s->metric?->label ?? '',
                    'unit'        => $s->metric?->unit ?? '',
                ],
                'date'                => $s->date,
                'value'               => (float) $s->value,
                'daily_points_earned' => (float) $s->daily_points_earned,
                'status'              => $s->status,
                'reference_id'        => $s->reference_id,
                'approved_at'         => $s->updated_at?->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'success' => true,
            'filters' => [
                'date'            => $request->date,
                'start_date'      => $request->start_date,
                'end_date'        => $request->end_date,
                'user_identifier' => $request->user_identifier,
                'metric_code'     => $request->metric_code,
            ],
            'total_records' => $paginated->total(),
            'data'          => $transformedData,
            'pagination'    => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'last_page'    => $paginated->lastPage(),
            ]
        ]);
    }

    /**
     * API 4: Fetch Active Metrics (GET /api/v1/external/metrics)
     */
    public function metrics()
    {
        $metrics = Metric::where('is_active', true)->get()->map(function ($m) {
            return [
                'id'          => $m->id,
                'metric_code' => strtoupper($m->key),
                'name'        => $m->label,
                'value_type'  => $m->value_type,
                'unit'        => $m->unit,
            ];
        });

        return response()->json([
            'success' => true,
            'metrics' => $metrics,
        ]);
    }

    /**
     * API 5: Fetch Active Employees (GET /api/v1/external/users)
     */
    public function users()
    {
        $users = User::with('roles')
            ->whereHas('roles', fn($q) => $q->whereNotIn('name', ['admin', 'supervisor', 'Admin', 'Supervisor']))
            ->get()
            ->map(function ($u) {
                return [
                    'id'            => $u->id,
                    'employee_code' => $u->employee_code ?? ('EMP' . $u->id),
                    'name'          => $u->name,
                    'mobile'        => $u->mobile,
                    'email'         => $u->email,
                    'role'          => $u->roles->first()?->name ?? 'Staff',
                ];
            });

        return response()->json([
            'success' => true,
            'users'   => $users,
        ]);
    }
}
