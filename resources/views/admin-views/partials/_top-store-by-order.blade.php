<style>
    .grid-card-clickable{
        padding-left: 6px;padding-right: 6px;cursor: pointer;
    }
    .grid-card{
        min-height: 170px;
    }
    .div-center{
        border-radius: 50%;width: 60px;height: 60px;border:2px solid #80808082;
    }
    .no-exist{
        font-size: 10px;
    }
</style>
<div class="card-header border-0 order-header-shadow">
    <h5 class="card-title d-flex justify-content-between flex-grow-1">
        <span>{{translate('top_store_by_order_received')}}</span>
        <a href="" class="fz-12px font-medium text-006AE5">{{translate('view_all')}}</a>
    </h5>
</div>

<div class="card-body">
    <div class="row">
        @foreach($top_store_by_order_received as $key=>$item)
            @php($shop=\App\Model\Shop::where('seller_id',$item['seller_id'])->first())
            @if(isset($shop))
                <div class="col-6 col-md-4 mt-2 grid-card-clickable" data-seller-id="{{ $item['seller_id'] }}">
                    <div class="grid-card">
                        <label class="label_1">{{ translate('Orders') }} : {{$item['count']}}</label>
                        <div class="mt-6 text-center div-center">
                            <img src="{{asset('storage/app/public/shop/'.$shop->image  ?? '' )}}">
                        </div>
                        <div class="text-center mt-2 no-exist">
                            <span>{{$shop['name']?? translate('Not exist')}}</span>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</div>

<script>
    "use strict";

    $(document).ready(function() {
        $('.grid-card-clickable').on('click', function() {
            var sellerId = $(this).data('seller-id');
            var url = "{{ route('admin.sellers.view', ':sellerId') }}";
            url = url.replace(':sellerId', sellerId);
            window.location.href = url;
        });
    });
</script>
