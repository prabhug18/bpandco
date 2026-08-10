# Project Documentation: BP&Co Performance Management & SaaS Roadmap

## 1. Executive Summary
The BP&Co Performance Management System is a robust, data-driven platform designed to track, score, and optimize employee productivity. By digitizing daily performance "slips" and applying dynamic scoring logic, the system provides real-time insights into workforce efficiency, incentive eligibility, and long-term performance trends.

---

## 2. Core Functional Modules

### A. Dynamic Scoring Engine
The heart of the system is a highly flexible scoring configuration that allows administrators to set performance tiers (Green, Yellow, Red, Grey) for any metric.
- **Metric Types**:
  - **Quantity Metrics**: Direct numeric output/production values.
  - **Percentage Metrics**: Calculated dynamically as a ratio against a configurable reference metric.
  - **Inverse Metrics**: Evaluates lower-is-better metrics (e.g., error rates, processing time) using `lte` (less-than-or-equal) target matching.
- **Daily Targets**: Instant point allocation based on daily output.
- **Cumulative Period Targets**: Points awarded for performance over 10-day, 20-day, and 30-day/monthly progressive target windows.
- **Role-Specific Tiers**: Custom scoring targets and tiers configured per employee role.

### B. Employee Performance Entry (Daily Slips)
A streamlined, mobile-responsive interface for employees to log their daily achievements.
- **Real-Time Points Preview**: Dynamic frontend preview calculating estimated daily points as employees enter values.
- **Submission Deadlines**: Built-in logic ensuring data entry occurs within required operational windows.

### C. Attendance & Geofencing Tracking
- **Location-Aware Check-In**: Captures exact GPS coordinates during daily attendance logging.
- **Geofence Validation**: Verifies employee location against authorized site boundaries.

### D. Supervisor & Admin Approval Queue
- **Verification Workflow**: Submitted slips and attendance records enter a pending queue for supervisor review.
- **Bulk Processing**: Single and batch actions for bulk approving or rejecting submissions.
- **Audit Reasons**: Mandatory rejection reason recording for operational transparency.

### E. Automated Reporting, Incentives & Increments
- **Individual & Team Performance Reports**: Multi-metric breakdowns across custom date ranges.
- **Status & Health Tracking**: Color-coded visual health indicators (Green/Yellow/Red).
- **Incentive & Increment Tracking**: Tracks eligible bonuses and wage increments with mark-as-paid status management for administrators.

### F. Administrative Data Override & System Controls
- **Data Editor**: Privileged override tool allowing admins to fix historical entries with audit tracking.
- **Holiday Calendar**: Configurable holiday management affecting operational working days and scoring logic.

### G. Secure WhatsApp Authentication & Notifications
- **OTP Login**: Modern, passwordless authentication using mobile OTP verification via WhatsApp.
- **WeConnext Integration**: Reliable API delivery of OTP codes, employee daily entry reminders, and supervisor approval alerts.

---

## 3. Technical Architecture & Access Control
The system is built on a modern, enterprise-grade stack ensuring high performance, security, and granular authorization.

- **Backend**: Laravel 10/11 (PHP 8.x)
- **Frontend**: Vue.js 3 with Inertia.js (Single Page Application experience)
- **Styling**: Vanilla CSS with modern Design Tokens
- **Database**: MySQL
- **Integrations**: WeConnext WhatsApp API, Spatie Permissions (RBAC)
- **Granular RBAC Permissions**:
  - `manage roles`, `configure metrics`, `configure scoring tiers`, `approve slips`, `manage system settings`, `manage data`, `configure holidays`, `configure incentives`.

---

## 4. SaaS Transformation Roadmap
To scale this solution from a single-company tool to a multi-tenant platform, the following architectural upgrades are planned:

### Phase 1: Multi-Tenant Isolation
- **Client-Centric Data**: Implementation of a `client_id` system across all tables with global Eloquent query scoping.
- **Global Scoping**: Automated data filtering to ensure each company can only access their own employees, metrics, and reports.

### Phase 2: Role & Permission Scoping
- **Tenant Isolation**: Utilizing Spatie's "Teams" feature to allow each client to manage their own custom roles and permissions without interference.

### Phase 3: Subscription & Feature Management
- **Tiered Access**: Implementation of a subscription module to control feature availability (e.g., "Basic" vs "Pro" plans).
- **Billing Integration**: Automated recurring billing via Laravel Cashier.

---

## 5. Conclusion
The current system successfully bridges the gap between raw productivity data and actionable performance metrics. With a clear roadmap toward a SaaS model, the platform is positioned to grow into a scalable solution capable of serving a wide range of industrial and retail clients.
