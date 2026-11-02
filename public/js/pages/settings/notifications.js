/**
 * Notification settings component
 */
"use strict";
/* global app, trans, launchToast */

$(function () {
    $('.notification-checkbox').on('change', async function () {
        const $input = $(this);
        const key = $input.attr('id');
        const val = $input.prop('checked');

        $input.prop('disabled', true);

        try {
            const response = await NotificationsSettings.updateUserSettings(key, val);

            if (response && response.success) {
                launchToast('success', trans('Success'), response.message || trans('Setting saved'));
            } else {
                launchToast('danger', trans('Error'), (response && response.message) || trans('Setting save failed'));
                $input.prop('checked', !val);
            }
        } catch (error) {
            launchToast('danger', trans('Error'), trans('Setting save failed'));
            $input.prop('checked', !val);
        } finally {
            $input.prop('disabled', false);
        }
    });

    NotificationsSettings.initPushToggle();
});

var NotificationsSettings = {

    updateUserSettings: function (key, value) {
        return $.ajax({
            type: 'POST',
            data: {
                key: key,
                value: value
            },
            dataType: 'json',
            url: app.baseUrl + '/my/settings/save'
        });
    },

    initPushToggle: function () {
        var $toggle = $('#notification_push_enabled');
        var $unsupported = $('#push-notifications-unsupported');

        if (!$toggle.length) {
            return;
        }

        if (!window.WebPushManager || !window.WebPushManager.isSupported || !window.WebPushManager.isSupported()) {
            $toggle.prop('disabled', true);
            $unsupported.removeClass('d-none');
            return;
        }

        this.syncPushToggleState();

        $toggle.on('change', async function () {
            var enabled = $(this).prop('checked');

            $toggle.prop('disabled', true);

            try {
                if (enabled) {
                    await window.WebPushManager.subscribe();
                    await NotificationsSettings.updateUserSettings('notification_push_enabled', true);
                    launchToast('success', trans('Success'), trans('Setting saved'));
                } else {
                    await window.WebPushManager.unsubscribe();
                    await NotificationsSettings.updateUserSettings('notification_push_enabled', false);
                    launchToast('success', trans('Success'), trans('Setting saved'));
                }
            } catch (error) {
                launchToast('danger', trans('Error'), trans('Setting save failed'));
            }

            await NotificationsSettings.syncPushToggleState();
            $toggle.prop('disabled', false);
        });
    },

    syncPushToggleState: async function () {
        var $toggle = $('#notification_push_enabled');

        if (!$toggle.length || !window.WebPushManager || !window.WebPushManager.getSubscription) {
            return;
        }

        try {
            var subscription = await window.WebPushManager.getSubscription();
            $toggle.prop('checked', !!subscription);
        } catch (e) {
            $toggle.prop('checked', false);
        }
    }

};
