<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    employees: Array,
    metrics: Array,
    scores: Array,
    month: String, // YYYY-MM
    period: { type: String, default: '30_days' },
});

const selectedMonth = ref(props.month);
const selectedPeriod = ref(props.period);

const applyFilter = () => {
    router.get(route('reports.team'), {
        month: selectedMonth.value,
        period: selectedPeriod.value,
    }, { preserveState: true });
};

// Find points for a specific user and metric in the selected period type
const getScore = (userId, metricId) => {
    const s = props.scores.find(sc => sc.user_id === userId && sc.metric_id === metricId);
    return s ? parseFloat(s.period_points_earned || 0) : null;
};

// Calculate total score for a user
const getTotalScore = (userId) => {
    return props.metrics.reduce((acc, metric) => {
        const val = getScore(userId, metric.id);
        return acc + (val || 0);
    }, 0);
};

// Cell color mapping based on Excel rules
const lightClass = (val, isTotal = false) => {
    if (val === null || val === undefined) return 'bg-light text-dark';
    
    // For totals, thresholds might be different - using standard for now
    if (val >= 70) return 'bg-success text-white fw-bold shadow-sm';
    if (val >= 50) return 'bg-warning text-dark fw-bold shadow-sm';
    if (val >= 30) return 'bg-danger text-white fw-bold shadow-sm';
    if (val > 0 || val <= 0) return 'bg-secondary text-white fw-bold shadow-sm'; // Grey for <30 or 0
    return 'bg-light text-dark';
};

const printReport = () => { window.print(); };
</script>

<template>
    <Head title="Team Summary Report" />
    <AuthenticatedLayout>
        <div class="d-flex justify-content-between align-items-center px-4 py-2 bg-white text-dark shadow-sm w-100 mb-0 d-print-none">
            <h4 class="fw-bold mb-0 banner-title">Excel Model Team Performance Summary</h4>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Period Selector -->
                <div class="bg-light p-1 rounded-pill shadow-sm d-flex gap-1 border">
                    <button class="btn btn-sm rounded-pill px-3" :class="selectedPeriod === '10_days' ? 'btn-primary shadow fw-bold' : 'text-muted'" @click="selectedPeriod = '10_days'; applyFilter()">10 Days</button>
                    <button class="btn btn-sm rounded-pill px-3" :class="selectedPeriod === '20_days' ? 'btn-primary shadow fw-bold' : 'text-muted'" @click="selectedPeriod = '20_days'; applyFilter()">20 Days</button>
                    <button class="btn btn-sm rounded-pill px-3" :class="selectedPeriod === '30_days' ? 'btn-primary shadow fw-bold' : 'text-muted'" @click="selectedPeriod = '30_days'; applyFilter()">30 Days</button>
                </div>
                <!-- Month Picker -->
                <input type="month" class="form-control form-control-sm glass-input shadow-sm" style="width: 150px; border-radius: 20px;" v-model="selectedMonth" @change="applyFilter">
                <button class="btn btn-primary shadow-sm rounded-pill px-4 ms-2" @click="printReport"><i class="bi bi-printer me-1"></i> Print</button>
            </div>
        </div>

        <div class="p-4" style="background: #f7f9fc; min-height: 100vh;">
            <div class="card shadow-sm border-0 p-4 bg-white table-print-container">
                <div class="text-center mb-4 pt-2">
                    <h3 class="fw-bold mb-1" style="color: #000;">BP and Co</h3>
                    <h5 class="fw-bold" style="color: #444;">Traffic Light Report – {{ new Date(month+'-01').toLocaleDateString('en-US', {month: 'long', year:'numeric'}) }} ({{ period.replace('_', 'Days ').replace('30', 'Consolidated') }})</h5>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered text-center align-middle excel-table">
                        <thead>
                            <tr style="background-color: #f7dac4; font-weight: bold; border-bottom: 2px solid #ccc;">
                                <th class="text-start pe-4">Staffs</th>
                                <th v-for="metric in metrics" :key="metric.id">{{ metric.label }}</th>
                                <th style="background-color: #fff6e5;">Total Mark for {{ period.replace('_', ' ').toUpperCase() }}</th>
                                <th class="d-print-none" style="width: 100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="emp in employees" :key="emp.id" class="table-row-hover">
                                <td class="text-start fw-bold text-uppercase">{{ emp.name }}</td>
                                
                                <!-- Dynamic Metric Points -->
                                <td v-for="metric in metrics" :key="metric.id" class="p-0 border" style="height: 38px;">
                                    <div class="w-100 h-100 d-flex align-items-center justify-content-center m-1 p-1 rounded" :class="lightClass(getScore(emp.id, metric.id))" style="width: calc(100% - 8px); height: calc(100% - 8px);">
                                        {{ getScore(emp.id, metric.id) !== null ? getScore(emp.id, metric.id).toFixed(2) : '-' }}
                                    </div>
                                </td>
                                
                                <!-- Total Mark -->
                                <td class="p-0 border" style="background-color: #f8f9fa; height: 38px;">
                                    <div class="w-100 h-100 d-flex align-items-center justify-content-center m-1 p-1 rounded" :class="lightClass(getTotalScore(emp.id), true)" style="width: calc(100% - 8px); height: calc(100% - 8px); font-size: 1.1em;">
                                        {{ getTotalScore(emp.id).toFixed(2) }}
                                    </div>
                                </td>
                                
                                <!-- Action Link -->
                                <td class="d-print-none">
                                    <Link :href="route('reports.individual', { user_id: emp.id, month: month })" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm">
                                        View
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="employees.length === 0">
                                <td :colspan="metrics.length + 3" class="text-center py-5 text-muted fw-bold">No staffs found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 p-3 rounded" style="background: rgba(0, 50, 135, 0.03); border: 1px dashed rgba(0, 50, 135, 0.2);">
                    <div class="d-flex flex-wrap gap-4 align-items-center justify-content-center">
                        <span class="fw-bold text-dark font-monospace text-uppercase me-2"><i class="bi bi-info-circle me-1"></i> Legend:</span>
                        <div class="d-flex align-items-center gap-2"><span class="badge bg-success shadow-sm" style="width: 25px; height: 12px;"></span> <span class="fw-semibold">Green (>70)</span></div>
                        <div class="d-flex align-items-center gap-2"><span class="badge bg-warning shadow-sm" style="width: 25px; height: 12px;"></span> <span class="fw-semibold text-dark">Yellow (50-70)</span></div>
                        <div class="d-flex align-items-center gap-2"><span class="badge bg-danger shadow-sm" style="width: 25px; height: 12px;"></span> <span class="fw-semibold">Red (30-50)</span></div>
                        <div class="d-flex align-items-center gap-2"><span class="badge bg-secondary shadow-sm" style="width: 25px; height: 12px;"></span> <span class="fw-semibold">Grey (<30 or Missed)</span></div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap');

.banner-title {
    font-family: 'Outfit', sans-serif;
    color: #003287;
    letter-spacing: 0.5px;
}

.excel-table {
    font-family: 'Inter', sans-serif;
    font-size: 0.85rem;
    border-collapse: collapse;
}

.excel-table td, .excel-table th {
    border: 1px solid #777 !important;
    vertical-align: middle;
}

.table-row-hover:hover {
    background-color: rgba(13, 110, 253, 0.05) !important;
}

@media print {
    body { background: white !important; }
    .table-print-container { margin: 0 !important; box-shadow: none !important; border: none !important; padding: 0 !important; }
}
</style>
