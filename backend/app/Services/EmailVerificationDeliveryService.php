<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

final class EmailVerificationDeliveryService
{
    /** @return array{status: string, message: string} */
    public function send(User $user): array
    {
        $mailer = (string) config('mail.default');
        $context = [
            'user_id' => $user->id,
            'email_hash' => hash('sha256', strtolower((string) $user->email)),
            'mailer' => $mailer,
        ];

        if (in_array($mailer, ['log', 'array'], true)) {
            Log::notice('Email verification was not externally delivered.', $context + ['status' => 'not_delivered', 'reason' => 'non_delivery_transport']);
            return ['status' => 'not_delivered', 'message' => 'Email delivery is not configured for external delivery. Configure SMTP or a managed mail provider first.'];
        }

        if ($mailer === 'smtp' && (! filled(config('mail.mailers.smtp.host')) || ! filled(config('mail.from.address')))) {
            Log::warning('Email verification delivery is not configured.', $context + ['status' => 'failed', 'reason' => 'missing_smtp_configuration']);
            return ['status' => 'failed', 'message' => 'Email delivery is not configured. Check the SMTP host and sender address.'];
        }

        try {
            $user->sendEmailVerificationNotification();
            Log::info('Email verification was accepted by the configured mail transport.', $context + ['status' => 'accepted_by_configured_mailer']);
            return ['status' => 'accepted_by_configured_mailer', 'message' => 'The verification message was accepted by the configured mail transport. Inbox delivery is not verified.'];
        } catch (Throwable $exception) {
            Log::warning('Email verification delivery failed.', $context + ['status' => 'failed', 'exception' => $exception::class]);
            return ['status' => 'failed', 'message' => 'The email provider rejected the verification message. Check the mail configuration and try again.'];
        }
    }
}
