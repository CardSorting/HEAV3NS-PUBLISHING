/**
 *
 * Messages Elements
 *
 */
"use strict";
/* global app, user, messengerVars, trans, filterXSS, messenger, getWebsiteFormattedAmount, renderUserStaffBadge, GifMedia */

function messengerString(value){
    return String(value === null || typeof value === 'undefined' ? '' : value);
}

// eslint-disable-next-line no-unused-vars
function messengerSanitizeText(value){
    return filterXSS(messengerString(value), {
        whiteList: {},
    });
}

// eslint-disable-next-line no-unused-vars
function messengerEscapeAttr(value){
    return filterXSS.escapeAttrValue(messengerString(value));
}

// eslint-disable-next-line no-unused-vars
function messengerSafeUrl(value, fallback){
    const safeFallback = fallback || '';
    const url = messengerString(value).trim();
    if(!url){
        return safeFallback;
    }

    // eslint-disable-next-line no-control-regex
    const normalized = url.replace(/[\u0000-\u001F\u007F\s]+/g, '');
    if(/^[a-zA-Z][a-zA-Z\d+\-.]*:/.test(normalized) && !/^https?:/i.test(normalized) && !/^blob:/i.test(normalized)){
        return safeFallback;
    }

    return url;
}

/**
 * Messenger contact component
 * @param contact
 * @returns {string}
 */
// eslint-disable-next-line no-unused-vars
function verifiedBadgeElement(isVerified){
    if(!isVerified || typeof messengerVars === 'undefined' || !messengerVars.verifiedBadgeHtml){
        return '';
    }

    return messengerVars.verifiedBadgeHtml;
}

// eslint-disable-next-line no-unused-vars
function staffBadgeElement(badge){
    return typeof renderUserStaffBadge === 'function'
        ? renderUserStaffBadge(badge, false, messengerVars.staffBadgeHtml)
        : '';
}

// eslint-disable-next-line no-unused-vars
function contactElement(contact){
    const isOwnReceiver = parseInt(contact.receiverID) === parseInt(user.user_id);
    const avatar = isOwnReceiver ? contact.senderAvatar : contact.receiverAvatar;
    const defaultAvatar = typeof messengerVars !== 'undefined' && messengerVars.defaultAvatarPath ? messengerVars.defaultAvatarPath : '';
    const safeAvatar = messengerSafeUrl(avatar, defaultAvatar);
    const name = isOwnReceiver ? contact.senderName : contact.receiverName;
    const safeName = name || '';
    const isVerified = isOwnReceiver ? contact.senderVerified : contact.receiverVerified;
    const staffBadge = isOwnReceiver ? contact.senderStaffBadge : contact.receiverStaffBadge;
    const lastMessage = contact.lastMessage === null || typeof contact.lastMessage === 'undefined' ? '' : String(contact.lastMessage).trim();
    const currentUserID = Number(user.user_id);
    const lastMessageSenderID = Number(contact.lastMessageSenderID || contact.senderID);
    const hasKnownSender = Number.isFinite(lastMessageSenderID);
    const sentByMe = hasKnownSender && lastMessageSenderID === currentUserID;
    const contactID = parseInt(contact.contactID, 10) || 0;
    const draft = typeof messenger !== 'undefined' && messenger.getDraftForContactId ? messenger.getDraftForContactId(contactID) : null;
    const draftMessage = draft && draft.message ? String(draft.message).trim() : '';
    const hasDraft = !!draft;
    const draftPreview = draftMessage.length ? draftMessage : trans(draft && draft.gif ? 'GIF' : 'Attachment');
    const preview = Number(contact.lastMessageTipID)
        ? messengerSanitizeText(trans('Tip'))
        : (lastMessage.length ? messengerSanitizeText(lastMessage) : messengerSanitizeText(trans(Number(contact.lastMessageHasGif) ? 'GIF' : 'Attachment')));
    const previewHtml = hasDraft
        ? '<span class="text-primary">' + messengerSanitizeText(trans('Draft')) + ':</span> ' + messengerSanitizeText(draftPreview)
        : (sentByMe && lastMessage.length ? `${messengerSanitizeText(trans('You'))}: ` : '') + preview;
    const isUnread = !sentByMe && parseInt(contact.isSeen) === 0;
    return `
      <a href="javascript:void(0)" class="d-flex align-items-center px-3 py-2 text-decoration-none contact-box contact-${contactID} " onclick="messenger.fetchConversation(${contactID}, {updateHistory: true})">
        <img src="${messengerEscapeAttr(safeAvatar)}" class="contact-avatar rounded-circle" alt="${messengerEscapeAttr(safeName)}" onerror="this.onerror=null;this.src=messengerVars.defaultAvatarPath;"/>
        <span class="d-flex flex-column flex-fill overflow-hidden ms-3">
            <span class="d-flex align-items-center justify-content-between w-100">
                <span class="d-flex align-items-center flex-grow-1 overflow-hidden contact-title">
                    <span class="contact-name text-truncate ${isUnread ? 'fw-bold' : ''}">${messengerSanitizeText(safeName)}</span>
                    ${verifiedBadgeElement(isVerified)}
                    ${staffBadgeElement(staffBadge)}
                </span>
                <small class="contact-time flex-shrink-0 text-muted ms-2">${messengerSanitizeText(contact.created_at || '')}</small>
            </span>
            <span class="d-flex align-items-center w-100 text-muted small">
                ${isUnread ? '<span class="contact-unread-indicator"></span>' : ''}
                <span class="text-truncate contact-message ${isUnread ? 'fw-bold' : ''}">
                        ${previewHtml}
                </span>
            </span>
        </span>
      </a>
    `;
}

