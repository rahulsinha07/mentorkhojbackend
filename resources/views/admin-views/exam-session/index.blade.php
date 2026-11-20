@extends('layouts.admin.app')

@section('title', translate('Exam Sessions'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span>{{translate('Exam Sessions — Rank Predictor Data')}}</span>
            </h1>
            <p class="text-muted mb-0">
                {{translate('Add the total appeared-candidate count for each JEE Main session here once NTA publishes it. The /jee-rank-predictor tool on the website reads this data automatically — no deploy needed.')}}
            </p>
        </div>

        <div class="row">
            <div class="col-sm-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{translate('Add new session')}}</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{route('admin.exam-sessions.store')}}" method="post">
                            @csrf
                            <div class="row g-3 align-items-end">
                                <div class="col-md-2">
                                    <label class="form-label">{{translate('Exam')}}</label>
                                    <select name="exam_type" class="form-control" required>
                                        <option value="jee_main">JEE Main</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">{{translate('Year')}}</label>
                                    <input type="number" name="year" class="form-control" min="2000" max="2100" value="{{old('year')}}" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">{{translate('Session')}}</label>
                                    <select name="session" class="form-control" required>
                                        <option value="january">January</option>
                                        <option value="april">April</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">{{translate('Total candidates appeared')}}</label>
                                    <input type="number" name="total_candidates" class="form-control" min="1" value="{{old('total_candidates')}}" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">{{translate('Source note')}} <small class="text-muted">({{translate('optional')}})</small></label>
                                    <input type="text" name="source_note" class="form-control" placeholder="{{translate('e.g. NTA press release, Careers360')}}" value="{{old('source_note')}}">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn--primary">{{translate('Add session')}}</button>
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
                                    <th>{{translate('Exam')}}</th>
                                    <th>{{translate('Year')}}</th>
                                    <th>{{translate('Session')}}</th>
                                    <th>{{translate('Total candidates')}}</th>
                                    <th>{{translate('Source note')}}</th>
                                    <th class="text-end">{{translate('Action')}}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($examSessions as $examSession)
                                    <tr>
                                        <td class="align-middle">{{strtoupper(str_replace('_', ' ', $examSession->exam_type))}}</td>
                                        <td class="align-middle">{{$examSession->year}}</td>
                                        <td class="align-middle text-capitalize">{{$examSession->session}}</td>
                                        <td class="align-middle">
                                            <input type="number" name="total_candidates" form="exam-session-update-{{$examSession->id}}" class="form-control form-control-sm" style="width:140px" value="{{$examSession->total_candidates}}" min="1" required>
                                        </td>
                                        <td class="align-middle">
                                            <input type="text" name="source_note" form="exam-session-update-{{$examSession->id}}" class="form-control form-control-sm" value="{{$examSession->source_note}}">
                                        </td>
                                        <td class="align-middle text-end">
                                            <button type="submit" form="exam-session-update-{{$examSession->id}}" class="btn btn-sm btn--primary">{{translate('Save')}}</button>
                                            <button type="submit" form="exam-session-delete-{{$examSession->id}}" class="btn btn-sm btn-outline-danger" onclick="return confirm('{{translate('Remove this session?')}}');">{{translate('Delete')}}</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">{{translate('No exam sessions yet.')}}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @foreach($examSessions as $examSession)
            <form id="exam-session-update-{{$examSession->id}}" action="{{route('admin.exam-sessions.update', $examSession->id)}}" method="post" class="d-none">
                @csrf
                @method('PUT')
            </form>
            <form id="exam-session-delete-{{$examSession->id}}" action="{{route('admin.exam-sessions.destroy', $examSession->id)}}" method="post" class="d-none">
                @csrf
                @method('DELETE')
            </form>
        @endforeach
    </div>
@endsection
