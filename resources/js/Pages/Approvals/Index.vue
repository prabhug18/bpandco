<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Alert from '@/Utils/Alert';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    pendingSlips: Array,
    pendingAttendance: Array,
    allEmployees: Array, // Full staff list
});

const activeTab = ref('slips');
const selectedEmployee = ref('');

const showImageModal = ref(false);
const selectedImage = ref('');

const viewImage = (path) => {
    selectedImage.value = `/storage/${path}`;
    showImageModal.value = true;
};

// Filter items
const filteredSlips = computed(() => {
    if (!selectedEmployee.value) return props.pendingSlips;
    return props.pendingSlips.filter(s => s.user.id == selectedEmployee.value);
});

const filteredAttendance = computed(() => {
    if (!selectedEmployee.value) return props.pendingAttendance;
    return props.pendingAttendance.filter(a => a.user.id == selectedEmployee.value);
});

// Single Selection Action logic
const approvalForm = useForm({
    comment: '',
});

const approveSlip = (slip) => {
    approvalForm.patch(route('approvals.slips.approve', slip.id), { preserveScroll: true, onSuccess: () => approvalForm.reset() });
};

const rejectSlip = (slip) => {
    if (!approvalForm.comment) {
        Alert.warning('Comment Required', 'Please provide a reason in the rejection comment box.');
        return;
    }
    approvalForm.patch(route('approvals.slips.reject', slip.id), { preserveScroll: true, onSuccess: () => approvalForm.reset() });
};

const attRejectionForm = useForm({
    rejection_reason: '',
});

const approveAttendance = (attendance) => {
    useForm({}).patch(route('approvals.attendance.approve', attendance.id), { preserveScroll: true });
};

const rejectAttendance = (attendance) => {
    if (!attRejectionForm.rejection_reason) {
        Alert.warning('Reason Required', 'Please provide a rejection reason in the comment box.');
        return;
    }
    attRejectionForm.patch(route('approvals.attendance.reject', attendance.id), { preserveScroll: true, onSuccess: () => attRejectionForm.reset() });
};

// Bulk Actions Logic
const selectedSlips = ref([]);
const selectedAttendanceIds = ref([]);
const bulkSlipsForm = useForm({ ids: [], comment: '' });
const bulkAttForm = useForm({ ids: [], rejection_reason: '' });

const selectAllSlips = computed({
    get: () => filteredSlips.value.length > 0 && selectedSlips.value.length === filteredSlips.value.length,
    set: (val) => {
        selectedSlips.value = val ? filteredSlips.value.map(s => s.id) : [];
    }
});

const selectAllAttendance = computed({
    get: () => filteredAttendance.value.length > 0 && selectedAttendanceIds.value.length === filteredAttendance.value.length,
    set: (val) => {
        selectedAttendanceIds.value = val ? filteredAttendance.value.map(a => a.id) : [];
    }
});

const bulkApproveSlips = () => {
    if (!selectedSlips.value.length) return Alert.error('Selection Required', 'No slips selected');
    bulkSlipsForm.ids = selectedSlips.value;
    bulkSlipsForm.post(route('approvals.slips.bulk-approve'), { preserveScroll: true, onSuccess: () => { selectedSlips.value = []; bulkSlipsForm.reset(); } });
};

const bulkRejectSlips = () => {
    if (!selectedSlips.value.length) return Alert.error('Selection Required', 'No slips selected');
    if (!bulkSlipsForm.comment) return Alert.warning('Comment Required', 'Please provide a rejection comment.');
    bulkSlipsForm.ids = selectedSlips.value;
    bulkSlipsForm.post(route('approvals.slips.bulk-reject'), { preserveScroll: true, onSuccess: () => { selectedSlips.value = []; bulkSlipsForm.reset(); } });
};

const bulkApproveAtt = () => {
    if (!selectedAttendanceIds.value.length) return Alert.error('Selection Required', 'No attendance selected');
    bulkAttForm.ids = selectedAttendanceIds.value;
    bulkAttForm.post(route('approvals.attendance.bulk-approve'), { preserveScroll: true, onSuccess: () => { selectedAttendanceIds.value = []; bulkAttForm.reset(); } });
};

const bulkRejectAtt = () => {
    if (!selectedAttendanceIds.value.length) return Alert.error('Selection Required', 'No attendance selected');
    if (!bulkAttForm.rejection_reason) return Alert.warning('Reason Required', 'Please provide a rejection reason.');
    bulkAttForm.ids = selectedAttendanceIds.value;
    bulkAttForm.post(route('approvals.attendance.bulk-reject'), { preserveScroll: true, onSuccess: () => { selectedAttendanceIds.value = []; bulkAttForm.reset(); } });
};
</script>

