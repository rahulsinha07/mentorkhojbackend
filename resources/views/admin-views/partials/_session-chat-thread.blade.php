@php
    $sessionChatMessages = $sessionChatMessages ?? collect();
@endphp
<div class="card mb-3">
    <div class="card-header">
        <h5 class="card-title mb-0">
            Session chat
            <span class="badge badge-soft-secondary">{{ $sessionChatMessages->count() }}</span>
        </h5>
        <small class="text-muted">Free threads cap the student at 5 messages. Email/phone in chat is blocked.</small>
    </div>
    @if($sessionChatMessages->isEmpty())
        <div class="card-body text-muted">No messages yet.</div>
    @else
        <div class="table-responsive">
            <table class="table table-borderless table-thead-bordered table-nowrap card-table mb-0">
                <thead class="thead-light">
                <tr>
                    <th>When</th>
                    <th>From</th>
                    <th>Student</th>
                    <th>Mentor</th>
                    <th>Message</th>
                </tr>
                </thead>
                <tbody>
                @foreach($sessionChatMessages as $msg)
                    <tr>
                        <td>{{ optional($msg->created_at)->format('d M Y H:i') }}</td>
                        <td class="text-capitalize">{{ $msg->sender_role }}</td>
                        <td>{{ \App\CentralLogics\SessionChatLogic::studentFirstName($msg->mentee) }}</td>
                        <td>{{ $msg->mentor?->display_name ?? '—' }}</td>
                        <td style="max-width:420px;white-space:normal;">{{ $msg->body }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
