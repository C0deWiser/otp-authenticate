@extends('fortify::layouts.fortify')

@section('title', __('Email Verified with OTP'))

@section('content')

    <h1>@lang('Email Verified with OTP')</h1>

    {!! str(__('This page is protected with `:middleware` middleware.', [
        'middleware' => 'verified.otp'
    ]))->markdown() !!}

@endsection
