@extends('layouts.admin.app')

@section('title', translate('Update category'))

@section('content')
    <div class="content container-fluid">

        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/category.png')}}" class="w--24" alt="{{ translate('category') }}">
                </span>
                <span>
                    @if($category->parent_id == 0)
                        {{ translate('category Update') }}
                    @else
                    {{ translate('Sub Category Update') }}
                    @endif
                </span>
            </h1>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{route('admin.category.update',[$category['id']])}}" method="post" enctype="multipart/form-data">
                    @csrf
                    @php($data = Helpers::get_business_settings('language'))
                    @php($defaultLanguage = Helpers::get_default_language())

                    @if($data && array_key_exists('code', $data[0]))
                            <ul class="nav nav-tabs d-inline-flex {{$category->parent_id == 0 ? 'mb--n-30' : 'mb-4'}}">
                                @foreach($data as $lang)
                                <li class="nav-item">
                                    <a class="nav-link lang_link {{$lang['default'] == true? 'active':''}}" href="#"
                                    id="{{$lang['code']}}-link">{{ Helpers::get_language_name($lang['code']).'('.strtoupper($lang['code']).')'}}</a>
                                </li>
                                @endforeach
                            </ul>
                    @endif

                    @if($data && array_key_exists('code', $data[0]))
                    <div class="row align-items-end g-4">
                        @foreach($data as $lang)
                            <?php
                                if (count($category['translations'])) {
                                    $translate = [];
                                    foreach ($category['translations'] as $t) {
                                        if ($t->locale == $lang['code'] && $t->key == "name") {
                                            $translate[$lang['code']]['name'] = $t->value;
                                        }
                                    }
                                }
                            ?>
                            <div class="col-sm-6 {{$lang['default'] == false ? 'd-none':''}} lang_form" id="{{$lang['code']}}-form">
                                <label class="input-label" for="exampleFormControlInput1">{{translate('name')}}
                                    ({{strtoupper($lang['code'])}})</label>
                                <input type="text" name="name[]" maxlength="255" value="{{$lang['code'] == 'en' ? $category['name'] : ($translate[$lang['code']]['name']??'')}}"
                                       class="form-control" @if($lang['status'] == true)
                                           oninvalid="document.getElementById('{{$lang['code']}}-link').click()" @endif
                                       placeholder="{{ translate('New Category') }}" {{$lang['status'] == true ? 'required':''}}>
                            </div>
                            <input type="hidden" name="lang[]" value="{{$lang['code']}}">
                        @endforeach
                        @else
                            <div class="col-sm-6 lang_form" id="{{$defaultLanguage}}-form">
                                <label class="input-label"
                                        for="exampleFormControlInput1">{{translate('name')}}
                                    ({{strtoupper($defaultLanguage)}})</label>
                                <input type="text" name="name[]" value="{{$category['name']}}"
                                        class="form-control" oninvalid="document.getElementById('en-link').click()"
                                        placeholder="{{ translate('New Category') }}" required>
                            </div>
                            <input type="hidden" name="lang[]" value="{{$defaultLanguage}}">
                        @endif
                        <input name="position" value="0" hidden>
                        @if($category->parent_id == 0)
                        <div class="col-sm-6">
                            <div class="text-center">
                                <img class="img--105" id="viewer"
                                    src="{{$category->imageFullPath}}"
                                     alt="{{ translate('category') }}"/>
                            </div>
                            <label>{{translate('image')}}</label><small class="text-danger">* ( {{translate('ratio')}} 3:1 )</small>
                            <div class="custom-file">
                                <input type="file" name="image" id="customFileEg1" class="custom-file-input"
                                        accept=".jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*">
                                <label class="custom-file-label" for="customFileEg1">{{translate('choose')}} {{translate('file')}}</label>
                            </div>
                        </div>
                        @endif
                        <div class="col-12">
                            <div class="btn--container justify-content-end">
                                <button type="reset" class="btn btn--reset">{{translate('reset')}}</button>
                                <button type="submit" class="btn btn--primary">{{translate('update')}}</button>
                            </div>
                        </div>
                    </div>
                </form>
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
