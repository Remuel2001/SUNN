$(document).ready(function () {
    const isMobile = window.innerWidth < 768;

    $('.datatable').DataTable({
        pageLength: isMobile ? 10 : 25,
        responsive: true,
        autoWidth: false,
        language: {
            search: "",
            searchPlaceholder: "Search...",
            lengthMenu: isMobile ? "_MENU_" : "_MENU_ per page",
            info: isMobile ? "_TOTAL_ entries" : "Showing _START_ to _END_ of _TOTAL_ entries"
        },
        dom: isMobile ? '<"mb-2"f>tip' : '<"row align-items-center mb-3"<"col-sm-6"l><"col-sm-6"f>>tip'
    });

    $('.select2').select2({ theme: 'bootstrap-5', width: '100%', dropdownAutoWidth: true });

    $('[data-bs-toggle="tooltip"]').tooltip();

    $('.auto-dismiss').delay(5000).fadeOut('slow');

    $('#markAllRead').click(function (e) {
        e.preventDefault();
        $.ajax({
            url: BASE_URL + '/api/notifications.php?action=mark_all_read',
            method: 'POST',
            success: function () { location.reload(); }
        });
    });

    $('.delete-record').click(function (e) {
        e.preventDefault();
        const form = $(this).closest('form');
        Swal.fire({
            title: 'Are you sure?',
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8'
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
    });

    $('.notification-item').click(function () {
        const notifId = $(this).data('id');
        if (notifId) {
            $.ajax({
                url: BASE_URL + '/api/notifications.php?action=mark_read&id=' + notifId,
                method: 'POST'
            });
        }
    });

    $('.navbar-collapse').on('shown.bs.collapse', function () {
        $('body').css('overflow', 'hidden');
    }).on('hidden.bs.collapse', function () {
        $('body').css('overflow', '');
    });

    $(window).on('resize', function () {
        const mobile = window.innerWidth < 768;
        $('.table-responsive').each(function () {
            $(this).find('table').css('font-size', mobile ? '.72rem' : '');
        });
    });

    updateClock();
    setInterval(updateClock, 1000);
});

function showToast(message, type) {
    type = type || 'success';
    const icons = { success: 'check-circle-fill', error: 'x-circle-fill', warning: 'exclamation-triangle-fill', info: 'info-circle-fill' };
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
    Toast.fire({ icon: type, title: message, iconColor: type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : type === 'warning' ? '#f59e0b' : '#06b6d4' });
}

function showLoader() {
    if ($('#loader').length === 0) {
        $('body').append(
            '<div id="loader" class="spinner-overlay">' +
            '<div class="text-center">' +
            '<div class="spinner-border text-primary" style="width:3rem;height:3rem" role="status"></div>' +
            '<p class="mt-3 text-muted fw-semibold">Loading...</p>' +
            '</div></div>'
        );
    }
}

function hideLoader() { $('#loader').remove(); }

function confirmAction(message, callback) {
    Swal.fire({
        title: 'Confirm',
        text: message || 'Are you sure?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Yes, proceed'
    }).then((result) => {
        if (result.isConfirmed && callback) callback();
    });
}

function ajaxForm(formId, callback) {
    const form = $(formId);
    form.submit(function (e) {
        e.preventDefault();
        const btn = form.find('[type="submit"]');
        const originalText = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Processing...');
        $.ajax({
            url: form.attr('action'),
            method: form.attr('method') || 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false,
            success: function (res) {
                btn.prop('disabled', false).html(originalText);
                if (typeof callback === 'function') callback(res);
                else if (res.success) {
                    showToast(res.message || 'Success!');
                    if (res.redirect) setTimeout(() => location.href = res.redirect, 1000);
                } else showToast(res.message || 'Error occurred!', 'error');
            },
            error: function () {
                btn.prop('disabled', false).html(originalText);
                showToast('Server error occurred', 'error');
            }
        });
    });
}

function updateClock() {
    const now = new Date();
    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const dateStr = days[now.getDay()] + ', ' + months[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear();
    const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const el = $('#liveClock');
    if (el.length) el.html('<i class="bi bi-calendar3 me-1"></i> ' + dateStr + ' &nbsp; <i class="bi bi-clock me-1"></i> ' + timeStr);
}
