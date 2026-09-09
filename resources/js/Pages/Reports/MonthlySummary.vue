<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    employees: { type: Array, default: () => [] },
    months: { type: Array, default: () => [] },
    financial_year: { type: String, default: '2026-2027' },
    completed_only: { type: Boolean, default: true },
    available_fys: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    selected_role: [Number, String, null],
    search: { type: String, default: '' },
});

const selectedFy = ref(props.financial_year);
const completedOnly = ref(props.completed_only);
const selectedRoleId = ref(props.selected_role || '');
const searchQuery = ref(props.search || '');

const applyFilter = () => {
    router.get(route('reports.monthly-summary'), {
        financial_year: selectedFy.value,
        completed_only: completedOnly.value ? 1 : 0,
        role_id: selectedRoleId.value || undefined,
        search: searchQuery.value || undefined,
    }, { preserveState: true, replace: true });
};

const resetFilter = () => {
    selectedFy.value = props.available_fys[0] || '2026-2027';
    completedOnly.value = true;
    selectedRoleId.value = '';
    searchQuery.value = '';
    applyFilter();
};

const exportExcel = () => {
    const params = new URLSearchParams({
        financial_year: selectedFy.value,
        completed_only: completedOnly.value ? 1 : 0,
        role_id: selectedRoleId.value || '',
        search: searchQuery.value || '',
    });
    window.location.href = route('reports.monthly-summary.export') + '?' + params.toString();
};

const printReport = () => {
    window.print();
};
</script>

<template>
    <Head title="Monthly Points Summary Report" />

    <AuthenticatedLayout>
        <div class="monthly-summary-container">
            <!-- Header Banner (hidden in print) -->
            <div class="header-banner mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h4 class="mb-1 banner-title">
                        <i class="bi bi-calendar3-range me-2 text-primary"></i>
                        Monthly Points Summary Report
                    </h4>
                    <p class="text-muted small mb-0">
                        Financial Year: <strong class="text-dark">{{ financial_year }}</strong> 
                        &bull; Active Columns: 
                        <strong class="text-primary">{{ months.map(m => m.name + ' ' + m.year).join(', ') }}</strong>
                        <span v-if="completed_only" class="badge bg-info text-dark ms-2">Completed Months Only</span>
                    </p>
                </div>

                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm d-flex align-items-center gap-1" @click="printReport">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <button class="btn btn-success btn-sm rounded-pill px-3 shadow-sm d-flex align-items-center gap-1 text-white" @click="exportExcel">
                        <i class="bi bi-file-earmark-excel"></i> Export Excel
                    </button>
                </div>
            </div>

            <!-- Filter Card (hidden in print) -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 filter-card">
                <div class="card-body p-3 p-md-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-6 col-md-3">
                            <label class="form-label small text-muted fw-semibold mb-1">Financial Year</label>
                            <select class="form-select form-select-sm rounded-3 shadow-sm" v-model="selectedFy" @change="applyFilter">
                                <option v-for="fy in available_fys" :key="fy" :value="fy">
                                    FY {{ fy }}
                                </option>
                            </select>
                        </div>

                        <div class="col-6 col-md-3">
                            <label class="form-label small text-muted fw-semibold mb-1">Filter by Role</label>
                            <select class="form-select form-select-sm rounded-3 shadow-sm" v-model="selectedRoleId" @change="applyFilter">
                                <option value="">All Roles</option>
                                <option v-for="role in roles" :key="role.id" :value="role.id">
                                    {{ role.name }}
                                </option>
                            </select>
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted fw-semibold mb-1">Search Employee</label>
                            <div class="input-group input-group-sm">
                                <input 
                                    type="text" 
                                    class="form-control rounded-start-3 shadow-sm" 
                                    placeholder="Search name..." 
                                    v-model="searchQuery" 
                                    @keyup.enter="applyFilter"
                                />
                                <button class="btn btn-primary rounded-end-3" @click="applyFilter">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-12 col-md-3 d-flex align-items-center justify-content-between pt-2">
                            <div class="form-check form-switch mb-0">
                                <input 
                                    class="form-check-input" 
                                    type="checkbox" 
                                    id="completedOnlySwitch" 
                                    v-model="completedOnly" 
                                    @change="applyFilter"
                                />
                                <label class="form-check-label small fw-semibold text-muted" for="completedOnlySwitch">
                                    Completed Months Only
                                </label>
                            </div>

                            <button class="btn btn-link btn-sm text-decoration-none text-muted p-0" @click="resetFilter" title="Reset all filters">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Threshold Legend -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 px-1 legend-bar">
                <div class="d-flex align-items-center gap-3 flex-wrap small">
                    <span class="text-muted fw-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.75rem;">Score Tier Legend:</span>
                    <span class="d-inline-flex align-items-center gap-1">
                        <span class="legend-swatch swatch-green"></span>
                        <span class="fw-semibold text-success">Green &ge; 70 pts</span>
                    </span>
                    <span class="d-inline-flex align-items-center gap-1">
                        <span class="legend-swatch swatch-yellow"></span>
                        <span class="fw-semibold text-warning-dark">Yellow 50 &ndash; 69 pts</span>
                    </span>
                    <span class="d-inline-flex align-items-center gap-1">
                        <span class="legend-swatch swatch-red"></span>
                        <span class="fw-semibold text-danger">Red &lt; 50 pts</span>
                    </span>
                    <span class="d-inline-flex align-items-center gap-1">
                        <span class="legend-swatch swatch-white"></span>
                        <span class="text-muted">Blank / No Data</span>
                    </span>
                </div>
                <div class="text-muted small">
                    Total Employees: <strong class="text-dark">{{ employees.length }}</strong>
                </div>
            </div>

            <!-- Excel-style Table Container -->
            <div class="table-responsive excel-table-card shadow-sm rounded-3 mb-5">
                <table class="table table-bordered excel-table mb-0 align-middle">
                    <thead>
                        <tr class="header-row">
                            <th class="col-sno text-center" rowspan="2">S No</th>
                            <th class="col-name text-center" rowspan="2">Name</th>
                            <th class="col-total text-center" rowspan="2">Total</th>
                            <th 
                                v-for="m in months" 
                                :key="m.key" 
                                class="col-month text-center text-nowrap"
                            >
                                <div class="month-name">{{ m.name }}</div>
                                <div class="month-year">{{ m.year }}</div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="emp in employees" :key="emp.id" class="data-row">
                            <!-- S.No (Rank) -->
                            <td class="text-center font-monospace fw-bold cell-sno">
                                {{ emp.s_no }}
                            </td>

                            <!-- Employee Name (Colored matching Total tier, just like client sheet) -->
                            <td 
                                class="cell-name fw-semibold text-truncate"
                                :class="`tier-bg-${emp.total_color}`"
                                :title="emp.name + ' (' + emp.role + ')'"
                            >
                                {{ emp.name }}
                            </td>

                            <!-- Total (Average Score, Colored matching Total tier) -->
                            <td 
                                class="text-center fw-bold cell-total font-monospace"
                                :class="`tier-bg-${emp.total_color}`"
                                :title="`Average: ${emp.total} pts | Sum: ${emp.sum} pts`"
                            >
                                {{ emp.total }}
                            </td>

                            <!-- Month-by-month points -->
                            <td 
                                v-for="m in months" 
                                :key="m.key" 
                                class="text-center font-monospace cell-points"
                                :class="emp.months[m.key]?.points !== null ? `tier-bg-${emp.months[m.key].color}` : 'cell-blank'"
                            >
                                <span v-if="emp.months[m.key]?.points !== null" class="points-val">
                                    {{ emp.months[m.key].points }}
                                </span>
                            </td>
                        </tr>

                        <!-- Empty state -->
                        <tr v-if="employees.length === 0">
                            <td :colspan="months.length + 3" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>
                                <span class="fw-semibold">No employee score records found for the selected filters.</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.monthly-summary-container {
    padding: 0.5rem 0.75rem;
}

