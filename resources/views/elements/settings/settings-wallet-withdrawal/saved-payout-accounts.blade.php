@php
    $editingPayoutAccountId = old('payout_account_id', $editingPayoutAccount?->id);
    $selectedPayoutMethod = (string) old('manual_method_id', $editingPayoutAccount?->manual_method_id ?: $manualWithdrawalMethods->first()?->id);
    $payoutAccountFormErrors = collect($errors->keys())->contains(fn ($key) => in_array($key, ['label', 'manual_method_id', 'payout_account_id', 'payout_account_mode', 'isDefault']) || str_starts_with($key, 'account_details'));
    $showPayoutAccountForm = $editingPayoutAccountId || $payoutAccountFormErrors;
    $accountMethods = clone $manualWithdrawalMethods;
    if ($editingPayoutAccount?->manualMethod && !$accountMethods->contains('id', $editingPayoutAccount->manual_method_id)) {
        $accountMethods = $accountMethods->push($editingPayoutAccount->manualMethod);
    }
@endphp
<div class="modal fade" tabindex="-1" aria-labelledby="payout-account-form-title" aria-describedby="payout-account-dialog-description" id="payout-account-dialog"
     data-view="{{ $showPayoutAccountForm ? ($editingPayoutAccountId ? 'edit' : 'create') : 'manage' }}">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header align-items-start">
                <div class="flex-grow-1 pe-3">
                    <button type="button" class="btn btn-link d-inline-flex align-items-center fs-6 fw-normal text-body-secondary p-0 mb-2 shadow-none text-decoration-none text-capitalize payout-account-back {{ $showPayoutAccountForm ? '' : 'd-none' }}">
                        @include('elements.icon', ['icon' => 'arrow-back-outline', 'variant' => 'small', 'classes' => 'me-1']){{ __('Payout accounts') }}
                    </button>
                    <div class="d-flex align-items-center gap-2">
                        <h5 id="payout-account-form-title" class="modal-title" data-manage-label="{{ __('Payout accounts') }}" data-create-label="{{ __('Add payout account') }}" data-edit-label="{{ __('Edit payout account') }}" data-delete-label="{{ __('Delete payout account') }}">{{ $showPayoutAccountForm ? ($editingPayoutAccountId ? __('Edit payout account') : __('Add payout account')) : __('Payout accounts') }}</h5>
                        <span id="payout-account-loading" class="spinner-border spinner-border-sm text-muted d-none" role="status">
                            <span class="visually-hidden">{{ __('Loading...') }}</span>
                        </span>
                    </div>
                    <p id="payout-account-dialog-description" class="small text-muted mb-0 mt-1" data-manage-label="{{ __('Save accounts to use for future withdrawals.') }}" data-form-label="{{ __('Enter the details for receiving withdrawals.') }}" data-delete-label="{{ __('Confirm which account you want to remove.') }}">{{ $showPayoutAccountForm ? __('Enter the details for receiving withdrawals.') : __('Save accounts to use for future withdrawals.') }}</p>
                </div>
                <button type="button" class="btn-close ms-auto flex-shrink-0" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <div id="payout-account-status" class="alert d-none" role="status" aria-live="polite"></div>
                <div id="payout-account-error" class="alert alert-danger d-none" role="alert"></div>
                <div id="payout-account-manage-view" class="{{ $showPayoutAccountForm ? 'd-none' : '' }}">
                    <div id="payout-account-list">
                        @include('elements.settings.settings-wallet-withdrawal.payout-account-list')
                    </div>
                    @if(!$manualWithdrawalMethods->count())
                        <p class="text-muted small mb-0 mt-3">{{ __('No manual payout methods are currently available.') }}</p>
                    @endif
                </div>
                <div id="payout-account-form-view" class="{{ $showPayoutAccountForm ? '' : 'd-none' }}">
                    <form id="payout-account-form" method="POST" action="{{ route('my.settings.payout-accounts.save') }}">
                        @csrf
                        <input id="payout-account-mode" type="hidden" name="payout_account_mode" value="{{ $editingPayoutAccountId ? 'edit' : 'create' }}">
                        <input id="payout-account-id" type="hidden" name="payout_account_id" value="{{ $editingPayoutAccountId }}">
                        @error('payout_account_id')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
                        <div class="mb-3">
                            <label class="form-label" for="payout-account-method">{{ __('Payment method') }}</label>
                            <select id="payout-account-method" name="manual_method_id" class="form-select @error('manual_method_id') is-invalid @enderror" @disabled($editingPayoutAccountId)>
                                @foreach($accountMethods as $method)
                                    <option value="{{ $method->id }}" @selected($selectedPayoutMethod === (string) $method->id) @disabled(!$method->enabled)>{{ $method->name }}</option>
                                @endforeach
                            </select>
                            <input id="payout-account-method-lock" type="hidden" name="manual_method_id" value="{{ $selectedPayoutMethod }}" @disabled(!$editingPayoutAccountId)>
                            <div class="invalid-feedback" role="alert">{{ $errors->first('manual_method_id') }}</div>
                            <div id="payout-account-method-help" class="form-text {{ $editingPayoutAccountId ? '' : 'd-none' }}">{{ __('The payment method stays the same when editing.') }}</div>
                        </div>
                        @foreach($accountMethods as $method)
                            <div class="manual-payout-account-fields row g-3 mb-3 {{ $selectedPayoutMethod === (string) $method->id ? '' : 'd-none' }}" data-method-id="{{ $method->id }}">
                                @include('elements.settings.manual-payout-fields', ['fields' => $method->account_fields ?? [], 'values' => $selectedPayoutMethod === (string) $method->id ? old('account_details', $editingPayoutAccount?->account_details ?? []) : [], 'prefix' => 'account_details', 'idPrefix' => 'payout-account-'.$method->id, 'grid' => true])
                            </div>
                        @endforeach
                        <div class="border-top pt-3 mt-4">
                            <h6 class="mb-3">{{ __('Account preferences') }}</h6>
                            <div class="mb-3">
                                <label class="form-label" for="payout-account-label">{{ __('Account nickname') }} <span class="text-muted small">{{ __('(optional)') }}</span></label>
                                <input id="payout-account-label" name="label" type="text" maxlength="191" placeholder="{{ __('e.g. Personal account') }}" class="form-control @error('label') is-invalid @enderror" value="{{ old('label', $editingPayoutAccount?->label) }}">
                                <div class="invalid-feedback" role="alert">{{ $errors->first('label') }}</div>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input @error('isDefault') is-invalid @enderror" id="payout-account-default" name="isDefault" value="1" @checked(old('isDefault', $editingPayoutAccount?->is_default))>
                                <label class="form-check-label" for="payout-account-default">{{ __('Use by default for this payment method') }}</label>
                                <div class="invalid-feedback" role="alert">{{ $errors->first('isDefault') }}</div>
                            </div>
                        </div>
                    </form>
                </div>
                <div id="payout-account-delete-view" class="d-none">
                    <p class="fw-bold mb-2" id="payout-account-delete-name" data-label="{{ __('Delete ":name"?') }}"></p>
                    <p class="text-muted mb-0">{{ __('You can add this account again later. Previous withdrawals keep their saved details.') }}</p>
                    <form id="payout-account-delete-form" method="POST">
                        @csrf
                        @method('DELETE')
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <div id="payout-account-manage-actions" class="d-flex flex-wrap justify-content-between w-100 gap-2 {{ $showPayoutAccountForm ? 'd-none' : '' }}">
                    <button type="button" class="btn btn-outline-secondary mb-0" data-bs-dismiss="modal">{{ __('Close') }}</button>
                    @if($manualWithdrawalMethods->count())
                        <button type="button" class="btn btn-primary d-inline-flex align-items-center mb-0" id="payout-account-add">
                            @include('elements.icon', ['icon' => 'add-outline', 'variant' => 'small', 'classes' => 'me-1']){{ __('Add account') }}
                        </button>
                    @endif
                </div>
                <div id="payout-account-form-actions" class="d-flex flex-wrap justify-content-end gap-2 {{ $showPayoutAccountForm ? '' : 'd-none' }}">
                    <button type="button" class="btn btn-outline-secondary mb-0 payout-account-back">{{ __('Cancel') }}</button>
                    <button id="payout-account-submit" class="btn btn-primary mb-0" type="submit" form="payout-account-form" data-create-label="{{ __('Save account') }}" data-edit-label="{{ __('Save changes') }}">{{ $editingPayoutAccountId ? __('Save changes') : __('Save account') }}</button>
                </div>
                <div id="payout-account-delete-actions" class="d-none d-flex flex-wrap justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary mb-0 payout-account-back">{{ __('Cancel') }}</button>
                    <button class="btn btn-danger mb-0" type="submit" form="payout-account-delete-form">{{ __('Delete account') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
@if($showPayoutAccountForm)
    <script>window.payoutAccountModalShouldOpen = true;</script>
@endif
