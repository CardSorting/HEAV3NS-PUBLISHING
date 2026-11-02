/**
 * Push (desktop) notifications component
 */
'use strict';
/* global app */


window.WebPushManager = {
    vapidPublicKey: window.app && window.app.webPushPublicKey ? window.app.webPushPublicKey : null,

    init: async function () {
        var self = this;

        if (!this.isSupported()) {
            $('#push-notifications-unsupported').removeClass('d-none');
            $('#push-notifications-box .form-check').addClass('d-none');
            return;
        }

        this.bindActions();

        try {
            await this.syncState();
        } catch (error) {
            // eslint-disable-next-line no-console
            console.error('Failed to sync push notification state.', error);
        }

        // Optional: keep UI in sync if permission changes while tab is open
        document.addEventListener('visibilitychange', async function () {
            if (document.visibilityState === 'visible') {
                try {
                    await self.syncState();
                } catch (error) {
                    // eslint-disable-next-line no-console
                    console.error('Failed to refresh push notification state.', error);
                }
            }
        });
    },

    isSupported: function () {
        return !!(
            'serviceWorker' in navigator &&
            'PushManager' in window &&
            'Notification' in window &&
            this.vapidPublicKey
        );
    },

    bindActions: function () {
        var self = this;

        $(document).on('change', '#notification_push_enabled', async function () {
            var $toggle = $(this);
            var enabled = $toggle.is(':checked');

            $toggle.prop('disabled', true);

            try {
                if (enabled) {
                    await self.subscribe();
                } else {
                    await self.unsubscribe();
                }

                await self.syncState();
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error(error);

                // Revert UI if action failed
                $toggle.prop('checked', !enabled);

                if (enabled && Notification.permission === 'denied') {
                    alert('Notifications are blocked in your browser settings.');
                } else {
                    alert(enabled
                        ? 'Could not enable notifications.'
                        : 'Could not disable notifications.'
                    );
                }
            } finally {
                $toggle.prop('disabled', false);
            }
        });
    },

    syncState: async function () {
        if (!this.isSupported()) {
            return;
        }

        var $toggle = $('#notification_push_enabled');
        var permission = Notification.permission;
        var subscription = await this.getSubscription();

        // If permission was denied, make sure toggle is off
        if (permission === 'denied') {
            $toggle.prop('checked', false);
            return;
        }

        $toggle.prop('checked', !!subscription);
    },

    buildUrl: function (path) {
        return app.baseUrl.replace(/\/$/, '') + path;
    },

    getSubscription: async function () {
        if (!this.isSupported()) {
            return null;
        }

        var registration = await navigator.serviceWorker.ready;
        return await registration.pushManager.getSubscription();
    },

    subscribe: async function () {
        if (!this.isSupported()) {
            throw new Error('Push notifications are not supported.');
        }

        if (Notification.permission === 'denied') {
            throw new Error('Notifications are blocked in your browser.');
        }

        var permission = Notification.permission;

        if (permission !== 'granted') {
            permission = await Notification.requestPermission();
        }

        if (permission !== 'granted') {
            throw new Error('Permission was not granted.');
        }

        var registration = await navigator.serviceWorker.ready;
        var subscription = await registration.pushManager.getSubscription();

        if (!subscription) {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKey)
            });
        }

        var payload = subscription.toJSON();
        payload.contentEncoding = (PushManager.supportedContentEncodings || ['aes128gcm', 'aesgcm'])[0];
        payload.deviceKey = this.getDeviceKey();

        await $.ajax({
            url: this.buildUrl('/my/setting/push/subscribe'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        return subscription;
    },

    unsubscribe: async function () {
        if (!this.isSupported()) {
            return;
        }

        var registration = await navigator.serviceWorker.ready;
        var subscription = await registration.pushManager.getSubscription();

        if (!subscription) {
            return;
        }

        var endpoint = subscription.endpoint;

        try {
            await subscription.unsubscribe();
        } catch (error) {
            // eslint-disable-next-line no-console
            console.warn('Browser unsubscribe failed, continuing backend cleanup.', error);
        }

        await $.ajax({
            url: this.buildUrl('/my/setting/push/unsubscribe'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                endpoint: endpoint,
                deviceKey: this.getDeviceKey()
            }),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
    },

    getDeviceKey: function () {
        var key = localStorage.getItem('push_device_key');

        if (!key) {
            key = 'dev_' + Math.random().toString(36).slice(2) + Date.now();
            localStorage.setItem('push_device_key', key);
        }

        return key;
    },

    urlBase64ToUint8Array: function (base64String) {
        var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = window.atob(base64);
        var outputArray = new Uint8Array(rawData.length);

        for (var i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }

        return outputArray;
    }
};

$(async function () {
    await window.WebPushManager.init();
});
