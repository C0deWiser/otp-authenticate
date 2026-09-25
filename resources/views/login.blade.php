<div>
    <h1>{{ __('One time password authentication') }}</h1>

    @include('otp::status')

    @if (session('status') === \Codewiser\Otp\Otp::SENT)
        <form method="post"
              action="{{ action([\Codewiser\Otp\Http\Controllers\AuthenticatedSessionController::class, 'verify']) }}">
            @csrf
            @method('put')

            <div>
                <label for="email">{{ __('Email') }}</label>
                <input type="email" id="email" name="email" required readonly value="{{ old('email') }}">

                @error('email')
                <div class="text-sm text-red-600">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="code">{{ __('Code') }}</label>
                <input type="text" id="code" name="code" required autofocus>

                @error('code')
                <div class="text-sm text-red-600">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <input type="checkbox" name="remember" id="remember">
                <label for="remember">@lang('Remember me')</label>
            </div>

            <button type="submit">{{ __('Submit') }}</button>
        </form>
    @endif

    <form method="post"
          action="{{ action([\Codewiser\Otp\Http\Controllers\AuthenticatedSessionController::class, 'issue']) }}">
        @csrf

        <div class="mb-4 font-medium text-sm text-green-600">
            {{ __('We will not warn you if email is not registered in the application.') }}
        </div>

        <div>
            <label for="email">{{ __('Email') }}</label>
            <input type="email" id="email" name="email" required autofocus autocomplete="email"
                   value="{{ old('email') }}">

            @error('email')
            <div class="text-sm text-red-600">{{ $message }}</div>
            @enderror
        </div>

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
