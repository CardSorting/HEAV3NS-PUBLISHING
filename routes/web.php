<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Admin routes ( Needs to be placed above )
Route::group(['prefix' => 'admin', 'middleware' => ['jsVars', 'admin']], function () {
    Route::get('/users/{id}/impersonate', 'UserController@impersonate')->name('admin.impersonate');
    Route::get('/leave-impersonation', 'UserController@leaveImpersonation')->name('admin.leaveImpersonation');
    Route::get('/clear-app-cache', 'GenericController@clearAppCache')->name('admin.clear.cache');

    Route::post('/withdrawals/{withdrawalId}/approve', 'WithdrawalsController@approveWithdrawal')->name('admin.withdrawals.approve');
    Route::post('/withdrawals/{withdrawalId}/reject', 'WithdrawalsController@rejectWithdrawal')->name('admin.withdrawals.reject');

    Route::get('/update', ['uses' => 'InstallerController@upgrade', 'as'   => 'installer.update']);
    Route::post('/update/doUpdate', ['uses' => 'InstallerController@doUpgrade', 'as'   => 'installer.doUpdate']);

});

// Home & contact page
Route::get('/', ['uses' => 'HomeController@index', 'as'   => 'home']);
Route::get('/contact', ['uses' => 'GenericController@contact', 'as'   => 'contact']);
Route::post('/contact/send', ['uses' => 'GenericController@sendContactMessage', 'as'   => 'contact.send']);

// Site entry age verification
Route::get('/age-check', ['uses' => 'AgeCheckController@show', 'as' => 'age-check.show']);
Route::get('/age-check/start', ['uses' => 'AgeCheckController@start', 'as' => 'age-check.start']);
Route::get('/age-check/callback', ['uses' => 'AgeCheckController@callback', 'as' => 'age-check.callback']);

// Language switcher route
Route::get('language/{locale}', ['uses' => 'GenericController@setLanguage', 'as'   => 'language']);

// Marketing consent is available to guests and remains protected by the web CSRF middleware.
Route::post('/conversion-tracking/consent', [App\Http\Controllers\ConversionTrackingController::class, 'consent'])
    ->middleware('throttle:30,1')->name('conversion-tracking.consent');

/* Auth Routes + Verify password */
Auth::routes(['verify'=>true]);
Route::get('email/verify', ['uses' => 'GenericController@userVerifyEmail', 'as' => 'verification.notice']);
Route::post('resendVerification', ['uses' => 'GenericController@resendConfirmationEmail', 'as'   => 'verfication.resend']);
// Social Auth login / register
Route::get('socialAuth/{provider}', ['uses' => 'Auth\LoginController@redirectToProvider', 'as' => 'social.login.start']);
Route::get('socialAuth/{provider}/callback', ['uses' => 'Auth\LoginController@handleProviderCallback', 'as' => 'social.login.callback']);

/*
 * (User) Protected routes
 */
