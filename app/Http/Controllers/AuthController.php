<?php

namespace App\Http\Controllers;

use App\Models\ReferralLink;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AuthController extends Controller
{
    // ─── Step 1: Validate Fields & Send OTP ──────────────────────────────────

    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|email|unique:users,email',
            'phone'                 => 'required|digits_between:7,15|unique:users,phone_number',
            'company_name'          => 'nullable|string|max:255',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required',
        ], [
            'name.required'                  => 'Name is required.',
            'email.required'                 => 'Email address is required.',
            'email.email'                    => 'Please enter a valid email address.',
            'email.unique'                   => 'This email is already registered.',
            'phone.required'                 => 'Phone number is required.',
            'phone.digits_between'           => 'Phone number must be between 7 and 15 digits.',
            'phone.unique'                   => 'This phone number is already registered.',
            'password.required'              => 'Password is required.',
            'password.min'                   => 'Password must be at least 8 characters.',
            'password.confirmed'             => 'Passwords do not match.',
            'password_confirmation.required' => 'Please confirm your password.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $parentUserId = null;

        if ($request->ref_code) {
            $referral = ReferralLink::where('code', $request->ref_code)->first();

            if (!$referral || $referral->is_used || $referral->expires_at->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired referral code'
                ], 422);
            }

            $parentUserId = $referral->user_id;
        }

        // Generate 6-digit OTP
        $otp = rand(100000, 999999);

        // Store form data + OTP in session (OTP expires in 10 minutes)
        Session::put('register_pending', [
            'name'         => $request->name,
            'email'        => $request->email,
            'phone'        => $request->phone,
            'company_name' => $request->company_name,
            'password'     => $request->password,
            'parent_user_id' => $parentUserId,
            'ref_code'     => $request->ref_code,
            'referral_link_id' => $referral->id ?? null,
            'otp'          => $otp,
            'otp_expires'  => now()->addMinutes(10)->timestamp,
            'otp_attempts' => 0,
        ]);

        Log::info('otp ' . $otp);

        // Send OTP via Email
        // Mail::send('mail.otp', ['otp' => $otp, 'name' => $request->name], function ($message) use ($request) {
        //     $message->to($request->email)
        //         ->subject('Your Verification Code - NumbersSystem');
        // });

        return response()->json([
            'success' => true,
            'message' => 'OTP sent to ' . $request->email,
            'email'   => substr($request->email, 0, 3) . '***' . strstr($request->email, '@'),
        ]);
    }


    // ─── Resend OTP ───────────────────────────────────────────────────────────

    public function resendOtp(Request $request)
    {
        $pending = Session::get('register_pending');

        if (! $pending) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired. Please start over.',
            ], 400);
        }

        $otp = rand(100000, 999999);

        $pending['otp']          = $otp;
        $pending['otp_expires']  = now()->addMinutes(10)->timestamp;
        $pending['otp_attempts'] = 0;

        Session::put('register_pending', $pending);

        // Mail::send('mail.otp', ['otp' => $otp, 'name' => $pending['name']], function ($message) use ($pending) {
        //     $message->to($pending['email'])
        //         ->subject('Your New Verification Code - NumbersSystem');
        // });

        Log::info('new otp ' . $otp);

        return response()->json([
            'success' => true,
            'message' => 'New OTP sent successfully.',
        ]);
    }








    public function verifyOtpAndRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|digits:6',
        ], [
            'otp.required' => 'Please enter the OTP.',
            'otp.digits'   => 'OTP must be 6 digits.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $pending = Session::get('register_pending');

        if (! $pending) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired. Please start over.',
            ], 400);
        }

        $referral = null;
        $parentUserId = null;
        $referralLinkId = null;

        if ($pending['referral_link_id']) {
            $referral = ReferralLink::where('id', $pending['referral_link_id'])->first();

            if (!$referral || $referral->is_used || $referral->expires_at->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired referral code'
                ], 422);
            }

            $parentUserId = $referral->user_id;
            $referralLinkId = $referral->id;
        }

        // Check OTP expiry
        if (now()->timestamp > $pending['otp_expires']) {
            Session::forget('register_pending');
            return response()->json([
                'success' => false,
                'message' => 'OTP has expired. Please request a new one.',
                'expired' => true,
            ], 400);
        }

        // Max 5 attempts
        if ($pending['otp_attempts'] >= 5) {
            Session::forget('register_pending');
            return response()->json([
                'success' => false,
                'message' => 'Too many failed attempts. Please start over.',
                'expired' => true,
            ], 400);
        }

        // Wrong OTP
        if ((string) $pending['otp'] !== (string) $request->otp) {
            $pending['otp_attempts']++;
            Session::put('register_pending', $pending);

            $remaining = 5 - $pending['otp_attempts'];
            return response()->json([
                'success' => false,
                'message' => "Incorrect OTP. {$remaining} attempt(s) remaining.",
            ], 422);
        }

        // ─── OTP Correct: Create User ─────────────────────────────────────
        Log::info('User Data Before Create', [
            'name'           => $pending['name'],
            'email'          => $pending['email'],
            'phone_number'   => $pending['phone'],
            'company_name'   => $pending['company_name'] ?? null,
            'password'       => Hash::make($pending['password']),
            'parent_user_id' => $pending['parent_user_id'] ?? null,
            'role'           => 'USER',
        ]);
        Log::info('User Data Before Create', $pending);
        $user = new User();
        $user->name = $pending['name'];
        $user->email = $pending['email'];
        $user->phone_number = $pending['phone'];
        $user->company_name = $pending['company_name'];
        $user->password = Hash::make($pending['password']);
        $user->parent_user_id = $pending['parent_user_id'];
        $user->role = "USER";


        $user->save();

        // $user = User::create([
        //     'name'           => $pending['name'],
        //     'email'          => $pending['email'],
        //     'phone_number'   => $pending['phone'],
        //     'company_name'   => $pending['company_name'] ?? null,
        //     'password'       => Hash::make($pending['password']),
        //     'parent_user_id' => $pending['parent_user_id'],
        //     'role'           => 'USER',         // referral registrations = dealer
        // ]);

        if (!empty($pending['referral_link_id'])) {
            ReferralLink::where('id', $pending['referral_link_id'])
                ->where('is_used', false) // safety
                ->update([
                    'is_used' => true,
                    'used_at' => now(),
                ]);
        }

        Log::info($user);

        Session::forget('register_pending');

        return response()->json([
            'success'  => true,
            'message'  => 'Account created successfully!',
            'redirect' => '/login',
        ]);
    }




    // public function showRegister()
    // {
    //     return view('auth.register');
    // }



    public function showRegister(Request $request)
    {
        $code = $request->query('ref-code');

        if (!$code) {
            throw new NotFoundHttpException();
        }

        $referral = ReferralLink::where('code', $code)
            ->where('is_used', false)            
            ->where('expires_at', '>', now())      
            ->first();

        if (!$referral) {
            throw new NotFoundHttpException(); // ya custom error page
        }

        $parentUser = $referral->user;

        return view('auth.register', [
            'refCode'    => $code,
            'parentUser' => $parentUser,
        ]);
    }
}
