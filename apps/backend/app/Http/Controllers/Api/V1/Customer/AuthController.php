<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Domain\Customer\Models\Customer;
use App\Domain\Customer\Support\OtpService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SendOtpRequest;
use App\Http\Requests\Api\V1\VerifyOtpRequest;
use App\Http\Resources\V1\CustomerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Phone + one-time SMS code sign-in. The storefront server keeps the token in an httpOnly cookie. */
class AuthController extends Controller
{
    /**
     * Texts a 6-digit code to the phone.
     *
     * @response array{resend_after: int, is_new: bool}
     */
    public function sendOtp(SendOtpRequest $request, OtpService $otp): JsonResponse
    {
        $phone = $request->validated('phone');

        if (Customer::isBlocked($phone)) {
            throw ValidationException::withMessages(['phone' => 'এই নম্বরে লগইন করা যাচ্ছে না। সাহায্যের জন্য হটলাইনে কল করুন।']);
        }

        try {
            $wait = $otp->send($phone);
        } catch (RuntimeException) {
            return response()->json(['message' => 'এখন SMS পাঠানো যাচ্ছে না, একটু পরে চেষ্টা করুন।'], 503);
        }

        return response()->json([
            'resend_after' => $wait,
            /** New numbers are asked for a name on the code screen. */
            'is_new' => ! Customer::where('phone', $phone)->exists(),
        ]);
    }

    /**
     * Checks the code and signs in, creating the account on first sign-in.
     *
     * @response array{token: string, customer: CustomerResource}
     */
    public function verify(VerifyOtpRequest $request, OtpService $otp): JsonResponse
    {
        $data = $request->validated();

        if (! $otp->verify($data['phone'], $data['code'])) {
            throw ValidationException::withMessages(['code' => 'কোডটি সঠিক নয় বা মেয়াদ শেষ।']);
        }

        $customer = Customer::firstOrCreate(['phone' => $data['phone']], ['name' => $data['name'] ?? null]);
        if ($customer->is_blocked) {
            throw ValidationException::withMessages(['phone' => 'এই নম্বরে লগইন করা যাচ্ছে না।']);
        }
        $customer->update(['last_login_at' => now(), 'name' => $customer->name ?? ($data['name'] ?? null)]);

        $token = $customer->createToken('storefront', ['customer'], now()->addDays(60))->plainTextToken;

        return response()->json(['token' => $token, 'customer' => new CustomerResource($customer->load('addresses.district', 'addresses.thana'))]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }
}
