@php use Codewiser\Otp\Http\Controllers\EmailVerificationController; @endphp
@extends('fortify::layouts.fortify')

@section('title', __('Email Verification'))

@section('content')

    <h1>@lang('Email Verification')</h1>

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
            <button type="submit" name="send">@lang('Send code')</button>
        </div>
    </form>

@endsection
