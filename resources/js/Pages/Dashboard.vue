<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import Alert from '@/Utils/Alert';

const props = defineProps({
    summary:      Object,
    metricscores: Array,
    roleMetrics:  Array,
    month:        String,
    period:       String,
    dateFrom:     String,
    dateTo:       String,
    slipReport:   Object,
    teamStats:    Object, // Recieved from backend
    showDetailsId: [String, Number],
});

// Period filter
const activePeriod = ref(props.period ?? 'this_month');
const customFrom   = ref(props.dateFrom);
const customTo     = ref(props.dateTo);

const periods = [
    { key: '10_days',    label: '10 DAYS' },
    { key: '20_days',    label: '20 DAYS' },
    { key: '30_days',    label: '30 DAYS' },
    { key: 'this_month', label: 'MONTH' },
    { key: '3_months',   label: '3 MO' },
    { key: '6_months',   label: '6 MO' },
    { key: '1_year',     label: '1 YR' },
    { key: 'custom',     label: 'CUSTOM' },
];

const setPeriod = (key) => {
    activePeriod.value = key;
    if (key !== 'custom') {
        router.get(route('dashboard'), { period: key, staff_id: props.teamStats?.selectedStaffId }, { preserveState: true });
    }
};

const applyCustom = () => {
    router.get(route('dashboard'), { 
        period: 'custom', 
        date_from: customFrom.value, 
        date_to: customTo.value,
        staff_id: props.teamStats?.selectedStaffId 
    }, { preserveState: true });
};

// Metric tile expand
const expandedTile = ref(null);
const tileDateFrom = ref(props.dateFrom);
const tileDateTo   = ref(props.dateTo);

const toggleTile = (metricId) => {
    expandedTile.value = expandedTile.value === metricId ? null : metricId;
};

const searchTile = (metric) => {
    // If admin/supervisor is viewing and HAS NOT selected a staff member
    if (props.teamStats && !props.teamStats.selectedStaffId) {
        Alert.warning('Selection Required', 'Please choose a staff member from the dropdown first to view their performance details.');
        return;
    }

    router.get(route('dashboard'), {
        period:       activePeriod.value,
        tile_from:    tileDateFrom.value,
        tile_to:      tileDateTo.value,
        show_details: metric.id,
        staff_id:     props.teamStats?.selectedStaffId
    }, { preserveState: true });
};

// Details Modal logic
const showModal = ref(false);
const selectedMetricForDetails = ref(null);

const viewMetricDetails = (metric) => {
    selectedMetricForDetails.value = metric;
    showModal.value = true;
};

const filteredSlips = computed(() => {
    if (!selectedMetricForDetails.value) return [];
    const mId = selectedMetricForDetails.value.id;
    const list = [];
    Object.keys(props.slipReport).forEach(date => {
        props.slipReport[date].forEach(slip => {
            if (slip.metric_id === mId) {
                list.push(slip);
            }
        });
    });
    return list.sort((a, b) => new Date(b.date) - new Date(a.date));
});

// Watch for the detail trigger from backend
watch(() => props.showDetailsId, (newId) => {
    if (newId) {
        const metric = props.roleMetrics.find(m => String(m.id) === String(newId));
        if (metric) {
            expandedTile.value = metric.id;
            viewMetricDetails(metric);
        }
    }
}, { immediate: true });

const closeDetails = () => {
    showModal.value = false;
    selectedMetricForDetails.value = null;
    // Clear show_details from URL
    router.get(route('dashboard'), {
        period:    activePeriod.value,
        tile_from: tileDateFrom.value,
        tile_to:   tileDateTo.value,
        staff_id:  props.teamStats?.selectedStaffId
    }, { preserveState: true, preserveScroll: true });
};

// Traffic light helpers
const iconColorClass = (light) => ({
    green:  'text-success',
    yellow: 'text-warning',
    red:    'text-danger',
    grey:   'text-secondary',
}[light] || 'text-muted');

const metricIcon = (label) => {
    const text = (label || '').toLowerCase();
    if (text.includes('sales')) return 'bi-graph-up-arrow';
    if (text.includes('collection')) return 'bi-cash-stack';
    if (text.includes('colour') || text.includes('color')) return 'bi-palette-fill';
    if (text.includes('customer')) return 'bi-headset';
    if (text.includes('late')) return 'bi-clock-history';
    if (text.includes('duty') || text.includes('time')) return 'bi-stopwatch';
    if (text.includes('panel')) return 'bi-grid-1x2-fill';
    return 'bi-check-circle-fill';
};

const totalScore = computed(() =>
    props.metricscores.reduce((sum, s) => sum + parseFloat(s.period_points_earned || 0), 0).toFixed(0)
);