/**
 * Messenger message component
 * @param message
 * @returns {string}
 */
// eslint-disable-next-line no-unused-vars
function messageElement(message){
    let isSender = false;
    if(parseInt(message.sender_id) === parseInt(user.user_id)){
        isSender = true;
    }

    if (message.tip) {
        return tipMessageElement(message, isSender);
    }

    let attachmentsHtml = '';
    message.attachments.map(function (file) {
        attachmentsHtml += messenger.parseMessageAttachment(file);
    });

    /* Paid message preview */
    if(message.hasUserUnlockedMessage === false && message.price > 0 && !isSender){
        return `
          <div class="col-12 g-0 py-1 message-box px-0 ${isSender ? 'message-box-sender' : 'message-box-receiver'}" data-messageid="${message.id}" id="m-${message.id}">
                <div class="m-0 paid-message-box message-box text-break alert ${isSender ? 'alert-primary text-white' : 'alert-default'}">
                        <div class="col-12 d-flex mb-2 ${isSender ? 'sender d-flex flex-row-reverse pe-0' : 'ps-0'}">
                            ${message.message === null || app.disableTextPreview === true ? '' : messenger.parseMessage(message.message)}
                        </div>
                        <div class="d-flex justify-content-center w-100">
                        ${lockedMessagePreview({'id' : message.id, 'price': message.price, 'lockedPreview': message.lockedPreview},message.sender)}
                        ${messagePinAction(message)}
                        </div>
                    </div>
                </div>
                ${messageTimestampElement(isSender, message)}
          </div>
        `;
    }
    else{
        var storyHtml = '';

        if (message.story_ref && message.story_ref.id) {
            var storyOwnerId = message.story_ref.owner_id ? Number(message.story_ref.owner_id) : null;

            var avatarFallback = null;
            if (storyOwnerId) {
                avatarFallback = (Number(message.sender_id) === storyOwnerId)
                    ? (message.sender && message.sender.avatar ? message.sender.avatar : null)
                    : (message.receiver && message.receiver.avatar ? message.receiver.avatar : null);
            } else {
                // fallback: assume story belongs to the "other" side of the conversation
                avatarFallback = isSender
                    ? (message.receiver && message.receiver.avatar ? message.receiver.avatar : null)
                    : (message.sender && message.sender.avatar ? message.sender.avatar : null);
            }

            storyHtml = storyReplyBubble(isSender, message.story_ref, avatarFallback);
        }
        /* Regular message preview */
        const gifHtml = message.gif && typeof GifMedia !== 'undefined'
            ? GifMedia.renderReference(message.gif, 'messenger')
            : '';
        const hasText = messengerString(message.message).trim().length > 0;
        return `
          <div class="col-12 g-0 py-1 message-box px-0 ${isSender ? 'message-box-sender' : 'message-box-receiver'}" data-messageid="${message.id}" id="m-${message.id}">
            ${storyHtml}
            ${hasText ? messageBubble(isSender, message, !attachmentsHtml.length && !gifHtml) : ''}
            ${gifHtml ? messageGif(isSender, gifHtml, message, !hasText) : ''}
            ${messageAttachments(isSender, attachmentsHtml, message, true)}
          </div>
    `;
    }

}

