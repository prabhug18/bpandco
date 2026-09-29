<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import Alert from '@/Utils/Alert';

const props = defineProps({
    metrics:       Array,
    existingSlips: Array,
    today:         String,
    yesterday:     String,
    reportRows:    Array,
    reportFrom:    String,
    reportTo:      String,
    isProduction:  Boolean,
});

const page = usePage();

// Identify if user is in Production role or has production metrics
const isProductionUser = computed(() => {
    return props.isProduction || 
           page.props.auth?.user?.roles?.includes('Production') || 
           props.metrics?.some(m => ['production', 'nc_thinner_mixing', 'enamel_thinner_mixing', 'mixing_thinners'].includes(m.key));
});

const productionMetricKeys = ['production', 'nc_thinner_mixing', 'enamel_thinner_mixing', 'mixing_thinners'];

// Filtered Metrics (Excluding Attendance)
const filteredMetrics = computed(() => 
    props.metrics.filter(m => m.key !== 'attendance')
);

// Navigation tabs
const displayTabs = computed(() => {
    if (!isProductionUser.value) {
        return filteredMetrics.value.map(m => ({
            id: m.id,
            label: m.label,
            is_prod_hub: false,
            metric: m,
            icon: metricIcon(m.label),
        }));
    }

    const tabs = [
        {
            id: 'production_hub',
            label: 'PRODUCTION & MIXING',
            is_prod_hub: true,
            icon: 'bi-box-seam-fill',
        }
    ];

    // Any other metrics assigned to this production user (e.g., Stock Checking)
    const otherMetrics = filteredMetrics.value.filter(m => !productionMetricKeys.includes(m.key));
    otherMetrics.forEach(m => {
        tabs.push({
            id: m.id,
            label: m.label,
            is_prod_hub: false,
            metric: m,
            icon: metricIcon(m.label),
        });
    });

    return tabs;
});

// Tab state
const activeTab = ref(isProductionUser.value ? 'production_hub' : (filteredMetrics.value.length > 0 ? filteredMetrics.value[0].id : null));

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const mId = urlParams.get('metric_id');
    if (mId) {
        const parsed = parseInt(mId);
        if (!isNaN(parsed) && displayTabs.value.some(t => t.id === parsed)) {
            activeTab.value = parsed;
        } else if (mId === 'production_hub') {
            activeTab.value = 'production_hub';
        }
    }
});

const selectedDate = ref(props.today);

// ── Standard single-metric Form ───────────────────────
const form = useForm({ metric_id: null, date: props.today, value: '' });

// Points preview for standard metrics
const pointsPreview = ref({});
let previewTimer = null;
const onValueChange = (val, metricId) => {
    clearTimeout(previewTimer);
    if (!val || val <= 0) { pointsPreview.value[metricId] = null; return; }
    previewTimer = setTimeout(async () => {
        try {
            const r = await axios.get(route('slips.preview-points'), { params: { metric_id: metricId, value: val, date: selectedDate.value } });
            pointsPreview.value[metricId] = r.data.points;
        } catch {}
    }, 350);
};

// Existing slip helpers
const getSlip = (metricId) =>
    props.existingSlips.find(s => s.metric_id == metricId && s.date === selectedDate.value);

const getSlipByKey = (key) => {
    const m = props.metrics.find(item => item.key === key);
    return m ? props.existingSlips.find(s => s.metric_id == m.id && s.date === selectedDate.value) : null;
};

// ── Production Multi-Field Form ───────────────────────
const prodForm = useForm({
    production_multi: true,
    date: props.today,
    boxes: '',
    nc_thinner_ltr: '',
    enamel_thinner_ltr: '',
});

const prodPreview = ref({
    box_points: 0,
    thinner_points: 0,
    total_thinners: 0,
    total_points: 0,
});