const getScoreForMetric = (metricId) =>
    props.metricscores.find(s => s.metric_id === metricId);

const formatVal = (val) => {
    const num = parseFloat(val);
    if (isNaN(num) || num === 0) return '0';
    if (num >= 100000) return (num / 100000).toFixed(2) + 'L';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toFixed(0);
};

const handleReview = (metricId = null) => {
    // If admin/supervisor is viewing and HAS NOT selected a staff member
    if (props.teamStats && !props.teamStats.selectedStaffId) {
        Alert.warning('Selection Required', 'Please choose a staff member from the dropdown first to view their performance report.');
        return;
    }

    const params = {
        from: props.dateFrom,
        to:   props.dateTo
    };

    if (props.teamStats?.selectedStaffId) {
        params.user_id = props.teamStats.selectedStaffId;
    }

    if (metricId) {
        params.metric_id = metricId;
    }
    
    router.get(route('reports.individual'), params);
};
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <!-- Correct Header Slot Integration -->
        <template #header>
            Main Dashboard
        </template>

        <div class="container-fluid py-4 min-vh-100" style="background: rgba(247, 249, 252, 0.5);">
            <div class="row g-4 gx-lg-5">
                
                <!-- ═══════════ LEFT: Traffic Score ═══════════ -->
                <div class="col-xl-3 col-lg-4">
                    <h5 class="fw-bold text-dark title-font mb-4">
                        <i class="bi bi-activity text-primary me-2"></i>
                        {{ teamStats?.targetName ? 'Score: ' + teamStats.targetName : 'My Traffic Score' }}
                    </h5>
                    
                    <div class="premium-card overflow-hidden">
                        <div class="bg-primary text-white text-center py-4">
                            <h2 class="display-4 fw-bold mb-0 font-monospace">{{ totalScore }}</h2>
                            <small class="text-uppercase fw-semibold" style="letter-spacing: 2px; opacity: 0.8; font-size: 0.8rem;">Total Score</small>
                        </div>
                        
                        <div class="p-0">
                            <ul class="list-group list-group-flush">
                                <li v-for="metric in roleMetrics" :key="metric.id" 
                                    class="list-group-item d-flex justify-content-between align-items-center py-3 border-bottom px-4"
                                    style="background: transparent;">
                                    <div class="d-flex flex-column overflow-hidden">
                                        <span class="text-secondary fw-bold text-uppercase title-font text-truncate pe-2" style="font-size: 0.75rem;" :title="metric.label">{{ metric.label }}</span>
                                        <small class="text-primary fw-bold">{{ formatVal(metric.total_value) }} {{ metric.unit }}</small>
                                    </div>
                                    <div class="d-flex align-items-center gap-3 flex-shrink-0">
                                        <span class="fw-bold font-monospace fs-5 text-dark">{{ getScoreForMetric(metric.id)?.period_points_earned ?? '—' }}</span>
                                        <i class="fs-5" :class="[metricIcon(metric.label), iconColorClass(getScoreForMetric(metric.id)?.traffic_light)]"></i>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- ═══════════ RIGHT: Tiles + Period Filter ════════════ -->
                <div class="col-xl-9 col-lg-8">

                    <!-- Management Overview (Only for Admin/Supervisors) -->
                    <div v-if="teamStats" class="row g-3 mb-4 fadeIn">
                        <!-- Staff Selector -->
                        <div class="col-md-3">
                            <div class="premium-card p-3 h-100 bg-white border-dark" style="border-left: 4px solid #212529;">
                                <h6 class="text-muted small fw-bold mb-2 text-uppercase">Select Staff</h6>
                                <select class="form-select form-select-sm glass-input cursor-pointer" 
                                    :value="teamStats.selectedStaffId || ''"
                                    @change="e => $inertia.get(route('dashboard'), { staff_id: e.target.value }, { preserveState: true })">
                                    <option value="">-- Choose Staff --</option>
                                    <option v-for="staff in teamStats.allStaff" :key="staff.id" :value="staff.id">
                                        {{ staff.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="premium-card p-3 h-100 d-flex align-items-center gap-3 bg-white border-primary" style="border-left: 4px solid #0d6efd;">
                                <div class="bg-primary bg-opacity-10 p-2 rounded-3 text-primary">
                                    <i class="bi bi-people-fill fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted small fw-bold mb-0 text-uppercase">Total Staff</h6>
                                    <div class="fs-5 fw-bold title-font">{{ teamStats.total_users }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <Link :href="route('approvals.index')" class="text-decoration-none h-100 d-block">
                                <div class="premium-card p-3 h-100 d-flex align-items-center gap-3 bg-white hover-bg" :class="{'border-danger': teamStats.pending_slips > 0}" style="border-left: 4px solid #dc3545;">
                                    <div class="bg-danger bg-opacity-10 p-2 rounded-3 text-danger">
                                        <i class="bi bi-hourglass-split fs-4"></i>
                                    </div>
                                    <div>
                                        <h6 class="text-muted small fw-bold mb-0 text-uppercase">Pending Slips</h6>
                                        <div class="fs-5 fw-bold title-font">{{ teamStats.pending_slips }}</div>
                                    </div>
                                </div>
                            </Link>
                        </div>
                        <div class="col-md-3">
                            <div class="premium-card p-3 h-100 d-flex align-items-center gap-3 bg-white" style="border-left: 4px solid #198754;">
                                <div class="bg-success bg-opacity-10 p-2 rounded-3 text-success">
                                    <i class="bi bi-graph-up fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted small fw-bold mb-0 text-uppercase">Team Avg Score</h6>
                                    <div class="fs-5 fw-bold title-font">{{ parseFloat(teamStats.avg_total_score).toFixed(1) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic Period Selector -->
                    <div class="d-flex flex-column mb-4 gap-3">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                            <h5 class="fw-bold text-dark title-font mb-0"><i class="bi bi-grid-fill text-primary me-2"></i>Daily Submission Modules</h5>
                            
                            <div class="period-scroll-container">
                                <div class="bg-white p-1 rounded-pill shadow-sm d-flex border gap-1 flex-nowrap w-100">
                                    <button v-for="p in periods" :key="p.key"
                                        class="btn rounded-pill px-2 py-1 fw-bold title-font text-nowrap"
                                        :class="activePeriod === p.key ? 'btn-primary shadow-sm' : 'btn-light text-muted'"
                                        style="font-size: 0.7rem; transition: all 0.3s; flex: 1;"
                                        @click="setPeriod(p.key)">
                                        {{ p.label }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Custom Date Row -->
                        <div v-if="activePeriod === 'custom'" class="row g-2 fadeIn bg-white p-3 rounded-4 border shadow-sm mx-0">
                            <div class="col-md-5">
                                <label class="small fw-bold text-muted mb-1 text-uppercase">Start Date</label>
                                <input type="date" class="form-control glass-input" v-model="customFrom">
                            </div>
                            <div class="col-md-5">
                                <label class="small fw-bold text-muted mb-1 text-uppercase">End Date</label>
                                <input type="date" class="form-control glass-input" v-model="customTo">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button class="btn btn-dark w-100 fw-bold py-2" @click="applyCustom">APPLY</button>
                            </div>
                        </div>
                    </div>

                    <!-- Metric Tiles Grid -->
                    <div class="row g-4 align-items-start">
                        <div v-for="metric in roleMetrics" :key="metric.id" class="col-lg-4 col-sm-6">
                            <div class="premium-card metric-tile h-100 d-flex flex-column" :class="{ 'expanded-tile': expandedTile === metric.id }">
                                <!-- Tile Header (Click to expand) -->
                                <div class="px-4 py-4 d-flex flex-column cursor-pointer flex-grow-1" @click="toggleTile(metric.id)">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-bold text-dark title-font fs-6 text-uppercase text-truncate pe-2" :title="metric.label">{{ metric.label }}</span>
                                        <i class="fs-4 flex-shrink-0" :class="[metricIcon(metric.label), iconColorClass(getScoreForMetric(metric.id)?.traffic_light)]"></i>
                                    </div>
                                    <div class="mt-auto">
                                        <h4 class="fw-bold mb-0 title-font">{{ formatVal(metric.total_value) }}</h4>
                                        <small class="text-muted fw-bold text-uppercase" style="font-size: 0.65rem;">Total {{ metric.unit }}</small>
                                    </div>
                                </div>
                                
                                <!-- Slips / Reports Quick Links -->
                                <div class="d-flex border-top bg-light">
                                    <template v-if="metric.key !== 'late' && !teamStats">
                                        <Link :href="metric.key === 'attendance' ? route('attendance.index') : route('slips.index', { metric_id: metric.id })" class="w-50 text-center py-3 text-decoration-none fw-bold small text-muted hover-bg">
                                            <i class="bi" :class="metric.key === 'attendance' ? 'bi-geo-alt' : 'bi-plus-circle'"></i> {{ metric.key === 'attendance' ? 'MARK' : 'SUBMIT' }}
                                        </Link>
                                        <div class="border-start"></div>
                                    </template>
                                    <button @click="handleReview(metric.id)" class="btn text-center py-3 text-decoration-none fw-bold small text-muted hover-bg border-0 bg-transparent" :class="metric.key === 'late' || teamStats ? 'w-100' : 'w-50'">
                                        <i class="bi bi-eye me-1"></i> REVIEW
                                    </button>
                                </div>

                                <!-- Collapsible Date Filter -->
                                <div class="expand-collapse-container" :style="{ maxHeight: expandedTile === metric.id ? '250px' : '0' }">
                                    <div class="p-3 border-top bg-white">
                                        <div class="row g-2 mb-3">
                                            <div class="col-6">
                                                <label class="small text-muted fw-bold mb-1">Start Date</label>
                                                <input type="date" class="form-control glass-input px-2" v-model="tileDateFrom">
                                            </div>
                                            <div class="col-6">
                                                <label class="small text-muted fw-bold mb-1">End Date</label>
                                                <input type="date" class="form-control glass-input px-2" v-model="tileDateTo">
                                            </div>
                                        </div>
                                        <button class="btn btn-dark w-100 fw-bold px-3 py-2 rounded-3" @click="searchTile(metric)">
                                            <i class="bi bi-search me-1"></i> View Records
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div> <!-- end tiles row -->

                    </div> <!-- end row g-4 -->
                </div> <!-- end col-xl-9 -->
            </div> <!-- end main row -->
        </div> <!-- end container-fluid -->

        <!-- Details Modal -->
        <div v-if="showModal" class="modal-backdrop fade show" @click="closeDetails"></div>
        <div v-if="showModal" class="modal fade show d-block" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg" @click.stop>
                    <div class="modal-header bg-primary text-white border-0">
                        <h5 class="modal-title fw-bold title-font text-uppercase">
                            <i class="bi bi-info-circle me-2"></i> {{ selectedMetricForDetails?.label }} Records
                        </h5>
                        <button type="button" class="btn-close btn-close-white shadow-none" @click="closeDetails"></button>
                    </div>
                    <div class="modal-body p-4 bg-light">
                        <div class="text-center mb-3">
                            <span class="badge bg-white text-primary border px-3 py-2 rounded-pill shadow-sm">
                                Period: {{ dateFrom }} to {{ dateTo }}
                            </span>
                        </div>
                        
                        <div class="table-responsive bg-white rounded-4 shadow-sm border overflow-hidden">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-primary bg-opacity-10">
                                    <tr class="small fw-bold text-muted">
                                        <th class="ps-4">DATE</th>
                                        <th class="text-center">VALUE</th>
                                        <th class="text-end pe-4">STATUS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="slip in filteredSlips" :key="slip.id">
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark">{{ new Date(slip.date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) }}</div>
                                            <small class="text-muted">{{ slip.comment || 'No comment' }}</small>
                                        </td>
                                        <td class="text-center fw-bold fs-5 text-primary">
                                            {{ slip.value }} <small class="fw-normal text-muted" style="font-size: 0.7rem;">{{ selectedMetricForDetails.unit }}</small>
                                        </td>
                                        <td class="text-end pe-4">
                                            <span class="badge rounded-pill px-3" 
                                                :class="slip.status === 'approved' ? 'bg-success bg-opacity-10 text-success' : 'bg-warning bg-opacity-10 text-warning'">
                                                {{ slip.status.toUpperCase() }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredSlips.length === 0">
                                        <td colspan="3" class="text-center py-5 text-muted fst-italic">
                                            No records found for this period.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light rounded-bottom-4 pt-0">
                        <button type="button" class="btn btn-secondary px-4 rounded-pill" @click="closeDetails">Close</button>
                    </div>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600&display=swap');

.title-font {
    font-family: 'Outfit', sans-serif;
    letter-spacing: 0.5px;
}

.period-scroll-container {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none; /* Firefox */
}

.period-scroll-container::-webkit-scrollbar {
    display: none; /* Chrome/Safari */
}

@media (min-width: 768px) {
    .period-scroll-container {
        width: auto;
    }
}

.premium-card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    border: 1px solid rgba(0,0,0,0.05);
}

.metric-tile {
    transition: all 0.3s ease;
}

.metric-tile:hover {
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    transform: translateY(-3px);
}

.expanded-tile {
    box-shadow: 0 10px 25px rgba(13, 110, 253, 0.15) !important;
    border-color: rgba(13, 110, 253, 0.4);
}

.cursor-pointer {
    cursor: pointer;
}

.hover-bg {
    transition: all 0.2s;
}

.hover-bg:hover {
    background-color: rgba(13, 110, 253, 0.05);
    color: #0d6efd !important;
}

.expand-collapse-container {
    overflow: hidden;
    transition: max-height 0.4s cubic-bezier(0, 1, 0, 1);
}

.glass-input {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 6px;
    font-family: 'Inter', sans-serif;
    transition: all 0.3s;
}

.glass-input:focus {
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
    border-color: #0d6efd;
    outline: none;
}
</style>
