<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\CentralLogics\FormMailLogic;
use App\CentralLogics\Helpers;
use App\CentralLogics\OtpDelivery;
use App\CentralLogics\WhatsAppOtpModule;
use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Carbon\CarbonInterval;

class PasswordResetController extends Controller
{
    private const RESET_EXPIRY_MINUTES = 30;

    public function __construct(
        private User $user,
    ){}

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function resetPasswordRequest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email_or_phone' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $identifier = $request['email_or_phone'];
        $isEmailLookup = filter_var($identifier, FILTER_VALIDATE_EMAIL);

        $customer = $this->user->where(['email' => $identifier])
            ->orWhere('phone', 'like', "%{$identifier}%")->first();

        if (!isset($customer)) {
            return response()->json(['errors' => [
                ['code' => 'not-found', 'message' => 'Customer not found!']
            ]], 404);
        }

        $otp_interval_time = Helpers::get_business_settings('otp_resend_time') ?? 60;
        $password_verification_data = DB::table('password_resets')->where('email_or_phone', $identifier)->first();

        if (isset($password_verification_data) && Carbon::parse($password_verification_data->created_at)->DiffInSeconds() < $otp_interval_time) {
            $time = $otp_interval_time - Carbon::parse($password_verification_data->created_at)->DiffInSeconds();

            $errors = [];
            $errors[] = [
                'code' => 'otp',
                'message' => translate('please_try_again_after_') . $time . ' ' . translate('seconds')
            ];
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $send_by_phone = Helpers::get_business_settings('phone_verification');
        $whatsappOtpEnabled = WhatsAppOtpModule::isEnabled();
        $phoneToSend = $customer->phone;

        // Phone OTP reset only when the user entered a phone number (not email).
        if (!$isEmailLookup && $phoneToSend
            && (($whatsappOtpEnabled && $send_by_phone) || $send_by_phone)) {
            $token = env('APP_MODE') == 'live' ? rand(100000, 999999) : 123456;

            DB::table('password_resets')->updateOrInsert(['email_or_phone' => $identifier], [
                'email_or_phone' => $identifier,
                'token' => (string) $token,
                'created_at' => now(),
            ]);

            $delivery = OtpDelivery::sendPhoneOtp($phoneToSend, $token);

            return response()->json([
                'message' => $delivery['message'],
                'channel' => $delivery['channel'],
            ], 200);
        }

        // Email reset: secure link token (30-minute expiry enforced on verify/submit).
        $token = env('APP_MODE') == 'live' ? Str::random(64) : 'dev-reset-token-' . Str::random(32);

        DB::table('password_resets')->updateOrInsert(['email_or_phone' => $identifier], [
            'email_or_phone' => $identifier,
            'token' => $token,
            'created_at' => now(),
            'otp_hit_count' => 0,
            'is_temp_blocked' => 0,
            'temp_block_time' => null,
        ]);

        $siteUrl = rtrim(env('MENTORKHOJ_SITE_URL', 'https://www.mentorkhoj.com'), '/');
        $resetLink = $siteUrl . '/reset-password?' . http_build_query([
            'email_or_phone' => $identifier,
            'token' => $token,
        ]);

        try {
            if (!FormMailLogic::sendPasswordResetEmail($customer['email'], trim($customer->f_name . ' ' . $customer->l_name), $token, $resetLink)) {
                return response()->json(['errors' => [
                    ['code' => 'mail-failed', 'message' => 'Could not send reset email. Please try again or contact ' . FormMailLogic::adminEmail() . '.']
                ]], 400);
            }
        } catch (\Exception $exception) {
            \Illuminate\Support\Facades\Log::error('Password reset email failed', [
                'email' => $customer->email,
                'error' => $exception->getMessage(),
            ]);
            return response()->json(['errors' => [
                ['code' => 'mail-failed', 'message' => 'Could not send reset email. Please try again or contact ' . FormMailLogic::adminEmail() . '.']
            ]], 400);
        }

        return response()->json([
            'message' => 'Email sent successfully.',
            'channel' => 'email',
        ], 200);
    }

    public function verifyToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email_or_phone' => 'required',
            'reset_token'=> 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $max_otp_hit = Helpers::get_business_settings('maximum_otp_hit') ?? 5;
        $max_otp_hit_time = Helpers::get_business_settings('otp_resend_time') ?? 60;
        $temp_block_time = Helpers::get_business_settings('temporary_block_time') ?? 600;

