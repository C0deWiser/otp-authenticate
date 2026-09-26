@php use Codewiser\Otp\Http\Controllers\AuthenticatedSessionController; @endphp
@extends('fortify::layouts.fortify')

@section('title', __('Authentication with OTP'))

@section('content')

    <h1>@lang('Authentication with OTP')</h1>

    <p class="notice">
        @lang('Authenticate with one time password. Provide your email address and we will send you a code.')
    </p>

    @include('otp::fragments.status')

    <form method="post" action="{{ action([AuthenticatedSessionController::class, 'store']) }}">
        @csrf

        <div>
            <label for="email">@lang('Email')</label>
            <input type="email" id="email" name="email" required autocomplete="email"
                   @if(! old('email')) autofocus @endif
                   value="{{ old('email') }}">

            @error('email')
            <div class="invalid">{{ $message }}</div>
            @enderror
        </div>

        @if (old('email'))

            <div>
                <label for="code">@lang('Code')</label>
                <input type="text" id="code" name="code" autofocus>

                @error('code')
                <div class="invalid">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <input type="checkbox" name="remember" id="remember">
                <label for="remember">@lang('Remember me')</label>
            </div>
        @endif

        <div>
            @if (old('email'))
                <button type="submit">@lang('Submit')</button>
            @endif
            <button type="submit" name="send" data-retry-after="{{ $availableIn }}">@lang('Send code')</button>
        </div>
    </form>

    @push('scripts')
        <script src="{{ asset('vendor/otp/countdown.js') }}" defer></script>
    @endpush

@endsection
