# One time passwords for Laravel

In a very simple case, our Laravel application authenticates users by login 
and password. Sometimes we force our application to verify users' emails.

This package brings two optional services:

* Allow user to authenticate with one-time-password (via email, without 
  password).
* Force user to periodically revalidate an email.

The authentication process will be:

* user provides an email
* application sends a notification with one time password
* user affirms authentication providing this password

> Use `/otp/login` route to authenticate users.

The revalidation process will be:

* user signs-in with login and password
* application sends a notification with one time password
* user affirms authentication providing this password

> Apply `EnsureOtpIsPassed` middleware to revalidate emails.

![otp](otp.png)

## Installation

Install service. Publish service provider, example views and translation files.

```php
composer require codewiser/otp-authenticate

php artisan vendor:publish --tag=otp
```

Register `\App\Providers\OtpServiceProvider` to `bootstrap/providers.php` file.

## Implementation

Apply the `MustVerifyEmailWithOtp` contract and, optionally, the 
`MustVerifyEmailWithOtp` trait to a `User` model. This contract extends the 
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

Register `Codewiser\Otp\Otp` in your  application's
`App\Providers\OtpServiceProvider` class.

```php
use Codewiser\Otp\Otp;
use Illuminate\Support\Facades\Auth;

/**
 * Register any application services.
 */
public function register(): void
{
    $this->app->singleton(Otp::class, fn($app) => new Otp(
        Auth::createUserProvider('users'),
        Auth::guard('web')
    ));
}
```

## Authentication

`Otp` service uses `UserProvider` to search users by their credentials. The 
name of "email" database column should match the `email` configuration value 
defined within your application's `fortify` configuration file.

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

`Login` template should include a form that makes a POST request to 
`/otp/login`.

#### Request one-time-password
  
When form sends a string `email` and `send` flag, the endpoint sends a 
notification with one-time-password to a given email.

If the send one-time-password request was successful, service will redirect 
back to the `/otp/login` route so that the user can log in with 
one-time-password. In addition, a status session variable will be set so 
that you may display the successful status on your login screen:

```php
@if (session('status'))
    <div class="mb-4 font-medium text-sm text-green-600">
        {{ session('status') }}
    </div>
@endif
```

#### Verify one-time-password

When form sends a string `email` and a `code`, the endpoint will verify code 
and authenticate user. In addition, a boolean `remember` field may be 
provided to indicate that the user would like to use the "remember me" 
functionality provided by Laravel.

If the login attempt is successful, service will redirect you to the URI 
configured via the `home` configuration option within your application's 
`fortify` configuration file. If the login request was an XHR request, a 200 
HTTP response will be returned.

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
            // Admin should verify their email for every session.
            return true;
        }
    
        if ($this->roles->contains('manager')) {
            // Manager should verify their email every month.
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

Service will take care of defining the route that displays this view when a
user is redirected to the `/otp/email` endpoint by
`EnsureOtpIsPassed` middleware.

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

`Verify email` template should include a form that makes a POST request to 
`/otp/email`.

#### Request one-time-password

When form sends a `send` flag, the endpoint sends a notification with 
one-time-password to a current user.

If the send one-time-password request was successful, service will redirect
back to the `/otp/email` route so that the user can verify email with
one-time-password. In addition, a status session variable will be set so
that you may display the successful status on your login screen:

```php
@if (session('status'))
    <div class="mb-4 font-medium text-sm text-green-600">
        {{ session('status') }}
    </div>
@endif
```

#### Verify one-time-password

When form sends a string `code`, the endpoint will verify it and update 
`email_verified_at` then.

If the request was not successful, the user will be redirected back to the
verify email screen and the validation errors will be available to you via the
shared `$errors` Blade template variable. Or, in the case of an XHR request,
the validation errors will be returned with the 422 HTTP response.

### Protecting Routes

Route middleware may be used to force users to re-verify email to access a 
given route. Service includes a `verified.otp` middleware alias, which is an 
alias for the `Codewiser\Otp\Http\Middleware\EnsureOtpIsPassed` middleware 
class. All you need to do is attach the `verified.otp` middleware to a route 
definition. 

`verified.otp` middleware extends the built-in Laravel's `verified` 
middleware. `verified.otp` handles only authenticated web requests, if 
`User` model implemented `MustVerifyEmailWithOtp` contract. Otherwise, 
request will be delegated to the parent `verified` middleware.

```php
Route::get('/dashboard', function () {
    // ...
})->middleware(['auth', 'verified.otp']);
```

If a user with outdated email attempts to access a route that has been assigned 
this middleware, they will automatically be redirected to the `otp.email` 
named route.

## Rate limiting

Predefined `otp` routes are protected with `throttle` middleware using names
mentioned in the example below.

Since the login route is used by guests, key your limits by the submitted
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
    
        $throttleKey = $request->user()?->id.'|'.$request->input('email');
    
        return [
            Limit::perMinute(1)->by('minute:'.$throttleKey),
            Limit::perDay(15)->by('day:'.$throttleKey),
        ];
    });

    // Named RateLimiter for verifying otp code (bruteforce protection)
    RateLimiter::for(OtpRateLimiter::VERIFY, function(Request $request)  {
    
        $throttleKey = $request->user()?->id.'|'.$request->input('email');
    
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