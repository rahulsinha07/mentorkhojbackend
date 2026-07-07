@extends('layouts.admin.app')

@section('title', translate('Update delivery-man'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/employee.png')}}" class="w--24" alt="{{ translate('deliveryman') }}">
                </span>
                <span>
                    {{translate('update deliveryman')}}
                </span>
            </h1>
        </div>
        <form action="{{route('admin.delivery-man.update',[$deliveryman['id']])}}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <span class="card-header-icon">
                            <i class="tio-user"></i>
                        </span> {{translate('General Information')}}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="input-label" for="exampleFormControlInput1">{{translate('First Name')}}</label>
                                            <input type="text" name="f_name" value="{{$deliveryman['f_name']}}" class="form-control"
                                                   placeholder="{{translate('Ex : First Name')}}" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="input-label" for="exampleFormControlInput1">{{translate('Last Name')}}</label>
                                            <input type="text" name="l_name" value="{{$deliveryman['l_name']}}" class="form-control"
                                                   placeholder="{{translate('Ex : Last Name')}}" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <label class="input-label" for="exampleFormControlInput1">{{translate('phone')}}</label>
                                    <input type="text" name="phone" value="{{$deliveryman['phone']}}" class="form-control"
                                            placeholder="{{ translate('Ex : 017********') }}"
                                            required>
                                </div>
                                <div class="col-md-12">
                                    <label class="input-label" for="exampleFormControlInput1">{{translate('branch')}}</label>
                                    <select name="branch_id" class="form-control">
                                        <option value="0" {{$deliveryman['branch_id']==0?'selected':''}}>{{translate('all')}}</option>
                                        @foreach(\App\Model\Branch::all() as $branch)
                                            <option value="{{$branch['id']}}" {{$deliveryman['branch_id']==$branch['id']?'selected':''}}>{{$branch['name']}}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="input-label" for="exampleFormControlInput1">{{translate('identity')}} {{translate('type')}}</label>
                                    <select name="identity_type" class="form-control">
                                        <option value="passport" {{$deliveryman['identity_type']=='passport'?'selected':''}}>{{translate('passport')}}</option>
                                        <option value="driving_license" {{$deliveryman['identity_type']=='driving_license'?'selected':''}}>{{translate('driving')}} {{translate('license')}}</option>
                                        <option value="nid" {{$deliveryman['identity_type']=='nid'?'selected':''}}>{{translate('nid')}}</option>
                                        <option value="restaurant_id" {{$deliveryman['identity_type']=='restaurant_id'?'selected':''}}>{{translate('store')}} {{translate('id')}}</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="input-label" for="exampleFormControlInput1">{{translate('identity')}} {{translate('number')}}</label>
                                    <input type="text" name="identity_number" value="{{$deliveryman['identity_number']}}"
                                            class="form-control" placeholder="{{ translate('Ex : DH-23434-LS') }}" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label d-none d-md-block "></label>
                                <div class="mb-4 text-center">
                                    <img class="initial-24" id="viewer"
                                         src="{{$deliveryman->imageFullPath}}"
                                         alt="{{ translate('deliveryman') }}"/>
                                </div>
                                <div class="form-group mb-0">
                                    <label class="form-label d-block">{{translate('deliveryman')}} {{translate('image')}} <small class="text-danger">* ( {{translate('ratio')}} 1:1 )</small></label>
                                    <div class="custom-file">
                                        <input type="file" name="image" id="customFileUpload" class="custom-file-input h--45px" accept=".jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*">
                                        <label class="custom-file-label h--45px" for="customFileUpload"></label>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="form-label d-block mb-2">
                                    {{ translate('Identity Image') }}
                                </label>
                                <div class="product--coba">
                                    <div class="row g-2" id="coba">
                                        @foreach($deliveryman->identityImageFullPath as $identification_image)
                                            <div class="two__item w-50">
                                                <div class="max-h-140px existing-item">
                                                    <img src="{{$identification_image}}" alt="{{ translate('identity_image') }}">
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title">
                        <span class="card-header-icon">
                            <i class="tio-user"></i>
                        </span> {{translate('General Information')}}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4 col-sm-6">
                            <label class="input-label" for="exampleFormControlInput1">{{translate('email')}}</label>
                            <input type="email" value="{{$deliveryman['email']}}" name="email" class="form-control"
                                    placeholder="{{ translate('Ex : ex@example.com') }}"
                                    required>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <label class="input-label" for="exampleFormControlInput1">{{translate('Password')}}</label>
                            <input type="text" name="password" class="form-control" placeholder="{{ translate('7+ character') }}">
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <label class="input-label" for="exampleFormControlInput1">{{translate('Confirm Password')}}</label>
                            <input type="text" name="password_confirmation" class="form-control" placeholder="{{ translate('7+ character') }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="btn--container justify-content-end mt-3">
                <button type="reset" class="btn btn--reset">{{translate('reset')}}</button>
                <button type="submit" class="btn btn--primary">{{translate('update')}}</button>
            </div>
        </form>
    </div>

@endsection

@push('script_2')
    <script>
        "use strict";

        function readURL(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();

                reader.onload = function (e) {
                    $('#viewer').attr('src', e.target.result);
                }

                reader.readAsDataURL(input.files[0]);
            }
        }

        $("#customFileUpload").change(function () {
            readURL(this);
        });
    </script>

    <script src="{{asset('public/assets/admin/js/spartan-multi-image-picker.js')}}"></script>
    <script type="text/javascript">
        $(function () {
            $("#coba").spartanMultiImagePicker({
                fieldName: 'identity_image[]',
                maxCount: 2,
                rowHeight: '140px',
                groupClassName: 'two__item',
                maxFileSize: '',
                placeholderImage: {
                    image: '{{asset('public/assets/admin/img/upload-vertical.png')}}',
                    width: '100%'
                },
                dropFileLabel: "Drop Here",
                onAddRow: function (index, file) {

                },
                onRenderedPreview: function (index) {

                },
                onRemoveRow: function (index) {

                },
                onExtensionErr: function (index, file) {
                    toastr.error('{{ translate("Please only input png or jpg type file") }}', {
                        CloseButton: true,
                        ProgressBar: true
                    });
                },
                onSizeErr: function (index, file) {
                    toastr.error('{{ translate("File size too big") }}', {
                        CloseButton: true,
                        ProgressBar: true
                    });
                }
            });
        });
    </script>
@endpush