let prodPreviewTimer = null;
const fetchProdPreview = () => {
    clearTimeout(prodPreviewTimer);
    const hasValue = parseFloat(prodForm.boxes) > 0 || parseFloat(prodForm.nc_thinner_ltr) > 0 || parseFloat(prodForm.enamel_thinner_ltr) > 0;
    if (!hasValue) {
        prodPreview.value = { box_points: 0, thinner_points: 0, total_thinners: 0, total_points: 0 };
        return;
    }
    prodPreviewTimer = setTimeout(async () => {
        try {
            const r = await axios.get(route('slips.preview-points'), {
                params: {
                    production_multi: 1,
                    boxes: prodForm.boxes,
                    nc_thinner_ltr: prodForm.nc_thinner_ltr,
                    enamel_thinner_ltr: prodForm.enamel_thinner_ltr,
                    date: selectedDate.value,
                }
            });
            prodPreview.value = r.data;
        } catch (e) {}
    }, 300);
};

const dynamicTotalThinners = computed(() => {
    const nc = parseFloat(prodForm.nc_thinner_ltr) || 0;
    const en = parseFloat(prodForm.enamel_thinner_ltr) || 0;
    const sum = nc + en;
    return sum > 0 ? (sum % 1 === 0 ? sum : sum.toFixed(1)) : 0;
});

// Production Slips & Computed Status
const prodBoxSlip = computed(() => getSlipByKey('production'));
const prodNCSlip = computed(() => getSlipByKey('nc_thinner_mixing'));
const prodEnamelSlip = computed(() => getSlipByKey('enamel_thinner_mixing'));
const prodMixingSlip = computed(() => getSlipByKey('mixing_thinners'));

const hasProdEntry = computed(() => {
    return !!(prodBoxSlip.value || prodNCSlip.value || prodEnamelSlip.value || prodMixingSlip.value);
});

const isProdApproved = computed(() => {
    return (prodBoxSlip.value && prodBoxSlip.value.status === 'approved') || 
           (prodMixingSlip.value && prodMixingSlip.value.status === 'approved');
});

const isProdPending = computed(() => {
    return (prodBoxSlip.value && prodBoxSlip.value.status === 'pending') || 
           (prodMixingSlip.value && prodMixingSlip.value.status === 'pending') ||
           (prodNCSlip.value && prodNCSlip.value.status === 'pending') ||
           (prodEnamelSlip.value && prodEnamelSlip.value.status === 'pending');
});

const isProdRejected = computed(() => {
    return (prodBoxSlip.value && prodBoxSlip.value.status === 'rejected') || 
           (prodMixingSlip.value && prodMixingSlip.value.status === 'rejected');
});

const prodRejectComment = computed(() => {
    return prodBoxSlip.value?.comment || prodMixingSlip.value?.comment || prodNCSlip.value?.comment || prodEnamelSlip.value?.comment || '';
});

// Edit mode states (forms reset immediately on submit; only populate if user clicks Edit)
const isEditingProd = ref(false);
const isEditingSingle = ref(false);

// Reset forms to zero/empty
const resetProdForm = () => {
    prodForm.boxes = '';
    prodForm.nc_thinner_ltr = '';
    prodForm.enamel_thinner_ltr = '';
    prodForm.date = selectedDate.value;
    prodPreview.value = { box_points: 0, thinner_points: 0, total_thinners: 0, total_points: 0 };
    isEditingProd.value = false;
};

const resetSingleForm = (metricId) => {
    form.reset('value');
    form.value = '';
    form.date = selectedDate.value;
    if (metricId) {
        pointsPreview.value[metricId] = null;
    } else {
        pointsPreview.value = {};
    }
    isEditingSingle.value = false;
};

// Populate production form from existing slips (only called on deliberate Edit Entry)
const syncProdForm = () => {
    prodForm.boxes = prodBoxSlip.value ? (parseFloat(prodBoxSlip.value.value) || '') : '';
    prodForm.nc_thinner_ltr = prodNCSlip.value ? (parseFloat(prodNCSlip.value.value) || '') : '';
    prodForm.enamel_thinner_ltr = prodEnamelSlip.value ? (parseFloat(prodEnamelSlip.value.value) || '') : '';
    prodForm.date = selectedDate.value;
    fetchProdPreview();
};

const startEditProd = () => {
    isEditingProd.value = true;
    syncProdForm();
};

const cancelEditProd = () => {
    resetProdForm();
};

// Standard Metric Helpers
const activeMetric = computed(() => props.metrics.find(m => m.id === activeTab.value));
const currentSlip = computed(() => activeMetric.value ? getSlip(activeMetric.value.id) : null);
const isSingleApproved = computed(() => currentSlip.value?.status === 'approved');
const isSinglePending  = computed(() => currentSlip.value?.status === 'pending');
const isSingleRejected = computed(() => currentSlip.value?.status === 'rejected');
const hasSingleEntry   = computed(() => !!currentSlip.value);

