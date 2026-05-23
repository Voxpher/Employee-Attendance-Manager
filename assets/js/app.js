/**
 * Employee Attendance Manager frontend.
 */
(function ($) {
    'use strict';

    var stream = null;
    var actionType = 'check_in';
    var employeeCalendar = null;
    var adminCalendar = null;
    var lastCaptureTime = 0;
    var captureDebounce = 600;
    var originalModalBody = '';

    function i18n(key, fallback) {
        if (typeof EAM !== 'undefined' && EAM.i18n && EAM.i18n[key]) {
            return EAM.i18n[key];
        }

        return fallback;
    }

    function text(value, fallback) {
        if (value === null || typeof value === 'undefined' || value === '') {
            return fallback || '';
        }

        return String(value);
    }

    function escapeHtml(value) {
        return text(value).replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[character];
        });
    }

    function escapeAttribute(value) {
        return escapeHtml(value).replace(/`/g, '&#096;');
    }

    function stopCamera() {
        if (!stream) {
            return;
        }

        stream.getTracks().forEach(function (track) {
            track.stop();
        });

        stream = null;
        $('#eam-video').hide();
    }

    function hideModal() {
        stopCamera();
        $('#eam-modal, #eam-modal-backdrop')
            .removeClass('is-open')
            .hide()
            .css({
                display: 'none',
                visibility: 'hidden',
                opacity: '0'
            });
    }

    function showNotice(message, type) {
        var selector = type === 'success' ? '#eam-modal-success' : '#eam-modal-error';
        $(selector).text(message).show();
    }

    function checkTodayStatus(callback) {
        $.post(EAM.ajax_url, {
            action: 'eam_today_status',
            nonce: EAM.nonce
        }).done(function (response) {
            callback(response.success ? response.data : {});
        }).fail(function () {
            callback({});
        });
    }

    function openModal() {
        $('#eam-modal').addClass('is-open').css({ display: 'flex', visibility: 'visible', opacity: '1' }).show();
        $('#eam-modal-backdrop').addClass('is-open').css({ display: 'flex', visibility: 'visible', opacity: '1' }).show();
    }

    function showModal(type) {
        checkTodayStatus(function (status) {
            if (type === 'check_in' && status.check_in) {
                window.alert(i18n('already_checked_in', 'Already checked in for today.'));
                return;
            }

            if (type === 'check_out' && !status.check_in) {
                window.alert(i18n('check_in_first', 'Please check in first before checking out.'));
                return;
            }

            actionType = type;

            if (originalModalBody) {
                $('#eam-modal-body').html(originalModalBody);
            }

            $('#eam-action-type').val(type);
            $('#eam-note').val('');
            $('#eam-photo-data').val('');
            $('#eam-photo-preview, #eam-video, #eam-modal-error, #eam-modal-success').hide();
            $('#eam-modal-title').text(type === 'check_in' ? 'Check-In' : 'Check-Out');

            openModal();
        });
    }

    function startCamera() {
        if (stream) {
            return;
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showNotice(i18n('camera_unavailable', 'Camera access is not available in this browser.'), 'error');
            return;
        }

        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } })
            .then(function (newStream) {
                var video = document.getElementById('eam-video');
                stream = newStream;
                video.srcObject = stream;
                video.play();
                $('#eam-video').show();
                $('#eam-photo-preview').hide();
            })
            .catch(function () {
                showNotice(i18n('camera_denied', 'Camera permission was denied or the camera is unavailable.'), 'error');
            });
    }

    function capturePhoto() {
        var now = Date.now();
        var video = document.getElementById('eam-video');
        var canvas = document.getElementById('eam-canvas');

        if (now - lastCaptureTime < captureDebounce) {
            return;
        }

        lastCaptureTime = now;

        if (!stream) {
            showNotice(i18n('camera_start', 'Please start the camera first.'), 'error');
            return;
        }

        if (!video || !canvas || !video.videoWidth || !video.videoHeight) {
            showNotice(i18n('camera_loading', 'Camera is still loading. Please try again.'), 'error');
            return;
        }

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);

        $('#eam-photo-data').val(canvas.toDataURL('image/png'));
        $('#eam-photo-preview').attr('src', canvas.toDataURL('image/png')).show();
        $('#eam-video').hide();
        stopCamera();
    }

    function submitAttendance() {
        var note = $('#eam-note').val().trim();
        var photo = $('#eam-photo-data').val();

        $('#eam-modal-error, #eam-modal-success').hide();

        if (!note) {
            showNotice(i18n('note_required', 'Note is required.'), 'error');
            return;
        }

        if (!photo) {
            showNotice(i18n('photo_required', 'Photo is required.'), 'error');
            return;
        }

        $('#eam-submit-attendance').prop('disabled', true).text(i18n('submitting', 'Submitting...'));

        $.post(EAM.ajax_url, {
            action: 'eam_submit_attendance',
            nonce: EAM.nonce,
            action_type: actionType,
            note: note,
            photo: photo
        }).done(function (response) {
            if (response.success) {
                showNotice(response.data.message || i18n('attendance_saved', 'Attendance saved.'), 'success');
                setTimeout(function () {
                    hideModal();
                    if (employeeCalendar) {
                        employeeCalendar.refetchEvents();
                    }
                    if (adminCalendar) {
                        adminCalendar.refetchEvents();
                    }
                    loadAdminTable();
                }, 1200);
                return;
            }

            showNotice((response.data && response.data.message) || i18n('save_failed', 'Attendance could not be saved.'), 'error');
        }).fail(function () {
            showNotice(i18n('network_error', 'Network error. Please try again.'), 'error');
        }).always(function () {
            $('#eam-submit-attendance').prop('disabled', false).text(i18n('submit', 'Submit'));
        });
    }

    function photoMarkup(url, label) {
        if (!url) {
            return '';
        }

        return '<img src="' + escapeAttribute(url) + '" alt="' + escapeAttribute(label) + '" class="eam-detail-photo">';
    }

    function showDayDetails(date) {
        $.post(EAM.ajax_url, {
            action: 'eam_get_day_details',
            nonce: EAM.nonce,
            date: date
        }).done(function (response) {
            var d;
            var html;

            if (!response.success || !response.data || !response.data.exists) {
                window.alert(i18n('no_record', 'No attendance record found for this date.'));
                return;
            }

            d = response.data;
            html = '<div class="eam-day-details">';
            html += '<h3>' + escapeHtml(i18n('attendance_for', 'Attendance for')) + ' ' + escapeHtml(d.date) + '</h3>';

            if (d.user_name) {
                html += '<p><strong>' + escapeHtml(i18n('employee', 'Employee:')) + '</strong> ' + escapeHtml(d.user_name) + '</p>';
            }

            html += '<div class="eam-day-grid">';
            html += '<div><h4 class="eam-in-heading">' + escapeHtml(i18n('check_in', 'Check-In')) + '</h4>';
            html += photoMarkup(d.check_in_photo, i18n('check_in', 'Check-In'));
            html += '<p><strong>' + escapeHtml(i18n('time', 'Time:')) + '</strong> ' + escapeHtml(text(d.check_in, i18n('not_available', 'N/A'))) + '</p>';
            html += '<p><strong>' + escapeHtml(i18n('note', 'Note:')) + '</strong> ' + escapeHtml(text(d.check_in_note, i18n('not_available', 'N/A'))) + '</p></div>';
            html += '<div><h4 class="eam-out-heading">' + escapeHtml(i18n('check_out', 'Check-Out')) + '</h4>';
            html += photoMarkup(d.check_out_photo, i18n('check_out', 'Check-Out'));
            html += '<p><strong>' + escapeHtml(i18n('time', 'Time:')) + '</strong> ' + escapeHtml(text(d.check_out, i18n('not_available', 'N/A'))) + '</p>';
            html += '<p><strong>' + escapeHtml(i18n('note', 'Note:')) + '</strong> ' + escapeHtml(text(d.check_out_note, i18n('not_available', 'N/A'))) + '</p></div>';
            html += '</div>';

            if (d.total_hours) {
                html += '<div class="eam-total-duration"><p>' + escapeHtml(i18n('total_duration', 'Total Duration:')) + ' ' + escapeHtml(d.total_hours) + '</p></div>';
            }

            html += '</div>';

            $('#eam-modal-title').text(i18n('details_title', 'Attendance Details'));
            $('#eam-modal-body').html(html);
            openModal();
        }).fail(function () {
            window.alert(i18n('load_failed', 'Failed to load details.'));
        });
    }

    function renderAdminTable(data) {
        var tbody = $('#eam-admin-table tbody');
        tbody.empty();

        if (!data || data.length === 0) {
            tbody.append($('<tr></tr>').append($('<td></td>', { colspan: 9, text: i18n('no_records', 'No records') })));
            return;
        }

        data.forEach(function (row) {
            var tr = $('<tr></tr>');
            var inPhoto = row.check_in_photo ? $('<a></a>', { href: row.check_in_photo, target: '_blank', rel: 'noopener noreferrer', text: i18n('view', 'View') }) : '-';
            var outPhoto = row.check_out_photo ? $('<a></a>', { href: row.check_out_photo, target: '_blank', rel: 'noopener noreferrer', text: i18n('view', 'View') }) : '-';

            $('<td></td>').text(text(row.date, '-')).appendTo(tr);
            $('<td></td>').text(text(row.employee, '-')).appendTo(tr);
            $('<td></td>').text(text(row.check_in, '-')).appendTo(tr);
            $('<td></td>').text(text(row.check_out, '-')).appendTo(tr);
            $('<td></td>').text(text(row.total_hours, '-')).appendTo(tr);
            $('<td></td>').append(inPhoto).appendTo(tr);
            $('<td></td>').append(outPhoto).appendTo(tr);
            $('<td></td>').text(text(row.check_in_note, '-')).appendTo(tr);
            $('<td></td>').text(text(row.check_out_note, '-')).appendTo(tr);

            tbody.append(tr);
        });
    }

    function loadAdminTable(date) {
        var table = $('#eam-admin-table');

        if (!table.length) {
            return;
        }

        $.post(EAM.ajax_url, {
            action: 'eam_admin_table',
            nonce: EAM.nonce,
            employee_id: $('#eam-filter-employee').val() || '',
            from: $('#eam-filter-from').val() || '',
            to: $('#eam-filter-to').val() || '',
            date: date || ''
        }).done(function (response) {
            if (response.success) {
                renderAdminTable(response.data);
            }
        });
    }

    function initEmployeeCalendar() {
        if (!$('#eam-employee-calendar').length || employeeCalendar) {
            return;
        }

        employeeCalendar = new FullCalendar.Calendar(document.getElementById('eam-employee-calendar'), {
            initialView: 'dayGridMonth',
            height: 'auto',
            headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
            events: function (info, success) {
                $.get(EAM.ajax_url, {
                    action: 'eam_get_events',
                    nonce: EAM.nonce,
                    start: info.startStr,
                    end: info.endStr
                }).done(function (response) {
                    success(response.data || []);
                });
            },
            dateClick: function (info) {
                showDayDetails(info.dateStr);
            }
        });
        employeeCalendar.render();
    }

    function initAdminCalendar() {
        if (!$('#eam-admin-calendar').length || adminCalendar) {
            return;
        }

        adminCalendar = new FullCalendar.Calendar(document.getElementById('eam-admin-calendar'), {
            initialView: 'dayGridMonth',
            height: 'auto',
            headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
            events: function (info, success) {
                $.get(EAM.ajax_url, {
                    action: 'eam_get_events',
                    nonce: EAM.nonce,
                    start: info.startStr,
                    end: info.endStr,
                    user_id: $('#eam-filter-employee').val() || ''
                }).done(function (response) {
                    success(response.data || []);
                });
            },
            dateClick: function (info) {
                loadAdminTable(info.dateStr);
            }
        });
        adminCalendar.render();
    }

    function waitForFullCalendar(callback) {
        if (typeof FullCalendar !== 'undefined') {
            callback();
            return;
        }

        window.setTimeout(function () {
            waitForFullCalendar(callback);
        }, 100);
    }

    function bindEvents() {
        $(document).on('click', '#eam-open-checkin', function (event) {
            event.preventDefault();
            showModal('check_in');
        });

        $(document).on('click', '#eam-open-checkout', function (event) {
            event.preventDefault();
            showModal('check_out');
        });

        $(document).on('click', '#eam-capture-photo', function (event) {
            event.preventDefault();
            if (!stream) {
                startCamera();
            } else {
                capturePhoto();
            }
        });

        $(document).on('click', '#eam-submit-attendance', function (event) {
            event.preventDefault();
            submitAttendance();
        });

        $(document).on('click', '#eam-modal-close, #eam-cancel-modal, #eam-modal-backdrop', function (event) {
            event.preventDefault();
            hideModal();
        });

        $(document).on('click', '#eam-filter-apply', function (event) {
            event.preventDefault();
            if (adminCalendar) {
                adminCalendar.refetchEvents();
            }
            loadAdminTable();
        });
    }

    function init() {
        hideModal();

        if (!$('#eam-employee-calendar').length) {
            return;
        }

        waitForFullCalendar(function () {
            originalModalBody = $('#eam-modal-body').html();
            initEmployeeCalendar();
            initAdminCalendar();
            bindEvents();
            loadAdminTable();
        });
    }

    $(document).ready(init);
    $(window).on('load pageshow', hideModal);
})(jQuery);
