<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Alert from '@/Utils/Alert';

const props = defineProps({
    incentives: Array,
    increments: Array,
    roles: Array,
});

const activeTab = ref('incentives');

const incentiveForm = useForm({
    consecutive_green_months: '',
    min_score: '',
    max_score: '',
    incentive_amount: '',
    role_id: '',
});

const incrementForm = useForm({
    green_months_required: '',
    increment_percentage: '',
    role_id: '',
});

const submitIncentive = () => {
    incentiveForm.post(route('admin.incentives.store'), {
        preserveScroll: true,
        onSuccess: () => incentiveForm.reset(),
    });
};

const submitIncrement = () => {
    incrementForm.post(route('admin.increments.store'), {
        preserveScroll: true,
        onSuccess: () => incrementForm.reset(),
    });
};

const deleteIncentive = async (id) => {
    if(await Alert.confirm('Delete Incentive Slab?', 'Are you sure you want to remove this incentive condition?')) {
        useForm({}).delete(route('admin.incentives.destroy', id), { preserveScroll: true });
    }
};

const deleteIncrement = async (id) => {
    if(await Alert.confirm('Delete Increment Slab?', 'Are you sure you want to remove this annual increment rate?')) {
        useForm({}).delete(route('admin.increments.destroy', id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Incentives & Increments" />
    <AuthenticatedLayout>
        <!-- Premium Header -->
        <div class="header-banner mb-4">
            <h4 class="mb-0 banner-title">Incentives & Increments Profile</h4>
        </div>

        <div class="container-fluid mt-4 mb-5">
            <div class="premium-card bg-white mb-4 overflow-hidden">
                <div class="bg-transparent border-bottom p-0">
                    <ul class="nav nav-tabs px-3 pt-3 border-0 d-flex gap-2">
                        <li class="nav-item">
                            <button class="nav-link px-4 py-3 fw-bold rounded-top" 
                                :class="activeTab === 'incentives' ? 'active-tab shadow-sm' : 'text-muted hover-tab'" 
                                @click="activeTab = 'incentives'" style="border: none; transition: all 0.3s;">
                                <i class="bi bi-gift-fill me-2"></i> Incentive Slabs
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link px-4 py-3 fw-bold rounded-top" 
                                :class="activeTab === 'increments' ? 'active-tab shadow-sm' : 'text-muted hover-tab'" 
                                @click="activeTab = 'increments'" style="border: none; transition: all 0.3s;">
                                <i class="bi bi-graph-up-arrow me-2"></i> Annual Increments
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="p-4" style="background: rgba(255,255,255,0.98);">
                    <!-- Incentives Tab -->
                    <div v-if="activeTab === 'incentives'">
                        <div class="alert alert-primary bg-primary text-white border-0 shadow-sm rounded-3 py-2 px-3 d-flex align-items-center gap-2 mb-4">
                            <i class="bi bi-info-circle-fill fs-4"></i>
                            <span class="small font-monospace">Incentives are cash bonuses rewarded when employees sustain 'Green' traffic light statuses for consecutive months.</span>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <h5 class="fw-bold mb-3 title-font text-dark"><i class="bi bi-plus-circle text-primary me-2"></i>Add Slab</h5>
                                <form @submit.prevent="submitIncentive" class="bg-light p-4 rounded shadow-sm border border-light">
                                    <div class="mb-3">
                                        <label class="small fw-bold text-muted mb-1">Consecutive Green Months</label>
                                        <input type="number" class="form-control glass-input" v-model="incentiveForm.consecutive_green_months" placeholder="e.g. 2" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="small fw-bold text-muted mb-1">Min Score (Qualifying Mark)</label>
                                        <input type="number" step="0.01" class="form-control glass-input" v-model="incentiveForm.min_score" placeholder="e.g. 70" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="small fw-bold text-muted mb-1">Incentive Reward (₹)</label>
                                        <input type="number" class="form-control glass-input" v-model="incentiveForm.incentive_amount" placeholder="e.g. 4000" required>
                                    </div>
                                    <div class="mb-4">
                                        <label class="small fw-bold text-muted mb-1">Target Role (Optional)</label>
                                        <select class="form-select glass-input px-2" v-model="incentiveForm.role_id">
                                            <option value="">Global (All Roles)</option>
                                            <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                                        </select>
                                    </div>
                                    <button class="btn btn-primary glass-btn w-100 py-2 fs-6">Create Slab</button>
                                </form>
                            </div>
                            <div class="col-md-8">
                                <h5 class="fw-bold mb-3 title-font text-dark">Active Slabs</h5>
                                <div class="table-responsive rounded shadow-sm border-0 bg-white">
                                    <table class="table table-hover align-middle custom-table mb-0">
                                        <thead>
                                            <tr>
                                                <th class="ps-4">Sustained Duration</th>
                                                <th>Minimum Score</th>
                                                <th>Reward Amount</th>
                                                <th>Target Area</th>
                                                <th class="pe-4 text-end">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="inc in incentives" :key="inc.id" class="table-row-hover">
                                                <td class="ps-4 fw-bold font-monospace text-primary">{{ inc.consecutive_green_months }} MONTHS</td>
                                                <td class="text-secondary fw-semibold">{{ inc.min_score }} pts</td>
                                                <td><span class="badge bg-success shadow-sm px-3 py-2 fs-6 rounded-pill">₹{{ inc.incentive_amount }}</span></td>
                                                <td>
                                                    <span v-if="inc.role" class="badge bg-light text-dark border">{{ inc.role.name }}</span>
                                                    <span v-else class="badge bg-primary px-3 py-2 text-white border-0 shadow-sm"><i class="bi bi-globe me-1"></i>GLOBAL</span>
                                                </td>
                                                <td class="pe-4 text-end">
                                                    <button class="btn btn-sm btn-outline-danger rounded-pill px-3 shadow-sm" @click="deleteIncentive(inc.id)"><i class="bi bi-trash"></i></button>
                                                </td>
                                            </tr>
                                            <tr v-if="incentives.length === 0">
                                                <td colspan="5" class="text-center py-5 text-muted fw-bold">No incentive slabs set.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Increments Tab -->
                    <div v-if="activeTab === 'increments'">
                        <div class="alert alert-primary bg-primary text-white border-0 shadow-sm rounded-3 py-2 px-3 d-flex align-items-center gap-2 mb-4">
                            <i class="bi bi-info-circle-fill fs-4"></i>
                            <span class="small font-monospace">Salary Increments are percentages added to basic pay if employees attain specific numbers of Green months annually.</span>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <h5 class="fw-bold mb-3 title-font text-dark"><i class="bi bi-plus-circle text-primary me-2"></i>Add Annual Rate</h5>
                                <form @submit.prevent="submitIncrement" class="bg-light p-4 rounded shadow-sm border border-light">
                                    <div class="mb-3">
                                        <label class="small fw-bold text-muted mb-1">Green Months Achieved (Out of 12)</label>
                                        <input type="number" class="form-control glass-input" v-model="incrementForm.green_months_required" placeholder="e.g. 9" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="small fw-bold text-muted mb-1">Salary Increment %</label>
                                        <input type="number" step="0.01" class="form-control glass-input" v-model="incrementForm.increment_percentage" placeholder="e.g. 15" required>
                                    </div>
                                    <div class="mb-4">
                                        <label class="small fw-bold text-muted mb-1">Target Role (Optional)</label>
                                        <select class="form-select glass-input px-2" v-model="incrementForm.role_id">
                                            <option value="">Global (All Roles)</option>
                                            <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                                        </select>
                                    </div>
                                    <button class="btn btn-primary glass-btn w-100 py-2 fs-6">Create Rate</button>
                                </form>
                            </div>
                            <div class="col-md-8">
                                <h5 class="fw-bold mb-3 title-font text-dark">Active Increment Rates</h5>
                                <div class="table-responsive rounded shadow-sm border-0 bg-white">
                                    <table class="table table-hover align-middle custom-table mb-0">
                                        <thead>
                                            <tr>
                                                <th class="ps-4">Required Consistency</th>
                                                <th>Percentage Gain</th>
                                                <th>Target Area</th>
                                                <th class="pe-4 text-end">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="inc in increments" :key="inc.id" class="table-row-hover">
                                                <td class="ps-4 fw-bold font-monospace text-primary">{{ inc.green_months_required }} / 12 MONTHS</td>
                                                <td><span class="badge bg-danger shadow-sm px-3 py-2 fs-6 rounded-pill"><i class="bi bi-arrow-up-right me-1"></i>{{ inc.increment_percentage }}%</span></td>
                                                <td>
                                                    <span v-if="inc.role" class="badge bg-light text-dark border">{{ inc.role.name }}</span>
                                                    <span v-else class="badge bg-primary px-3 py-2 text-white border-0 shadow-sm"><i class="bi bi-globe me-1"></i>GLOBAL</span>
                                                </td>
                                                <td class="pe-4 text-end">
                                                    <button class="btn btn-sm btn-outline-danger rounded-pill px-3 shadow-sm" @click="deleteIncrement(inc.id)"><i class="bi bi-trash"></i></button>
                                                </td>
                                            </tr>
                                            <tr v-if="increments.length === 0">
                                                <td colspan="4" class="text-center py-5 text-muted fw-bold">No increment rates set.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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

.active-tab {
    background-color: #0d6efd !important;
    color: white !important;
}

.hover-tab:hover {
    background-color: rgba(13, 110, 253, 0.05);
}

.glass-input {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 8px;
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
    box-shadow: 0 6px 15px rgba(13, 110, 253, 0.2) !important;
}

.custom-table {
    font-family: 'Inter', sans-serif;
    background: white;
}

.custom-table thead {
    background: #f8f9fa;
    border-bottom: 2px solid #e9ecef;
}

.custom-table th {
    font-family: 'Outfit', sans-serif;
    color: #495057;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    padding: 15px 10px;
    border: none;
}

.table-row-hover:hover {
    background-color: #f8f9ff !important;
    transform: scale(1.001);
    transition: all 0.2s;
}
</style>
