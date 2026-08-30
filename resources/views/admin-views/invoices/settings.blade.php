@extends('layouts.admin.app')

@section('title', translate('Invoice Settings'))

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title">{{ translate('Invoice Settings') }}</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-5 mb-3">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">{{ translate('Company Profile') }} <small class="text-muted">({{ translate('Locked') }})</small></h5></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ translate('Brand') }}</dt><dd class="col-sm-8">{{ $company['brand_name'] ?? '' }}</dd>
                        <dt class="col-sm-4">{{ translate('Legal Name') }}</dt><dd class="col-sm-8">{{ $company['legal_name'] ?? '' }}</dd>
                        <dt class="col-sm-4">{{ translate('GSTIN') }}</dt><dd class="col-sm-8"><strong>{{ $company['gstin'] ?? '' }}</strong></dd>
                        <dt class="col-sm-4">{{ translate('PAN') }}</dt><dd class="col-sm-8">{{ $company['pan'] ?? '' }}</dd>
                        <dt class="col-sm-4">{{ translate('CIN') }}</dt><dd class="col-sm-8">{{ $company['cin'] ?? '' }}</dd>
                        <dt class="col-sm-4">{{ translate('Address') }}</dt><dd class="col-sm-8">{{ $company['address'] ?? '' }}</dd>
                        <dt class="col-sm-4">{{ translate('Phone') }}</dt><dd class="col-sm-8">{{ $company['phone'] ?? '' }}</dd>
                        <dt class="col-sm-4">{{ translate('Email') }}</dt><dd class="col-sm-8">{{ $company['email'] ?? '' }}</dd>
                        <dt class="col-sm-4">{{ translate('Website') }}</dt><dd class="col-sm-8">{{ $company['website'] ?? '' }}</dd>
                    </dl>
                    <p class="text-muted small mt-3 mb-0">{{ translate('Legal entity details are fixed and updated via deployment only.') }}</p>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <form action="{{ route('admin.invoice-settings.update') }}" method="post" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Branding') }}</h5></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>{{ translate('Invoice Logo') }}</label>
                            @if($logoUrl)
                                <div class="mb-2"><img src="{{ $logoUrl }}" alt="logo" style="max-height:80px;max-width:200px;object-fit:contain;"></div>
                            @endif
                            <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/jpg">
                        </div>
                        <div class="form-group">
                            <label>{{ translate('Brand Color') }}</label>
                            <input type="color" name="brand_color" class="form-control form-control-color" value="{{ old('brand_color', $settings->brand_color) }}">
                        </div>
                        <div class="form-group mb-0">
                            <label>{{ translate('Footer Text') }}</label>
                            <textarea name="footer_text" class="form-control" rows="2">{{ old('footer_text', $settings->footer_text) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Invoice Defaults') }}</h5></div>
                    <div class="card-body row">
                        <div class="col-md-4 form-group">
                            <label>{{ translate('Prefix') }}</label>
                            <input type="text" name="invoice_prefix" class="form-control" value="{{ old('invoice_prefix', $settings->invoice_prefix) }}" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>{{ translate('Number Padding') }}</label>
                            <input type="number" name="number_padding" class="form-control" min="3" max="10" value="{{ old('number_padding', $settings->number_padding) }}" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>{{ translate('Next Sequence') }}</label>
                            <input type="number" name="next_sequence" class="form-control" min="1" value="{{ old('next_sequence', $settings->next_sequence) }}" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>{{ translate('Currency') }}</label>
                            <input type="text" name="default_currency" class="form-control" value="{{ old('default_currency', $settings->default_currency) }}" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>{{ translate('Default Tax Mode') }}</label>
                            <select name="default_tax_mode" class="form-control">
                                @foreach(['none','cgst_sgst','igst','gst','custom'] as $mode)
                                    <option value="{{ $mode }}" @selected(old('default_tax_mode', $settings->default_tax_mode) === $mode)>{{ strtoupper(str_replace('_','+',$mode)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>{{ translate('Default Tax Rate %') }}</label>
                            <input type="number" step="0.01" name="default_tax_rate" class="form-control" value="{{ old('default_tax_rate', $settings->default_tax_rate) }}">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>{{ translate('Payment Terms (days)') }}</label>
                            <input type="number" name="default_payment_terms_days" class="form-control" min="0" value="{{ old('default_payment_terms_days', $settings->default_payment_terms_days) }}">
                        </div>
                        <div class="col-12 form-group">
                            <label>{{ translate('Default Notes') }}</label>
                            <textarea name="default_notes" class="form-control" rows="2">{{ old('default_notes', $settings->default_notes) }}</textarea>
                        </div>
                        <div class="col-12 form-group mb-0">
                            <label>{{ translate('Default Terms & Conditions') }}</label>
                            <textarea name="default_terms" class="form-control" rows="4">{{ old('default_terms', $settings->default_terms) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Bank Details') }}</h5></div>
                    <div class="card-body row">
                        <div class="col-md-6 form-group"><label>{{ translate('Bank Name') }}</label><input type="text" name="bank_name" class="form-control" value="{{ old('bank_name', $settings->bank_name) }}"></div>
                        <div class="col-md-6 form-group"><label>{{ translate('Account Name') }}</label><input type="text" name="account_name" class="form-control" value="{{ old('account_name', $settings->account_name) }}"></div>
                        <div class="col-md-6 form-group"><label>{{ translate('Account Number') }}</label><input type="text" name="account_number" class="form-control" value="{{ old('account_number', $settings->account_number) }}"></div>
                        <div class="col-md-6 form-group"><label>{{ translate('IFSC') }}</label><input type="text" name="ifsc" class="form-control" value="{{ old('ifsc', $settings->ifsc) }}"></div>
                        <div class="col-md-6 form-group"><label>{{ translate('Branch') }}</label><input type="text" name="bank_branch" class="form-control" value="{{ old('bank_branch', $settings->bank_branch) }}"></div>
                        <div class="col-md-6 form-group mb-0"><label>{{ translate('UPI ID') }}</label><input type="text" name="upi_id" class="form-control" value="{{ old('upi_id', $settings->upi_id) }}"></div>
                    </div>
                </div>

                <button type="submit" class="btn btn--primary">{{ translate('Save Settings') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
