<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import { watch, onMounted } from 'vue';

const props = defineProps({
    metrics:       Array,
    existingSlips: Array,
    today:         String,
    yesterday:     String,
    reportRows:    Array,
    reportFrom:    String,
    reportTo:      String,
});

// ── Filtered Metrics (Excluding Attendance) ────────
const filteredMetrics = computed(() => 
    props.metrics.filter(m => m.key !== 'attendance')
);

// ── Tab state ─────────────────────────────────────────
const activeTab    = ref(filteredMetrics.value.length > 0 ? filteredMetrics.value[0].id : null);

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const mId = urlParams.get('metric_id');
    if (mId) {
        activeTab.value = parseInt(mId);
    }
});

const selectedDate = ref(props.today);

// ── Form ──────────────────────────────────────────────
const form = useForm({ metric_id: null, date: props.today, value: '' });

// ── Points preview ────────────────────────────────────
const pointsPreview = ref({});
let previewTimer = null;
const onValueChange = (val, metricId) => {
    clearTimeout(previewTimer);
    if (!val || val <= 0) { pointsPreview.value[metricId] = null; return; }
    previewTimer = setTimeout(async () => {
        try {
            const r = await axios.get(route('slips.preview-points'), { params: { metric_id: metricId, value: val } });
            pointsPreview.value[metricId] = r.data.points;
        } catch {}
    }, 350);
};

// ── Existing slip helpers ─────────────────────────────
const getSlip = (metricId) =>
    props.existingSlips.find(s => s.metric_id == metricId && s.date === selectedDate.value);

// Watch for changes to date/metric to clear form
watch([activeTab, selectedDate], ([newTab, newDate]) => {
    form.value = '';
    pointsPreview.value[newTab] = null;
}, { immediate: true });
const isApproved = (metricId) => getSlip(metricId)?.status === 'approved';
const isPending  = (metricId) => getSlip(metricId)?.status === 'pending';
const isRejected = (metricId) => getSlip(metricId)?.status === 'rejected';

const activeMetric = computed(() => props.metrics.find(m => m.id === activeTab.value));

// ── Submit ────────────────────────────────────────────
const submitSlip = (metricId) => {
    form.metric_id = metricId;
    form.date      = selectedDate.value;
    form.post(route('slips.store'), {
        preserveScroll: true,
        onSuccess: () => { form.reset('value'); pointsPreview.value[metricId] = null; },
    });
};

// ── Report date filter ─────────────────────────────────
const reportFrom = ref(props.reportFrom);
const reportTo   = ref(props.reportTo);
const fetchReport = () => {
    router.get(route('slips.index'), { report_from: reportFrom.value, report_to: reportTo.value }, { preserveState: true });
};

// Icon Helper
const metricIcon = (label) => {
    const text = (label || '').toLowerCase();
    if (text.includes('sales')) return 'bi-graph-up-arrow';
    if (text.includes('collection')) return 'bi-cash-stack';
    if (text.includes('colour') || text.includes('color')) return 'bi-palette-fill';
    if (text.includes('customer')) return 'bi-headset';
    if (text.includes('late')) return 'bi-clock-history';
    if (text.includes('duty') || text.includes('time')) return 'bi-stopwatch';
    if (text.includes('attendance')) return 'bi-calendar-check-fill';
    if (text.includes('panel')) return 'bi-grid-1x2-fill';
    return 'bi-check-circle-fill';
};
// Date Helper
const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

// Value Helper
const formatValue = (val) => {
    const num = parseFloat(val);
    if (isNaN(num)) return val;
    if (num >= 100000) return (num / 100000).toFixed(1) + 'L';
    if (num >= 1000) return (num / 1000).toFixed(0) + 'K';
    return num;
};
</script>

