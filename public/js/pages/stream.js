/*
* Stream viewing JS component
*/
"use strict";
/* global app, user, streamVars, launchToast, trans, Pusher, PusherBatchAuthorizer, pusher, updateButtonState, soketi, socketsDriver, RTCStream, RTMPStream */

// eslint-disable-next-line no-unused-vars
var Stream = {
    pusher: null,
    streamComments: {},

    init: function (driver) {
        this.initAuxiliaryModules();
        this.initStreamPaymentButtons();

        // Driver-specific playback init
        if (driver === 'livekit') {
            RTCStream.init();
        } else if (driver === 'pushr') {
            RTMPStream.init();
        }
    },

    initAuxiliaryModules: function (){
        this.initPusher();
        this.presenceChannelConnect(streamVars.streamId);
        this.chatChannelConnect(streamVars.streamId);
        this.resetTextAreaHeight();

        this.initAutoScroll();
        this.scrollChatToBottom();

        if (streamVars.streamOwnerId === user.user_id) {
            this.showChatMessageActions();
        }
    },

    initPusher: function () {
        Pusher.logToConsole = !!streamVars.pusherDebug;
        let params = {
            authorizer: PusherBatchAuthorizer,
            authDelay: 200,
            authEndpoint: app.baseUrl + '/authorizeStreamPresence',
            auth: {
                headers: {
                    'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')
                }
            }
        };

        if (socketsDriver === 'soketi') {
            params.wsHost = soketi.host;
            params.wsPort = soketi.port;
            params.forceTLS = !!soketi.useTSL;
        } else {
            params.cluster = streamVars.pusherCluster;
        }

        this.pusher = new Pusher(socketsDriver === 'soketi' ? soketi.key : pusher.key, params);
    },

    presenceChannelConnect: function (streamId) {
        var presenceChannel = this.pusher.subscribe("presence-stream-" + streamId);
        presenceChannel.bind('pusher:subscription_succeeded', members => {
            this.setLiveUsersCount(members.count);
        });
        presenceChannel.bind('pusher:member_added', () => {
            this.setLiveUsersCount(presenceChannel.members.count);
        });
        presenceChannel.bind('pusher:member_removed', () => {
            this.setLiveUsersCount(presenceChannel.members.count);
        });
    },

    chatChannelConnect: function (streamId) {
        let channel = this.pusher.subscribe('private-stream-chat-channel-' + streamId);
        channel.bind('new-message', data => {
            if (data.userId !== user.user_id) {
                this.appendCommentToStreamChat(data.message);
                this.updateChatNoCommentsLabel();
            }
        });

        channel.bind('new-tip', data => {
            if (data.userId !== user.user_id) {
                Stream.addTipBox(data.message);
            }
        });

    },

    sendMessage: function (streamId) {
        updateButtonState('loading', $('.send-message'));

        if (!$('.messageBoxInput').val().trim()) {
            $('.messageBoxInput').addClass('is-invalid');
            updateButtonState('loaded', $('.send-message'));
            return false;
        } else {
            $('.messageBoxInput').removeClass('is-invalid');
        }

        $.post(app.baseUrl + '/stream/comments/add', {
            message: $('.messageBoxInput').val(),
            streamId: streamId
        }, result => {
            this.appendCommentToStreamChat(result.dataHtml);
            this.updateChatNoCommentsLabel();
            this.resetTextAreaHeight();
            updateButtonState('loaded', $('.send-message'));
        }).fail(result => {
            launchToast(
                'danger',
                trans('Error'),
                (result.responseJSON && result.responseJSON.message) ? result.responseJSON.message : trans('Too many attempts. Please try again later.')
            );
            updateButtonState('loaded', $('.send-message'));
        });
    },

    setLiveUsersCount: function (val) {
        $('.live-stream-users-count').text(val);
    },

    appendCommentToStreamChat: function (comment) {
        $('.conversation-content').append(comment);
        $('.messageBoxInput').val('');
        if (streamVars.streamOwnerId === user.user_id) {
            this.showChatMessageActions();
        }
        this.scrollChatToBottom();
    },

    scrollChatToBottom: function () {
        if ($('.conversation-content .stream-chat-message').length) {
            $(".conversation-content").animate({ scrollTop: $('.conversation-content')[0].scrollHeight }, 800);
        }
    },

    initAutoScroll: function () {
        $(".messageBoxInput").keydown(function (e) {
            if (e.keyCode === 13 && !e.shiftKey) {
                e.preventDefault();
                $('.send-message').trigger('click');
            }
        });
    },

    showChatMessageActions: function () {
        $('.chat-message-action').removeClass('d-none');
    },

    deleteComment: function (commentId) {
        if (confirm(trans("Are you sure you want to delete this comment?"))) {
            $.ajax({
                type: 'DELETE',
                url: app.baseUrl + '/stream/comments/delete',
                data: { id: commentId },
                success: () => {
                    $('[data-commentid="' + commentId + '"]').remove();
                    this.updateChatNoCommentsLabel();
                    launchToast('success', trans('Success'), trans('Comment removed'));
                },
                error: result => {
                    launchToast('danger', trans('Error'), result.responseJSON.message);
                }
            });
        }
    },

    resetTextAreaHeight: function () {
        $(".messageBoxInput").css('height', 45);
    },

    updateChatNoCommentsLabel: function () {
        if ($('.conversation-content .stream-chat-message').length) {
            $('.no-chat-comments-label').addClass('d-none').removeClass('d-flex');
        } else {
            $('.no-chat-comments-label').addClass('d-flex').removeClass('d-none');
        }
    },

    initStreamPaymentButtons: function () {
        $('.stream-subscribe-label').on('click', () => $('.stream-subscribe-button').click());
        $('.stream-unlock-label').on('click', () => $('.stream-unlock-button').click());
    },

    /**
     * Adds a new tip to the tips wrapper
     * If there are more than 3 already, fade out the oldest and remove it
     * @param renderedHtml
     */
    addTipBox: function(renderedHtml) {
        // Find/create the tips row
        let $wrapper = $('.stream-tips');
        if (!$wrapper.length) {
            $wrapper = $(`
      <div class="stream-tips px-3 pt-3">
        <div class="px-3">
          <div class="row"></div>
        </div>
      </div>
    `).appendTo('.stream-details-wrapper');
        }

        const $row = $wrapper.find('.row').first();

        // Build element hidden, prepend, then fade in (like appendPostResults)
        const $item = $(renderedHtml).hide();
        $row.prepend($item);
        $item.fadeIn('slow');

        // Keep only latest 3: fade out any beyond the third, then remove
        const $boxes = $row.children('.col-4');
        if ($boxes.length > 3) {
            $boxes.slice(3).each(function () {
                $(this).fadeOut(200, function () { $(this).remove(); });
            });
        }
    }


};
