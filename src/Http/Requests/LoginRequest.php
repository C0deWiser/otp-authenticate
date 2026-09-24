<?php

namespace Codewiser\Otp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Laravel\Fortify\Fortify;

class LoginRequest extends FormRequest
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
            'code'           => 'required|string',
            'remember'       => 'sometimes',
        ];
    }
}
