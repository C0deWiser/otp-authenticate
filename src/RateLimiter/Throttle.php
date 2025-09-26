<?php

namespace Codewiser\Otp\RateLimiter;

enum Throttle: string
{
    case issue = 'otp-issue';
    case verify = 'otp-verify';
}
