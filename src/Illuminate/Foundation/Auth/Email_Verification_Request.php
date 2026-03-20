<?php

declare (strict_types=1);
namespace Illuminate\Foundation\Auth;

use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Http\Form_Request;
use Illuminate\Validation\Validator;
class Email_Verification_Request extends Form_Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (!hash_equals((string) $this->user()->get_key(), (string) $this->route('id'))) {
            return false;
        }
        if (!hash_equals(hash('sha256', (string) $this->user()->get_email_for_verification()), (string) $this->route('hash'))) {
            return false;
        }
        return true;
    }
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }
    /**
     * Fulfill the email verification request.
     */
    public function fulfill(): void
    {
        if (!$this->user()->has_verified_email()) {
            $this->user()->mark_email_as_verified();
            event(new Verified($this->user()));
        }
    }
    /**
     * Configure the validator instance.
     *
     * @return \Illuminate\Validation\Validator
     */
    public function with_validator(Validator $validator)
    {
        return $validator;
    }
}