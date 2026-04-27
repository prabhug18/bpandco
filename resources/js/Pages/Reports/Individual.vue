<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    employee: Object,
    allEmployees: Array,
    metrics: Array,
    slips: Object,
    attendance: Object,
    scores: Array,
    month: String, // format YYYY-MM
    days: Number,
    // New optional props from drill-down
    metric_id: [String, Number],
    date_from: String,
    date_to: String,
    period: String,
});

const selectedMonth = ref(props.month);
const activePeriod  = ref(props.period || 'this_month');
const customFrom    = ref(props.date_from || `${props.month}-01`);
const customTo      = ref(props.date_to || `${props.month}-${props.days}`);
const selectedEmployeeId = ref(props.employee?.id || '');

const hasSelection = computed(() => !!props.employee);

const periods = [
    { key: 'this_month', label: 'THIS MONTH' },
    { key: '10_days',    label: '10 DAYS' },
    { key: '20_days',    label: '20 DAYS' },
    { key: '30_days',    label: '30 DAYS' },
    { key: '3_months',   label: '3 MO' },
    { key: '6_months',   label: '6 MO' },
    { key: '1_year',     label: '1 YR' },
    { key: 'custom',     label: 'CUSTOM' },
];

const applyFilter = (key = activePeriod.value) => {
    activePeriod.value = key;
    const params = { period: key, user_id: selectedEmployeeId.value };
    
    if (key === 'custom') {
        params.date_from = customFrom.value;
        params.date_to   = customTo.value;
    }
    
    router.get(route('reports.individual'), params, { preserveState: true });
};

const onEmployeeChange = () => {
    applyFilter();
};

// Period Blocks logic
const periodBlocks = computed(() => {
    // If it's a long period (3+ months), we only show one "Summary" block
    if (['3_months', '6_months', '1_year'].includes(activePeriod.value)) {
        return [{ label: "Summary", start: 1, end: 1, isSummary: true }];
    }

    // Default 10-day blocks
    return [
        { label: "1-10", start: 1, end: 10, targetType: '10_days' },
        { label: "11-20", start: 11, end: 20, targetType: '20_days', achievableType: '10_days' },
        { label: `21-${props.days}`, start: 21, end: props.days, targetType: '30_days', achievableType: '10_days' }
    ];
});

const filteredMetrics = computed(() => {
    if (props.metric_id) {
        return props.metrics.filter(m => m.id == props.metric_id);
    }
    return props.metrics;
});

const getDayVal = (dateStr, metricId) => {
    const daySlips = props.slips[dateStr] || [];
    return daySlips.find(s => s.metric_id === metricId)?.value || '-';
};

const getDayPoints = (dateStr, metricId) => {
    const daySlips = props.slips[dateStr] || [];
    const slip = daySlips.find(s => s.metric_id === metricId);
    return slip ? slip.daily_points_earned : '-';
};

const getPeriodScore = (metricId, type) => {
    return props.scores.find(s => s.metric_id === metricId && s.period_type === type);
};

const formatTargets = (targets, isPeriod = false) => {
    if(!targets || targets.length === 0) return '';
    return targets.map(t => {
        const valLabel = t.min_value >= 100000 ? `${(t.min_value/100000).toFixed(1)}L` : (t.min_value >= 1000 ? `${(t.min_value/1000).toFixed(0)}K` : t.min_value);
        const pts = t.points_awarded ?? t.daily_points;
        return `[${valLabel}-${parseFloat(pts).toFixed(2)}P]`;
    }).join(' ');
};

const getPeriodTargetsForPrefix = (metric, typePrefix) => {
    return metric.period_targets?.filter(t => t.period_type === typePrefix) || [];
};

