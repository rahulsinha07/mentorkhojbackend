@extends('payment-gateway.layouts.master')

@push('script')
@endpush

@section('content')
<center><h1>Please do not refresh this page...</h1></center>

<form action="{{ route('razor-pay.payment', ['payment_id' => $data->id]) }}" id="form" method="POST">
    @csrf

    <script 
        src="https://checkout.razorpay.com/v1/checkout.js"
        data-key="{{ config('razor_config.api_key') }}" 
        data-amount="{{ round($data->payment_amount * 100) }}" 
        data-currency="{{ $data->currency_code ?? 'INR' }}"
        data-buttontext="Pay {{ round($data->payment_amount, 2) . ' ' . $data->currency_code }}"
        data-name="Mentorkhoj" 
        data-description="Payment for order #{{ $data->id }}"
        data-image="{{ asset('public/assets/admin/img/app_logo.png') }}" 
        data-prefill.name="{{ $payer->name ?? 'User' }}"
        data-prefill.email="{{ $payer->email ?? 'user@example.com' }}"
        data-prefill.contact="{{ $payer->phone ?? '9999999999' }}"
        data-theme.color="#ff7529">
    </script>

    <button id="pay-button" type="submit" style="display:none"></button>
</form>

<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function () {
    document.getElementById("pay-button").click();
});
</script>
@endsection
