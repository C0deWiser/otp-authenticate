@if ((int) $availableIn > 0)
    <p class="notice"
       data-otp-countdown
       data-otp-countdown-seconds="{{ (int) $availableIn }}"
       data-otp-countdown-template="{{ __('Next code available in :seconds', ['seconds' => '__otp_countdown_seconds__']) }}">
        {{ __('Next code available in :seconds', ['seconds' => $availableIn]) }}
    </p>

    @push('scripts')
        <script src="{{ asset('vendor/otp/countdown.js') }}" defer></script>
    @endpush
@endif
