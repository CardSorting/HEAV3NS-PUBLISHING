/* global app, messenger, trans, messengerSanitizeText, messengerEscapeAttr, launchToast, showDialog, hideDialog */
"use strict";

// Shared pins contain previews only. Opening a message uses the normal authorized conversation endpoint.
var MessengerPins = {
    pins: [],
    contactId: null,
    request: null,
    revision: 0,
    pending: false,
    historyView: false,
    refreshNeeded: false,

    init: function(){
        $(document).on('click', '.message-pin-toggle', function(){
            MessengerPins.setPin(Number($(this).attr('data-pin-messageid')), $(this).attr('data-pinned') === '1');
        }).on('click', '.messenger-pin-preview, .messenger-pin-jump', function(){
            const id = Number($(this).attr('data-pin-messageid'));
            const dialog = $('#pinned-messages-dialog');
            if(dialog.hasClass('show')){
                dialog.one('hidden.bs.modal', function(){ MessengerPins.jump(id); });
                hideDialog('pinned-messages-dialog');
            }
            else MessengerPins.jump(id);
        }).on('click', '.messenger-pins-view-all', function(){
            showDialog('pinned-messages-dialog');
        }).on('click', '.messenger-pins-latest', function(){
            messenger.persistActiveMessageDraft(true);
            messenger.closeConversationMessageSearch(false);
            messenger.fetchConversation(MessengerPins.contactId);
        });
    },

    beginLoad: function(contactId){
        this.revision++;
        if(this.request) this.request.abort();
        this.request = null;
        if(Number(contactId) !== Number(this.contactId)){
            this.pins = [];
            this.historyView = false;
            this.refreshNeeded = false;
            hideDialog('pinned-messages-dialog');
        }
        this.contactId = Number(contactId);
        this.hide();
    },

    hide: function(){
        $('.messenger-pins-bar, .messenger-pins-history').addClass('d-none');
    },

    loaded: function(data, options){
        this.contactId = Number(messenger.state.activeConversationUserID);
        if(options.mode === 'replace') this.historyView = !!data.hasNewer;
        if(Array.isArray(data.pins)) this.apply(data.pins, false);
        this.render();
        if(options.aroundId) this.highlight(options.aroundId);
        if(this.refreshNeeded){
            this.refreshNeeded = false;
            this.refresh(this.contactId);
        }
    },

    refresh: function(contactId){
        if(Number(contactId) !== Number(this.contactId) || messenger.state.messageTemplateMode || messenger.state.newConversationMode) return;
        if(messenger.state.conversationPagination.loading){
            this.refreshNeeded = true;
            return;
        }
        const revision = ++this.revision;
        if(this.request) this.request.abort();
        this.request = $.ajax({
            type: 'GET', url: app.baseUrl + '/my/messenger/pins/' + encodeURIComponent(contactId), dataType: 'json',
            success: function(result){
                if(revision !== MessengerPins.revision || Number(messenger.state.activeConversationUserID) !== Number(contactId)) return;
                MessengerPins.apply(result.pins || [], true);
            },
            error: function(result, status){
                if(status === 'abort' || revision !== MessengerPins.revision) return;
                launchToast('danger', trans('Error'), trans('Could not load pinned messages.'));
            }
        });
    },

    apply: function(pins, redraw){
        this.pins = pins;
        let changed = false;
        messenger.state.conversation.forEach(function(message){
            const pin = pins.find(function(item){ return Number(item.id) === Number(message.id); });
            const pinnedAt = pin ? pin.pinned_at : null;
            if(!!message.pinned_at !== !!pinnedAt) changed = true;
            message.pinned_at = pinnedAt;
            message.pinned_by = pin ? pin.pinned_by : null;
        });
        if(changed && redraw){
            const content = $('.conversation-content')[0];
            messenger.reloadConversation({scrollToBottom: false, preserveScroll: content ? {height: content.scrollHeight, top: content.scrollTop} : null});
        }
        this.render();
    },

    render: function(){
        const hidden = messenger.state.messageTemplateMode || messenger.state.newConversationMode;
        $('.messenger-pins-bar').toggleClass('d-none', hidden || !this.pins.length);
        $('.messenger-pins-history').toggleClass('d-none', hidden || !this.historyView);
        $('.messenger-pins-latest').text(trans('Return to latest'));
        const first = this.pins[0];
        $('.messenger-pin-preview').text(first ? first.preview : '').attr('data-pin-messageid', first ? first.id : '');
        $('.messenger-pins-count').text('(' + this.pins.length + ')');
        $('.messenger-pins-list').html(this.pins.map(function(pin){
            return `<div class="list-group-item d-flex align-items-center gap-3 p-3">
                <button type="button" class="messenger-pin-jump border-0 bg-transparent text-start text-body small flex-fill overflow-hidden text-break p-0" data-pin-messageid="${messengerEscapeAttr(pin.id)}">${messengerSanitizeText(pin.preview)}</button>
                <button type="button" class="message-pin-toggle message-action-button border-0 text-muted flex-shrink-0 d-flex align-items-center justify-content-center p-0" data-pin-messageid="${messengerEscapeAttr(pin.id)}" data-pinned="0" title="${messengerEscapeAttr(trans('Unpin message'))}" aria-label="${messengerEscapeAttr(trans('Unpin message'))}"><ion-icon name="close-outline" aria-hidden="true"></ion-icon></button>
            </div>`;
        }).join(''));
        $('.message-pin-toggle').prop('disabled', this.pending);
        if(!this.pins.length) hideDialog('pinned-messages-dialog');
    },

    setPin: function(messageId, pinned){
        if(this.pending || !this.contactId) return;
        const contactId = this.contactId;
        this.pending = true;
        $('.message-pin-toggle').prop('disabled', true);
        $.ajax({
            type: 'POST', url: app.baseUrl + '/my/messenger/pin/' + encodeURIComponent(messageId),
            data: {pinned: pinned ? 1 : 0}, dataType: 'json',
            success: function(){
                launchToast('success', trans('Success'), trans(pinned ? 'Message pinned.' : 'Message unpinned.'));
                MessengerPins.refresh(contactId);
            },
            error: function(result){
                const response = result.responseJSON || {};
                const errors = response.errors || {};
                launchToast('danger', trans('Error'), (errors.pinned || [])[0] || response.message || trans('Could not update pinned messages.'));
            },
            complete: function(){
                MessengerPins.pending = false;
                $('.message-pin-toggle').prop('disabled', false);
            }
        });
    },

    jump: function(messageId){
        if(!this.pins.some(function(pin){ return Number(pin.id) === messageId; })) return;
        if(document.getElementById('m-' + messageId)){
            this.highlight(messageId);
            return;
        }
        messenger.persistActiveMessageDraft(true);
        messenger.closeConversationMessageSearch(false);
        messenger.fetchConversation(this.contactId, {aroundId: messageId, scrollToBottom: false});
    },

    highlight: function(messageId){
        const element = document.getElementById('m-' + messageId);
        const content = $('.conversation-content')[0];
        if(!element || !content) return;
        content.scrollTop += element.getBoundingClientRect().top - content.getBoundingClientRect().top - content.clientHeight / 3;
        element.setAttribute('tabindex', '-1');
        element.focus({preventScroll: true});
        element.classList.add('bg-body-tertiary', 'rounded-3');
        setTimeout(function(){ element.classList.remove('bg-body-tertiary', 'rounded-3'); }, 1400);
    },

    failed: function(options){
        // Keep a way back if an old pin was deleted or access changed while navigating.
        if(options.aroundId) this.historyView = true;
        this.render();
    },

    deferLiveMessage: function(){
        if(!this.historyView) return false;
        $('.messenger-pins-latest').text(trans('New messages — return to latest'));
        return true;
    }
};

$(function(){ MessengerPins.init(); });
