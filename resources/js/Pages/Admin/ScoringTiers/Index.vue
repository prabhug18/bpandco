<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Alert from '@/Utils/Alert';

const props = defineProps({
    roles: Array,
    metrics: Array
});

const visibleMetrics = computed(() => {
    return props.metrics.filter(m => !['duty_time', 'late'].includes(m.key));
});

const activeRole = ref(props.roles.length > 0 ? props.roles[0].id : null);
const activeTab = ref(null);

watch(activeRole, (newRoleId) => {
    const role = props.roles.find(r => r.id === newRoleId);
    if (visibleMetrics.value.length > 0) {
        activeTab.value = visibleMetrics.value[0].id;
    } else {
        activeTab.value = null;
    }
}, { immediate: true });

const activeRoleName = computed(() => {
    return props.roles.find(r => r.id === activeRole.value)?.name || '';
});

const currentDailyTiers = computed(() => {
    if (!activeTab.value || !activeRole.value) return [];
    const metric = props.metrics.find(m => m.id === activeTab.value);
    if (!metric || !metric.daily_scoring_tiers) return [];
    return metric.daily_scoring_tiers.filter(t => t.role_id === activeRole.value);
});

const currentPeriodTargets = computed(() => {
    if (!activeTab.value || !activeRole.value) return [];
    const metric = props.metrics.find(m => m.id === activeTab.value);
    if (!metric || !metric.period_targets) return [];
    return metric.period_targets.filter(t => t.role_id === activeRole.value);
});

const dailyForm = useForm({
    role_id: '',
    min_value: '',
    daily_points: '',
    tier_label: 'green'
});

const periodForm = useForm({
    role_id: '',
    period_type: 'monthly',
    tier_label: 'green',
    min_value: '',
    points_awarded: ''
});

const addDailyTier = (metricId) => {
    dailyForm.role_id = activeRole.value;
    dailyForm.post(route('admin.scoring-tiers.daily.store', metricId), {
        preserveScroll: true,
        onSuccess: () => dailyForm.reset(),
    });
};

const deleteDailyTier = async (tierId) => {
    if(await Alert.confirm('Delete this daily tier?', 'This action cannot be undone.')) {
        useForm({}).delete(route('admin.scoring-tiers.daily.destroy', tierId), { preserveScroll: true });
    }
};

const addPeriodTarget = (metricId) => {
    periodForm.role_id = activeRole.value;
    periodForm.post(route('admin.scoring-tiers.period.store', metricId), {
        preserveScroll: true,
        onSuccess: () => periodForm.reset('min_value', 'points_awarded'),
    });
};

const deletePeriodTarget = async (targetId) => {
    if(await Alert.confirm('Delete this period target?', 'This action cannot be undone.')) {
        useForm({}).delete(route('admin.scoring-tiers.period.destroy', targetId), { preserveScroll: true });
    }
};

const getMetricName = (id) => {
    return props.metrics.find(m => m.id === id)?.label || '';
};

const getScoringType = (id) => {
    const type = props.metrics.find(m => m.id === id)?.scoring_type || '';
    return type === '10_20_30_days' ? 'Cumulative (10/20/30 Days)' : 'Flat Monthly Target';
};

</script>