// Determine Light Class based on metric's defined tiers or points
const lightClass = (val, metric = null, max = 0) => {
    const v = parseFloat(val);
    if(isNaN(v) || v <= 0) return 'bg-secondary text-white'; // grey
    
    // If it's a daily points value and we have the metric tiers
    if (metric && metric.daily_scoring_tiers?.length > 0) {
        // Find the tier that matches this points value
        const tier = metric.daily_scoring_tiers.find(t => parseFloat(t.daily_points) === v);
        if (tier) {
            if (tier.tier_label === 'green') return 'bg-success text-white';
            if (tier.tier_label === 'yellow') return 'bg-warning text-dark';
            if (tier.tier_label === 'red') return 'bg-danger text-white';
            if (tier.tier_label === 'grey') return 'bg-secondary text-white';
        }
    }

    // Fallback for period points (using max ratio)
    if (max > 0) {
        const ratio = (v / max) * 100;
        if(ratio >= 100) return 'bg-success text-white';
        if(ratio >= 70) return 'bg-warning text-dark';
        if(ratio >= 40) return 'bg-danger text-white';
        return 'bg-secondary text-white';
    }

    // Default heuristic
    if(v >= 0.6) return 'bg-success text-white'; 
    if(v >= 0.4) return 'bg-warning text-dark';
    return 'bg-danger text-white';
};

const getMetricMaxPoints = (metric, type) => {
    const targets = getPeriodTargetsForPrefix(metric, type);
    return targets.length > 0 ? Math.max(...targets.map(t => parseFloat(t.points_awarded || 0))) : 0;
};

const formatDate = (dayNum) => {
    const d = new Date(`${props.month}-${String(dayNum).padStart(2, '0')}T00:00:00`);
    const formatted = d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }).replace(/ /g, '-');
    return formatted; // e.g. "11-May-25"
};

const getDayName = (dayNum) => {
    const d = new Date(`${props.month}-${String(dayNum).padStart(2, '0')}T00:00:00`);
    return d.toLocaleDateString('en-US', { weekday: 'short' }); 
};

// Aggregate day bases report row (Respects metric filters)
const getDayBasisPoints = (dayNum) => {
    let total = 0;
    const dateStr = `${props.month}-${String(dayNum).padStart(2, '0')}`;
    filteredMetrics.value.forEach(m => {
        total += parseFloat(getDayPoints(dateStr, m.id) || 0);
    });
    return total > 0 ? total.toFixed(2) : '-';
};

// Calculate total points for a period type (Respects metric filters)
const getPeriodTotalPoints = (type) => {
    return props.scores
        .filter(s => s.period_type === type && filteredMetrics.value.some(m => m.id == s.metric_id))
        .reduce((sum, s) => sum + parseFloat(s.period_points_earned || 0), 0);
};

const printReport = () => { window.print(); };
</script>

