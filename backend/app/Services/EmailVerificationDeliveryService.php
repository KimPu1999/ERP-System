<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
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

        if ($mailer === 'log') {
            // The log transport is intentionally useful for local verification:
            // Laravel renders the real signed notification into laravel.log, but
            // nothing is sent to Gmail or another external inbox.
            try {
                $user->sendEmailVerificationNotification();
                Log::notice('Email verification was written to the local log transport.', $context + ['status' => 'logged_only']);
                return ['status' => 'logged_only', 'message' => 'The verification message was written to the backend log only. It was not sent to an external inbox.'];
            } catch (Throwable $exception) {
                Log::warning('Email verification log delivery failed.', $context + ['status' => 'failed', 'exception' => $exception::class]);
                return ['status' => 'failed', 'message' => 'The local verification message could not be written. Check the backend log and try again.'];
            }
        }

        if ($mailer === 'array') {
            Log::notice('Email verification was not externally delivered.', $context + ['status' => 'not_delivered', 'reason' => 'array_transport']);
            return ['status' => 'not_delivered', 'message' => 'Email delivery is not configured for external delivery. Configure SMTP or a managed mail provider first.'];
        }

        if ($mailer === 'smtp' && (! filled(config('mail.mailers.smtp.host')) || ! filled(config('mail.from.address')))) {
            Log::warning('Email verification delivery is not configured.', $context + ['status' => 'failed', 'reason' => 'missing_smtp_configuration']);
            return ['status' => 'failed', 'message' => 'Email delivery is not configured. Check the SMTP host and sender address.'];
        }

        try {
            if ((bool) config('mail.verification_queue', false)) {
                $user->notify(new QueuedVerifyEmail());
                Log::info('Email verification was queued for the configured mail transport.', $context + ['status' => 'queued', 'queue' => 'mail']);
                return ['status' => 'queued', 'message' => 'The verification message was queued for delivery.'];
            }

            $user->sendEmailVerificationNotification();
            Log::info('Email verification was accepted by the configured mail transport.', $context + ['status' => 'accepted_by_configured_mailer']);
            return ['status' => 'accepted_by_configured_mailer', 'message' => 'The verification message was accepted by the configured mail transport. Inbox delivery is not verified.'];
        } catch (Throwable $exception) {
            Log::warning('Email verification delivery failed.', $context + ['status' => 'failed', 'exception' => $exception::class]);
            return ['status' => 'failed', 'message' => 'The email provider rejected the verification message. Check the mail configuration and try again.'];
        }
    }
}