<template>
    <Head title="Approvals Queue" />
    <AuthenticatedLayout>
        <!-- Premium Header -->
        <div class="header-banner">
            <h4 class="mb-0 banner-title">Approval Queue</h4>
        </div>

        <div class="container-fluid mt-4 mb-5">

            <div class="premium-card mb-4 p-3 d-flex flex-column flex-md-row align-items-center gap-2 gap-md-3 w-auto mx-auto shadow-sm border-0">
                <label class="fw-bold m-0 px-2 text-primary small text-uppercase letter-spacing-1">Filter by Employee:</label>
                <select class="form-select glass-input shadow-none border w-100 w-md-auto" v-model="selectedEmployee">
                    <option value="">All Employees</option>
                    <option v-for="user in allEmployees" :key="user.id" :value="user.id">{{ user.name }}</option>
                </select>
            </div>

            <div class="premium-card border-0">
                <div class="border-bottom p-0">
                    <ul class="nav nav-tabs px-3 pt-3 border-0 gap-2">
                        <li class="nav-item">
                            <button class="nav-link px-4 fw-bold action-tab" :class="{'active tab-active': activeTab === 'slips'}" @click="activeTab = 'slips'">
                                Pending Slips <span class="badge bg-primary ms-1 rounded-pill">{{ filteredSlips.length }}</span>
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link px-4 fw-bold action-tab" :class="{'active tab-active': activeTab === 'attendance'}" @click="activeTab = 'attendance'">
                                Attendance <span class="badge bg-primary ms-1 rounded-pill">{{ filteredAttendance.length }}</span>
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="card-body p-4">
                    <!-- Slips Tab -->
                    <div v-if="activeTab === 'slips'">
                        <div class="bulk-actions-wrapper mb-4 bg-light p-3 rounded-4 shadow-sm border">
                            <div class="row g-3 align-items-center">
                                <div class="col-12 col-lg-6">
                                    <div class="d-flex flex-column">
                                        <input type="text" class="form-control glass-input text-dark border shadow-none" placeholder="Rejection Comment for Single/Bulk..." v-model="approvalForm.comment" @input="bulkSlipsForm.comment = $event.target.value">
                                        <small class="text-danger italic mt-1" v-if="!approvalForm.comment"><i class="bi bi-info-circle me-1"></i>Required for rejection</small>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <div class="d-flex gap-2 flex-wrap flex-sm-nowrap">
                                        <button class="btn glass-btn bg-success text-white flex-grow-1 px-4 fw-bold shadow-sm" @click="bulkApproveSlips" :disabled="!selectedSlips.length">
                                            <i class="bi bi-check-all me-1"></i> Approve ({{ selectedSlips.length }})
                                        </button>
                                        <button class="btn glass-btn bg-danger text-white flex-grow-1 px-4 fw-bold shadow-sm" @click="bulkRejectSlips" :disabled="!selectedSlips.length">
                                            <i class="bi bi-x-circle me-1"></i> Reject ({{ selectedSlips.length }})
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive rounded shadow-sm">
                            <table class="table table-hover align-middle mb-0 custom-table">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 50px;">
                                            <input class="form-check-input" type="checkbox" v-model="selectAllSlips">
                                        </th>
                                        <th>Employee</th>
                                        <th>Date</th>
                                        <th>Metric</th>
                                        <th>Value</th>
                                        <th>Points Preview</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="slip in filteredSlips" :key="slip.id" class="table-row-hover">
                                        <td class="text-center">
                                            <input class="form-check-input" type="checkbox" :value="slip.id" v-model="selectedSlips">
                                        </td>
                                        <td class="fw-bold">{{ slip.user.name }}</td>
                                        <td class="text-secondary fw-semibold">{{ new Date(slip.date).toLocaleDateString() }}</td>
                                        <td>{{ slip.metric.label }}</td>
                                        <td><span class="badge bg-light text-dark fw-bold border p-2">{{ slip.value }} {{ slip.metric.unit }}</span></td>
                                        <td><span class="text-primary fw-bold" style="font-size: 1.1em;">+ {{ slip.daily_points_earned }} pts</span></td>
                                        <td class="text-end pe-4">
                                            <div class="btn-group shadow-sm rounded-pill overflow-hidden">
                                                <button class="btn btn-sm btn-success px-3 fw-bold" @click="approveSlip(slip)">Approve</button>
                                                <button class="btn btn-sm btn-danger px-3 fw-bold" @click="rejectSlip(slip)">Reject</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredSlips.length === 0">
                                        <td colspan="7" class="text-center py-5 text-muted fw-bold">No pending slips for approval.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Attendance Tab -->
                    <div v-if="activeTab === 'attendance'">
                        <div class="bulk-actions-wrapper mb-4 bg-light p-3 rounded-4 shadow-sm border">
                            <div class="row g-3 align-items-center">
                                <div class="col-12 col-lg-6">
                                    <div class="d-flex flex-column">
                                        <input type="text" class="form-control glass-input text-dark border shadow-none" placeholder="Rejection Reason for Single/Bulk..." v-model="attRejectionForm.rejection_reason" @input="bulkAttForm.rejection_reason = $event.target.value">
                                        <small class="text-danger italic mt-1" v-if="!attRejectionForm.rejection_reason"><i class="bi bi-info-circle me-1"></i>Required for rejection</small>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <div class="d-flex gap-2 flex-wrap flex-sm-nowrap">
                                        <button class="btn glass-btn bg-success text-white flex-grow-1 px-4 fw-bold shadow-sm" @click="bulkApproveAtt" :disabled="!selectedAttendanceIds.length">
                                            <i class="bi bi-check-all me-1"></i> Approve ({{ selectedAttendanceIds.length }})
                                        </button>
                                        <button class="btn glass-btn bg-danger text-white flex-grow-1 px-4 fw-bold shadow-sm" @click="bulkRejectAtt" :disabled="!selectedAttendanceIds.length">
                                            <i class="bi bi-x-circle me-1"></i> Reject ({{ selectedAttendanceIds.length }})
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive rounded shadow-sm">
                            <table class="table table-hover align-middle mb-0 custom-table">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 50px;">
                                            <input class="form-check-input" type="checkbox" v-model="selectAllAttendance">
                                        </th>
                                        <th>Employee</th>
                                        <th>Date</th>
                                        <th>Check-in</th>
                                        <th>Auto Status</th>
                                        <th>Photo</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="att in filteredAttendance" :key="att.id" class="table-row-hover">
                                        <td class="text-center">
                                            <input class="form-check-input" type="checkbox" :value="att.id" v-model="selectedAttendanceIds">
                                        </td>
                                        <td class="fw-bold">{{ att.user.name }}</td>
                                        <td class="text-secondary fw-semibold">{{ new Date(att.date).toLocaleDateString() }}</td>
                                        <td class="fw-bold font-monospace">{{ att.check_in_time }}</td>
                                        <td>
                                            <span class="badge px-3 py-2 rounded-pill shadow-sm" :class="att.status === 'present' ? 'bg-success text-white' : 'bg-warning text-dark'">
                                                {{ att.status.toUpperCase() }}
                                            </span>
                                        </td>
                                        <td>
                                            <button v-if="att.image_path" @click="viewImage(att.image_path)" class="btn btn-sm glass-btn text-info fw-bold border-info">
                                                <i class="bi bi-camera me-1"></i> View
                                            </button>
                                            <span v-else class="text-muted small italic">No Image</span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="btn-group shadow-sm rounded-pill overflow-hidden">
                                                <button class="btn btn-sm btn-success px-3 fw-bold" @click="approveAttendance(att)">Approve</button>
                                                <button class="btn btn-sm btn-danger px-3 fw-bold" @click="rejectAttendance(att)">Reject</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredAttendance.length === 0">
                                        <td colspan="7" class="text-center py-5 text-muted fw-bold">No pending attendance for approval.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Image Preview Modal -->
        <Modal :show="showImageModal" @close="showImageModal = false" maxWidth="lg">
            <div class="p-3">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <h5 class="fw-bold mb-0 text-primary">Verification Photo</h5>
                    <button class="btn-close" @click="showImageModal = false"></button>
                </div>
                <div class="text-center bg-light rounded p-2 overflow-hidden shadow-inner">
                    <img :src="selectedImage" class="img-fluid rounded shadow-sm border" style="max-height: 70vh;" alt="Verification Photo">
                </div>
                <div class="mt-3 d-flex justify-content-between align-items-center">
                    <small class="text-muted italic">Press ESC or click outside to close</small>
                    <button class="btn btn-dark px-4 rounded-pill" @click="showImageModal = false">Close Viewer</button>
                </div>
            </div>
        </Modal>
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

