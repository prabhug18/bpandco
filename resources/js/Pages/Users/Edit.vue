<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    userToEdit: Object,
    roles: Array,
    permissions: Array
});

const form = useForm({
    name: props.userToEdit.name,
    email: props.userToEdit.email,
    mobile: props.userToEdit.mobile,
    password: '',
    password_confirmation: '',
    roles: props.userToEdit.roles.map(r => r.name),
    permissions: props.userToEdit.permissions.map(p => p.name),
});

const submit = () => {
    form.put(route('users.update', props.userToEdit.id));
};
</script>

<template>
    <Head title="Edit User Account" />
    <AuthenticatedLayout>
        <template #header>
            Edit User Profile
        </template>

        <div class="container-fluid py-4 max-w-7xl mx-auto">
            
            <div class="mb-4">
                <Link :href="route('users.index')" class="text-decoration-none fw-bold text-muted">
                    <i class="bi bi-arrow-left me-1"></i> Back to Staff Directory
                </Link>
            </div>

            <form @submit.prevent="submit" class="row g-4 align-items-start" autocomplete="off">
                
                <!-- Left Column: User Info & Security -->
                <div class="col-lg-7">
                    <div class="premium-card p-4">
                        <h5 class="fw-bold title-font text-primary mb-4 border-bottom pb-2">
                            <i class="bi bi-person-lines-fill me-2"></i> Account Details
                        </h5>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-muted small">FULL NAME</label>
                                <input type="text" v-model="form.name" class="form-control glass-input" required autocomplete="off">
                                <small class="text-danger" v-if="form.errors.name">{{ form.errors.name }}</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-muted small">EMAIL ADDRESS</label>
                                <input type="email" v-model="form.email" class="form-control glass-input" required autocomplete="off">
                                <small class="text-danger" v-if="form.errors.email">{{ form.errors.email }}</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-muted small">MOBILE NUMBER</label>
                                <input type="text" v-model="form.mobile" class="form-control glass-input" placeholder="(Optional)">
                                <small class="text-danger" v-if="form.errors.mobile">{{ form.errors.mobile }}</small>
                            </div>
                        </div>

                        <h5 class="fw-bold title-font text-danger mt-5 mb-4 border-bottom pb-2">
                            <i class="bi bi-shield-lock me-2"></i> Security / Reset Password
                        </h5>
                        <p class="small text-muted mb-3 fst-italic">Leave blank if you do not wish to change the current password.</p>
                        
                        <!-- Browser Decoy to prevent prefill -->
                        <input type="password" style="display:none;" name="browser-decoy" />

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-muted small">NEW PASSWORD</label>
                                <input type="password" v-model="form.password" class="form-control glass-input" 
                                    autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly');">
                                <small class="text-danger" v-if="form.errors.password">{{ form.errors.password }}</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-muted small">CONFIRM PASSWORD</label>
                                <input type="password" v-model="form.password_confirmation" class="form-control glass-input" 
                                    autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly');">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Access Control -->
                <div class="col-lg-5">
                    <div class="premium-card p-4 h-100">
                        <h5 class="fw-bold title-font text-success mb-4 border-bottom pb-2">
                            <i class="bi bi-key-fill me-2"></i> Access & Roles
                        </h5>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted small">ASSIGN ROLES</label>
                            <div class="glass-input p-3 border rounded bg-light" style="max-height: 200px; overflow-y: auto;">
                                <div v-for="role in roles" :key="role.id" class="form-check mb-2">
                                    <input class="form-check-input cursor-pointer shadow-none" type="checkbox" :value="role.name" :id="'role_'+role.id" v-model="form.roles">
                                    <label class="form-check-label fw-bold cursor-pointer text-capitalize" :for="'role_'+role.id">
                                        {{ role.name }}
                                    </label>
                                </div>
                                <div v-if="!roles.length" class="text-muted small fst-italic">No roles defined in the system.</div>
                            </div>
                            <small class="text-danger" v-if="form.errors.roles">{{ form.errors.roles }}</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted small">DIRECT PERMISSIONS (ADVANCED)</label>
                            <p class="small text-muted mb-2 lh-sm">Check these to assign specific granular permissions bypassing the core Roles above.</p>
                            <div class="glass-input p-3 border rounded bg-light" style="max-height: 250px; overflow-y: auto;">
                                <div v-for="permission in permissions" :key="permission.id" class="form-check mb-2">
                                    <input class="form-check-input cursor-pointer shadow-none" type="checkbox" :value="permission.name" :id="'perm_'+permission.id" v-model="form.permissions">
                                    <label class="form-check-label fw-semibold cursor-pointer w-100 small" :for="'perm_'+permission.id">
                                        {{ permission.name }}
                                    </label>
                                </div>
                            </div>
                            <small class="text-danger" v-if="form.errors.permissions">{{ form.errors.permissions }}</small>
                        </div>

                        <div class="mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary premium-btn w-100 fw-bold py-2" :disabled="form.processing">
                                <i class="bi bi-check2-circle me-1"></i> Update User Profile
                            </button>
                        </div>
                    </div>
                </div>

            </form>
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
.premium-btn {
    border-radius: 8px;
    transition: all 0.3s;
}
.premium-btn:hover {
    box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
    transform: translateY(-2px);
}
</style>
