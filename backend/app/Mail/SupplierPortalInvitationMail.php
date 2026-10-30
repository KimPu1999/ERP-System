<?php
namespace App\Mail;
use App\Models\SupplierPortalInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
final class SupplierPortalInvitationMail extends Mailable {
    use Queueable, SerializesModels;
    public function __construct(public readonly SupplierPortalInvitation $invitation, public readonly string $token) {}
    public function build(): static { return $this->subject('Supplier Portal invitation')->view('mail.supplier-portal-invitation',['invitation'=>$this->invitation,'token'=>$this->token,'frontendUrl'=>config('app.frontend_url',env('FRONTEND_URL'))]); }
}
