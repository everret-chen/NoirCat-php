<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Localized email verification notification.
 *
 * The signed link points at the API endpoint
 * GET /api/auth/email/verify/{id}/{hash}, which is intentionally public:
 * the signature proves mailbox ownership, and the hash binds the link to the
 * email address it was sent to.
 */
class VerifyEmailNotification extends VerifyEmail
{
    public function toMail(mixed $notifiable): MailMessage
    {
        $minutes = (int) config('auth.verification.expire', 1440);

        return (new MailMessage())
            ->subject(__('mail.verify_subject'))
            ->greeting(__('mail.greeting', ['name' => (string) ($notifiable->username ?? '')]))
            ->line(__('mail.verify_line'))
            ->action(__('mail.verify_action'), $this->verificationUrl($notifiable))
            ->line(__('mail.verify_expiry', ['minutes' => $minutes]))
            ->salutation(__('mail.salutation'));
    }
}
