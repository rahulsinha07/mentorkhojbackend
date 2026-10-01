@php
    $sessionChatMessages = $sessionChatMessages ?? collect();
    $adminComposeMenteeId = $adminComposeMenteeId
        ?? optional($sessionChatMessages->first())->mentee_user_id
        ?? null;
@endphp
<div class="card mb-3">
    <div class="card-header">
        <h5 class="card-title mb-0">
            Session chat
            <span class="badge badge-soft-secondary">{{ $sessionChatMessages->count() }}</span>
        </h5>
        <small class="text-muted">Free threads cap the student at 5 messages. Email/phone in chat is blocked. ADMIN support is unlimited. Paid mentorship messaging stays open for 1 week after the last grant/session (or while a session is planned); admin can override on the Session packs Messaging column.</small>
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
                        <td>{{ $msg->sender_role === 'admin' ? 'ADMIN' : ($msg->mentor?->display_name ?? '—') }}</td>
                        <td style="max-width:420px;white-space:normal;">{{ $msg->body }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($adminComposeMenteeId)
        <div class="card-footer">
            <form method="POST" action="{{ route('admin.mentor.session-messages.store') }}">
                @csrf
                <input type="hidden" name="mentee_user_id" value="{{ (int) $adminComposeMenteeId }}">
                <label class="input-label">Send as ADMIN (unlimited support thread)</label>
                <textarea name="body" rows="3" required maxlength="2000" class="form-control mb-2"
                          placeholder="Message the student as ADMIN…"></textarea>
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="tio-send"></i> Send as ADMIN
                </button>
            </form>
        </div>
    @endif
</div>
