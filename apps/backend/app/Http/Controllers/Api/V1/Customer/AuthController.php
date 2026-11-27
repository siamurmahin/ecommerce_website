<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Domain\Customer\Actions\MergeCustomers;
use App\Domain\Customer\Models\Customer;
use App\Domain\Customer\Support\OtpService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SendEmailOtpRequest;
use App\Http\Requests\Api\V1\SendOtpRequest;
use App\Http\Requests\Api\V1\VerifyEmailOtpRequest;
use App\Http\Requests\Api\V1\VerifyOtpRequest;
use App\Http\Resources\V1\CustomerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Sign-in with a one-time code by SMS (phone) or email, and adding the other one to an account later.
 * The storefront server keeps the token in an httpOnly cookie.
 */
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

        return $this->signIn($customer);
    }

    /**
     * Emails a 6-digit code to sign in with.
     *
     * @response array{resend_after: int, is_new: bool}
     */
    public function sendEmailOtp(SendEmailOtpRequest $request, OtpService $otp): JsonResponse
    {
        $email = $request->validated('email');
        $customer = Customer::findByVerifiedEmail($email);

        if ($customer?->is_blocked) {
            throw ValidationException::withMessages(['email' => 'এই ইমেইলে লগইন করা যাচ্ছে না। সাহায্যের জন্য হটলাইনে কল করুন।']);
        }

        return $this->mailCode($otp, $email) ?? response()->json([
            'resend_after' => OtpService::RESEND_AFTER,
            /** New addresses are asked for a name on the code screen. */
            'is_new' => ! $customer,
        ]);
    }

    /**
     * Checks the emailed code and signs in, creating an account (without a phone) on first sign-in.
     *
     * @response array{token: string, customer: CustomerResource}
     */
    public function verifyEmail(VerifyEmailOtpRequest $request, OtpService $otp): JsonResponse
    {
        $data = $request->validated();

        if (! $otp->verifyEmail($data['email'], $data['code'])) {
            throw ValidationException::withMessages(['code' => 'কোডটি সঠিক নয় বা মেয়াদ শেষ।']);
        }

        $customer = Customer::findByVerifiedEmail($data['email'])
            ?? tap(new Customer(['email' => $data['email'], 'name' => $data['name'] ?? null]), fn (Customer $c) => $c->forceFill(['email_verified_at' => now()])->save());
        if ($customer->is_blocked) {
            throw ValidationException::withMessages(['email' => 'এই ইমেইলে লগইন করা যাচ্ছে না।']);
        }
        $customer->update(['last_login_at' => now(), 'name' => $customer->name ?? ($data['name'] ?? null)]);

        return $this->signIn($customer);
    }

    /**
     * Signed in: emails a code to an address the customer wants to sign in with later.
     *
     * @response array{resend_after: int}
     */
    public function sendEmailChangeOtp(SendEmailOtpRequest $request, OtpService $otp): JsonResponse
    {
        $email = $request->validated('email');
        $this->ensureEmailFree($email, $request->user());

        return $this->mailCode($otp, $email) ?? response()->json(['resend_after' => OtpService::RESEND_AFTER]);
    }

    /** Signed in: confirms the emailed code and makes the address a way to sign in. */
    public function confirmEmail(VerifyEmailOtpRequest $request, OtpService $otp): CustomerResource
    {
        $data = $request->validated();
        $customer = $request->user();

        if (! $otp->verifyEmail($data['email'], $data['code'])) {
            throw ValidationException::withMessages(['code' => 'কোডটি সঠিক নয় বা মেয়াদ শেষ।']);
        }
        $this->ensureEmailFree($data['email'], $customer);
        $customer->forceFill(['email' => $data['email'], 'email_verified_at' => now()])->save();

        return new CustomerResource($customer->load('addresses.district', 'addresses.thana'));
    }

    /**
     * Signed in without a phone: confirms the SMS code (sent by `otp`) and adds the number. If that number already
     * has an account (a guest one from earlier orders, or a phone sign-in), the two become one and a new token is
     * issued for it.
     *
     * @response array{token: string, customer: CustomerResource}
     */
    public function confirmPhone(VerifyOtpRequest $request, OtpService $otp, MergeCustomers $merge): JsonResponse
    {
        $data = $request->validated();
        $customer = $request->user();

        if ($customer->phone) {
            throw ValidationException::withMessages(['phone' => 'এই অ্যাকাউন্টে আগেই একটি নম্বর আছে।']);
        }
        if (! $otp->verify($data['phone'], $data['code'])) {
            throw ValidationException::withMessages(['code' => 'কোডটি সঠিক নয় বা মেয়াদ শেষ।']);
        }

        $existing = Customer::where('phone', $data['phone'])->first();
        if ($existing?->is_blocked) {
            throw ValidationException::withMessages(['phone' => 'এই নম্বর যুক্ত করা যাচ্ছে না। সাহায্যের জন্য হটলাইনে কল করুন।']);
        }
        $account = $existing ? $merge->handle($customer, $existing) : tap($customer)->update(['phone' => $data['phone']]);

        return $this->signIn($account);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    private function signIn(Customer $customer): JsonResponse
    {
        $token = $customer->createToken('storefront', ['customer'], now()->addDays(60))->plainTextToken;

        return response()->json(['token' => $token, 'customer' => new CustomerResource($customer->load('addresses.district', 'addresses.thana'))]);
    }

    /** Null once sent; a 503 when the mail server refused it. */
    private function mailCode(OtpService $otp, string $email): ?JsonResponse
    {
        try {
            $otp->sendEmail($email);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'এখন ইমেইল পাঠানো যাচ্ছে না, একটু পরে চেষ্টা করুন।'], 503);
        }

        return null;
    }

    private function ensureEmailFree(string $email, Customer $customer): void
    {
        $owner = Customer::findByVerifiedEmail($email);
        if ($owner && ! $owner->is($customer)) {
            throw ValidationException::withMessages(['email' => 'এই ইমেইল আরেকটি অ্যাকাউন্টে যুক্ত আছে।']);
        }
    }
}
