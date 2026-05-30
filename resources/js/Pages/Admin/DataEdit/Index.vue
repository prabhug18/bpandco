<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch, onMounted } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    users: Array,
    metrics: Array,
    data: Array,
    filters: Object,
});

const selectedUser = ref(props.filters.user_id || '');
const dateFrom = ref(props.filters.date_from);
const dateTo = ref(props.filters.date_to);

const applyFilters = () => {
    router.get(route('admin.data.edit'), {
        user_id: selectedUser.value,
        date_from: dateFrom.value,
        date_to: dateTo.value,
    }, { preserveState: true });
};

const selectUser = (id) => {
    selectedUser.value = id;
    applyFilters();
};

const printReport = () => { window.print(); };

// --- Editing Workflow ---
const editForm = useForm({
    user_id: '',
    date: '',
    attendance: '',
    metrics: {},
});

const openEditModal = (dateRow) => {
    editForm.user_id = selectedUser.value;
    editForm.date = dateRow.date;
    editForm.attendance = dateRow.attendance || 'absent';
    
    // Pre-fill metrics
    editForm.metrics = {};
    props.metrics.forEach(m => {
        editForm.metrics[m.id] = dateRow.metrics[m.id] !== undefined ? dateRow.metrics[m.id] : null;
    });

    const userName = props.users.find(u => u.id == selectedUser.value)?.name;

    // Build the form HTML
    let html = `
        <div style="text-align:left; font-family:'Inter', sans-serif;">
            <p class="text-muted mb-3 small">Editing data for <strong>${userName}</strong> on <strong class="text-primary">${dateRow.formatted_date}</strong></p>
            
            <div class="mb-3">
                <label class="form-label fw-bold text-dark small">Attendance Status</label>
                <select id="swal-attendance" class="form-select border-primary shadow-sm">
                    <option value="present" ${editForm.attendance === 'present' ? 'selected' : ''}>Present</option>
                    <option value="half_day" ${editForm.attendance === 'half_day' ? 'selected' : ''}>Half Day</option>
                    <option value="late" ${editForm.attendance === 'late' ? 'selected' : ''}>Late</option>
                    <option value="absent" ${editForm.attendance === 'absent' ? 'selected' : ''}>Absent</option>
                </select>
            </div>
            <hr class="my-3 border-secondary" style="opacity: 0.1">
    `;

    props.metrics.forEach(m => {
        if (m.key !== 'attendance' && m.key !== 'late') {
            const val = editForm.metrics[m.id] !== null ? editForm.metrics[m.id] : '';
            html += `
                <div class="mb-3">
                    <label class="form-label fw-bold text-dark small">${m.label} <span class="text-muted fw-normal">(${m.unit})</span></label>
                    <input type="number" id="swal-metric-${m.id}" class="form-control" value="${val}" placeholder="Enter ${m.unit}">
                </div>
            `;
        }
    });

    html += `</div>`;

    Swal.fire({
        title: '✏️ Edit Daily Data',
        html: html,
        showCancelButton: true,
        confirmButtonText: 'Save Changes',
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        focusConfirm: false,
        preConfirm: () => {
            const att = document.getElementById('swal-attendance').value;
            editForm.attendance = att;
            
            props.metrics.forEach(m => {
                if (m.key !== 'attendance' && m.key !== 'late') {
                    const el = document.getElementById(`swal-metric-${m.id}`);
                    editForm.metrics[m.id] = el.value !== '' ? parseFloat(el.value) : null;
                }
            });
            return true;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            editForm.post(route('admin.data.update'), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({ icon: 'success', title: 'Saved!', text: 'Data updated and points recalculated.', timer: 1500, showConfirmButton: false });
                }
            });
        }
    });
};

const getBgColor = (colorCode) => {
    if (colorCode === 'green') return '#22c55e';
    if (colorCode === 'yellow') return '#eab308';
    if (colorCode === 'red') return '#ef4444';
    return '#64748b'; // grey
};
</script>