function tipMessageElement(message, isSender) {
    const label = trans(isSender ? 'You sent a tip of :amount' : 'You received a tip of :amount', {amount: message.tip.amountLabel});
    const refunded = message.tip.status === 'refunded';

    return `
        <div class="col-12 g-0 py-2 message-box message-tip text-center px-0" data-messageid="${messengerEscapeAttr(message.id)}" id="m-${messengerEscapeAttr(message.id)}">
            <div class="d-flex flex-column align-items-center gap-1">
                <div class="d-inline-flex align-items-center gap-2 border rounded-3 px-3 py-2 small bg-body-tertiary text-body text-break">
                    <ion-icon name="gift-outline" class="fs-5 flex-shrink-0" aria-hidden="true"></ion-icon>
                    <span>${messengerSanitizeText(label)}${refunded ? ' <span class="text-body-secondary">(' + messengerSanitizeText(trans('Refunded')) + ')</span>' : ''}</span>
                </div>
                <div class="message-time text-muted">${messagePinMarker(message)}${messengerSanitizeText(getMessageTimeLabel(message))}</div>
            </div>
        </div>
    `;
}

function messageGif(isSender, gifHtml, message, showActions){
    return `
        <div class="col-12 d-flex ${isSender ? 'justify-content-end pe-1' : 'justify-content-start ps-0'}">
            <div class="message-stack d-flex flex-column ${isSender ? 'align-items-end' : 'align-items-start'}">
                <div class="message-main-row d-flex align-items-center ${isSender ? 'sender flex-row-reverse' : ''}">
                    ${gifHtml}
                    ${showActions ? messageActions(isSender, message) : ''}
                </div>
                ${messageTimestampElement(isSender, message)}
            </div>
        </div>
    `;
}

function messageTimestampElement(isSender, message) {
    const timeLabel = getMessageTimeLabel(message);
    if(!timeLabel){
        return '';
    }

    return `
        <div class="message-time text-muted ${isSender ? 'text-end pe-2' : 'text-start ps-0'}">
            ${messagePinMarker(message)}
            ${messengerSanitizeText(timeLabel)}
        </div>
    `;
}

// eslint-disable-next-line no-unused-vars
function messageDateDividerElement(message) {
    const dateLabel = getMessageDateLabel(message);
    if(!dateLabel){
        return '';
    }

    return `
        <div class="message-date-divider d-flex align-items-center text-muted">
            <span class="message-date-divider-line flex-fill"></span>
            <span class="message-date-divider-label px-2">${messengerSanitizeText(dateLabel)}</span>
            <span class="message-date-divider-line flex-fill"></span>
        </div>
    `;
}

