<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\ZohoSetting;
use App\Services\ZohoMeetingService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ZohoSettingController extends Controller
{
    public function __construct(private ZohoMeetingService $zoho)
    {
    }

    public function edit(): View
    {
        $setting = ZohoSetting::current();

        return view('admin-views.zoho-settings.edit', compact('setting'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|string|max:255',
            'client_secret' => 'nullable|string|max:1000',
        ]);

        $setting = ZohoSetting::current();
        $setting->client_id = $validated['client_id'];
        if (!empty($validated['client_secret'])) {
            $setting->client_secret = $validated['client_secret'];
        }
        $setting->save();

        Toastr::success(translate('Zoho credentials saved. Click Connect Zoho to authorize.'));

        return back();
    }

    public function connect(): RedirectResponse
    {
        $setting = ZohoSetting::current();
        if (!$setting->isConfigured()) {
            Toastr::error(translate('Enter and save the Client ID and Client Secret first'));

            return back();
        }

        return redirect()->away($this->zoho->getAuthorizationUrl(route('admin.zoho-settings.callback')));
    }

    public function callback(Request $request): RedirectResponse
    {
        $code = (string) $request->get('code', '');
        if ($code === '') {
            Toastr::error(translate('Zoho did not return an authorization code'));

            return redirect()->route('admin.zoho-settings.edit');
        }

        try {
            $this->zoho->handleOAuthCallback($code, route('admin.zoho-settings.callback'), auth('admin')->id());
        } catch (\Throwable $e) {
            Toastr::error(translate('Zoho connect failed').': '.$e->getMessage());

            return redirect()->route('admin.zoho-settings.edit');
        }

        $result = $this->zoho->createTestMeeting();
        if ($result) {
            $this->zoho->deleteMeeting($result['meeting_id']);
            Toastr::success(translate('Connected to Zoho, and a test meeting was created and deleted successfully'));
        } else {
            Toastr::warning(translate('Connected to Zoho, but the test meeting could not be created — check the error below and use Test Connection after adjusting'));
        }

        return redirect()->route('admin.zoho-settings.edit');
    }

    public function testConnection(): RedirectResponse
    {
        $setting = ZohoSetting::current();
        if (!$setting->isConnected()) {
            Toastr::error(translate('Not connected to Zoho yet — click Connect Zoho first'));

            return back();
        }

        $result = $this->zoho->createTestMeeting();
        if (!$result) {
            Toastr::error(translate('Test failed').': '.($setting->fresh()->last_error ?? translate('Unknown error')));

            return back();
        }

        $this->zoho->deleteMeeting($result['meeting_id']);
        Toastr::success(translate('Zoho API is working — test meeting created and deleted successfully'));

        return back();
    }

    public function disconnect(): RedirectResponse
    {
        $this->zoho->disconnect();
        Toastr::success(translate('Disconnected from Zoho'));

        return back();
    }
}
