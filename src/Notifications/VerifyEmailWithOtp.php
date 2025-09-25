<?php

namespace Codewiser\Otp\Notifications;

use Closure;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailWithOtp extends Notification
{
    /**
     * The callback that should be used to build the mail message.
     *
     * @var (Closure(mixed, string): MailMessage|Mailable)|null
     */
    public static ?Closure $toMailCallback = null;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $otp)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $this->otp);
        }

        return (new MailMessage)
            ->subject(__('One time password'))
            ->line(__('Your one time password for accessing the application is:'))
            ->line("**$this->otp**");
    }

    /**
     * Set a callback that should be used when building the notification mail message.
     *
     * @param  Closure(mixed, string): (MailMessage|Mailable)  $callback
     *
     * @return void
     */
    public static function toMailUsing($callback): void
    {
        static::$toMailCallback = $callback;
    }
}
