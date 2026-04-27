<script setup>
import { ref, watch, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Alert from '@/Utils/Alert';

const isSidebarCollapsed = ref(false);
const isMobileActive = ref(false);
const showProfileCard = ref(false);
const showQuickLinks = ref(false);

const isReportsOpen = ref(false);
const isAdminOpen = ref(false);

const toggleSidebar = () => {
    const isMobile = window.innerWidth <= 768;
    if (isMobile) {
        isMobileActive.value = !isMobileActive.value;
        isSidebarCollapsed.value = false;
    } else {
        isSidebarCollapsed.value = !isSidebarCollapsed.value;
        isMobileActive.value = false;
    }
};

const logout = () => {
    router.post(route('logout'));
};

const page = usePage();

// Watch for flash messages
watch(() => page.props.flash, (flash) => {
    if (flash?.success) {
        Alert.toast(flash.success, 'success');
    }
    if (flash?.error) {
        Alert.toast(flash.error, 'error');
    }
}, { deep: true, immediate: true });
</script>

<template>
    <div class="min-h-screen app-wrapper">
        <!-- Sidebar Toggle -->
        <input type="checkbox" id="checkbox" hidden :checked="isSidebarCollapsed || isMobileActive">
        <label for="checkbox" class="toggle-btn" id="toggleBtn" @click.prevent="toggleSidebar">
            <div class="bars" id="bar1"></div>
            <div class="bars" id="bar2"></div>
            <div class="bars" id="bar3"></div>
        </label>

        <!-- Sidebar -->
        <nav class="sidebar" :class="{ 'collapsed': isSidebarCollapsed, 'active': isMobileActive }" id="sidebar">
            <div class="d-flex align-items-center justify-content-center" style="height: 80px; margin-bottom: 10px;">
                <img src="/assets/images/logo/login-logo.png" alt="BP & Co" style="max-height: 50px; max-width: 80%;" v-if="!isSidebarCollapsed">
            </div>
            <ul class="mt-2 text-dark">
                <li>
                    <Link :href="route('dashboard')" :class="{ 'active': route().current('dashboard') }">
                        <i class="bi bi-speedometer2"></i><span>Dashboard</span>
                    </Link>
                </li>
                <li v-if="$page.props.auth.user.permissions.includes('submit slips')">
                    <Link :href="route('attendance.index')" :class="{ 'active': route().current('attendance.*') }">
                        <i class="bi bi-geo-alt"></i><span>Daily Attendance</span>
                    </Link>
                </li>

                <li v-if="$page.props.auth.user.permissions.includes('submit slips')">
                    <Link :href="route('slips.index')" :class="{ 'active': route().current('slips.*') }">
                        <i class="bi bi-file-earmark-plus"></i><span>Daily Slips</span>
                    </Link>
                </li>

                <li v-if="$page.props.auth.user.permissions.includes('approve slips')">
                    <Link :href="route('approvals.index')" :class="{ 'active': route().current('approvals.*') }">
                        <i class="bi bi-check2-circle"></i><span>Approval Queue</span>
                    </Link>
                </li>

                <li v-if="$page.props.auth.user.permissions.includes('manage users')">
                    <Link :href="route('users.index')" :class="{ 'active': route().current('users.*') }">
                        <i class="bi bi-people"></i><span>Staff Management</span>
                    </Link>
                </li>

                <!-- Reporting Submenu -->
                <li class="has-submenu" :class="{ 'open': isReportsOpen }">
                    <a href="javascript:void(0)" class="submenu-toggle" @click="isReportsOpen = !isReportsOpen">
                        <i class="bi bi-bar-chart-line"></i><span>Performance Reports</span><i class="bi bi-chevron-down arrow ms-auto" :style="{ transform: isReportsOpen ? 'rotate(180deg)' : 'none', transition: '0.3s' }"></i>
                    </a>
                    <ul class="submenu ps-3" :class="{ 'd-block': isReportsOpen, 'd-none': !isReportsOpen }">
                        <li>
                            <Link :href="route('reports.individual')" :class="{ 'active': route().current('reports.individual') }">
                                <i class="bi bi-file-earmark-spreadsheet"></i><span> My Report</span>
                            </Link>
                        </li>
                        <li v-if="$page.props.auth.user.permissions.includes('view team reports')">
                            <Link :href="route('reports.team')" :class="{ 'active': route().current('reports.team') }">
                                <i class="bi bi-grid-3x3-gap"></i><span> Team Summary</span>
                            </Link>
                        </li>
                        <li v-if="$page.props.auth.user.permissions.includes('configure incentives')">
                            <Link :href="route('reports.incentives')" :class="{ 'active': route().current('reports.incentives') }">
                                <i class="bi bi-cash-coin"></i><span> Incentive Payables</span>
                            </Link>
                        </li>
                        <li v-if="$page.props.auth.user.permissions.includes('configure incentives')">
                            <Link :href="route('reports.increments')" :class="{ 'active': route().current('reports.increments') }">
                                <i class="bi bi-graph-up-arrow"></i><span> Salary Increments</span>
                            </Link>
                        </li>
                    </ul>
                </li>

                <!-- Admin Settings Submenu -->
                <li class="has-submenu" :class="{ 'open': isAdminOpen }" 
                    v-if="$page.props.auth.user.permissions.some(p => ['manage roles', 'configure metrics', 'configure scoring tiers', 'configure holidays', 'configure incentives', 'manage system settings'].includes(p))">
                    <a href="javascript:void(0)" class="submenu-toggle" @click="isAdminOpen = !isAdminOpen">
                        <i class="bi bi-sliders"></i><span>Backend Config</span><i class="bi bi-chevron-down arrow ms-auto" :style="{ transform: isAdminOpen ? 'rotate(180deg)' : 'none', transition: '0.3s' }"></i>
                    </a>
                    <ul class="submenu ps-3" :class="{ 'd-block': isAdminOpen, 'd-none': !isAdminOpen }">
                        <li v-if="$page.props.auth.user.permissions.includes('manage roles')">
                            <Link :href="route('admin.roles.index')" :class="{ 'active': route().current('admin.roles.*') }">
                                <i class="bi bi-shield-lock"></i><span> Roles & Permissions</span>
                            </Link>
                        </li>
                        <li v-if="$page.props.auth.user.permissions.includes('configure metrics')">
                            <Link :href="route('admin.metrics.index')" :class="{ 'active': route().current('admin.metrics.*') }">
                                <i class="bi bi-gear"></i><span> Metrics & Roles</span>
                            </Link>
                        </li>
                        <li v-if="$page.props.auth.user.permissions.includes('configure scoring tiers')">
                            <Link :href="route('admin.scoring-tiers.index')" :class="{ 'active': route().current('admin.scoring-tiers.*') }">
                                <i class="bi bi-bullseye"></i><span> Targets & Tiers</span>
                            </Link>
                        </li>
                        <li v-if="$page.props.auth.user.permissions.includes('configure holidays')">
                            <Link :href="route('admin.holidays.index')" :class="{ 'active': route().current('admin.holidays.*') }">
                                <i class="bi bi-calendar-check"></i><span> Holidays & Leaves</span>
                            </Link>
                        </li>
                        <li v-if="$page.props.auth.user.permissions.includes('configure incentives')">
                            <Link :href="route('admin.incentives.index')" :class="{ 'active': route().current('admin.incentives.*') }">
                                <i class="bi bi-gift"></i><span> Incentive Slabs</span>
                            </Link>
                        </li>
                        <li v-if="$page.props.auth.user.permissions.includes('manage system settings')">
                            <Link :href="route('admin.settings.index')" :class="{ 'active': route().current('admin.settings.*') }">
                                <i class="bi bi-cpu"></i><span> System Settings</span>
                            </Link>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>

        <div class="main-content d-flex flex-column min-vh-100" :class="{ 'expanded': isSidebarCollapsed }" id="mainContent">
            <!-- Header -->
            <div id="mainHeader" class="d-flex justify-content-between align-items-center px-4 py-2 bg-white text-dark shadow-sm w-100">
                <div class="d-flex align-items-center flex-grow-1 overflow-hidden">
                    <h4 class="fw-bold mb-0 ms-5 ms-md-0 text-truncate header-title">
                        <slot name="header" />
                    </h4>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="profile-icon">
                        <i class="bi bi-grid fs-4 me-3" title="Quick Links" style="cursor:pointer;" @click="showQuickLinks = !showQuickLinks"></i>
                        <i class="bi bi-person-circle fs-4" title="Admin Profile" style="cursor:pointer;" @click="showProfileCard = !showProfileCard"></i>
                    </div>
                </div>
            </div>

            <!-- Profile Card -->
            <div id="profileCard" class="premium-profile-card" :class="{ 'show': showProfileCard }">
                <div class="card-glass-header border-bottom p-4 text-center text-white" style="background: linear-gradient(135deg, #0f172a, #334155); border-radius: 20px 20px 0 0;">
                    <div class="user-avatar-placeholder mx-auto mb-3 shadow-sm d-flex align-items-center justify-content-center bg-white text-primary fw-bold" style="width: 65px; height: 65px; border-radius: 50%; font-size: 1.5rem;">
                        {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                    </div>
                    <h5 class="fw-bold mb-1">{{ $page.props.auth.user.name }}</h5>
                    <small class="text-white-50 letter-spacing-1 text-uppercase" style="font-size: 0.75rem;">{{ $page.props.auth.user.roles[0] || 'User' }}</small>
                </div>
                
                <div class="p-3 bg-white" style="border-radius: 0 0 20px 20px;">
                    <div class="d-flex align-items-center px-3 py-2 text-muted mb-3 rounded-3" style="background: rgba(0,0,0,0.03);">
                        <i class="bi bi-envelope-at fs-5 me-3 text-secondary"></i>
                        <span class="small fw-semibold title-font" style="word-break: break-all;">{{ $page.props.auth.user.email }}</span>
                    </div>
                    
                    <button @click="logout" class="btn btn-outline-danger w-100 fw-bold d-flex align-items-center justify-content-center gap-2 py-2 rounded-3 hover-lift">
                        <i class="bi bi-box-arrow-right"></i> Secure Logout
                    </button>
                </div>
            </div>

            <!-- Quick Links -->
            <div id="quickLinkPopup" class="quicklink-popup shadow-lg rounded-4 p-4 bg-white" :class="{ 'show': showQuickLinks }">
                <h6 class="text-muted small fw-bold mb-3 text-uppercase letter-spacing-1">Quick Access</h6>
                <div class="row g-3 text-center">
                    <div class="col-4">
                        <Link :href="route('dashboard')" class="quick-link-item" @click="showQuickLinks = false">
                            <div class="quick-icon bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-speedometer2"></i>
                            </div>
                            <small class="fw-bold">Dash</small>
                        </Link>
                    </div>
                    <div class="col-4" v-if="$page.props.auth.user.permissions.includes('submit slips')">
                        <Link :href="route('attendance.index')" class="quick-link-item" @click="showQuickLinks = false">
                            <div class="quick-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-geo-alt"></i>
                            </div>
                            <small class="fw-bold">Atten</small>
                        </Link>
                    </div>
                    <div class="col-4" v-if="$page.props.auth.user.permissions.includes('submit slips')">
                        <Link :href="route('slips.index')" class="quick-link-item" @click="showQuickLinks = false">
                            <div class="quick-icon bg-info bg-opacity-10 text-info">
                                <i class="bi bi-file-earmark-plus"></i>
                            </div>
                            <small class="fw-bold">Slips</small>
                        </Link>
                    </div>
                    <div class="col-4" v-if="$page.props.auth.user.permissions.includes('approve slips')">
                        <Link :href="route('approvals.index')" class="quick-link-item" @click="showQuickLinks = false">
                            <div class="quick-icon bg-danger bg-opacity-10 text-danger">
                                <i class="bi bi-check2-circle"></i>
                            </div>
                            <small class="fw-bold">Appr</small>
                        </Link>
                    </div>
                    <div class="col-4">
                        <Link :href="route('reports.individual')" class="quick-link-item" @click="showQuickLinks = false">
                            <div class="quick-icon bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-bar-chart-line"></i>
                            </div>
                            <small class="fw-bold">Report</small>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Page Content -->
            <main class="p-0 mt-3 flex-grow-1">
                <slot />
            </main>

            <!-- Footer Section -->
            <footer class="text-center py-4 bg-white border-top mt-4 shadow-sm w-100">
                <div class="container-fluid">
                    <p class="mb-0 text-muted small fw-bold" style="letter-spacing: 1px; font-family: 'Inter', sans-serif;">
                        {{ new Date().getFullYear() }} &copy; BP & Co Performance Tracker | 
                        <span class="text-primary">Powered By CTRLNEXT Technologies</span>
                    </p>
                </div>
            </footer>
        </div>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600&display=swap');

.app-wrapper {
    font-family: 'Outfit', sans-serif;
}
</style>

<style scoped>
/* Sidebar overlap handling for mobile */
@media (max-width: 768px) {
    .sidebar.active {
        left: 0 !important;
    }
    .header-title {
        font-size: 1.1rem;
        max-width: 180px;
    }
    #mainHeader {
        padding-left: 10px !important;
        padding-right: 10px !important;
    }
    .quicklink-popup {
        right: 10px;
        left: 10px;
        width: auto;
        top: 60px;
    }
    .premium-profile-card {
        right: 10px;
        left: 10px;
        width: auto;
        top: 60px;
    }
}

/* Premium Profile Card */
.premium-profile-card {
    position: fixed;
    top: 70px;
    right: 20px;
    width: 300px;
    background: transparent;
    border-radius: 20px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    display: none;
    z-index: 1000;
    opacity: 0;
    transform: translateY(-15px);
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.premium-profile-card.show {
    display: block;
    opacity: 1;
    transform: translateY(0);
}

.hover-lift {
    transition: transform 0.2s;
}
.hover-lift:hover {
    transform: translateY(-2px);
}

/* Ensure links in sidebar highlight properly */
.sidebar ul li a.active {
    background-color: #f6f9ff;
    color: #003287;
    border-left: 4px solid #003287;
}

.profile-card {
    transition: all 0.3s ease;
}

.quicklink-popup {
    position: fixed;
    top: 70px;
    right: 80px;
    width: 280px;
    background: white;
    z-index: 1000;
    display: none;
    opacity: 0;
    transform: translateY(-15px);
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.quicklink-popup.show {
    display: block;
    opacity: 1;
    transform: translateY(0);
}

.quick-link-item {
    text-decoration: none;
    color: #475569;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}

.quick-link-item:hover {
    transform: translateY(-3px);
    color: #0d6efd;
}

.quick-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    transition: all 0.3s;
}

.quick-link-item:hover .quick-icon {
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.letter-spacing-1 {
    letter-spacing: 1px;
}

.submenu {
    list-style: none;
    margin-top: 5px;
    padding-left: 1rem;
}
.submenu li a {
    font-size: 0.9em;
    padding: 8px 15px;
}
</style>
