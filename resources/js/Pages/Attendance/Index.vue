<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import Modal from '@/Components/Modal.vue';
import Alert from '@/Utils/Alert';

const props = defineProps({
    records: Array,
    today: String,
    rules: Object,
});

// Camera & Video Refs
const video = ref(null);
const canvas = ref(null);
const stream = ref(null);
const cameraError = ref(null);
const isCapturing = ref(false);

// GPS State
const location = ref({ lat: null, lng: null, accuracy: null, error: null });
const isWithinGeofence = ref(null);
const distanceFromCenter = ref(null);

const form = useForm({
    image_blob: null,
    latitude: null,
    longitude: null,
});

// Start Camera Stream
const startCamera = async () => {
    try {
        stream.value = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user' },
            audio: false
        });
        if (video.value) {
            video.value.srcObject = stream.value;
        }
    } catch (err) {
        cameraError.value = "Camera access denied or unavailable. Please use a mobile device with a camera.";
        Alert.error('Camera Error', cameraError.value);
    }
};

// Continuous GPS Tracking
let watchId = null;
const startLocationTracking = () => {
    if (!navigator.geolocation) {
        location.value.error = "Geolocation not supported";
        return;
    }

    watchId = navigator.geolocation.watchPosition(
        (pos) => {
            location.value.lat = pos.coords.latitude;
            location.value.lng = pos.coords.longitude;
            location.value.accuracy = pos.coords.accuracy;
            location.value.error = null;
            
            // Local Haversine check for UI feedback
            checkGeofence(pos.coords.latitude, pos.coords.longitude);
        },
        (err) => {
            location.value.error = err.message;
        },
        { enableHighAccuracy: true }
    );
};

const checkGeofence = (lat, lng) => {
    const centerLat = parseFloat(props.rules.geofence_latitude);
    const centerLng = parseFloat(props.rules.geofence_longitude);
    const radius = parseFloat(props.rules.geofence_radius);

    if (!centerLat || !centerLng) return;

    const R = 6371000; // meters
    const dLat = (centerLat - lat) * Math.PI / 180;
    const dLon = (centerLng - lng) * Math.PI / 180;
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
              Math.cos(lat * Math.PI / 180) * Math.cos(centerLat * Math.PI / 180) *
              Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    const distance = R * c;

    distanceFromCenter.value = distance.toFixed(1);
    isWithinGeofence.value = distance <= radius;
};

onMounted(() => {
    startCamera();
    startLocationTracking();
});

onUnmounted(() => {
    if (stream.value) {
        stream.value.getTracks().forEach(track => track.stop());
    }
    if (watchId) {
        navigator.geolocation.clearWatch(watchId);
    }
});

const captureAndSubmit = () => {
    if (!video.value || !canvas.value) return;
    
    isCapturing.value = true;
    const context = canvas.value.getContext('2d');
    canvas.value.width = video.value.videoWidth;
    canvas.value.height = video.value.videoHeight;
    context.drawImage(video.value, 0, 0);
    
    const dataUrl = canvas.value.toDataURL('image/png');
    
    form.image_blob = dataUrl;
    form.latitude = location.value.lat;
    form.longitude = location.value.lng;

    form.post(route('attendance.store'), {
        preserveScroll: true,
        onSuccess: () => {
            isCapturing.value = false;
            Alert.toast('Attendance marked successfully!', 'success');
        },
        onError: () => isCapturing.value = false
    });
};

const formatDate = (dateStr) => {
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).toUpperCase();
};

const statusBadge = (status) => ({
    present: 'bg-success',
    late: 'bg-warning text-dark',
    half_day: 'bg-orange text-white',
    absent: 'bg-danger',
    holiday: 'bg-secondary',
}[status] || 'bg-light text-dark');

const approvalBadge = (status) => ({
    approved: 'bg-success',
    pending: 'bg-warning text-dark',
    rejected: 'bg-danger',
}[status] || 'bg-secondary');

// Image Modal Logic
const showImageModal = ref(false);
const selectedImage = ref('');
const viewImage = (path) => {
    selectedImage.value = `/storage/${path}`;
    showImageModal.value = true;
};
</script>

