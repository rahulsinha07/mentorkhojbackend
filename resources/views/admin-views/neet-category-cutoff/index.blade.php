@extends('layouts.admin.app')

@section('title', translate('NEET Category Cutoffs'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span>{{translate('NEET Category Cutoffs — Cutoff Checker Data')}}</span>
            </h1>
            <p class="text-muted mb-0">
                {{translate('Add each year\'s official NTA qualifying cutoff (per category) here once published. The /neet-cutoff-checker tool reads this automatically — no deploy needed.')}}
            </p>
        </div>

        <div class="row">
            <div class="col-sm-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{translate('Add new cutoff')}}</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{route('admin.neet-category-cutoffs.store')}}" method="post">
                            @csrf
                            <div class="row g-3 align-items-end">
                                <div class="col-md-2">
                                    <label class="form-label">{{translate('Year')}}</label>
                                    <input type="number" name="year" class="form-control" min="2000" max="2100" value="{{old('year')}}" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">{{translate('Category')}}</label>
                                    <select name="category" class="form-control" required>
                                        <option value="general">General</option>
                                        <option value="ews">EWS</option>
                                        <option value="obc_ncl">OBC-NCL</option>
                                        <option value="sc">SC</option>
                                        <option value="st">ST</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">{{translate('Percentile')}}</label>
                                    <input type="number" name="percentile" class="form-control" min="1" max="100" value="{{old('percentile')}}" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">{{translate('Cutoff low')}}</label>
                                    <input type="number" name="cutoff_marks_low" class="form-control" min="0" max="720" value="{{old('cutoff_marks_low')}}" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">{{translate('Cutoff high')}}</label>
                                    <input type="number" name="cutoff_marks_high" class="form-control" min="0" max="720" value="{{old('cutoff_marks_high')}}" required>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn--primary w-100">{{translate('Add')}}</button>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">{{translate('Source note')}} <small class="text-muted">({{translate('optional')}})</small></label>
                                    <input type="text" name="source_note" class="form-control" placeholder="{{translate('e.g. NTA NEET (UG) result, 14 June 2025')}}" value="{{old('source_note')}}">
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-sm-12">
                <div class="card">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>{{translate('Year')}}</th>
                                    <th>{{translate('Category')}}</th>
                                    <th>{{translate('Percentile')}}</th>
                                    <th>{{translate('Cutoff low')}}</th>
                                    <th>{{translate('Cutoff high')}}</th>
                                    <th>{{translate('Source note')}}</th>
                                    <th class="text-end">{{translate('Action')}}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($cutoffs as $cutoff)
                                    <tr>
                                        <td class="align-middle">{{$cutoff->year}}</td>
                                        <td class="align-middle text-uppercase">{{$cutoff->category}}</td>
                                        <td class="align-middle">{{$cutoff->percentile}}th</td>
                                        <td class="align-middle">
                                            <input type="number" name="cutoff_marks_low" form="neet-cutoff-update-{{$cutoff->id}}" class="form-control form-control-sm" style="width:90px" value="{{$cutoff->cutoff_marks_low}}" min="0" max="720" required>
                                        </td>
                                        <td class="align-middle">
                                            <input type="number" name="cutoff_marks_high" form="neet-cutoff-update-{{$cutoff->id}}" class="form-control form-control-sm" style="width:90px" value="{{$cutoff->cutoff_marks_high}}" min="0" max="720" required>
                                        </td>
                                        <td class="align-middle">
                                            <input type="text" name="source_note" form="neet-cutoff-update-{{$cutoff->id}}" class="form-control form-control-sm" value="{{$cutoff->source_note}}">
                                        </td>
                                        <td class="align-middle text-end">
                                            <button type="submit" form="neet-cutoff-update-{{$cutoff->id}}" class="btn btn-sm btn--primary">{{translate('Save')}}</button>
                                            <button type="submit" form="neet-cutoff-delete-{{$cutoff->id}}" class="btn btn-sm btn-outline-danger" onclick="return confirm('{{translate('Remove this cutoff?')}}');">{{translate('Delete')}}</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">{{translate('No cutoffs yet.')}}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @foreach($cutoffs as $cutoff)
            <form id="neet-cutoff-update-{{$cutoff->id}}" action="{{route('admin.neet-category-cutoffs.update', $cutoff->id)}}" method="post" class="d-none">
                @csrf
                @method('PUT')
            </form>
            <form id="neet-cutoff-delete-{{$cutoff->id}}" action="{{route('admin.neet-category-cutoffs.destroy', $cutoff->id)}}" method="post" class="d-none">
                @csrf
                @method('DELETE')
            </form>
        @endforeach
    </div>
@endsection
