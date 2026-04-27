<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Alert from '@/Utils/Alert';

const props = defineProps({
    records: Array,
    month: String,
});

const selectedMonth = ref(props.month);

const changeMonth = () => {
    router.get(route('reports.incentives'), { month: selectedMonth.value }, { preserveState: true });
};

const markPaid = async (id) => {
    if (await Alert.confirm('Mark as Paid?', 'Are you sure you want to mark this incentive as paid?', 'Yes, mark as paid')) {
        useForm({}).patch(route('reports.incentives.paid', id), { preserveScroll: true });
    }
};

const total = () => props.records.reduce((sum, r) => sum + parseFloat(r.incentive_amount || 0), 0);
</script>

<template>
    <Head title="Incentive Payables" />
    <AuthenticatedLayout>
        <div class="d-flex justify-content-between align-items-center px-4 py-2 bg-white text-dark shadow-sm w-100 mb-0">
            <h4 class="fw-bold mb-0">Monthly Incentive Payables</h4>
            <div class="d-flex gap-2 align-items-center">
                <input type="month" class="form-control form-control-sm" v-model="selectedMonth" @change="changeMonth">
            </div>
        </div>

        <div class="container-fluid mt-4 mb-5">

            <div class="card shadow-sm border-0 p-4 bg-white">
                <div class="text-center mb-4">
                    <h3 class="fw-bold mb-1">BP and Co</h3>
                    <h5 class="text-muted">Incentive Payables – {{ month }}</h5>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered text-center align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th class="text-start">Employee</th>
                                <th>Monthly Score</th>
                                <th>Consecutive Green Months</th>
                                <th>Incentive Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(rec, i) in records" :key="rec.id">
                                <td>{{ i + 1 }}</td>
                                <td class="text-start fw-bold">{{ rec.user.name }}</td>
                                <td>{{ rec.total_score }}</td>
                                <td>{{ rec.consecutive_green_months }} months</td>
                                <td class="fw-bold fs-5 text-success">₹{{ parseFloat(rec.incentive_amount).toLocaleString() }}</td>
                                <td>
                                    <span class="badge" :class="rec.status === 'paid' ? 'bg-success' : 'bg-warning text-dark'">
                                        {{ rec.status.toUpperCase() }}
                                    </span>
                                </td>
                                <td>
                                    <button v-if="rec.status !== 'paid'" class="btn btn-sm btn-success" @click="markPaid(rec.id)">
                                        Mark Paid
                                    </button>
                                    <span v-else class="text-muted small">—</span>
                                </td>
                            </tr>
                            <tr v-if="records.length === 0">
                                <td colspan="7" class="py-5 text-muted">No incentive records for this month.</td>
                            </tr>
                        </tbody>
                        <tfoot class="table-info fw-bold" v-if="records.length > 0">
                            <tr>
                                <td colspan="4" class="text-end">Total Payable:</td>
                                <td class="text-success fs-5">₹{{ total().toLocaleString() }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