.header-banner {
    background: #ffffff;
    border-radius: 14px;
    padding: 1.25rem 1.75rem;
    box-shadow: 0 4px 20px rgba(0, 50, 135, 0.05);
    border: 1px solid rgba(0, 50, 135, 0.08);
}

.banner-title {
    font-family: 'Outfit', sans-serif;
    font-weight: 700;
    color: #003287;
    letter-spacing: -0.3px;
}

.filter-card {
    background: #ffffff;
    border: 1px solid #e9ecef;
}

/* Legend Swatches */
.legend-swatch {
    width: 14px;
    height: 14px;
    display: inline-block;
    border-radius: 3px;
    border: 1px solid rgba(0,0,0,0.15);
}
.swatch-green { background-color: #2e7d32; }
.swatch-yellow { background-color: #f59e0b; }
.swatch-red { background-color: #dc3545; }
.swatch-white { background-color: #ffffff; }

.text-warning-dark {
    color: #b45309;
}

/* Excel Style Table */
.excel-table-card {
    background: #ffffff;
    border: 1px solid #c8d1dc;
    max-height: 80vh;
    overflow: auto;
}

.excel-table {
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.92rem;
}

/* Borders */
.excel-table th,
.excel-table td {
    border-right: 1px solid #000000;
    border-bottom: 1px solid #000000;
    padding: 6px 10px;
    height: 38px;
}

.excel-table thead th {
    position: sticky;
    top: 0;
    z-index: 10;
    background-color: #ffffff;
    color: #000000;
    font-weight: 700;
    border-top: 1px solid #000000;
    border-bottom: 2px solid #000000;
}

.col-sno {
    width: 65px;
    min-width: 60px;
}

.col-name {
    min-width: 220px;
    text-align: left !important;
}

.col-total {
    width: 80px;
    min-width: 75px;
}

.col-month {
    min-width: 80px;
    width: 85px;
}

.month-name {
    font-size: 0.85rem;
    font-weight: 700;
    line-height: 1.1;
}

.month-year {
    font-size: 0.75rem;
    font-weight: 600;
    opacity: 0.85;
}

/* Cell Specifics */
.cell-sno {
    background-color: #ffffff;
    color: #000000;
}

.cell-name {
    padding-left: 12px;
}

.cell-points {
    font-weight: 600;
    font-size: 0.95rem;
}

.cell-blank {
    background-color: #ffffff;
}

/* Client Exact Traffic-Light Colors */
.tier-bg-green {
    background-color: #2e7d32 !important; /* Rich Green */
    color: #ffffff !important;
}

.tier-bg-yellow {
    background-color: #f59e0b !important; /* Vibrant Amber/Yellow */
    color: #111827 !important;
}

.tier-bg-red {
    background-color: #dc3545 !important; /* Distinct Red */
    color: #ffffff !important;
}

.tier-bg-white {
    background-color: #ffffff !important;
    color: #111827 !important;
}

/* Print Rules */
@media print {
    body {
        background: #ffffff !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .header-banner,
    .filter-card,
    .legend-bar,
    #sidebarMenu,
    #mainHeader,
    nav {
        display: none !important;
    }

    .monthly-summary-container {
        padding: 0 !important;
    }

    .excel-table-card {
        max-height: none !important;
        box-shadow: none !important;
        border: none !important;
        overflow: visible !important;
    }

    .excel-table {
        width: 100% !important;
        font-size: 8pt !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .excel-table th,
    .excel-table td {
        padding: 3px 6px !important;
        height: 24px !important;
    }
}
</style>
