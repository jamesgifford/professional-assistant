<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Svix\Exception\WebhookVerificationException;
use Svix\Webhook;
use Symfony\Component\HttpFoundation\Response;

class VerifyResendSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local', 'testing')) {
            return $next($request);
        }

        $secret = config('services.resend.webhook_secret');

        if (empty($secret)) {
            abort(500, 'Resend webhook secret not configured.');
        }

        try {
            $webhook = new Webhook($secret);
            $webhook->verify($request->getContent(), [
                'svix-id' => $request->header('svix-id', ''),
                'svix-timestamp' => $request->header('svix-timestamp', ''),
                'svix-signature' => $request->header('svix-signature', ''),
            ]);
        } catch (WebhookVerificationException $e) {
            Log::warning('Resend webhook signature verification failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            abort(401, 'Invalid webhook signature.');
        }

        return $next($request);
    }
}
