# BP&Co External Billing Software API Integration Specification

> **Document Version**: 1.1.0  
> **Target Audience**: Client Technical Team, Software Integrators, POS/Billing Software Developers  
> **Purpose**: Technical specification for integrating third-party shop billing/POS software with BP&Co Performance Management System.

---

## 1. Overview & Workflow

In retail shop operations, employees record all transactions, daily sales figures, bill counts, and product entries in their shop's proprietary **Billing Software**.

To eliminate manual double-entry of data:
1. **Push Sync (Sales Ingestion)**: The shop billing software compiles daily sales & productivity figures per employee and calls `POST /api/v1/external/bulk-sync` to create/update performance slips in BP&Co.
2. **Pull Sync (Approved Slips Query)**: The third-party billing software calls `GET /api/v1/external/slips/approved` to query verified/approved slips for any user, metric module, and date or date range.

```
┌──────────────────────────────┐              ┌────────────────────────────────────────────────────────┐
│  Shop Billing / POS System   │              │             BP&Co Performance Platform API             │
├──────────────────────────────┤              ├────────────────────────────────────────────────────────┤
│ 1. Push Daily Sales Metrics  │───POST──────>│ Sanctum Authentication -> Payload Validation           │
│                              │              │ Employee Lookup -> Metric Mapping -> Slips Creation    │
│                              │              │ Dynamic Point Calculation                              │
│                              │              │                                                        │
│ 2. Query Approved Slips      │───GET───────>│ Fetch Approved Records (filtered by user, metric, date)│
│    (Status Verification)     │<──JSON───────│ Returns Approved Slips & Earned Daily Points           │
└──────────────────────────────┘              └────────────────────────────────────────────────────────┘
```

---

## 2. Authentication & Authorization

All API calls must include a **Sanctum API Bearer Token** in the HTTP Authorization header.

- **Header Name**: `Authorization`
- **Header Value**: `Bearer {YOUR_API_TOKEN}`
- **Accept**: `application/json`
- **Content-Type**: `application/json`

---

## 3. API Endpoints

### 3.1 Bulk Sales & Metric Data Ingestion (`POST /api/v1/external/bulk-sync`)

Pushes bulk sales metrics for multiple employees in a single request.

#### Endpoint
`POST https://your-bpandco-domain.com/api/v1/external/bulk-sync`

#### Headers
```http
Authorization: Bearer 1|abcdef1234567890qwertyuiop
Content-Type: application/json
Accept: application/json
```

#### Request Payload Schema (JSON)
| Field | Type | Required | Description |
|---|---|---|---|
| `sync_date` | string (YYYY-MM-DD) | Yes | Default date of the batch sync |
| `store_code` | string | No | Optional store or branch identifier |
| `auto_approve` | boolean | No | `true` (default) to approve slips immediately; `false` to send to supervisor queue |
| `data` | array | Yes | Array of employee records |
| `data[].user_identifier` | string | Yes | Employee Code (`EMP101`), Mobile Number (`9876543210`), or Email |
| `data[].date` | string (YYYY-MM-DD) | Yes | Transaction date for this entry |
| `data[].reference_id` | string | No | Third-party POS transaction/batch ID for tracing |
| `data[].metrics` | array | Yes | Array of performance metric values |
| `data[].metrics[].metric_code` | string | Yes | Standardized BP&Co metric key (e.g. `TOTAL_SALES_INR`) |
| `data[].metrics[].value` | numeric | Yes | Numeric achievement value (e.g. `45000.50`) |

#### Request Payload Example
```json
{
  "sync_date": "2026-08-10",
  "store_code": "STORE_001",
  "auto_approve": true,
  "data": [
    {
      "user_identifier": "EMP101",
      "date": "2026-08-10",
      "reference_id": "POS-INV-88210",
      "metrics": [
        {
          "metric_code": "TOTAL_SALES_INR",
          "value": 45000.50
        },
        {
          "metric_code": "BILLS_COUNT",
          "value": 38
        }
      ]
    }
  ]
}
```