Route::group(['middleware' => ['auth', 'verified', '2fa']], function () {
    Route::get('/account/suspended', 'AccountSuspensionController')->name('account.suspension');
    Route::get('/file-attachments/{attachment}/download', 'FileAttachmentDownloadController@owner')->name('file-attachments.owner');
    Route::get('/file-attachments/messages/{message}/{attachment}/download', 'FileAttachmentDownloadController@message')->name('file-attachments.message');
    Route::get('/file-attachments/admin/{attachment}/download', 'FileAttachmentDownloadController@admin')->name('file-attachments.admin');
    Route::post('/manual-payment-documents', 'ManualPaymentDocumentController@store')->middleware('throttle:20,1')->name('manual-payment-documents.store');
    Route::get('/manual-payment-documents/{document}', 'ManualPaymentDocumentController@download')->name('manual-payment-documents.download');
    Route::delete('/manual-payment-documents/{document}', 'ManualPaymentDocumentController@destroy')->name('manual-payment-documents.destroy');
    Route::get('/manual-payment-receiving-images', 'ManualPaymentReceivingImageController@show')->name('manual-payment-receiving-images.show');
    Route::post('/conversion-tracking/pending', [App\Http\Controllers\ConversionTrackingController::class, 'pending'])
        ->middleware('throttle:20,1')->name('conversion-tracking.pending');
    Route::post('/conversion-tracking/acknowledge', [App\Http\Controllers\ConversionTrackingController::class, 'acknowledge'])
        ->middleware('throttle:20,1')->name('conversion-tracking.acknowledge');

    // Settings panel routes
    Route::group(['prefix' => 'my', 'as' => 'my.'], function () {

        Route::group(['prefix' => 'vault', 'as' => 'vault.'], function () {
            Route::get('/', ['uses' => 'VaultController@index', 'as' => 'index']);
            Route::get('/items', ['uses' => 'VaultController@items', 'as' => 'items']);
            Route::post('/resolve', ['uses' => 'VaultController@resolve', 'as' => 'resolve']);
            Route::get('/{attachment}/usage', ['uses' => 'VaultController@usage', 'as' => 'usage'])->where('attachment', '[a-fA-F0-9-]{32,36}');
            Route::post('/{attachment}/archive', ['uses' => 'VaultController@archive', 'as' => 'archive'])->where('attachment', '[a-fA-F0-9-]{32,36}');
            Route::post('/{attachment}/restore', ['uses' => 'VaultController@restore', 'as' => 'restore'])->where('attachment', '[a-fA-F0-9-]{32,36}');
            Route::delete('/{attachment}', ['uses' => 'VaultController@destroy', 'as' => 'destroy'])->where('attachment', '[a-fA-F0-9-]{32,36}');
        });

        /*
         * (My) Settings
         */
        // Deposit - Payments
        Route::post('/settings/deposit/generateStripeSession', [
            'uses' => 'PaymentsController@generateStripeSession',
            'as'   => 'settings.deposit.generateStripeSession',
        ]);
        Route::post('/settings/flags/save', ['uses' => 'SettingsController@updateFlagSettings', 'as'   => 'settings.flags.save']);
        Route::post('/settings/profile/save', ['uses' => 'SettingsController@saveProfile', 'as'   => 'settings.profile.save']);
        Route::post('/settings/rates/save', ['uses' => 'SettingsController@saveRates', 'as'   => 'settings.rates.save']);
        Route::post('/settings/creator/apply', ['uses' => 'SettingsController@applyForCreator', 'as' => 'settings.creator.apply'])->middleware('throttle:6,1,creator-application:');
        Route::post('/settings/profile/upload/{uploadType}', ['uses' => 'SettingsController@uploadProfileAsset', 'as'   => 'settings.profile.upload']);
        Route::post('/settings/profile/remove/{assetType}', ['uses' => 'SettingsController@removeProfileAsset', 'as'   => 'settings.profile.remove']);
        Route::post('/settings/save', ['uses' => 'SettingsController@updateUserSettings', 'as'   => 'settings.save']);
        Route::post('/settings/verify/upload', ['uses' => 'SettingsController@verifyUpload', 'as'   => 'settings.verify.upload']);
        Route::post('/settings/verify/upload/delete', ['uses' => 'SettingsController@deleteVerifyAsset', 'as'   => 'settings.verify.delete']);
        Route::post('/settings/verify/save', ['uses' => 'SettingsController@saveVerifyRequest', 'as'   => 'settings.verify.save']);
        Route::post('/settings/release-forms/upload', ['uses' => 'SettingsController@releaseFormUpload', 'as'   => 'settings.release-forms.upload']);
        Route::post('/settings/release-forms/upload/delete', ['uses' => 'SettingsController@deleteReleaseFormAsset', 'as'   => 'settings.release-forms.upload.delete']);
        Route::post('/settings/release-forms/save', ['uses' => 'SettingsController@saveReleaseForm', 'as'   => 'settings.release-forms.save']);
        Route::delete('/settings/release-forms/{releaseForm}', ['uses' => 'SettingsController@deleteReleaseForm', 'as'   => 'settings.release-forms.delete']);
        Route::get('/settings/privacy/countries', ['uses' => 'SettingsController@getCountries', 'as'   => 'settings.verify.countries']);
        Route::post('/settings/taxes/save', ['uses' => 'SettingsController@addUserTaxInformation', 'as'   => 'settings.taxes.save']);
        Route::post('/settings/payout-accounts/save', ['uses' => 'SettingsController@savePayoutAccount', 'as'   => 'settings.payout-accounts.save']);
        Route::get('/settings/payout-accounts/{payoutAccount}', ['uses' => 'SettingsController@showPayoutAccount', 'as'   => 'settings.payout-accounts.show']);
        Route::delete('/settings/payout-accounts/{payoutAccount}', ['uses' => 'SettingsController@deletePayoutAccount', 'as'   => 'settings.payout-accounts.delete']);
        Route::post('/settings/assets/ai/{assetType}', ['uses' => 'SettingsController@generateProfileAsset', 'as'   => 'settings.profile.generateAsset'])->middleware('feature.throttle:profile_asset_generate');

        // Profile save
        Route::get('/settings/{type?}', ['uses' => 'SettingsController@index', 'as'   => 'settings']);
        Route::post('/settings/account/save', ['uses' => 'SettingsController@saveAccount', 'as'   => 'settings.account.save']);
        Route::post('/settings/account/email-change', ['uses' => 'UserEmailChangeController@store', 'as' => 'settings.account.email.store'])
            ->middleware('throttle:6,1');
        Route::post('/settings/account/email-change/resend', ['uses' => 'UserEmailChangeController@resend', 'as' => 'settings.account.email.resend'])
            ->middleware('throttle:6,1');
        Route::post('/settings/account/email-change/cancel', ['uses' => 'UserEmailChangeController@cancel', 'as' => 'settings.account.email.cancel']);
        Route::get('/settings/account/email-change/{emailChange}/{token}', ['uses' => 'UserEmailChangeController@verify', 'as' => 'settings.account.email.verify'])
            ->middleware('signed');
        Route::post('/settings/account/delete-request', ['uses' => 'AccountDeletionController@store', 'as' => 'settings.account.delete-request']);
        Route::post('/settings/account/delete-request/cancel', ['uses' => 'AccountDeletionController@cancel', 'as' => 'settings.account.delete-request.cancel']);

        // Spotify integration
        Route::get('/settings/spotify', ['uses' => 'SettingsController@spotifyIndex', 'as'   => 'settings.spotify']);
        Route::get('/settings/spotify/redirect', ['uses' => 'SettingsController@spotifyRedirect', 'as'   => 'settings.spotify.redirect']);
        Route::get('/settings/spotify/callback', ['uses' => 'SettingsController@spotifyCallback', 'as'   => 'settings.spotify.callback']);
        Route::post('/settings/spotify/disconnect', ['uses' => 'SettingsController@spotifyDisconnect', 'as'   => 'settings.spotify.disconnect']);
        Route::get('/settings/spotify/search', ['uses' => 'SettingsController@spotifySearchTracks', 'as'   => 'settings.spotify.search']);
        Route::post('/settings/spotify/anthem', ['uses' => 'SettingsController@spotifySetAnthem', 'as'   => 'settings.spotify.anthem']);
        Route::post('/settings/spotify/refresh', ['uses' => 'SettingsController@spotifyRefreshSnapshot', 'as'   => 'settings.spotify.refresh']);

        Route::post('/setting/push/subscribe', [
            'uses' => 'PushSubscriptionController@store',
            'as'   => 'push.subscribe',
        ]);

        Route::post('/setting/push/unsubscribe', [
            'uses' => 'PushSubscriptionController@destroy',
            'as'   => 'push.unsubscribe',
        ]);

        /*
         * (My) Notifications
         */
        Route::get('/notifications/{type?}', ['uses' => 'NotificationsController@index', 'as'   => 'notifications']);

        /*
         * (My) Messenger
         */
        Route::group(['prefix' => 'messenger', 'as' => 'messenger.'], function () {
            Route::get('/', ['uses' => 'MessengerController@index', 'as' => 'get']);
            Route::get('/fetchContacts', ['uses' => 'MessengerController@fetchContacts', 'as' => 'fetch']);
            Route::get('/fetchMessages/{userID}', ['uses' => 'MessengerController@fetchMessages', 'as' => 'fetch.user']);
            Route::get('/pins/{userID}', ['uses' => 'MessengerController@fetchPinnedMessages', 'as' => 'pins.fetch'])->whereNumber('userID');
            Route::post('/pin/{messageID}', ['uses' => 'MessengerController@setMessagePin', 'as' => 'pins.set'])->whereNumber('messageID');
            Route::get('/messageTemplates', ['uses' => 'MessengerController@fetchMessageTemplates', 'as' => 'templates.fetch']);
            Route::post('/messageTemplates', ['uses' => 'MessengerController@saveMessageTemplate', 'as' => 'templates.save']);
            Route::post('/sendMessage', ['uses' => 'MessengerController@sendMessage', 'as' => 'send'])->middleware('feature.throttle:messenger_send');
            Route::delete('/delete/{commentID}', ['uses' => 'MessengerController@deleteMessage', 'as' => 'delete']);
            Route::post('/authorizeUser', ['uses' => 'MessengerController@authorizeUser', 'as' => 'authorize']);
            Route::post('/markSeen', ['uses' => 'MessengerController@markSeen', 'as' => 'mark']);
        });
        /*
         * (My) Bookmarks
         */
        Route::any('/bookmarks/{type?}', ['uses' => 'BookmarksController@index', 'as'   => 'bookmarks']);
//        Route::get('/bookmarks/{type}',['uses' => 'BookmarksController@filterBookmarks', 'as'   => 'bookmarks.filter']);

        /*
         * (My) Lists
         */
        Route::group(['prefix' => '', 'as' => 'lists.'], function () {
            Route::get('/lists', ['uses' => 'ListsController@index', 'as'   => 'all']);
            Route::post('/lists/save', ['uses' => 'ListsController@saveList', 'as'   => 'save']);
            Route::get('/lists/{list_id}', ['uses' => 'ListsController@showList', 'as'   => 'show']);
            Route::delete('/lists/delete', ['uses' => 'ListsController@deleteList', 'as'   => 'delete']);
            Route::post('/lists/members/save', ['uses' => 'ListsController@addListMember', 'as'   => 'members.save']);
            Route::delete('/lists/members/delete', ['uses' => 'ListsController@deleteListMember', 'as'   => 'members.delete']);
            Route::post('/lists/members/clear', ['uses' => 'ListsController@clearList', 'as'   => 'members.clear']);
            Route::post('/lists/manage/follows', ['uses' => 'ListsController@manageUserFollows', 'as'   => 'manage.follows']);
        });

        // (My) Streams routes
        Route::group(['prefix' => 'streams', 'as' => 'streams.'], function () {
            Route::get('', ['uses' => 'StreamsController@index', 'as'   => 'get']);
            Route::post('init', ['uses' => 'StreamsController@initStream', 'as'   => 'init'])->middleware('feature.throttle:streams_init');
            Route::post('edit', ['uses' => 'StreamsController@saveStreamDetails', 'as'   => 'edit']);
            Route::post('stop', ['uses' => 'StreamsController@stopStream', 'as'   => 'stop']);
            Route::delete('delete', ['uses' => 'StreamsController@deleteStream', 'as'   => 'delete']);
            Route::post('poster-upload', ['uses' => 'StreamsController@posterUpload', 'as'   => 'poster.upload']);
            Route::get('broadcast', ['uses' => 'StreamsController@liveKitBroadCast', 'as'  => 'livekit.broadcast']);
            Route::post('livekit/token', ['uses' => 'StreamsController@generateToken', 'as'  => 'livekit.token']);
        });

        Route::group(['prefix' => '', 'as' => 'polls.'], function () {
            Route::post('/polls/save', ['uses' => 'ListsController@saveList', 'as'   => 'save']);
        });

    });

    Route::post('authorizeStreamPresence', ['uses' => 'StreamsController@authorizeUser', 'as'  => 'public.stream.authorizeUser']);
    Route::post('stream/comments/add', ['uses' => 'StreamsController@addComment', 'as'  => 'public.stream.comment.add'])->middleware('feature.throttle:stream_comments_add');
    Route::delete('stream/comments/delete', ['uses' => 'StreamsController@deleteComment', 'as'  => 'public.stream.comment.delete']);
    Route::get('stream/archive/{streamID}/{slug}', ['uses' => 'StreamsController@getVod', 'as'  => 'public.vod.get']);
    Route::get('stream/{streamID}/{slug}', ['uses' => 'StreamsController@getStream', 'as'  => 'public.stream.get']);

    Route::post('/report/content', ['uses' => 'ListsController@postReport', 'as'   => 'report.content']);

    Route::group(['prefix' => 'payment', 'as' => 'payment.'], function () {
        Route::post('/initiate', ['uses' => 'PaymentsController@initiatePayment', 'as'   => 'initiatePayment']);
        Route::post('/initiate/validate', ['uses' => 'PaymentsController@paymentInitiateValidator', 'as'   => 'initiatePaymentValidator']);
        Route::get('/paypal/status', ['uses' => 'PaymentsController@executePaypalPayment', 'as'   => 'executePaypalPayment']);
        Route::get('/stripe/status', ['uses' => 'PaymentsController@getStripePaymentStatus', 'as'   => 'checkStripePaymentStatus']);
        Route::get('/yookassa/status', ['uses' => 'PaymentsController@checkAndUpdateYooKassaTransaction', 'as'   => 'checkYooKassaPaymentStatus']);
        Route::get('/mollie/status', ['uses' => 'PaymentsController@checkAndUpdateMollieTransaction', 'as'   => 'checkMolliePaymentStatus']);
        Route::get('/flutterwave/status', ['uses' => 'PaymentsController@checkAndUpdateFlutterwaveTransaction', 'as'   => 'checkFlutterwavePaymentStatus']);
        Route::get('/coingate/status', ['uses' => 'PaymentsController@checkAndUpdateCoinGateTransaction', 'as'   => 'checkCoinGatePaymentStatus']);
        Route::get('/xendit/status', ['uses' => 'PaymentsController@checkAndUpdateXenditTransaction', 'as'   => 'checkXenditPaymentStatus']);
        Route::get('/paddle/status', ['uses' => 'PaymentsController@checkAndUpdatePaddleTransaction', 'as'   => 'checkPaddlePaymentStatus']);
        Route::get('/cryptocom/status', ['uses' => 'PaymentsController@checkAndUpdateCryptoComTransaction', 'as'   => 'checkCryptoComPaymentStatus']);
        Route::get('/nowpayments/status', ['uses' => 'PaymentsController@checkAndUpdateNowPaymentsTransaction', 'as'   => 'checkNowPaymentStatus']);
        Route::get('/ccbill/status', ['uses' => 'PaymentsController@processCCBillTransaction', 'as'   => 'checkCCBillPaymentStatus']);
        Route::get('/paystack/status', ['uses' => 'PaymentsController@verifyPaystackTransaction', 'as'   => 'checkPaystackPaymentStatus']);
        Route::get('/mercado/status', ['uses' => 'PaymentsController@verifyMercadoTransaction', 'as'   => 'checkMercadoPaymentStatus']);
        Route::get('/verotel/status', ['uses' => 'PaymentsController@verifyVerotelTransaction', 'as'   => 'checkVerotelPaymentStatus']);
        Route::get('/razorpay/status', ['uses' => 'PaymentsController@verifyRazorPayTransaction', 'as'   => 'checkRazorPayPaymentStatus']);
        Route::post('/taxes/quote', ['uses' => 'PaymentsController@quoteTaxes', 'as'   => 'quoteTaxes']);
    });

    // Feed routes
    Route::get('/feed', ['uses' => 'FeedController@index', 'as'   => 'feed']);
    Route::get('/feed/posts', ['uses' => 'FeedController@getFeedPosts', 'as'   => 'feed.posts']);

    // Reels routes
    Route::group(['prefix' => 'reels', 'as' => 'reels.'], function () {
        Route::get('', ['uses' => 'ReelsController@index', 'as' => 'index']);
        Route::get('/create', ['uses' => 'ReelsController@create', 'as' => 'create']);
        Route::post('/create', ['uses' => 'ReelsController@store', 'as' => 'store'])->middleware('feature.throttle:reels_store');
        Route::get('/feed', ['uses' => 'ReelsController@feed', 'as' => 'feed']);
        Route::post('/view', ['uses' => 'ReelsController@markView', 'as' => 'view']);
        Route::get('/comments', ['uses' => 'ReelsController@comments', 'as' => 'comments']);
        Route::get('/comments/replies', ['uses' => 'ReelsController@commentReplies', 'as' => 'comments.replies']);
        Route::post('/comments/add', ['uses' => 'ReelsController@addComment', 'as' => 'comments.add'])->middleware('feature.throttle:reels_comments_add');
        Route::delete('/comments/delete', ['uses' => 'ReelsController@deleteComment', 'as' => 'comments.delete']);
        Route::post('/reaction', ['uses' => 'ReelsController@reaction', 'as' => 'react']);
        Route::post('/bookmark', ['uses' => 'ReelsController@bookmark', 'as' => 'bookmark']);
        Route::delete('/delete', ['uses' => 'ReelsController@delete', 'as' => 'delete']);
        Route::get('/{reel_id}', ['uses' => 'ReelsController@show', 'as' => 'get']);
    });

    // File uploader routes
    Route::group(['prefix' => 'attachment', 'as' => 'attachment.'], function () {
        Route::post('/resolve', ['uses' => 'AttachmentController@resolve', 'as' => 'resolve']);
        Route::post('/upload/{type}', ['uses' => 'AttachmentController@upload', 'as'   => 'upload']);
        Route::post('/uploadChunked/{type}', ['uses' => 'AttachmentController@uploadChunk', 'as'   => 'upload.chunked']);
        Route::get('/transcoding-statuses', ['uses' => 'AttachmentController@transcodingStatuses', 'as' => 'transcoding.statuses']);
        Route::post('/transcoding/retry', ['uses' => 'AttachmentController@retryTranscoding', 'as' => 'transcoding.retry']);
        Route::post('/remove', ['uses' => 'AttachmentController@removeAttachment', 'as'   => 'remove']);
    });

    // Posts routes
    Route::group(['prefix' => 'posts', 'as' => 'posts.'], function () {
        Route::post('/save', ['uses' => 'PostsController@savePost', 'as'   => 'save'])->middleware('feature.throttle:posts_save');
        Route::get('/create', ['uses' => 'PostsController@create', 'as'   => 'create']);
        Route::get('/edit/{post_id}', ['uses' => 'PostsController@edit', 'as'   => 'edit']);
        Route::get('/comments/replies', ['uses' => 'PostsController@getCommentReplies', 'as' => 'get.comment.replies']);
        Route::get('/{post_id}/{username}', ['uses' => 'PostsController@getPost', 'as'   => 'get']);
        Route::get('/comments', ['uses' => 'PostsController@getPostComments', 'as'   => 'get.comments']);
        Route::post('/comments/add', ['uses' => 'PostsController@addNewComment', 'as'   => 'add.comments'])->middleware('feature.throttle:posts_comments_add');
        Route::post('/comments/edit', ['uses' => 'PostsController@editComment', 'as'   => 'edit.comments']);
        Route::delete('/comments/delete', ['uses' => 'PostsController@deleteComment', 'as'   => 'delete.comments']);

        Route::post('/reaction', ['uses' => 'PostsController@updateReaction', 'as'   => 'react']);
        Route::post('/bookmark', ['uses' => 'PostsController@updatePostBookmark', 'as'   => 'bookmark']);
        Route::post('/pin', ['uses' => 'PostsController@updatePostPin', 'as'   => 'pin']);
        Route::delete('/delete', ['uses' => 'PostsController@deletePost', 'as'   => 'delete']);

        Route::post('/polls/vote', ['uses' => 'PostsController@userPollVote', 'as'   => 'polls.vote']);
    });

    // Subscriptions routes
    Route::group(['prefix' => 'subscriptions', 'as' => 'subscriptions.'], function () {
        Route::get('/{subscriptionId}/cancel/{redirectTo}', ['uses' => 'SubscriptionsController@cancelSubscription', 'as'   => 'cancel']);
    });

    // Withdrawals routes
    Route::group(['prefix' => 'withdrawals', 'as' => 'withdrawals.'], function () {
        Route::post('/request', ['uses' => 'WithdrawalsController@requestWithdrawal', 'as'   => 'request']);
        Route::get('/onboarding', ['uses' => 'WithdrawalsController@onboarding', 'as'   => 'onboarding']);
    });

    // Invoices routes
    Route::group(['prefix' => 'invoices', 'as' => 'invoices.'], function () {
        Route::get('/{id}', ['uses' => 'InvoicesController@index', 'as'   => 'get']);
    });

    // Countries routes
    Route::group(['prefix' => 'countries', 'as' => 'countries.'], function () {
        Route::get('', ['uses' => 'GenericController@countries', 'as'   => 'get']);
    });

    // Ai routes
    Route::group(['prefix' => 'suggestions', 'as' => 'suggestions.'], function () {
        Route::post('/generate', ['uses' => 'AiController@generateSuggestion', 'as'   => 'generate'])->middleware('feature.throttle:suggestions_generate');
    });

    Route::post('/auth/presence-channel', ['uses' => 'GenericController@authorizePresenceChannel', 'as' => 'presence.auth']);

    // Private stories routes
    Route::group(['prefix' => 'stories', 'as' => 'stories.'], function () {
        Route::get('/create', ['uses' => 'StoriesController@create', 'as' => 'create']);
        Route::post('/create', ['uses' => 'StoriesController@store', 'as' => 'store'])->middleware('feature.throttle:stories_store');
        Route::get('/feed', ['uses' => 'StoriesController@feed', 'as' => 'feed']);
        Route::get('/payload/{id}', ['uses' => 'StoriesController@payload', 'as' => 'payload']);

        Route::post('/upload', ['uses' => 'StoriesController@upload', 'as' => 'upload']);
        Route::post('/view', ['uses' => 'StoriesController@view', 'as' => 'view']);

        Route::delete('/delete', ['uses' => 'StoriesController@delete', 'as' => 'delete']);
        Route::post('/pin-toggle', ['uses' => 'StoriesController@pinToggle', 'as' => 'pinToggle']);
    });

    Route::group(['prefix' => 'sounds', 'as' => 'sounds.'], function () {
        Route::get('/trending', ['uses' => 'SoundsController@trending', 'as' => 'trending']);
        Route::get('/search', ['uses' => 'SoundsController@search', 'as' => 'search']);
    });

});

