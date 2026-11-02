@php
    $deliveries = collect();
    $logAvailable = false;
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('conversion_deliveries')) {
            $columns = ['id', 'transaction_id', 'method', 'status', 'attempts', 'created_at', 'last_error'];
            if (\Illuminate\Support\Facades\Schema::hasColumns('conversion_deliveries', ['purchase_type', 'is_recurring'])) {
                $columns = array_merge($columns, ['purchase_type', 'is_recurring']);
            }
            $deliveries = \App\Model\ConversionDelivery::query()
                ->latest('id')
                ->limit(20)
                ->get($columns);
            $logAvailable = true;
        }
    } catch (\Throwable $exception) {
        // The settings page must remain usable before the upgrade migration runs.
    }
@endphp

<div class="admin-data-log">
    <p class="admin-data-log__description">{{ __('Latest 20 tracking events. Check your provider’s dashboard to confirm delivery.') }}</p>
    @if (! $logAvailable)
        <p class="admin-data-log__empty">{{ __('The delivery log will be available after the conversion-tracking migration runs.') }}</p>
    @elseif ($deliveries->isEmpty())
        <p class="admin-data-log__empty">{{ __('No conversion deliveries have been recorded yet.') }}</p>
    @else
        <div class="admin-data-log__table-wrap" role="region" aria-label="{{ __('Recent conversion deliveries') }}" tabindex="0">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Delivery') }}</th>
                        <th scope="col">{{ __('Transaction') }}</th>
                        <th scope="col">{{ __('Purchase') }}</th>
                        <th scope="col">{{ __('Method') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th scope="col">{{ __('Attempts') }}</th>
                        <th scope="col">{{ __('Created') }}</th>
                        <th scope="col">{{ __('Error') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($deliveries as $delivery)
                        @php
                            $statusClass = match ($delivery->status) {
                                'sent', 'browser_dispatched' => 'is-success',
                                'pending', 'delivering', 'browser_claimed' => 'is-warning',
                                'failed' => 'is-danger',
                                default => 'is-neutral',
                            };
                            $statusLabel = $delivery->status === 'test'
                                ? __('Dry run')
                                : __(ucfirst(str_replace('_', ' ', $delivery->status)));
                            $purchaseLabel = match ($delivery->purchase_type ?? 'subscription') {
                                'subscription' => $delivery->is_recurring ? __('Subscription renewal') : __('Subscription'),
                                'post_unlock' => __('Paid post'),
                                'message_unlock' => __('Paid message'),
                                'stream_access' => __('Livestream access'),
                                'tip' => __('Tip'),
                                default => __('Purchase'),
                            };
                        @endphp
                        <tr>
                            <td>#{{ $delivery->id }}</td>
                            <td>#{{ $delivery->transaction_id }}</td>
                            <td>{{ $purchaseLabel }}</td>
                            <td>{{ __(ucfirst($delivery->method)) }}</td>
                            <td><span class="admin-status-badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                            <td>{{ $delivery->attempts }}</td>
                            <td class="admin-data-table__muted">{{ $delivery->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="admin-data-table__message">{{ $delivery->last_error ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
