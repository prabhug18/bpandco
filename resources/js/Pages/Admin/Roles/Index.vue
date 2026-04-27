<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Alert from '@/Utils/Alert';

const props = defineProps({
    roles: Array,
    permissions: Array,
});

const isEditing = ref(false);
const showForm = ref(false);

const form = useForm({
    id: null,
    name: '',
    permissions: [],
});

const openCreate = () => {
    form.reset();
    form.clearErrors();
    isEditing.value = false;
    showForm.value = true;
};

const openEdit = (role) => {
    form.reset();
    form.clearErrors();
    form.id = role.id;
    form.name = role.name;
    form.permissions = role.permissions.map(p => p.name);
    isEditing.value = true;
    showForm.value = true;
};

const closeForm = () => {
    showForm.value = false;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('admin.roles.update', form.id), {
            onSuccess: () => closeForm(),
        });
    } else {
        form.post(route('admin.roles.store'), {
            onSuccess: () => closeForm(),
        });
    }
};

const deleteRole = async (id) => {
    if (await Alert.confirm('Delete Role?', 'Are you sure you want to delete this role? This will remove access for all users assigned to it.')) {
        form.delete(route('admin.roles.destroy', id));
    }
};
</script>

<template>
    <Head title="Roles & Permissions" />
    <AuthenticatedLayout>
        <template #header>
            Roles & Permissions Matrix
        </template>

        <div class="container-fluid py-4 max-w-7xl mx-auto">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold text-dark title-font mb-1">Access Control</h4>
                    <p class="text-muted small mb-0">Define organizational roles and strict permission rules.</p>
                </div>
                <button class="btn btn-primary premium-btn px-4 bg-primary text-white" @click="openCreate">
                    <i class="bi bi-shield-plus me-1"></i> Create Role
                </button>
            </div>

            <div class="row g-4">
                <!-- Data Display -->
                <div :class="showForm ? 'col-lg-7' : 'col-12'" style="transition: all 0.3s;">
                    <div class="row g-4">
                        <div v-for="role in roles" :key="role.id" class="col-md-6 col-xl-4">
                            <div class="premium-card h-100 p-4 d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-sm rounded bg-light-primary text-primary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="bi bi-shield-lock-fill fs-5"></i>
                                        </div>
                                        <h5 class="fw-bold mb-0 text-capitalize title-font">{{ role.name }}</h5>
                                    </div>
                                </div>
                                
                                <div class="flex-grow-1 mb-3">
                                    <p class="small text-muted fw-bold mb-2 text-uppercase" style="letter-spacing: 1px;">Permissions Assigned ({{ role.permissions.length }})</p>
                                    <div class="d-flex flex-wrap gap-1">
                                        <span v-for="perm in role.permissions" :key="perm.id" class="badge bg-secondary opacity-75 fw-normal">
                                            {{ perm.name }}
                                        </span>
                                        <span v-if="!role.permissions.length" class="text-muted small fst-italic">No permissions assigned.</span>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2 border-top pt-3 mt-auto">
                                    <button class="btn btn-sm btn-light text-primary fw-bold px-3 shadow-sm hover-lift" @click="openEdit(role)">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-light text-danger fw-bold shadow-sm hover-lift" @click="deleteRole(role.id)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Slide Form Panel -->
                <div v-if="showForm" class="col-lg-5">
                    <div class="premium-card p-4 sticky-top" style="top: 20px;">
                        <div class="d-flex justify-content-between mb-4">
                            <h5 class="fw-bold title-font text-primary">
                                <i class="bi bi-shield-lock me-2"></i>{{ isEditing ? 'Edit Access Role' : 'Create Access Role' }}
                            </h5>
                            <button class="btn-close shadow-none" @click="closeForm"></button>
                        </div>

                        <form @submit.prevent="submit">
                            <div class="mb-4">
                                <label class="form-label fw-bold text-muted small">ROLE NAME</label>
                                <input type="text" v-model="form.name" class="form-control glass-input" placeholder="e.g. Sales Executive" required>
                                <small v-if="form.errors.name" class="text-danger">{{ form.errors.name }}</small>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-muted small">ASSIGN SYSTEM PERMISSIONS</label>
                                <div class="glass-input p-3 border rounded shadow-sm bg-light" style="max-height: 250px; overflow-y: auto;">
                                    <div v-for="permission in permissions" :key="permission.id" class="form-check mb-2">
                                        <input class="form-check-input shadow-none cursor-pointer" type="checkbox" :value="permission.name" :id="'perm_'+permission.id" v-model="form.permissions">
                                        <label class="form-check-label fw-semibold cursor-pointer w-100" :for="'perm_'+permission.id">
                                            {{ permission.name }}
                                        </label>
                                    </div>
                                </div>
                                <small v-if="form.errors.permissions" class="text-danger">{{ form.errors.permissions }}</small>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-light fw-bold w-50" @click="closeForm">Cancel</button>
                                <button type="submit" class="btn btn-primary fw-bold w-50 shadow-sm" :disabled="form.processing">
                                    {{ isEditing ? 'Update Role' : 'Save Role' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.title-font {
    font-family: 'Outfit', sans-serif;
    letter-spacing: 0.5px;
}
.premium-card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    border: 1px solid rgba(0,0,0,0.05);
}
.bg-light-primary {
    background-color: rgba(13, 110, 253, 0.1) !important;
}
.glass-input {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 6px;
    transition: all 0.3s;
}
.glass-input:focus {
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
    border-color: #0d6efd;
    outline: none;
}
.cursor-pointer {
    cursor: pointer;
}
.hover-lift {
    transition: transform 0.2s;
}
.hover-lift:hover {
    transform: translateY(-2px);
}
.premium-btn {
    border-radius: 8px;
    transition: all 0.3s;
}
.premium-btn:hover {
    box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
    transform: translateY(-2px);
}
</style>
