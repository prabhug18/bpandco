<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    roleGroups: Array,
    month: String, // YYYY-MM
    period: { type: String, default: '30_days' },
});

const selectedMonth = ref(props.month);

const applyFilter = () => {
    router.get(route('reports.team'), {
        month: selectedMonth.value,
        period: '30_days',
    }, { preserveState: true });
};

// Generate list of human-readable month options for the dropdown
const monthOptions = computed(() => {
    const options = [];
    const [currentYr, currentMo] = props.month.split('-').map(Number);
    const baseDate = new Date(currentYr, currentMo - 1, 1);

    // Generate 12 months before and 6 months after
    for (let i = -12; i <= 6; i++) {
        const d = new Date(baseDate.getFullYear(), baseDate.getMonth() + i, 1);
        const yr = d.getFullYear();
        const mo = String(d.getMonth() + 1).padStart(2, '0');
        const val = `${yr}-${mo}`;
        const label = d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        options.push({ val, label });
    }
    return options.reverse(); // Most recent first
});

// Shift month forward (+1) or backward (-1)
const shiftMonth = (offset) => {
    const [yr, mo] = selectedMonth.value.split('-').map(Number);
    const d = new Date(yr, mo - 1 + offset, 1);
    const newYr = d.getFullYear();
    const newMo = String(d.getMonth() + 1).padStart(2, '0');
    selectedMonth.value = `${newYr}-${newMo}`;
    applyFilter();
};

// CSS class and style mapping based on traffic light color
const getLightStyle = (light) => {
    switch (light) {
        case 'green':
            return { backgroundColor: '#198754', color: '#ffffff', fontWeight: 'bold' };
        case 'yellow':
            return { backgroundColor: '#ffc107', color: '#000000', fontWeight: 'bold' };
        case 'red':
            return { backgroundColor: '#dc3545', color: '#ffffff', fontWeight: 'bold' };
        case 'grey':
        default:
            return { backgroundColor: '#6c757d', color: '#ffffff', fontWeight: 'bold' };
    }
};

const printReport = () => { window.print(); };
</script>