<template>
    <Head title="Admin Data Editor" />
    <AuthenticatedLayout>
        <!-- Top Toolbar -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center px-4 py-3 bg-white text-dark shadow-sm w-100 mb-0 border-bottom d-print-none">
            <h4 class="fw-bold mb-3 mb-md-0 banner-title text-uppercase">Data Editor</h4>
            
            <div class="d-flex align-items-center gap-3 flex-wrap justify-content-center">
                <!-- Custom Date Filter -->
                <div class="d-flex align-items-center gap-2 bg-light p-1 rounded-pill shadow-sm border px-3">
                    <label class="small fw-bold text-muted m-0">FROM:</label>
                    <input type="date" class="form-control form-control-sm border-0 bg-transparent shadow-none" v-model="dateFrom" @change="applyFilters">
                    <label class="small fw-bold text-muted m-0 ms-2">TO:</label>
                    <input type="date" class="form-control form-control-sm border-0 bg-transparent shadow-none" v-model="dateTo" @change="applyFilters">
                </div>

                <button class="btn btn-outline-dark shadow-sm rounded-pill px-4 fw-bold" @click="printReport">
                    <i class="bi bi-printer me-1"></i> Export PDF
                </button>
            </div>
        </div>

        <div class="p-4 theme-dark-container">
            <div class="card premium-dark-card border-0 shadow-lg p-4 mx-auto" style="max-width: 1400px;">
                
                <!-- Inner Toolbar: User Selection -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary" style="border-opacity: 0.2;">
                    <div class="d-flex align-items-center gap-3">
                        <label class="text-info fw-bold text-uppercase letter-spacing-1 small mb-0">Select Staff:</label>
                        <select class="form-select form-select-sm dark-select border-info shadow-none text-white fw-bold" v-model="selectedUser" @change="applyFilters" style="width: 250px;">
                            <option value="">All Staff (Summary)</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                </div>

                <!-- View A: All Users Summary -->
                <div v-if="!selectedUser" class="table-responsive rounded shadow-inner-dark">
                    <table class="table table-dark table-hover align-middle mb-0 custom-dark-table">
                        <thead>
                            <tr class="header-row">
                                <th class="text-center" style="width: 80px;">S.NO</th>
                                <th>NAME</th>
                                <th class="text-center" style="width: 150px;">ACTION</th>
                                <th class="text-center" style="width: 200px;">TOTAL SCORE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in data" :key="row.id" class="dark-row-hover">
                                <td class="text-center text-muted fw-bold">{{ index + 1 }}</td>
                                <td class="fw-bold fs-6 text-light">{{ row.name }}</td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-warning rounded-pill px-3 fw-bold" @click="selectUser(row.id)">
                                        <i class="bi bi-pencil-square me-1"></i> Edit Data
                                    </button>
                                </td>
                                <td class="p-0">
                                    <div class="w-100 h-100 d-flex align-items-center justify-content-center fw-bold fs-5 shadow-inner" 
                                         :style="{ backgroundColor: getBgColor(row.color), color: '#fff', minHeight: '55px' }">
                                        {{ row.total_points }}
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="data.length === 0">
                                <td colspan="4" class="text-center py-5 text-muted">No data found for the selected dates.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- View B: Specific User Date-wise Edit -->
                <div v-else class="table-responsive rounded shadow-inner-dark">
                    <table class="table table-dark table-hover align-middle mb-0 custom-dark-table text-center">
                        <thead>
                            <tr class="header-row">
                                <th class="text-start pe-3">DATE</th>
                                <th>ATTENDANCE</th>
                                <th v-for="m in metrics.filter(m => !['attendance', 'late'].includes(m.key))" :key="m.id">
                                    {{ m.label.toUpperCase() }}
                                </th>
                                <th style="width: 120px;">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in data" :key="row.date" class="dark-row-hover">
                                <td class="text-start fw-bold text-info">{{ row.formatted_date }}</td>
                                
                                <td>
                                    <span class="badge rounded-pill px-3 py-2 border border-secondary" 
                                          :class="{'bg-success': row.attendance === 'present', 'bg-warning text-dark': row.attendance === 'half_day', 'bg-danger': row.attendance === 'late', 'bg-secondary': row.attendance === 'absent'}">
                                        {{ row.attendance ? row.attendance.toUpperCase() : 'ABSENT' }}
                                    </span>
                                </td>

                                <td v-for="m in metrics.filter(m => !['attendance', 'late'].includes(m.key))" :key="m.id">
                                    <span class="fw-semibold text-light" v-if="row.metrics[m.id] !== null">
                                        {{ row.metrics[m.id] }}
                                    </span>
                                    <span class="text-muted small italic" v-else>-</span>
                                </td>

                                <td>
                                    <button class="btn btn-sm btn-warning shadow-sm fw-bold px-3 py-1 rounded-3" @click="openEditModal(row)">
                                        EDIT
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="data.length === 0">
                                <td :colspan="metrics.length + 1" class="text-center py-5 text-muted">No dates generated.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap');

.banner-title {
    font-family: 'Outfit', sans-serif;
    color: #003287;
    letter-spacing: 0.5px;
}

.theme-dark-container {
    background: linear-gradient(135deg, #09090b 0%, #18181b 100%);
    min-height: calc(100vh - 140px);
}

.premium-dark-card {
    background: #1e1e24; /* Very dark grayish blue */
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.05) !important;
}

.dark-select {
    background-color: #27272a;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2338bdf8' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
}
.dark-select:focus {
    border-color: #0ea5e9;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.25);
}

.shadow-inner-dark {
    box-shadow: inset 0 2px 10px rgba(0,0,0,0.5);
    background: #111113;
    border: 1px solid rgba(255,255,255,0.05);
}

.custom-dark-table {
    font-family: 'Inter', sans-serif;
    --bs-table-bg: transparent;
    --bs-table-color: #e2e8f0;
}

.custom-dark-table .header-row th {
    background: rgba(255,255,255,0.02);
    color: #94a3b8;
    font-family: 'Outfit', sans-serif;
    font-weight: 700;
    letter-spacing: 1px;
    border-bottom: 2px solid rgba(255,255,255,0.1) !important;
    padding: 18px 15px;
}

.custom-dark-table td {
    border-bottom: 1px solid rgba(255,255,255,0.05);
    padding: 10px 15px;
}

.dark-row-hover:hover td {
    background: rgba(255,255,255,0.03) !important;
}

.letter-spacing-1 {
    letter-spacing: 1px;
}
</style>
