# One time passwords for Laravel

In a very simple case, our Laravel applications authenticate users by login 
and password. Sometimes we force our applications to verify users' emails.

This package brings two optional services:

* Allow user to authenticate with one-time-password (via email).
* Force user to periodically revalidate an email.

The authentication process will be:

* user provides an email (login)
* application sends an email with one time password
* user affirms authentication providing this email (login)

> Use `login-otp` route to authenticate users.

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

Customize the views published to `resources/views/vendor/otp`.

## Implementation

Apply the `MustVerifyEmailWithOtp` contract and the `MustVerifyEmailWithOtp`
trait to a `User` model. This contract and trait extend the well known
`MustVerifyEmail`.

_Models/User.php_

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

We need to instruct package how to return the "login" view.

All of the authentication view's rendering logic may be customized using the 
appropriate methods available via the `\Codewiser\Otp\OtpService` class. 
Typically, you should call this method from the boot method 
of your application's `App\Providers\OtpServiceProvider` class. Service will 
take care of defining the `/login/otp` route that returns this view:

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
* a form that makes a POST request to `/login/otp`. 
  This endpoint expects a string `email` and sends a 
  notification with one-time-password to a given email.
* a form that makes a PUT request to `/login/otp`.
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
they continue accessing your application. To get started, you should ensure 
that your `App\Models\User` class implements the 
`Codewiser\Otp\Contracts\MustVerifyEmailWithOtp` interface instead of 
`Illuminate\Contracts\Auth\MustVerifyEmail`.

### Verification Frequency

Configure the frequency of email verification in a service provider class.
Register `Codewiser\Otp\OtpVerify` in your application's 
`App\Providers\OtpServiceProvider` class.

```php
use Codewiser\Otp\OtpVerify;

/**
 * Register any application services.
 */
public function register(): void
{
    $this->app->singleton(OtpVerify::class,
        fn($app) => new OtpVerify('P1W')
    );
}
```

For example, if we define `new OtpVerify('P1M')`, users should
revalidate an email using otp at least once a month.

Passing `null` means that every authentication process is accompanied by an otp.

> Either way, the otp process will be invoked no more often than once
> during user session.

### Customizing View

All of the view's rendering logic may be customized using the
appropriate methods available via the `\Codewiser\Otp\OtpService` class.
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
user is redirected to the `/email/otp` endpoint by 
`Codewiser\Otp\Http\Middleware\EnsureOtpIsPassed` middleware.

`Verify email` template should include:
* a form that makes a POST request to `/email/otp`.
  This endpoint sends a notification with one-time-password to current user.
* a form that makes a PUT request to `/email/otp`.
  This endpoint expects a string `code` to verify.

Every time user successfully verified his email, the service updates 
`email_verified_at` attribute.

### Protecting Routes

To specify that a route or group of routes requires that the user has 
verified their email address, you should attach `EnsureOtpIsPassed` 
middleware to the route:

Email revalidation will
```php
use Codewiser\Otp\Http\Middleware\EnsureOtpIsPassed;

Route::get('/dashboard', function () {
    // ...
})->middleware([EnsureOtpIsPassed]);
```

## Rate limiting

Predefined `otp` routes are protected with `throttle` middleware using names
mentioned in the example below.

Since the login routes are used by guests, key your limits by the submitted
`email` (or the request IP) instead of the authenticated user id.

```php
<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    // Named RateLimiter for issuing otp code
    RateLimiter::for('otp-issue', fn(Request $request) => [
        Limit::perMinute(1)->by(
            'minute:'.$request->user()?->id.$request->input('email')
        ),
        Limit::perDay(15)->by(
            'day:'.$request->user()?->id.$request->input('email')
        ),
    ]);

    // Named RateLimiter for verifying otp code (bruteforce protection)
    RateLimiter::for('otp-verify', fn(Request $request) => [
        Limit::perDay(30)->by(
            $request->user()?->id.$request->input('email')
        )
    ]);
}
```

## Customization

You may change the published blade templates (see `resources/views/vendor/otp`), 
or you may register custom views. You may register a custom function to 
generate otp codes. And you may register a custom function for composing a 
notification.

```php
use Codewiser\Otp\Notifications\EmailWithOtp;
use Codewiser\Otp\Otp;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    // Callback to generate one-time-password.
    Otp::newCodeUsing(fn() => rand(1000, 9999));
    
    EmailWithOtp::toMailUsing(function(object $notifiable, string $code) {
        // Custom notification.
    });
}
```