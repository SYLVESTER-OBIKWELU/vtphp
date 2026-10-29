<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SendEmailVerificationRequest;
use App\Http\Resources\UserResource;
use App\Mail\VerifyEmail;
use App\Models\User;
use VtPhp\Exceptions\AuthorizationException;
use VtPhp\Http\JsonResponse;
use VtPhp\Http\Request;
use VtPhp\Mail\Mailer;
use VtPhp\Routing\UrlSigner;

final class EmailVerificationController
{
    public function send(SendEmailVerificationRequest $request, Mailer $mailer): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        // Respond identically whether or not the email is registered or
        // already verified, to avoid leaking account state.
        if ($user !== null && !$user->hasVerifiedEmail()) {
            $mailer->send(new VerifyEmail($user, $user->verificationUrl()));
        }

        return response()->json(['message' => 'If that email address is registered and unverified, a verification link has been sent.']);
    }

    public function verify(User $user, string $hash, Request $request, UrlSigner $signer): UserResource
    {
        if (!$signer->hasValidSignature($request->psr()) || !hash_equals($user->verificationHash(), $hash)) {
            throw new AuthorizationException('Invalid or expired email verification link.');
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return UserResource::make($user);
    }
}
