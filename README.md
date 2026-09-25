# One time passwords for Laravel

In a very simple case, our Laravel application authenticates users by login 
and password. Sometimes we force our application to verify users' emails.

This package brings two optional services:

* Allow user to authenticate with one-time-password (via email, without 
  password).
* Force user to periodically revalidate an email.

The authentication process will be:

* user provides an email (login)
* application sends an email with one time password
* user affirms authentication providing this password

> Use `/otp/login` route to authenticate users.

The revalidation process will be:

* user signs-in with login and password
* application sends an email with one time password
* user affirms authentication providing this password

> Apply `EnsureOtpIsPassed` middleware to revalidate emails.

![otp](otp.png)

## Installation

Install service. Publish Service Provider and views.

```php
composer require codewiser/otp-authenticate

php artisan vendor:publish --tag=otp
```

Register `\App\Providers\OtpServiceProvider` to `bootstrap/providers.php` file.

## Implementation

Apply the `MustVerifyEmailWithOtp` interface and, optionally, the 
`MustVerifyEmailWithOtp` trait to a `User` model. This interface extends the 
well known `MustVerifyEmail`.

```php
use Codewiser\Otp\Contracts\MustVerifyEmailWithOtp;
use Codewiser\Otp\Traits\MustVerifyEmailWithOtp as HasOtp;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements MustVerifyEmailWithOtp 
{
    use HasOtp;
    
    //
}
```

## Authentication

### Service Injection

First, we need to register `Codewiser\Otp\OtpAuthenticate` service. 
Register it in your application's `App\Providers\OtpServiceProvider` class.

```php
use Codewiser\Otp\OtpAuthenticate;
use Illuminate\Support\Facades\Auth;

/**
 * Register any application services.
 */
public function register(): void
{
    $this->app->singleton(OtpAuthenticate::class,
        fn($app) => new OtpAuthenticate(
            Auth::createUserProvider('users'),
            Auth::guard('web')
        )
    );
}
```

### Customizing View 

We need to instruct package how to return the "otp/login" view.

All of the authentication view's rendering logic may be customized using the 
appropriate methods available via the `\Codewiser\Otp\Otp` class. 
Typically, you should call this method from the boot method 
of your application's `App\Providers\OtpServiceProvider` class. Service will 
take care of defining the `/otp/login` route that returns this view:

```php
use Codewiser\Otp\Otp;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Otp::loginView('otp::login');
}
```

`Login` template should include:
* a form that makes a POST request to `/otp/login`. 
  This endpoint expects a string `email` and sends a 
  notification with one-time-password to a given email.
* a form that makes a PUT request to `/otp/login`.
  This endpoint expects a string `email` and a `code`. 
  The name of the `email` field should match the `email` value 
  within the `config/fortify.php` configuration file. 
  In addition, a boolean `remember` field may be provided to indicate that the 
  user would like to use the "remember me" functionality provided by Laravel.

If the login attempt is successful, service will redirect you to the `/` URI. 
If the login request was an XHR request, a 200 HTTP response will be returned.

If the request was not successful, the user will be redirected back to the 
login screen and the validation errors will be available to you via the 
shared `$errors` Blade template variable. Or, in the case of an XHR request, 
the validation errors will be returned with the 422 HTTP response.

## Email Verification

You may wish for users to **periodically re-verify** their email address before 
they continue accessing your application.

### Verification Strategy

You may configure a strategy of email verification for every user personally.
`MustVerifyEmailWithOtp` interface has `shouldVerifyEmail` method, and you 
may implement it respecting user properties, e.g. user roles, etc. 

```php
use Codewiser\Otp\Contracts\MustVerifyEmailWithOtp;
use Codewiser\Otp\Traits\MustVerifyEmailWithOtp as HasOtp;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements MustVerifyEmailWithOtp 
{
    use HasOtp;
    
    public function shouldVerifyEmail() : bool
    {
        if (! $this->hasVerifiedEmail()) {
            return true;
        }
    
        if ($this->roles->contains('admin')) {
            // Admin should reverify their email every time.
            return true;
        }
    
        if ($this->roles->contains('manager')) {
            // Manager should reverify their email every month.
            return now()
                ->diffAsCarbonInterval($this->email_verified_at)
                ->greaterThan(
                    new \DateInterval('P1M')
                );
        }
        
        return false;
    }
}
```

The otp process will be invoked no more often than once during user session.

### Customizing View

All of the view's rendering logic may be customized using the
appropriate methods available via the `\Codewiser\Otp\Otp` class.
Typically, you should call this method from the boot method
of your application's `App\Providers\OtpServiceProvider` class.

```php
use Codewiser\Otp\Otp;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Otp::verifyEmailView('otp::verify-email');
}
```

Service will take care of defining the route that displays this view when a 
user is redirected to the `/otp/email` endpoint by 
`Codewiser\Otp\Http\Middleware\EnsureOtpIsPassed` middleware.

`Verify email` template should include:
* a form that makes a POST request to `/otp/email`.
  This endpoint sends a notification with one-time-password to a current user.
* a form that makes a PUT request to `/otp/email`.
  This endpoint expects a string `code` to verify.

Every time user successfully verified the email, the service updates 
`email_verified_at` attribute.

### Protecting Routes

To specify that a route or group of routes requires that the user has 
verified their email address, you should attach `EnsureOtpIsPassed` 
middleware to the route. This middleware extends the built-in Laravel's
`verified` middleware.

```php
use Codewiser\Otp\Http\Middleware\EnsureOtpIsPassed;

Route::get('/dashboard', function () {
    // ...
})->middleware(EnsureOtpIsPassed::class);
```

## Rate limiting

Predefined `otp` routes are protected with `throttle` middleware using names
mentioned in the example below.

Since the login routes are used by guests, key your limits by the submitted
`email` (or the request IP) instead of the authenticated user id.

```php
<?php

namespace App\Providers;

use Codewiser\Otp\RateLimiter\OtpRateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    // Named RateLimiter for issuing otp code
    RateLimiter::for(OtpRateLimiter::ISSUE, function(Request $request) {
    
        $throttleKey =
                $request->user()?->id.'|'.
                $request->input('email').'|'.
                $request->ip();
    
        return [
            Limit::perMinute(1)->by('minute:'.$throttleKey),
            Limit::perDay(15)->by('day:'.$throttleKey),
        ];
    });

    // Named RateLimiter for verifying otp code (bruteforce protection)
    RateLimiter::for(OtpRateLimiter::VERIFY, function(Request $request)  {
    
        $throttleKey =
                $request->user()?->id.'|'.
                $request->input('email').'|'.
                $request->ip();
    
        return [
            Limit::perDay(30)->by($throttleKey)
        ];
    });
}
```

## Customization

You may change the published blade templates (see `resources/views/vendor/otp`), 
or you may register custom views. You may register a custom function to 
generate otp codes. And you may register a custom function for composing a 
notification.

```php
use Codewiser\Otp\Notifications\OtpNotification;
use Codewiser\Otp\Otp;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    // Login custom view
    Otp::loginView('otp.login');
    
    // Verify email custom view
    Otp::verifyEmailView('otp.verify-email');

    // Callback to generate one-time-password.
    Otp::newCodeUsing(fn() => rand(1000, 9999));
    
    OtpNotification::toMailUsing(function(object $notifiable, string $code) {
        // Custom notification.
    });
}
```