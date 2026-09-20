<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Localized password reset notification.
 *
 * The link carries the broker token and the email address as query parameters
 * so a single-page frontend can post them back to
 * POST /api/auth/password/reset.
 */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token)
    {
    }

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage())
            ->subject(__('mail.reset_subject'))
            ->greeting(__('mail.greeting', ['name' => (string) ($notifiable->username ?? '')]))
            ->line(__('mail.reset_line'))
            ->action(__('mail.reset_action'), $this->resetUrl($notifiable))
            ->line(__('mail.reset_expiry', ['minutes' => $minutes]))
            ->salutation(__('mail.salutation'));
    }

    private function resetUrl(mixed $notifiable): string
    {
        $email = method_exists($notifiable, 'getEmailForPasswordReset')
            ? (string) $notifiable->getEmailForPasswordReset()
            : '';

        return rtrim((string) config('noircat.frontend_url'), '/').'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $email,
        ]);
    }
}
