<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitation extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(public string $token, public string $organizationName)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(__('Organization invitation'))->line(__('You have been invited to :organization.', ['organization' => $this->organizationName]))
            ->line(__('Sign in or register with this email address and verify it. This link expires after 48 hours and can only be used once.'))
            ->action(__('Review invitation'), route('invitations.accept', ['token' => $this->token]));
    }
}
