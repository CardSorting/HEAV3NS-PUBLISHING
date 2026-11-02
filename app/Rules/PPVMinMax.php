<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Str;

class PPVMinMax implements Rule
{
    protected $type = 'post';

    protected $minLimit = 1;

    protected $maxLimit = 500;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($type = 'post')
    {
        $this->type = $type;
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
        $hasError = false;
        if($this->type === 'stream') {
            $this->minLimit = getSetting('payments.min_ppv_stream_price') ? (int)getSetting('payments.min_ppv_stream_price') : 5;
            $this->maxLimit = getSetting('payments.max_ppv_stream_price') ? (int)getSetting('payments.max_ppv_stream_price') : 500;
        }
        elseif ($this->type == 'post'){
            $this->minLimit = getSetting('payments.min_ppv_post_price') ? (int)getSetting('payments.min_ppv_post_price') : 1;
            $this->maxLimit = getSetting('payments.max_ppv_post_price') ? (int)getSetting('payments.max_ppv_post_price') : 500;
        }
        elseif ($this->type == 'message'){
            $this->minLimit = getSetting('payments.min_ppv_message_price') ? (int)getSetting('payments.min_ppv_message_price') : 1;
            $this->maxLimit = getSetting('payments.max_ppv_message_price') ? (int)getSetting('payments.max_ppv_message_price') : 500;
        }
        if($this->type === 'stream'){
            if(getSetting('streams.allow_free_streams')){
                if((int)$value < $this->minLimit && (int)$value != 0){
                    $hasError = true;
                }
            }
            else{
                if((int)$value < $this->minLimit){
                    $hasError = true;
                }
            }
        }
        else{
            if((int)$value < $this->minLimit && (int)$value != 0){
                $hasError = true;
            }
        }

        if((int)$value > $this->maxLimit){
            $hasError = true;
        }
        return !$hasError;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('The price must be between :min and :max.', ['min' => $this->minLimit ?? 1, 'max' => $this->maxLimit ?? 500]);
    }
}
