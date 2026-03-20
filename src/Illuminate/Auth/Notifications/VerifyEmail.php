<?php

declare (strict_types=1);
namespace Illuminate\Auth\Notifications;

use Illuminate\Notifications\Messages\Mail_Message;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\URL;
class Verify_Email extends Notification
{
    /**
     * The callback that should be used to create the verify email URL.
     *
     * @var \Closure|null
     */
    public static $create_url_callback;
    /**
     * The callback that should be used to build the mail message.
     *
     * @var (\Closure(mixed, string): \Illuminate\Notifications\Messages\MailMessage|\Illuminate\Contracts\Mail\Mailable)|null
     */
    public static $to_mail_callback;
    /**
     * Get the notification's channels.
     *
     * @param  mixed  $notifiable
     * @return array|string
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }
    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function to_mail($notifiable)
    {
        $verification_url = $this->verification_url($notifiable);
        if (static::$to_mail_callback) {
            return call_user_func(static::$to_mail_callback, $notifiable, $verification_url);
        }
        return $this->build_mail_message($verification_url);
    }
    /**
     * Get the verify email notification mail message for the given URL.
     *
     * @param  string  $url
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    protected function build_mail_message($url)
    {
        return (new Mail_Message())->subject(Lang::get('Verify Email Address'))->line(Lang::get('Please click the button below to verify your email address.'))->action(Lang::get('Verify Email Address'), $url)->line(Lang::get('If you did not create an account, no further action is required.'));
    }
    /**
     * Get the verification URL for the given notifiable.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function verification_url($notifiable)
    {
        if (static::$create_url_callback) {
            return call_user_func(static::$create_url_callback, $notifiable);
        }
        return URL::temporary_signed_route('verification.verify', Carbon::now()->add_minutes(Config::get('auth.verification.expire', 60)), ['id' => $notifiable->get_key(), 'hash' => hash('sha256', (string) $notifiable->get_email_for_verification())]);
    }
    /**
     * Set a callback that should be used when creating the email verification URL.
     *
     * @param  \Closure  $callback
     */
    public static function create_url_using($callback): void
    {
        static::$create_url_callback = $callback;
    }
    /**
     * Set a callback that should be used when building the notification mail message.
     *
     * @param  \Closure(mixed, string): (\Illuminate\Notifications\Messages\MailMessage|\Illuminate\Contracts\Mail\Mailable)  $callback
     */
    public static function to_mail_using($callback): void
    {
        static::$to_mail_callback = $callback;
    }
}