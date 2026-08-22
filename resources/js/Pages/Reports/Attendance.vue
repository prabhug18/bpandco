<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    viewMode: { type: String, default: 'month' },
    month: String, // YYYY-MM
    year: String,  // YYYY
    daysInMonth: { type: Number, default: 31 },
    reportData: Array,
    allRoles: Array,
    allEmployeesList: Array,
    selectedUserId: [String, Number],
    selectedRoleId: [String, Number],
});

const currentMode = ref(props.viewMode);
const selectedMonth = ref(props.month);
const selectedYear = ref(props.year);
const userIdFilter = ref(props.selectedUserId || '');
const roleIdFilter = ref(props.selectedRoleId || '');

const applyFilter = () => {
    router.get(route('reports.attendance'), {
        view_mode: currentMode.value,
        month: selectedMonth.value,
        year: selectedYear.value,
        user_id: userIdFilter.value,
        role_id: roleIdFilter.value,
    }, { preserveState: true });
};

const switchMode = (mode) => {
    currentMode.value = mode;
    applyFilter();
};

// Generate list of month options for dropdown
const monthOptions = computed(() => {
    const options = [];
    const [currentYr, currentMo] = props.month.split('-').map(Number);
    const baseDate = new Date(currentYr, currentMo - 1, 1);

    for (let i = -12; i <= 6; i++) {
        const d = new Date(baseDate.getFullYear(), baseDate.getMonth() + i, 1);
        const yr = d.getFullYear();
        const mo = String(d.getMonth() + 1).padStart(2, '0');
        const val = `${yr}-${mo}`;
        const label = d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        options.push({ val, label });
    }
    return options.reverse();
});

// Shift month (-1 / +1)
const shiftMonth = (offset) => {
    const [yr, mo] = selectedMonth.value.split('-').map(Number);
    const d = new Date(yr, mo - 1 + offset, 1);
    const newYr = d.getFullYear();
    const newMo = String(d.getMonth() + 1).padStart(2, '0');
    selectedMonth.value = `${newYr}-${newMo}`;
    applyFilter();
};

// Available years
const yearOptions = computed(() => {
    const currentYr = new Date().getFullYear();
    const years = [];
    for (let y = currentYr; y >= currentYr - 4; y--) {
        years.push(String(y));
    }
    return years;
});

// Status badge styling helper
const getStatusBadge = (status) => {
    switch (status) {
        case 'P':
            return { label: 'P', bg: '#198754', color: '#ffffff', title: 'Present' };
        case 'L':
            return { label: 'L', bg: '#ffc107', color: '#000000', title: 'Late' };
        case 'HD':
            return { label: 'HD', bg: '#fd7e14', color: '#ffffff', title: 'Half Day' };
        case 'A':
            return { label: 'A', bg: '#dc3545', color: '#ffffff', title: 'Absent' };
        case 'H':
            return { label: 'H', bg: '#0d6efd', color: '#ffffff', title: 'Holiday' };
        default:
            return { label: '-', bg: '#f8f9fa', color: '#adb5bd', title: 'No Record' };
    }
};

const printReport = () => { window.print(); };

const exportExcel = () => {
    const url = route('reports.attendance.export', {
        view_mode: currentMode.value,
        month: selectedMonth.value,
        year: selectedYear.value,
        user_id: userIdFilter.value,
        role_id: roleIdFilter.value,
    });
    window.location.href = url;
};
</script>

