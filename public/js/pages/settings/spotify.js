"use strict";
/* global app, launchToast, trans, getStoredSvg */

$('#spotify-track-q').on('keydown', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        $('#spotify-track-search').trigger('click');
    }
});

$(function () {
    $('#spotify-disconnect').on('click', function (e) {
        e.preventDefault();
        $.post(app.baseUrl + '/my/settings/spotify/disconnect', {}, function (res) {
            if (res.success) location.reload();
        });
    });

    $('#spotify-refresh').on('click', function (e) {
        e.preventDefault();

        var $btn = $(this);
        var oldHtml = $btn.html();

        $btn.prop('disabled', true).addClass('disabled');
        $btn.html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + trans('Refreshing...'));

        $.ajax({
            type: 'POST',
            url: app.baseUrl + '/my/settings/spotify/refresh',
            success: function (res) {
                if (!res || !res.success) {
                    launchToast('danger', trans('Error'), res?.message || trans('Refresh failed'), 'now');
                    return;
                }

                var items = res.data || [];

                if (!items.length) {
                    $('#spotify-top-artists').html('<div class="text-muted small">' + trans('No artists found.') + '</div>');
                    launchToast('success', trans('Success'), trans('Top artists updated'), 'now');
                    return;
                }

                var html = items.map(function (a) {
                    return `
                    <div class="me-2 mb-2 text-center spotify-artist-wrapper">
                        <img src="${a.image || ''}" class="rounded spotify-artist-tile" alt="">
                        <div class="small mt-1 text-truncate">${a.name || ''}</div>
                    </div>
                `;
                }).join('');

                $('#spotify-top-artists').html(html);

                launchToast('success', trans('Success'), trans('Top artists updated'), 'now');
            },
            error: function (xhr) {
                var msg = xhr.responseJSON?.message || trans('Something went wrong.');
                launchToast('danger', trans('Error'), msg, 'now');
            },
            complete: function () {
                $btn.prop('disabled', false).removeClass('disabled');
                $btn.html(oldHtml);
            }
        });
    });

    $('#spotify-track-search').on('click', function (e) {
        e.preventDefault();
        const q = ($('#spotify-track-q').val() || '').trim();
        if (!q) return;

        $('#spotify-track-results').html('<div class="text-muted">'+trans('Searching...')+'</div>');

        $.get(app.baseUrl + '/my/settings/spotify/search', { q: q }, function (res) {
            if (!res.success) return;

            const items = res.data || [];
            if (!items.length) {
                $('#spotify-track-results').html('<div class="text-muted">'+trans('No results.')+'</div>');
                return;
            }

            const html = items.map(function (t) {
                return `
                <div class="d-flex align-items-center border rounded p-2 mb-2">
                    <img src="${t.image || ''}" class="rounded me-2 spotify-card-avatar">
                    <div class="flex-grow-1">
                        <div class="fw-bold">${t.name || ''}</div>
                        <div class="text-muted small">${t.artist || ''}</div>
                    </div>
                    <button class="btn btn-sm btn-outline-primary spotify-set-anthem mb-0" data-track-id="${t.id}" type="button">
                        ${trans('Set')}
                    </button>
                </div>`;
            }).join('');

            $('#spotify-track-results').html(html);
        });
    });

    $(document).on('click', '.spotify-set-anthem', function (e) {
        e.preventDefault();
        const trackId = $(this).data('track-id');

        $.post(app.baseUrl + '/my/settings/spotify/anthem', { track_id: trackId }, function (res) {
            if (!res.success) return;

            const t = res.data || {};

            // show anthem as a proper card
            $('#spotify-anthem-current')
                .attr('data-track-id', t.id || trackId)
                .html(`
                <div class="d-flex align-items-center border rounded p-2">
                    <img src="${t.image || ''}" class="rounded me-2 spotify-card-avatar">
                    <div class="flex-grow-1">
                        <div class="fw-bold">${t.name || ''}</div>
                        <div class="text-muted small">${t.artist || ''}</div>
                    </div>
                    ${t.url ? `<a class="me-1" href="${t.url}" target="_blank" rel="noopener">${getStoredSvg('spotify')}</a>` : ``}
                </div>
            `);

            // clear search UI
            $('#spotify-track-results').empty();
            $('#spotify-track-q').val('');

            launchToast('success', trans('Success'), trans('Anthem updated'), 'now');
        });
    });
});