#### Response Payload Example (HTTP 200 Success)
```json
{
  "success": true,
  "message": "Bulk data sync processed successfully.",
  "summary": {
    "total_records_received": 1,
    "slips_created_or_updated": 2,
    "failed_entries": 0
  },
  "results": [
    {
      "user_identifier": "EMP101",
      "user_name": "Rajesh Kumar",
      "date": "2026-08-10",
      "status": "success",
      "metrics_processed": [
        {
          "metric_code": "TOTAL_SALES_INR",
          "value": 45000.50,
          "daily_points_earned": 150.00,
          "slip_id": 1042
        },
        {
          "metric_code": "BILLS_COUNT",
          "value": 38,
          "daily_points_earned": 50.00,
          "slip_id": 1043
        }
      ]
    }
  ],
  "errors": []
}
```

---

### 3.2 Fetch Approved Slips (`GET /api/v1/external/slips/approved`)

Allows the third-party billing software to query approved performance slips for a specific employee, metric module, and date or date range.

#### Endpoint
`GET https://your-bpandco-domain.com/api/v1/external/slips/approved`

#### Query Parameters
| Parameter | Type | Required | Description | Example |
|---|---|---|---|---|
| `date` | string | Optional | Filter by single date (YYYY-MM-DD) | `2026-08-10` |
| `start_date` | string | Optional | Filter by range start date (YYYY-MM-DD) | `2026-08-01` |
| `end_date` | string | Optional | Filter by range end date (YYYY-MM-DD) | `2026-08-10` |
| `user_identifier` | string | Optional | Filter by Employee Code, Mobile, or Email | `EMP101` |
| `metric_code` | string | Optional | Filter by specific Metric / Module Code | `TOTAL_SALES_INR` |

#### Request Example
`GET /api/v1/external/slips/approved?user_identifier=EMP101&metric_code=TOTAL_SALES_INR&date=2026-08-10`

#### Response Payload Example (HTTP 200 Success)
```json
{
  "success": true,
  "filters": {
    "date": "2026-08-10",
    "user_identifier": "EMP101",
    "metric_code": "TOTAL_SALES_INR"
  },
  "total_records": 1,
  "data": [
    {
      "slip_id": 1042,
      "user": {
        "id": 10,
        "employee_code": "EMP101",
        "name": "Rajesh Kumar",
        "mobile": "9876543210"
      },
      "metric": {
        "metric_code": "TOTAL_SALES_INR",
        "name": "Total Daily Sales (₹)",
        "unit": "INR"
      },
      "date": "2026-08-10",
      "value": 45000.50,
      "daily_points_earned": 150.00,
      "status": "approved",
      "approved_at": "2026-08-10 11:30:00"
    }
  ]
}
```

---

### 3.3 Fetch Active Metrics List (`GET /api/v1/external/metrics`)

Allows the billing software to query active metric codes in BP&Co.

#### Endpoint
`GET https://your-bpandco-domain.com/api/v1/external/metrics`

#### Response Example (HTTP 200)
```json
{
  "success": true,
  "metrics": [
    {
      "id": 1,
      "metric_code": "TOTAL_SALES_INR",
      "name": "Total Daily Sales (₹)",
      "value_type": "quantity",
      "unit": "INR"
    },
    {
      "id": 2,
      "metric_code": "BILLS_COUNT",
      "name": "Total Bills Generated",
      "value_type": "quantity",
      "unit": "Bills"
    }
  ]
}
```

---

### 3.4 Fetch Active Employees List (`GET /api/v1/external/users`)

Allows the billing software to resolve employee codes and mobile numbers mapped in BP&Co.

#### Endpoint
`GET https://your-bpandco-domain.com/api/v1/external/users`

#### Response Example (HTTP 200)
```json
{
  "success": true,
  "users": [
    {
      "id": 10,
      "employee_code": "EMP101",
      "name": "Rajesh Kumar",
      "mobile": "9876543210",
      "role": "Sales Associate"
    }
  ]
}
```

---

## 4. Error Handling & Validation Rules

1. **User Identifier Resolution**: If `user_identifier` cannot be matched to any user, that specific record is logged under `errors` and returned in the bulk sync response while continuing remaining entries.
2. **Metric Code Resolution**: If `metric_code` is invalid or inactive, an error is returned for that metric item without failing the entire request batch.
3. **Only Approved Slips Returned**: The `GET /api/v1/external/slips/approved` endpoint strictly returns slips with `status = 'approved'`. Pending or rejected slips are excluded.
