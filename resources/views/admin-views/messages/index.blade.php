@extends('layouts.admin.app')

@section('title', translate('Messages'))

@push('css_or_js')
<link rel="stylesheet" href="{{asset('/public/assets/admin/css/lightbox.min.css')}}">
@endpush

@section('content')
<div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/messages.png')}}" class="w--24" alt="{{ translate('message') }}">
                </span>
                <span>{{translate('Messages')}} <span class="badge badge-soft-primary ml-2" id="conversation_count"></span></span>
            </h1>
        </div>

        <div class="row g-0">
            <div class="col-md-4 col-xxl-3">
                <div class="card h-100 conv--sidebar--card rounded-right-0">
                    <div class="card-header border-0 px-0 mx-20px">
                        <div class="conv-open-user w-100">
                            <img class="w-47px" src="{{auth('admin')->user()->imageFullPath}}"
                                    alt="{{ translate('admin') }}">
                            <div class="info">
                                <h6 class="subtitle mb-0">{{auth('admin')->user()->f_name}} {{auth('admin')->user()->l_name}}</h6>
                                <span>{{auth('admin')->user()->role->name}}</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-0 px-0">
                        <div class="input-group-overlay input-group-sm mb-3 mx-20px">
                            <input placeholder="{{ translate('Search user') }}"
                                class="cz-filter-search form-control form-control-sm appended-form-control"
                                type="text" id="search-conversation-user" autocomplete="off">
                        </div>
                        <div class="conv--sidebar" id="conversation_sidebar">
                            @php($array=[])
                            @foreach($conversations as $conv)
                                @if(in_array($conv->user_id,$array)==false)
                                    @php(array_push($array,$conv->user_id))
                                    @php($user=\App\User::find($conv->user_id))
                                    @if(isset($user))
                                    @php($unchecked=\App\Model\Conversation::where(['user_id'=>$conv->user_id,'checked'=>0])->count())
                                        <div class="sidebar_primary_div customer-list view-conversation-details {{$unchecked!=0?'conv-active':''}}"
                                                data-url="{{route('admin.message.view',[$conv->user_id])}}"
                                                data-customer="customer-{{$conv->user_id}}"
                                                id="customer-{{$conv->user_id}}">
                                            <div class="conv-open-user w-100">
                                                <img class="w-47px" src="{{$user->imageFullPath}}"
                                                    alt="{{ translate('admin') }}">
                                                <span class="status active"></span>
                                                <div class="info">
                                                    <h6 class="subtitle mb-0 sidebar_name chat-count">{{$user['f_name'].' '.$user['l_name']}}</h6>
                                                    <span>{{ translate('customer') }}</span>
                                                </div>
                                                <span class="badge badge-info" id="counter-{{$conv->user_id}}">{{$unchecked!=0?$unchecked:''}}</span>
                                            </div>
                                        </div>
                                    @endif
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-8 col-xxl-9 pl-0 view-conversion" id="view-conversation">
                <div class="h-100 d-flex justify-content-center align-items-center card __shadow rounded-left-0 py-5 py-md-0 text-center">
                    <img src="{{asset('/public/assets/admin/img/view-conv.png')}}" class="mw-100" alt="{{ translate('image') }}">
                    <div>
                        {{translate('Click from the customer list to view conversation')}}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        $("#search-conversation-user").on("keyup", function () {
            var input_value = this.value.toLowerCase().trim();

            let sidebar_primary_div = $(".sidebar_primary_div");
            let sidebar_name = $(".sidebar_name");

            for (i = 0; i < sidebar_primary_div.length; i++) {
                const text_value = sidebar_name[i].innerText;
                if (text_value.toLowerCase().indexOf(input_value) > -1) {
                    sidebar_primary_div[i].style.display = "";
                } else {
                    sidebar_primary_div[i].style.setProperty("display", "none", "important");
                }
            }
        });

        let current_selected_user = null;

        $('.view-conversation-details').on('click', function (){
            let url = $(this).data('url');
            let customer = $(this).data('customer');
            viewConvs(url, customer)
        });

        function viewConvs(url, id_to_active) {
            current_selected_user = id_to_active;

            var counter_element = $('#counter-'+ current_selected_user.slice(9));
            var customer_element = $('#'+current_selected_user);
            if(counter_element !== "undefined") {
                counter_element.empty();
                counter_element.removeClass("badge");
                counter_element.removeClass("badge-info");
            }
            if(customer_element !== "undefined") {
                customer_element.removeClass("conv-active");
            }

            $('.customer-list').removeClass('conv-active');
            $('#' + id_to_active).addClass('conv-active');
            $.get({
                url: url,
                success: function (data) {
                    $('#view-conversation').html(data.view);
                }
            });
        }

        $('.save-conversation-reply').on('click', function (){
            let url = $(this).data('url');
            replyConvs(url)
        });

        function replyConvs(url) {
            var form = document.querySelector('#reply-form');
            var formdata = new FormData(form);

            if (!formdata.get('reply') && !formdata.get('images[]')) {
                toastr.error('{{translate("Reply message is required!")}}', {
                    CloseButton: true,
                    ProgressBar: true
                });
                return "false";
            }

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.ajax({
                url: url,
                type: 'POST',
                data: formdata,
                processData: false,
                contentType: false,
                success: function (data) {
                    toastr.success('{{translate('Message sent')}}', {
                        CloseButton: true,
                        ProgressBar: true
                    });
                    $('#view-conversation').html(data.view);
                },
                error() {
                    toastr.error('{{translate("Reply message is required!")}}', {
                        CloseButton: true,
                        ProgressBar: true
                    });
                }
            });
        }

        function renderUserList() {
            $('#loading').show();
            $.ajax({
                url: "{{route('admin.message.get_conversations')}}",
                type: 'GET',
                cache: false,
                success: function (response) {
                    $('#loading').hide();
                    $("#conversation_sidebar").html(response.conversation_sidebar)

                },
                error: function (err) {
                    $('#loading').hide();
                }
            });
        }

    </script>

@endpush
