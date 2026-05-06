<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Alert from '@/Utils/Alert';

const props = defineProps({
    users: { type: Array, required: true },
    roles: { type: Array, required: true },
    filters: { type: Object, default: () => ({ search: '', role: '' }) },
});

const form = ref({
    search: props.filters.search || '',
    role: props.filters.role || '',
});

// Vanilla helper to remove empty/null values
const cleanParams = (obj) => {
    return Object.fromEntries(
        Object.entries(obj).filter(([_, v]) => v !== '' && v !== null && v !== undefined)
    );
};

let throttleTimeout = null;
watch(
    () => form.value,
    () => {
        if (throttleTimeout) clearTimeout(throttleTimeout);
        throttleTimeout = setTimeout(() => {
            router.get(route('users.index'), cleanParams(form.value), {
                preserveState: true,
                replace: true,
            });
        }, 300);
    },
    { deep: true }
);

const clearFilters = () => {
    form.value.search = '';
    form.value.role = '';
};

const exportToExcel = () => {
    const params = cleanParams(form.value);
    const queryString = new URLSearchParams(params).toString();
    window.location.href = route('users.export') + '?' + queryString;
};

const deleteUser = async (user) => {
    if (await Alert.confirm('Delete Staff Member?', `Permanently delete "${user.name}"? This cannot be undone.`, 'Yes, delete user')) {
        useForm({}).delete(route('users.destroy', user.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Staff Management" />
    <AuthenticatedLayout>
        <template #header>Staff Management</template>

        <div class="container-fluid my-4">
            <div class="card shadow-sm border-0 p-4 bg-white">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <h6 class="fw-bold text-muted mb-0">All Employees ({{ users.length }})</h6>
                    <div class="d-flex gap-2">
                        <button @click="exportToExcel" class="btn btn-outline-success rounded px-3">
                            <i class="bi bi-file-earmark-spreadsheet me-2"></i>Export Staff
                        </button>
                        <Link :href="route('users.create')" class="btn btn-primary rounded px-4">
                            <i class="bi bi-person-plus me-2"></i>Add User
                        </Link>
                    </div>
                </div>

                <!-- Filter Bar -->
                <div class="row g-3 mb-4 p-3 bg-light rounded-3">
                    <div class="col-md-6 col-lg-4">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                            <input v-model="form.search" type="text" placeholder="Search by name or email..." class="form-control border-start-0 shadow-none" />
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <select v-model="form.role" class="form-select shadow-none">
                            <option value="">All Roles</option>
                            <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-center">
                        <button @click="clearFilters" class="btn btn-link text-muted p-0 text-decoration-none small">
                            <i class="bi bi-x-circle me-1"></i>Clear Filters
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Employee Name</th>
                                <th>Email</th>
                                <th>Role(s)</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(user, index) in users" :key="user.id">
                                <td class="text-muted">{{ index + 1 }}</td>
                                <td class="fw-bold">
                                    <i class="bi bi-person-circle me-2 text-primary"></i>{{ user.name }}
                                </td>
                                <td class="text-muted">{{ user.email }}</td>
                                <td>
                                    <span v-for="role in user.roles" :key="role.id"
                                        class="badge me-1"
                                        :class="{
                                            'bg-danger': role.name === 'admin',
                                            'bg-warning text-dark': role.name === 'supervisor',
                                            'bg-info text-dark': role.name === 'user',
                                            'bg-secondary': !['admin','supervisor','user'].includes(role.name),
                                        }">
                                        {{ role.name }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <Link :href="route('users.edit', user.id)"
                                            class="btn btn-sm btn-outline-primary" title="Edit User">
                                            <i class="bi bi-pencil-square"></i>
                                        </Link>
                                        <button class="btn btn-sm btn-outline-danger" @click="deleteUser(user)" title="Delete User">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="users.length === 0">
                                <td colspan="5" class="text-center py-5 text-muted">No users found matching your filters.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
