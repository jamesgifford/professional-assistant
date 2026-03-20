<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twilio\Security\RequestValidator;

class VerifyTwilioSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local', 'testing')) {
            return $next($request);
        }

        $authToken = config('services.twilio.auth_token');

        if (empty($authToken)) {
            abort(500, 'Twilio auth token not configured.');
        }

        $validator = new RequestValidator($authToken);

        $signature = $request->header('X-Twilio-Signature', '');
        $url = $request->fullUrl();
        $params = $request->all();

        if (! $validator->validate($signature, $url, $params)) {
            abort(403, 'Invalid Twilio signature.');
        }

        return $next($request);
    }
}