<template>
    <Head title="Staff Slips" />
    <AuthenticatedLayout>
        <template #header>
            Daily Performance Slips
        </template>

        <div class="premium-slips-container">
            <div class="row g-3">
                
                <!-- Left Panel: Submission Hub (7 Units) -->
                <div class="col-xl-7">
                    <div class="premium-card p-3 p-md-4 h-100">
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
                            <div>
                                <h5 class="fw-bold title-font text-dark mb-1">Slips Submission</h5>
                                <p class="text-muted small mb-0">Record daily performance.</p>
                            </div>
                            <div class="bg-light p-1 rounded-pill d-flex border shadow-sm submission-date-toggle">
                                <button class="btn btn-xs px-3 rounded-pill fw-bold" 
                                    :class="selectedDate === today ? 'btn-primary' : 'btn-light text-muted'"
                                    style="font-size: 0.75rem;"
                                    @click="selectedDate = today">Today</button>
                                <button class="btn btn-xs px-3 rounded-pill fw-bold"
                                    :class="selectedDate === yesterday ? 'btn-primary' : 'btn-light text-muted'"
                                    style="font-size: 0.75rem;"
                                    @click="selectedDate = yesterday">Yesterday</button>
                            </div>
                        </div>

                        <!-- Pill Metric Selector -->
                        <div class="metrics-scroller mb-4">
                            <div class="pill-group">
                                <button v-for="m in filteredMetrics" :key="m.id"
                                    class="pill-item"
                                    :class="{ active: activeTab === m.id }"
                                    @click="activeTab = m.id">
                                    <i class="bi me-1" :class="metricIcon(m.label)"></i>
                                    {{ m.label.toUpperCase() }}
                                </button>
                            </div>
                        </div>

                        <!-- Entry Area -->
                        <div v-if="activeMetric" class="entry-stage text-center py-2">
                            <form @submit.prevent="submitSlip(activeMetric.id)" class="mx-auto" style="max-width: 400px;">
                                <div class="metric-visual mb-2 mx-auto shadow-sm">
                                    <i class="bi fs-3 text-primary" :class="metricIcon(activeMetric.label)"></i>
                                </div>
                                <h5 class="fw-bold title-font text-uppercase mb-3 mt-1">{{ activeMetric.label }}</h5>
                                
                                <div class="position-relative mb-3">
                                    <input type="number" step="0.01" min="0"
                                        class="glass-input-big text-center"
                                        :placeholder="'Enter ' + activeMetric.unit"
                                        :disabled="isApproved(activeMetric.id)"
                                        v-model="form.value"
                                        @input="onValueChange(form.value, activeMetric.id)"
                                        autocomplete="off"
                                        required>
                                    
                                    <!-- Points Preview Bubble -->
                                    <div v-if="pointsPreview[activeMetric.id]" class="points-badge fadeIn">
                                        +{{ pointsPreview[activeMetric.id] }} PTS
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary premium-submit-btn w-100 py-2 mb-2" 
                                    :disabled="form.processing || isApproved(activeMetric.id)">
                                    <i class="bi bi-send-check me-2" v-if="!form.processing"></i>
                                    <span v-else class="spinner-border spinner-border-sm me-2"></span>
                                    {{ isApproved(activeMetric.id) ? 'LOCKED' : 'SUBMIT' }}
                                </button>

                                <div v-if="Object.keys(form.errors).length > 0" class="alert alert-danger p-2 small mb-2 text-start">
                                    <ul class="mb-0 ps-3">
                                        <li v-for="err in form.errors">{{ err }}</li>
                                    </ul>
                                </div>

                                <div class="alert-box small">
                                    <p v-if="isApproved(activeMetric.id)" class="text-success fw-bold">
                                        <i class="bi bi-patch-check-fill me-1"></i> This entry has been verified and locked.
                                    </p>
                                    <p v-else-if="isPending(activeMetric.id)" class="text-warning fw-bold">
                                        <i class="bi bi-hourglass-split me-1"></i> Submitted & awaiting supervisor approval.
                                    </p>
                                    <p v-else-if="isRejected(activeMetric.id)" class="text-danger fw-bold">
                                        <i class="bi bi-exclamation-octagon-fill me-1"></i> Rejected: {{ getSlip(activeMetric.id)?.comment }}
                                    </p>
                                    <p v-else class="text-muted"><i class="bi bi-info-circle me-1"></i> Only one submission allowed per day.</p>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right Panel: History & Summary (5 Units) -->
                <div class="col-xl-5">
                    <div class="premium-card p-3 p-md-4 h-100 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold title-font text-dark mb-0">Records</h6>
                            <div class="d-flex gap-1">
                                <input type="date" class="form-control form-control-sm glass-input-small" v-model="reportFrom">
                                <input type="date" class="form-control form-control-sm glass-input-small" v-model="reportTo">
                                <button class="btn btn-sm btn-dark px-2 py-0" @click="fetchReport"><i class="bi bi-filter"></i></button>
                            </div>
                        </div>

                        <div class="table-responsive flex-grow-1">
                            <table class="table table-hover align-middle premium-mini-table">
                                <thead class="bg-light sticky-top">
                                    <tr>
                                        <th class="ps-2">DATE</th>
                                        <th class="text-center">METRICS</th>
                                        <th class="text-end pe-2">PTS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in reportRows" :key="row.date">
                                        <td class="ps-2 fw-bold text-muted" style="font-size: 0.75rem; white-space: nowrap;">
                                            {{ formatDate(row.date) }}
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center flex-wrap gap-1">
                                                <template v-for="m in metrics" :key="m.id">
                                                    <div v-if="row[m.key]" 
                                                         class="badge rounded-pill border d-flex align-items-center px-2 py-1"
                                                         :class="{
                                                            'bg-success-light text-success border-success': row[m.key + '_status'] === 'approved',
                                                            'bg-warning-light text-warning border-warning': row[m.key + '_status'] === 'pending',
                                                            'bg-danger-light text-danger border-danger': row[m.key + '_status'] === 'rejected',
                                                            'bg-light text-muted border-secondary': !row[m.key + '_status'] || row[m.key + '_status'] === 'none'
                                                         }"
                                                         style="font-size: 0.65rem;"
                                                         :title="m.label + ': ' + row[m.key] + ' (' + (row[m.key + '_status'] || 'pending') + ')'">
                                                        <i class="bi me-1" :class="metricIcon(m.label)"></i>
                                                        <span class="me-1 fw-bold opacity-75" style="font-size: 0.55rem;">{{ m.label.substring(0,3).toUpperCase() }}:</span>
                                                        {{ formatValue(row[m.key]) }}
                                                    </div>
                                                </template>
                                            </div>
                                        </td>
                                        <td class="text-end pe-2">
                                            <span class="badge bg-primary rounded-pill px-2 py-1" style="font-size: 0.7rem;">{{ parseFloat(row.total_points).toFixed(0) }}</span>
                                        </td>
                                    </tr>
                                    <tr v-if="reportRows.length === 0">
                                        <td colspan="3" class="text-center py-5 text-muted small">No recent activity.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.premium-slips-container {
    max-width: 1300px;
    margin: 0.5rem auto;
    padding: 0 0.5rem;
    font-family: 'Inter', sans-serif;
}

