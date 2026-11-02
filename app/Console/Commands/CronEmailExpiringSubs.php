<?php

namespace App\Console\Commands;

use App\Model\Subscription;
use App\Model\Transaction;
use App\Providers\EmailsServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class CronEmailExpiringSubs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:email_expiring_subs {--dry-run : Show which users would be emailed without sending messages}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Emails users when wallet-funded subscriptions are about to expire without enough credit';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');
        $candidateCount = 0;

        Log::channel('cronjobs')->info('[*]['.date('H:i:s')."]  Starting: Expiring wallet-funded subs credit reminders..\r\n");
        if ($dryRun) {
            $this->info('Dry run enabled. No emails will be sent.');
            Log::channel('cronjobs')->info('[*]['.date('H:i:s')."]  Dry run enabled. No expiring wallet-funded subs emails will be sent.\r\n");
        }

        // Wallet-funded subs that are due in the next 24h and may fail renewal due to low credit.
        $renewalSubs = Subscription::with('subscriber.wallet', 'creator')
            ->where('status', Subscription::ACTIVE_STATUS)
            ->where('provider', Transaction::CREDIT_PROVIDER)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDay())
            ->get();

        foreach ($renewalSubs as $subToRenew) {
            if ($subToRenew->subscriber == null || $subToRenew->creator == null) {
                continue;
            }

            if (($subToRenew->subscriber->settings['notification_email_expiring_subs'] ?? null) !== 'true') {
                continue;
            }

            $availableCredit = (float) optional($subToRenew->subscriber->wallet)->total;
            if ($availableCredit >= (float) $subToRenew->amount) {
                continue;
            }

            $candidateCount++;
            $message = sprintf(
                'Would email subscriber #%d <%s> for subscription #%d to creator #%d. Wallet: %.2f, required: %.2f, expires: %s',
                $subToRenew->subscriber->id,
                $subToRenew->subscriber->email,
                $subToRenew->id,
                $subToRenew->creator->id,
                $availableCredit,
                (float) $subToRenew->amount,
                $subToRenew->expires_at
            );

            if ($dryRun) {
                $this->line($message);
                Log::channel('cronjobs')->info('[*]['.date('H:i:s')."]  Dry run: ".$message."\r\n");
                continue;
            }

            App::setLocale($subToRenew->subscriber->settings['locale'] ?? config('app.locale'));
            EmailsServiceProvider::sendGenericEmail(
                [
                    'email' => $subToRenew->subscriber->email,
                    'subject' => __('Expiring subscription'),
                    'title' => __('Hello, :subscriberName', ['subscriberName' => $subToRenew->subscriber->name]),
                    'content' => __('Your subscription to :creatorName is about to expire in the next 24 hours, but your wallet balance is too low to renew it. Please top up your credit in order to keep your subscription going.', ['creatorName' => $subToRenew->creator->name]),
                    'button' => [
                        'text' => __('Top up wallet'),
                        'url' => route('my.settings', ['type' => 'wallet']),
                    ],
                ]
            );
        }

        if ($dryRun) {
            $this->info("Dry run complete. {$candidateCount} email(s) would be sent.");
            Log::channel('cronjobs')->info('[*]['.date('H:i:s')."]  Expiring wallet-funded subs credit reminders dry run complete. {$candidateCount} email(s) would be sent.\r\n");
        } else {
            Log::channel('cronjobs')->info('[*]['.date('H:i:s')."]  Expiring wallet-funded subs credit reminders sent successfully. {$candidateCount} email(s) sent.\r\n");
        }

        return 0;
    }
}
