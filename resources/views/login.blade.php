@extends('fortify::layouts.fortify')

@section('title', __('One time password authentication'))

@section('content')

    <h1>@lang('One time password authentication')</h1>

    @include('otp::fragments.status')

    @if (session('status') === \Codewiser\Otp\Otp::SENT)
        <form method="post"
              action="{{ action([\Codewiser\Otp\Http\Controllers\AuthenticatedSessionController::class, 'verify']) }}">
            @csrf
            @method('put')

            <div>
                <label for="email">@lang('Email')</label>
                <input type="email" id="email" name="email" required readonly value="{{ old('email') }}">

                @error('email')
                <div class="invalid">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="code">@lang('Code')</label>
                <input type="text" id="code" name="code" required autofocus>

                @error('code')
                <div class="invalid">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <input type="checkbox" name="remember" id="remember">
                <label for="remember">@lang('Remember me')</label>
            </div>

            <button type="submit">@lang('Submit')</button>
        </form>
    @endif

    <form method="post"
          action="{{ action([\Codewiser\Otp\Http\Controllers\AuthenticatedSessionController::class, 'issue']) }}">
        @csrf

        <p class="alert">
            @lang('We will not warn you if email is not registered in the application.')
        </p>

        <div>
            <label for="email">@lang('Email')</label>
            <input type="email" id="email" name="email" required autofocus autocomplete="email"
                   value="{{ old('email') }}">

            @error('email')
            <div class="invalid">{{ $message }}</div>
            @enderror
        </div>

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
