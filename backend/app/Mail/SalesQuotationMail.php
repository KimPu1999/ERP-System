<?php
namespace App\Mail;
use App\Models\SalesQuotation;
use Illuminate\Mail\Mailable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
final class SalesQuotationMail extends Mailable { use Queueable, SerializesModels; public function __construct(public readonly SalesQuotation $quotation, public readonly string $messageText) {} public function build(): static { return $this->subject('Sales quotation '.$this->quotation->number)->view('mail.sales-quotation',['quotation'=>$this->quotation,'messageText'=>$this->messageText]); } }
