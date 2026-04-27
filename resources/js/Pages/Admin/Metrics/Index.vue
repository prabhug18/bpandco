<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    metrics: Array,
    availableRoles: Array
});

// Helper for fast role assignment submissions
const toggleRole = (metric, roleId) => {
    // Collect current role ids
    let currentRoleIds = metric.roles.map(r => r.id);
    
    if (currentRoleIds.includes(roleId)) {
        currentRoleIds = currentRoleIds.filter(id => id !== roleId);
    } else {
        currentRoleIds.push(roleId);
    }

    const form = useForm({
        role_ids: currentRoleIds
    });
    
    // Auto submit the update
    form.put(route('admin.metrics.update', metric.id), {
        preserveScroll: true,
    });
};

const toggleActive = (metric) => {
    const form = useForm({
        is_active: !metric.is_active
    });
    
    form.put(route('admin.metrics.update', metric.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Metrics Configuration" />

    <AuthenticatedLayout>
        <!-- Premium Header -->
        <div class="header-banner mb-4">
            <h4 class="mb-0 banner-title">Dynamic Metrics Configuration</h4>
        </div>

        <div class="container-fluid mb-5">
            <div class="premium-card p-4 mx-auto" style="max-width: 1200px;">
                <div class="alert alert-primary bg-primary text-white border-0 shadow-sm rounded-3 py-3 px-4 d-flex align-items-center gap-3 mb-4">
                    <i class="bi bi-gear-fill fs-3"></i>
                    <div>
                        <strong class="d-block mb-1 font-monospace text-uppercase" style="letter-spacing: 0.5px;">Global Metric Registry</strong> 
                        <span>Select which roles are required to submit data for each metric. Metrics disabled here will disappear from user queues.</span>
                    </div>
                </div>

                <div class="table-responsive rounded shadow-sm border-0">
                    <table class="table table-hover align-middle custom-table mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Metric / Descriptor</th>
                                <th>Unit & Pattern</th>
                                <th class="text-center">Global Status</th>
                                <th class="pe-4">Role Enforcement</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="metric in metrics" :key="metric.id" class="table-row-hover">
                                <td class="ps-4">
                                    <div class="fw-bold text-dark fs-6">{{ metric.label }}</div>
                                    <div class="text-muted small italic">ID: {{ metric.id }}</div>
                                </td>
                                <td>
                                    <span class="badge px-3 py-2 rounded-pill shadow-sm bg-light text-dark border">{{ metric.unit }}</span>
                                    <div class="mt-2">
                                        <small class="text-primary fw-bold font-monospace bg-light px-2 py-1 rounded">{{ metric.scoring_type.replace(/_/g, ' ').toUpperCase() }}</small>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-inline-block" style="transform: scale(1.3);">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                            :checked="metric.is_active" 
                                            @change="toggleActive(metric)"
                                            style="cursor: pointer;">
                                    </div>
                                    <div class="mt-1 small" :class="metric.is_active ? 'text-success fw-bold' : 'text-danger fw-bold'">
                                        {{ metric.is_active ? 'ACTIVE' : 'DISABLED' }}
                                    </div>
                                </td>
                                <td class="pe-4 py-3">
                                    <div class="d-flex flex-wrap gap-2">
                                        <!-- Render toggleable badges for each role -->
                                        <button v-for="role in availableRoles" :key="role.id"
                                            class="btn btn-sm rounded-pill fw-bold"
                                            :class="metric.roles.some(r => r.id === role.id) ? 'btn-primary shadow-sm glass-btn' : 'btn-outline-secondary text-muted'"
                                            style="transition: all 0.2s;"
                                            @click="toggleRole(metric, role.id)">
                                            {{ role.name.toUpperCase() }}
                                            <i v-if="metric.roles.some(r => r.id === role.id)" class="bi bi-check-circle-fill ms-1"></i>
                                            <i v-else class="bi bi-plus-circle ms-1"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="metrics.length === 0">
                                <td colspan="4" class="text-center py-5 text-muted fw-bold">No metrics found. Database needs seeding.</td>
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

.glass-btn {
    border: none;
    transition: transform 0.2s, box-shadow 0.2s;
    font-family: 'Inter', sans-serif;
}

.glass-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(13, 110, 253, 0.2) !important;
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

.btn-outline-secondary {
    border-style: dashed;
    border-color: #ccc;
}
.btn-outline-secondary:hover {
    background: rgba(0,0,0,0.03);
    color: #333 !important;
}
</style>
