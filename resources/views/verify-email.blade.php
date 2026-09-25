@extends('fortify::layouts.fortify')

@section('title', __('One time password'))

@section('content')

    <h1>@lang('One time password')</h1>

    @include('otp::fragments.status')

    <form method="post"
          action="{{ action([\Codewiser\Otp\Http\Controllers\EmailVerificationController::class, 'verify']) }}">
        @csrf
        @method('put')

        <div>
            <label for="code">@lang('Code')</label>
            <input type="text" id="code" name="code" required autofocus>

            @error('code')
            <div class="invalid">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit">@lang('Submit')</button>
    </form>

    <form method="post"
          action="{{ action([\Codewiser\Otp\Http\Controllers\EmailVerificationController::class, 'issue']) }}">
        @csrf

        @include('otp::fragments.countdown', ['availableIn' => $availableIn])

        <button type="submit">
            @if (session('status') === \Codewiser\Otp\Otp::SENT)
                @lang('Send another one')
            @else
                @lang('Send code')
            @endif
        </button>
    </form>

@endsection