.banner-title {
    font-family: 'Outfit', sans-serif;
    color: #003287;
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

.action-tab {
    color: #888;
    font-family: 'Outfit', sans-serif;
    font-size: 1.1rem;
    border: none;
    background: transparent;
    transition: all 0.3s;
    letter-spacing: 0.5px;
}

.action-tab:hover {
    color: #0d6efd;
}

.tab-active {
    color: #0d6efd !important;
    border-bottom: 3px solid #0d6efd !important;
}

.glass-input {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    padding: 10px 15px;
    font-family: 'Inter', sans-serif;
    font-size: 0.95rem;
    transition: all 0.3s;
}

.glass-input:focus {
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
    border-color: #0d6efd;
    outline: none;
}

.glass-btn {
    border: none;
    border-radius: 30px;
    transition: transform 0.2s, box-shadow 0.2s;
}

.glass-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15) !important;
}
.glass-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Custom Table Styles */
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
}

@media (max-width: 768px) {
    .header-banner { padding: 15px; }
    .banner-title { font-size: 1.1rem; }
    .card-body { padding: 1rem !important; }
    .action-tab { padding: 8px 12px !important; font-size: 0.9rem; }
    .custom-table th { font-size: 0.75rem; padding: 10px 5px; }
    .custom-table td { font-size: 0.85rem; padding: 10px 5px; }
    .glass-btn { font-size: 0.85rem; padding: 8px 12px !important; }
    .bulk-actions-wrapper { padding: 1rem !important; }
}
</style>
