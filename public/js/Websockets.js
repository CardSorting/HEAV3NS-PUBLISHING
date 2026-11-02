"use strict";

/**
 * Websockets object
 * -----------------
 * Stores all logic for initializing our Pusher (or Soketi) connection
 * and binding events to channels.
 */
/* global app, user, pusher, Pusher, notifications, filterXSS,
soketi, socketsDriver, messenger, FileUpload, trans,
incrementNotificationsCount, launchToast */

var Websockets = {

    pusherClient: null,
    privateChannel: null,
    presenceChannel: null,

    initialize: function () {
        if (typeof user !== 'undefined' && user.is_suspended) {
            return;
        }

        // If "app" or "user" is missing, we cannot proceed
        if (typeof app === 'undefined' || typeof user === 'undefined') {
            // eslint-disable-next-line no-console
            console.warn("Websockets: 'app' or 'user' is undefined. Initialization aborted.");
            return;
        }

        try {

            Pusher.logToConsole = app.debug;
            // 1) Create the Pusher (or Soketi) configuration
            const config = this.createPusherConfig();

            // 2) Get the correct key
            const key = this.getPusherKey();

            // 3) Initialize the client
            this.pusherClient = new Pusher(key, config);

            // [Optional Debug – you can remove or tweak in production]
            this.debugConnectionEvents();

            // 4) Subscribe to our user-specific (private) channel
            this.privateChannel = this.pusherClient.subscribe(user.username);

            // 5) Subscribe to the presence channel
            //    (We still subscribe to it, but we'll only bind events if logging is on)
            if(app.show_online_users_indicator){
                this.presenceChannel = this.pusherClient.subscribe('presence-global');
            }

            // 6) Bind event handlers
            const currentLocation = window.location.href;
            this.bindPrivateChannelEvents(this.privateChannel, currentLocation);
            if(app.show_online_users_indicator) {
                this.bindPresenceChannelEvents(this.presenceChannel, currentLocation);
            }

        } catch (e) {
            // eslint-disable-next-line no-console
            console.warn("Websockets initialization failed:", e);
        }
    },

    /**
     * Creates the Pusher (or Soketi) config object, including the custom authorizer logic.
     */
    createPusherConfig: function () {
        let config = {
            cluster: (typeof pusher !== 'undefined') ? pusher.cluster : '',
            forceTLS: true,
            authorizer: this.createCustomAuthorizer()
        };

        if (typeof socketsDriver !== 'undefined' && socketsDriver === 'soketi') {
            config.wsHost = soketi.host;
            config.wsPort = soketi.port;
            config.forceTLS = soketi.useTSL === true;
            config.authorizer = this.createCustomAuthorizer();
        }

        return config;
    },

    /**
     * Determines which key to use (Pusher or Soketi).
     */
    getPusherKey: function () {
        if (typeof socketsDriver !== 'undefined' && socketsDriver === 'soketi') {
            return soketi.key;
        }
        return (typeof pusher !== 'undefined') ? pusher.key : '';
    },

    /**
     * Returns a custom authorizer function to handle presence vs private channels.
     */
    createCustomAuthorizer: function () {
        return function (channel) {
            // Default endpoint for private channels
            let endpoint = app.baseUrl + '/my/messenger/authorizeUser';

            // If this is a presence channel, use the presence endpoint
            if (channel.name.startsWith('presence-')) {
                endpoint = app.baseUrl + '/auth/presence-channel';
            }

            return {
                authorize: function (socketId, callback) {
                    $.ajax({
                        url: endpoint,
                        type: 'POST',
                        data: {
                            socket_id: socketId,
                            'channel_name[]': channel.name
                        },
                        headers: {
                            'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function (response) {
                            callback(false, response);
                        },
                        error: function (xhr) {
                            // eslint-disable-next-line no-console
                            console.error('Authorization error:', xhr);
                            callback(true, xhr);
                        }
                    });
                }
            };
        };
    },

    /**
     * (Optional) Debug: Bind connection-level events to see pusher's internal logs.
     * Only do this if Pusher.logToConsole = true in development.
     */
    debugConnectionEvents: function () {
        // If you definitely only want logs when Pusher.logToConsole === true:
        if (!Pusher.logToConsole) {
            return;
        }

        this.pusherClient.connection.bind('state_change', (states) => {
            // eslint-disable-next-line no-console
            console.log('Pusher state changed:', states.previous, '->', states.current);
        });
        this.pusherClient.connection.bind('error', (err) => {
            // eslint-disable-next-line no-console
            console.warn('Pusher connection error:', err);
        });
        this.pusherClient.connection.bind('connected', () => {
            // eslint-disable-next-line no-console
            console.log('Pusher is connected');
        });
        // Uncomment if you want a global listener for all events
        // this.pusherClient.bind_global((eventName, data) => {
        //   console.log('[Global Event]', eventName, data);
        // });
    },

    /**
     * Binds event handlers for the user-specific channel.
     */
    bindPrivateChannelEvents: function (channel, location) {
        if (!channel) return;

        // Reconcile after the user channel is ready to receive events.
        channel.bind('pusher:subscription_succeeded', function () {
            if(typeof FileUpload !== 'undefined' && typeof FileUpload.syncTranscodingStatuses === 'function'){
                FileUpload.syncTranscodingStatuses();
            }
        });

        channel.bind('new-notification', function (data) {
            let toastTitle = trans('Notification');
            if (data.type === 'new-message') {
                toastTitle = 'New message';
                incrementNotificationsCount('.menu-notification-badge.chat-menu-count');
            }
            incrementNotificationsCount('.menu-notification-badge.notifications-menu-count');

            if (location.indexOf('/my/notifications') >= 0) {
                notifications.updateUserNotificationsList();
            }
            if (location.indexOf('my/messenger') >= 0 && data.type === 'new-message') {
                return;
            }
            launchToast('success', trans(toastTitle), filterXSS(data.message));
        });

        channel.bind('messenger-actions', function (data) {
            if (data.type === 'new-messenger-conversation' && location.indexOf('my/messenger') >= 0) {
                messenger.fetchContacts();
                messenger.fetchConversation(data.notification.fromUserID);
                messenger.hideEmptyChatElements();
                messenger.reloadConversationHeader();
            }
        });

        channel.bind('video-processing', function (data) {
            if(typeof FileUpload !== 'undefined' && typeof FileUpload.applyTranscodingUpdate === 'function'){
                FileUpload.applyTranscodingUpdate(data);
            }
        });

    },

    /**
     * Binds event handlers for the presence channel (e.g. presence-global),
     * but *only* if Pusher.logToConsole == true.
     */
    bindPresenceChannelEvents: function (channel) {
        if (!channel) return;

        // Only bind these events if console logging is enabled
        if (!Pusher.logToConsole) {
            return;
        }

        channel.bind('pusher:subscription_succeeded', (members) => {
            // eslint-disable-next-line no-console
            console.log('[Presence] subscription_succeeded. Members:', members);
        });

        channel.bind('pusher:member_added', (member) => {
            // eslint-disable-next-line no-console
            console.log('[Presence] member_added:', member);
        });

        channel.bind('pusher:member_removed', (member) => {
            // eslint-disable-next-line no-console
            console.log('[Presence] member_removed:', member);
        });

        channel.bind('pusher:subscription_error', (status) => {
            // eslint-disable-next-line no-console
            console.error('[Presence] subscription_error:', status);
        });
    }
};

/**
 * jQuery document ready
 */
$(function () {
    Websockets.initialize();
});
