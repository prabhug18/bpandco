<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Alert from '@/Utils/Alert';

const props = defineProps({
    settings: Object,
    roles: Array,
    selected_role_id: [Number, String],
    api_tokens: Array,
    new_token: String,
});

const tokenForm = useForm({
    name: '',
    store_code: '',
});

const createApiToken = () => {
    if (!tokenForm.name) {
        Alert.error('Validation Error', 'Please enter an Integration / Token Name');
        return;
    }

    tokenForm.post(route('admin.settings.api-tokens.store'), {
        preserveScroll: true,
        onSuccess: () => {
            tokenForm.reset();
            Alert.toast('API Token generated successfully!', 'success');
        },
        onError: (err) => {
            Alert.error('Error', Object.values(err)[0] || 'Failed to generate token');
        }
    });
};

const revokeToken = (token) => {
    if (confirm(`Are you sure you want to revoke API Token '${token.name}'?`)) {
        useForm({}).delete(route('admin.settings.api-tokens.revoke', token.id), {
            preserveScroll: true,
            onSuccess: () => Alert.toast('Token revoked successfully', 'info'),
        });
    }
};

const form = useForm({
    role_id: props.selected_role_id || '',
    attendance_work_start: props.settings.attendance_work_start || '09:00',
    attendance_grace_end: props.settings.attendance_grace_end || '09:15',
    attendance_half_day: props.settings.attendance_half_day || '10:00',
    geofence_latitude: props.settings.geofence_latitude || '',
    geofence_longitude: props.settings.geofence_longitude || '',
    geofence_radius: props.settings.geofence_radius || '200',

    // WhatsApp Settings
    whatsapp_api_url: props.settings.whatsapp_api_url || 'https://app.weconnext.in/api/v1/messages/send',
    whatsapp_api_token: props.settings.whatsapp_api_token || '',
    whatsapp_otp_template: props.settings.whatsapp_otp_template || 'bpandco_login_otp',
    whatsapp_reminder_template: props.settings.whatsapp_reminder_template || 'bpandco_employee_reminder',
    whatsapp_approval_template: props.settings.whatsapp_approval_template || 'bpandco_supervisor_reminder',
    whatsapp_employee_reminder_time: props.settings.whatsapp_employee_reminder_time || '19:00',
    whatsapp_supervisor_reminder_time: props.settings.whatsapp_supervisor_reminder_time || '10:00',
    whatsapp_otp_enabled: props.settings.whatsapp_otp_enabled === '1' || props.settings.whatsapp_otp_enabled === 'true' || props.settings.whatsapp_otp_enabled === true,
    whatsapp_employee_reminder_enabled: props.settings.whatsapp_employee_reminder_enabled === '1' || props.settings.whatsapp_employee_reminder_enabled === 'true' || props.settings.whatsapp_employee_reminder_enabled === true,
    whatsapp_supervisor_reminder_enabled: props.settings.whatsapp_supervisor_reminder_enabled === '1' || props.settings.whatsapp_supervisor_reminder_enabled === 'true' || props.settings.whatsapp_supervisor_reminder_enabled === true,
});

const fetchLocation = () => {
    if (!navigator.geolocation) {
        Alert.error('Unsupported', 'Geolocation is not supported by your browser');
        return;
    }
    
    Alert.toast('Fetching current location...', 'info');
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            form.geofence_latitude = pos.coords.latitude.toFixed(7);
            form.geofence_longitude = pos.coords.longitude.toFixed(7);
            Alert.toast('Location fetched successfully', 'success');
        },
        (err) => Alert.error('Location Error', err.message),
        { enableHighAccuracy: true }
    );
};

