@php use Codewiser\Otp\Http\Controllers\EmailVerificationController; @endphp
@extends('fortify::layouts.fortify')

@section('title', __('Email Verification with OTP'))

@section('content')

    <h1>@lang('Email Verification with OTP')</h1>

    <p class="notice">
        @lang('Verify your email with one time password. Provide your email address and we will send you a code.')
    </p>

    @include('otp::fragments.status')

    <form method="post" action="{{ action([EmailVerificationController::class, 'store']) }}">
        @csrf

        <div>
            <label for="code">@lang('Code')</label>
            <input type="text" id="code" name="code" autofocus>

            @error('code')
            <div class="invalid">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <button type="submit">@lang('Submit')</button>
            <button type="submit" name="send" data-retry-after="{{ $availableIn }}">@lang('Send code')</button>
        </div>
    </form>

    @push('scripts')
        <script src="{{ asset('vendor/otp/countdown.js') }}" defer></script>
    @endpush

@endsection
