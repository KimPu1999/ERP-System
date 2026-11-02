<?php

namespace App\Mail;

use App\Models\PlatformAdminInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class PlatformAdminInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly PlatformAdminInvitation $invitation, public readonly string $token) {}

    public function build(): static
    {
        return $this->subject('Platform Control Center invitation')
            ->view('mail.platform-admin-invitation', [
                'invitation' => $this->invitation,
                'token' => $this->token,
                'frontendUrl' => config('app.frontend_url', env('FRONTEND_URL')),
            ]);
    }
}
