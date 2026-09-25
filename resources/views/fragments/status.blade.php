@if (session('status'))
    <div class="notice">
        @switch(session('status'))
            @case(\Codewiser\Otp\Otp::SENT)
                @lang('otp::messages.'.\Codewiser\Otp\Otp::SENT)
                @break
            @default
                {{ session('status') }}
        @endswitch
    </div>
@endif