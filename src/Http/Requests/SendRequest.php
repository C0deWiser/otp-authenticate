<?php

namespace Codewiser\Otp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Laravel\Fortify\Fortify;

class SendRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            Fortify::email() => 'required|email',
        ];
    }
}
