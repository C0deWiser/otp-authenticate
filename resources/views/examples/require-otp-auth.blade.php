@extends('fortify::layouts.fortify')

@section('title', __('Authenticated with OTP'))

@section('content')

    <h1>@lang('Authenticated with OTP')</h1>

    {!! str(__('This page is protected with `:middleware` middleware.', [
        'middleware' => 'auth.otp'
    ]))->markdown() !!}

@endsection
