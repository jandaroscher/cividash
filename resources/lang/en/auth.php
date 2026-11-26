<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    // Custom auth messages
    'login_success' => 'Successfully logged in.',
    'two_factor_admin_login_required' => 'Two-factor authentication is set up or required for this account. Please sign in through the admin.',
    'password_login_disabled' => 'Password login is disabled. Please sign in via the central login.',
    'logout_success' => 'Successfully logged out.',
];
