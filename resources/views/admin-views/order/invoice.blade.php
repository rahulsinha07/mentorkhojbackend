@extends('layouts.admin.app')

@section('title', translate('invoice'))

@section('content')

    <div class="content container-fluid initial-38">
        <div class="row justify-content-center" id="printableArea">
            <div class="col-md-12">
                <div class="text-center">
                    <input type="button" class="btn btn--primary non-printable text-white" data-type="printableArea" id="printableSection"
                           value="{{translate('Proceed, If thermal printer is ready.')}}"/>
                    <a href="{{url()->previous()}}" class="btn btn--danger non-printable text-white">{{ translate('Back') }}</a>
                </div>
                <hr class="non-printable">
            </div>
            @php($logo=\App\Model\BusinessSetting::where(['key'=>'logo'])->first()->value)
            <div class="initial-38-1">
                <div class="pt-3">
                    <div class="pt-3">
                        <img class="initial-38-2"
                             src="{{ App\CentralLogics\Helpers::onErrorImage($logo, asset('storage/app/public/restaurant') . '/' . $logo, asset('/public/assets/admin/img/food.png'), 'restaurant/')}}"
                             alt="{{ translate('logo') }}">
                    </div>
                </div>
                <div class="text-center pt-2 mb-3">
                    <h2  class="initial-38-3">{{ $order->branch->name }}</h2>
                    <h5 class="text-break initial-38-4">
                        {{ $order->branch->address }}
                    </h5>
                    <h5 class="initial-38-4 initial-38-3">
                        {{ translate('Phone') }} : {{\App\Model\BusinessSetting::where(['key'=>'phone'])->first()->value}}
                    </h5>
                    @if ($order->branch->gst_status)
                        <h5 class="initial-38-4 initial-38-3 fz-12px">
                            {{ translate('Gst No') }} : {{ $order->branch->gst_code }}
                        </h5>
                    @endif
                </div>
                <span class="initial-38-5">---------------------------------------------------------------------------------</span>
                <div class="row mt-3">
                    <div class="col-6">
                        <h5>{{ translate('Order ID') }} :
                            <span class="font-light"> {{$order['id']}}</span>
                        </h5>
                    </div>
                    <div class="col-6">
                        <h5>
                            <span class="font-light">
                            {{date('d M Y h:i a',strtotime($order['created_at']))}}
                            </span>
                        </h5>
                    </div>
                    <div class="col-12">
                        @if($order->is_guest == 0)
                            @if(isset($order->customer))
                                <h5>
                                    {{ translate('Customer Name') }} :<span class="font-light">{{$order->customer['f_name'].' '.$order->customer['l_name']}}</span>
                                </h5>
                                <h5>
                                    {{ translate('phone') }} :<span class="font-light">{{$order->customer['phone']}}</span>
                                </h5>
                                @php($address=\App\Model\CustomerAddress::find($order['delivery_address_id']))
                                <h5 class="text-break">
                                    {{ translate('address') }} :<span class="font-light">{{isset($address)?$address['address']:''}}</span>
                                </h5>
                            @endif
                        @else
                            @if($order->order_type == 'delivery')
                                @if(isset($order->delivery_address))
                                    <h5>
                                        {{ translate('Customer Name') }} :<span class="font-light">{{$order->delivery_address['contact_person_name']}}</span>
                                    </h5>
                                    <h5>
                                        {{ translate('Customer Name') }} :<span class="font-light">{{$order->delivery_address['contact_person_number']}}</span>
                                    </h5>
                                    <h5 class="text-break">
                                        {{ translate('address') }} :<span class="font-light">{{$order->delivery_address['address']}}</span>
                                    </h5>
                                @endif
                            @endif
                        @endif

                    </div>
                </div>
                <h5 class="text-uppercase"></h5>
                <span class="initial-38-5">---------------------------------------------------------------------------------</span>
                <table class="table table-bordered mt-3">
                    <thead>
                    <tr>
                        <th class="initial-38-6 border-top-0 border-bottom-0">{{ translate('QTY') }}</th>
                        <th class="initial-38-7 border-top-0 border-bottom-0">{{ translate('DESC') }}</th>
                        <th class="initial-38-7 border-top-0 border-bottom-0">{{ translate('Price') }}</th>
                    </tr>
                    </thead>

                    <tbody>
                    @php($subTotal=0)
                    @php($totalTax=0)
                    @php($total_dis_on_pro=0)
                    @php($updatedTotalTax=0)
                    @php($vatStatus = '')
                    @foreach($order->details as $detail)

                        @if($detail->product_details !=null)
                            @php($product = json_decode($detail->product_details, true))
                            <tr>
                                <td class="">
                                    {{$detail['quantity']}}
                                </td>
                                <td class="">
                                    {{$product['name']}} <br>
                                    @if(count(json_decode($detail['variation'],true))>0)
                                        <strong><u>Variation : </u></strong>
                                        @foreach(json_decode($detail['variation'],true)[0] ?? json_decode($detail['variation'],true) as $key1 =>$variation)
                                            <div class="font-size-sm text-body">
                                                <span class="text-capitalize">{{$key1}} :  </span>
                                                <span class="font-weight-bold">{{$variation}} {{$key1=='price'?\App\CentralLogics\Helpers::currency_symbol():''}}</span>
                                            </div>
                                        @endforeach
                                    @endif
                                    <span>{{ translate('Unit Price') }} : {{ Helpers::set_symbol($detail['price']) }}</span><br>
                                    <span>{{ translate('Qty') }} : {{ $detail['quantity']}}</span><br>
                                    <span>{{ translate('Discount') }} : {{ Helpers::set_symbol($detail['discount_on_product']) }}</span>

                                </td>
                                <td class="w-28p">
                                    @php($amount=($detail['price']-$detail['discount_on_product'])*$detail['quantity'])
                                    {{ Helpers::set_symbol($amount) }}
                                </td>
                            </tr>

                            @php($subTotal+=$amount)
                            @php($totalTax+=$detail['tax_amount']*$detail['quantity'])
                            @php($updatedTotalTax+= $detail['vat_status'] === 'included' ? 0 : $detail['tax_amount']*$detail['quantity'])
                            @php($vatStatus = $detail['vat_status'])

                        @endif

                    @endforeach
                    </tbody>
                </table>
                <div class="px-3">
                    <dl class="row text-right justify-content-center">
                        <dt class="col-6">{{ translate('Items Price') }}:</dt>
                        <dd class="col-6">{{ Helpers::set_symbol($subTotal) }}</dd>
                        <dt class="col-6">{{translate('Tax / VAT')}} {{ $vatStatus == 'included' ? translate('(included)') : '' }}:</dt>
                        <dd class="col-6">{{ Helpers::set_symbol($totalTax) }}</dd>

                        <dt class="col-6">{{ translate('Subtotal') }}:</dt>
                        <dd class="col-6">
                            {{ Helpers::set_symbol($subTotal+$updatedTotalTax) }}</dd>
                        <dt class="col-6">{{ translate('Coupon Discount') }}:</dt>
                        <dd class="col-6">
                            - {{ Helpers::set_symbol($order['coupon_discount_amount']) }}</dd>
                        <dt class="col-6">{{translate('extra Discount')}}:</dt>
                        <dd class="col-6">
                            - {{ Helpers::set_symbol($order['extra_discount']) }}</dd>
                        <dt class="col-6">{{ translate('Delivery Fee') }}:</dt>
                        <dd class="col-6">
                            @if($order['order_type']=='take_away')
                                @php($del_c=0)
                            @else
                                @php($del_c=$order['delivery_charge'])
                            @endif
                            {{ Helpers::set_symbol($del_c) }}
                            <hr>
                        </dd>

                        <dt class="col-6 font-20px">{{ translate('Total') }}:</dt>
                        <dd class="col-6 font-20px">{{ Helpers::set_symbol($subTotal+$del_c+$updatedTotalTax-$order['coupon_discount_amount']-$order['extra_discount']) }}</dd>
                    </dl>
                    <span class="initial-38-5">---------------------------------------------------------------------------------</span>
                    <h5 class="text-center pt-1">
                        <span class="d-block">"""{{ translate('THANK YOU') }}"""</span>
                    </h5>
                    <span class="initial-38-5">---------------------------------------------------------------------------------</span>
                    <span class="d-block text-center">{{ $footer_text->value }}</span>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script src="{{asset('public/assets/admin/js/jquery.js')}}"></script>
    <script>
        "use strict";

        $(document).ready(function() {
            "use strict";
            $('#printableSection').on('click', function() {
                var type = $(this).data('type');
                printDiv(type);
            });

            function printDiv(divName) {
                var printContents = document.getElementById(divName).innerHTML;
                var originalContents = document.body.innerHTML;
                document.body.innerHTML = printContents;
                window.print();
                document.body.innerHTML = originalContents;
            }
        });
    </script>
@endpush
