<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:55',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        // Send verification email
        event(new Registered($user));

        // Generate access token
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 'Registered successfully. Please check your email to verify your account.');

    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->error('Invalid credentials.', 401);
        }

        if (! $user->hasVerifiedEmail()) {
            return $this->error('Email not verified..', 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

       return $this->success([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);

    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->success([], 'Logged out');
    }

    public function user(Request $request)
    {
        return $this->success($request->user(), 'User');
    }

    public function resendVerificationEmail(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $request->user()->sendEmailVerificationNotification();

        return $this->success([],'Verification email resent.');

    }

    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return $this->error('Invalid verification link.', 403);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->success([],'Email already verified.');
        }

        $user->markEmailAsVerified();
        return $this->success([],'Email verified successfully.');

    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $otp = random_int(100000, 999999);

        // Store OTP in cache for 5 minutes (300 seconds)
        Cache::put('otp_' . $request->email, $otp, now()->addSeconds(300));

        // Send email manually
        Mail::raw("Your OTP code is: $otp", function ($message) use ($request) {
            $message->to($request->email)
                ->subject('Password Reset OTP');
        });

        return $this->success([],'OTP sent to email');

    }

    public function verifyOtpAndReset(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|digits:6',
            'password' => 'required|confirmed|min:8',
        ]);

        $cachedOtp = Cache::get('otp_' . $request->email);

        if (!$cachedOtp || $cachedOtp != $request->otp) {
            return $this->error('Invalid or expired OTP', 403);

        }

        // OTP verified, reset password
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Invalidate OTP
        Cache::forget('otp_' . $request->email);

        return $this->success([],'Password reset successfully');

    }

}
