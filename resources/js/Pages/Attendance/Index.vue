<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import Alert from '@/Utils/Alert';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    records: Array,
    today: String,
    yesterday: String,
    rules: Object,
});

const showImageModal = ref(false);
const selectedImage = ref('');

const viewImage = (path) => {
    selectedImage.value = `/storage/${path}`;
    showImageModal.value = true;
};

const form = useForm({
    date: props.today,
    check_in_time: '',
    image: null,
});

const fileInput = ref(null);
const showDatePicker = ref(false);

const handleImage = (e) => { form.image = e.target.files[0]; };

const submit = async () => {
    // ── Pre-Submission Checks ────────
    const existing = props.records.find(r => r.date === form.date);
    
    if (existing) {
        if (existing.approval_status === 'approved') {
            Alert.error('Entry Locked', `Your attendance for ${formatDate(existing.date)} has already been approved. No further changes allowed.`);
            return;
        }
        
        const actionText = existing.approval_status === 'rejected' ? 'Resubmit' : 'Update';
        const confirmed = await Alert.confirm(
            `${actionText} Attendance?`, 
            `Your submission for ${formatDate(existing.date)} is currently ${existing.approval_status}. Do you want to ${actionText.toLowerCase()} it with new details?`,
            `Yes, ${actionText}!`
        );
        if (!confirmed) return;
    }

    form.post(route('attendance.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('check_in_time', 'image');
            if (fileInput.value) fileInput.value.value = ''; // Clear visual file name
        },
    });
};

const formatDate = (dateStr) => {
    const d = new Date(dateStr);
    const day = d.getDate().toString().padStart(2, '0');
    const month = d.toLocaleString('en-US', { month: 'short' }).toUpperCase();
    const year = d.getFullYear();
    return `${day}-${month}-${year}`;
};

const statusBadge = (status) => ({
    present: 'bg-success',
    late: 'bg-warning text-dark',
    half_day: 'bg-orange text-white',
    absent: 'bg-danger',
    holiday: 'bg-secondary',
}[status] || 'bg-light text-dark');

const approvalBadge = (status) => ({
    approved: 'bg-success',
    pending: 'bg-warning text-dark',
    rejected: 'bg-danger',
}[status] || 'bg-secondary');

const reApply = (record) => {
    form.date = record.date;
    form.check_in_time = record.check_in_time;
    showDatePicker.value = true;
    Alert.toast('Form updated with rejected details. Please upload a new photo.', 'info');
};
</script>

<template>
    <Head title="Attendance" />
    <AuthenticatedLayout>
        <div class="d-flex justify-content-between align-items-center px-4 py-2 bg-white text-dark shadow-sm w-100">
            <h4 class="fw-bold mb-0">Attendance Entry</h4>
        </div>

        <div class="container-fluid mt-4 mb-5">
            <div class="row">
                <!-- Submit Form -->
                <div class="col-md-4 mb-4">
                    <div class="card shadow-sm border-0 p-4">
                        <h5 class="fw-bold text-primary border-bottom pb-2 mb-4">Mark Attendance</h5>

                        <form @submit.prevent="submit" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Date</label>
                                <div v-if="!showDatePicker && (form.date === today || form.date === yesterday)" class="btn-group w-100">
                                    <button type="button" class="btn" :class="form.date === today ? 'btn-primary' : 'btn-outline-primary'" @click="form.date = today">Today</button>
                                    <button type="button" class="btn" :class="form.date === yesterday ? 'btn-primary' : 'btn-outline-primary'" @click="form.date = yesterday">Yesterday</button>
                                    <button type="button" class="btn btn-outline-secondary" @click="showDatePicker = true"><i class="bi bi-calendar3"></i></button>
                                </div>
                                <div v-else class="input-group">
                                    <input type="date" class="form-control" v-model="form.date" :max="today">
                                    <button type="button" class="btn btn-outline-secondary" @click="showDatePicker = false; form.date = today">Reset</button>
                                </div>
                                <small class="text-muted mt-1 d-block" v-if="form.date !== today && form.date !== yesterday">
                                    <i class="bi bi-info-circle me-1"></i> Older dates only allowed for resubmitting rejected entries.
                                </small>
                                <div v-if="form.errors.date" class="text-danger mt-1 small">{{ form.errors.date }}</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Check-in Time</label>
                                <input type="time" class="form-control" v-model="form.check_in_time" required>
                                <small class="text-muted">Work start: {{ rules.work_start }} | Grace: {{ rules.grace_end }} | After {{ rules.half_day }} = Half Day</small>
                                <div v-if="form.errors.check_in_time" class="text-danger mt-1 small">{{ form.errors.check_in_time }}</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Upload Photo <span class="text-danger">*</span></label>
                                <input type="file" ref="fileInput" accept="image/*" class="form-control" @change="handleImage" required>
                                <small class="text-muted">Upload your attendance photo (max 5MB)</small>
                                <div v-if="form.errors.image" class="text-danger mt-1 small">{{ form.errors.image }}</div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100" :disabled="form.processing">
                                Submit Attendance
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Attendance History -->
                <div class="col-md-8">
                    <div class="card shadow-sm border-0 p-4">
                        <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">Recent Attendance (Last 30 Days)</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Check-in</th>
                                        <th>Status</th>
                                        <th>Approval</th>
                                        <th>Photo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="r in records" :key="r.id">
                                        <td class="fw-bold">{{ formatDate(r.date) }}</td>
                                        <td>{{ r.check_in_time }}</td>
                                        <td><span class="badge" :class="statusBadge(r.status)">{{ r.status.replace('_', ' ').toUpperCase() }}</span></td>
                                        <td>
                                            <div class="d-flex flex-column align-items-start gap-1">
                                                <span class="badge" :class="approvalBadge(r.approval_status)">{{ r.approval_status.toUpperCase() }}</span>
                                                <button v-if="r.approval_status === 'rejected'" @click="reApply(r)" class="btn btn-sm btn-link text-primary p-0 fw-bold" style="font-size: 0.75rem;">
                                                    <i class="bi bi-arrow-clockwise"></i> Re-apply
                                                </button>
                                            </div>
                                            <div v-if="r.approval_status === 'rejected' && r.rejection_reason" class="text-danger small mt-1 fw-semibold italic" style="max-width: 150px; line-height: 1.2;">
                                                Reason: {{ r.rejection_reason }}
                                            </div>
                                        </td>
                                        <td>
                                            <button v-if="r.image_path" @click="viewImage(r.image_path)" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-image"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="records.length === 0">
                                        <td colspan="5" class="text-center text-muted">No attendance records yet.</td>
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
                    <h5 class="fw-bold mb-0">Attendance Photo</h5>
                    <button class="btn-close" @click="showImageModal = false"></button>
                </div>
                <div class="text-center bg-light rounded p-2 overflow-hidden">
                    <img :src="selectedImage" class="img-fluid rounded shadow-sm" style="max-height: 70vh;" alt="Attendance Photo">
                </div>
                <div class="mt-3 text-end">
                    <button class="btn btn-secondary px-4" @click="showImageModal = false">Close</button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.bg-orange {
    background-color: #fd7e14 !important;
}
</style>