        $verify = DB::table('password_resets')->where(['token' => $request['reset_token'], 'email_or_phone' => $request['email_or_phone']])->first();
        if (isset($verify)) {
            if ($this->isResetTokenExpired($verify)) {
                DB::table('password_resets')->where('email_or_phone', $request['email_or_phone'])->delete();
                return response()->json(['errors' => [
                    ['code' => 'expired', 'message' => 'Reset link has expired. Please request a new password reset email.']
                ]], 400);
            }

            if(isset($verify->temp_block_time ) && Carbon::parse($verify->temp_block_time)->DiffInSeconds() <= $temp_block_time){
                $time = $temp_block_time - Carbon::parse($verify->temp_block_time)->DiffInSeconds();

                $errors = [];
                $errors[] = ['code' => 'otp_block_time',
                    'message' => translate('please_try_again_after_') . CarbonInterval::seconds($time)->cascade()->forHumans()
                ];
                return response()->json([
                    'errors' => $errors
                ], 403);
            }

            return response()->json(['message' => "Token found, you can proceed"], 200);

        }
        else{

            $verification_data= DB::table('password_resets')->where('email_or_phone', $request['email_or_phone'])->first();

            if(isset($verification_data)){
                $time= $temp_block_time - Carbon::parse($verification_data->temp_block_time)->DiffInSeconds();

                if(isset($verification_data->temp_block_time ) && Carbon::parse($verification_data->temp_block_time)->DiffInSeconds() <= $temp_block_time){
                    $time= $temp_block_time - Carbon::parse($verification_data->temp_block_time)->DiffInSeconds();

                    $errors = [];
                    $errors[] = [
                        'code' => 'otp_block_time',
                        'message' => translate('please_try_again_after_') . CarbonInterval::seconds($time)->cascade()->forHumans()
                    ];
                    return response()->json([
                        'errors' => $errors
                    ], 403);
                }

                if($verification_data->is_temp_blocked == 1 && Carbon::parse($verification_data->created_at)->DiffInSeconds() >= $max_otp_hit_time){
                    DB::table('password_resets')->updateOrInsert(['email_or_phone' => $request['email_or_phone']],
                        [
                            'otp_hit_count' => 0,
                            'is_temp_blocked' => 0,
                            'temp_block_time' => null,
                            'created_at' => now(),
                        ]);
                }

                if($verification_data->otp_hit_count >= $max_otp_hit &&  Carbon::parse($verification_data->created_at)->DiffInSeconds() < $max_otp_hit_time &&  $verification_data->is_temp_blocked == 0){
                    DB::table('password_resets')->updateOrInsert(['email_or_phone' => $request['email_or_phone']],
                        [
                            'is_temp_blocked' => 1,
                            'temp_block_time' => now(),
                            'created_at' => now(),
                        ]);

                    $errors = [];
                    $errors[] = [
                        'code' => 'otp_temp_blocked',
                        'message' => translate('Too_many_attempts. please_try_again_after_'). CarbonInterval::seconds($time)->cascade()->forHumans()
                    ];
                    return response()->json([
                        'errors' => $errors
                    ], 405);
                }

            }

            DB::table('password_resets')->updateOrInsert(['email_or_phone' => $request['email_or_phone']],
                [
                    'otp_hit_count' => DB::raw('otp_hit_count + 1'),
                    'created_at' => now(),
                    'temp_block_time' => null,
                ]);
        }


        return response()->json(['errors' => [
            ['code' => 'invalid', 'message' => 'OTP is not matched']
        ]], 400);
    }

    public function resetPasswordSubmit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email_or_phone' => 'required',
            'reset_token' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $data = DB::table('password_resets')->where(['email_or_phone' => $request['email_or_phone']])
            ->where(['token' => $request['reset_token']])->first();

        if (isset($data)) {
            if ($this->isResetTokenExpired($data)) {
                DB::table('password_resets')->where('email_or_phone', $request['email_or_phone'])->delete();
                return response()->json(['errors' => [
                    ['code' => 'expired', 'message' => 'Reset link has expired. Please request a new password reset email.']
                ]], 400);
            }

            if ($request['password'] == $request['confirm_password']) {
                $customer = $this->user->where(['email' => $request['email_or_phone']])->orWhere('phone', $request['email_or_phone'])->first();
                $customer->password = bcrypt($request['confirm_password']);
                $customer->save();

                DB::table('password_resets')
                    ->where(['email_or_phone' => $request['email_or_phone']])
                    ->where(['token' => $request['reset_token']])->delete();

                return response()->json(['message' => 'Password changed successfully.'], 200);
            }
            return response()->json(['errors' => [
                ['code' => 'mismatch', 'message' => 'Password did not match!']
            ]], 401);
        }
        return response()->json(['errors' => [
            ['code' => 'invalid', 'message' => 'Invalid token.']
        ]], 400);
    }

    private function isResetTokenExpired(object $row): bool
    {
        if (!isset($row->created_at)) {
            return true;
        }

        $expiryMinutes = (int) (env('PASSWORD_RESET_EXPIRY_MINUTES', self::RESET_EXPIRY_MINUTES));

        return Carbon::parse($row->created_at)->diffInMinutes(now()) >= $expiryMinutes;
    }
}
