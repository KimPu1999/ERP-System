<?php
return ['billing'=>['provider'=>env('BILLING_PROVIDER','manual'),'trial_days'=>(int)env('BILLING_TRIAL_DAYS',14),'grace_days'=>(int)env('BILLING_GRACE_DAYS',7),'webhook_secrets'=>['default'=>env('BILLING_WEBHOOK_SECRET')]]];