.title-font { font-family: 'Outfit', sans-serif; }

.premium-card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    border: 1px solid rgba(0,0,0,0.05);
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    padding: 1.25rem !important;
}

/* Metrics Pill Container */
.metrics-scroller {
    background: rgba(0,0,0,0.02);
    padding: 10px;
    border-radius: 14px;
}

.pill-group {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: center;
}
.pill-item {
    background: #fff;
    border: 1px solid #eee;
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 700;
    color: #666;
    white-space: nowrap;
    transition: all 0.2s;
}

@media (min-width: 768px) {
    .premium-slips-container { margin: 1rem auto; padding: 0 1rem; }
    .premium-card { padding: 2rem !important; }
    .pill-item { padding: 8px 16px; font-size: 0.75rem; }
    .metrics-scroller { padding: 8px; }
    .pill-group { gap: 8px; }
}
.pill-item.active {
    background: #0d6efd;
    border-color: #0d6efd;
    color: #fff;
}

/* Entry Stage */
.metric-visual {
    width: 60px;
    height: 60px;
    background: #f8fafc;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(0,0,0,0.03);
}

.glass-input-big {
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    width: 100%;
    padding: 12px;
    font-size: 1.5rem;
    font-weight: 800;
    border-radius: 12px;
    transition: all 0.3s;
    font-family: 'Outfit', sans-serif;
}
.glass-input-big:focus {
    background: #fff;
    border-color: #0d6efd;
    box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1);
    outline: none;
}

.points-badge {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: #22c55e;
    color: white;
    padding: 6px 12px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 800;
    box-shadow: 0 4px 10px rgba(34, 197, 94, 0.2);
    z-index: 5;
}

.premium-submit-btn {
    border-radius: 12px;
    font-weight: 700;
    font-size: 0.9rem;
    transition: all 0.2s;
}
.premium-submit-btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 5px 15px rgba(13, 110, 253, 0.2);
}

/* History table */
.glass-input-small {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 6px;
    max-width: 120px;
    font-size: 0.75rem;
}

.premium-mini-table {
    font-size: 0.85rem;
}
.premium-mini-table thead th {
    border: none;
    color: #94a3b8;
    letter-spacing: 0.5px;
    font-size: 0.7rem;
    padding: 10px 8px;
}
.premium-mini-table tbody td {
    border-bottom: 1px solid rgba(0,0,0,0.02);
    padding: 10px 8px;
}

.metric-mini-badge {
    font-size: 1.1rem;
    transition: transform 0.2s;
}
.metric-mini-badge:hover {
    transform: scale(1.2);
}

.bg-primary-light { background-color: rgba(13, 110, 253, 0.05); }
.bg-success-light { background-color: rgba(25, 135, 84, 0.05); }
.bg-warning-light { background-color: rgba(255, 193, 7, 0.05); }
.bg-danger-light  { background-color: rgba(220, 53, 69, 0.05); }

.fadeIn {
    animation: fadeInAnim 0.3s ease-out forwards;
}
@keyframes fadeInAnim {
    from { opacity: 0; transform: translateY(-20%); }
    to { opacity: 1; transform: translateY(-50%); }
}

@media (max-width: 1199px) {
    .premium-card { margin-bottom: 1rem; }
}
</style>