// Public story routes
Route::group(['prefix' => 'stories', 'as' => 'stories.'], function () {
    Route::get('/s/{story}', ['uses' => 'StoriesController@share', 'as' => 'share']);
    Route::get('/profile/{username}', ['uses' => 'StoriesController@profile', 'as' => 'profile']);
    Route::get('/highlights/{username}', ['uses' => 'StoriesController@highlights', 'as' => 'highlights']);
});

// Subscriptions routes
Route::group(['prefix' => 'subscriptions', 'as' => 'subscriptions.', 'middleware' => ['auth', 'verified', '2fa']], function () {
    Route::get('/{subscriptionId}/cancel/{redirectTo}', ['uses' => 'SubscriptionsController@cancelSubscription', 'as'   => 'cancel']);
});

// 2FA related routes
Route::group(['middleware' => ['auth', 'verified']], function () {
    Route::get('device-verify', ['uses' => 'TwoFAController@index', 'as' => '2fa.index']);
    Route::post('device-verify', ['uses' => 'TwoFAController@store', 'as' => '2fa.post'])->middleware('throttle:2fa-verification');
    Route::get('device-verify/reset', ['uses' => 'TwoFAController@resend', 'as' => '2fa.resend']);
    Route::delete('device-verify/delete', ['uses' => 'TwoFAController@deleteDevice', 'as' => '2fa.delete']);
});