<template>
    <Head title="Employee Attendance Performance Report" />
    <AuthenticatedLayout>
        <!-- Top Toolbar -->
        <div class="d-flex justify-content-between align-items-center px-4 py-2 bg-white text-dark shadow-sm w-100 mb-0 d-print-none flex-wrap gap-2">
            <h4 class="fw-bold mb-0 banner-title">
                <i class="bi bi-calendar-check me-2 text-primary"></i>Employee Attendance Performance Report
            </h4>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Mode Switcher Toggle Pills -->
                <div class="bg-light p-1 rounded-pill shadow-sm d-flex gap-1 border">
                    <button class="btn btn-sm rounded-pill px-3" 
                            :class="currentMode === 'month' ? 'btn-primary shadow fw-bold' : 'text-muted'" 
                            @click="switchMode('month')">
                        <i class="bi bi-calendar-month me-1"></i> Month-wise
                    </button>
                    <button class="btn btn-sm rounded-pill px-3" 
                            :class="currentMode === 'year' ? 'btn-primary shadow fw-bold' : 'text-muted'" 
                            @click="switchMode('year')">
                        <i class="bi bi-calendar-range me-1"></i> Year-wise
                    </button>
                </div>

                <!-- Export Excel Button -->
                <button class="btn btn-success shadow-sm rounded-pill px-4 ms-2" @click="exportExcel">
                    <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
                </button>

                <!-- Print Button -->
                <button class="btn btn-outline-primary shadow-sm rounded-pill px-4" @click="printReport">
                    <i class="bi bi-printer me-1"></i> Print
                </button>
            </div>
        </div>

        <div class="p-4" style="background: #f7f9fc; min-height: 100vh;">
            <div class="card shadow-sm border-0 p-4 bg-white table-print-container">
                
                <!-- Filter Bar -->
                <div class="row g-3 mb-4 p-3 rounded-3 bg-light border d-print-none align-items-center">
                    <!-- Month Selector (Month Mode) -->
                    <div v-if="currentMode === 'month'" class="col-md-4 col-sm-6">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1">Select Month</label>
                        <div class="d-flex align-items-center bg-white p-1 rounded-pill border shadow-sm gap-1">
                            <button class="btn btn-sm btn-white rounded-circle shadow-sm px-2 py-1 text-dark" 
                                    title="Previous Month" 
                                    @click="shiftMonth(-1)">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <select class="form-select form-select-sm border-0 bg-transparent fw-bold text-center pe-4" 
                                    style="cursor: pointer; color: #003287;" 
                                    v-model="selectedMonth" 
                                    @change="applyFilter">
                                <option v-for="opt in monthOptions" :key="opt.val" :value="opt.val">
                                    {{ opt.label }}
                                </option>
                            </select>
                            <button class="btn btn-sm btn-white rounded-circle shadow-sm px-2 py-1 text-dark" 
                                    title="Next Month" 
                                    @click="shiftMonth(1)">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Year Selector (Year Mode) -->
                    <div v-if="currentMode === 'year'" class="col-md-3 col-sm-6">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1">Select Year</label>
                        <select class="form-select form-select-sm rounded-pill shadow-sm fw-bold" v-model="selectedYear" @change="applyFilter">
                            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>

                    <!-- Department / Role Filter -->
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1">Department / Role</label>
                        <select class="form-select form-select-sm rounded-pill shadow-sm" v-model="roleIdFilter" @change="applyFilter">
                            <option value="">All Departments</option>
                            <option v-for="r in allRoles" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                    </div>

                    <!-- Employee Filter -->
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1">Employee</label>
                        <select class="form-select form-select-sm rounded-pill shadow-sm" v-model="userIdFilter" @change="applyFilter">
                            <option value="">All Employees</option>
                            <option v-for="e in allEmployeesList" :key="e.id" :value="e.id">{{ e.name }}</option>
                        </select>
                    </div>
                </div>

                <!-- Report Title Banner -->
                <div class="text-center mb-4 pt-2 border-bottom pb-3">
                    <h3 class="fw-bold mb-1 tracking-wide" style="color: #000; letter-spacing: 1px;">B.P.&CO</h3>
                    <h5 class="fw-bold text-uppercase" style="color: #003287; letter-spacing: 0.5px;">
                        EMPLOYEE ATTENDANCE PERFORMANCE REPORT – 
                        <span v-if="currentMode === 'month'">
                            {{ new Date(month + '-01').toLocaleDateString('en-US', { month: 'long', year: 'numeric' }).toUpperCase() }}
                        </span>
                        <span v-else>YEAR {{ year }}</span>
                    </h5>
                </div>

                <!-- MODE 1: Month-wise Daily Attendance Matrix Table -->
                <div v-if="currentMode === 'month'" class="table-responsive">
                    <table class="table table-bordered text-center align-middle attendance-table shadow-sm">
                        <thead>
                            <tr style="background-color: #003287; color: white; font-weight: bold;">
                                <th class="text-start ps-3" style="min-width: 150px; position: sticky; left: 0; background: #003287; z-index: 2;">STAFF NAME</th>
                                <th style="min-width: 110px;">ROLE</th>
                                <!-- Days Columns (1 to daysInMonth) -->
                                <th v-for="d in daysInMonth" :key="d" style="min-width: 32px; padding: 6px 2px; font-size: 0.8rem;">
                                    {{ d }}
                                </th>
                                <th style="background-color: #198754; color: white; min-width: 60px;">P</th>
                                <th style="background-color: #ffc107; color: black; min-width: 60px;">L</th>
                                <th style="background-color: #fd7e14; color: white; min-width: 60px;">HD</th>
                                <th style="background-color: #dc3545; color: white; min-width: 60px;">A</th>
                                <th style="background-color: #0d6efd; color: white; min-width: 80px;">POINTS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="emp in reportData" :key="emp.id" class="table-row-hover">
                                <td class="text-start ps-3 fw-bold text-uppercase text-dark" style="position: sticky; left: 0; background: white; z-index: 1;">
                                    {{ emp.name }}
                                </td>
                                <td><span class="badge bg-light text-dark border">{{ emp.role }}</span></td>
                                
                                <!-- Daily Status Cells -->
                                <td v-for="d in daysInMonth" :key="d" class="p-1">
                                    <div class="d-flex align-items-center justify-content-center rounded-circle mx-auto fw-bold"
                                         style="width: 24px; height: 24px; font-size: 0.72rem;"
                                         :style="{ backgroundColor: getStatusBadge(emp.dailyGrid[d]?.status).bg, color: getStatusBadge(emp.dailyGrid[d]?.status).color }"
                                         :title="`${emp.dailyGrid[d]?.date}: ${getStatusBadge(emp.dailyGrid[d]?.status).title} (${emp.dailyGrid[d]?.points} pts)`">
                                        {{ getStatusBadge(emp.dailyGrid[d]?.status).label }}
                                    </div>
                                </td>

                                <!-- Summary Columns -->
                                <td class="fw-bold text-success">{{ emp.presentCount }}</td>
                                <td class="fw-bold text-warning">{{ emp.lateCount }}</td>
                                <td class="fw-bold text-primary">{{ emp.halfDayCount }}</td>
                                <td class="fw-bold text-danger">{{ emp.absentCount }}</td>
                                <td class="fw-bold text-primary bg-light fs-6">{{ emp.totalPoints }}</td>
                            </tr>
                            <tr v-if="reportData.length === 0">
                                <td :colspan="daysInMonth + 7" class="text-center py-5 text-muted fw-bold">
                                    No employee attendance records found for this period.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- MODE 2: Year-wise 12-Month Attendance Matrix Table -->
                <div v-if="currentMode === 'year'" class="table-responsive">
                    <table class="table table-bordered text-center align-middle attendance-table shadow-sm">
                        <thead>
                            <tr style="background-color: #003287; color: white; font-weight: bold;">
                                <th class="text-start ps-3" style="min-width: 160px;">STAFF NAME</th>
                                <th style="min-width: 120px;">ROLE</th>
                                <!-- Months Columns Jan - Dec -->
                                <th v-for="m in 12" :key="m" style="min-width: 80px;" class="text-uppercase">
                                    {{ new Date(2026, m - 1, 1).toLocaleDateString('en-US', { month: 'short' }) }}
                                </th>
                                <th style="background-color: #198754; color: white; min-width: 100px;">TOTAL PRESENT</th>
                                <th style="background-color: #ffc107; color: black; min-width: 90px;">TOTAL LATE</th>
                                <th style="background-color: #d97706; color: white; min-width: 110px;">ANNUAL SCORE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="emp in reportData" :key="emp.id" class="table-row-hover">
                                <td class="text-start ps-3 fw-bold text-uppercase text-dark">
                                    {{ emp.name }}
                                </td>
                                <td><span class="badge bg-light text-dark border">{{ emp.role }}</span></td>

                                <!-- Monthly Summary Cells -->
                                <td v-for="m in 12" :key="m" class="p-2">
                                    <div class="small fw-bold text-dark">
                                        {{ emp.monthlyGrid[m]?.presentCount }}/{{ emp.monthlyGrid[m]?.totalDays }}
                                    </div>
                                    <small v-if="emp.monthlyGrid[m]?.lateCount > 0" class="badge bg-warning text-dark px-1 py-0" style="font-size: 0.65rem;">
                                        {{ emp.monthlyGrid[m]?.lateCount }} L
                                    </small>
                                </td>

                                <!-- Yearly Summary Columns -->
                                <td class="fw-bold text-success fs-6">{{ emp.yearlyTotalPresent }} Days</td>
                                <td class="fw-bold text-warning fs-6">{{ emp.yearlyTotalLate }}</td>
                                <td class="fw-bold text-primary bg-light fs-6">{{ emp.yearlyTotalPoints }}</td>
                            </tr>
                            <tr v-if="reportData.length === 0">
                                <td colspan="17" class="text-center py-5 text-muted fw-bold">
                                    No employee attendance data found for year {{ year }}.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Legend -->
                <div class="mt-4 p-3 rounded" style="background: rgba(0, 50, 135, 0.03); border: 1px dashed rgba(0, 50, 135, 0.2);">
                    <div class="d-flex flex-wrap gap-4 align-items-center justify-content-center">
                        <span class="fw-bold text-dark font-monospace text-uppercase me-2">
                            <i class="bi bi-info-circle me-1"></i> Attendance Status Legend:
                        </span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-circle shadow-sm text-white" style="background-color: #198754; width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem;">P</span>
                            <span class="fw-semibold">Present</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-circle shadow-sm text-dark" style="background-color: #ffc107; width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem;">L</span>
                            <span class="fw-semibold">Late</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-circle shadow-sm text-white" style="background-color: #fd7e14; width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem;">HD</span>
                            <span class="fw-semibold">Half Day</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-circle shadow-sm text-white" style="background-color: #dc3545; width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem;">A</span>
                            <span class="fw-semibold">Absent</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-circle shadow-sm text-white" style="background-color: #0d6efd; width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem;">H</span>
                            <span class="fw-semibold">Holiday</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700&display=swap');

.banner-title {
    font-family: 'Outfit', sans-serif;
    color: #003287;
}

.attendance-table {
    font-family: 'Inter', sans-serif;
    font-size: 0.85rem;
    border-collapse: collapse;
}

.attendance-table td, .attendance-table th {
    border: 1px solid #dee2e6 !important;
    vertical-align: middle;
}

.table-row-hover:hover {
    background-color: rgba(13, 110, 253, 0.04) !important;
}

@media print {
    body { background: white !important; }
    .table-print-container { margin: 0 !important; box-shadow: none !important; border: none !important; padding: 0 !important; }
}
</style>