<template>
    <Head title="Traffic Light Performance Report" />
    <AuthenticatedLayout>
        <!-- Top Toolbar -->
        <div class="d-flex justify-content-between align-items-center px-4 py-2 bg-white text-dark shadow-sm w-100 mb-0 d-print-none">
            <h4 class="fw-bold mb-0 banner-title">
                <i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Traffic Light Performance Report
            </h4>
            
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Easy Month Controls -->
                <div class="d-flex align-items-center bg-light p-1 rounded-pill border shadow-sm gap-1">
                    <button class="btn btn-sm btn-white rounded-circle shadow-sm px-2 py-1 text-dark" 
                            title="Previous Month" 
                            @click="shiftMonth(-1)">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    
                    <select class="form-select form-select-sm border-0 bg-transparent fw-bold text-center pe-4" 
                            style="width: 170px; cursor: pointer; color: #003287;" 
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

                <!-- Print Button -->
                <button class="btn btn-primary shadow-sm rounded-pill px-4 ms-2" @click="printReport">
                    <i class="bi bi-printer me-1"></i> Print
                </button>
            </div>
        </div>

        <div class="p-4" style="background: #f7f9fc; min-height: 100vh;">
            <div class="card shadow-sm border-0 p-4 bg-white table-print-container">
                <!-- Report Header -->
                <div class="text-center mb-4 pt-2 border-bottom pb-3">
                    <h3 class="fw-bold mb-1 tracking-wide" style="color: #000; letter-spacing: 1px;">B.P.&CO</h3>
                    <h5 class="fw-bold text-uppercase" style="color: #d97706; letter-spacing: 0.5px;">
                        TRAFFIC LIGHT REPORT (30 DAYS) {{ new Date(month + '-01').toLocaleDateString('en-US', { month: 'long', year: 'numeric' }).toUpperCase() }}
                    </h5>
                </div>

                <!-- Departmental Tables Loop -->
                <div v-for="(group, gIdx) in roleGroups" :key="gIdx" class="mb-5">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-dark px-3 py-2 fs-6 rounded-pill text-uppercase font-monospace shadow-sm">
                            {{ group.role }}
                        </span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered text-center align-middle excel-table shadow-sm">
                            <thead>
                                <tr style="background-color: #f7dac4; font-weight: bold; border-bottom: 2px solid #999;">
                                    <th class="text-start ps-3" style="min-width: 160px;">STAFFS</th>
                                    <th v-for="metric in group.metrics" :key="metric.id" class="text-uppercase" style="min-width: 120px;">
                                        {{ metric.label || metric.name }}
                                    </th>
                                    <th style="background-color: #d97706; color: #ffffff !important; min-width: 150px; font-weight: bold;" class="text-uppercase">
                                        TOTAL MARK FOR 30 DAYS
                                    </th>
                                    <th class="d-print-none" style="width: 90px;">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="emp in group.members" :key="emp.id" class="table-row-hover">
                                    <td class="text-start ps-3 fw-bold text-uppercase text-dark">
                                        {{ emp.name }}
                                    </td>

                                    <!-- Metric Data Cells with Traffic Light Colors -->
                                    <td v-for="metric in group.metrics" :key="metric.id" class="p-1 border text-center align-middle">
                                        <div class="d-flex align-items-center justify-content-center p-2 rounded shadow-sm fs-6"
                                             :style="getLightStyle(emp.metricData[metric.id]?.light)">
                                            {{ emp.metricData[metric.id]?.formattedValue || '0' }}
                                        </div>
                                    </td>

                                    <!-- Total Mark for Period -->
                                    <td class="p-1 border text-center align-middle" style="background-color: #fff9f5;">
                                        <div class="d-flex align-items-center justify-content-center p-2 rounded shadow-sm fs-6"
                                             :style="getLightStyle(emp.totalMark >= 70 ? 'green' : (emp.totalMark >= 50 ? 'yellow' : (emp.totalMark > 0 ? 'red' : 'grey')))">
                                            {{ emp.totalMark }}
                                        </div>
                                    </td>

                                    <!-- View Action -->
                                    <td class="d-print-none text-center">
                                        <Link :href="route('reports.individual', { user_id: emp.id, month: month })" 
                                              class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm">
                                            View
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="group.members.length === 0">
                                    <td :colspan="group.metrics.length + 3" class="text-center py-4 text-muted fw-bold">
                                        No staff members found for this department.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer Legend -->
                <div class="mt-4 p-3 rounded" style="background: rgba(0, 50, 135, 0.03); border: 1px dashed rgba(0, 50, 135, 0.2);">
                    <div class="d-flex flex-wrap gap-4 align-items-center justify-content-center">
                        <span class="fw-bold text-dark font-monospace text-uppercase me-2">
                            <i class="bi bi-info-circle me-1"></i> Traffic Light Legend:
                        </span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge shadow-sm" style="background-color: #198754; width: 25px; height: 14px;"></span>
                            <span class="fw-semibold">Green (Target Achieved / High Performance)</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge shadow-sm" style="background-color: #ffc107; width: 25px; height: 14px;"></span>
                            <span class="fw-semibold text-dark">Yellow (Moderate Performance)</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge shadow-sm" style="background-color: #dc3545; width: 25px; height: 14px;"></span>
                            <span class="fw-semibold">Red (Below Target Alert)</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge shadow-sm" style="background-color: #6c757d; width: 25px; height: 14px;"></span>
                            <span class="fw-semibold">Grey (Inactive / Zero Slips Logged)</span>
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

.excel-table {
    font-family: 'Inter', sans-serif;
    font-size: 0.9rem;
    border-collapse: collapse;
}

.excel-table td, .excel-table th {
    border: 1px solid #999 !important;
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