// External media moderation callbacks
Route::post('moderation/sightengine/callback/{item}', 'SightengineModerationCallbackController')
    ->whereNumber('item')
    ->middleware(['signed', 'throttle:60,1'])
    ->name('moderation.sightengine.callback');

Route::post('payment/stripeStatusUpdate', [
    'as'   => 'stripe.payment.update',
    'uses' => 'PaymentsController@stripePaymentsHook',
]);

Route::post('payment/stripeConnectStatusUpdate', [
    'as'   => 'stripeConnect.payment.update',
    'uses' => 'PaymentsController@stripeConnectHook',
]);

Route::post('payment/paypalStatusUpdate', [
    'as'   => 'paypal.payment.update',
    'uses' => 'PaymentsController@paypalPaymentsHook',
]);

Route::post('payment/yookassaStatusUpdate', [
    'as'   => 'yookassa.payment.update',
    'uses' => 'PaymentsController@yooKassaHook',
]);

Route::post('payment/mollieStatusUpdate', [
    'as'   => 'mollie.payment.update',
    'uses' => 'PaymentsController@mollieHook',
]);

Route::post('payment/flutterwaveStatusUpdate', [
    'as'   => 'flutterwave.payment.update',
    'uses' => 'PaymentsController@flutterwaveHook',
]);

