<?php

namespace App\Http\Controllers;

use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OtpController extends Controller
{
    public function send(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate(['purpose' => ['sometimes', 'in:email_verification,account_confirmation']]);
        $otp->request($request->user(), $data['purpose'] ?? OtpService::PURPOSE_EMAIL);
        return response()->json(['message' => 'If eligible, a verification code has been sent.']);
    }

    public function verify(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'purpose' => ['sometimes', 'in:email_verification,account_confirmation'],
            'code' => ['required', 'digits:6'],
        ]);
        $otp->verify($request->user(), $data['code'], $data['purpose'] ?? OtpService::PURPOSE_EMAIL);
        return response()->json(['message' => 'Verification successful.']);
    }
}
