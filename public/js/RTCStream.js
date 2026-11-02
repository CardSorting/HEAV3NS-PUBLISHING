/*
* RTMP streams JS component
*/
"use strict";
/* global app, streamVars, launchToast, trans, LivekitClient */


// eslint-disable-next-line no-unused-vars
var RTCStream = {
    room: null,
    remoteVideo: null,
    remoteTrackElements: {},
    userInteracted: false,
    combinedStream: new MediaStream(),

    init: function () {
        this.bindUIEvents();
    },

    bindUIEvents: function () {
        const onUserGesture = () => {
            this.userInteracted = true;
            $('#stream-start-overlay').fadeOut();
            $(document).off('click keydown', onUserGesture);
            this.fetchTokenAndConnect();
        };

        if(streamVars.canWatchStream){
            $(document).on('click keydown', onUserGesture);
        }
    },

    unmuteAudio: function () {
        if (this.remoteVideo && this.remoteVideo.srcObject) {
            this.remoteVideo.muted = false;
            this.remoteVideo.volume = 0.5;
            const playAttempt = this.remoteVideo.play();
            if (playAttempt) {
                // eslint-disable-next-line no-console
                playAttempt.catch(err => console.warn("Audio play() still blocked:", err));
            }
        }
    },

    fetchTokenAndConnect: function () {
        $.post(app.baseUrl + '/my/streams/livekit/token', {
            channel: 'stream_' + streamVars.streamId,
            identity: 'viewer-' + Math.floor(Math.random() * 1000)
        }).done(response => {
            this.connectToRoom(response.token, response.wsUrl);
        }).fail(err => {
            launchToast('danger', trans('Error'), "Error fetching token: " + err.responseJSON.message);
        });
    },

    connectToRoom: function (token, wsUrl) {
        this.room = new LivekitClient.Room();
        this.room.on(LivekitClient.RoomEvent.TrackSubscribed, this.onTrackSubscribed.bind(this));
        this.room.on(LivekitClient.RoomEvent.TrackUnsubscribed, this.onTrackUnsubscribed.bind(this));

        this.room.connect(wsUrl, token).then(() => {
            if(app.debug){
                // eslint-disable-next-line no-console
                console.log("Viewer connected to room");
            }
        }).catch(err => {
            if(app.debug){
                // eslint-disable-next-line no-console
                console.error("Viewer connection error:", err);
            }
        });
    },

    onTrackSubscribed: function (track, publication, participant) {
        const key = participant.sid + '_' + track.kind;

        if (this.remoteTrackElements[key]) {
            this.remoteTrackElements[key].remove();
        }

        try {
            track.mediaStreamTrack.enabled = true;
            this.combinedStream.addTrack(track.mediaStreamTrack);

            if (!this.remoteVideo) {
                const videoEl = document.createElement('video');
                videoEl.srcObject = this.combinedStream;
                videoEl.autoplay = true;
                videoEl.playsInline = true;
                videoEl.controls = true;
                videoEl.style.width = '100%';
                videoEl.style.maxHeight = '70vh';
                videoEl.style.backgroundColor = '#000';

                $('#placeholder').remove();
                $('#remote-videos').empty().append(videoEl);

                this.remoteVideo = videoEl;
                this.remoteTrackElements[key] = videoEl;

                if (this.userInteracted) {
                    videoEl.muted = false;
                    videoEl.volume = 0.5;
                    const attempt = videoEl.play();
                    // eslint-disable-next-line no-console
                    if (attempt) attempt.catch(console.warn);
                }
            }
        } catch (err) {
            if(app.debug) {
                // eslint-disable-next-line no-console
                console.error("Error attaching track:", err);
            }
        }
    },

    onTrackUnsubscribed: function (track, publication, participant) {
        const key = participant.sid + '_' + track.kind;
        if (this.remoteTrackElements[key]) {
            this.remoteTrackElements[key].remove();
            delete this.remoteTrackElements[key];
        }
    }
};