Route::post('payment/coingateStatusUpdate', [
    'as'   => 'coingate.payment.update',
    'uses' => 'PaymentsController@coingateHook',
]);

Route::post('payment/xenditStatusUpdate', [
    'as'   => 'xendit.payment.update',
    'uses' => 'PaymentsController@xenditHook',
]);

Route::post('payment/paddleStatusUpdate', [
    'as'   => 'paddle.payment.update',
    'uses' => 'PaymentsController@paddleHook',
]);

Route::post('payment/cryptocomStatusUpdate', [
    'as'   => 'cryptocom.payment.update',
    'uses' => 'PaymentsController@cryptocomHook',
]);

Route::post('payment/nowPaymentsStatusUpdate', [
    'as'   => 'nowPayments.payment.update',
    'uses' => 'PaymentsController@nowPaymentsHook',
]);

Route::post('payment/ccBillPaymentStatusUpdate', [
    'as'   => 'ccBill.payment.update',
    'uses' => 'PaymentsController@ccBillHook',
]);

Route::post('payment/paystackPaymentStatusUpdate', [
    'as'   => 'paystack.payment.update',
    'uses' => 'PaymentsController@paystackHook',
]);

Route::post('payment/mercadoPaymentStatusUpdate', [
    'as'   => 'mercado.payment.update',
    'uses' => 'PaymentsController@mercadoHook',
]);

