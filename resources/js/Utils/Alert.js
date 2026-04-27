import Swal from 'sweetalert2';

/**
 * Premium Alert Utility using SweetAlert2
 */
const Alert = {
    /**
     * Show a simple success toast or alert
     */
    success(title, text = '') {
        return Swal.fire({
            icon: 'success',
            title,
            text,
            confirmButtonColor: '#003287',
            timer: 3000,
            timerProgressBar: true,
        });
    },

    /**
     * Show an error alert
     */
    error(title, text = '') {
        return Swal.fire({
            icon: 'error',
            title,
            text,
            confirmButtonColor: '#d33',
        });
    },

    /**
     * Show a warning alert
     */
    warning(title, text = '') {
        return Swal.fire({
            icon: 'warning',
            title,
            text,
            confirmButtonColor: '#f8bb86',
        });
    },

    /**
     * Show a confirmation dialog
     * Returns a Promise that resolves to true if confirmed
     */
    confirm(title, text = '', confirmButtonText = 'Yes, Proceed') {
        return Swal.fire({
            title,
            text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#003287',
            cancelButtonColor: '#6e7881',
            confirmButtonText,
            reverseButtons: true,
            background: '#ffffff',
            customClass: {
                popup: 'premium-swal-popup',
                title: 'premium-swal-title',
                confirmButton: 'premium-swal-confirm',
                cancelButton: 'premium-swal-cancel'
            }
        }).then((result) => {
            return result.isConfirmed;
        });
    },

    /**
     * Show a non-intrusive toast notification
     */
    toast(title, icon = 'success') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.onmouseenter = Swal.stopTimer;
                toast.onmouseleave = Swal.resumeTimer;
            }
        });
        return Toast.fire({
            icon,
            title
        });
    }
};

export default Alert;
