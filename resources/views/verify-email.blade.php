<div>
    <h1>{{ __('One time password') }}</h1>

    @include('otp::status')

    <form method="post"
          action="{{ action([\Codewiser\Otp\Http\Controllers\EmailVerificationController::class, 'verify']) }}">
        @csrf
        @method('put')

        <div>
            <label for="code">{{ __('Code') }}</label>
            <input type="text" id="code" name="code" required autofocus>

            @error('code')
            <div class="text-sm text-red-600">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit">{{ __('Submit') }}</button>
    </form>

    <form method="post"
          action="{{ action([\Codewiser\Otp\Http\Controllers\EmailVerificationController::class, 'issue']) }}">
        @csrf

        @if($availableIn)
            <!-- implement js countdown here -->
            <p>{{ __('Next code available in :seconds', ['seconds' => $availableIn]) }}</p>
        @endif

        <button type="submit">
            @if (session('status') === \Codewiser\Otp\Otp::SENT)
                {{ __('Send another one') }}
            @else
                {{ __('Send code') }}
            @endif
        </button>
    </form>

</div>