Route::get('payment/verotelPaymentStatusUpdate', [
    'as'   => 'verotel.payment.update',
    'uses' => 'PaymentsController@verotelHook',
]);

Route::post('payment/razorPayPaymentStatusUpdate', [
    'as'   => 'razorpay.payment.update',
    'uses' => 'PaymentsController@razorPayHook',
]);

Route::post('transcoding/coconut/update', [
    'as'   => 'transcoding.coconut.update',
    'uses' => 'AttachmentController@handleCoconutHook',
]);

// Install & upgrade routes
Route::middleware(App\Http\Middleware\RedirectToInstallationProgress::class)->group(function () {
    Route::get('/install', ['uses' => 'InstallerController@install', 'as'   => 'installer.install']);
    Route::post('/install/savedbinfo', ['uses' => 'InstallerController@testAndSaveDBInfo', 'as'   => 'installer.savedb']);
    Route::post('/install/beginInstall', ['uses' => 'InstallerController@beginInstall', 'as'   => 'installer.beginInstall']);
});
Route::get('/install/progress', ['uses' => 'InstallerController@installationProgress', 'as' => 'installer.progress']);
Route::post('/install/advance', ['uses' => 'InstallerController@advanceInstallation', 'as' => 'installer.advance'])
    ->middleware('throttle:30,1');