const startEditSingle = () => {
    isEditingSingle.value = true;
    if (currentSlip.value) {
        form.value = parseFloat(currentSlip.value.value) || '';
        if (activeMetric.value) {
            onValueChange(form.value, activeMetric.value.id);
        }
    }
};

const cancelEditSingle = () => {
    resetSingleForm(activeMetric.value?.id);
};

const isApproved = (metricId) => getSlip(metricId)?.status === 'approved';
const isPending  = (metricId) => getSlip(metricId)?.status === 'pending';
const isRejected = (metricId) => getSlip(metricId)?.status === 'rejected';

// Watchers: Always reset forms to empty/zero on tab or date change
watch([activeTab, selectedDate], ([newTab, newDate]) => {
    resetSingleForm(newTab);
    resetProdForm();
    prodForm.date = newDate;
    form.date = newDate;
}, { immediate: true });

// Submit standard single slip
const submitSlip = (metricId) => {
    form.metric_id = metricId;
    form.date      = selectedDate.value;
    form.post(route('slips.store'), {
        preserveScroll: true,
        onSuccess: () => {
            resetSingleForm(metricId);
            Alert.success('Submitted', 'Slip submitted successfully! Field has been reset to zero.');
        },
    });
};

// Submit production 3-field slip
const submitProdForm = () => {
    prodForm.date = selectedDate.value;
    prodForm.post(route('slips.store'), {
        preserveScroll: true,
        onSuccess: () => {
            resetProdForm();
            Alert.success('Submitted', 'Production slips submitted successfully! Fields have been reset to zero.');
        },
    });
};

// Report date filter
const reportFrom = ref(props.reportFrom);
const reportTo   = ref(props.reportTo);
const fetchReport = () => {
    router.get(route('slips.index'), { report_from: reportFrom.value, report_to: reportTo.value }, { preserveState: true });
};

