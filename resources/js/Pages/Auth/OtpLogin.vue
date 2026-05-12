<script setup>
import { ref } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const otpSent = ref(page.props.flash?.otp_sent || false);
const mobileNumber = ref(page.props.flash?.mobile || '');

const phoneForm = useForm({
    mobile: '',
});

const otpForm = useForm({
    mobile: mobileNumber.value,
    otp: '',
    remember: false,
});

const sendOtp = () => {
    phoneForm.post(route('otp.send'), {
        onSuccess: (page) => {
            if (page.props.flash?.otp_sent) {
                otpSent.value = true;
                mobileNumber.value = page.props.flash.mobile;
                otpForm.mobile = mobileNumber.value;
            }
        },
    });
};

const verifyOtp = () => {
    otpForm.post(route('otp.verify'));
};

const resendOtp = () => {
    phoneForm.mobile = mobileNumber.value;
    sendOtp();
};

const backToMobile = () => {
    otpSent.value = false;
    otpForm.reset();
};
</script>

<template>
    <Head title="BP & Co | Login with OTP" />

    <div class="login-page">
        <div class="login-card">
            <div class="login-header">
                <img src="/assets/images/logo/login-logo.png" alt="BP & Co" class="logo-clean">
                <h2>BP & Co</h2>
                <p class="text-muted">Login with WhatsApp OTP</p>
            </div>

            <div v-if="$page.props.flash?.success" class="mb-4 text-sm font-medium text-green-600 text-center">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.errors?.error" class="mb-4 text-sm font-medium text-red-600 text-center">
                {{ $page.props.errors.error }}
            </div>

            <!-- Step 1: Request OTP -->
            <form v-if="!otpSent" @submit.prevent="sendOtp">
                <div class="mb-4">
                    <label class="form-label">Mobile Number</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-mobile-alt"></i></span>
                        <input
                            type="text"
                            class="form-control"
                            placeholder="Enter mobile number"
                            v-model="phoneForm.mobile"
                            required
                            autofocus
                        >
                    </div>
                    <div v-if="phoneForm.errors.mobile" class="text-danger small mt-1">
                        {{ phoneForm.errors.mobile }}
                    </div>
                    <small class="text-muted mt-2 d-block">We will send a 6-digit OTP to your WhatsApp.</small>
                </div>

                <button
                    type="submit"
                    class="btn btn-login w-100 mb-3"
                    :disabled="phoneForm.processing"
                >
                    <span v-if="phoneForm.processing" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                    Send WhatsApp OTP
                </button>
                
                <div class="text-center mt-3">
                    <Link :href="route('login')" class="text-decoration-none small text-muted">
                        <i class="fas fa-arrow-left me-1"></i> Back to Email Login
                    </Link>
                </div>
            </form>

            <!-- Step 2: Verify OTP -->
            <form v-else @submit.prevent="verifyOtp">
                <div class="text-center mb-4">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <span class="badge bg-light text-dark border">{{ mobileNumber }}</span>
                        <button type="button" @click="backToMobile" class="btn btn-sm btn-link text-decoration-none ms-2" title="Change number">
                            <i class="fas fa-pencil-alt text-muted"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label text-center d-block">Enter 6-digit OTP</label>
                    <input
                        type="text"
                        class="form-control text-center fs-4 letter-spacing-2"
                        placeholder="••••••"
                        v-model="otpForm.otp"
                        maxlength="6"
                        required
                        autofocus
                    >
                    <div v-if="otpForm.errors.otp" class="text-danger small mt-1 text-center">
                        {{ otpForm.errors.otp }}
                    </div>
                </div>

                <div class="mb-3 form-check text-start">
                    <input type="checkbox" class="form-check-input" id="remember" v-model="otpForm.remember">
                    <label class="form-check-label small" for="remember">Remember me</label>
                </div>

                <button
                    type="submit"
                    class="btn btn-login w-100 mb-3"
                    :disabled="otpForm.processing"
                >
                    <span v-if="otpForm.processing" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                    Verify & Login
                </button>
                
                <div class="text-center mt-3">
                    <button type="button" @click="resendOtp" class="btn btn-link text-decoration-none small" :disabled="phoneForm.processing">
                        Resend OTP
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.login-header h2 {
    margin-bottom: 0.5rem;
}
.letter-spacing-2 {
    letter-spacing: 0.5rem;
}
</style>
