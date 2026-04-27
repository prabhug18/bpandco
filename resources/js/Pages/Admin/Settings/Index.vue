<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Alert from '@/Utils/Alert';

const props = defineProps({
    settings: Object
});

const form = useForm({
    attendance_work_start: props.settings.attendance_work_start || '09:00',
    attendance_grace_end: props.settings.attendance_grace_end || '09:15',
    attendance_half_day: props.settings.attendance_half_day || '10:00',
});

const submit = () => {
    form.post(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => Alert.toast('Settings saved successfully', 'success'),
    });
};
</script>

<template>
    <Head title="System Settings" />

    <AuthenticatedLayout>
        <div class="header-banner mb-4">
            <h4 class="mb-0 banner-title">System Configuration</h4>
        </div>

        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <div class="premium-card p-4">
                        <h5 class="fw-bold text-primary mb-4 border-bottom pb-2">
                            <i class="bi bi-clock-history me-2"></i> Attendance Rules
                        </h5>
                        
                        <form @submit.prevent="submit">
                            <div class="mb-4">
                                <label class="form-label fw-bold text-dark">Work Start Time</label>
                                <div class="input-group shadow-sm">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-play-circle text-success"></i></span>
                                    <input type="time" class="form-control glass-input border-start-0" v-model="form.attendance_work_start" required>
                                </div>
                                <small class="text-muted mt-1 d-block italic">The official time work begins (e.g. 09:00 AM)</small>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-dark">Grace Period End</label>
                                <div class="input-group shadow-sm">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-stopwatch text-warning"></i></span>
                                    <input type="time" class="form-control glass-input border-start-0" v-model="form.attendance_grace_end" required>
                                </div>
                                <small class="text-muted mt-1 d-block italic">Late status starts AFTER this time (e.g. 09:15 AM)</small>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-dark">Half-Day Threshold</label>
                                <div class="input-group shadow-sm">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-hourglass-split text-danger"></i></span>
                                    <input type="time" class="form-control glass-input border-start-0" v-model="form.attendance_half_day" required>
                                </div>
                                <small class="text-muted mt-1 d-block italic">Check-ins after this time are marked as Half-Day (e.g. 10:00 AM)</small>
                            </div>

                            <div class="mt-5 border-top pt-4">
                                <button type="submit" class="btn btn-primary glass-btn w-100 py-3 fw-bold shadow" :disabled="form.processing">
                                    <i class="bi bi-cloud-arrow-up-fill me-2"></i> SAVE CONFIGURATION
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="alert alert-info border-0 shadow-sm rounded-4 p-4">
                        <h6 class="fw-bold"><i class="bi bi-info-circle-fill me-2"></i> Configuration Guide</h6>
                        <hr>
                        <p class="small mb-2">Changing these values will affect <strong>new</strong> attendance submissions immediately.</p>
                        <ul class="small ps-3">
                            <li class="mb-2"><strong>Work Start:</strong> Basis for calculating if someone is on time.</li>
                            <li class="mb-2"><strong>Grace Period:</strong> Allows for a small delay (e.g. 15 mins) before marking as "Late".</li>
                            <li><strong>Half-Day:</strong> A hard cutoff point after which a full day's attendance cannot be claimed.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.header-banner {
    background: linear-gradient(135deg, rgba(0, 50, 135, 0.05) 0%, rgba(0, 50, 135, 0.1) 100%);
    padding: 18px 30px;
    border-bottom: 1px solid rgba(0, 50, 135, 0.1);
}
.banner-title {
    font-family: 'Outfit', sans-serif;
    color: #003287;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 1.4rem;
}
.premium-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    border: 1px solid rgba(0,0,0,0.03);
}
.glass-input {
    border: 1px solid #e2e8f0;
    padding: 12px;
    border-radius: 0 10px 10px 0;
}
.glass-btn {
    border-radius: 12px;
    transition: all 0.3s;
}
.glass-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(13, 110, 253, 0.3) !important;
}
</style>
