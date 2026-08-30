<div class="col-12 mt-1 mb-1">
    <h6 class="text-capitalize mb-0">{{ translate('Mentor Bookings') }}</h6>
</div>

<div class="col-sm-6 col-lg-3">
    <a class="dashboard--card h-100" href="{{ route('admin.mentor.bookings.list', ['status' => 'requested']) }}">
        <h6 class="subtitle">{{ translate('Requested') }}</h6>
        <h2 class="title">{{ $data['mentor_requested'] }}</h2>
        <img src="{{ asset('/public/assets/admin/img/dashboard/pending.png') }}" alt="" class="dashboard-icon">
    </a>
</div>

<div class="col-sm-6 col-lg-3">
    <a class="dashboard--card h-100" href="{{ route('admin.mentor.bookings.list', ['status' => 'confirmed']) }}">
        <h6 class="subtitle">{{ translate('Confirmed') }}</h6>
        <h2 class="title">{{ $data['mentor_confirmed'] }}</h2>
        <img src="{{ asset('/public/assets/admin/img/dashboard/confirmed.png') }}" alt="" class="dashboard-icon">
    </a>
</div>

<div class="col-sm-6 col-lg-3">
    <a class="dashboard--card h-100" href="{{ route('admin.mentor.bookings.list', ['status' => 'completed']) }}">
        <h6 class="subtitle">{{ translate('Completed') }}</h6>
        <h2 class="title">{{ $data['mentor_completed'] }}</h2>
        <img src="{{ asset('/public/assets/admin/img/dashboard/packaging.png') }}" alt="" class="dashboard-icon">
    </a>
</div>

<div class="col-sm-6 col-lg-3">
    <a class="dashboard--card h-100" href="{{ route('admin.mentor.bookings.list', ['status' => 'cancelled']) }}">
        <h6 class="subtitle">{{ translate('Cancelled') }}</h6>
        <h2 class="title">{{ $data['mentor_cancelled'] }}</h2>
        <img src="{{ asset('/public/assets/admin/img/dashboard/out-for-delivery.png') }}" alt="" class="dashboard-icon">
    </a>
</div>

<div class="col-12 mt-3 mb-1">
    <h6 class="text-capitalize mb-0">{{ translate('Demo Bookings') }}</h6>
</div>

<div class="col-sm-6 col-lg-3">
    <a class="order--card h-100" href="{{ route('admin.demo-bookings.index', ['status' => 'new']) }}">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center">
                <img src="{{ asset('public/assets/admin/img/delivery/1.png') }}" alt="dashboard" class="oder--card-icon">
                <span>{{ translate('New') }}</span>
            </h6>
            <span class="card-title text-success">{{ $data['demo_new'] }}</span>
        </div>
    </a>
</div>

<div class="col-sm-6 col-lg-3">
    <a class="order--card h-100" href="{{ route('admin.demo-bookings.index', ['status' => 'contacted']) }}">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center">
                <img src="{{ asset('public/assets/admin/img/delivery/2.png') }}" alt="{{ translate('dashboard') }}" class="oder--card-icon">
                <span>{{ translate('Contacted') }}</span>
            </h6>
            <span class="card-title text-info">{{ $data['demo_contacted'] }}</span>
        </div>
    </a>
</div>

<div class="col-sm-6 col-lg-3">
    <a class="order--card h-100" href="{{ route('admin.demo-bookings.index', ['status' => 'scheduled']) }}">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center">
                <img src="{{ asset('public/assets/admin/img/delivery/3.png') }}" alt="{{ translate('dashboard') }}" class="oder--card-icon">
                <span>{{ translate('Scheduled') }}</span>
            </h6>
            <span class="card-title text-warning">{{ $data['demo_scheduled'] }}</span>
        </div>
    </a>
</div>

<div class="col-sm-6 col-lg-3">
    <a class="order--card h-100" href="{{ route('admin.demo-bookings.index', ['status' => 'converted']) }}">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center">
                <img src="{{ asset('public/assets/admin/img/delivery/4.png') }}" alt="{{ translate('dashboard') }}" class="oder--card-icon">
                <span>{{ translate('Converted') }}</span>
            </h6>
            <span class="card-title text-success">{{ $data['demo_converted'] }}</span>
        </div>
    </a>
</div>
