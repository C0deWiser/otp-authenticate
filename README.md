# One time passwords for Laravel

In a very simple case, our Laravel applications authenticates users by login 
and password. Sometimes we force our applications to verify user's emails.

This package requires users to periodically re-verify emails using one time 
passwords.

The authentication process will be:

* user signs-in with login and password
* application sends an email with one time password
* user affirms authentication providing this password

This process is similar to email verification scenario, but invokes more 
than once.

## Installation

Install service.

```php
php artisan otp:install
```

### Service Provider

Configure the frequency of email re-verification and rate limiters in a service 
provider class.

_Providers/OtpServiceProvider.php_

```php
<?php

namespace App\Providers;

use Codewiser\Otp\OtpLimit;
use Codewiser\Otp\OtpService;
use Codewiser\Otp\Throttle;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class OtpServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OtpService::class, 
            fn($app) => new OtpService('P1W')
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for(Throttle::issue, fn(Request $request) => [
            OtpLimit::perMinute(1)->for(Throttle::issue)->by('minute:'.$request->user()->id),
            OtpLimit::perDay(15)->for(Throttle::issue)->by('day:'.$request->user()->id),
        ]);

        RateLimiter::for(Throttle::verify, fn(Request $request) => [
            OtpLimit::perDay(30)->for(Throttle::verify)->by($request->user()->id)
        ]);
    }
}
```

As you can see, we use extended `OtpLimit` with custom response that returns 
user back to the previous page with a throttle message.

You may use base `Limit` as well.

### Otp service constructor

`OtpService` class constructor has one optional parameter. It is a string in
[date interval](https://www.php.net/manual/en/dateinterval.construct.php) 
format.

For example, if we define `new OtpService('P1M')`, users will sign in 
using otp at least once a month.

Empty constructor `new OtpService()` means that every sign in accompanies 
by otp.

> Either way, the otp process will be invoked no more often than once 
> during user session.

### Otp user contract

Apply `MustVerifyEmailWithOtp` contract and `MustVerifyEmailWithOtp` trait 
to a `User` model. These contract and trait extends well known 
`MustVerifyEmail`.

_Models/User.php_

```php
use Codewiser\Otp\Contracts\MustVerifyEmailWithOtp;
use Codewiser\Otp\Traits\MustVerifyEmailWithOtp as HasOtp;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements MustVerifyEmailWithOtp {
    use HasOtp;
    
    //
}
```

### Protecting routes

Use `EnsureOtpIsPassed` middleware to protect only stateful (`web`) requests.

To protect stateless (`api`) requests keep using 
`EnsureEmailIsVerified` (aka `verified`) middleware.

_routes/web.php_

```php
use Codewiser\Otp\EnsureOtpIsPassed;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureOtpIsPassed::class])->group(function () {
    //
});
```

## Customization

You may change published blade template, or you may register custom view.
You may register custom function to generate otp codes.
And you may register custom function for composing a notification.

```php
use Codewiser\Otp\OtpService;
use Codewiser\Otp\Notifications\EmailWithOtp;
use Illuminate\Support\ServiceProvider;

class OtpServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        OtpService::view(fn() => view('auth.custom-view'));
        
        OtpService::newCodeUsing(fn() => rand(1000, 9999));
        
        EmailWithOtp::toMailUsing(function(object $notifiable, string $otp) {
            //
        });
    }
}
```