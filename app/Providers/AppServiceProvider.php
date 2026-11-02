<?php

namespace App\Providers;

use App\Model\Attachment;
use App\Model\PaymentRequest;
use App\Model\Post;
use App\Model\ReleaseForm;
use App\Model\Sound;
use App\Model\Stream;
use App\Model\Subscription;
use App\Model\Transaction;
use App\Model\UserMessage;
use App\Model\UserListMember;
use App\Model\UserVerify;
use App\Model\Withdrawal;
use App\Observers\AttachmentsObserver;
use App\Observers\PaymentRequestsObserver;
use App\Observers\PostApprovalObserver;
use App\Observers\ReleaseFormsObserver;
use App\Observers\SoundsObserver;
use App\Observers\StreamsObserver;
use App\Observers\SubscriptionsObserver;
use App\Observers\TransactionsObserver;
use App\Observers\UserListMembersObserver;
use App\Observers\UserMessagesObserver;
use App\Observers\UsersObserver;
use App\Observers\UserVerifyObserver;
use App\Observers\WithdrawalsObserver;
use App\Model\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (!InstallerServiceProvider::checkIfInstalled()) {
            return false;
        }

        UserVerify::observe(UserVerifyObserver::class);
        ReleaseForm::observe(ReleaseFormsObserver::class);
        Withdrawal::observe(WithdrawalsObserver::class);
        PaymentRequest::observe(PaymentRequestsObserver::class);
        UserMessage::observe(UserMessagesObserver::class);
        Attachment::observe(AttachmentsObserver::class);
        Transaction::observe(TransactionsObserver::class);
        Post::observe(PostApprovalObserver::class);
        Subscription::observe(SubscriptionsObserver::class);
        UserListMember::observe(UserListMembersObserver::class);
        User::observe(UsersObserver::class);
        Sound::observe(SoundsObserver::class);
        Stream::observe(StreamsObserver::class);
        if(getSetting('security.enforce_app_ssl')){
            \URL::forceScheme('https');
        }
        Schema::defaultStringLength(191);
        if(!InstallerServiceProvider::glck()){
            dd(base64_decode('SW52YWxpZCBzY3JpcHQgc2lnbmF0dXJl'));
        }
        // Overriding timezone, if provided
        if(getSetting('site.timezone')){
            config(['app.timezone' => getSetting('site.timezone')]);
            date_default_timezone_set(getSetting('site.timezone'));
        }
        // Both Laravel paginator types share the frontend pagination component.
        Paginator::defaultView('vendor.pagination.compact');
        Paginator::defaultSimpleView('vendor.pagination.compact-simple');
    }
}
