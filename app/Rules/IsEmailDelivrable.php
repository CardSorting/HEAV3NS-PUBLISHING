<?php

namespace App\Rules;

use App\Services\AbstractApiClient;
use Illuminate\Contracts\Validation\Rule;

class IsEmailDelivrable implements Rule
{
    private string $validationMessage = 'Email address is undeliverable.';

    /**
     * Create a new rule instance.
     */
    public function __construct(
        private ?AbstractApiClient $abstractApiClient = null,
        private ?string $apiKey = null,
    ) {
        $this->abstractApiClient ??= app(AbstractApiClient::class);
        $this->apiKey ??= (string) getSetting('security.email_abstract_api_key');
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $status = $this->abstractApiClient->emailDeliverability(
            $this->apiKey,
            (string) $value,
        );

        if ($status === 'deliverable') {
            return true;
        }

        if ($status !== 'undeliverable') {
            $this->validationMessage = 'Email address could not be verified. Please try again later.';
        }

        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __($this->validationMessage);
    }
}