<template>
    <Head title="Individual Performance Report" />
    <AuthenticatedLayout>
        <div class="bg-white border-bottom shadow-sm d-print-none">
            <div class="px-4 py-3">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div>
                            <h4 class="fw-bold mb-0 banner-title text-uppercase">Individual Performance Report</h4>
                            <p class="text-muted small mb-0">
                                <span v-if="hasSelection">{{ employee.name }} | {{ activePeriod.toUpperCase() }}</span>
                                <span v-else>Please select a staff member to view report</span>
                            </p>
                        </div>
                        <div v-if="allEmployees && allEmployees.length > 0" class="ms-lg-4">
                            <select class="form-select glass-input border shadow-none w-auto fw-bold text-primary px-4 rounded-pill" v-model="selectedEmployeeId" @change="onEmployeeChange">
                                <option value="" disabled>-- SELECT STAFF MEMBER --</option>
                                <option v-for="emp in allEmployees" :key="emp.id" :value="emp.id">STAFF: {{ emp.name.toUpperCase() }}</option>
                            </select>
                        </div>
                    </div>
                    
                    <div v-if="hasSelection" class="d-flex flex-wrap align-items-center gap-2">
                        <div class="bg-light p-1 rounded-pill d-flex border shadow-sm me-2">
                            <button v-for="p in periods" :key="p.key"
                                class="btn rounded-pill px-3 py-1 fw-bold"
                                :class="activePeriod === p.key ? 'btn-primary shadow-sm' : 'btn-light text-muted'"
                                style="font-size: 0.7rem;"
                                @click="applyFilter(p.key)">
                                {{ p.label }}
                            </button>
                        </div>
                        <button class="btn btn-outline-dark shadow-sm rounded-pill px-4 fw-bold" @click="printReport">
                            <i class="bi bi-printer me-1"></i> PRINT
                        </button>
                    </div>
                </div>

                <!-- Custom Date Range Row -->
                <div v-if="activePeriod === 'custom'" class="row g-2 mt-2 fadeIn bg-light p-2 rounded-3 border">
                    <div class="col-md-4">
                        <input type="date" class="form-control form-control-sm glass-input" v-model="customFrom">
                    </div>
                    <div class="col-md-4">
                        <input type="date" class="form-control form-control-sm glass-input" v-model="customTo">
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-sm btn-dark w-100 fw-bold" @click="applyFilter('custom')">APPLY DATES</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-4" style="background: #f7f9fc; min-height: 100vh;">
            <!-- Selection Prompt -->
            <div v-if="!hasSelection" class="d-flex flex-column align-items-center justify-content-center py-5">
                <div class="premium-card p-5 text-center shadow-lg border-0" style="max-width: 500px;">
                    <div class="mb-4">
                        <i class="bi bi-person-badge text-primary" style="font-size: 4rem;"></i>
                    </div>
                    <h3 class="fw-bold text-dark">Staff Report Viewer</h3>
                    <p class="text-muted">Use the dropdown above to select a staff member and view their detailed performance analytics.</p>
                </div>
            </div>

            <div v-if="hasSelection" class="text-center mb-4 pt-3 print-header">
                <h3 class="fw-bold mb-1" style="color: #000;">BP and Co</h3>
                <h5 class="fw-bold" style="color: #444;">Traffic Light Report</h5>
                <h6 class="fw-bold text-uppercase" style="color: #555;">{{ employee.name }}</h6>
            </div>

            <!-- Generate Separate Tables / Summary -->
            <div v-if="hasSelection" v-for="(block, bIdx) in periodBlocks" :key="bIdx" class="card shadow-sm border-0 bg-white mb-5 table-print-container" style="overflow-x: auto;">
                <!-- Summary Table for Long Periods -->
                <table v-if="block.isSummary" class="table table-bordered text-center align-middle m-0 excel-table">
                    <thead class="bg-primary text-white">
                        <tr>
                            <th class="py-3">METRIC</th>
                            <th class="py-3">PERIOD TOTAL</th>
                            <th class="py-3">TARGET RANGE</th>
                            <th class="py-3">POINTS EARNED</th>
                            <th class="py-3">STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="metric in filteredMetrics" :key="metric.id">
                            <td class="fw-bold text-start ps-3 py-3 text-uppercase">{{ metric.label }}</td>
                            <td class="fw-bold fs-5">{{ getPeriodScore(metric.id, '30_days')?.cumulative_value || '0.00' }}</td>
                            <td class="small">{{ formatTargets(getPeriodTargetsForPrefix(metric, '30_days'), true) }}</td>
                            <td class="fw-bold fs-5">{{ parseFloat(getPeriodScore(metric.id, '30_days')?.period_points_earned || 0).toFixed(0) }}</td>
                            <td>
                                <span class="badge px-3 py-2 rounded-pill" :class="lightClass(getPeriodScore(metric.id, '30_days')?.period_points_earned, getMetricMaxPoints(metric, '30_days'))">
                                    {{ getPeriodScore(metric.id, '30_days')?.traffic_light?.toUpperCase() || 'NONE' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <table v-else class="table table-bordered text-center align-middle m-0 excel-table">
                    <thead>
                        <!-- Header Row 1 -->
                        <tr style="background-color: #f7dac4; font-weight: bold; border-bottom: 2px solid #ccc;">
                            <th class="metric-col">
                                <div class="text-danger">{{ new Date(props.month+'-01').toLocaleDateString('en-US', {month: 'short', year:'2-digit'}) }}</div>
                                <div style="font-size: 0.75rem;">{{ formatDate(block.start) }} to {{ formatDate(block.end) }}</div>
                            </th>
                            <!-- Days headers -->
                            <th v-for="d in (block.end - block.start + 1)" :key="d" style="min-width: 65px; border-right: 1px solid #ddd;">
                                <div class="small fw-bold">{{ formatDate(block.start + d - 1) }}</div>
                                <div class="small text-muted">{{ getDayName(block.start + d - 1) }}</div>
                            </th>
                            
                            <!-- Aggregation headers -->
                            <th style="min-width: 90px; background-color: #fff6e5;">10 Days Target</th>
                            <th style="min-width: 90px; background-color: #fff6e5;">10 Days Achieved</th>
                            <th style="min-width: 90px; background-color: #f0f7ff;">20 Days Target</th>
                            <th style="min-width: 90px; background-color: #f0f7ff;">20 Days Achieved</th>
                            <th style="min-width: 90px; background-color: #eefdf4;">30 Days Target</th>
                            <th style="min-width: 90px; background-color: #f1f0ff;">Consolidated Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="metric in filteredMetrics" :key="metric.id">
                            <!-- Metric Value Row -->
                            <tr class="value-row">
                                <td class="fw-bold py-2 metric-col text-uppercase text-start ps-3" style="border-bottom: 0;">{{ metric.label }}</td>
                                <!-- Days Val -->
                                <td v-for="d in (block.end - block.start + 1)" :key="d" class="py-2 fw-semibold" style="border-bottom: 0;">
                                    {{ getDayVal(`${month}-${String(block.start + d - 1).padStart(2, '0')}`, metric.id) }}
                                </td>
                                
                                <!-- Right side aggregation targets & values (Values row) -->
                                <td class="small fw-bold text-success target-legend">{{ formatTargets(getPeriodTargetsForPrefix(metric, '10_days'), true) }}</td>
                                <td class="fw-bold bg-white">{{ getPeriodScore(metric.id, '10_days')?.cumulative_value > 0 ? getPeriodScore(metric.id, '10_days').cumulative_value : '-' }}</td>

                                <td class="small fw-bold text-primary target-legend">{{ formatTargets(getPeriodTargetsForPrefix(metric, '20_days'), true) }}</td>
                                <td class="fw-bold bg-white">{{ getPeriodScore(metric.id, '20_days')?.cumulative_value > 0 ? getPeriodScore(metric.id, '20_days').cumulative_value : '-' }}</td>

                                <td class="small fw-bold text-danger target-legend">{{ formatTargets(getPeriodTargetsForPrefix(metric, '30_days'), true) }}</td>
                                <td class="fw-bold bg-white">{{ getPeriodScore(metric.id, '30_days')?.period_points_earned > 0 ? parseFloat(getPeriodScore(metric.id, '30_days').period_points_earned).toFixed(0) : '-' }}</td>
                            </tr>

                            <!-- Daily Points Row -->
                            <tr class="points-row">
                                <td class="small fw-bold text-start ps-3 py-1" style="border-top: 0; font-size: 0.65rem;">
                                    <div v-for="(tier, idx) in metric.daily_scoring_tiers" :key="idx" 
                                         :class="{
                                            'text-success': tier.tier_label === 'green',
                                            'text-warning': tier.tier_label === 'yellow',
                                            'text-danger': tier.tier_label === 'red',
                                            'text-secondary': tier.tier_label === 'grey'
                                         }">
                                        ({{ tier.min_value >= 1000 ? (tier.min_value/1000)+'K' : tier.min_value }}-{{ parseFloat(tier.daily_points).toFixed(2) }}P)
                                    </div>
                                </td>
                                <!-- Days Points with Colors -->
                                <td v-for="d in (block.end - block.start + 1)" :key="d" 
                                    class="py-1 fw-bold border" 
                                    :class="lightClass(getDayPoints(`${month}-${String(block.start + d - 1).padStart(2, '0')}`, metric.id), metric)">
                                    {{ getDayPoints(`${month}-${String(block.start + d - 1).padStart(2, '0')}`, metric.id) }}
                                </td>

                                <!-- Aggregation Points with Colors -->
                                <td class="bg-light"></td>
                                <td class="fw-bold" :class="lightClass(getPeriodScore(metric.id, '10_days')?.period_points_earned, null, getMetricMaxPoints(metric, '10_days'))">
                                    {{ getPeriodScore(metric.id, '10_days')?.period_points_earned > 0 ? parseFloat(getPeriodScore(metric.id, '10_days').period_points_earned).toFixed(0) : '-' }}
                                </td>
                                
                                <td class="bg-light"></td>
                                <td class="fw-bold" :class="lightClass(getPeriodScore(metric.id, '20_days')?.period_points_earned, null, getMetricMaxPoints(metric, '20_days'))">
                                    {{ getPeriodScore(metric.id, '20_days')?.period_points_earned > 0 ? parseFloat(getPeriodScore(metric.id, '20_days').period_points_earned).toFixed(0) : '-' }}
                                </td>

                                <td class="bg-light"></td>
                                <td class="fw-bold" :class="lightClass(getPeriodScore(metric.id, '30_days')?.period_points_earned, null, getMetricMaxPoints(metric, '30_days'))">
                                    {{ getPeriodScore(metric.id, '30_days')?.period_points_earned > 0 ? parseFloat(getPeriodScore(metric.id, '30_days').period_points_earned).toFixed(0) : '-' }}
                                </td>
                            </tr>
                        </template>

                        <!-- Final Footer Row for Day Basis -->
                        <tr style="border-top: 3px solid #000;">
                            <td class="fw-bold text-end bg-light">Day Basis Total</td>
                            <td v-for="d in (block.end - block.start + 1)" :key="d" 
                                class="fw-bold" 
                                :class="lightClass(getDayBasisPoints(block.start + d - 1))">
                                {{ getDayBasisPoints(block.start + d - 1) }}
                            </td>
                            <!-- Totals logic -->
                            <td class="bg-light fw-bold text-end pe-3">Total 10D</td>
                            <td class="fw-bold" :class="lightClass(getPeriodTotalPoints('10_days'), null, 100)">
                                {{ getPeriodTotalPoints('10_days') > 0 ? getPeriodTotalPoints('10_days') : '-' }}
                            </td>
                            <td class="bg-light fw-bold text-end pe-3">Total 20D</td>
                            <td class="fw-bold" :class="lightClass(getPeriodTotalPoints('20_days'), null, 100)">
                                {{ getPeriodTotalPoints('20_days') > 0 ? getPeriodTotalPoints('20_days') : '-' }}
                            </td>
                            <td class="bg-light fw-bold text-end pe-3">Total 30D</td>
                            <td class="fw-bold fs-5" :class="lightClass(getPeriodTotalPoints('30_days'), null, 100)">
                                {{ getPeriodTotalPoints('30_days') > 0 ? getPeriodTotalPoints('30_days') : '-' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
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
    font-size: 0.8rem;
    border-collapse: collapse;
}

.excel-table td, .excel-table th {
    border: 1px solid #555 !important;
    vertical-align: middle;
}

.metric-col {
    min-width: 140px;
    background: #fdfdfd;
}

.target-legend {
    white-space: pre-wrap; 
    font-size: 0.72rem;
    line-height: 1.2;
}

@media print {
    body { background: white !important; }
    .table-print-container {
        page-break-inside: avoid;
        margin-bottom: 2rem !important;
        box-shadow: none !important;
        border: none !important;
    }
}
</style>
