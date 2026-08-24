<?php

return [
    'login_as' => 'Login as',
    'return' => 'Return to admin',
    'confirm' => 'Open this account as :name? You can return to admin without logging out.',
    'banner' => 'You are viewing the system as :name.',
    'flash' => [
        'started' => 'You are now signed in as :name.',
        'stopped' => 'Returned to your admin account.',
        'enabled' => 'Support access unlocked.',
        'disabled' => 'Support access locked.',
    ],
    'errors' => [
        'disabled' => 'Login-as-user is turned off.',
        'already' => 'You are already viewing another account. Return to admin first.',
        'self' => 'You are already signed in as this user.',
        'not_active' => 'You are not viewing another account.',
        'impersonator_missing' => 'Could not return to admin. Please sign in again.',
        'invalid_otp' => 'Invalid access code.',
    ],
];