<template>
    <Head title="Scoring Tiers Setup" />

    <AuthenticatedLayout>
        <!-- Premium Header -->
        <div class="header-banner mb-4">
            <h4 class="mb-0 banner-title">Scoring Tiers & Targets Configuration</h4>
        </div>

        <div class="container-fluid mb-5 d-flex flex-column flex-md-row gap-4">
            <!-- Sidebar list of Roles -->
            <div class="premium-card flex-shrink-0 side-card" style="width: 250px; height: fit-content;">
                <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4">
                    <h6 class="fw-bold text-uppercase text-muted" style="letter-spacing: 1px;">Select Role</h6>
                </div>
                <div class="list-group list-group-flush rounded-bottom pb-3 px-2 border-0">
                    <button v-for="role in roles" :key="role.id"
                        class="list-group-item list-group-item-action border-0 rounded mb-1 px-3 py-2 fw-semibold text-capitalize d-flex justify-content-between align-items-center"
                        :class="{'active-tab shadow-sm': activeRole === role.id}"
                        style="transition: all 0.2s; font-family: 'Inter', sans-serif;"
                        @click="activeRole = role.id">
                        {{ role.name }}
                        <i v-if="activeRole === role.id" class="bi bi-chevron-right fs-6"></i>
                    </button>
                </div>
            </div>

            <!-- Configuration Area -->
            <div class="flex-grow-1" v-if="activeRole && activeTab" style="min-width: 0;">
                <div class="premium-card p-4 mb-4">
                    <!-- Metric Tabs Navigation -->
                    <ul class="nav nav-pills mb-4 gap-2 border-bottom pb-3" style="flex-wrap: wrap;">
                        <li class="nav-item" v-for="metric in visibleMetrics" :key="metric.id">
                            <button class="nav-link fw-bold px-4 rounded-pill" 
                                :class="{'active bg-primary text-white shadow-sm': activeTab === metric.id, 'bg-light text-dark': activeTab !== metric.id}"
                                @click="activeTab = metric.id">
                                {{ metric.label }}
                                <span v-if="!metric.is_active" class="badge bg-danger rounded-pill ms-1" style="font-size:0.6rem;">Inactive</span>
                            </button>
                        </li>
                    </ul>

                    <h5 class="fw-bold text-primary mb-3 title-font d-flex align-items-center">
                        <span class="text-dark text-capitalize me-2">Role: {{ activeRoleName }}</span> 
                        <i class="bi bi-arrow-right mx-2 text-muted"></i> 
                        Metric: {{ getMetricName(activeTab) }}
                        <span class="badge bg-light text-dark border ms-3 fw-normal fs-6 shadow-sm">{{ getScoringType(activeTab) }}</span>
                    </h5>
                    
                    <div class="alert alert-primary bg-primary text-white border-0 shadow-sm rounded-3 py-3 px-4 d-flex align-items-center gap-3">
                        <i class="bi bi-info-circle fs-3"></i>
                        <div>
                            <strong class="d-block mb-1 font-monospace">Rule Logic:</strong> 
                            <span v-if="getScoringType(activeTab).includes('Flat')">This metric requires ONLY a total flat monthly sum to hit its Green/Yellow/Red tiers.</span>
                            <span v-else>This metric gives you fixed Daily Points, and evaluates cumulative targets across 10-day, 20-day, and 30-day periods. The values entered here generate the <b>[Value-Points]</b> legends dynamically in the performance reports.</span>
                        </div>
                    </div>

                    <div class="row pt-2">
                        <!-- Daily Tiers (Left Side) -->
                        <div class="col-md-6 border-end pe-4" v-if="getScoringType(activeTab).includes('Cumulative')">
                            <h5 class="fw-bold text-dark mb-4 title-font"><i class="bi bi-calendar-day me-2 text-primary"></i>Daily Limits (Base Points)</h5>
                            
                            <form @submit.prevent="addDailyTier(activeTab)" class="row g-2 align-items-end mb-4 bg-light p-3 rounded shadow-sm border">
                                <div class="col-sm-3">
                                    <label class="small text-muted fw-bold mb-1">Light</label>
                                    <select class="form-select glass-input px-2" v-model="dailyForm.tier_label">
                                        <option value="green">Green</option>
                                        <option value="yellow">Yellow</option>
                                        <option value="red">Red</option>
                                        <option value="grey">Grey</option>
                                    </select>
                                </div>
                                <div class="col-sm-3">
                                    <label class="small text-muted fw-bold mb-1">Min Value</label>
                                    <input type="number" step="0.01" class="form-control glass-input" v-model="dailyForm.min_value" required placeholder="e.g. 5000">
                                </div>
                                <div class="col-sm-3">
                                    <label class="small text-muted fw-bold mb-1">Base Points</label>
                                    <input type="number" step="0.01" class="form-control glass-input" v-model="dailyForm.daily_points" required placeholder="e.g. 1">
                                </div>
                                <div class="col-sm-3">
                                    <button class="btn btn-primary glass-btn w-100 py-2">Add</button>
                                </div>
                            </form>

                            <ul class="list-group list-group-flush fs-6 mt-3">
                                <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent border-bottom px-0 py-3"
                                    v-for="dt in currentDailyTiers" :key="dt.id">
                                    <div class="d-flex align-items-center">
                                        <span class="badge shadow-sm px-2 py-1 rounded-pill me-2" 
                                              :class="{
                                                'bg-success': dt.tier_label === 'green',
                                                'bg-warning text-dark': dt.tier_label === 'yellow',
                                                'bg-danger': dt.tier_label === 'red',
                                                'bg-secondary': dt.tier_label === 'grey'
                                              }" style="font-size: 0.6rem;">&nbsp;</span>
                                        <span class="font-monospace text-dark">
                                            Value <span class="text-primary fw-bold">&ge; {{ dt.min_value }}</span> = <span class="badge bg-success shadow-sm fs-6 px-3">{{ dt.daily_points }} pts</span>
                                        </span>
                                    </div>
                                    <button class="btn btn-sm btn-outline-danger rounded-pill px-3" @click="deleteDailyTier(dt.id)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </li>
                                <li class="list-group-item text-muted text-center py-4 border-0" v-if="currentDailyTiers.length === 0">
                                    <small><i class="bi bi-box me-1"></i> No daily conditions set for this role.</small>
                                </li>
                            </ul>
                        </div>

                        <!-- Period Targets (Right Side) -->
                        <div class="col-md-6 ps-4" :class="{'col-md-12 ps-0 border-0': getScoringType(activeTab).includes('Flat')}">
                            <h5 class="fw-bold text-dark mb-4 title-font"><i class="bi bi-flag-fill me-2 text-danger"></i>Period Targets (Green/Yellow/Red)</h5>
                            
                            <form @submit.prevent="addPeriodTarget(activeTab)" class="row g-2 align-items-end mb-4 bg-light p-3 rounded shadow-sm border">
                                <div class="col-sm-3" v-if="getScoringType(activeTab).includes('Cumulative')">
                                    <label class="small text-muted fw-bold mb-1">Period</label>
                                    <select class="form-select glass-input px-2" v-model="periodForm.period_type">
                                        <option value="10_days">10 Days</option>
                                        <option value="20_days">20 Days</option>
                                        <option value="30_days">30 Days</option>
                                    </select>
                                </div>
                                <div class="col-sm-3" v-else>
                                    <label class="small text-muted fw-bold mb-1">Period</label>
                                    <select class="form-select glass-input px-2" v-model="periodForm.period_type">
                                        <option value="monthly">Monthly</option>
                                    </select>
                                </div>

                                <div class="col-sm-3">
                                    <label class="small text-muted fw-bold mb-1">Light</label>
                                    <select class="form-select glass-input px-2" v-model="periodForm.tier_label">
                                        <option value="green">Green</option>
                                        <option value="yellow">Yellow</option>
                                        <option value="red">Red</option>
                                        <option value="grey">Grey</option>
                                    </select>
                                </div>
                                <div class="col-sm-3">
                                    <label class="small text-muted fw-bold mb-1">Min Value</label>
                                    <input type="number" step="0.01" class="form-control glass-input px-2" v-model="periodForm.min_value" required placeholder="Ex: 50000">
                                </div>
                                <div class="col-sm-3">
                                    <label class="small text-muted fw-bold mb-1">Total Marks</label>
                                    <input type="number" step="0.01" class="form-control glass-input px-2" v-model="periodForm.points_awarded" required placeholder="Ex: 10">
                                </div>
                                <div class="col-12 mt-3">
                                    <button class="btn btn-primary glass-btn w-100 py-2">Create Target</button>
                                </div>
                            </form>

                            <ul class="list-group list-group-flush fs-6 mt-3">
                                <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent border-bottom px-0 py-3"
                                    v-for="pt in currentPeriodTargets" :key="pt.id">
                                    <span>
                                        <span class="badge shadow-sm px-3 py-2 rounded-pill me-2" 
                                              :class="{
                                                'bg-success': pt.tier_label === 'green',
                                                'bg-warning text-dark': pt.tier_label === 'yellow',
                                                'bg-danger': pt.tier_label === 'red',
                                                'bg-secondary': pt.tier_label === 'grey'
                                              }">{{ pt.tier_label.toUpperCase() }}</span>
                                        <strong class="text-muted font-monospace me-2">[{{ pt.period_type.replace('_', ' ').toUpperCase() }}]</strong>
                                        <span class="text-dark">Val: <strong class="text-primary">{{ pt.min_value }}</strong> &rarr; <span class="badge bg-dark fs-6">{{ pt.points_awarded }} pts</span></span>
                                    </span>
                                    <button class="btn btn-sm btn-outline-danger rounded-pill px-3" @click="deletePeriodTarget(pt.id)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </li>
                                <li class="list-group-item text-muted text-center py-4 border-0" v-if="currentPeriodTargets.length === 0">
                                    <small><i class="bi bi-flag me-1"></i> No targets set for this role.</small>
                                </li>
                            </ul>
                        </div>
                    </div>

                </div>
            </div>
            
            <div v-else class="text-center text-muted col pt-5">
                <i class="bi bi-arrow-left-circle fs-1 mb-3 d-block text-primary" style="opacity: 0.5;"></i>
                <h5 class="title-font text-muted">Select a role and metric to configure targets</h5>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap');

.header-banner {
    background: linear-gradient(135deg, rgba(0, 50, 135, 0.05) 0%, rgba(0, 50, 135, 0.1) 100%);
    padding: 18px 30px;
    border-bottom: 1px solid rgba(0, 50, 135, 0.1);
    box-shadow: 0 4px 15px rgba(0,0,0,0.02);
}

.banner-title, .title-font {
    font-family: 'Outfit', sans-serif;
    color: #003287;
}
.banner-title {
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    font-size: 1.4rem;
}

.premium-card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.04);
}

.glass-input {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    padding: 10px 15px;
    font-family: 'Inter', sans-serif;
    transition: all 0.3s;
}

.glass-input:focus {
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
    border-color: #0d6efd;
    outline: none;
}

.glass-btn {
    border: none;
    border-radius: 8px;
    transition: transform 0.2s, box-shadow 0.2s;
    font-family: 'Inter', sans-serif;
    font-weight: 600;
}

.glass-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15) !important;
}

.active-tab {
    background-color: #0d6efd !important;
    color: white !important;
}

.list-group-item:hover:not(.active-tab) {
    background-color: rgba(13, 110, 253, 0.05);
}
</style>
