<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Alert from '@/Utils/Alert';

const props = defineProps({
    holidays: Array,
    users: Array
});

const form = useForm({
    date: '',
    reason: '',
    type: 'global_holiday',
    user_id: ''
});

const submit = () => {
    form.post(route('admin.holidays.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('date', 'reason', 'user_id'),
    });
};

const deleteHoliday = async (id) => {
    if(await Alert.confirm('Remove Schedule Entry?', 'Are you sure you want to remove this record?')) {
        useForm({}).delete(route('admin.holidays.destroy', id), {
            preserveScroll: true
        });
    }
};
</script>

<template>
    <Head title="Holidays & Leaves Configuration" />

    <AuthenticatedLayout>
        <!-- Premium Header -->
        <div class="header-banner mb-4">
            <h4 class="mb-0 banner-title">Schedule Configuration</h4>
        </div>

        <div class="container-fluid mb-5">
            <div class="row">
                <!-- Add New -->
                <div class="col-md-4 mb-4">
                    <div class="premium-card p-4">
                        <h4 class="fw-bold text-primary border-bottom pb-3 mb-4 title-font">
                            Add Schedule Event
                        </h4>
                        
                        <div class="alert alert-primary bg-primary text-white border-0 shadow-sm rounded-3 py-2 px-3 d-flex align-items-center gap-2 mb-4">
                            <i class="bi bi-calendar-event fs-4"></i>
                            <span class="small font-monospace">Holidays adjust target calculations globally or individually.</span>
                        </div>
                        
                        <form @submit.prevent="submit">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted mb-1">Schedule Type</label>
                                <select class="form-select glass-input px-3 py-2" v-model="form.type" required>
                                    <option value="global_holiday">Global Holiday (All Users)</option>
                                    <option value="sick_leave">Sick Leave</option>
                                    <option value="weekly_off">Weekly Off</option>
                                </select>
                            </div>

                            <div class="mb-3" v-if="form.type !== 'global_holiday'">
                                <label class="form-label small fw-bold text-muted mb-1">Select User</label>
                                <select class="form-select glass-input px-3 py-2" v-model="form.user_id" required>
                                    <option value="" disabled>Select Staff Member</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted mb-1">Date</label>
                                <input type="date" class="form-control glass-input px-3 py-2" v-model="form.date" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted mb-1">Reason / Occasion</label>
                                <input type="text" class="form-control glass-input px-3 py-2" v-model="form.reason" placeholder="e.g. Diwali, Sick, Sunday" required>
                            </div>

                            <button type="submit" class="btn btn-primary glass-btn w-100 py-2 fs-6 mt-1 shadow-sm" :disabled="form.processing">
                                Save Configuration
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Existing List -->
                <div class="col-md-8">
                    <div class="premium-card p-4">
                        <h4 class="fw-bold text-dark border-bottom pb-3 mb-4 title-font">
                            Configured Schedule Register
                        </h4>
                        
                        <div class="table-responsive rounded shadow-sm border-0">
                            <table class="table table-hover custom-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Date</th>
                                        <th>Schedule Type</th>
                                        <th>Reason</th>
                                        <th>Assigned Scope</th>
                                        <th class="pe-4 text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="h in holidays" :key="h.id" class="table-row-hover">
                                        <td class="ps-4 text-primary fw-bold font-monospace">{{ new Date(h.date).toLocaleDateString('en-GB') }}</td>
                                        <td>
                                            <span class="badge px-3 py-2 rounded-pill shadow-sm"
                                                :class="{
                                                    'bg-success': h.type === 'global_holiday',
                                                    'bg-danger': h.type === 'sick_leave',
                                                    'bg-warning text-dark': h.type === 'weekly_off'
                                                }">
                                                {{ h.type.replace('_', ' ').toUpperCase() }}
                                            </span>
                                        </td>
                                        <td class="text-secondary fw-semibold">{{ h.reason }}</td>
                                        <td>
                                            <span v-if="h.user" class="fw-bold text-dark"><i class="bi bi-person me-1 text-muted"></i>{{ h.user.name }}</span>
                                            <span v-else class="text-primary fw-bold bg-light px-2 py-1 rounded"><i class="bi bi-globe me-1"></i>EVERYONE</span>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <button class="btn btn-sm btn-outline-danger rounded-pill px-3 shadow-sm" @click="deleteHoliday(h.id)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="holidays.length === 0">
                                        <td colspan="5" class="text-center py-5 text-muted fw-bold">No holidays or leaves configured yet.</td>
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
