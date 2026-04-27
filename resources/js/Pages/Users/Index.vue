<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import Alert from '@/Utils/Alert';

const props = defineProps({
    users: { type: Array, required: true },
});

const deleteUser = async (user) => {
    if (await Alert.confirm('Delete Staff Member?', `Permanently delete "${user.name}"? This cannot be undone.`, 'Yes, delete user')) {
        useForm({}).delete(route('users.destroy', user.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Staff Management" />
    <AuthenticatedLayout>
        <div class="d-flex justify-content-between align-items-center px-4 py-2 bg-white text-dark shadow-sm w-100">
            <h4 class="fw-bold mb-0">Staff Management</h4>
        </div>

        <div class="container-fluid my-5">

            <div class="card shadow-sm border-0 p-4 bg-white">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-muted mb-0">All Employees ({{ users.length }})</h6>
                    <Link :href="route('users.create')" class="btn btn-primary rounded px-4">
                        <i class="bi bi-person-plus me-2"></i>Add User
                    </Link>
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
                                <td colspan="5" class="text-center py-5 text-muted">No users found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
