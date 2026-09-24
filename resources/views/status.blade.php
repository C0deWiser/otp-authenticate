@if (session('status'))
    <div class="mb-4 font-medium text-sm text-green-600">
        @switch(session('status'))
            @case(\Codewiser\Otp\Otp::OTP_SENT)
                @lang('One time password has been sent to your email address.')
                @break
            @case(\Codewiser\Otp\Otp::OTP_LOST)
                @lang('One time password is lost, we\'ve sent you a new one.')
                @break
            @case(\Codewiser\Otp\Otp::OTP_MISMATCH)
                @lang('One time password does not match our records.')
                @break
            @case(\Codewiser\Otp\Otp::OTP_THROTTLE)
                {{ __('Wait for :delay before retry.', ['delay' => session('delay')]) }}
                @break
            @default
                {{ session('status') }}
        @endswitch
    </div>
@endif