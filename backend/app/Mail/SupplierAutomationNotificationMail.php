<?php

namespace App\Mail;

use App\Models\ErpNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class SupplierAutomationNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ErpNotification $notification) {}

    public function build(): static
    {
        return $this->subject($this->notification->title)
            ->view('mail.supplier-automation-notification', ['notification' => $this->notification]);
    }
}
