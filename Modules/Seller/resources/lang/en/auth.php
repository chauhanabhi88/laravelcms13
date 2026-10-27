<?php

return [
    'login' => [
        'title' => 'Sign in to your supplier panel',
        'subtitle' => 'Manage your catalog, orders and payments in one place.',
        'login' => 'Email or mobile number',
        'mobile' => 'Mobile number',
        'password' => 'Password',
        'otp' => 'One-time code',
        'remember' => 'Keep me signed in',
        'forgot' => 'Forgot password?',
        'submit' => 'Sign in',
        'with_password' => 'Use password',
        'with_otp' => 'Use one-time code',
        'send_otp' => 'Send code',
        'resend_otp' => 'Send a new code',
        'no_account' => 'New to selling with us?',
        'register_link' => 'Create a supplier account',
    ],

    'register' => [
        'title' => 'Create your supplier account',
        'subtitle' => 'It takes a couple of minutes. Keep your GSTIN and bank details handy for the next step.',
        'name' => 'Your full name',
        'email' => 'Business email',
        'phone' => 'Mobile number',
        'phone_hint' => 'We will send a one-time code to verify this number.',
        'password' => 'Create password',
        'password_confirmation' => 'Confirm password',
        'accept_terms' => 'I agree to the terms of use and the privacy notice, and consent to my details being used to verify and run my seller account.',
        'submit' => 'Continue',
        'have_account' => 'Already registered?',
        'login_link' => 'Sign in',
    ],

    'verify' => [
        'title' => 'Verify your mobile number',
        'subtitle' => 'Enter the 6-digit code sent to +91 :phone.',
        'otp' => 'Verification code',
        'submit' => 'Verify and create account',
        'resend' => 'Resend code',
        'resend_in' => 'Resend in :seconds s',
        'change' => 'Change details',
    ],

    'forgot' => [
        'title' => 'Reset your password',
        'subtitle' => 'Enter the email of your supplier login and we will send you a reset link.',
        'email' => 'Email',
        'submit' => 'Send reset link',
        'back' => 'Back to sign in',
    ],

    'reset' => [
        'title' => 'Choose a new password',
        'email' => 'Email',
        'password' => 'New password',
        'password_confirmation' => 'Confirm new password',
        'submit' => 'Update password',
    ],

    'reset_mail' => [
        'subject' => 'Reset your supplier panel password — :app',
        'intro' => 'We received a request to reset the password of your supplier login.',
        'action' => 'Reset password',
        'expiry' => 'This link expires in :minutes minutes.',
        'ignore' => 'If you did not ask for this, you can ignore this email; your password stays the same.',
    ],

    'messages' => [
        'invalid_credentials' => 'These details do not match a supplier login.',
        'invalid_otp' => 'The code is incorrect or has expired.',
        'throttled' => 'Too many attempts. Please try again in :seconds seconds.',
        'account_disabled' => 'This login has been disabled. Please contact the account owner.',
        'otp_sent' => 'We sent a 6-digit code to +91 :phone.',
        'login_otp_sent' => 'If this number has a supplier login, a code is on its way.',
        'otp_wait' => 'Please wait :seconds seconds before asking for another code.',
        'otp_failed' => 'We could not send the code right now. Please try again shortly.',
        'registration_expired' => 'Your registration session expired. Please enter your details again.',
        'already_registered' => 'An account with this email or mobile already exists. Please sign in.',
        'registered' => 'Welcome aboard! Let\'s set up your business.',
        'logged_out' => 'You have been signed out.',
        'reset_link_sent' => 'If that email has a supplier login, a reset link is on its way.',
        'password_reset' => 'Your password has been updated. Please sign in.',
    ],
];