// Icon Helper
const metricIcon = (label) => {
    const text = (label || '').toLowerCase();
    if (text.includes('box') || text.includes('production')) return 'bi-box-seam-fill';
    if (text.includes('nc thinner')) return 'bi-droplet-half';
    if (text.includes('enamel')) return 'bi-paint-bucket';
    if (text.includes('mixing') || text.includes('thinner')) return 'bi-funnel-fill';
    if (text.includes('stock')) return 'bi-clipboard-check-fill';
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

// Abbreviation Helper for Report badges
const metricAbbr = (m) => {
    if (m.key === 'production') return 'BOX';
    if (m.key === 'nc_thinner_mixing') return 'NC';
    if (m.key === 'enamel_thinner_mixing') return 'ENA';
    if (m.key === 'mixing_thinners') return 'MIX';
    return (m.label || '').substring(0, 3).toUpperCase();
};

// Value Helper
const formatValue = (val) => {
    const num = parseFloat(val);
    if (isNaN(num)) return val;
    if (num >= 100000) return (num / 100000).toFixed(1) + 'L';
    if (num >= 1000) return (num / 1000).toFixed(0) + 'K';
    return num % 1 === 0 ? num : num.toFixed(1);
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
                                <button v-for="tab in displayTabs" :key="tab.id"
                                    class="pill-item"
                                    :class="{ active: activeTab === tab.id }"
                                    @click="activeTab = tab.id">
                                    <i class="bi me-1" :class="tab.icon"></i>
                                    {{ tab.label.toUpperCase() }}
                                </button>
                            </div>
                        </div>

                        <!-- Entry Area A: Production 3-Input Hub (Box + NC Thinner + Enamel Thinner) -->
                        <div v-if="activeTab === 'production_hub'" class="entry-stage py-2">
                            <form @submit.prevent="submitProdForm" class="mx-auto" style="max-width: 480px;">
                                <div class="text-center mb-3">
                                    <div class="metric-visual mb-2 mx-auto shadow-sm" style="background: linear-gradient(135deg, #e0f2fe 0%, #dbeafe 100%);">
                                        <i class="bi bi-box-seam-fill fs-3 text-primary"></i>
                                    </div>
                                    <h5 class="fw-bold title-font text-uppercase mb-1">Daily Production & Mixing</h5>
                                    <p class="text-muted small mb-0">Record boxes produced & thinner mixing litres</p>
                                </div>

                                <!-- Today's Submitted Entry Summary Card -->
                                <div v-if="hasProdEntry" class="card border-0 shadow-sm mb-3 rounded-4 overflow-hidden text-start" 
                                     :class="{
                                         'bg-warning-subtle border border-warning': isProdPending,
                                         'bg-success-subtle border border-success': isProdApproved,
                                         'bg-danger-subtle border border-danger': isProdRejected
                                     }">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="fw-bold small text-uppercase d-flex align-items-center gap-1"
                                                  :class="{'text-warning-emphasis': isProdPending, 'text-success': isProdApproved, 'text-danger': isProdRejected}">
                                                <i class="bi" :class="{
                                                    'bi-hourglass-split': isProdPending,
                                                    'bi-patch-check-fill': isProdApproved,
                                                    'bi-exclamation-octagon-fill': isProdRejected
                                                }"></i>
                                                <span>{{ isProdApproved ? 'Verified & Locked Slip' : (isProdPending ? 'Submitted Entry (Pending Approval)' : 'Entry Rejected') }}</span>
                                            </span>
                                            <span class="badge rounded-pill px-2.5 py-1"
                                                  :class="{
                                                      'bg-warning text-dark': isProdPending,
                                                      'bg-success text-white': isProdApproved,
                                                      'bg-danger text-white': isProdRejected
                                                  }">
                                                {{ prodBoxSlip?.status?.toUpperCase() || prodMixingSlip?.status?.toUpperCase() }}
                                            </span>
                                        </div>

                                        <!-- Submitted values row -->
                                        <div class="row g-2 text-center my-1">
                                            <div class="col-4">
                                                <div class="p-2 bg-white rounded-3 border">
                                                    <div class="text-muted" style="font-size: 0.65rem;">BOXES</div>
                                                    <div class="fw-bold text-dark fs-6">{{ prodBoxSlip ? parseFloat(prodBoxSlip.value) : 0 }}</div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="p-2 bg-white rounded-3 border">
                                                    <div class="text-muted" style="font-size: 0.65rem;">NC THINNER</div>
                                                    <div class="fw-bold text-dark fs-6">{{ prodNCSlip ? parseFloat(prodNCSlip.value) : 0 }} <span style="font-size:0.65rem;">L</span></div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="p-2 bg-white rounded-3 border">
                                                    <div class="text-muted" style="font-size: 0.65rem;">ENAMEL</div>
                                                    <div class="fw-bold text-dark fs-6">{{ prodEnamelSlip ? parseFloat(prodEnamelSlip.value) : 0 }} <span style="font-size:0.65rem;">L</span></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top" style="border-color: rgba(0,0,0,0.08) !important;">
                                            <div class="small" style="font-size: 0.75rem;">
                                                <span v-if="isProdPending && !isEditingProd" class="text-warning-emphasis">
                                                    <i class="bi bi-info-circle me-1"></i>Input fields below reset to 0.
                                                </span>
                                                <span v-else-if="isEditingProd" class="text-primary fw-bold">
                                                    <i class="bi bi-pencil-fill me-1"></i>Edit mode active.
                                                </span>
                                                <span v-else-if="isProdApproved" class="text-success">
                                                    <i class="bi bi-shield-check me-1"></i>Slip verified & locked.
                                                </span>
                                                <span v-else-if="isProdRejected" class="text-danger">
                                                    <i class="bi bi-x-circle me-1"></i>{{ prodRejectComment }}
                                                </span>
                                            </div>

                                            <div v-if="isProdPending && !isEditingProd">
                                                <button type="button" class="btn btn-sm btn-outline-primary px-3 py-0.5 rounded-pill fw-bold" style="font-size: 0.75rem;" @click="startEditProd">
                                                    <i class="bi bi-pencil-square me-1"></i>Edit Entry
                                                </button>
                                            </div>
                                            <div v-else-if="isEditingProd">
                                                <button type="button" class="btn btn-sm btn-outline-secondary px-3 py-0.5 rounded-pill fw-bold" style="font-size: 0.75rem;" @click="cancelEditProd">
                                                    <i class="bi bi-x-circle me-1"></i>Cancel Edit
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="production-fields-card p-3 rounded-4 mb-3 border shadow-xs" style="background: #ffffff;">
                                    
                                    <!-- 1. Box Production -->
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label mb-0 fw-bold small text-dark d-flex align-items-center">
                                                <span class="badge bg-primary me-2 px-2 py-1 rounded-pill" style="font-size: 0.7rem;">1</span>
                                                <i class="bi bi-box-seam text-primary me-1"></i> Box Production
                                            </label>
                                            <span v-if="prodPreview.box_points > 0" class="badge bg-success-light text-success border border-success fw-bold" style="font-size: 0.75rem;">
                                                +{{ prodPreview.box_points }} PTS
                                            </span>
                                            <span v-else class="text-muted" style="font-size: 0.7rem;">
                                                Target: &ge; 15 Boxes (0.66 PTS)
                                            </span>
                                        </div>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0"
                                                class="form-control form-control-lg glass-input-field"
                                                :placeholder="isProdPending && !isEditingProd ? 'Submitted & Reset to 0' : 'Enter Boxes produced'"
                                                :disabled="isProdApproved || (isProdPending && !isEditingProd)"
                                                v-model="prodForm.boxes"
                                                @input="fetchProdPreview"
                                                autocomplete="off">
                                            <span class="input-group-text bg-light text-muted fw-bold small">Boxes</span>
                                        </div>
                                    </div>

                                    <hr class="my-3 opacity-25">

                                    <!-- 2. NC Thinner Mixing -->
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label mb-0 fw-bold small text-dark d-flex align-items-center">
                                                <span class="badge bg-primary me-2 px-2 py-1 rounded-pill" style="font-size: 0.7rem;">2</span>
                                                <i class="bi bi-droplet-half text-info me-1"></i> NC Thinner Mixing
                                            </label>
                                            <span class="text-muted" style="font-size: 0.7rem;">Litres</span>
                                        </div>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0"
                                                class="form-control form-control-lg glass-input-field"
                                                :placeholder="isProdPending && !isEditingProd ? 'Submitted & Reset to 0' : 'Enter NC Thinner (Ltr)'"
                                                :disabled="isProdApproved || (isProdPending && !isEditingProd)"
                                                v-model="prodForm.nc_thinner_ltr"
                                                @input="fetchProdPreview"
                                                autocomplete="off">
                                            <span class="input-group-text bg-light text-muted fw-bold small">Ltr</span>
                                        </div>
                                    </div>

                                    <!-- 3. Enamel Thinner Mixing -->
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label mb-0 fw-bold small text-dark d-flex align-items-center">
                                                <span class="badge bg-primary me-2 px-2 py-1 rounded-pill" style="font-size: 0.7rem;">3</span>
                                                <i class="bi bi-paint-bucket text-warning me-1"></i> Enamel Thinner Mixing
                                            </label>
                                            <span class="text-muted" style="font-size: 0.7rem;">Litres</span>
                                        </div>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0"
                                                class="form-control form-control-lg glass-input-field"
                                                :placeholder="isProdPending && !isEditingProd ? 'Submitted & Reset to 0' : 'Enter Enamel Thinner (Ltr)'"
                                                :disabled="isProdApproved || (isProdPending && !isEditingProd)"
                                                v-model="prodForm.enamel_thinner_ltr"
                                                @input="fetchProdPreview"
                                                autocomplete="off">
                                            <span class="input-group-text bg-light text-muted fw-bold small">Ltr</span>
                                        </div>
                                    </div>

                                    <!-- Real-time Thinners Combined Calculation -->
                                    <div class="p-2.5 rounded-3 d-flex justify-content-between align-items-center mt-2"
                                         style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px 14px;">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-white p-1 shadow-xs text-primary d-flex align-items-center justify-content-center border" style="width: 32px; height: 32px;">
                                                <i class="bi bi-funnel-fill" style="font-size: 0.9rem;"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark" style="font-size: 0.8rem;">Total Thinners Mixed</div>
                                                <div class="text-muted" style="font-size: 0.7rem;">NC + Enamel combined</div>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="d-flex align-items-center justify-content-end gap-1">
                                                <span class="fw-bold text-dark fs-6">{{ dynamicTotalThinners }}</span>
                                                <span class="text-muted small">Ltr</span>
                                                <span v-if="prodPreview.thinner_points > 0" class="badge bg-success-light text-success border border-success ms-1 px-2 py-0.5" style="font-size: 0.7rem;">
                                                    +{{ prodPreview.thinner_points }} PTS
                                                </span>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.65rem;">Target: &ge; 300 Ltr (0.66 PTS)</div>
                                        </div>
                                    </div>

                                </div>

                                <!-- Total Estimated Daily Points Summary Badge -->
                                <div v-if="prodPreview.total_points > 0" class="d-flex justify-content-between align-items-center p-2.5 mb-3 rounded-3 bg-primary-light border border-primary px-3 py-2">
                                    <span class="text-primary fw-bold small">
                                        <i class="bi bi-award-fill me-1"></i> Total Estimated Daily Points:
                                    </span>
                                    <span class="badge bg-primary px-3 py-1.5 rounded-pill fs-6 fw-bold">
                                        +{{ prodPreview.total_points }} PTS
                                    </span>
                                </div>

                                <button type="submit" class="btn premium-submit-btn w-100 py-2.5 mb-2" 
                                    :class="{
                                        'btn-secondary opacity-75': (isProdPending && !isEditingProd) || isProdApproved,
                                        'btn-primary': !((isProdPending && !isEditingProd) || isProdApproved)
                                    }"
                                    :disabled="prodForm.processing || isProdApproved || (isProdPending && !isEditingProd)">
                                    <i class="bi bi-send-check me-2" v-if="!prodForm.processing && !isProdPending && !isProdApproved && !isEditingProd"></i>
                                    <i class="bi bi-lock-fill me-2" v-else-if="!prodForm.processing && isProdApproved"></i>
                                    <i class="bi bi-hourglass-split me-2" v-else-if="!prodForm.processing && isProdPending && !isEditingProd"></i>
                                    <i class="bi bi-arrow-repeat me-2" v-else-if="!prodForm.processing && isEditingProd"></i>
                                    <span v-else-if="prodForm.processing" class="spinner-border spinner-border-sm me-2"></span>

                                    <span v-if="isProdApproved">LOCKED (APPROVED)</span>
                                    <span v-else-if="isProdPending && !isEditingProd">SUBMITTED (AWAITING APPROVAL)</span>
                                    <span v-else-if="isEditingProd">UPDATE PRODUCTION SLIPS</span>
                                    <span v-else-if="isProdRejected">RESUBMIT PRODUCTION SLIPS</span>
                                    <span v-else>SUBMIT PRODUCTION SLIPS</span>
                                </button>

                                <div v-if="Object.keys(prodForm.errors).length > 0" class="alert alert-danger p-2 small mb-2 text-start">
                                    <ul class="mb-0 ps-3">
                                        <li v-for="err in prodForm.errors" :key="err">{{ err }}</li>
                                    </ul>
                                </div>

                                <div class="alert-box small text-center">
                                    <p v-if="isProdApproved" class="text-success fw-bold mb-0">
                                        <i class="bi bi-patch-check-fill me-1"></i> This entry has been verified and locked.
                                    </p>
                                    <p v-else-if="isProdPending && !isEditingProd" class="text-warning fw-bold mb-0">
                                        <i class="bi bi-hourglass-split me-1"></i> Submitted & awaiting supervisor approval. Input fields have been reset to 0.
                                    </p>
                                    <p v-else-if="isEditingProd" class="text-primary fw-bold mb-0">
                                        <i class="bi bi-pencil-square me-1"></i> Editing pending entry. Click Update or Cancel.
                                    </p>
                                    <p v-else-if="isProdRejected" class="text-danger fw-bold mb-0">
                                        <i class="bi bi-exclamation-octagon-fill me-1"></i> Rejected: {{ prodRejectComment }}
                                    </p>
                                    <p v-else class="text-muted mb-0"><i class="bi bi-info-circle me-1"></i> Enter daily output. Slips are submitted together for supervisor approval.</p>
                                </div>
                            </form>
                        </div>

                        <!-- Entry Area B: Standard Single Metric Slip Submission (Non-Production / Other Metrics) -->
                        <div v-else-if="activeMetric" class="entry-stage text-center py-2">
                            <form @submit.prevent="submitSlip(activeMetric.id)" class="mx-auto" style="max-width: 400px;">
                                <div class="metric-visual mb-2 mx-auto shadow-sm">
                                    <i class="bi fs-3 text-primary" :class="metricIcon(activeMetric.label)"></i>
                                </div>
                                <h5 class="fw-bold title-font text-uppercase mb-3 mt-1">{{ activeMetric.label }}</h5>

                                <!-- Today's Submitted Single Metric Entry Summary Card -->
                                <div v-if="hasSingleEntry" class="card border-0 shadow-sm mb-3 rounded-4 overflow-hidden text-start"
                                     :class="{
                                         'bg-warning-subtle border border-warning': isSinglePending,
                                         'bg-success-subtle border border-success': isSingleApproved,
                                         'bg-danger-subtle border border-danger': isSingleRejected
                                     }">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="fw-bold small text-uppercase d-flex align-items-center gap-1"
                                                  :class="{'text-warning-emphasis': isSinglePending, 'text-success': isSingleApproved, 'text-danger': isSingleRejected}">
                                                <i class="bi" :class="{
                                                    'bi-hourglass-split': isSinglePending,
                                                    'bi-patch-check-fill': isSingleApproved,
                                                    'bi-exclamation-octagon-fill': isSingleRejected
                                                }"></i>
                                                <span>{{ isSingleApproved ? 'Verified & Locked Slip' : (isSinglePending ? 'Submitted Entry (Pending Approval)' : 'Entry Rejected') }}</span>
                                            </span>
                                            <span class="badge rounded-pill px-2.5 py-1"
                                                  :class="{
                                                      'bg-warning text-dark': isSinglePending,
                                                      'bg-success text-white': isSingleApproved,
                                                      'bg-danger text-white': isSingleRejected
                                                  }">
                                                {{ currentSlip?.status?.toUpperCase() }}
                                            </span>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center p-2.5 bg-white rounded-3 border my-1">
                                            <div class="text-muted small fw-semibold">{{ activeMetric.label }}</div>
                                            <div class="fw-bold text-dark fs-6">
                                                {{ parseFloat(currentSlip.value) }} <span class="text-muted small">{{ activeMetric.unit }}</span>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top" style="border-color: rgba(0,0,0,0.08) !important;">
                                            <div class="small" style="font-size: 0.75rem;">
                                                <span v-if="isSinglePending && !isEditingSingle" class="text-warning-emphasis">
                                                    <i class="bi bi-info-circle me-1"></i>Input field below reset to 0.
                                                </span>
                                                <span v-else-if="isEditingSingle" class="text-primary fw-bold">
                                                    <i class="bi bi-pencil-fill me-1"></i>Edit mode active.
                                                </span>
                                                <span v-else-if="isSingleApproved" class="text-success">
                                                    <i class="bi bi-shield-check me-1"></i>Slip verified & locked.
                                                </span>
                                                <span v-else-if="isSingleRejected" class="text-danger">
                                                    <i class="bi bi-x-circle me-1"></i>{{ currentSlip?.comment }}
                                                </span>
                                            </div>

                                            <div v-if="isSinglePending && !isEditingSingle">
                                                <button type="button" class="btn btn-sm btn-outline-primary px-3 py-0.5 rounded-pill fw-bold" style="font-size: 0.75rem;" @click="startEditSingle">
                                                    <i class="bi bi-pencil-square me-1"></i>Edit Entry
                                                </button>
                                            </div>
                                            <div v-else-if="isEditingSingle">
                                                <button type="button" class="btn btn-sm btn-outline-secondary px-3 py-0.5 rounded-pill fw-bold" style="font-size: 0.75rem;" @click="cancelEditSingle">
                                                    <i class="bi bi-x-circle me-1"></i>Cancel Edit
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="position-relative mb-3">
                                    <input type="number" step="0.01" min="0"
                                        class="glass-input-big text-center"
                                        :placeholder="isSinglePending && !isEditingSingle ? 'Submitted (Reset to 0)' : 'Enter ' + activeMetric.unit"
                                        :disabled="isSingleApproved || (isSinglePending && !isEditingSingle)"
                                        v-model="form.value"
                                        @input="onValueChange(form.value, activeMetric.id)"
                                        autocomplete="off"
                                        :required="!isSinglePending || isEditingSingle">
                                    
                                    <!-- Points Preview Bubble -->
                                    <div v-if="pointsPreview[activeMetric.id]" class="points-badge fadeIn">
                                        +{{ pointsPreview[activeMetric.id] }} PTS
                                    </div>
                                </div>

                                <button type="submit" class="btn premium-submit-btn w-100 py-2 mb-2" 
                                    :class="{
                                        'btn-secondary opacity-75': (isSinglePending && !isEditingSingle) || isSingleApproved,
                                        'btn-primary': !((isSinglePending && !isEditingSingle) || isSingleApproved)
                                    }"
                                    :disabled="form.processing || isSingleApproved || (isSinglePending && !isEditingSingle)">
                                    <i class="bi bi-send-check me-2" v-if="!form.processing && !isSinglePending && !isSingleApproved && !isEditingSingle"></i>
                                    <i class="bi bi-lock-fill me-2" v-else-if="!form.processing && isSingleApproved"></i>
                                    <i class="bi bi-hourglass-split me-2" v-else-if="!form.processing && isSinglePending && !isEditingSingle"></i>
                                    <i class="bi bi-arrow-repeat me-2" v-else-if="!form.processing && isEditingSingle"></i>
                                    <span v-else-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>

                                    <span v-if="isSingleApproved">LOCKED (APPROVED)</span>
                                    <span v-else-if="isSinglePending && !isEditingSingle">SUBMITTED (AWAITING APPROVAL)</span>
                                    <span v-else-if="isEditingSingle">UPDATE SLIP</span>
                                    <span v-else-if="isSingleRejected">RESUBMIT SLIP</span>
                                    <span v-else>SUBMIT</span>
                                </button>

                                <div v-if="Object.keys(form.errors).length > 0" class="alert alert-danger p-2 small mb-2 text-start">
                                    <ul class="mb-0 ps-3">
                                        <li v-for="err in form.errors" :key="err">{{ err }}</li>
                                    </ul>
                                </div>

                                <div class="alert-box small">
                                    <p v-if="isSingleApproved" class="text-success fw-bold mb-0">
                                        <i class="bi bi-patch-check-fill me-1"></i> This entry has been verified and locked.
                                    </p>
                                    <p v-else-if="isSinglePending && !isEditingSingle" class="text-warning fw-bold mb-0">
                                        <i class="bi bi-hourglass-split me-1"></i> Submitted & awaiting supervisor approval. Input field has been reset to 0.
                                    </p>
                                    <p v-else-if="isEditingSingle" class="text-primary fw-bold mb-0">
                                        <i class="bi bi-pencil-square me-1"></i> Editing pending entry. Click Update or Cancel.
                                    </p>
                                    <p v-else-if="isSingleRejected" class="text-danger fw-bold mb-0">
                                        <i class="bi bi-exclamation-octagon-fill me-1"></i> Rejected: {{ currentSlip?.comment }}
                                    </p>
                                    <p v-else class="text-muted mb-0"><i class="bi bi-info-circle me-1"></i> Only one submission allowed per day.</p>
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
                                                         :title="m.label + ': ' + row[m.key] + ' ' + (m.unit || '') + ' (' + (row[m.key + '_status'] || 'pending') + ')'">
                                                        <i class="bi me-1" :class="metricIcon(m.label)"></i>
                                                        <span class="me-1 fw-bold opacity-75" style="font-size: 0.55rem;">{{ metricAbbr(m) }}:</span>
                                                        {{ formatValue(row[m.key]) }}
                                                    </div>
                                                </template>
                                            </div>
                                        </td>
                                        <td class="text-end pe-2">
                                            <span class="badge bg-primary rounded-pill px-2 py-1" style="font-size: 0.7rem;">{{ parseFloat(row.total_points).toFixed(2).replace(/\.00$/, '') }}</span>
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

.glass-input-field {
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px 0 0 10px;
    font-size: 1.1rem;
    font-weight: 700;
    transition: all 0.2s;
    font-family: 'Outfit', sans-serif;
}
.glass-input-field:focus {
    background: #fff;
    border-color: #0d6efd;
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
    outline: none;
}

.production-fields-card {
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
}

.shadow-xs {
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
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
