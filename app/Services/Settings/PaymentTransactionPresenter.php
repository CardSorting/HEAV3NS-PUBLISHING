<?php

namespace App\Services\Settings;

use App\Model\Transaction;
use App\Providers\SettingsServiceProvider;

class PaymentTransactionPresenter
{
    public function present(Transaction $transaction, int $viewerId): array
    {
        return [
            'statusClass' => $this->getStatusClass($transaction),
            'typePresentation' => $this->getTypePresentation($transaction),
            'formattedAmount' => $this->getFormattedAmount($transaction, $viewerId),
            'createdDateLabel' => $transaction->created_at?->format('M j, Y'),
            'senderDisplayName' => $this->getUserDisplayName($transaction->sender, $viewerId),
            'senderProfileUrl' => $this->getUserProfileUrl($transaction->sender),
            'receiverDisplayName' => $this->getUserDisplayName($transaction->receiver, $viewerId),
            'receiverProfileUrl' => $this->getUserProfileUrl($transaction->receiver),
            'canViewInvoice' => $this->canViewInvoice($transaction, $viewerId),
            'invoiceUrl' => $this->getInvoiceUrl($transaction, $viewerId),
        ];
    }

    protected function getStatusClass(Transaction $transaction): string
    {
        return match ($transaction->status) {
            Transaction::APPROVED_STATUS => 'success',
            Transaction::PENDING_STATUS,
            Transaction::INITIATED_STATUS => 'info',
            Transaction::CANCELED_STATUS,
            Transaction::REFUNDED_STATUS => 'warning',
            Transaction::PARTIALLY_PAID_STATUS => 'primary',
            Transaction::DECLINED_STATUS => 'danger',
            default => 'secondary',
        };
    }

    protected function getTypePresentation(Transaction $transaction): array
    {
        $label = $this->getTypeLabel($transaction);
        $meta = null;
        $url = null;
        $tooltip = null;

        if ($transaction->type === Transaction::WITHDRAWAL_TYPE) {
            $meta = $transaction->payment_provider ? __($transaction->payment_provider) : null;
        } elseif ($transaction->type === Transaction::DEPOSIT_TYPE) {
            $meta = $transaction->payment_provider ? ucfirst(__($transaction->payment_provider)) : null;
            if ($transaction->payment_provider === Transaction::MANUAL_PROVIDER) {
                $meta = data_get($transaction->paymentRequest?->details_snapshot, 'method_name') ?: $meta;
            }
        }

        if ($transaction->type === Transaction::STREAM_ACCESS) {
            $meta = __('Stream');
            $url = $this->getStreamUrl($transaction);
            $tooltip = $url ? null : __('Stream VOD unavailable');
        } elseif ($transaction->type === Transaction::POST_UNLOCK) {
            $postUrl = $this->getPostUrl($transaction);
            if ($postUrl) {
                $meta = __('Post');
                $url = $postUrl;
            }
        } elseif ($transaction->type === Transaction::TIP_TYPE) {
            $postUrl = $this->getPostUrl($transaction);
            if ($postUrl) {
                $meta = __('Post');
                $url = $postUrl;
            } elseif ($transaction->stream) {
                $meta = __('Stream');
                $url = $this->getStreamUrl($transaction);
                $tooltip = $url ? null : __('Stream VOD unavailable');
            } else {
                $meta = __('User');
            }
        } elseif ($transaction->type === Transaction::MESSAGE_UNLOCK) {
            $meta = __('Message');
        }

        return compact('label', 'meta', 'url', 'tooltip');
    }

    protected function getTypeLabel(Transaction $transaction): string
    {
        return match ($transaction->type) {
            Transaction::WITHDRAWAL_TYPE => __('Withdrawal'),
            Transaction::DEPOSIT_TYPE => __('Deposit'),
            Transaction::TIP_TYPE => __('Tip'),
            Transaction::CHAT_TIP_TYPE => __('Chat tip'),
            Transaction::POST_UNLOCK => __('Post unlock'),
            Transaction::MESSAGE_UNLOCK => __('Message unlock'),
            Transaction::STREAM_ACCESS => __('Stream access'),
            Transaction::ONE_MONTH_SUBSCRIPTION => __('One-month subscription'),
            Transaction::THREE_MONTHS_SUBSCRIPTION => __('Three-month subscription'),
            Transaction::SIX_MONTHS_SUBSCRIPTION => __('Six-month subscription'),
            Transaction::YEARLY_SUBSCRIPTION => __('Yearly subscription'),
            Transaction::SUBSCRIPTION_RENEWAL => __('Subscription renewal'),
            default => ucfirst(__($transaction->type)),
        };
    }

    protected function getStreamUrl(Transaction $transaction): ?string
    {
        if (!$transaction->stream) {
            return null;
        }

        if ($transaction->stream->status === 'in-progress') {
            return route('public.stream.get', ['streamID' => $transaction->stream->id, 'slug' => $transaction->stream->slug]);
        }

        if (data_get($transaction->stream->settings, 'dvr') && $transaction->stream->vod_link) {
            return route('public.vod.get', ['streamID' => $transaction->stream->id, 'slug' => $transaction->stream->slug]);
        }

        return null;
    }

    protected function getPostUrl(Transaction $transaction): ?string
    {
        $post = $transaction->post()->first();
        $receiver = $transaction->receiver()->first();

        if (!$post || !$receiver) {
            return null;
        }

        return route('posts.get', ['post_id' => $post->id, 'username' => $receiver->username]);
    }

    protected function getFormattedAmount(Transaction $transaction, int $viewerId): string
    {
        $amount = (float) $transaction->amount;

        if ($transaction->decodedTaxes && $viewerId === $transaction->recipient_user_id) {
            $amount -= (float) ($transaction->decodedTaxes['taxesTotalAmount'] ?? 0);
        }

        return SettingsServiceProvider::getWebsiteFormattedAmount(number_format($amount, 2, '.', ','));
    }

    protected function getUserDisplayName($user, int $viewerId): string
    {
        if (!$user) {
            return '—';
        }

        return $user->id === $viewerId ? __('You') : $user->name;
    }

    protected function getUserProfileUrl($user): ?string
    {
        if (!$user) {
            return null;
        }

        return route('profile', ['username' => $user->username]);
    }

    protected function canViewInvoice(Transaction $transaction, int $viewerId): bool
    {
        return (bool) (
            $transaction->invoice_id &&
            $transaction->receiver &&
            $transaction->receiver->id !== $viewerId &&
            $transaction->status === Transaction::APPROVED_STATUS
        );
    }

    protected function getInvoiceUrl(Transaction $transaction, int $viewerId): ?string
    {
        if (!$this->canViewInvoice($transaction, $viewerId)) {
            return null;
        }

        return route('invoices.get', ['id' => $transaction->invoice_id]);
    }
}
