<script setup>
import { ref } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';

const page = usePage();

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="BP & Co | Login" />

    <div class="login-page">
        <div class="login-card">
            <div class="login-header">
                <img src="/assets/images/logo/login-logo.png" alt="BP & Co" class="logo-clean">
                <h2>BP & Co</h2>
                <p class="text-muted">Login to your dashboard</p>
            </div>

            <div v-if="status" class="mb-4 text-sm font-medium text-green-600 text-center">
                {{ status }}
            </div>
            
            <div v-if="$page.props.errors?.error" class="mb-4 text-sm font-medium text-danger text-center">
                {{ $page.props.errors.error }}
            </div>

            <form @submit.prevent="submit">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input
                            type="email"
                            class="form-control"
                            placeholder="Enter your email"
                            v-model="form.email"
                            required
                            autofocus
                            autocomplete="username"
                        >
                    </div>
                    <div v-if="form.errors.email" class="text-danger small mt-1">
                        {{ form.errors.email }}
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input
                            :type="showPassword ? 'text' : 'password'"
                            class="form-control"
                            placeholder="Enter password"
                            v-model="form.password"
                            required
                            autocomplete="current-password"
                        >
                        <span class="input-group-text" @click="showPassword = !showPassword" style="cursor: pointer;">
                            <i class="fas" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                        </span>
                    </div>
                    <div v-if="form.errors.password" class="text-danger small mt-1">
                        {{ form.errors.password }}
                    </div>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember" v-model="form.remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>

                <button
                    type="submit"
                    class="btn btn-login w-100"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                    Login
                </button>

                <div class="text-center mt-4">
                    <div class="position-relative mb-3">
                        <hr class="text-muted">
                        <span class="position-absolute top-50 start-50 translate-middle bg-white px-2 small text-muted">OR</span>
                    </div>
                    <Link :href="route('otp.form')" class="btn btn-outline-success w-100">
                        <i class="fab fa-whatsapp me-2"></i> Login with WhatsApp OTP
                    </Link>
                </div>

            </form>
        </div>
    </div>
</template>

<style scoped>
/* Scoped styles can be added here if any specific overrides are needed for Vue */
.login-header h2 {
    margin-bottom: 0.5rem;
}
</style>