// eslint-disable-next-line no-unused-vars
function getMessageDateKey(message) {
    if(message.dateKey){
        return message.dateKey;
    }

    const date = getMessageDate(message);
    if(!date){
        return '';
    }

    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${date.getFullYear()}-${month}-${day}`;
}

function getMessageDateLabel(message) {
    if(message.dateLabel){
        return message.dateLabel;
    }

    const date = getMessageDate(message);
    if(!date){
        return '';
    }

    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);

    if(date.toDateString() === today.toDateString()){
        return trans('Today');
    }

    if(date.toDateString() === yesterday.toDateString()){
        return trans('Yesterday');
    }

    return date.toLocaleDateString(undefined, {month: 'short', day: 'numeric', year: 'numeric'});
}

function getMessageTimeLabel(message) {
    if(message.timeLabel){
        return message.timeLabel;
    }

    const date = getMessageDate(message);
    if(!date){
        return message.dateAdded || '';
    }

    return date.toLocaleTimeString(undefined, {hour: 'numeric', minute: '2-digit'}).toLowerCase();
}

function getMessageDate(message) {
    if(!message.created_at){
        return null;
    }

    const date = new Date(message.created_at);
    return Number.isNaN(date.getTime()) ? null : date;
}

/**
 * Message bubble component
 * @param isSender
 * @param message
 * @returns {string}
 */
function messageBubble(isSender, message, showTimestamp) {
    return `
        <div class="d-flex flex-row">
                <div class="col-12 d-flex ${isSender ? 'justify-content-end pe-1' : 'justify-content-start ps-0'}">
                    <div class="message-stack d-flex flex-column ${isSender ? 'align-items-end' : 'align-items-start'}">
                        <div class="message-main-row d-flex align-items-center ${isSender ? 'sender flex-row-reverse' : ''}">
                        <div class="m-0 message-bubble text-break alert ${isSender ? 'alert-primary text-white' : 'alert-default'}">${message.hasUserUnlockedMessage === false && message.price > 0 && !isSender && app.disableTextPreview === true ? '' : messenger.parseMessage(message.message)}</div>
                        ${messageActions(isSender, message)}
                        </div>
                        ${showTimestamp ? messageTimestampElement(isSender, message) : ''}
                    </div>
                </div>
        </div>
    `;
}

function messageAttachments(isSender, attachmentsHtml, message, showTimestamp){
    if(!attachmentsHtml.length){
        return '';
    }

    const hasAudioAttachments = attachmentsHtml.indexOf('messenger-audio-attachment') !== -1;
    const hasFileAttachments = attachmentsHtml.indexOf('messenger-file-attachment') !== -1;
    const hasVisualAttachments = attachmentsHtml.indexOf('<img') !== -1 || attachmentsHtml.indexOf('<video') !== -1;
    const isAudioOnly = hasAudioAttachments && !hasVisualAttachments;

    return `
             <div class="col-12 d-flex ${isSender ? 'justify-content-end pe-1' : 'justify-content-start ps-0'}">
                <div class="message-stack mw-100 d-flex flex-column ${isSender ? 'align-items-end' : 'align-items-start'}">
                    <div class="message-main-row mw-100 d-flex align-items-center ${isSender ? 'sender flex-row-reverse' : ''}">
                    <div class="attachments-holder row g-0 ${hasAudioAttachments ? 'attachments-holder-has-audio' : ''} ${hasFileAttachments ? 'attachments-holder-has-files' : ''} ${isAudioOnly ? 'attachments-holder-audio-only' : ''} ${isSender ? 'justify-content-end' : ''}">
                        ${attachmentsHtml}
                    </div>
                    ${messageActions(isSender, message)}
                    </div>
                    ${showTimestamp ? messageTimestampElement(isSender, message) : ''}
                </div>
            </div>
     `;
}

function messagePinMarker(message){
    return message.pinned_at ? '<ion-icon name="pin" class="align-middle me-1" aria-hidden="true"></ion-icon>' : '';
}

function messagePinAction(message){
    if (messenger.state.messageTemplateMode || messenger.state.newConversationMode) return '';
    const label = messengerEscapeAttr(trans(message.pinned_at ? 'Unpin message' : 'Pin message'));
    return `<button type="button" class="message-pin-toggle message-action-button d-flex justify-content-center align-items-center border-0 text-muted p-0" data-pin-messageid="${messengerEscapeAttr(message.id)}" data-pinned="${message.pinned_at ? 0 : 1}" title="${label}" aria-label="${label}"><ion-icon name="${message.pinned_at ? 'pin' : 'pin-outline'}" aria-hidden="true"></ion-icon></button>`;
}

function messageActions(isSender, message){
    const messageID = parseInt(message.id, 10) || 0;
    const senderID = parseInt(message.sender_id, 10) || 0;
    return `
        <div class="d-flex align-items-center message-actions-wrapper ${isSender ? 'me-2' : 'ms-2'}">
            ${messagePinAction(message)}

            ${isSender ? `
                <div class="d-flex justify-content-center align-items-center pointer-cursor">
                    <div class="to-tooltip message-action-button d-flex justify-content-center align-items-center"  data-bs-placement="top" title="${messengerEscapeAttr(trans('Delete'))}" onClick="messenger.showMessageDeleteDialog(${messageID})">
                        <ion-icon name="trash-outline"></ion-icon>
                    </div>
                </div>
            ` : ``}


             ${isSender === false ? `
                <div class="d-flex justify-content-center align-items-center pointer-cursor">
                    <div class="to-tooltip message-action-button d-flex justify-content-center align-items-center"  data-bs-placement="top" title="${messengerEscapeAttr(trans('Report'))}" onClick="Lists.showReportBox(${senderID}, null, ${messageID}, null);">
                        <ion-icon name="flag-outline"></ion-icon>
                    </div>
                </div>
            ` : ``}

           ${isSender && message.price > 0 ? `
            <div class="d-flex justify-content-center align-items-center">
                <div class="to-tooltip message-action-button d-flex justify-content-center align-items-center"  data-bs-placement="top" title="${messengerEscapeAttr(trans('Paid message'))}">
                    <ion-icon name="cash-outline"></ion-icon>
                 </div>
            </div>
        ` : ``}
      </div>
    `;
}

/**
 * Locked message preview element
 * @param messageData
 * @param senderData
 * @returns {string}
 */
function lockedMessagePreview(messageData, senderData) {
    const safeMessageData = messageData || {};
    const lockedPreview = safeMessageData.lockedPreview || {};
    const hasBlurredPreview = lockedPreview.hasBlurred === true || lockedPreview.hasBlurred === 1 || lockedPreview.hasBlurred === '1';
    const previewPath = messengerSafeUrl(lockedPreview.preview, messengerVars.lockedMessageSVGPath);
    const shouldProtectMedia = typeof app !== 'undefined' && app.feedDisableRightClickOnMedia === true;
    const protectedMediaClass = shouldProtectMedia ? ' no-long-press' : '';
    const protectedMediaAttr = shouldProtectMedia ? ' draggable="false"' : '';

    return `
            <div class="messenger-locked-card${protectedMediaClass} ${hasBlurredPreview ? 'has-blurred-preview' : 'has-fallback-preview'}">
                <div class="lockedPreviewWrapper${protectedMediaClass}">
                    <img class="messenger-locked-preview-media${protectedMediaClass}" src="${messengerEscapeAttr(previewPath)}" alt="${messengerEscapeAttr(trans('Locked message'))}"${protectedMediaAttr}>
                </div>
                <div class="messenger-locked-action${protectedMediaClass}">
                    ${lockedMessageMediaCounts(lockedPreview, shouldProtectMedia)}
                    ${lockedMessagePaymentButton(safeMessageData, senderData, shouldProtectMedia)}
                </div>
            </div>
`;
}

function lockedMessageMediaCounts(lockedPreview, shouldProtectMedia) {
    const counts = lockedPreview.mediaCounts || {};
    const countItems = [
        {type: 'image', icon: 'images-outline'},
        {type: 'video', icon: 'videocam-outline'},
        {type: 'audio', icon: 'musical-notes-outline'},
        {type: 'document', icon: 'document-text-outline'},
        {type: 'file', icon: 'document-text-outline'},
    ];
    let itemsHtml = '';

    $.each(countItems, function (index, item) {
        const count = parseInt(counts[item.type], 10) || 0;
        if(count > 0){
            itemsHtml += `
                <span class="messenger-locked-count-item">
                    <ion-icon name="${messengerEscapeAttr(item.icon)}"></ion-icon>
                    <span>${messengerSanitizeText(count)}</span>
                </span>
            `;
        }
    });

    const textLength = parseInt(lockedPreview.textLength, 10) || 0;
    if(!itemsHtml.length && textLength > 0){
        itemsHtml = `
            <span class="messenger-locked-count-item">
                <ion-icon name="chatbox-ellipses-outline"></ion-icon>
                <span>${messengerSanitizeText(textLength)}</span>
            </span>
        `;
    }

    if(!itemsHtml.length){
        return '';
    }
    const protectedMediaClass = shouldProtectMedia ? ' no-long-press' : '';

    return `
        <div class="messenger-locked-counts${protectedMediaClass}">
            <div class="d-flex align-items-center">
                ${itemsHtml}
            </div>
            <div class="d-none d-md-block small messenger-locked-counts-label">${messengerSanitizeText(trans('PPV content'))}</div>
            <div class="d-flex align-items-center">
                <ion-icon name="lock-closed-outline"></ion-icon>
            </div>
        </div>
    `;
}

/**
 * Locked message payment button
 * @param messageData
 * @param senderData
 * @returns {string}
 */
function lockedMessagePaymentButton(messageData, senderData, shouldProtectMedia) {
    const safeSenderData = senderData || {};
    const safeMessageData = messageData || {};
    let modalData = `
                        data-bs-toggle="modal"
                        data-bs-target="#checkout-center"
                        data-type="message-unlock"
                        data-recipient-id="${messengerEscapeAttr(parseInt(safeSenderData.id, 10) || '')}"
                        data-amount="${messengerEscapeAttr(safeMessageData.price || 0)}"
                        data-first-name="${messengerEscapeAttr(user.billingData.first_name)}"
                        data-last-name="${messengerEscapeAttr(user.billingData.last_name)}"
                        data-billing-address="${messengerEscapeAttr(user.billingData.billing_address)}"
                        data-country="${messengerEscapeAttr(user.billingData.country)}"
                        data-city="${messengerEscapeAttr(user.billingData.city)}"
                        data-state="${messengerEscapeAttr(user.billingData.state)}"
                        data-postcode="${messengerEscapeAttr(user.billingData.postcode)}"
                        data-available-credit="${messengerEscapeAttr(user.billingData.credit)}"
                        data-username="${messengerEscapeAttr(safeSenderData.username)}"
                        data-name="${messengerEscapeAttr(safeSenderData.name)}"
                        data-avatar="${messengerEscapeAttr(messengerSafeUrl(safeSenderData.avatar, ''))}"
                        data-message-id="${messengerEscapeAttr(parseInt(safeMessageData.id, 10) || '')}"
    `;

    if(safeSenderData.canEarnMoney === false) {
        modalData = `
            data-bs-placement="top"
            title="${messengerEscapeAttr(trans('This creator cannot earn money yet'))}"
        `;
    }
    const protectedMediaClass = shouldProtectMedia ? ' no-long-press' : '';

    return `
                <button class="btn btn-round btn-primary w-100 d-flex align-items-center justify-content-center justify-content-md-between mb-0 to-tooltip messenger-locked-unlock-btn${protectedMediaClass}" ${modalData}>
                <span class="d-none d-md-block">${messengerSanitizeText(trans('Locked message'))}</span>  <span>${messengerSanitizeText(trans('Unlock for'))} ${messengerSanitizeText(getWebsiteFormattedAmount(safeMessageData.price || 0))}</span>
                </button>
    `;
}


// eslint-disable-next-line no-unused-vars
function messengerEmptyState(templateID) {
    const template = document.getElementById(templateID);
    return template ? template.innerHTML : '';
}

// eslint-disable-next-line no-unused-vars
function noConversationLabel() {
    return messengerEmptyState('messenger-no-conversation-template');
}

// eslint-disable-next-line no-unused-vars
function noMessagesLabel() {
    return messengerEmptyState('messenger-no-messages-template');
}

// eslint-disable-next-line no-unused-vars
function newConversationLabel() {
    return messengerEmptyState('messenger-new-conversation-template');
}

// eslint-disable-next-line no-unused-vars
function noContactsLabel() {
    return messengerEmptyState('messenger-no-contacts-template');
}

// eslint-disable-next-line no-unused-vars
function noContactsSearchLabel() {
    return messengerEmptyState('messenger-no-contacts-search-template');
}

// eslint-disable-next-line no-unused-vars
function noMessageSearchResultsLabel() {
    return messengerEmptyState('messenger-no-message-search-template');
}

function storyReplyBubble(isSender, storyRef, avatarFallback) {
    var preview = (storyRef && storyRef.preview) ? messengerSafeUrl(storyRef.preview, '') : '';
    var storyId = storyRef && storyRef.id ? parseInt(storyRef.id, 10) : null;

    // Safe translation helper (in case trans() isn't present on some pages)
    var t = (typeof trans === "function") ? trans : function (s) { return s; };

    var thumbHtml = '';

    if (preview) {
        // Preview thumbnail (media story) – keep same shape as avatar thumb
        thumbHtml = `
            <img src="${messengerEscapeAttr(preview)}"
                 class="rounded-circle me-2 story-reply-thumb story-reply-thumb--avatar"
                 alt="">
        `;
    } else if (avatarFallback) {
        var safeAvatarFallback = messengerSafeUrl(avatarFallback, '');
        // Avatar fallback (text story or missing preview)
        thumbHtml = `
            <img src="${messengerEscapeAttr(safeAvatarFallback)}"
                 class="rounded-circle me-2 story-reply-thumb story-reply-thumb--avatar"
                 alt="">
        `;
    } else {
        // Placeholder
        thumbHtml = `<div class="rounded-circle me-2 story-reply-thumb story-reply-thumb--placeholder"></div>`;
    }

    return `
        <div class="d-flex flex-row mb-1">
            <div class="col-12 d-flex ${isSender ? 'sender d-flex flex-row-reverse pe-1' : 'ps-0'}">
                <a href="#"
                   class="m-0 message-bubble alert ${isSender ? 'alert-primary text-white' : 'alert-default'} text-decoration-none story-reply-bubble"
                   data-story-id="${messengerEscapeAttr(storyId || '')}">
                    <div class="d-flex align-items-center">
                        ${thumbHtml}
                        <div class="d-flex flex-column">
                            <div class="fw-bold">${messengerSanitizeText(t('Story reply'))}</div>
                            <div class="${isSender ? 'text-white-50' : 'text-muted'}">
                                ${messengerSanitizeText(preview ? t('Tap to view') : t('Text story'))}
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    `;
}