<template>
    <Head title="Attendance" />
    <AuthenticatedLayout>
        <div class="header-banner px-4 py-3 bg-white shadow-sm">
            <h4 class="fw-bold mb-0 text-primary">Live Attendance</h4>
        </div>

        <div class="container-fluid mt-4 mb-5">
            <div class="row">
                <!-- Camera Section -->
                <div class="col-lg-5 mb-4">
                    <div class="premium-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                            <h5 class="fw-bold text-dark mb-0">Capture Attendance</h5>
                            <span class="badge bg-light text-dark border rounded-pill">{{ today }}</span>
                        </div>

                        <!-- Status Indicators -->
                        <div class="d-flex flex-column gap-2 mb-4">
                            <div class="status-indicator" :class="location.lat ? 'status-ok' : 'status-waiting'">
                                <i class="bi" :class="location.lat ? 'bi-geo-alt-fill' : 'bi-geo-alt'"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-bold small">GPS Location</div>
                                    <div class="small" v-if="location.lat">
                                        Accuracy: {{ location.accuracy?.toFixed(1) }}m
                                        <span v-if="isWithinGeofence !== null" :class="isWithinGeofence ? 'text-success' : 'text-danger'">
                                            ({{ isWithinGeofence ? 'On-site' : 'Outside Office' }})
                                        </span>
                                    </div>
                                    <div class="small text-muted" v-else-if="location.error">Error: {{ location.error }}</div>
                                    <div class="small text-muted blinking" v-else>Searching for satellites...</div>
                                </div>
                            </div>
                        </div>

                        <!-- Camera Viewport -->
                        <div class="camera-container mb-4 position-relative overflow-hidden rounded-4 shadow-sm bg-black">
                            <div v-if="cameraError" class="camera-error p-4 text-center text-white">
                                <i class="bi bi-camera-video-off fs-1 mb-2 d-block"></i>
                                <div class="fw-bold">{{ cameraError }}</div>
                            </div>
                            <video v-else ref="video" autoplay playsinline muted class="video-feed h-100 w-100"></video>
                            
                            <!-- Geofence Warning Overlay -->
                            <div v-if="isWithinGeofence === false" class="geofence-warning">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i> OUTSIDE OFFICE RADIUS
                            </div>
                        </div>

                        <canvas ref="canvas" class="d-none"></canvas>

                        <button 
                            @click="captureAndSubmit" 
                            class="btn btn-primary glass-btn w-100 py-3 fw-bold shadow position-relative"
                            :disabled="isCapturing || cameraError || !location.lat"
                        >
                            <span v-if="isCapturing" class="spinner-border spinner-border-sm me-2"></span>
                            <i v-else class="bi bi-camera-fill me-2 fs-5"></i>
                            {{ isCapturing ? 'SUBMITTING...' : 'CAPTURE & MARK ATTENDANCE' }}
                        </button>
                    </div>
                </div>

                <!-- History Table -->
                <div class="col-lg-7">
                    <div class="premium-card p-4">
                        <h5 class="fw-bold text-dark border-bottom pb-2 mb-4">Attendance History</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light small text-uppercase">
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Location</th>
                                        <th>Photo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="r in records" :key="r.id">
                                        <td class="fw-bold small">{{ formatDate(r.date) }}</td>
                                        <td class="small">{{ r.check_in_time }}</td>
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <span class="badge w-100" :class="statusBadge(r.status)">{{ r.status.toUpperCase() }}</span>
                                                <span class="badge w-100" :class="approvalBadge(r.approval_status)">{{ r.approval_status.toUpperCase() }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div v-if="r.latitude" class="small d-flex flex-column">
                                                <span :class="r.is_within_geofence ? 'text-success' : 'text-danger'">
                                                    <i class="bi" :class="r.is_within_geofence ? 'bi-check-circle-fill' : 'bi-x-circle-fill'"></i>
                                                    {{ r.is_within_geofence ? 'On-site' : 'Outside' }}
                                                </span>
                                                <span class="text-muted" style="font-size: 0.7rem;">Dist: {{ r.distance_from_center }}m</span>
                                            </div>
                                            <span v-else class="text-muted small">N/A</span>
                                        </td>
                                        <td>
                                            <button v-if="r.image_path" @click="viewImage(r.image_path)" class="btn btn-sm btn-light border rounded-pill px-3">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="records.length === 0">
                                        <td colspan="5" class="text-center text-muted py-5 small">No records found.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Photo Modal -->
        <Modal :show="showImageModal" @close="showImageModal = false" maxWidth="lg">
            <div class="p-3">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <h5 class="fw-bold mb-0 text-primary">Attendance Photo</h5>
                    <button class="btn-close" @click="showImageModal = false"></button>
                </div>
                <div class="text-center bg-dark rounded p-1">
                    <img :src="selectedImage" class="img-fluid rounded" style="max-height: 75vh;" alt="Capture">
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap');

.header-banner { font-family: 'Outfit', sans-serif; }
.premium-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    border: 1px solid rgba(0,0,0,0.03);
}

.camera-container {
    height: 350px;
    background: #000;
}
.video-feed { object-fit: cover; }

.status-indicator {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    border-radius: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}
.status-ok { border-left: 4px solid #10b981; }
.status-waiting { border-left: 4px solid #f59e0b; }

.geofence-warning {
    position: absolute;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(220, 38, 38, 0.9);
    color: white;
    padding: 8px 20px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.75rem;
    white-space: nowrap;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    z-index: 10;
}

.blinking { animation: blinker 1.5s linear infinite; }
@keyframes blinker { 50% { opacity: 0; } }

.glass-btn {
    border-radius: 15px;
    transition: all 0.3s;
}
.glass-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(13, 110, 253, 0.3) !important;
}

.bg-orange { background-color: #f97316 !important; }
</style>
