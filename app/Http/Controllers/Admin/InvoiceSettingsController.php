<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\CentralLogics\InvoiceCompanyProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateInvoiceSettingsRequest;
use App\Model\Invoice\InvoiceSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InvoiceSettingsController extends Controller
{
    public function edit(): View
    {
        $settings = InvoiceSetting::instance();
        $company = InvoiceCompanyProfile::locked();
        $logoUrl = InvoiceCompanyProfile::logoUrl($settings->logo);

        return view('admin-views.invoices.settings', compact('settings', 'company', 'logoUrl'));
    }

    public function update(UpdateInvoiceSettingsRequest $request): RedirectResponse
    {
        $settings = InvoiceSetting::instance();
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo'] = Helpers::upload('invoice/', 'png', $request->file('logo'));
        } else {
            unset($data['logo']);
        }

        $settings->fill($data);
        $settings->save();

        return back()->with('success', translate('Invoice settings updated successfully'));
    }
}
