<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

final class ResetPasswordNotification extends ResetPassword
{
    protected function resetUrl($notifiable): string
    {
        $path = $notifiable->is_platform_super_admin
            ? '/platform/reset-password'
            : '/reset-password';

        return rtrim((string) config('app.frontend_url'), '/') . $path . '?' . http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