Route::post('/install/cancel', ['uses' => 'InstallerController@cancelInstallation', 'as' => 'installer.cancel'])
    ->middleware('throttle:10,1');
Route::post('/install/restart', ['uses' => 'InstallerController@restartInstallation', 'as' => 'installer.restart'])
    ->middleware('throttle:10,1');
Route::get('/install/finishInstall', ['uses' => 'InstallerController@finishInstall', 'as'   => 'installer.finishInstall']);

// (Feed/Search) Suggestions filter
Route::post('/suggestions/members', ['uses' => 'FeedController@filterSuggestedMembers', 'as'   => 'suggestions.filter']);

// Public pages
Route::get('/pages/{slug}', ['uses' => 'PublicPagesController@getPage', 'as'   => 'pages.get']);

Route::get('/search', ['uses' => 'SearchController@index', 'as' => 'search.get']);
Route::get('/search/posts', ['uses' => 'SearchController@getSearchPosts', 'as' => 'search.posts']);
Route::get('/search/users', ['uses' => 'SearchController@getUsersSearch', 'as' => 'search.users']);
Route::get('/search/streams', ['uses' => 'SearchController@getStreamsSearch', 'as' => 'search.streams']);

Route::post('/markBannerAsSeen', ['uses' => 'GenericController@markBannerAsSeen', 'as'   => 'banner.mark.seen']);

// Public profile
Route::get('/file-attachments/posts/{post}/{attachment}/download', 'FileAttachmentDownloadController@post')->name('file-attachments.post');
Route::get('/{username}', ['uses' => 'ProfileController@index', 'as'   => 'profile']);
Route::get('/{username}/posts', ['uses' => 'ProfileController@getUserPosts', 'as'   => 'profile.posts']);
Route::get('/{username}/streams', ['uses' => 'ProfileController@getUserStreams', 'as'   => 'profile.streams']);
Route::get('/{username}/reels', ['uses' => 'ProfileController@getUserReels', 'as'   => 'profile.reels']);

Route::fallback(function () {
    abort(404);
});
