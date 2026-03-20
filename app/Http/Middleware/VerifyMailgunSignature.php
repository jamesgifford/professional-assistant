<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMailgunSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local', 'testing')) {
            return $next($request);
        }

        $signingKey = config('services.mailgun.webhook_signing_key');

        if (empty($signingKey)) {
            abort(500, 'Mailgun webhook signing key not configured.');
        }

        $timestamp = $request->input('timestamp', '');
        $token = $request->input('token', '');
        $signature = $request->input('signature', '');

        if (empty($timestamp) || empty($token) || empty($signature)) {
            abort(403, 'Missing Mailgun signature parameters.');
        }

        $expectedSignature = hash_hmac('sha256', $timestamp.$token, $signingKey);

        if (! hash_equals($expectedSignature, $signature)) {
            abort(403, 'Invalid Mailgun signature.');
        }

        return $next($request);
    }
}
