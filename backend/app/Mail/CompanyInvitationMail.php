<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class CompanyInvitationMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public readonly Invitation $invitation, public readonly string $token) {}
    public function build(): static { return $this->subject('You have been invited to Enterprise ERP Cloud')->view('mail.company-invitation', ['invitation' => $this->invitation, 'token' => $this->token, 'frontendUrl' => config('app.frontend_url', env('FRONTEND_URL'))]); }
}
