<?php

return [
    // MFA can be disabled only in an explicitly local or test, debug-enabled process.
    // Every other environment fails closed to required MFA.
    'platform_mfa_required' => !(
        in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)
        && filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN)
        && ! filter_var(env('PLATFORM_MFA_REQUIRED', true), FILTER_VALIDATE_BOOLEAN)
    ),
    'max_export_rows' => (int) env('SECURITY_MAX_EXPORT_ROWS', 10000),
];