const changeRole = (id) => {
    window.location.href = route('admin.settings.index', { role_id: id });
};

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
            <!-- Role Selector -->
            <div class="premium-card p-3 mb-4 d-flex align-items-center gap-3 overflow-auto no-scrollbar">
                <span class="fw-bold text-muted small text-uppercase me-2" style="min-width: 100px;">Select Role:</span>
                <button 
                    @click="changeRole('')"
                    class="btn btn-sm rounded-pill px-4 transition"
                    :class="!selected_role_id ? 'btn-primary shadow' : 'btn-outline-secondary'"
                >
                    Global
                </button>
                <button 
                    v-for="role in roles" 
                    :key="role.id" 
                    @click="changeRole(role.id)"
                    class="btn btn-sm rounded-pill px-4 transition"
                    :class="selected_role_id == role.id ? 'btn-primary shadow' : 'btn-outline-secondary'"
                >
                    {{ role.name.replace(/_/g, ' ') }}
                </button>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="premium-card p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                            <h5 class="fw-bold text-primary mb-0">
                                <i class="bi bi-clock-history me-2"></i> Attendance Rules
                            </h5>
                            <span class="badge bg-light text-primary rounded-pill px-3 py-2 border">
                                Mode: {{ selected_role_id ? 'Role-Specific' : 'Global' }}
                            </span>
                        </div>
                        
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
                                    <i class="bi bi-cloud-arrow-up-fill me-2"></i> SAVE ATTENDANCE RULES
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="premium-card p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                            <h5 class="fw-bold text-primary mb-0">
                                <i class="bi bi-geo-alt-fill me-2"></i> Geofence Settings
                            </h5>
                            <button @click="fetchLocation" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                <i class="bi bi-pin-map me-1"></i> Fetch My Location
                            </button>
                        </div>

                        <form @submit.prevent="submit">
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label fw-bold text-dark">Center Latitude</label>
                                    <input type="number" step="0.0000001" class="form-control glass-input" v-model="form.geofence_latitude" placeholder="e.g. 19.0760">
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label fw-bold text-dark">Center Longitude</label>
                                    <input type="number" step="0.0000001" class="form-control glass-input" v-model="form.geofence_longitude" placeholder="e.g. 72.8777">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-dark">Radius (Meters)</label>
                                <div class="input-group shadow-sm">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-broadcast text-info"></i></span>
                                    <input type="number" class="form-control glass-input border-start-0" v-model="form.geofence_radius" placeholder="e.g. 200">
                                </div>
                                <small class="text-muted mt-1 d-block italic">Allowed distance from center for on-site attendance.</small>
                            </div>

                            <div class="mt-5 border-top pt-4">
                                <button type="submit" class="btn btn-primary glass-btn w-100 py-3 fw-bold shadow" :disabled="form.processing">
                                    <i class="bi bi-shield-check me-2"></i> SAVE GEOFENCE
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="alert alert-info border-0 shadow-sm rounded-4 p-4 mb-4">
                        <h6 class="fw-bold"><i class="bi bi-info-circle-fill me-2"></i> Configuration Guide</h6>
                        <hr>
                        <p class="small mb-2">Changing these values will affect <strong>new</strong> attendance submissions immediately.</p>
                        <ul class="small ps-3">
                            <li class="mb-2"><strong>Work Start:</strong> Basis for calculating if someone is on time.</li>
                            <li class="mb-2"><strong>Grace Period:</strong> Allows for a small delay (e.g. 15 mins) before marking as "Late".</li>
                            <li><strong>Half-Day:</strong> A hard cutoff point after which a full day's attendance cannot be claimed.</li>
                        </ul>
                    </div>

                    <!-- WhatsApp Configuration -->
                    <div class="premium-card p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                            <h5 class="fw-bold text-success mb-0">
                                <i class="fab fa-whatsapp me-2"></i> WhatsApp Integration
                            </h5>
                        </div>

                        <form @submit.prevent="submit">
                            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">WeConnext API Credentials</h6>
                            <div class="mb-3">
                                <label class="form-label text-muted small">API Base URL</label>
                                <input type="url" class="form-control" v-model="form.whatsapp_api_url" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label text-muted small">API Token (Bearer)</label>
                                <input type="password" class="form-control" v-model="form.whatsapp_api_token" placeholder="Enter token to update">
                                <small class="text-muted d-block mt-1" v-if="props.settings.whatsapp_api_token">Token is configured. Enter a new one to change it.</small>
                            </div>

                            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Templates & Toggles</h6>
                            
                            <!-- Login OTP -->
                            <div class="row align-items-center mb-3">
                                <div class="col-8">
                                    <label class="form-label mb-0 fw-bold">Login OTP</label>
                                    <small class="text-muted d-block">Allow users to login via WhatsApp</small>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input" type="checkbox" role="switch" v-model="form.whatsapp_otp_enabled">
                                    </div>
                                </div>
                                <div class="col-12 mt-2" v-if="form.whatsapp_otp_enabled">
                                    <input type="text" class="form-control form-control-sm" v-model="form.whatsapp_otp_template" placeholder="Template Name (e.g. bpandco_login_otp)">
                                </div>
                            </div>

                            <!-- Employee Reminder -->
                            <div class="row align-items-center mb-3">
                                <div class="col-8">
                                    <label class="form-label mb-0 fw-bold">Employee Daily Reminder</label>
                                    <small class="text-muted d-block">Remind if no slip submitted</small>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input" type="checkbox" role="switch" v-model="form.whatsapp_employee_reminder_enabled">
                                    </div>
                                </div>
                                <div class="col-12 mt-2" v-if="form.whatsapp_employee_reminder_enabled">
                                    <div class="d-flex gap-2">
                                        <input type="time" class="form-control form-control-sm" v-model="form.whatsapp_employee_reminder_time" style="width: 120px;">
                                        <input type="text" class="form-control form-control-sm" v-model="form.whatsapp_reminder_template" placeholder="Template Name">
                                    </div>
                                </div>
                            </div>

                            <!-- Supervisor Reminder -->
                            <div class="row align-items-center mb-4">
                                <div class="col-8">
                                    <label class="form-label mb-0 fw-bold">Supervisor Reminder</label>
                                    <small class="text-muted d-block">Remind about pending approvals</small>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input" type="checkbox" role="switch" v-model="form.whatsapp_supervisor_reminder_enabled">
                                    </div>
                                </div>
                                <div class="col-12 mt-2" v-if="form.whatsapp_supervisor_reminder_enabled">
                                    <div class="d-flex gap-2">
                                        <input type="time" class="form-control form-control-sm" v-model="form.whatsapp_supervisor_reminder_time" style="width: 120px;">
                                        <input type="text" class="form-control form-control-sm" v-model="form.whatsapp_approval_template" placeholder="Template Name">
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 border-top pt-4">
                                <button type="submit" class="btn btn-success glass-btn w-100 py-3 fw-bold shadow" :disabled="form.processing">
                                    <i class="bi bi-whatsapp me-2"></i> SAVE WHATSAPP SETTINGS
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Third-Party POS & Billing API Tokens Card -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="premium-card p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                            <div>
                                <h5 class="fw-bold text-primary mb-0">
                                    <i class="bi bi-key me-2"></i> POS & Billing Software API Integration Tokens
                                </h5>
                                <small class="text-muted">Manage Bearer tokens for external shop POS / billing software sync.</small>
                            </div>
                        </div>

                        <!-- Generated Token Banner Alert -->
                        <div v-if="new_token" class="alert alert-success border-0 shadow-sm p-3 mb-4 rounded-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div class="fw-bold text-uppercase"><i class="bi bi-exclamation-triangle-fill me-1"></i> API Token Generated! Copy It Now:</div>
                                <button type="button" class="btn btn-sm btn-dark rounded-pill px-3 py-0" @click="navigator.clipboard.writeText(new_token); Alert.toast('Token copied to clipboard!', 'success');">
                                    <i class="bi bi-clipboard me-1"></i> Copy
                                </button>
                            </div>
                            <div class="bg-white p-2 rounded border font-monospace text-dark user-select-all fw-bold fs-6">
                                {{ new_token }}
                            </div>
                            <small class="text-muted mt-1 d-block">This plain-text token will not be displayed again for security reasons.</small>
                        </div>

                        <!-- Generate Token Form -->
                        <form @submit.prevent="createApiToken" class="row g-3 align-items-end mb-4 bg-light p-3 rounded-3 border">
                            <div class="col-md-5">
                                <label class="form-label small fw-bold text-muted text-uppercase mb-1">Token Name / Integration Name</label>
                                <input type="text" class="form-control form-control-sm" v-model="tokenForm.name" placeholder="e.g. Main Shop POS System" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted text-uppercase mb-1">Store / Branch Code (Optional)</label>
                                <input type="text" class="form-control form-control-sm" v-model="tokenForm.store_code" placeholder="e.g. STORE_001">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-sm btn-primary rounded-pill w-100 fw-bold shadow-sm" :disabled="tokenForm.processing">
                                    <i class="bi bi-plus-lg me-1"></i> Generate API Token
                                </button>
                            </div>
                        </form>

                        <!-- Existing Tokens List -->
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small text-uppercase fw-bold text-muted">
                                    <tr>
                                        <th>Integration Name</th>
                                        <th>Store Code</th>
                                        <th>Created By</th>
                                        <th>Last Used</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="t in api_tokens" :key="t.id">
                                        <td class="fw-bold text-dark">{{ t.name }}</td>
                                        <td><code>{{ t.store_code || '-' }}</code></td>
                                        <td class="small text-muted">{{ t.creator?.name || 'Admin' }}</td>
                                        <td class="small text-muted">{{ t.last_used_at ? new Date(t.last_used_at).toLocaleString() : 'Never' }}</td>
                                        <td>
                                            <span class="badge rounded-pill" :class="t.is_active ? 'bg-success' : 'bg-danger'">
                                                {{ t.is_active ? 'Active' : 'Revoked' }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <button v-if="t.is_active" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1" @click="revokeToken(t)">
                                                Revoke
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!api_tokens || api_tokens.length === 0">
                                        <td colspan="6" class="text-center py-4 text-muted small">
                                            No POS / Billing integration tokens created yet.
                                        </td>
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
