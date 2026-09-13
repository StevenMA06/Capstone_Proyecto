<?php

return [
    'two_factor_enabled' => (bool) env('ACCESS_TWO_FACTOR_ENABLED', false),
    'code_minutes' => max(1, min(10, (int) env('ACCESS_CODE_MINUTES', 5))),
    'challenge_minutes' => 15,
    'code_attempts' => 5,
    'resend_seconds' => 60,
    'emails_per_window' => 5,
];
