@extends('layouts.admin.app')

@section('title', translate('Add new category'))

@section('content')
    <div class="content container-fluid">

        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/category.png')}}" class="w--24" alt="{{ translate('category') }}">
                </span>
                <span>
                    {{translate('category_setup')}}
                </span>
            </h1>
        </div>

        <div class="row g-2">
            <div class="col-sm-12 col-lg-12">
                <div class="card">
                    <div class="card-body pt-sm-0 pb-sm-4">
                        <form action="{{route('admin.category.store')}}" method="post" enctype="multipart/form-data">
                            @csrf
                            @php($data = Helpers::get_business_settings('language'))
                            @php($defaultLanguage = Helpers::get_default_language())
                            @if ($data && array_key_exists('code', $data[0]))
                                <ul class="nav nav-tabs d-inline-flex mb--n-30">
                                    @foreach ($data as $lang)
                                    <li class="nav-item">
                                        <a class="nav-link lang_link {{ $lang['default'] == true ? 'active' : '' }}" href="#"
                                        id="{{ $lang['code'] }}-link">{{ Helpers::get_language_name($lang['code']) . '(' . strtoupper($lang['code']) . ')' }}</a>
                                    </li>
                                    @endforeach
                                </ul>
                                <div class="row align-items-end g-4">
                                    @foreach ($data as $lang)
                                        <div class="col-sm-6 {{ $lang['default'] == false ? 'd-none' : '' }} lang_form"
                                                id="{{ $lang['code'] }}-form">
                                            <label class="form-label"
                                                    for="exampleFormControlInput1">{{ translate('category') }} {{ translate('name') }}
                                                ({{ strtoupper($lang['code']) }})</label>
                                            <input type="text" name="name[]" class="form-control" placeholder="{{ translate('Ex: Size') }}" maxlength="255"
                                                    {{$lang['status'] == true ? 'required':''}}
                                                    @if($lang['status'] == true) oninvalid="document.getElementById('{{$lang['code']}}-link').click()" @endif>
                                        </div>
                                        <input type="hidden" name="lang[]" value="{{ $lang['code'] }}">
                                    @endforeach
                                    @else
                                        <div class="lang_form col-sm-6" id="{{ $defaultLanguage }}-form">
                                            <label class="form-label"
                                                    for="exampleFormControlInput1">{{translate('category')}} {{ translate('name') }}
                                                ({{ strtoupper($defaultLanguage) }})</label>
                                            <input type="text" name="name[]" class="form-control" maxlength="255"
                                                    placeholder="{{ translate('New Category') }}" required>
                                        </div>
                                        <input type="hidden" name="lang[]" value="{{ $defaultLanguage }}">
                                    @endif
                                    <input name="position" value="0" hidden>
                                    <div class="col-sm-6">
                                        <div>
                                            <div class="text-center mb-3">
                                                <img id="viewer" class="img--105" src="{{ asset('public/assets/admin/img/160x160/1.png') }}" alt="{{ translate('image') }}" />
                                            </div>
                                        </div>
                                        <label class="form-label text-capitalize">{{ translate('category image') }}</label><small class="text-danger">* ( {{ translate('ratio') }}3:1 )</small>
                                        <div class="custom-file">
                                            <input type="file" name="image" id="customFileEg1" class="custom-file-input"
                                                    accept=".jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*" required oninvalid="document.getElementById('en-link').click()">
                                            <label class="custom-file-label" for="customFileEg1">{{ translate('choose') }}
                                                {{ translate('file') }}</label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="btn--container justify-content-end">
                                            <button type="reset" class="btn btn--reset">{{translate('reset')}}</button>
                                            <button type="submit" class="btn btn--primary">{{translate('submit')}}</button>
                                        </div>
                                    </div>
                                </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-sm-12 col-lg-12">
                <div class="card">
                    <div class="card-header border-0">
                        <div class="card--header">
                            <h5 class="card-title">{{translate('Category Table')}} <span class="badge badge-soft-secondary">{{ $categories->total() }}</span> </h5>
                            <form action="{{url()->current()}}" method="GET">
                                <div class="input-group">
                                    <input id="datatableSearch_" type="search" name="search" maxlength="255"
                                           class="form-control pl-5"
                                           placeholder="{{translate('Search_by_Name')}}" aria-label="Search"
                                           value="{{$search}}" required autocomplete="off">
                                           <i class="tio-search tio-input-search"></i>
                                    <div class="input-group-append">
                                        <button type="submit" class="input-group-text">
                                            {{translate('search')}}
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <!-- Table -->
                    <div class="table-responsive datatable-custom">
                        <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                            <thead class="thead-light">
                            <tr>
                                <th class="text-center">{{translate('#')}}</th>
                                <th>{{translate('category_image')}}</th>
                                <th>{{translate('name')}}</th>
                                <th>{{translate('status')}}</th>
                                <th>{{translate('priority')}}</th>
                                <th class="text-center">{{translate('action')}}</th>
                            </tr>
                            </thead>

                            <tbody>
                            @foreach($categories as $key=>$category)
                                <tr>
                                    <td class="text-center">{{$categories->firstItem()+$key}}</td>
                                    <td>
                                        <img src="{{$category->imageFullPath}}" class="img--50 ml-3"
                                             alt="{{ translate('category') }}">
                                    </td>
                                    <td>
                                    <span class="d-block font-size-sm text-body text-trim-50">
                                        {{$category['name']}}
                                    </span>
                                    </td>
                                    <td>

                                        <label class="toggle-switch">
                                            <input type="checkbox"
                                                class="toggle-switch-input status-change-alert" id="stocksCheckbox{{ $category->id }}"
                                                   data-route="{{ route('admin.category.status', [$category->id, $category->status ? 0 : 1]) }}"
                                                   data-message="{{ $category->status? translate('you_want_to_disable_this_category'): translate('you_want_to_active_this_category') }}"
                                                {{ $category->status ? 'checked' : '' }}>
                                            <span class="toggle-switch-label text">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>

                                    </td>
                                    <td>
                                        <div class="max-85">
                                            <select name="priority" class="custom-select"
                                                    onchange="location.href='{{ route('admin.category.priority', ['id' => $category['id'], 'priority' => '']) }}' + this.value">
                                                @for($i = 1; $i <= 10; $i++)
                                                    <option value="{{ $i }}" {{ $category->priority == $i ? 'selected' : '' }}>{{ $i }}</option>
                                                @endfor
                                            </select>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="action-btn"
                                                href="{{route('admin.category.edit',[$category['id']])}}">
                                            <i class="tio-edit"></i></a>
                                            <a class="action-btn btn--danger btn-outline-danger form-alert" href="javascript:"
                                               data-id="category-{{$category['id']}}"
                                               data-message="{{ translate("Want to delete this") }}?">
                                                <i class="tio-delete-outlined"></i>
                                            </a>
                                        </div>
                                        <form action="{{route('admin.category.delete',[$category['id']])}}"
                                                method="post" id="category-{{$category['id']}}">
                                            @csrf @method('delete')
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>


                        @if(count($categories) == 0)
                        <div class="text-center p-4">
                            <img class="w-120px mb-3" src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="{{ translate('image') }}">
                            <p class="mb-0">{{translate('No_data_to_show')}}</p>
                        </div>
                        @endif

                        <table>
                            <tfoot>
                            {!! $categories->links() !!}
                            </tfoot>
                        </table>

                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin/js/category.js') }}"></script>
    <script>
        "use strict";

        $(".lang_link").click(function(e){
            e.preventDefault();
            $(".lang_link").removeClass('active');
            $(".lang_form").addClass('d-none');
            $(this).addClass('active');

            let form_id = this.id;
            let lang = form_id.split("-")[0];
            console.log(lang);
            $("#"+lang+"-form").removeClass('d-none');
            if(lang == '{{$defaultLanguage}}')
            {
                $(".from_part_2").removeClass('d-none');
            }
            else
            {
                $(".from_part_2").addClass('d-none');
            }
        });
    </script>
@endpush
