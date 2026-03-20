<?php

declare (strict_types=1);
namespace Illuminate\Auth\Notifications;

use Illuminate\Notifications\Messages\Mail_Message;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;
class Reset_Password extends Notification
{
    /**
     * The callback that should be used to create the reset password URL.
     *
     * @var (\Closure(mixed, string): string)|null
     */
    public static $create_url_callback;
    /**
     * The callback that should be used to build the mail message.
     *
     * @var (\Closure(mixed, string): \Illuminate\Notifications\Messages\MailMessage|\Illuminate\Contracts\Mail\Mailable)|null
     */
    public static $to_mail_callback;
    /**
     * Create a notification instance.
     *
     * @param  string  $token
     */
    public function __construct(
        /**
         * The password reset token.
         */
        #[\Sensitive_Parameter]
        public $token
    )
    {
    }
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
        if (static::$to_mail_callback) {
            return call_user_func(static::$to_mail_callback, $notifiable, $this->token);
        }
        return $this->build_mail_message($this->reset_url($notifiable));
    }
    /**
     * Get the reset password notification mail message for the given URL.
     *
     * @param  string  $url
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    protected function build_mail_message($url)
    {
        return (new Mail_Message())->subject(Lang::get('Reset Password Notification'))->line(Lang::get('You are receiving this email because we received a password reset request for your account.'))->action(Lang::get('Reset Password'), $url)->line(Lang::get('This password reset link will expire in :count minutes.', ['count' => config('auth.passwords.' . config('auth.defaults.passwords') . '.expire')]))->line(Lang::get('If you did not request a password reset, no further action is required.'));
    }
    /**
     * Get the reset URL for the given notifiable.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function reset_url($notifiable): string|\Illuminate\Contracts\Routing\Url_Generator
    {
        if (static::$create_url_callback) {
            return call_user_func(static::$create_url_callback, $notifiable, $this->token);
        }
        return url(route('password.reset', ['token' => $this->token, 'email' => $notifiable->get_email_for_password_reset()], false));
    }
    /**
     * Set a callback that should be used when creating the reset password button URL.
     *
     * @param  \Closure(mixed, string): string  $callback
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