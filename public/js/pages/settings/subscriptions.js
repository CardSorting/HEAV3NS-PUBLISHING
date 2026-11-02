/**
 * Subscription settings component
 */
"use strict";
/* global app, bootstrap, updateButtonState, trans */

var SubscriptionsSettings = {
    selectedSubID: null,
    redirectTo: 'subscriptions',
    confirmSubCancelation: function (subIDToCancel, redirectTo = 'subscriptions') {
        SubscriptionsSettings.redirectTo = redirectTo;
        SubscriptionsSettings.selectedSubID = subIDToCancel;
        const dialog = document.getElementById('subscription-cancel-dialog');
        if (dialog) {
            bootstrap.Modal.getOrCreateInstance(dialog).show();
        }
    },
    cancelSubscription: function () {
        updateButtonState('loading',$('#subscription-cancel-dialog .btn'), trans('Confirm'));
        window.location.href = app.baseUrl + '/subscriptions/'+SubscriptionsSettings.selectedSubID+'/cancel/'+SubscriptionsSettings.redirectTo;
    }
};
