/**
 * Messages Component
 */
"use strict";
/* global app, messengerVars, pusher, FileUpload,
  Lists, Pusher, PusherBatchAuthorizer, updateButtonState, updateTooltipTitle,
  mswpScanPage, trans, incrementNotificationsCount, passesMinMaxPPVMessageLimits
  launchToast, initTooltips, soketi, socketsDriver, showDialog, hideDialog, noConversationLabel, noMessagesLabel,
  newConversationLabel, contactElement, noContactsLabel, noContactsSearchLabel, noMessageSearchResultsLabel, messageDateDividerElement, getMessageDateKey, messageElement,
  verifiedBadgeElement, staffBadgeElement, messengerSanitizeText, messengerEscapeAttr, messengerSafeUrl, messengerString, mediaSettings, bindNoLongPressEvents, Autolinker, user, GifPicker, GifMedia, MessengerPins */

$(function () {
    if(messengerVars.bootFullMessenger){
        messenger.boot();
        messenger.initConversationHistory();
        messenger.loadMessageDrafts();
        messenger.fetchContacts(function () {
            messenger.openInitialConversation();
        });
        messenger.initContactsSearch();
        messenger.initConversationMessageSearch();
        messenger.initAutoScroll();
        messenger.initMarkAsSeen();
        messenger.initMessageActionsToggle();
        messenger.resetTextAreaHeight();
        messenger.initResponsiveLayout();
        messenger.initScrollPagination();
        FileUpload.initDropZone('.dropzone','/attachment/upload/message', mediaSettings.use_chunked_uploads);
        messenger.initVoiceRecorder();
        messenger.initSelectizeUserList();
        messenger.initMessageTemplatesUI();
        messenger.initMessageDrafts();
        messenger.initGifComposer();
    }
    messenger.initNewConversationUI();
});

$(document).on("click", ".story-reply-bubble", function (e) {
    e.preventDefault();

    var storyId = $(this).data("story-id");
    if (!storyId) return;

    if (!window.StoriesSwiper) return;

    window.StoriesSwiper.openByStoryIdOrFetch(storyId);
});

function adjustMessengerLayout() {
    var shell = document.querySelector('.messenger-shell');
    var mobileNavHeight = $('.mobile-bottom-nav').outerHeight() || $('.fixed-bottom').outerHeight() || 0;
    var isMobile = window.matchMedia('(max-width: 991.98px)').matches;
    var viewportHeight = Math.max(420, window.innerHeight - (isMobile ? mobileNavHeight : 0));

    if (!shell) return;
    shell.style.height = viewportHeight + 'px';
}

