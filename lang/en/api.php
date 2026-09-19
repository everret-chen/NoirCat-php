<?php

declare(strict_types=1);

return [
    'errors' => [
        // 1xxx authentication
        'UNAUTHENTICATED' => 'Authentication required.',
        'TOKEN_EXPIRED' => 'Your session has expired, please sign in again.',
        'INVALID_CREDENTIALS' => 'Invalid username or password.',
        'ACCOUNT_DISABLED' => 'This account has been disabled.',

        // 2xxx authorization
        'FORBIDDEN' => 'You are not allowed to perform this action.',
        'INSUFFICIENT_ROLE' => 'Your role does not grant enough permissions.',

        // 3xxx validation and resources
        'VALIDATION_FAILED' => 'The submitted data is invalid.',
        'RESOURCE_NOT_FOUND' => 'The requested resource was not found.',
        'ROUTE_NOT_FOUND' => 'The requested endpoint does not exist.',
        'METHOD_NOT_ALLOWED' => 'The HTTP method is not allowed for this endpoint.',

        // 4xxx business rules
        'BUSINESS_RULE_VIOLATION' => 'The operation violates a business rule.',

        // 5xxx system
        'RATE_LIMITED' => 'Too many requests, please try again later.',
        'SYSTEM_ERROR' => 'Internal server error.',
        'SERVICE_UNAVAILABLE' => 'Service temporarily unavailable.',
    ],

    'messages' => [
        'registered' => 'Registration successful.',
        'logged_in' => 'Signed in successfully.',
        'logged_out' => 'Signed out successfully.',
        'profile_updated' => 'Profile updated.',
        'avatar_updated' => 'Avatar updated.',
    ],
];
