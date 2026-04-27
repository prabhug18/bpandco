<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    roles: Array
});

const form = useForm({
    name: '',
    email: '',
    mobile: '',
    password: '',
    password_confirmation: '',
    role: ''
});

const submit = () => {
    form.post(route('users.store'), {
        onSuccess: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Add User" />

    <AuthenticatedLayout>
        <div class="d-flex justify-content-between align-items-center px-4 py-2 bg-white text-dark shadow-sm w-100">
            <h4 class="fw-bold mb-0">Add User</h4>
        </div>

        <div class="container-fluid my-5">
            <div class="card form-card shadow-lg border-0">
                <div class="card-body p-5">
                    <form @submit.prevent="submit" autocomplete="off">
                        <!-- Section 1: User Details -->
                        <div class="section-style mb-4">
                            <h5 class="fw-bold mb-3 border-bottom pb-2">User Details</h5>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" v-model="form.name" class="form-control custom-input" placeholder="Enter Name" required autocomplete="off" />
                                    <div v-if="form.errors.name" class="text-danger mt-1 small">{{ form.errors.name }}</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" v-model="form.email" class="form-control custom-input" placeholder="Enter Email" required autocomplete="off" />
                                    <div v-if="form.errors.email" class="text-danger mt-1 small">{{ form.errors.email }}</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Role <span class="text-danger">*</span></label>
                                    <select v-model="form.role" class="form-select custom-input" required>
                                        <option value="" disabled selected>Select Role</option>
                                        <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
                                    </select>
                                    <div v-if="form.errors.role" class="text-danger mt-1 small">{{ form.errors.role }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Login Credentials -->
                        <div class="section-style mb-4">
                            <h5 class="fw-bold mb-3 border-bottom pb-2">Login Credentials</h5>
                            
                            <!-- Browser Decoy to prevent prefill -->
                            <input type="password" style="display:none;" name="browser-decoy" />

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" v-model="form.password" 
                                        class="form-control custom-input" placeholder="Enter Password" 
                                        required minlength="6" autocomplete="new-password"
                                        readonly onfocus="this.removeAttribute('readonly');" />
                                    <div v-if="form.errors.password" class="text-danger mt-1 small">{{ form.errors.password }}</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" v-model="form.password_confirmation" 
                                        class="form-control custom-input" placeholder="Re-enter Password" 
                                        required autocomplete="new-password"
                                        readonly onfocus="this.removeAttribute('readonly');" />
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary px-5 py-2 mt-3" :disabled="form.processing">
                                Submit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.custom-input {
    border-radius: 6px;
    border: 1px solid #ced4da;
    padding: 0.5rem;
}
.section-style h5 {
    color: #495057;
}
</style>
