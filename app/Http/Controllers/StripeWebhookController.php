<?php

namespace App\Http\Controllers;

use App\Domain\Payments\Actions\HandlePaymentWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException) {
            return response('Invalid signature', 400);
        } catch (\UnexpectedValueException) {
            return response('Invalid payload', 400);
        }

        try {
            (new HandlePaymentWebhook)->execute($event);
        } catch (\Throwable) {
            return response('Webhook processing failed', 500);
        }

        return response('', 200);
    }
}
