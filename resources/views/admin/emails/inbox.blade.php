@extends('layouts.admin')

@section('title', 'Email Inbox')

@section('content')
<div class="page-head">
    <div class="page-sub" style="margin: 0;">Every email in and out, with the deal or client it belongs to.</div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openComposeModal()">
            <i class="fas fa-paper-plane"></i> Compose email
        </button>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Direction</th>
                <th>To / From</th>
                <th>Subject</th>
                <th>Context</th>
            </tr>
        </thead>
        <tbody>
            @foreach($emails as $email)
            <tr>
                <td style="font-size: 0.85rem; color: var(--text-muted);">{{ $email->created_at->format('d M Y, h:i A') }}</td>
                <td>
                    @if($email->direction == 'inbound')
                        <span class="badge badge-success"><i class="fas fa-arrow-down"></i> Inbound</span>
                    @else
                        <span class="badge badge-info"><i class="fas fa-arrow-up"></i> Outbound</span>
                    @endif
                </td>
                <td style="font-size: 0.875rem;">
                    @if($email->direction == 'inbound')
                        <strong>From:</strong> {{ $email->from_address }}
                    @else
                        <strong>To:</strong> {{ $email->to_address }}
                    @endif
                </td>
                <td style="font-weight: 600;">
                    <a href="#" onclick="viewEmail('{{ addslashes($email->subject) }}', `{{ $email->body_html }}`)" style="color: var(--primary); text-decoration: none;">
                        {{ $email->subject }}
                    </a>
                </td>
                <td style="font-size: 0.85rem;">
                    @if($email->deal_id)
                        <span class="badge">Deal #{{ $email->deal_id }}</span>
                    @endif
                    @if($email->lead_id)
                        <span class="badge">Lead #{{ $email->lead_id }}</span>
                    @endif
                </td>
            </tr>
            @endforeach
            @if($emails->isEmpty())
            <tr>
                <td colspan="5"><div class="empty-state"><p>No emails found.</p></div></td>
            </tr>
            @endif
        </tbody>
    </table>
    </div>

    <div style="margin-top: 2rem;">
        {{ $emails->links() }}
    </div>
</div>

<!-- View Email Modal -->
<div class="modal-overlay" id="viewEmailModal">
    <div class="modal-content" style="max-width: 800px; width: 90%;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 1rem;">
            <h3 id="view_email_subject" style="margin: 0; color: var(--text-main);"></h3>
            <button class="close-modal" type="button" onclick="document.getElementById('viewEmailModal').classList.remove('show')"><i class="fas fa-times"></i></button>
        </div>
        <div id="view_email_body" style="background: var(--bg-subtle); padding: 1rem; border-radius: var(--radius-sm); min-height: 200px; max-height: 60vh; overflow-y: auto;">
        </div>
    </div>
</div>

<script>
    function viewEmail(subject, bodyHtml) {
        document.getElementById('view_email_subject').innerText = subject;
        document.getElementById('view_email_body').innerHTML = bodyHtml;
        document.getElementById('viewEmailModal').classList.add('show');
    }
</script>

<!-- Compose Email Modal -->
<div class="modal-overlay" id="composeEmailModal">
    <div class="modal-content" style="max-width: 800px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <button class="close-modal" type="button" onclick="document.getElementById('composeEmailModal').classList.remove('show')"><i class="fas fa-times"></i></button>
        <h2 style="margin-bottom: 1rem;">Compose Email</h2>
        <form action="{{ route('admin.emails.send') }}" method="POST">
            @csrf
            <div style="display: flex; gap: 10px; margin-bottom: 1rem;">
                <select id="templateSelector" class="form-control" style="flex: 1; padding: 0.7rem; border-radius: 8px; border: 1px solid var(--border);" onchange="loadTemplate(this.value)">
                    <option value="">-- Load a Template --</option>
                </select>
            </div>
            <div style="margin-bottom: 1rem;">
                <input type="email" name="to_address" id="email_to" class="form-control" style="width:100%; padding: 0.7rem; border-radius: 8px; border: 1px solid var(--border);" placeholder="To: email@client.com" required>
            </div>
            <div style="margin-bottom: 1rem;">
                <input type="text" name="subject" id="email_subject" class="form-control" style="width:100%; padding: 0.7rem; border-radius: 8px; border: 1px solid var(--border);" placeholder="Subject" required>
            </div>
            <div style="margin-bottom: 1rem;">
                <textarea name="body_html" id="email_body" class="form-control" style="width:100%; height: 300px;"></textarea>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">Send Email</button>
            </div>
        </form>
    </div>
</div>

<script src="@versioned('js/rich-editor.js')"></script>
<script>
    RichEditor.attach('#email_body');

    let templatesData = [];

    function fetchTemplates() {
        fetch('{{ route("admin.email_templates.api") }}')
            .then(res => res.json())
            .then(data => {
                templatesData = data;
                let selector = document.getElementById('templateSelector');
                selector.innerHTML = '<option value="">-- Load a Template --</option>';
                data.forEach(t => {
                    selector.innerHTML += `<option value="${t.id}">${t.name}</option>`;
                });
            });
    }

    function loadTemplate(id) {
        if (!id) return;
        let t = templatesData.find(x => x.id == id);
        if (t) {
            document.getElementById('email_subject').value = t.subject;
            RichEditor.setContent('email_body', t.body_html);
        }
    }

    function openComposeModal() {
        document.getElementById('email_to').value = '';
        document.getElementById('email_subject').value = '';
        RichEditor.setContent('email_body', '');
        fetchTemplates();
        document.getElementById('composeEmailModal').classList.add('show');
    }
</script>
@endsection
