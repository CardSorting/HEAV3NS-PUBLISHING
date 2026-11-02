<?php

namespace App\Model;

use App\Notifications\VerifyEmailNotification;
use App\Providers\GenericHelperServiceProvider;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Permission\Traits\HasRoles;
use BezhanSalleh\FilamentShield\Traits\HasPanelShield;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    public function delete()
    {
        return $this->getConnection()->transaction(function () {
            if (!$this->exists || !$this->newQuery()->whereKey($this->getKey())->lockForUpdate()->first()) {
                return false;
            }
            return parent::delete();
        }, 3);
    }

    use Notifiable;

    use HasRoles;

    use HasPanelShield; // Required for Shield to apply permissions in Filament

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->isBanned()) {
            return false;
        }

        if (config('settings.admin_version') !== 'v2') {
            return $this->role_id === 1;
        }

        // (v2) Allow all roles except "user"
        return $this->role_id !== 2;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'email', 'role_id', 'password', 'username', 'bio', 'birthdate', 'location', 'website', 'avatar', 'cover', 'settings',
        'billing_address', 'first_name', 'last_name', 'profile_access_price',
        'gender_id', 'gender_pronoun',
        'profile_access_price_6_months',
        'profile_access_price_12_months',
        'profile_access_price_3_months',
        'public_profile',
        'billing_address', 'first_name', 'last_name', 'city', 'country', 'state', 'postcode',
        'email_verified_at', 'paid_profile',
        'auth_provider', 'auth_provider_id', 'enable_2fa', 'enable_geoblocking', 'open_profile', 'referral_code',
        'last_active_at',
        'last_ip',
        'identity_verified_at',
        'stripe_account_id',
        'stripe_onboarding_verified',
        'stripe_onboarding_verified',
        'country_id',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password', 'remember_token', 'last_ban_id', 'bans', 'activeBan', 'active_ban',
        'creatorEnrollment', 'creator_enrollment',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'public_profile' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }

    /*
     * Virtual attributes
     * TODO: This causes some issues when we're trying to internally refer to to actual raw values
     * TODO: Maybe refactor
     */
    public function getAvatarAttribute($value)
    {
        return GenericHelperServiceProvider::getStorageAvatarPath($value);
    }

    public function getCoverAttribute($value)
    {
        return GenericHelperServiceProvider::getStorageCoverPath($value);
    }

    /**
     * Gets current count of active subscribers.
     * @return int
     * @throws \Exception
     */
    public function getFansCountAttribute() {
        $activeSubscriptionsCount = Subscription::query()
            ->where('recipient_user_id', Auth::user()->id)
            ->whereDate('expires_at', '>=', new \DateTime('now', new \DateTimeZone('UTC')))
            ->count('id');

        return $activeSubscriptionsCount;
    }

    /**
     * Gets the count of followers.
     * @return int|mixed
     */
    public function getFollowingCountAttribute() {
        $userId = Auth::user()->id;
        $userFollowingMembers = UserList::query()
            ->where(['user_id' => $userId, 'type' => 'following'])
            ->withCount('members')->first();

        return $userFollowingMembers != null && $userFollowingMembers->members_count > 0 ? $userFollowingMembers->members_count : 0;
    }

    public function getIsActiveCreatorAttribute($value)
    {
        if(getSetting('compliance.monthly_posts_before_inactive')){
            $check = Post::where('user_id', $this->id)->where('created_at', '>=', Carbon::now()->subDays(30))->count();
            $hasPassedPreApprovedLimit = true;
            if(getSetting('compliance.admin_approved_posts_limit')){
                $hasPassedPreApprovedLimit = Post::where('user_id', $this->id)->where('status', Post::APPROVED_STATUS)->count();
                $hasPassedPreApprovedLimit = $hasPassedPreApprovedLimit >= (int)getSetting('compliance.admin_approved_posts_limit');
            }
            return $hasPassedPreApprovedLimit && $check >= (int)getSetting('compliance.monthly_posts_before_inactive');
        }
        return true;
    }

    /*
     * Relationships
     */
    /** @return HasOne<CreatorEnrollment, $this> */
    public function creatorEnrollment(): HasOne
    {
        return $this->hasOne(CreatorEnrollment::class);
    }

    /**
     * @return HasMany<UserBan, $this>
     */
    public function bans(): HasMany
    {
        return $this->hasMany(UserBan::class);
    }

    /** @return HasOne<UserBan, $this> */
    public function activeBan(): HasOne
    {
        return $this->hasOne(UserBan::class)->whereNull('lifted_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('id');
    }

    public function isBanned(): bool
    {
        if ($this->getAttribute('last_ban_id') === null) {
            return false;
        }

        return $this->relationLoaded('activeBan') ? $this->getRelation('activeBan') !== null : $this->activeBan()->exists();
    }

    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class)->where('status', Post::APPROVED_STATUS);
    }

    /**
     * @return HasMany<PostComment, $this>
     */
    public function postComments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    /**
     * @return HasMany<Reel, $this>
     */
    public function reels(): HasMany
    {
        return $this->hasMany(Reel::class)->where('status', Reel::APPROVED_STATUS);
    }

    /**
     * @return HasMany<Reaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function activeSubscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'sender_user_id')->where('status', 'completed');
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function expiredSubscriptions($limit = 9): HasMany
    {
        return $this->hasMany(Subscription::class, 'sender_user_id')->whereIn('status', [Subscription::EXPIRED_STATUS, Subscription::CANCELED_STATUS])->limit($limit);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function activeCanceledSubscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'sender_user_id')->where('status', 'canceled')->where('expire_at', '<', Carbon::now());
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscription::class, 'recipient_user_id');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function earningsTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'recipient_user_id')
            ->where('status', Transaction::APPROVED_STATUS)
            ->where('type', '!=', Transaction::WITHDRAWAL_TYPE);
    }

    public function getEarningsYtdAttribute(): float
    {
        return (float) $this->earningsTransactions()
            ->whereYear('created_at', now()->year)
            ->sum('amount');
    }

    /**
     * @return HasMany<Withdrawal, $this>
     */
    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    /**
     * @return HasMany<UserPayoutAccount, $this>
     */
    public function payoutAccounts(): HasMany
    {
        return $this->hasMany(UserPayoutAccount::class)->latest('is_default')->latest('id');
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * @return HasMany<UserList, $this>
     */
    public function lists(): HasMany
    {
        return $this->hasMany(UserList::class);
    }

    /**
     * @return HasMany<UserBookmark, $this>
     */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(UserBookmark::class);
    }

    /**
     * @return HasOne<Wallet, $this>
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * @return HasOne<UserVerify, $this>
     */
    public function verification(): HasOne
    {
        return $this->hasOne(UserVerify::class);
    }

    /**
     * @return HasOne<PendingUserEmailChange, $this>
     */
    public function pendingEmailChange(): HasOne
    {
        return $this->hasOne(PendingUserEmailChange::class);
    }

    /**
     * @return HasMany<UserEmailChangeRequest, $this>
     */
    public function emailChangeRequests(): HasMany
    {
        return $this->hasMany(UserEmailChangeRequest::class);
    }

    /**
     * @return HasMany<ReleaseForm, $this>
     */
    public function releaseForms(): HasMany
    {
        return $this->hasMany(ReleaseForm::class);
    }

    /**
     * @return HasOne<CreatorOffer, $this>
     */
    public function offer(): HasOne
    {
        return $this->hasOne(CreatorOffer::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function userCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    /**
     * @return HasMany<Story, $this>
     */
    public function stories(): HasMany
    {
        return $this->hasMany(Story::class)->where('status', Story::APPROVED_STATUS)
            ->latest('created_at');
    }

    /**
     * @return HasOne<UserSpotifyAccount, $this>
     */
    public function spotifyAccount(): HasOne
    {
        return $this->hasOne(UserSpotifyAccount::class);
    }

    public function getLastActiveForHumansAttribute()
    {
        if (!$this->last_active_at) {
            return 'N/A';
        }

        $time = Carbon::parse($this->last_active_at);
        $secondsAgo = $time->diffInSeconds(now());

        // If it's less than 60 seconds, shift the timestamp so no seconds away are shown
        if ($secondsAgo < 60) {
            $time->subSeconds(60);
        }

        // This way, diffForHumans() will show "1 minute ago"
        return $time->diffForHumans();
    }
}
