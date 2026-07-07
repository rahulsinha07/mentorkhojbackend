@extends('layouts.admin.app')

@section('title', translate('Language Translate'))

@push('css_or_js')
    <link href="{{asset('public/assets/admin')}}/vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="card border __language-card">
            <div class="card-header">
                <h5 class="card-title">{{translate('language_content_table')}}</h5>
                <a href="{{route('admin.business-settings.web-app.system-setup.language.index')}}"
                    class="btn btn-sm btn--danger btn-icon-split">
                    <span class="text text-capitalize">{{translate('back')}}</span>
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered m-0" id="dataTable" width="100%" cellspacing="0">
                        <thead class="thead-light">
                        <tr>
                            <th scope="col">{{translate('SL')}}</th>
                            <th scope="col">{{translate('key')}}</th>
                            <th scope="col">{{translate('value')}}</th>
                            <th scope="col"></th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach($lang_data as $count=>$language)
                            <tr id="lang-{{$language['key']}}">
                                <td>{{$count+1}}</td>
                                <td class="translate-key">
                                    <div class="white-space-initial">
                                        <input type="text" name="key[]" value="{{$language['key']}}" hidden>
                                        <label>{{$language['key']}}</label>
                                    </div>
                                </td>
                                <td class="translate-value">
                                    <input type="text" class="form-control w-100" name="value[]"
                                            id="value-{{$count+1}}"
                                            value="{{$language['value']}}">
                                </td>
                                <td>
                                    <button type="button"
                                            onclick="update_lang('{{urlencode($language['key'])}}',$('#value-{{$count+1}}').val())"
                                            class="btn btn-primary update-language">{{translate('Update')}}
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script src="https://code.jquery.com/jquery-3.1.1.min.js" ></script>

    <script src="{{asset('public/assets/admin')}}/vendor/datatables/dataTables.bootstrap4.min.js"></script>
    <script>

        $(document).ready(function () {
            $('#dataTable').DataTable({
                    "pageLength": {{\App\CentralLogics\Helpers::getPagination()}}
                });
        });


        function update_lang(key, value) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.ajax({
                url: "{{route('admin.business-settings.web-app.system-setup.language.translate-submit',[$lang])}}",
                method: 'POST',
                data: {
                    key: key,
                    value: value
                },
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (response) {
                    toastr.success('{{translate('text_updated_successfully')}}');
                },
                complete: function () {
                    $('#loading').hide();
                },
            });
        }

        function remove_key(key) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                }
            });
            $.ajax({
                url: "{{route('admin.business-settings.web-app.system-setup.language.remove-key',[$lang])}}",
                method: 'POST',
                data: {
                    key: key
                },
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (response) {
                    toastr.success('{{translate('Key removed successfully')}}');
                    $('#lang-'+key).hide();
                },
                complete: function () {
                    $('#loading').hide();
                },
            });
        }

    </script>

@endpush
