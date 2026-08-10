<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    leaderboard: Array,
    month: String,     // YYYY-MM
    monthName: String, // e.g. "JUNE GREENSCORE-2026"
});

const selectedMonth = ref(props.month);

const applyFilter = () => {
    router.get(route('reports.greenscore'), {
        month: selectedMonth.value,
    }, { preserveState: true });
};

// Generate list of human-readable month options for the dropdown
const monthOptions = computed(() => {
    const options = [];
    const [currentYr, currentMo] = props.month.split('-').map(Number);
    const baseDate = new Date(currentYr, currentMo - 1, 1);

    for (let i = -12; i <= 6; i++) {
        const d = new Date(baseDate.getFullYear(), baseDate.getMonth() + i, 1);
        const yr = d.getFullYear();
        const mo = String(d.getMonth() + 1).padStart(2, '0');
        const val = `${yr}-${mo}`;
        const label = d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        options.push({ val, label });
    }
    return options.reverse();
});

// Shift month forward (+1) or backward (-1)
const shiftMonth = (offset) => {
    const [yr, mo] = selectedMonth.value.split('-').map(Number);
    const d = new Date(yr, mo - 1 + offset, 1);
    const newYr = d.getFullYear();
    const newMo = String(d.getMonth() + 1).padStart(2, '0');
    selectedMonth.value = `${newYr}-${newMo}`;
    applyFilter();
};

const printReport = () => { window.print(); };
</script>

<template>
    <Head title="Greenscore Leaderboard Report" />
    <AuthenticatedLayout>
        <!-- Top Toolbar -->
        <div class="d-flex justify-content-between align-items-center px-4 py-2 bg-white text-dark shadow-sm w-100 mb-0 d-print-none">
            <h4 class="fw-bold mb-0 banner-title">
                <i class="bi bi-trophy me-2 text-warning"></i>Greenscore Hall of Fame Leaderboard
            </h4>
            
            <div class="d-flex align-items-center gap-2">
                <!-- Easy Month Controls -->
                <div class="d-flex align-items-center bg-light p-1 rounded-pill border shadow-sm gap-1">
                    <button class="btn btn-sm btn-white rounded-circle shadow-sm px-2 py-1 text-dark" 
                            title="Previous Month" 
                            @click="shiftMonth(-1)">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    
                    <select class="form-select form-select-sm border-0 bg-transparent fw-bold text-center pe-4" 
                            style="width: 170px; cursor: pointer; color: #003287;" 
                            v-model="selectedMonth" 
                            @change="applyFilter">
                        <option v-for="opt in monthOptions" :key="opt.val" :value="opt.val">
                            {{ opt.label }}
                        </option>
                    </select>

                    <button class="btn btn-sm btn-white rounded-circle shadow-sm px-2 py-1 text-dark" 
                            title="Next Month" 
                            @click="shiftMonth(1)">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>

                <!-- Print Button -->
                <button class="btn btn-primary shadow-sm rounded-pill px-4 ms-2" @click="printReport">
                    <i class="bi bi-printer me-1"></i> Print
                </button>
            </div>
        </div>

        <div class="p-4" style="background: #f7f9fc; min-height: 100vh;">
            <div class="card shadow-lg border-0 p-0 bg-white mx-auto table-print-container" style="max-width: 950px; border-radius: 12px; overflow: hidden;">
                
                <!-- Image 1 Banner Layout Header -->
                <div class="greenscore-header text-center">
                    <!-- Top Title Banner -->
                    <div class="py-3 fs-3 fw-bold text-dark text-uppercase tracking-wider shadow-sm" style="background-color: #f6c096; letter-spacing: 2px;">
                        {{ monthName.split(' ')[0] }} GREENSCORE-{{ monthName.split(' ')[1] || new Date().getFullYear() }}
                    </div>
                    <!-- Green Accent Strip -->
                    <div style="background-color: #1b8046; height: 35px;" class="w-100 shadow-inner"></div>
                    <!-- Sub Header -->
                    <div class="py-2 fs-5 fw-bold text-dark tracking-widest" style="background-color: #e59a68; letter-spacing: 3px;">
                        B.P.&CO
                    </div>
                    <!-- Section Title -->
                    <div class="py-2 fs-5 fw-bold text-dark tracking-widest border-bottom border-dark" style="background-color: #e59a68; letter-spacing: 3px;">
                        HIGHEST SCORE
                    </div>
                </div>

                <!-- Leaderboard Table -->
                <div class="table-responsive p-0">
                    <table class="table table-bordered text-center align-middle m-0 greenscore-table">
                        <thead>
                            <tr style="background-color: #f7dac4; font-weight: bold;">
                                <th style="width: 10%; font-size: 1.1rem;">S.NO</th>
                                <th style="width: 30%; font-size: 1.1rem;">NAME</th>
                                <th style="width: 30%; font-size: 1.1rem;">DATA</th>
                                <th style="width: 30%; font-size: 1.1rem;">RESULTS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, idx) in leaderboard" :key="idx" class="table-row-hover">
                                <td class="fw-bold fs-5 text-dark">{{ item.sno }}</td>
                                <td class="fw-bold fs-5 text-uppercase text-dark">{{ item.name }}</td>
                                <td class="fw-bold fs-5 text-dark font-monospace">{{ item.data }}</td>
                                <td class="fw-bold fs-5 text-dark">{{ item.results }}</td>
                            </tr>
                            <tr v-if="leaderboard.length === 0">
                                <td colspan="4" class="text-center py-5 text-muted fw-bold fs-5">
                                    No highest scores recorded for {{ monthName }}.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Seal -->
                <div class="p-3 text-center bg-light border-top text-muted small fw-bold text-uppercase d-print-none">
                    Verified Performance Report &bull; B.P.&CO Management System
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@500;600;700&display=swap');

.banner-title {
    font-family: 'Outfit', sans-serif;
    color: #003287;
}

.greenscore-table {
    font-family: 'Inter', sans-serif;
    border-collapse: collapse;
}

.greenscore-table th, .greenscore-table td {
    border: 2px solid #333 !important;
    padding: 12px 16px !important;
}

.table-row-hover:hover {
    background-color: rgba(246, 192, 150, 0.2) !important;
}

@media print {
    body { background: white !important; }
    .table-print-container { max-width: 100% !important; margin: 0 !important; box-shadow: none !important; border: none !important; }
}
</style>
