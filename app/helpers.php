<?php

if (! function_exists('email_domain')) {
    /**
     * Extract the domain part from an email address.
     * email_domain('juan@acme.com') → 'acme.com'
     */
    function email_domain(string $email): string
    {
        return substr(strrchr($email, '@'), 1);
    }
}

if (! function_exists('mask_email')) {
    /**
     * Partially mask an email for safe display in logs or UI.
     * mask_email('juan@acme.com') → 'ju**@acme.com'
     */
    function mask_email(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $visible = min(2, strlen($local));

        return substr($local, 0, $visible).str_repeat('*', max(0, strlen($local) - $visible)).'@'.$domain;
    }
}

if (! function_exists('api_success')) {
    /**
     * Consistent JSON success envelope.
     */
    function api_success(mixed $data, int $status = 200): \Illuminate\Http\JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }
}

if (! function_exists('api_error')) {
    /**
     * Consistent JSON error envelope.
     */
    function api_error(string $message, int $status = 400): \Illuminate\Http\JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
