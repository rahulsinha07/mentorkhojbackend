<div class="footer">
    <div class="row justify-content-between align-items-center">
        <div class="col">
            <p class="font-size-sm mb-0">
                <span class="d-none d-sm-inline-block">{{ Str::limit(Helpers::get_business_settings('footer_text'), 100) }}</span>
            </p>
        </div>
        <div class="col-auto">
            <div class="d-flex justify-content-end">
                <ul class="list-inline list-separator">
                    <li class="list-inline-item">
                        <a class="list-separator-link" href="{{route('admin.business-settings.store.ecom-setup')}}">{{translate('restaurant')}} {{translate('settings')}}</a>
                    </li>

                    <li class="list-inline-item">
                        <a class="list-separator-link" href="{{route('admin.settings')}}">{{translate('profile')}}</a>
                    </li>

                    <li class="list-inline-item">
                        <div class="hs-unfold">
                            <a class="js-hs-unfold-invoker btn btn-icon btn-ghost-secondary rounded-circle"
                               href="{{route('admin.dashboard')}}">
                                <i class="tio-home-outlined"></i>
                            </a>
                        </div>
                    </li>

                    <li class="list-inline-item">
                        <label class="badge badge-success text-capitalize">
                            {{translate('Software Version')}} {{ env('SOFTWARE_VERSION') }}
                        </label>
                    </li>

                </ul>
            </div>
        </div>
    </div>
</div>
