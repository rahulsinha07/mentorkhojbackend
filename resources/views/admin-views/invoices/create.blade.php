@extends('layouts.admin.app')
@section('title', translate('Create Invoice'))
@section('content')
<div class="content container-fluid">
    <div class="page-header"><h1 class="page-header-title">{{ translate('Create Invoice') }}</h1></div>
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    @include('admin-views.invoices.partials.form')
</div>
@endsection
