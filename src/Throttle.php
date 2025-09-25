<?php

namespace Codewiser\Otp;

enum Throttle: string
{
    case issue = 'otp-issue';
    case verify = 'otp-verify';
}
