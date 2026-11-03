<?php

return [
    'platform_mfa_required' => (bool) env('PLATFORM_MFA_REQUIRED', true),
    'max_export_rows' => (int) env('SECURITY_MAX_EXPORT_ROWS', 10000),
];
