<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    records: Array,
    year: [String, Number],
});

const selectedYear = ref(props.year);

const changeYear = () => {
    router.get(route('reports.increments'), { year: selectedYear.value }, { preserveState: true });
};
</script>

<template>
    <Head title="Annual Increments" />
    <AuthenticatedLayout>
        <div class="d-flex justify-content-between align-items-center px-4 py-2 bg-white text-dark shadow-sm w-100 mb-0">
            <h4 class="fw-bold mb-0">Annual Salary Increments</h4>
            <div class="d-flex gap-2 align-items-center">
                <label class="small fw-semibold text-muted me-1">Financial Year:</label>
                <input type="number" class="form-control form-control-sm" style="width: 100px;" v-model="selectedYear" @change="changeYear" min="2020" max="2100">
            </div>
        </div>

        <div class="container-fluid mt-4 mb-5">
            <div class="card shadow-sm border-0 p-4 bg-white">
                <div class="text-center mb-4">
                    <h3 class="fw-bold mb-1">BP and Co</h3>
                    <h5 class="text-muted">Salary Increment Report – FY {{ year }}-{{ parseInt(year) + 1 }}</h5>
                    <small class="text-muted">(April {{ year }} – March {{ parseInt(year) + 1 }})</small>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered text-center align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th class="text-start">Employee</th>
                                <th>Green Months</th>
                                <th>Out of 12</th>
                                <th>Performance</th>
                                <th>Increment %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(rec, i) in records" :key="rec.id">
                                <td>{{ i + 1 }}</td>
                                <td class="text-start fw-bold">{{ rec.user.name }}</td>
                                <td>
                                    <div class="d-flex justify-content-center align-items-center gap-1">
                                        <span class="badge bg-success">{{ rec.green_months_count }}</span>
                                        <span class="text-muted small">green</span>
                                    </div>
                                </td>
                                <td class="text-muted">12</td>
                                <td>
                                    <div class="progress" style="height: 8px; width: 100px; margin: 0 auto;">
                                        <div class="progress-bar bg-success" :style="`width: ${(rec.green_months_count / 12) * 100}%`"></div>
                                    </div>
                                    <small class="text-muted">{{ ((rec.green_months_count / 12) * 100).toFixed(0) }}%</small>
                                </td>
                                <td>
                                    <span v-if="rec.increment_percentage > 0" class="badge bg-primary fs-6 px-3">
                                        {{ rec.increment_percentage }}%
                                    </span>
                                    <span v-else class="badge bg-secondary">No Increment</span>
                                </td>
                            </tr>
                            <tr v-if="records.length === 0">
                                <td colspan="6" class="py-5 text-muted">No increment records for this financial year. Run <code>php artisan increments:calculate --year={{ year }}</code>.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 p-3 bg-light rounded small text-muted">
                    <strong>Note:</strong> Increment percentages are applied to the employee's basic salary. Run <code>php artisan increments:calculate</code> every April to compute these values.
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
