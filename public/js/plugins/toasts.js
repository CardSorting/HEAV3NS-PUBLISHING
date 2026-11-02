"use strict";
/* global bootstrap */
/**
 * Stackable toasts plugin
 **/
(function ($) {
    const TOAST_CONTAINER_HTML = `<div id="toast-container" class="toast-container" aria-live="polite" aria-atomic="true"></div>`;

    $.toastDefaults = {
        position: 'bottom-right',
        dismissible: true,

        // If true, toasts stack; if false, the current toast replaces the previous ones.
        stackable: true,

        // Maximum number of toasts to keep in the DOM when stackable is true.
        // Set to 1 to effectively "replace" while still keeping stackable enabled.
        maxStack: 3,

        pauseDelayOnHover: true,
        style: {
            toast: '',
            info: '',
            success: '',
            warning: '',
            error: '',
        }
    };

    $('body').on('hidden.bs.toast', '.toast', function () {
        $(this).remove();
    });

    let toastRunningCount = 1;

    function escapeHtml(value) {
        return $('<div>').text(value || '').html();
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/"/g, '&quot;');
    }

    function displaySubtitle(value) {
        value = String(value || '').trim();

        return value && value.toLowerCase() !== 'now' ? value : '';
    }

    function displayType(value) {
        value = String(value || '').trim().toLowerCase();

        if (value === 'error') {
            return 'danger';
        }

        return ['success', 'warning', 'danger'].includes(value) ? value : '';
    }

    function enforceMaxStack(toastContainer) {
        if (!$.toastDefaults.stackable) return;

        const maxStack = parseInt($.toastDefaults.maxStack, 10);
        if (Number.isNaN(maxStack) || maxStack <= 0) return;

        const $toasts = toastContainer.find('.toast');
        const overflow = $toasts.length - maxStack;
        if (overflow > 0) {
            // Remove oldest toasts first
            $toasts.slice(0, overflow).each(function () {
                $(this).remove();
            });
        }
    }

    function render(opts) {
        /** No container, create our own **/
        if (!$('#toast-container').length) {
            const position = ['top-right', 'top-left', 'top-center', 'bottom-right', 'bottom-left', 'bottom-center']
                .includes($.toastDefaults.position) ? $.toastDefaults.position : 'bottom-right';

            $('body').prepend(TOAST_CONTAINER_HTML);
            $('#toast-container').addClass(position);
        }

        let toastContainer = $('#toast-container');
        let html = '';
        let id = opts.id || `toast-${toastRunningCount}`;
        let title = opts.title;
        let subtitle = displaySubtitle(opts.subtitle);
        let content = opts.content;
        let img = opts.img;
        let indicator = opts.indicator;
        let toastType = displayType(indicator && indicator.type ? indicator.type : opts.type);
        let delayOrAutohide = opts.delay ? `data-bs-delay="${opts.delay}"` : `data-bs-autohide="false"`;
        let hideAfter = ``;
        let dismissible = $.toastDefaults.dismissible;
        let globalToastStyles = $.toastDefaults.style.toast;
        let paused = false;

        if (typeof opts.dismissible !== 'undefined') {
            dismissible = opts.dismissible;
        }

        if ($.toastDefaults.pauseDelayOnHover && opts.delay) {
            delayOrAutohide = `data-bs-autohide="false"`;
            hideAfter = `data-hide-after="${Math.floor(Date.now() / 1000) + (opts.delay / 1000)}"`;
        }

        html = `<div id="${escapeAttr(id)}" class="toast ${globalToastStyles}" role="alert" aria-live="assertive" aria-atomic="true" ${delayOrAutohide} ${hideAfter}>`;
        html += `<div class="toast-body d-flex align-items-start py-2 px-3">`;

        if (img) {
            html += `<img src="${escapeAttr(img.src)}" class="me-2 ${escapeAttr(img.class || '')}" alt="${escapeAttr(img.alt || 'Image')}">`;
        }

        html += `<div class="toast-content flex-grow-1 pe-2${toastType ? ' toast-content-with-type' : ''}">`;
        html += `<div class="toast-title-row d-flex align-items-center">`;

        if (toastType) {
            html += `<span class="toast-type-dot toast-type-dot-${toastType}" aria-hidden="true"></span>`;
        }

        html += `<strong class="d-block text-white">${escapeHtml(title)}</strong>`;
        html += `</div>`;

        if (subtitle) {
            html += `<small class="d-block toast-subtitle mt-1">${escapeHtml(subtitle)}</small>`;
        }

        if (content) {
            html += `<div class="toast-message mt-1">${escapeHtml(content)}</div>`;
        }

        html += `</div>`;

        if (dismissible) {
            html += `<button type="button" class="btn-close btn-close-white toast-close ms-2 p-0" data-bs-dismiss="toast" aria-label="Close"></button>`;
        }

        html += `</div>`;
        html += `</div>`;

        if (!$.toastDefaults.stackable) {
            toastContainer.find('.toast').each(function () {
                $(this).remove();
            });

            toastContainer.append(html);
            bootstrap.Toast.getOrCreateInstance(toastContainer.find('.toast:last')[0]).show();
        } else {
            toastContainer.append(html);

            // Enforce max stack after appending
            enforceMaxStack(toastContainer);

            bootstrap.Toast.getOrCreateInstance(toastContainer.find('.toast:last')[0]).show();
        }

        if ($.toastDefaults.pauseDelayOnHover && opts.delay) {
            setTimeout(function () {
                if (!paused) {
                    var toast = document.getElementById(id);
                    if (toast) bootstrap.Toast.getOrCreateInstance(toast).hide();
                }
            }, opts.delay);

            $('body').on('mouseover', `#${id}`, function () {
                paused = true;
            });

            $(document).on('mouseleave', '#' + id, function () {
                const current = Math.floor(Date.now() / 1000),
                    future = parseInt($(this).data('hideAfter'));

                paused = false;

                if (current >= future) {
                    bootstrap.Toast.getOrCreateInstance(this).hide();
                }
            });
        }

        toastRunningCount++;
    }

    /**
     * Show a snack
     * @param type
     * @param title
     * @param delay
     */
    $.snack = function (type, title, delay) {
        return render({
            type,
            title,
            delay
        });
    };

    /**
     * Show a toast
     * @param opts
     */
    $.toast = function (opts) {
        return render(opts);
    };
}(jQuery));
