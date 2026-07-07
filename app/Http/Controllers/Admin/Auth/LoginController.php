<?php

namespace App\Http\Controllers\Admin\Auth;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\RegisterDevice;
use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Jenssegers\Agent\Facades\Agent;

class LoginController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest:admin', ['except' => ['logout']]);
    }

    /**
     * @param $tmp
     * @return void
     */
    public function captcha($tmp): void
    {
        $phrase = new PhraseBuilder;
        $code = $phrase->build(4);
        $builder = new CaptchaBuilder($code, $phrase);
        $builder->setBackgroundColor(220, 210, 230);
        $builder->setMaxAngle(25);
        $builder->setMaxBehindLines(0);
        $builder->setMaxFrontLines(0);
        $builder->build($width = 100, $height = 40, $font = null);
        $phrase = $builder->getPhrase();

        if(Session::has('default_captcha_code')) {
            Session::forget('default_captcha_code');
        }
        Session::put('default_captcha_code', $phrase);
        header("Cache-Control: no-cache, must-revalidate");
        header("Content-Type:image/jpeg");
        $builder->output();
    }

    /**
     * @return Application|Factory|View
     */
    public function login(): Factory|View|Application
    {
        $logoName = Helpers::get_business_settings('logo');
        $logo = Helpers::onErrorImage($logoName, asset('storage/app/public/restaurant') . '/' . $logoName, asset('public/assets/admin/img/160x160/img2.jpg'), 'restaurant/');
        return view('admin-views.auth.login', compact('logo'));
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function submit(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6'
        ]);

        $ip = $request->ip();
        $browserName = Agent::browser();
        $browserVersion = Agent::version($browserName);
        $deviceType = Agent::device();
        $devicePlatform = Agent::platform();

        $recaptcha = Helpers::get_business_settings('recaptcha');
        if (isset($recaptcha) && $recaptcha['status'] == 1) {
            $request->validate([
                'g-recaptcha-response' => [
                    function ($attribute, $value, $fail) {
                        $secret_key = Helpers::get_business_settings('recaptcha')['secret_key'];
                        $response = $value;
                        $url = 'https://www.google.com/recaptcha/api/siteverify?secret=' . $secret_key . '&response=' . $response;
                        $response = \file_get_contents($url);
                        $response = json_decode($response);
                        if (!$response->success) {
                            $fail(\App\CentralLogics\translate('ReCaptcha Failed'));
                        }
                    },
                ],
            ]);
        } else {
            if (strtolower($request->default_captcha_value) != strtolower(Session('default_captcha_code'))) {
                Session::forget('default_captcha_code');
                return back()->withErrors(\App\CentralLogics\translate('Captcha Failed'));
            }
        }

        if(Session::has('default_captcha_code')) {
            Session::forget('default_captcha_code');
        }

        if (auth('admin')->attempt(['email' => $request->email, 'password' => $request->password, 'status' => 1], $request->remember)) {
            $uniqueIdentifier = md5($ip . $browserName . $browserVersion . $deviceType . $devicePlatform);

            $registeredDevice = RegisterDevice::where(['unique_identifier' => $uniqueIdentifier, 'user_type' => 'admin'])->first();
            if (!isset($registeredDevice)){
                $registerDevice = new RegisterDevice();
                $registerDevice->user_id = auth('admin')->user()->id;
                $registerDevice->user_type = 'admin';
                $registerDevice->ip_address = $ip;
                $registerDevice->browser_name = $browserName;
                $registerDevice->browser_version = $browserVersion;
                $registerDevice->device_type = $deviceType;
                $registerDevice->device_platform = $devicePlatform;
                $registerDevice->is_robot = Agent::isRobot() ? 1 : 0;
                $registerDevice->unique_identifier = $uniqueIdentifier;
                $registerDevice->save();

                try {
                    $emailServices = Helpers::get_business_settings('mail_config');
                    if (isset($emailServices['status']) && $emailServices['status'] == 1) {
                        Mail::to(auth('admin')->user()->email)->send(new \App\Mail\Admin\LoginAlert($request->ip()));
                    }
                } catch (\Exception $e) {
                }

            }

            return redirect()->route('admin.dashboard');
        }

        return redirect()->back()->withInput($request->only('email', 'remember'))
            ->withErrors(['Credentials does not match.']);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function logout(Request $request): RedirectResponse
    {
        auth()->guard('admin')->logout();
        return redirect()->route('admin.auth.login');
    }
}
