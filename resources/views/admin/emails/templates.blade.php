@extends('layouts.admin')

@section('title', 'Proposal Templates')

@section('content')
<div class="page-head">
    <div class="page-sub" style="margin: 0;">The proposals the team sends, written once and reused.</div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="document.getElementById('addTemplateModal').classList.add('show')">
            <i class="fas fa-plus"></i> New template
        </button>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Template name</th>
                <th>Subject</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($templates as $template)
            <tr>
                <td style="font-weight: 600;">{{ $template->name }}</td>
                <td>{{ $template->subject }}</td>
                <td><div style="display: flex; gap: 6px; justify-content: flex-end;">
                    <button class="btn btn-ghost btn-sm" style="color: var(--info);" onclick="editTemplate({{ $template->id }}, '{{ addslashes($template->name) }}', '{{ addslashes($template->subject) }}', `{{ $template->body_html }}`)">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <form action="{{ route('admin.email_templates.delete', $template->id) }}" method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to delete this template?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--danger);">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </form>
                </div></td>
            </tr>
            @endforeach
            @if($templates->isEmpty())
            <tr>
                <td colspan="3"><div class="empty-state"><p>No templates found. Create your first patterned proposal.</p></div></td>
            </tr>
            @endif
        </tbody>
    </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addTemplateModal">
    <div class="modal-content" style="max-width: 800px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <button class="close-modal" type="button" onclick="document.getElementById('addTemplateModal').classList.remove('show')"><i class="fas fa-times"></i></button>
        <h2 style="margin-bottom: 1rem;">New Proposal Template</h2>
        <form action="{{ route('admin.email_templates.store') }}" method="POST">
            @csrf
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px;">Template Name</label>
                <input type="text" name="name" class="form-control" style="width:100%; padding: 0.8rem; border-radius: 8px; border: 1px solid var(--border);" required placeholder="e.g. Standard Website Proposal">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px;">Email Subject</label>
                <input type="text" name="subject" class="form-control" style="width:100%; padding: 0.8rem; border-radius: 8px; border: 1px solid var(--border);" required placeholder="e.g. Your Custom Proposal from Systematic IT Solutions">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px;">Upload Custom HTML (Optional)</label>
                <input type="file" id="html_upload_add" accept=".html,.htm" onchange="loadHtmlFile(this, 'editor_add')" class="form-control" style="width:100%; padding: 0.5rem; border-radius: 8px; border: 1px solid var(--border);">
                <small style="color: var(--text-muted); font-size: 0.75rem;">Uploading an HTML file will load its content into the editor below.</small>
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px;">Email Body (HTML)</label>
                <textarea name="body_html" id="editor_add" class="form-control" style="width:100%; height: 300px; padding: 0.8rem; border-radius: 8px; border: 1px solid var(--border);"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fas fa-floppy-disk"></i> Save template</button>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editTemplateModal">
    <div class="modal-content" style="max-width: 800px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <button class="close-modal" type="button" onclick="document.getElementById('editTemplateModal').classList.remove('show')"><i class="fas fa-times"></i></button>
        <h2 style="margin-bottom: 1rem;">Edit Proposal Template</h2>
        <form id="editTemplateForm" method="POST">
            @csrf
            @method('PUT')
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px;">Template Name</label>
                <input type="text" name="name" id="edit_name" class="form-control" style="width:100%; padding: 0.8rem; border-radius: 8px; border: 1px solid var(--border);" required>
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px;">Email Subject</label>
                <input type="text" name="subject" id="edit_subject" class="form-control" style="width:100%; padding: 0.8rem; border-radius: 8px; border: 1px solid var(--border);" required>
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px;">Upload Custom HTML (Optional)</label>
                <input type="file" id="html_upload_edit" accept=".html,.htm" onchange="loadHtmlFile(this, 'editor_edit')" class="form-control" style="width:100%; padding: 0.5rem; border-radius: 8px; border: 1px solid var(--border);">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px;">Email Body (HTML)</label>
                <textarea name="body_html" id="editor_edit" class="form-control" style="width:100%; height: 300px; padding: 0.8rem; border-radius: 8px; border: 1px solid var(--border);"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fas fa-check"></i> Update template</button>
        </form>
    </div>
</div>

<script src="@versioned('js/rich-editor.js')"></script>
<script>
    RichEditor.attach('#editor_add, #editor_edit');

    function loadHtmlFile(input, editorId) {
        if (input.files && input.files[0]) {
            let reader = new FileReader();
            reader.onload = function(e) {
                RichEditor.setContent(editorId, e.target.result);
                input.value = ""; // Reset
            };
            reader.readAsText(input.files[0]);
        }
    }

    function editTemplate(id, name, subject, body_html) {
        // Built from url() rather than a leading slash: the CRM is served from a
        // subfolder on the live site, where '/admin/...' lands on the main website.
        document.getElementById('editTemplateForm').action = '{{ url('admin/email-templates') }}/' + id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_subject').value = subject;
        RichEditor.setContent('editor_edit', body_html);
        document.getElementById('editTemplateModal').classList.add('show');
    }
</script>
@endsection