var messenger = {

    state : {
        contacts:[],
        conversation:[],
        activeConversationUserID:null,
        activeConversationUser:null,
        redirectedToMessage: false,
        messagePrice: 5,
        isPaidMessage: false,
        activeMessageID: null,
        receiverIDs: [],
        initialConversationOpened: false,
        newConversationMode: false,
        newConversationSelectAllToggle: false,
        contactSearchQuery: '',
        contactSearchTimer: null,
        conversationSearchQuery: '',
        conversationSearchTimer: null,
        isSendingMessage: false,
        activeMobilePane: 'contacts',
        messageTemplateMode: false,
        messageTemplates: {},
        activeMessageTemplateTrigger: 'follower_created',
        messageTemplatesLoaded: false,
        isSavingMessageTemplate: false,
        messageDrafts: {},
        messageDraftSaveTimer: null,
        isRestoringMessageDraft: false,
        contactPagination: {
            limit: 15,
            hasMore: false,
            loading: false,
            request: null,
            fillTimer: null,
        },
        conversationPagination: {
            limit: 30,
            hasMore: false,
            loading: false,
            oldestMessageId: null,
            request: null,
        },
        liveChannelKeys: {},
        voiceRecorder: {
            mediaRecorder: null,
            stream: null,
            chunks: [],
            timer: null,
            waveformAnimation: null,
            audioContext: null,
            audioSource: null,
            analyser: null,
            waveformData: null,
            seconds: 0,
            mimeType: null,
            extension: null,
            isRecording: false,
            shouldSave: true,
        },
    },

    maxNewConversationContacts: 60,

    pusher: null,
    selectizeInstance: null,

    scrollConversationToBottom: function(){
        const content = $('.conversation-content')[0];
        if(!content){
            return;
        }

        const scrollFn = function () {
            content.scrollTop = content.scrollHeight;
        };

        scrollFn();
        window.requestAnimationFrame(scrollFn);
        window.setTimeout(scrollFn, 60);
        window.setTimeout(scrollFn, 180);
    },

    toggleContactsSpinner: function(loading){
        $('.contacts-pagination').toggleClass('d-none', !loading && !messenger.state.contactPagination.hasMore);
        $('.messenger-contacts-spinner').toggleClass('d-none', !loading);
    },

    toggleMessagesSpinner: function(loading){
        $('.conversation-history-control').toggleClass('d-none', !loading && !messenger.state.conversationPagination.hasMore);
        $('.messenger-messages-spinner').toggleClass('d-none', !loading);
    },

    renderNewConversationPlaceholder: function(){
        $('.messenger-shell').addClass('new-conversation-active');
        $('.conversation-loading-box').addClass('d-none');
        $('.conversation-header, .conversation-header-loading-box').addClass('d-none');
        $('.conversation-history-control').addClass('d-none');
        $('.conversation-content').removeClass('d-none').html(newConversationLabel());
    },

    initConversationHistory: function(){
        if(!window.history || !window.history.pushState){
            return;
        }

        $(window).off('popstate.messengerHistory').on('popstate.messengerHistory', function (event) {
            const state = event.originalEvent && event.originalEvent.state ? event.originalEvent.state : {};
            const username = state.conversation || messenger.getConversationUsernameFromUrl();

            if(username){
                messenger.fetchConversation(username, {
                    updateHistory: false,
                    showLoader: true,
                });
            }
            else{
                messenger.setMobilePane('contacts');
            }
        });
    },

    openInitialConversation: function(){
        if(messenger.state.initialConversationOpened){
            return;
        }

        messenger.state.initialConversationOpened = true;

        const conversationUsername = messenger.getConversationUsernameFromUrl();
        if(conversationUsername){
            messenger.seedMobileConversationHistory(conversationUsername);
            messenger.fetchConversation(conversationUsername, {
                updateHistory: false,
                replaceHistory: !messenger.isMobileLayout(),
            });
            return;
        }

        if(messenger.isMobileLayout()){
            $('.conversation-content').html(noConversationLabel());
            messenger.setMobilePane('contacts');
            return;
        }

        if(messengerVars.lastContactID !== false && messengerVars.lastContactID !== 0){
            messenger.fetchConversation(messengerVars.lastContactID, {
                updateHistory: false,
                replaceHistory: true,
            });
        }
        else{
            $('.conversation-content').html(noConversationLabel());
            messenger.setMobilePane('contacts');
        }
    },

    isMobileLayout: function(){
        return window.matchMedia('(max-width: 991.98px)').matches;
    },

    getConversationUsernameFromUrl: function(){
        try{
            return (new URL(window.location.href)).searchParams.get('conversation') || '';
        }
        catch(e){
            return '';
        }
    },

    seedMobileConversationHistory: function(username){
        if(!messenger.isMobileLayout() || !window.history || !window.history.replaceState || !window.history.pushState || !username){
            return;
        }

        try{
            const conversationUrl = new URL(window.location.href);
            const contactsUrl = new URL(window.location.href);
            contactsUrl.searchParams.delete('conversation');

            window.history.replaceState({}, '', contactsUrl.toString());
            window.history.pushState({conversation: username}, '', conversationUrl.toString());
        }
        // eslint-disable-next-line no-empty
        catch(e){}
    },

    updateConversationHistory: function(contact, options){
        if(!window.history || !window.history.pushState || !contact || !contact.username){
            return;
        }

        options = options || {};

        let url;
        try{
            url = new URL(window.location.href);
        }
        catch(e){
            return;
        }

        const currentUsername = url.searchParams.get('conversation');
        url.searchParams.set('conversation', contact.username);

        const state = {
            conversation: contact.username,
        };

        if(options.replace || currentUsername === contact.username){
            window.history.replaceState(state, '', url.toString());
        }
        else{
            window.history.pushState(state, '', url.toString());
        }
    },

    clearConversationHistory: function(){
        if(!window.history || !window.history.replaceState){
            return;
        }

        try{
            const url = new URL(window.location.href);
            url.searchParams.delete('conversation');
            window.history.replaceState({}, '', url.toString());
        }
        // eslint-disable-next-line no-empty
        catch(e){}
    },

    initScrollPagination: function(){
        $('.conversations-list').off('scroll.messengerPagination').on('scroll.messengerPagination', function () {
            const remaining = this.scrollHeight - this.scrollTop - this.clientHeight;
            if(remaining <= 120){
                messenger.fetchContacts({append: true, triggeredByScroll: true});
            }
        });

        $('.conversation-content').off('scroll.messengerPagination').on('scroll.messengerPagination', function () {
            if(this.scrollTop <= 80){
                messenger.loadOlderMessages();
            }
        });
    },

    /**
     * Boots up the main messenger functions
     */
    boot: function(){
        Pusher.logToConsole = app.debug;
        let params = {
            authorizer: PusherBatchAuthorizer,
            authDelay: 200,
            authEndpoint: app.baseUrl + '/my/messenger/authorizeUser',
            auth: {
                headers: {
                    'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')
                }
            }
        };
        if(socketsDriver === 'soketi'){
            params.wsHost = soketi.host;
            params.wsPort = soketi.port;
            params.forceTLS = soketi.useTSL ? true : false;
        }
        else{
            params.cluster = messengerVars.pusherCluster;
        }
        messenger.pusher = new Pusher(socketsDriver === 'soketi' ? soketi.key : pusher.key, params);
    },

    initResponsiveLayout: function(){
        adjustMessengerLayout();
        $(window).off('resize.messengerLayout').on('resize.messengerLayout', function () {
            adjustMessengerLayout();
            messenger.setMobilePane(messenger.state.activeConversationUserID ? messenger.state.activeMobilePane : 'contacts');
            messenger.scheduleContactsViewportFill();
        });

        $('.messenger-mobile-back').off('click').on('click', function () {
            if(messenger.isMobileLayout() && messenger.getConversationUsernameFromUrl()){
                window.history.back();
                return;
            }

            messenger.setMobilePane('contacts');
        });
    },

    setMobilePane: function(pane){
        messenger.state.activeMobilePane = pane;

        if(!messenger.isMobileLayout()){
            $('.messenger-shell').removeClass('messenger-show-conversation');
            return;
        }

        $('.messenger-shell').toggleClass('messenger-show-conversation', pane === 'conversation');
        if(pane === 'contacts'){
            messenger.scheduleContactsViewportFill();
        }
    },

    /**
     * Instantiates pusher sockets for each conversation (batched)
     */
    initLiveSockets: function(){
        if(!messenger.pusher){
            return;
        }

        $.each(messenger.state.contacts, function (k, v) {
            const minID = Math.min(v.receiverID,v.senderID);
            const maxID = Math.max(v.receiverID,v.senderID);
            const keyID = ("" + minID + '-' + maxID);

            if(messenger.state.liveChannelKeys[keyID]){
                return;
            }

            messenger.state.liveChannelKeys[keyID] = true;
            let channel = messenger.pusher.subscribe('private-chat-channel-'+keyID);
            channel.unbind('new-message');
            channel.bind('new-message', function(data) {
                const message = jQuery.parseJSON(data.message);
                messenger.applyLiveMessage(message);
            });
            channel.unbind('message-pins-changed');
            channel.bind('message-pins-changed', function(){
                if(typeof MessengerPins !== 'undefined'){
                    const contactId = Number(v.senderID) === Number(user.user_id) ? v.receiverID : v.senderID;
                    MessengerPins.refresh(contactId);
                }
            });
        });
    },

    applyLiveMessage: function (message) {
        const sentByMe = Number(message.sender_id) === Number(user.user_id);
        const contactID = sentByMe ? Number(message.receiver_id) : Number(message.sender_id);
        const active = Number(messenger.state.activeConversationUserID) === contactID;
        if(active && typeof MessengerPins !== 'undefined' && MessengerPins.deferLiveMessage()){
            messenger.addLatestMessageToConversation(contactID, message);
            messenger.fetchContacts({preserveLength: true, silent: true});
            return;
        }
        const alreadyPresent = messenger.state.conversation.some(function (item) { return String(item.id) === String(message.id); });
        if(sentByMe){
            // Shared channel events are safe for the recipient. Other sender tabs fetch their own authorized view.
            if(active && !alreadyPresent && !messenger.state.isSendingMessage && !messenger.state.messageTemplateMode && !messenger.state.newConversationMode){
                $.ajax({
                    type: 'GET', url: app.baseUrl + '/my/messenger/fetchMessages/' + encodeURIComponent(contactID),
                    data: {limit: messenger.state.conversationPagination.limit},
                    success: function (result) {
                        if(Number(messenger.state.activeConversationUserID) !== contactID || messenger.state.messageTemplateMode || messenger.state.newConversationMode
                            || (typeof MessengerPins !== 'undefined' && MessengerPins.historyView)) return;
                        const ownedMessage = (((result || {}).data || {}).messages || []).find(function (item) { return String(item.id) === String(message.id); });
                        if(!ownedMessage || messenger.state.conversation.some(function (item) { return String(item.id) === String(message.id); })) return;
                        messenger.state.conversation.push(ownedMessage);
                        messenger.state.conversation.sort(function (a, b) { return Number(a.id) - Number(b.id); });
                        messenger.reloadConversation({scrollToBottom: true});
                    }
                });
            }
        }
        else if(active){
            if(!alreadyPresent) messenger.state.conversation.push(message);
            messenger.reloadConversation({scrollToBottom: true});
            messenger.markConversationAsRead(contactID, 'read');
        }
        else{
            messenger.markConversationAsRead(contactID, 'unread');
            messenger.updateUnreadMessagesCount(parseInt($('#unseenMessages').html()) + 1);
        }
        messenger.addLatestMessageToConversation(contactID, message);
        messenger.fetchContacts({preserveLength: true, silent: true});
    },

    /**
     * Initiate chatbox scroll to bottom event
     */
    initAutoScroll: function(){
        $(".messageBoxInput").keydown(function(e){
            if (e.keyCode === 13 && !e.shiftKey)
            {
                e.preventDefault();
                $('.send-message').trigger('click');
            }
        });
    },

    /**
     * Fetches messenger contacts
     */
    fetchContacts: function (options, callback) {
        if(typeof options === 'function'){
            callback = options;
            options = {};
        }

        options = $.extend({
            append: false,
            preserveLength: false,
            silent: false,
        }, options || {});

        callback = callback || function(){};

        if(messenger.state.isSendingMessage && messenger.state.newConversationMode){
            return true;
        }

        if(messenger.state.contactPagination.loading){
            if(!options.append && messenger.state.contactPagination.request){
                messenger.state.contactPagination.request.abort();
                messenger.state.contactPagination.loading = false;
            }
            else{
                return true;
            }
        }

        messenger.state.contactPagination.loading = true;
        if(options.append){
            messenger.toggleContactsSpinner(true);
        }

        let requestLimit = messenger.state.contactPagination.limit;
        let requestOffset = options.append ? messenger.state.contacts.length : 0;

        if(options.preserveLength && messenger.state.contacts.length > requestLimit){
            requestLimit = messenger.state.contacts.length;
        }

        const contactRequest = $.ajax({
            type: 'GET',
            url: app.baseUrl + '/my/messenger/fetchContacts',
            data: {
                limit: requestLimit,
                offset: requestOffset,
                query: messenger.state.contactSearchQuery
            },
            dataType: 'json',
            success: function (result) {
                if(messenger.state.contactPagination.request !== contactRequest){
                    return;
                }

                messenger.state.contactPagination.loading = false;
                messenger.state.contactPagination.request = null;

                if(result.status !== 'success'){
                    return;
                }

                const fetchedContacts = result.data.contacts || [];

                if(options.append){
                    const knownContacts = {};
                    $.each(messenger.state.contacts, function (index, contact) {
                        knownContacts[contact.contactID] = true;
                    });

                    $.each(fetchedContacts, function (index, contact) {
                        if(!knownContacts[contact.contactID]){
                            messenger.state.contacts.push(contact);
                        }
                    });
                }
                else{
                    messenger.state.contacts = fetchedContacts;
                }

                messenger.state.contactPagination.hasMore = !!result.data.hasMore;
                messenger.toggleContactsSpinner(false);
                messenger.reloadContactsList();
                messenger.initLiveSockets();
                callback();
            },
            error: function () {
                if(messenger.state.contactPagination.request !== contactRequest){
                    return;
                }

                messenger.state.contactPagination.loading = false;
                messenger.state.contactPagination.request = null;
                messenger.toggleContactsSpinner(false);
            }
        });
        messenger.state.contactPagination.request = contactRequest;
    },

    initContactsSearch: function(){
        const searchWrapper = $('.conversation-search');
        const toggleButton = $('.messenger-contacts-search-toggle');
        const searchInput = $('.messenger-contacts-search');
        const clearButton = $('.messenger-contacts-search-clear');

        if(!searchInput.length){
            return;
        }

        toggleButton.off('click.messengerContactsSearch').on('click.messengerContactsSearch', function () {
            const isOpening = searchWrapper.hasClass('d-none');
            searchWrapper.toggleClass('d-none', !isOpening);
            toggleButton.toggleClass('active', isOpening);

            if(isOpening){
                searchInput.trigger('focus');
            }
            else{
                messenger.clearContactsSearch(false);
            }
        });

        searchInput.off('input.messengerContactsSearch').on('input.messengerContactsSearch', function () {
            const query = $(this).val().trim();
            clearButton.toggleClass('d-none', query.length === 0);

            clearTimeout(messenger.state.contactSearchTimer);
            messenger.state.contactSearchTimer = setTimeout(function () {
                if(query === messenger.state.contactSearchQuery){
                    return;
                }

                messenger.state.contactSearchQuery = query;
                messenger.state.contacts = [];
                messenger.state.contactPagination.hasMore = false;
                messenger.fetchContacts({silent: true});
            }, 250);
        });

        clearButton.off('click.messengerContactsSearch').on('click.messengerContactsSearch', function () {
            messenger.clearContactsSearch(true);
        });
    },

    clearContactsSearch: function(focusInput){
        $('.messenger-contacts-search').val('');
        $('.messenger-contacts-search-clear').addClass('d-none');

        clearTimeout(messenger.state.contactSearchTimer);
        if(messenger.state.contactSearchQuery.length){
            messenger.state.contactSearchQuery = '';
            messenger.state.contacts = [];
            messenger.state.contactPagination.hasMore = false;
            messenger.fetchContacts({silent: true});
        }

        if(focusInput){
            $('.messenger-contacts-search').trigger('focus');
        }
    },

    initMessageTemplatesUI: function(){
        $('.messenger-templates-toggle').off('click.messageTemplates').on('click.messageTemplates', function () {
            if(messenger.state.messageTemplateMode){
                messenger.closeMessageTemplatesUI(true);
            }
            else{
                messenger.openMessageTemplatesUI();
            }
        });

        $('.messenger-templates-close').off('click.messageTemplates').on('click.messageTemplates', function () {
            messenger.closeMessageTemplatesUI(true);
        });

        $('.message-template-trigger').off('click.messageTemplates').on('click.messageTemplates', function () {
            const triggerType = $(this).data('trigger');
            if(!triggerType || triggerType === messenger.state.activeMessageTemplateTrigger){
                return;
            }
            messenger.persistActiveMessageTemplateDraft();
            messenger.state.activeMessageTemplateTrigger = triggerType;
            messenger.renderMessageTemplateEditor();
        });
    },

    setComposerSendButtonMode: function(templateMode){
        const title = templateMode ? trans('Save automation') : trans('Send message');
        const icon = templateMode ? 'checkmark-outline' : 'paper-plane';
        $('.send-message')
            .each(function () { updateTooltipTitle(this, title); })
            .html('<div class="d-flex justify-content-center align-items-center"><ion-icon name="' + icon + '"></ion-icon></div>');
    },

    openMessageTemplatesUI: function(){
        messenger.persistActiveMessageDraft(true);
        messenger.closeConversationMessageSearch(false);
        if(messenger.state.newConversationMode){
            messenger.closeNewConversationUI();
        }

        messenger.state.messageTemplateMode = true;
        if(typeof MessengerPins !== 'undefined') MessengerPins.hide();
        $('#messenger-gif-composer [data-gif-toolbar-slot]').addClass('d-none');
        messenger.setGifSelection(null);
        $('.messenger-templates-toggle').addClass('active');
        $('.conversation-header, .new-conversation-header, .conversation-header-loading-box, .conversation-message-search, .conversation-history-control, .conversation-content, .conversation-loading-box').addClass('d-none');
        $('.message-templates-panel').removeClass('d-none');
        $('.conversation-writeup, #messenger-gif-composer').removeClass('hidden');
        $('.tip-btn').addClass('d-none');
        $('.messageBoxInput').attr('placeholder', trans('Write a welcome message..'));
        messenger.setComposerSendButtonMode(true);
        messenger.setMobilePane('conversation');

        if(messenger.state.messageTemplatesLoaded){
            messenger.renderMessageTemplateEditor();
            return;
        }

        $.ajax({
            type: 'GET',
            url: app.baseUrl + '/my/messenger/messageTemplates',
            dataType: 'json',
            success: function (result) {
                messenger.state.messageTemplates = result.data.templates || {};
                messenger.state.messageTemplatesLoaded = true;
                messenger.renderMessageTemplateEditor();
            },
            error: function () {
                launchToast('danger', trans('Error'), trans('Could not load message automations.'));
            }
        });
    },

    closeMessageTemplatesUI: function(restoreConversation){
        if(!messenger.state.messageTemplateMode){
            return;
        }

        messenger.persistActiveMessageTemplateDraft();
        messenger.state.messageTemplateMode = false;
        $('#messenger-gif-composer [data-gif-toolbar-slot]').removeClass('d-none');
        $('.messenger-templates-toggle').removeClass('active');
        $('.message-templates-panel').addClass('d-none');
        $('.conversation-content').removeClass('d-none');
        $('.tip-btn').removeClass('d-none');
        $('.messageBoxInput').attr('placeholder', trans('Write a message..'));
        messenger.setComposerSendButtonMode(false);
        messenger.clearMessageBox();
        messenger.clearMessagePrice();
        messenger.clearFileUploadsState();

        if(restoreConversation === false){
            return;
        }

        if(messenger.state.activeConversationUserID){
            $('.conversation-header').removeClass('d-none');
            messenger.reloadConversation({scrollToBottom: false});
            messenger.restoreActiveMessageDraft();
            if(typeof MessengerPins !== 'undefined'){
                MessengerPins.render();
                MessengerPins.refresh(messenger.state.activeConversationUserID);
            }
        }
        else{
            $('.conversation-content').html(noConversationLabel());
            $('.conversation-writeup, #messenger-gif-composer').addClass('hidden');
            messenger.setMobilePane('contacts');
        }
    },

    getMessageTemplateMeta: function(triggerType){
        const meta = {
            follower_created: {
                title: trans('New followers'),
                description: trans('Sent once when someone follows you.'),
            },
            subscription_created: {
                title: trans('New subscribers'),
                description: trans('Sent once when someone subscribes to you.'),
            },
        };
        return meta[triggerType] || meta.follower_created;
    },

    initMessageDrafts: function(){
        messenger.loadMessageDrafts();

        $('.conversation-writeup .messageBoxInput')
            .off('input.messageDrafts')
            .on('input.messageDrafts', function () {
                messenger.scheduleActiveMessageDraftSave();
            });

        if(FileUpload.myDropzone){
            FileUpload.myDropzone.on('success', function () {
                messenger.scheduleActiveMessageDraftSave();
            });

            FileUpload.myDropzone.on('removedfile', function () {
                messenger.scheduleActiveMessageDraftSave();
            });
        }

        $(window)
            .off('beforeunload.messageDrafts')
            .on('beforeunload.messageDrafts', function (event) {
                messenger.persistActiveMessageDraft(true);
                if (typeof FileUpload !== 'undefined' && FileUpload.isLoading === true) {
                    event.preventDefault();
                    event.returnValue = '';
                    return '';
                }
            });
    },

    getMessageDraftStorageKey: function(){
        const userID = user && user.user_id ? user.user_id : 'guest';
        return 'messengerDrafts:' + userID;
    },

    initGifComposer: function(){
        const container = document.getElementById('messenger-gif-composer');
        if(container && typeof GifPicker !== 'undefined'){
            GifPicker.mount(container, {
                surface: 'messenger',
                onChange: function(){
                    if(!messenger.state.isRestoringMessageDraft){
                        messenger.scheduleActiveMessageDraftSave();
                    }
                },
            });
        }
    },

    getGifSelection: function(){
        const container = document.getElementById('messenger-gif-composer');
        return container && typeof GifPicker !== 'undefined' ? GifPicker.getSelection(container) : null;
    },

    setGifSelection: function(gif){
        const container = document.getElementById('messenger-gif-composer');
        if(container && typeof GifPicker !== 'undefined'){
            const wasRestoring = messenger.state.isRestoringMessageDraft;
            messenger.state.isRestoringMessageDraft = true;
            try {
                // Discard searches from the previous recipient before restoring its selection.
                GifPicker.destroy(container);
                messenger.initGifComposer();
                GifPicker.setSelection(container, gif || null);
            }
            finally {
                messenger.state.isRestoringMessageDraft = wasRestoring;
            }
        }
    },

    loadMessageDrafts: function(){
        try {
            messenger.state.messageDrafts = JSON.parse(localStorage.getItem(messenger.getMessageDraftStorageKey()) || '{}') || {};
        }
        catch (error) {
            messenger.state.messageDrafts = {};
        }
    },

    saveMessageDrafts: function(){
        try {
            localStorage.setItem(messenger.getMessageDraftStorageKey(), JSON.stringify(messenger.state.messageDrafts));
        }
        catch (error) {
            // Ignore storage failures; drafts are a non-critical enhancement.
        }
    },

    getMessageDraftKey: function(userID){
        const parsedUserID = parseInt(userID, 10);
        return parsedUserID ? String(parsedUserID) : null;
    },

    getActiveMessageDraftKey: function(){
        if(messenger.state.messageTemplateMode || messenger.state.newConversationMode){
            return null;
        }
        if(messenger.state.conversationPagination.loading
            && String(messenger.state.receiverIDs[0] || '') !== String(messenger.state.activeConversationUserID || '')){
            // The visible composer still belongs to the previous contact until this fetch completes.
            return null;
        }

        return messenger.getMessageDraftKey(messenger.state.activeConversationUserID);
    },

    getDraftForContactId: function(contactID){
        const key = messenger.getMessageDraftKey(contactID);
        if(!key){
            return null;
        }

        const draft = messenger.state.messageDrafts[key] || null;
        return messenger.isMessageDraftEmpty(draft) ? null : draft;
    },

    isMessageDraftEmpty: function(draft){
        if(!draft){
            return true;
        }

        return !(draft.message || '').trim().length && !(draft.attachments || []).length && !draft.gif;
    },

    getCurrentMessageDraft: function(){
        return {
            message: $('.conversation-writeup .messageBoxInput').val() || '',
            price: messenger.state.isPaidMessage ? messenger.state.messagePrice : 0,
            attachments: FileUpload.getDraftAttachments(),
            gif: messenger.getGifSelection(),
            updated_at: Date.now(),
        };
    },

    scheduleActiveMessageDraftSave: function(){
        window.clearTimeout(messenger.state.messageDraftSaveTimer);
        messenger.state.messageDraftSaveTimer = window.setTimeout(function () {
            messenger.persistActiveMessageDraft(false);
        }, 150);
    },

    persistActiveMessageDraft: function(force){
        if(force){
            window.clearTimeout(messenger.state.messageDraftSaveTimer);
            messenger.state.messageDraftSaveTimer = null;
        }
        const key = messenger.getActiveMessageDraftKey();
        if(!key || messenger.state.isRestoringMessageDraft){
            return;
        }

        messenger.persistMessageDraft(key, force);
    },

    persistMessageDraft: function(key, force){
        if(!key || (messenger.state.isRestoringMessageDraft && force !== true)){
            return;
        }

        const draft = messenger.getCurrentMessageDraft();
        if(messenger.isMessageDraftEmpty(draft)){
            delete messenger.state.messageDrafts[key];
        }
        else{
            messenger.state.messageDrafts[key] = draft;
        }

        messenger.saveMessageDrafts();
        messenger.updateContactDraftPreview(key);
    },

    clearMessageDraft: function(userID){
        const key = messenger.getMessageDraftKey(userID);
        if(!key){
            return;
        }

        delete messenger.state.messageDrafts[key];
        messenger.saveMessageDrafts();
        messenger.updateContactDraftPreview(key);
    },

    updateContactDraftPreview: function(contactID){
        const key = messenger.getMessageDraftKey(contactID);
        if(!key){
            return;
        }

        const contact = (messenger.state.contacts || []).find(function (item) {
            return String(item.contactID) === key;
        });

        if(!contact){
            return;
        }

        const target = $('.contact-' + key).find('.contact-message').first();
        if(!target.length){
            return;
        }

        const draft = messenger.getDraftForContactId(key);
        let previewHtml = '';
        if(draft){
            const draftMessage = draft.message ? String(draft.message).trim() : '';
            const draftPreview = draftMessage.length ? draftMessage : trans(draft.gif ? 'GIF' : 'Attachment');
            previewHtml = '<span class="text-primary">' + messengerSanitizeText(trans('Draft')) + ':</span> ' + messengerSanitizeText(draftPreview);
            if(target.html() !== previewHtml){
                target.html(previewHtml);
            }
            return;
        }

        const lastMessage = contact.lastMessage === null || typeof contact.lastMessage === 'undefined' ? '' : String(contact.lastMessage).trim();
        const currentUserID = Number(user.user_id);
        const lastMessageSenderID = Number(contact.lastMessageSenderID || contact.senderID);
        const hasKnownSender = Number.isFinite(lastMessageSenderID);
        const sentByMe = hasKnownSender && lastMessageSenderID === currentUserID;
        const preview = Number(contact.lastMessageTipID)
            ? messengerSanitizeText(trans('Tip'))
            : (lastMessage.length ? messengerSanitizeText(lastMessage) : messengerSanitizeText(trans(Number(contact.lastMessageHasGif) ? 'GIF' : 'Attachment')));

        previewHtml = (sentByMe && lastMessage.length ? messengerSanitizeText(trans('You')) + ': ' : '') + preview;
        if(target.html() !== previewHtml){
            target.html(previewHtml);
        }
    },

    updateContactSeenState: function(contactID){
        const key = messenger.getMessageDraftKey(contactID);
        if(!key){
            return;
        }

        const contactRow = $('.contact-' + key);
        if(!contactRow.length){
            return;
        }

        contactRow.find('.contact-unread-indicator').remove();
        contactRow.find('.contact-name, .contact-message').removeClass('fw-bold');
    },

    restoreActiveMessageDraft: function(){
        const key = messenger.getActiveMessageDraftKey();
        const draft = key ? messenger.state.messageDrafts[key] : null;

        messenger.state.isRestoringMessageDraft = true;
        $('.conversation-writeup .messageBoxInput').val(draft && draft.message ? draft.message : '');
        messenger.resetTextAreaHeight();

        if(draft && parseFloat(draft.price || 0) > 0){
            messenger.state.messagePrice = draft.price;
            messenger.state.isPaidMessage = true;
            $('#message-price').val(draft.price);
            $('.message-price-lock').addClass('d-none');
            $('.message-price-close').removeClass('d-none');
        }
        else{
            messenger.clearMessagePrice();
        }

        messenger.setFileUploadsState(draft && draft.attachments ? draft.attachments : []);
        messenger.setGifSelection(draft && draft.gif ? draft.gif : null);
        messenger.state.isRestoringMessageDraft = false;
    },

    getActiveMessageTemplate: function(){
        const triggerType = messenger.state.activeMessageTemplateTrigger;
        if(!messenger.state.messageTemplates[triggerType]){
            messenger.state.messageTemplates[triggerType] = {
                id: null,
                trigger_type: triggerType,
                enabled: false,
                message: '',
                price: 0,
                attachments: [],
            };
        }
        return messenger.state.messageTemplates[triggerType];
    },

    persistActiveMessageTemplateDraft: function(){
        if(!messenger.state.messageTemplateMode){
            return;
        }
        const template = messenger.getActiveMessageTemplate();
        template.enabled = $('.message-template-enabled').is(':checked');
        template.message = $('.conversation-writeup .messageBoxInput').val();
        template.price = messenger.state.isPaidMessage ? messenger.state.messagePrice : 0;
        template.attachments = FileUpload.getDraftAttachments();
    },

    renderMessageTemplateEditor: function(){
        const triggerType = messenger.state.activeMessageTemplateTrigger;
        const template = messenger.getActiveMessageTemplate();
        const meta = messenger.getMessageTemplateMeta(triggerType);

        $('.message-template-trigger').removeClass('active');
        $('.message-template-trigger[data-trigger="' + triggerType + '"]').addClass('active');
        $('.message-template-title').text(meta.title);
        $('.message-template-description').text(meta.description);
        $('.message-template-enabled').prop('checked', !!template.enabled);
        $('.conversation-writeup .messageBoxInput').val(template.message || '');
        messenger.resetTextAreaHeight();

        if(parseFloat(template.price || 0) > 0){
            messenger.state.messagePrice = template.price;
            messenger.state.isPaidMessage = true;
            $('#message-price').val(template.price);
            $('.message-price-lock').addClass('d-none');
            $('.message-price-close').removeClass('d-none');
        }
        else{
            messenger.clearMessagePrice();
        }

        messenger.setFileUploadsState(template.attachments || []);
    },

    setFileUploadsState: function(attachments){
        FileUpload.restoreAttachments(attachments || []);
    },

    saveMessageTemplate: function(forceSave){
        if(FileUpload.isRestoring || FileUpload.restoreFailed || FileUpload.pendingVaultSelections > 0) return false;
        if(FileUpload.isBusy() && forceSave === false){
            FileUpload.showUnfinishedUploadsDialog(function () {
                messenger.saveMessageTemplate(true);
            });
            return false;
        }

        if(messenger.state.isSavingMessageTemplate){
            return false;
        }

        messenger.persistActiveMessageTemplateDraft();
        const template = messenger.getActiveMessageTemplate();

        if(template.enabled && parseFloat(template.price || 0) > 0 && template.attachments.length === 0 && !app.isTextOnlyPPVAllowed){
            $('#no-attachments-locked-post').modal('show');
            return false;
        }

        if(template.enabled && !(template.message || '').trim().length && !template.attachments.length){
            launchToast('danger', trans('Error'), trans('Please add a message or attachment before enabling this automation.'));
            return false;
        }

        messenger.state.isSavingMessageTemplate = true;
        updateButtonState('loading', $('.send-message'));

        $.ajax({
            type: 'POST',
            url: app.baseUrl + '/my/messenger/messageTemplates',
            data: {
                trigger_type: template.trigger_type,
                enabled: template.enabled ? 1 : 0,
                message: template.message,
                price: template.price,
                attachments: template.attachments,
            },
            dataType: 'json',
            success: function (result) {
                messenger.state.isSavingMessageTemplate = false;
                messenger.state.messageTemplates[result.data.template.trigger_type] = result.data.template;
                messenger.renderMessageTemplateEditor();
                $('#confirm-post-save').modal('hide');
                updateButtonState('loaded', $('.send-message'));
                messenger.setComposerSendButtonMode(true);
                launchToast('success', trans('Success'), result.message);
            },
            error: function (result) {
                messenger.state.isSavingMessageTemplate = false;
                updateButtonState('loaded', $('.send-message'));
                messenger.setComposerSendButtonMode(true);
                launchToast('danger', trans('Error'), result.responseJSON && result.responseJSON.message ? result.responseJSON.message : trans('Could not save message automation.'));
            }
        });
    },

    initConversationMessageSearch: function(){
        const searchWrapper = $('.conversation-message-search');
        const toggleButton = $('.conversation-message-search-toggle');
        const searchInput = $('.conversation-message-search-input');
        const clearButton = $('.conversation-message-search-clear');

        if(!searchInput.length){
            return;
        }

        toggleButton.off('click.conversationMessageSearch').on('click.conversationMessageSearch', function () {
            if(!messenger.state.activeConversationUserID){
                return;
            }

            const isOpening = searchWrapper.hasClass('d-none');
            searchWrapper.toggleClass('d-none', !isOpening);
            toggleButton.toggleClass('active', isOpening);

            if(isOpening){
                searchInput.trigger('focus');
            }
            else{
                messenger.clearConversationMessageSearch(false, true);
            }
        });

        searchInput.off('input.conversationMessageSearch').on('input.conversationMessageSearch', function () {
            const query = $(this).val().trim();
            clearButton.toggleClass('d-none', query.length === 0);

            clearTimeout(messenger.state.conversationSearchTimer);
            messenger.state.conversationSearchTimer = setTimeout(function () {
                messenger.runConversationMessageSearch(query);
            }, 300);
        });

        clearButton.off('click.conversationMessageSearch').on('click.conversationMessageSearch', function () {
            messenger.clearConversationMessageSearch(true, true);
        });
    },

    runConversationMessageSearch: function(query){
        if(query === messenger.state.conversationSearchQuery || !messenger.state.activeConversationUserID){
            return;
        }

        messenger.state.conversationSearchQuery = query;
        messenger.state.conversationPagination.hasMore = false;
        $('.conversation-message-search-count').addClass('d-none').text('');

        if(query.length === 0){
            messenger.fetchConversation(messenger.state.activeConversationUserID, {
                scrollToBottom: false,
                showLoader: false,
            });
            return;
        }

        messenger.fetchConversation(messenger.state.activeConversationUserID, {
            scrollToBottom: false,
            showLoader: false,
            searchMode: true,
            searchQuery: query,
        });
    },

    clearConversationMessageSearch: function(focusInput, reloadConversation){
        $('.conversation-message-search-input').val('');
        $('.conversation-message-search-clear').addClass('d-none');
        $('.conversation-message-search-count').addClass('d-none').text('');

        clearTimeout(messenger.state.conversationSearchTimer);
        if(messenger.state.conversationSearchQuery.length){
            messenger.state.conversationSearchQuery = '';
            messenger.state.conversationPagination.hasMore = false;

            if(reloadConversation && messenger.state.activeConversationUserID){
                messenger.fetchConversation(messenger.state.activeConversationUserID, {
                    scrollToBottom: false,
                    showLoader: false,
                });
            }
        }

        if(focusInput){
            $('.conversation-message-search-input').trigger('focus');
        }
    },

    closeConversationMessageSearch: function(reloadConversation){
        $('.conversation-message-search').addClass('d-none');
        $('.conversation-message-search-toggle').removeClass('active');
        messenger.clearConversationMessageSearch(false, reloadConversation);
    },

    updateConversationMessageSearchCount: function(){
        const countElement = $('.conversation-message-search-count');
        if(!messenger.state.conversationSearchQuery.length || !countElement.length){
            countElement.addClass('d-none').text('');
            return;
        }

        const count = messenger.state.conversation.length;
        countElement.text(count === 1 ? trans('1 result') : trans(':count results').replace(':count', count));
        countElement.removeClass('d-none');
    },

    /**
     * Fetches conversation with certain user
     * @param userID
     */
    fetchConversation: function (userID, options) {
        options = $.extend({
            beforeId: null,
            aroundId: null,
            mode: 'replace',
            preserveScroll: null,
            scrollToBottom: true,
            showLoader: true,
            searchMode: false,
            searchQuery: '',
            updateHistory: false,
            replaceHistory: false,
        }, options || {});

        if(messenger.state.conversationPagination.loading){
            if(options.mode === 'replace' && messenger.state.conversationPagination.request){
                messenger.state.conversationPagination.request.abort();
                messenger.state.conversationPagination.loading = false;
            }
            else{
                return false;
            }
        }

        if(options.mode === 'replace'){
            if(typeof MessengerPins !== 'undefined') MessengerPins.beginLoad(userID);
            if(!options.searchMode && messenger.state.activeConversationUserID !== userID){
                messenger.persistActiveMessageDraft(true);
                messenger.setGifSelection(null);
            }
            if(!options.searchMode && messenger.state.activeConversationUserID !== userID){
                messenger.closeConversationMessageSearch(false);
            }
            messenger.closeMessageTemplatesUI(false);
            messenger.closeNewConversationUI();
            messenger.state.activeConversationUserID = userID;
            messenger.state.activeConversationUser = null;
            if(!options.searchMode){
                $('.conversation-header').addClass('d-none');
                $('.conversation-header-loading-box').removeClass('d-none');
            }
            $('.conversation-content').html('');
            messenger.state.conversation = [];

            if(options.showLoader){
                $('.conversation-loading-box').removeClass('d-none');
            }
        }

        messenger.state.conversationPagination.loading = true;
        if(options.mode === 'prepend'){
            messenger.toggleMessagesSpinner(true);
        }

        const conversationRequest = $.ajax({
            type: 'GET',
            url: app.baseUrl + '/my/messenger/fetchMessages/' + encodeURIComponent(userID),
            data: {
                limit: messenger.state.conversationPagination.limit,
                before_id: options.beforeId,
                around_id: options.aroundId,
                query: options.searchQuery
            },
            dataType: 'json',
            success: function (result) {
                if(messenger.state.conversationPagination.request !== conversationRequest){
                    return;
                }

                messenger.state.conversationPagination.loading = false;
                messenger.state.conversationPagination.request = null;

                if(result.status !== 'success'){
                    return;
                }

                if(messenger.state.newConversationMode && options.mode === 'replace'){
                    messenger.renderNewConversationPlaceholder();
                    messenger.toggleMessagesSpinner(false);
                    return;
                }

                const messages = result.data.messages || [];
                const activeContact = result.data.contact || null;
                const resolvedUserID = activeContact && activeContact.id ? activeContact.id : userID;
                messenger.state.activeConversationUserID = resolvedUserID;
                messenger.state.activeConversationUser = result.data.contact || messenger.state.activeConversationUser;
                messenger.state.conversationPagination.hasMore = !!result.data.hasMore;
                messenger.state.conversationPagination.oldestMessageId = result.data.oldestMessageId || (messages.length ? messages[0].id : null);
                messenger.hideEmptyChatElements();

                if(options.mode === 'prepend'){
                    messenger.state.conversation = messages.concat(messenger.state.conversation);
                }
                else{
                    messenger.state.conversation = messages;
                    messenger.setActiveContact(resolvedUserID, {
                        markSeen: true,
                        focusInput: !options.searchMode,
                    });
                }

                if(options.mode === 'replace' && !options.searchMode && activeContact && (options.updateHistory || options.replaceHistory)){
                    messenger.updateConversationHistory(activeContact, {
                        replace: options.replaceHistory || !options.updateHistory,
                    });
                }

                messenger.reloadConversation({
                    preserveScroll: options.mode === 'prepend' ? options.preserveScroll : null,
                    scrollToBottom: options.mode === 'prepend' ? false : options.scrollToBottom,
                });
                messenger.reloadConversationHeader();
                if(typeof MessengerPins !== 'undefined') MessengerPins.loaded(result.data, options);
                if(options.mode === 'replace' && !options.searchMode){
                    messenger.restoreActiveMessageDraft();
                }
                messenger.updateConversationMessageSearchCount();

                if(app.feedDisableRightClickOnMedia === true){
                    messenger.disableMesagesRightClick();
                }

                messenger.toggleConversationHistoryControl();
                messenger.toggleMessagesSpinner(false);
                messenger.setMobilePane('conversation');
                initTooltips();
            },
            error: function (result) {
                if(messenger.state.conversationPagination.request !== conversationRequest){
                    return;
                }

                messenger.state.conversationPagination.loading = false;
                messenger.state.conversationPagination.request = null;
                $('.conversation-loading-box').addClass('d-none');
                $('.conversation-header-loading-box').addClass('d-none');
                if(typeof MessengerPins !== 'undefined') MessengerPins.failed(options);
                messenger.toggleMessagesSpinner(false);
                if(result && result.responseJSON && result.responseJSON.message){
                    launchToast('danger', trans('Error'), result.responseJSON.message);
                }
            }
        });
        messenger.state.conversationPagination.request = conversationRequest;
    },

    loadOlderMessages: function(){
        if(!messenger.state.activeConversationUserID || !messenger.state.conversationPagination.hasMore || messenger.state.conversationPagination.loading){
            return false;
        }

        const content = $('.conversation-content')[0];
        const preserveScroll = content ? {
            height: content.scrollHeight,
            top: $('.conversation-content').scrollTop(),
        } : null;

        messenger.fetchConversation(messenger.state.activeConversationUserID, {
            beforeId: messenger.state.conversationPagination.oldestMessageId,
            mode: 'prepend',
            preserveScroll: preserveScroll,
            showLoader: false,
        });
    },

    /**
     * Sends the message
     * @returns {boolean}
     */
    sendMessage: function(forceSave = false) {
        if(FileUpload.isRestoring || FileUpload.restoreFailed || FileUpload.pendingVaultSelections > 0) return false;

        if(messenger.state.messageTemplateMode){
            return messenger.saveMessageTemplate(forceSave);
        }

        const gif = messenger.getGifSelection();
        if(gif && (messenger.state.isPaidMessage || FileUpload.attachaments.length > 0)){
            launchToast('warning', trans('Error'), trans('GIFs cannot be combined with uploads or paid messages.'));
            return false;
        }

        if(messenger.state.voiceRecorder.isRecording){
            launchToast('warning', trans('Recording'), trans('Stop recording before sending.'));
            return false;
        }

        if(FileUpload.isBusy() && forceSave === false){
            FileUpload.showUnfinishedUploadsDialog(function () {
                messenger.sendMessage(true);
            });
            return false;
        }

        if(messenger.state.isPaidMessage && FileUpload.attachaments.length === 0){
            if(!app.isTextOnlyPPVAllowed){
                $('#no-attachments-locked-post').modal('show');
                return false;
            }
        }

        if(messenger.state.isSendingMessage){
            return false;
        }

        updateButtonState('loading',$('.send-message'));

        if(!$('.messageBoxInput').val().trim().length && FileUpload.attachaments.length === 0 && !gif){
            updateButtonState('loaded',$('.send-message'));
            return false;
        }

        messenger.state.isSendingMessage = true;
        const sentReceiverIDs = messenger.state.receiverIDs.slice();

        $.ajax({
            type: 'POST',
            url: app.baseUrl + '/my/messenger/sendMessage',
            data: {
                message: $('.conversation-writeup .messageBoxInput').val(),
                attachments : FileUpload.attachaments,
                receiverIDs : messenger.state.receiverIDs,
                price: messenger.state.isPaidMessage ? messenger.state.messagePrice : 0,
                gif: gif,
            },
            dataType: 'json',
            success: function (result) {
                messenger.state.isSendingMessage = false;
                $.each(sentReceiverIDs, function (index, receiverID) {
                    messenger.clearMessageDraft(receiverID);
                });
                if(messenger.state.messageTemplateMode || sentReceiverIDs.join(',') !== messenger.state.receiverIDs.join(',')){
                    messenger.fetchContacts({preserveLength: true, silent: true});
                    updateButtonState('loaded', $('.send-message'));
                    return;
                }
                messenger.clearMessageBox();
                messenger.clearMessagePrice();
                messenger.resetTextAreaHeight();
                messenger.clearFileUploadsState();
                if(messenger.state.receiverIDs.length === 1){
                    messenger.state.conversation.push(result.data.message);
                    messenger.addLatestMessageToConversation(result.data.message.receiverID,result.data.message);

                    if(messenger.state.newConversationMode){
                        messenger.fetchContacts(function () {});
                        messenger.state.activeConversationUserID = result.data.message.receiver_id;
                        messenger.fetchConversation(result.data.message.receiver_id, {updateHistory: true});
                    }
                    else{
                        if(typeof MessengerPins !== 'undefined' && MessengerPins.historyView){
                            messenger.fetchConversation(messenger.state.activeConversationUserID);
                        }
                        else messenger.reloadConversation({scrollToBottom: true});
                        messenger.fetchContacts({preserveLength: true, silent: true});
                    }

                    messenger.closeNewConversationUI();
                }
                else{
                    const latestContactId = result.data[result.data.length - 1].message.receiver_id;
                    if(messenger.state.newConversationMode){
                        messenger.fetchContacts();
                    }
                    messenger.state.activeConversationUserID = latestContactId;
                    messenger.fetchConversation(latestContactId, {updateHistory: true});
                    initTooltips();
                    if(result.errors){
                        launchToast('danger',trans('Error'),result.errors);
                    }
                }

                $('#confirm-post-save').modal('hide');
                messenger.hideEmptyChatElements();
                messenger.setMobilePane('conversation');
                updateButtonState('loaded', $('.send-message'));
                initTooltips();
            },
            error: function (result) {
                launchToast('danger',trans('Error'),result.responseJSON.message);
                updateButtonState('loaded',$('.send-message'));
                messenger.state.isSendingMessage = false;
            }
        });
    },

    /**
     * Clears up uploaded files
     */
    clearFileUploadsState: function(){
        FileUpload.clearSelection();
        FileUpload.attachaments = [];
        if(FileUpload.myDropzone){
            FileUpload.myDropzone.files = [];
        }
        $('.dropzone-previews').html('');
        FileUpload.isLoading = false;
        FileUpload.refreshTranscodingState();
        FileUpload.updatePreviewsState();
    },

    initVoiceRecorder: function(){
        const config = messenger.getVoiceRecorderConfig();

        if(
            !mediaSettings.voice_messages_enabled ||
            !navigator.mediaDevices ||
            !navigator.mediaDevices.getUserMedia ||
            !config
        ){
            $('.voice-record-toggle').addClass('d-none');
            return false;
        }

        $('.voice-record-toggle').removeClass('d-none');

        $('.voice-record-toggle').off('click').on('click', function () {
            messenger.startVoiceRecording();
        });

        $('.voice-record-stop').off('click').on('click', function () {
            messenger.stopVoiceRecording(true);
        });

        $('.voice-record-cancel').off('click').on('click', function () {
            messenger.stopVoiceRecording(false);
        });
    },

    getVoiceRecorderConfig: function(){
        if(typeof MediaRecorder === 'undefined'){
            return null;
        }

        const candidates = Array.isArray(mediaSettings.voice_recorder_formats) ? mediaSettings.voice_recorder_formats : [];

        for(let i = 0; i < candidates.length; i++){
            if(typeof MediaRecorder.isTypeSupported !== 'function' || MediaRecorder.isTypeSupported(candidates[i].mimeType)){
                return candidates[i];
            }
        }

        return null;
    },

    startVoiceRecording: function(){
        if(messenger.state.voiceRecorder.isRecording){
            return false;
        }

        const config = messenger.getVoiceRecorderConfig();
        if(!config){
            launchToast('danger', trans('Error'), trans('Audio recording is not supported by this browser.'));
            return false;
        }

        navigator.mediaDevices.getUserMedia({audio: true}).then(function (stream) {
            const options = config.mimeType ? {mimeType: config.mimeType} : {};
            let recorder = null;

            try {
                recorder = new MediaRecorder(stream, options);
            }
            catch (error) {
                stream.getTracks().forEach(function (track) {
                    track.stop();
                });
                messenger.cleanupVoiceRecorder();
                launchToast('danger', trans('Error'), trans('Audio recording is not supported by this browser.'));
                return;
            }

            messenger.state.voiceRecorder = {
                mediaRecorder: recorder,
                stream: stream,
                chunks: [],
                timer: null,
                waveformAnimation: null,
                audioContext: null,
                audioSource: null,
                analyser: null,
                waveformData: null,
                seconds: 0,
                mimeType: config.mimeType,
                extension: config.extension,
                isRecording: true,
                shouldSave: true,
            };

            recorder.ondataavailable = function (event) {
                if(event.data && event.data.size > 0){
                    messenger.state.voiceRecorder.chunks.push(event.data);
                }
            };

            recorder.onstop = function () {
                messenger.finishVoiceRecording();
            };

            recorder.start();
            messenger.setVoiceRecordingUI(true);
            messenger.startVoiceRecordingWaveform(stream);
            messenger.updateVoiceRecordingTimer();
            messenger.state.voiceRecorder.timer = window.setInterval(messenger.updateVoiceRecordingTimer, 1000);
        }).catch(function () {
            messenger.cleanupVoiceRecorder();
            launchToast('danger', trans('Error'), trans('Could not access your microphone.'));
        });
    },

    stopVoiceRecording: function(shouldSave){
        const recorderState = messenger.state.voiceRecorder;
        recorderState.shouldSave = shouldSave;

        if(recorderState.mediaRecorder && recorderState.mediaRecorder.state !== 'inactive'){
            recorderState.mediaRecorder.stop();
        }
        else{
            messenger.cleanupVoiceRecorder();
        }
    },

    finishVoiceRecording: function(){
        const recorderState = messenger.state.voiceRecorder;

        if(recorderState.shouldSave && recorderState.chunks.length){
            const mimeType = recorderState.mimeType || 'audio/webm';
            const blob = new Blob(recorderState.chunks, {type: mimeType});

            if(blob.size > 0 && FileUpload.myDropzone){
                const file = new File(
                    [blob],
                    'voice-note-' + Date.now() + '.' + recorderState.extension,
                    {type: mimeType, lastModified: Date.now()}
                );
                FileUpload.myDropzone.addFile(file);
            }
        }

        messenger.cleanupVoiceRecorder();
    },

    cleanupVoiceRecorder: function(){
        const recorderState = messenger.state.voiceRecorder;

        if(recorderState.timer){
            window.clearInterval(recorderState.timer);
        }

        messenger.stopVoiceRecordingWaveform();

        if(recorderState.stream){
            recorderState.stream.getTracks().forEach(function (track) {
                track.stop();
            });
        }

        messenger.state.voiceRecorder = {
            mediaRecorder: null,
            stream: null,
            chunks: [],
            timer: null,
            waveformAnimation: null,
            audioContext: null,
            audioSource: null,
            analyser: null,
            waveformData: null,
            seconds: 0,
            mimeType: null,
            extension: null,
            isRecording: false,
            shouldSave: true,
        };

        messenger.setVoiceRecordingUI(false);
    },

    setVoiceRecordingUI: function(isRecording){
        $('.conversation-writeup').toggleClass('is-recording', isRecording);
        $('.messenger-voice-recorder').toggleClass('d-none', !isRecording);
        $('.messenger-voice-recording-time').text('0:00');
        messenger.drawVoiceWaveformIdle();
    },

    updateVoiceRecordingTimer: function(){
        const recorderState = messenger.state.voiceRecorder;
        const minutes = Math.floor(recorderState.seconds / 60);
        const seconds = recorderState.seconds % 60;

        $('.messenger-voice-recording-time').text(minutes + ':' + String(seconds).padStart(2, '0'));
        recorderState.seconds += 1;
    },

    startVoiceRecordingWaveform: function(stream){
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        const canvas = $('.messenger-voice-waveform').get(0);

        if(!AudioContext || !canvas){
            messenger.drawVoiceWaveformIdle();
            return false;
        }

        try {
            const audioContext = new AudioContext();
            const analyser = audioContext.createAnalyser();
            const audioSource = audioContext.createMediaStreamSource(stream);

            analyser.fftSize = 256;
            analyser.smoothingTimeConstant = 0.82;
            audioSource.connect(analyser);

            if(audioContext.state === 'suspended' && typeof audioContext.resume === 'function'){
                audioContext.resume();
            }

            messenger.state.voiceRecorder.audioContext = audioContext;
            messenger.state.voiceRecorder.audioSource = audioSource;
            messenger.state.voiceRecorder.analyser = analyser;
            messenger.state.voiceRecorder.waveformData = new Uint8Array(analyser.frequencyBinCount);

            messenger.drawVoiceRecordingWaveform();
        }
        catch (error) {
            messenger.drawVoiceWaveformIdle();
        }
    },

    stopVoiceRecordingWaveform: function(){
        const recorderState = messenger.state.voiceRecorder;

        if(recorderState.waveformAnimation){
            window.cancelAnimationFrame(recorderState.waveformAnimation);
        }

        if(recorderState.audioSource){
            try {
                recorderState.audioSource.disconnect();
            }
            catch (error) {
                // Ignore disconnect failures while tearing down the recorder.
            }
        }

        if(recorderState.audioContext && recorderState.audioContext.state !== 'closed'){
            try {
                recorderState.audioContext.close();
            }
            catch (error) {
                // Ignore close failures while tearing down the recorder.
            }
        }

        messenger.drawVoiceWaveformIdle();
    },

    drawVoiceRecordingWaveform: function(){
        const recorderState = messenger.state.voiceRecorder;

        if(!recorderState.isRecording || !recorderState.analyser || !recorderState.waveformData){
            return false;
        }

        recorderState.analyser.getByteFrequencyData(recorderState.waveformData);
        messenger.drawVoiceWaveform(recorderState.waveformData);

        recorderState.waveformAnimation = window.requestAnimationFrame(messenger.drawVoiceRecordingWaveform);
    },

    drawVoiceWaveformIdle: function(){
        messenger.drawVoiceWaveform(null);
    },

    drawVoiceWaveform: function(data){
        const canvas = $('.messenger-voice-waveform').get(0);

        if(!canvas){
            return false;
        }

        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        const width = Math.max(1, Math.floor(rect.width * dpr));
        const height = Math.max(1, Math.floor(rect.height * dpr));

        if(canvas.width !== width || canvas.height !== height){
            canvas.width = width;
            canvas.height = height;
        }

        const context = canvas.getContext('2d');
        const cssColor = getComputedStyle(canvas).getPropertyValue('--messenger-voice-waveform-color').trim();
        const barColor = cssColor || '#dc3545';
        const barCount = Math.max(18, Math.floor(rect.width / 8));
        const barWidth = Math.max(2 * dpr, Math.floor(width / (barCount * 2)));
        const gap = Math.max(2 * dpr, Math.floor((width - (barCount * barWidth)) / Math.max(1, barCount - 1)));
        const centerY = height / 2;
        const minBarHeight = 3 * dpr;
        const maxBarHeight = height * 0.82;

        context.clearRect(0, 0, width, height);
        context.fillStyle = barColor;

        for(let i = 0; i < barCount; i++){
            const dataIndex = data ? Math.floor((i / barCount) * data.length) : null;
            const level = data ? data[dataIndex] / 255 : 0.18;
            const easedLevel = Math.pow(level, 1.35);
            const barHeight = Math.max(minBarHeight, easedLevel * maxBarHeight);
            const x = i * (barWidth + gap);
            const y = centerY - (barHeight / 2);

            context.globalAlpha = data ? 0.36 + (level * 0.64) : 0.25;
            context.fillRect(x, y, barWidth, barHeight);
        }

        context.globalAlpha = 1;
    },

    /**
     * Marks message as seen
     */
    initMarkAsSeen:function(){
        $( ".messageBoxInput" ).on('click', function() {
            messenger.markAsSeen();
        });
    },

    markAsSeen: function(){
        if(!messenger.state.activeConversationUserID){
            return false;
        }

        $.ajax({
            type: 'POST',
            url: app.baseUrl + '/my/messenger/markSeen',
            data: {userID:messenger.state.activeConversationUserID},
            dataType: 'json',
            success: function (result) {
                messenger.markConversationAsRead(messenger.state.activeConversationUserID,'read');
                messenger.updateUnreadMessagesCount(parseInt($('#unseenMessages').html()) - result.data.count);
                incrementNotificationsCount('.menu-notification-badge.chat-menu-count', (-parseInt(result.data.count)));
                messenger.updateContactSeenState(messenger.state.activeConversationUserID);
            }
        });
    },

    initMessageActionsToggle: function(){
        $(document).off('click.messengerActionsToggle', '.message-main-row');
        $(document).on('click.messengerActionsToggle', '.message-main-row', function (e) {
            if(window.matchMedia('(min-width: 992px)').matches || $(e.target).closest('.message-action-button').length){
                return;
            }

            const messageBox = $(this).closest('.message-box');
            $('.message-box.actions-visible').not(messageBox).removeClass('actions-visible');
            messageBox.toggleClass('actions-visible');
        });

        $(document).off('click.messengerActionsDismiss');
        $(document).on('click.messengerActionsDismiss', function (e) {
            if($(e.target).closest('.message-box').length){
                return;
            }

            $('.message-box.actions-visible').removeClass('actions-visible');
        });
    },

    /**
     * Checks if user already has a conversation with certain user
     * @param contactID
     * @returns {boolean}
     */
    isExistingContact: function(contactID){
        let isNewContact = false;
        $.map(messenger.state.contacts,function (contact) {
            if(contactID === contact.contactID){
                isNewContact = true;
            }
        });
        return isNewContact;
    },

    /**
     * Reloads conversation list
     */
    reloadContactsList: function () {
        if(messenger.state.contacts.length === 0 && messenger.state.contactPagination.loading){
            return;
        }

        let contactsHtml = '';
        $.each( messenger.state.contacts, function( key, value ) {
            contactsHtml += contactElement(value);
        });

        if(messenger.state.contacts.length > 0){
            $('.conversations-list').html(contactsHtml);
        }
        else if(messenger.state.contactSearchQuery.length){
            $('.conversations-list').html(noContactsSearchLabel());
        }
        else{
            $('.conversations-list').html(noContactsLabel());
        }

        $('.contact-'+messenger.state.activeConversationUserID).addClass('contact-active');
        messenger.toggleContactsPaginationControl();
        messenger.scheduleContactsViewportFill();
        initTooltips();
    },

    toggleContactsPaginationControl: function(){
        messenger.toggleContactsSpinner(false);
    },

    scheduleContactsViewportFill: function(){
        window.clearTimeout(messenger.state.contactPagination.fillTimer);
        messenger.state.contactPagination.fillTimer = window.setTimeout(function () {
            messenger.fillContactsViewport();
        }, 80);
    },

    fillContactsViewport: function(){
        const contactsList = $('.conversations-list')[0];
        if(
            !contactsList ||
            !messenger.state.contactPagination.hasMore ||
            messenger.state.contactPagination.loading ||
            !$(contactsList).is(':visible') ||
            contactsList.clientHeight <= 0
        ){
            return;
        }

        const hasScrollRoom = contactsList.scrollHeight > contactsList.clientHeight + 120;
        if(!hasScrollRoom){
            messenger.fetchContacts({append: true, silent: true});
        }
    },

    toggleConversationHistoryControl: function(){
        const shouldShow = !messenger.state.newConversationMode && !messenger.state.conversationSearchQuery.length && messenger.state.conversationPagination.hasMore && messenger.state.conversation.length > 0;
        $('.conversation-history-control').toggleClass('d-none', !shouldShow);
        if(!shouldShow){
            messenger.toggleMessagesSpinner(false);
        }
    },

    resolveActiveConversationContact: function(){
        if(messenger.state.activeConversationUser){
            return messenger.state.activeConversationUser;
        }

        if(typeof messenger.state.conversation[0] === 'undefined'){
            return null;
        }

        const lastMessage = messenger.state.conversation[messenger.state.conversation.length - 1];
        const isSender = parseInt(lastMessage.sender_id) === parseInt(user.user_id);
        const fallbackContact = isSender ? lastMessage.receiver : lastMessage.sender;

        if(!fallbackContact){
            return null;
        }

        return {
            id: fallbackContact.id,
            username: fallbackContact.username,
            avatar: fallbackContact.avatar || messengerVars.defaultAvatarPath,
            name: fallbackContact.name,
            profileUrl: fallbackContact.profileUrl,
            canEarnMoney: typeof fallbackContact.canEarnMoney === 'undefined' ? true : fallbackContact.canEarnMoney,
            verified: !!fallbackContact.verified,
            staff_badge: fallbackContact.staff_badge || null,
        };
    },

    /**
     * Reloads conversation header
     */
    reloadConversationHeader: function(){
        if(messenger.state.newConversationMode){
            $('.conversation-header').addClass('d-none');
            $('.conversation-header-loading-box').addClass('d-none');
            return;
        }

        const activeContact = messenger.resolveActiveConversationContact();
        if(!activeContact){
            return;
        }

        $('.conversation-header').removeClass('d-none');
        $('.conversation-header-loading-box').addClass('d-none');
        const avatar = messengerSafeUrl(activeContact.avatar, messengerVars.defaultAvatarPath);
        $('.conversation-header-avatar')
            .attr('src', avatar)
            .off('error.messengerAvatar')
            .on('error.messengerAvatar', function () {
                $(this).attr('src', messengerVars.defaultAvatarPath);
            });
        $('.conversation-header-user').text(activeContact.name || '');
        $('.conversation-header-verified-badge').html(verifiedBadgeElement(activeContact.verified) + staffBadgeElement(activeContact.staff_badge));
        $('.conversation-header-username').text('@'+(activeContact.username || ''));
        $('.conversation-profile-link').attr('href', messengerSafeUrl(activeContact.profileUrl, '#'));

        $('.details-holder .unfollow-btn').unbind('click');
        $('.details-holder .block-btn').unbind('click');
        $('.details-holder .report-btn').unbind('click');

        if(messengerVars.followingContacts.indexOf(activeContact.id) >= 0){
            $('.unfollow-btn').html(trans('Unfollow'));
            $('.details-holder .unfollow-btn').on('click',function () {
                Lists.showListManagementConfirmation('unfollow', activeContact.id);
            });
        }
        else{
            $('.unfollow-btn').html(trans('Follow'));
            $('.unfollow-btn').on('click',function () {
                Lists.updateListMember(user.lists.following,activeContact.id,'add', true);
                window.location.reload();
            });
        }

        $('.details-holder .block-btn').on('click',function () {
            Lists.showListManagementConfirmation('block', activeContact.id);
        });
        $('.details-holder .report-btn').on('click',function () {
            Lists.showReportBox(activeContact.id,null);
        });

        if(activeContact.canEarnMoney === false) {
            $('.tip-btn').addClass('hidden');
        } else {
            $('.tip-btn').removeClass('hidden');
            $('.tip-btn').each(function () {
                $(this)
                    .removeData(['username','name','avatar','recipientId'])
                    .data({
                        username: activeContact.username,
                        name: activeContact.name,
                        avatar: avatar,
                        recipientId: activeContact.id
                    })
                    .attr({
                        'data-username': activeContact.username,
                        'data-name': activeContact.name,
                        'data-avatar': avatar,
                        'data-recipient-id': activeContact.id
                    });
            });
        }
    },

    /**
     * Reloads conversation
     */
    reloadConversation: function (options) {
        options = $.extend({
            scrollToBottom: true,
            preserveScroll: null,
        }, options || {});

        if(messenger.state.newConversationMode){
            messenger.renderNewConversationPlaceholder();
            return;
        }

        let conversationHtml = '';
        let currentDateKey = null;
        $.each( messenger.state.conversation, function( key, value ) {
            const messageDateKey = getMessageDateKey(value);
            if(messageDateKey && messageDateKey !== currentDateKey){
                conversationHtml += messageDateDividerElement(value);
                currentDateKey = messageDateKey;
            }
            conversationHtml += messageElement(value);
        });

        $('.conversation-content').html(conversationHtml || (messenger.state.conversationSearchQuery.length ? noMessageSearchResultsLabel() : noMessagesLabel()));
        if(typeof GifMedia !== 'undefined'){
            GifMedia.hydrate($('.conversation-content')[0]);
        }

        const content = $('.conversation-content')[0];
        let urlParams = new URLSearchParams(window.location.search);

        if(content && options.preserveScroll) {
            content.scrollTop = content.scrollHeight - options.preserveScroll.height + options.preserveScroll.top;
        }
        else if(urlParams.has('token') && !messenger.state.redirectedToMessage) {
            let token = '#m-'.concat(urlParams.get('token'));
            if($('.conversation-content .message-box').length && $('.conversation-content').find(token).length){
                let offset = $('.conversation-content').find(token).offset().top - $('.conversation-content').offset().top + $('.conversation-content').scrollTop();
                $(".conversation-content").animate({scrollTop: offset}, 'slow');
            }

            const primaryRgb = getComputedStyle(document.documentElement)
                .getPropertyValue('--theme-primary-rgb')
                .trim() || '203,12,159';
            $('.conversation-content').find(token).animate({
                backgroundColor: `rgba(${primaryRgb},.2)`,
            }, 1000).delay(2000).queue(function() {
                $('.conversation-content').find(token).animate({
                    backgroundColor: "rgba(0,0,0,0)",
                }, 1000).dequeue();
            });

            messenger.state.redirectedToMessage = true;
        }
        else if(options.scrollToBottom !== false && $('.conversation-content .message-box').length){
            messenger.scrollConversationToBottom();
        }

        $('.conversation-loading-box').addClass('d-none');
        messenger.toggleConversationHistoryControl();
        messenger.initMessengerGalleries();
    },

    /**
     * Method used for auto adjusting textarea message height on resize
     * @param el
     */
    textAreaAdjust: function(el) {
        el.style.height = "44px";
        el.style.height = Math.min(el.scrollHeight, 140) + "px";
    },

    /**
     * Resets the send new message text area height
     */
    resetTextAreaHeight: function(){
        $(".messageBoxInput").css('height',44);
    },

    /**
     * Set currently active contact
     * @param userID
     */
    setActiveContact: function (userID, options) {
        options = $.extend({
            markSeen: true,
            focusInput: true,
        }, options || {});

        if(options.focusInput){
            $('.messageBoxInput').focus();
        }

        $('#receiverID').val(userID);
        messenger.state.receiverIDs = [userID];
        $('.contact-box').removeClass('contact-active');
        $('.contact-'+userID).addClass('contact-active');

        if(options.markSeen){
            messenger.markAsSeen();
        }
    },

    /**
     * Clears up the new message field
     */
    clearMessageBox: function(){
        $(".messageBoxInput").val('');
        messenger.setGifSelection(null);
    },

    /**
     * Updates the unread messages count
     * @param val
     * @returns {boolean}
     */
    updateUnreadMessagesCount: function (val) {
        const safeValue = Math.max(0, val);
        $("#unseenMessages").html(safeValue);
        return true;
    },

    /**
     * Marks conversation as being read
     * @param userID
     * @param type
     */
    markConversationAsRead: function (userID, type) {
        $.map(messenger.state.contacts,function (contact,k) {
            if(userID === contact.contactID){
                let newContact = contact;
                newContact.isSeen = type === 'read' ? 1 : 0;
                messenger.state.contacts[k] = newContact;
            }
        });
    },

    /**
     * Appends latest message to the conversation
     * @param contactID
     * @param message
     */
    addLatestMessageToConversation: function (contactID, message) {
        let contactKey = null;
        let newContact = null;
        $.map(messenger.state.contacts,function (contact,k) {
            if(contactID === contact.contactID){
                newContact = contact;
                contactKey = k;
                newContact.lastMessage = message.message;
                newContact.lastMessageHasGif = message.gif ? 1 : 0;
                newContact.lastMessageTipID = message.tip_transaction_id || null;
                newContact.created_at = message.dateAdded || contact.created_at;
                newContact.senderID = message.sender_id;
                newContact.lastMessageSenderID = message.sender_id;
                messenger.state.contacts[k] = newContact;
            }
        });

        let newContactsList = messenger.state.contacts;
        if(contactKey !== null){
            newContactsList.splice(contactKey, 1);
            newContactsList.unshift(newContact);
            messenger.state.contacts = newContactsList;
            messenger.reloadContactsList();
        }
    },

    /**
     * Globally instantiates all message attachments and groups them into individual galleries
     */
    initMessengerGalleries: function(){
        $('.message-box').each(function (index, item) {
            if($(item).find('.attachments-holder').children().length > 0){
                mswpScanPage($(item),'mswp', {
                    history: false,
                });
            }
        });
    },

    /**
     * Replaces message's newlines with html break lines
     * @param text
     * @returns {*}
     */
    parseMessage: function(text){
        const safeHtml = messengerSanitizeText(text).replace(/\n/g, '<br/>');

        if(app.allow_hyperlinks) {
            return Autolinker.link(safeHtml, {
                urls: {
                    schemeMatches: true,
                    wwwMatches: true,
                    tldMatches: false
                },
                email: false,
                phone: false,
                mention: false,
                hashtag: false,
                sanitizeHtml: false,
                className: '',
                truncate: {length: 64, location: 'middle'},
                replaceFn: function (match) {
                    var tag = match.buildTag();
                    tag.setAttr('rel', 'nofollow noopener noreferrer');
                    return tag;
                }
            });
        }
        return safeHtml;
    },

    /**
     * Loads UI elements for loaded messenger
     */
    hideEmptyChatElements: function () {
        $('.conversation-writeup, #messenger-gif-composer').removeClass('hidden');
        $('.no-contacts').addClass('hidden');
    },

    /**
     * Instantiates & applies selectize on the new conversation modal
     */
    initSelectizeUserList: function(){
        if (typeof Selectize === 'undefined') return;

        messenger.selectizeInstance = $('#select-repo').selectize({
            valueField: 'id',
            labelField: 'name',
            searchField: 'name',
            options: messengerVars.availableContacts,
            create: false,
            maxOptions: messenger.maxNewConversationContacts,
            dropdownParent: 'body',

            render: {
                option: function (item, escape) {
                    return '<div class="d-flex align-items-center overflow-hidden w-100">' +
                        '<img class="searchAvatar flex-shrink-0 ms-3 my-1" src="' + escape(item.avatar) + '" alt="">' +
                        '<span class="name text-truncate ms-2">' + escape(item.name) + '</span>' +
                        staffBadgeElement(item.staff_badge) +
                        '</div>';
                },
                item: function (item, escape) {
                    return '<div class="d-inline-flex align-items-center overflow-hidden mw-100">' +
                        '<img class="searchAvatar flex-shrink-0 ms-1" src="' + escape(item.avatar) + '" alt="">' +
                        '<span class="name text-truncate ms-2">' + escape(item.name) + '</span>' +
                        staffBadgeElement(item.staff_badge) +
                        '</div>';
                }
            },

            onChange: function (value) {
                var arr = Array.isArray(value) ? value : (value ? [value] : []);
                messenger.state.receiverIDs = arr.map(function (x) { return parseInt(x, 10); });
            }
        });
    },

    showSetPriceDialog: function () {
        $('#message-set-price-dialog').modal('show');
    },

    clearMessagePrice: function(){
        messenger.state.messagePrice = 5;
        messenger.state.isPaidMessage = false;
        $('#message-price').val(5);
        $('.message-price-lock').removeClass('d-none');
        $('.message-price-close').addClass('d-none');
        $('#message-set-price-dialog').modal('hide');
    },

    saveMessagePrice: function(){
        messenger.state.isPaidMessage = true;
        messenger.state.messagePrice = $('#message-price').val();
        if(!passesMinMaxPPVMessageLimits(messenger.state.messagePrice)){
            $('#message-price').addClass('is-invalid');
            return false;
        }
        $('.message-price-lock').addClass('d-none');
        $('.message-price-close').removeClass('d-none');
        $('#message-set-price-dialog').modal('hide');
        $('#message-price').removeClass('is-invalid');
        messenger.scheduleActiveMessageDraftSave();
    },

    /**
     * Parses messenger's attachment previews
     * @param file
     * @returns {string}
     */
    parseMessageAttachment: function(file){
        file = file || {};
        if(file.type === 'file' || file.attachmentType === 'file') {
            const name = messengerString(file.original_name) || trans('File');
            const extension = messengerString(file.extension).toUpperCase();
            const downloadUrl = messengerSafeUrl(file.download_url, '');
            const size = messengerString(file.size_label);
            return '<div class="messenger-file-attachment mt-2 w-100">'
                + '<div class="file-attachment-card d-flex align-items-center gap-3 border rounded p-3 w-100">'
                + '<ion-icon name="' + (extension === 'ZIP' ? 'archive-outline' : 'document-text-outline') + '" class="text-primary fs-4 flex-shrink-0" aria-hidden="true"></ion-icon>'
                + '<div class="file-attachment-details"><div class="file-attachment-name text-break fw-semibold" title="' + messengerEscapeAttr(name) + '" aria-label="' + messengerEscapeAttr(name) + '">' + messengerSanitizeText(name) + '</div>'
                + '<div class="small text-body-secondary">' + messengerSanitizeText(extension + (size ? ' · ' + size : '')) + '</div></div>'
                + (downloadUrl ? '<a class="btn btn-outline-primary btn-sm mb-0 flex-shrink-0" href="' + messengerEscapeAttr(downloadUrl) + '" aria-label="' + messengerEscapeAttr(trans('Download :filename').replace(':filename', name)) + '">' + messengerSanitizeText(trans('Download')) + '</a>' : '')
                + '</div></div>';
        }
        let attachmentsHtml = '';
        const filePath = messengerEscapeAttr(messengerSafeUrl(file.path, '#'));
        const thumbnail = messengerEscapeAttr(messengerSafeUrl(file.thumbnail, file.path || '#'));
        const fileType = messengerString(file.type).toLowerCase();
        const pathWithoutQuery = messengerString(file.path).split('?')[0].split('#')[0];
        const filenameWithoutQuery = messengerString(file.filename).split('?')[0].split('#')[0];
        const pathExtensionMatch = pathWithoutQuery.match(/\.([a-z0-9]+)$/i);
        const filenameExtensionMatch = filenameWithoutQuery.match(/\.([a-z0-9]+)$/i);
        const pathExtension = pathExtensionMatch ? pathExtensionMatch[1].toLowerCase() : '';
        const filenameExtension = filenameExtensionMatch ? filenameExtensionMatch[1].toLowerCase() : '';
        const storedPath = (filenameWithoutQuery || pathWithoutQuery).toLowerCase();
        const audioExtensions = ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'webm'];
        const videoExtensions = ['avi', 'mp4', 'wmw', 'mpeg', 'm4v', 'moov', 'mov'];
        const isStoredInAudioDirectory = storedPath.indexOf('/audio/') !== -1 || storedPath.indexOf('messenger/audio/') !== -1;
        const isAudio = fileType === 'audio' ||
            isStoredInAudioDirectory ||
            audioExtensions.indexOf(fileType) !== -1 ||
            audioExtensions.indexOf(pathExtension) !== -1 ||
            audioExtensions.indexOf(filenameExtension) !== -1;
        const isVideo = !isAudio && (
            videoExtensions.indexOf(fileType) !== -1 ||
            videoExtensions.indexOf(pathExtension) !== -1 ||
            videoExtensions.indexOf(filenameExtension) !== -1
        );

        if(isVideo){
            attachmentsHtml = `
                <a href="${filePath}" rel="mswp" title="" class="me-2 mt-2 no-long-press">
                    <div class="video-wrapper">
                     <video class="video-preview" src="${filePath}" width="150" height="150" controls controlsList="nodownload" autoplay muted playsinline></video>
                    </div>
                 </a>`;
        }
        else if(isAudio){
            attachmentsHtml = `
                <div class="messenger-audio-attachment mt-2 d-flex align-items-center no-long-press">
                    <div class="messenger-audio-wrapper">
                         <audio class="messenger-audio-preview" src="${filePath}" controls controlsList="nodownload" preload="metadata"></audio>
                    </div>
                 </div>`;
        }
        else if(['png', 'jpg', 'jpeg'].indexOf(fileType) !== -1 || ['png', 'jpg', 'jpeg'].indexOf(pathExtension) !== -1 || ['png', 'jpg', 'jpeg'].indexOf(filenameExtension) !== -1){
            attachmentsHtml = `
                    <a href="${filePath}" rel="mswp" title="" class="no-long-press">
                        <img src="${thumbnail}" class="me-2 mt-2">
                    </a>`;
        }
        else{
            attachmentsHtml = `<img src="${thumbnail}" class="me-2 mt-2">`;
        }
        return attachmentsHtml;
    },

    /**
     * Shows up message delete confirmation dialog
     * @param messageID
     */
    showMessageDeleteDialog: function(messageID){
        showDialog('message-delete-dialog');
        messenger.state.activeMessageID = messageID;
    },

    /**
     * Removes own comments
     */
    deleteMessage: function () {
        $.ajax({
            type: 'DELETE',
            dataType: 'json',
            url: app.baseUrl + '/my/messenger/delete/' + messenger.state.activeMessageID,
            success: function (result) {
                let element = $('*[data-messageid="'+messenger.state.activeMessageID+'"]');
                element.remove();
                hideDialog('message-delete-dialog');
                launchToast('success',trans('Success'),trans('Message removed'));
                if(result.isLastMessage === true){
                    messenger.fetchContacts(function () {
                        if(messenger.state.contacts.length >= 1){
                            messenger.state.activeConversationUserID = messenger.state.contacts[0].contactID;
                            messenger.fetchConversation(messenger.state.activeConversationUserID, {replaceHistory: true});
                        }
                        else{
                            messenger.fetchContacts();
                            messenger.clearConversationHistory();
                            if(typeof MessengerPins !== 'undefined') MessengerPins.beginLoad(0);
                            $('.conversation-content').html(noConversationLabel());
                            $('.conversation-writeup, #messenger-gif-composer').addClass('hidden');
                            $('.conversation-header').addClass('d-none');
                            messenger.setMobilePane('contacts');
                        }

                    });
                }
                else{
                    messenger.fetchConversation(messenger.state.activeConversationUserID);
                }
            },
            error: function (result) {
                hideDialog('message-delete-dialog');
                launchToast('danger',trans('Error'),result.responseJSON.message);
            }
        });
    },

    /**
     * Inits the new conversation UI events
     */
    initNewConversationUI: function(){
        $('.new-conversation-toggle').off('click.newConversation').on('click.newConversation', function () {
            if(messenger.state.newConversationMode){
                messenger.closeNewConversationUI();
            }
            else{
                messenger.openNewConversationUI();
            }
        });

        $('.new-conversation-close').off('click.newConversation').on('click.newConversation', function () {
            messenger.closeNewConversationUI();
        });

        $('.new-conversation-toggle-all').off('click.newConversation').on('click.newConversation', function () {
            messenger.toggleAllContacts();
        });
    },

    /**
     * Closes the new conversation UI
     * @returns {boolean}
     */
    closeNewConversationUI: function () {
        const wasNewConversationMode = messenger.state.newConversationMode;
        $('.new-conversation-toggle').removeClass('active');
        $('.messenger-shell').removeClass('new-conversation-active');
        $('.new-conversation-header').addClass('d-none');
        $('.conversation-header-loading-box').addClass('d-none');
        if(messenger.selectizeInstance !== null){
            messenger.selectizeInstance[0].selectize.clear();
        }

        if(!wasNewConversationMode){
            if(messenger.state.activeConversationUserID){
                $('.conversation-header').removeClass('d-none');
            }
            messenger.toggleConversationHistoryControl();
            return true;
        }

        messenger.state.newConversationMode = false;

        if(messenger.state.contacts.length === 0 && messengerVars.lastContactID === 0){
            $('.conversation-content').html(noConversationLabel());
            $('.conversation-writeup, #messenger-gif-composer').addClass('hidden');
            $('.conversation-header').addClass('d-none');
            messenger.setMobilePane('contacts');
            return true;
        }

        if(messenger.state.activeConversationUserID){
            $('.conversation-writeup, #messenger-gif-composer').removeClass('hidden');
            $('.conversation-header').removeClass('d-none');

            const activeContact = messenger.resolveActiveConversationContact();
            if(activeContact){
                messenger.updateConversationHistory(activeContact, {replace: true});
            }

            if(messenger.state.conversation.length){
                messenger.reloadConversation({scrollToBottom: false});
                messenger.reloadConversationHeader();
                messenger.restoreActiveMessageDraft();
                messenger.toggleConversationHistoryControl();
                messenger.setMobilePane('conversation');
                if(typeof MessengerPins !== 'undefined'){
                    MessengerPins.render();
                    MessengerPins.refresh(messenger.state.activeConversationUserID);
                }
            }
            else{
                messenger.fetchConversation(messenger.state.activeConversationUserID, {
                    scrollToBottom: false,
                    showLoader: true,
                    replaceHistory: true,
                });
            }
            return true;
        }

        if(!messenger.state.activeConversationUserID){
            $('.conversation-content').html(noConversationLabel());
            $('.conversation-writeup, #messenger-gif-composer').addClass('hidden');
            $('.conversation-header').addClass('d-none');
            messenger.setMobilePane('contacts');
        }
        return true;
    },

    /**
     * Toggles all contacts in new create message dialog | mass message
     */
    toggleAllContacts: function(){
        if(messenger.state.newConversationSelectAllToggle === false){
            var el = messenger.selectizeInstance[0].selectize;
            var optKeys = Object.keys(el.options);
            let i = 0;
            optKeys.forEach(function (key) {
                if(i > messenger.maxNewConversationContacts){return false;}
                el.addItem(key);
                i++;
            });
            messenger.state.newConversationSelectAllToggle = true;
        }
        else{
            messenger.selectizeInstance[0].selectize.clear();
            messenger.state.newConversationSelectAllToggle = false;
        }
    },

    /**
     * Opens up the new conversation dialog
     * @returns {boolean}
     */
    openNewConversationUI: function () {
        if(messengerVars.availableContacts.length === 0) {
            return false;
        }
        messenger.persistActiveMessageDraft(true);
        messenger.state.newConversationMode = true;
        if(typeof MessengerPins !== 'undefined') MessengerPins.hide();
        messenger.clearMessageBox();
        messenger.clearMessagePrice();
        messenger.clearFileUploadsState();
        messenger.closeConversationMessageSearch(false);
        messenger.closeMessageTemplatesUI(false);
        messenger.clearConversationHistory();
        if(messenger.state.conversationPagination.loading && messenger.state.conversationPagination.request){
            const conversationRequest = messenger.state.conversationPagination.request;
            messenger.state.conversationPagination.loading = false;
            messenger.state.conversationPagination.request = null;
            conversationRequest.abort();
        }
        messenger.hideEmptyChatElements();
        $('.messenger-shell').addClass('new-conversation-active');
        $('.new-conversation-toggle').addClass('active');
        $('.conversation-header').addClass('d-none');
        $('.conversation-header-loading-box').addClass('d-none');
        $('.new-conversation-header').removeClass('d-none');
        messenger.renderNewConversationPlaceholder();
        messenger.setMobilePane('conversation');
        return true;
    },

    /**
     * Disabling right for posts ( if site wise setting is set to do it )
     */
    disableMesagesRightClick: function () {
        $(".attachments-holder, .messenger-locked-card, .lockedPreviewWrapper, .messenger-locked-preview-media, .messenger-locked-action, .messenger-locked-counts, .messenger-locked-unlock-btn").unbind('contextmenu');
        $(".attachments-holder, .messenger-locked-card, .lockedPreviewWrapper, .messenger-locked-preview-media, .messenger-locked-action, .messenger-locked-counts, .messenger-locked-unlock-btn").on("contextmenu",function(){
            return false;
        });
        $(".post-media, .pswp__item").unbind('contextmenu');
        $(".post-media, .pswp__item").on("contextmenu",function(){
            return false;
        });
        bindNoLongPressEvents();
    },

};
