<div>
    <h1>{{ __('One time password') }}</h1>

    @if (session('status'))
        <div class="mb-4 font-medium text-sm text-green-600">
            @switch(session('status'))
                @case(\Codewiser\Otp\OtpService::OTP_SENT)
                    {{ __('One time password has been sent to your email address.') }}
                    @break
                @case(\Codewiser\Otp\OtpService::OTP_LOST)
                    {{ __('One time password is lost, we\'ve sent you a new one.') }}
                    @break
                @case(\Codewiser\Otp\OtpService::OTP_MISMATCH)
                    {{ __('One time password does not match our records.') }}
                    @break
                @case(\Codewiser\Otp\OtpService::OTP_THROTTLE)
                    {{ __('Wait for :delay before retry.', ['delay' => session('delay')]) }}
                    @break
                @default
                    {{ session('status') }}
            @endswitch
        </div>
    @endif

    <form method="post" action="{{ route('user-otp.verify') }}">
        @csrf
        @method('put')

        <div>
            <label for="otp">{{ __('Code') }}</label>
            <input type="text" name="otp" required>

            @error('otp')
            <div class="text-sm text-red-600">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit">{{ __('Submit') }}</button>
    </form>

    <form method="post" action="{{ route('user-otp.send') }}">
        @csrf

        @if($availableIn)
            <p>{{ __('Next code available in :seconds', ['seconds' => $availableIn]) }}</p>
        @endif

        <button type="submit">{{ __('Send another one') }}</button>
    </form>

</div>
