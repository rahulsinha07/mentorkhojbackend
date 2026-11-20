@extends('layouts.admin.app')

@section('title', translate('College Cutoffs'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span>{{translate('College Cutoffs — College Predictor Data')}}</span>
            </h1>
            <p class="text-muted mb-0">
                {{translate('Upload the JoSAA opening/closing rank CSV for a year + round here. The /jee-college-predictor tool reads this data automatically — no deploy needed. Re-uploading the same year+round replaces that batch.')}}
            </p>
        </div>

        <div class="row">
            <div class="col-sm-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{translate('Import a batch')}}</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{route('admin.college-cutoffs.import')}}" method="post" enctype="multipart/form-data">
                            @csrf
                            <div class="row g-3 align-items-end">
                                <div class="col-md-2">
                                    <label class="form-label">{{translate('Year')}}</label>
                                    <input type="number" name="year" class="form-control" min="2000" max="2100" value="{{old('year')}}" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">{{translate('Round')}}</label>
                                    <input type="number" name="round" class="form-control" min="1" max="10" value="{{old('round')}}" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">{{translate('CSV file')}}</label>
                                    <input type="file" name="csv_file" class="form-control" accept=".csv,.txt" required>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn--primary w-100">{{translate('Import')}}</button>
                                </div>
                            </div>
                            <p class="mt-3 mb-0 text-xs text-muted">
                                {{translate('Required header (exact):')}}
                                <code>institute,program,quota,seat_type,gender,opening_rank,closing_rank</code>
                            </p>
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
                                    <th>{{translate('Round')}}</th>
                                    <th>{{translate('Rows')}}</th>
                                    <th class="text-end">{{translate('Action')}}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($batches as $batch)
                                    <tr>
                                        <td class="align-middle">{{$batch->year}}</td>
                                        <td class="align-middle">{{$batch->round}}</td>
                                        <td class="align-middle">{{number_format($batch->row_count)}}</td>
                                        <td class="align-middle text-end">
                                            <form action="{{route('admin.college-cutoffs.destroy', [$batch->year, $batch->round])}}" method="post" class="d-inline" onsubmit="return confirm('{{translate('Remove this entire batch?')}}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">{{translate('Delete')}}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">{{translate('No batches imported yet.')}}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
