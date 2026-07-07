@extends('layouts.admin.app')

@section('title', translate('Product Setup'))

@section('content')
<div class="content container-fluid">
    @include('admin-views.business-settings.partial.business-settings-navmenu')

    <div class="tab-content">
        <div class="tab-pane fade show active" id="business-setting">
            <div class="card">

                <div class="card-body">
                    <form action="{{route('admin.business-settings.store.product-setup-update')}}" method="post" enctype="multipart/form-data">
                        @csrf
                        <div class="row align-items-end">
                            @php($stock_limit=\App\Model\BusinessSetting::where('key','minimum_stock_limit')->first()->value)
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label class="input-label" for="minimum_stock_limit">{{translate('minimum stock limit')}}</label>
                                    <input type="number" min="1" value="{{$stock_limit}}"
                                           name="minimum_stock_limit" class="form-control" placeholder="" required>
                                </div>
                            </div>
                            @php($tax_status= \App\CentralLogics\Helpers::get_business_settings('product_vat_tax_status'))
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label class="input-label" for="exampleFormControlInput1">{{translate('Product VAT/TAX Status (Included/Excluded)')}}</label>
                                    <select name="product_vat_tax_status" class="form-control">
                                        <option value="excluded" {{$tax_status =='excluded'?'selected':''}}>{{translate('excluded')}}</option>
                                        <option value="included" {{$tax_status =='included'?'selected':''}}>{{translate('included')}}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4 col-sm-6 mt-5">
                                @php($featuredProductStatus=\App\CentralLogics\Helpers::get_business_settings('featured_product_status'))
                                <div class="form-group">
                                    <label class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                            <span class="pr-1 d-flex align-items-center switch--label">
                                            <span class="line--limit-1">
                                                <strong>{{translate('featured_product')}}</strong>
                                            </span>
                                                <span class="form-label-secondary text-danger d-flex ml-1" data-toggle="tooltip" data-placement="right"
                                                      data-original-title="{{translate('If the status is off most featured product will not show to user.')}}">
                                                    <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="info">
                                                </span>
                                            </span>
                                        <input type="checkbox" name="featured_product_status" class="toggle-switch-input" {{ $featuredProductStatus == 1 ? 'checked' : '' }}>
                                        <span class="toggle-switch-label text">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-4 col-sm-6">
                                @php($trendingProductStatus=\App\CentralLogics\Helpers::get_business_settings('trending_product_status'))
                                <div class="form-group">
                                    <label class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                            <span class="pr-1 d-flex align-items-center switch--label">
                                            <span class="line--limit-1">
                                                <strong>{{translate('trending_product')}}</strong>
                                            </span>
                                                <span class="form-label-secondary text-danger d-flex ml-1" data-toggle="tooltip" data-placement="right"
                                                      data-original-title="{{translate('If the status is off most trending product will not show to user.')}}">
                                                    <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="info">
                                                </span>
                                            </span>
                                        <input type="checkbox" name="trending_product_status" class="toggle-switch-input" {{ $trendingProductStatus == 1 ? 'checked' : '' }}>
                                        <span class="toggle-switch-label text">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                    </label>
                                </div>
                            </div>


                            <div class="col-md-4 col-sm-6">
                                @php($mostReviewedProductStatus=\App\CentralLogics\Helpers::get_business_settings('most_reviewed_product_status'))
                                <div class="form-group">
                                    <label class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                            <span class="pr-1 d-flex align-items-center switch--label">
                                            <span class="line--limit-1">
                                                <strong>{{translate('most_reviewed_product')}}</strong>
                                            </span>
                                                <span class="form-label-secondary text-danger d-flex ml-1" data-toggle="tooltip" data-placement="right"
                                                      data-original-title="{{translate('If the status is off most reviewed product will not show to user.')}}">
                                                    <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="info">
                                                </span>
                                            </span>
                                        <input type="checkbox" name="most_reviewed_product_status" class="toggle-switch-input" {{ $mostReviewedProductStatus == 1 ? 'checked' : '' }}>
                                        <span class="toggle-switch-label text">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-4 col-sm-6">
                                @php($recommendedProductStatus=\App\CentralLogics\Helpers::get_business_settings('recommended_product_status'))
                                <div class="form-group">
                                    <label class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                            <span class="pr-1 d-flex align-items-center switch--label">
                                            <span class="line--limit-1">
                                                <strong>{{translate('recommended_product')}}</strong>
                                            </span>
                                                <span class="form-label-secondary text-danger d-flex ml-1" data-toggle="tooltip" data-placement="right"
                                                      data-original-title="{{translate('If the status is off recommended product will not show to user.')}}">
                                                    <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="info">
                                                </span>
                                            </span>
                                        <input type="checkbox" name="recommended_product_status" class="toggle-switch-input" {{ $recommendedProductStatus == 1 ? 'checked' : '' }}>
                                        <span class="toggle-switch-label text">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="btn--container justify-content-end">
                            <button type="reset" class="btn btn--reset">{{translate('reset')}}</button>
                            <button type="{{env('APP_MODE')!='demo'?'submit':'button'}}"
                                    class="btn btn--primary call-demo">{{translate('save')}}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

