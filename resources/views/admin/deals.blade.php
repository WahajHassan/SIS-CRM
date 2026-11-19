@extends('layouts.admin')

@section('title', 'Deals & Activity')

@section('content')
<style>
    /* The deal form is long. Its fields are stacked with one rhythm so the eye
       can run down them, and grouped sections are told apart by a rule rather
       than by a box inside a box. */
    .dl-f { margin-bottom: 0.85rem; }
    /* The field's name, which stays put once the field has something in it. */
    .dl-label {
        display: block; margin-bottom: 5px;
        font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em;
        text-transform: uppercase; color: var(--text-soft);
    }
    .dl-group { border-top: 1px solid var(--border); margin-top: 1.2rem; padding-top: 1.2rem; }
    .dl-group-title { font-weight: 700; font-size: 0.82rem; color: var(--text-main); margin-bottom: 0.9rem;
                      display: flex; align-items: center; gap: 8px; }
    .dl-group-title i { color: var(--primary); }
    .dl-two { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .dl-three { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; }
    .dl-file { font-size: 0.8rem; padding: 0.45rem 0.6rem; }
    .dl-doc { margin-top: 6px; font-size: 0.78rem; }
    .dl-doc a { color: var(--primary); text-decoration: none; font-weight: 600; }
    .dl-acts { display: flex; gap: 4px; align-items: center; }
    .dl-cols { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 1.3rem; align-items: start; }
    @media (max-width: 1100px) { .dl-cols { grid-template-columns: 1fr; } .dl-two { grid-template-columns: 1fr; } }
    .dl-who { font-size: 0.85rem; }
    .dl-who b { font-weight: 600; color: var(--text-muted); }
    .dl-who .is-closer b { color: var(--primary); }
</style>

<div class="dl-cols">
    <!-- Deals List -->
    <div class="card">
        <div class="card-header" style="flex-wrap: wrap;">
            <div class="card-title"><i class="fas fa-handshake"></i> All sales activity</div>
            <form method="GET" action="{{ route('admin.deals') }}" style="display: flex; gap: 8px; margin: 0;">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by deal or agent…" style="width: 220px; padding: 0.4rem 0.7rem;">
                <button type="submit" class="btn btn-ghost btn-sm" title="Search"><i class="fas fa-search"></i></button>
                @if(request('search'))
                    <a href="{{ route('admin.deals') }}" class="btn btn-ghost btn-sm" title="Clear search" style="color: var(--danger);"><i class="fas fa-times"></i></a>
                @endif
            </form>

            {{-- request()->query() so the file matches what is on screen rather
                 than quietly downloading everything. --}}
            <a href="{{ route('admin.deals.export', request()->query()) }}" class="btn btn-ghost btn-sm"
               title="Download these deals as a spreadsheet">
                <i class="fas fa-file-csv"></i> Export
            </a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Client/Project</th>
                        <th>Value</th>
                        <th>Agent / Closer</th>
                        <th>Status</th>
                        @if(!auth()->user()->isAgent())
                        <th>Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($deals as $deal)
                    <tr>
                        <td style="font-size: 0.8rem; color: var(--text-muted);">{{ $deal->created_at->format('d M, Y') }}</td>
                        <td style="font-weight: 600;">{{ $deal->name }}</td>
                        <td style="font-weight: 700; color: var(--success);">${{ number_format($deal->value) }}</td>
                        <td class="dl-who">
                            <div><b>Agent:</b> {{ $performers->firstWhere('name', $deal->closed_by)?->display_name ?? $deal->closed_by }}</div>
                            @if($deal->closer_by)
                            <div class="is-closer" style="margin-top: 3px; font-size: 0.78rem;">
                                <b>Closer:</b> {{ $performers->firstWhere('name', $deal->closer_by)?->display_name ?? $deal->closer_by }}
                            </div>
                            @endif
                        </td>
                        <td>
                            @php
                                $dealTone = match($deal->status) {
                                    'WON' => 'badge-success',
                                    'LOST' => 'badge-danger',
                                    'DRAFT' => '',
                                    default => 'badge-info',
                                };
                            @endphp
                            <span class="badge {{ $dealTone }}">{{ $deal->status }}</span>
                        </td>
                        @if(!auth()->user()->isAgent())
                        <td>
                          <div class="dl-acts">
                            <button type="button" class="btn btn-ghost btn-sm" title="Edit deal" style="color: var(--primary);" onclick="document.getElementById('editDealModal_{{ $deal->id }}').style.display='flex'"><i class="fas fa-edit"></i></button>
                            <button type="button" class="btn btn-ghost btn-sm" title="Send Email/Proposal" style="color: var(--info);" onclick="openEmailModal({{ $deal->id }}, '{{ addslashes($deal->business_email) }}', 'deal')"><i class="fas fa-envelope"></i></button>
                            @if(auth()->user()->isAdmin())
                            <form action="{{ route('admin.deals.delete', $deal->id) }}" method="POST" onsubmit="confirmDelete(event, this, 'Delete this record?')" style="margin: 0; display: inline-flex;">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm" title="Delete" style="color: var(--danger);"><i class="fas fa-trash-alt"></i></button>
                            </form>
                            @endif

                            <!-- Edit Deal Modal -->
                            <div id="editDealModal_{{ $deal->id }}" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.55); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 1000; padding: 1rem;">
                                <div class="card modal-content" style="width: 680px; max-width: 100%; max-height: 90vh; overflow-y: auto; margin: 0; text-align: left;">
                                    <div class="card-header">
                                        <div class="card-title"><i class="fas fa-pen"></i> Edit deal activity</div>
                                        <button type="button" class="btn btn-ghost btn-icon" title="Close" onclick="document.getElementById('editDealModal_{{ $deal->id }}').style.display='none'"><i class="fas fa-xmark"></i></button>
                                    </div>
                                    <form action="{{ route('admin.deals.update', $deal->id) }}" method="POST" enctype="multipart/form-data">
                                        @csrf @method('PUT')

                                        <!-- Normal Fields Grid -->
                                        <div class="dl-two" style="margin-bottom: 1.3rem;">
                                            <!-- Left Column -->
                                            <div>
                                                <div class="dl-f">
                                                    <label class="dl-label">Client / project</label>
                                                    <input type="text" name="name" value="{{ $deal->name }}" placeholder="Client Name" class="control">
                                                </div>
                                                <div class="dl-f">
                                                    <label class="dl-label">Total amount</label>
                                                    <input type="number" name="value" id="editDealValue_{{ $deal->id }}" value="{{ $deal->value }}" placeholder="Total Amount" class="control" oninput="calcEditRemaining('{{ $deal->id }}')">
                                                </div>
                                                <div class="dl-f">
                                                    <label class="dl-label">Charged amount</label>
                                                    <input type="number" name="charge_payment" id="editDealCharge_{{ $deal->id }}" value="{{ $deal->charge_payment }}" placeholder="Charged Amount" class="control" oninput="calcEditRemaining('{{ $deal->id }}')">
                                                </div>
                                                <div class="dl-f">
                                                    <label class="dl-label">Remaining amount</label>
                                                    <input type="number" name="remaining_payment" id="editDealRemaining_{{ $deal->id }}" value="{{ $deal->remaining_payment }}" placeholder="Remaining Amount" readonly class="control" style="background: var(--bg-subtle); cursor: not-allowed;">
                                                </div>
                                                <div class="dl-f">
                                                    <label class="dl-label">Agent</label>
                                                    <select name="closed_by" class="control">
                                                        <option value="">Select Agent</option>
                                                        <option value="Self-Generated by Closer" {{ $deal->closed_by == 'Self-Generated by Closer' ? 'selected' : '' }} style="font-weight: bold; color: var(--primary);">Self-Generated by Closer</option>
                                                        @foreach($performers->where('is_closer', false)->filter(fn($p) => $p->status !== 'INACTIVE' || $deal->closed_by == $p->name) as $p)
                                                            <option value="{{ $p->name }}" {{ $deal->closed_by == $p->name ? 'selected' : '' }}>{{ $p->display_name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="dl-f">
                                                    <label class="dl-label">Closer</label>
                                                    <select name="closer_by" class="control">
                                                        <option value="">Select Closer</option>
                                                        @foreach($performers->where('is_closer', true)->filter(fn($p) => $p->status !== 'INACTIVE' || $deal->closer_by == $p->name) as $p)
                                                            <option value="{{ $p->name }}" {{ $deal->closer_by == $p->name ? 'selected' : '' }}>{{ $p->display_name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="dl-f">
                                                    <label class="dl-label">Status</label>
                                                    <select name="status" class="control">
                                                        <option value="WON" {{ $deal->status == 'WON' ? 'selected' : '' }}>WON</option>
                                                        <option value="LOST" {{ $deal->status == 'LOST' ? 'selected' : '' }}>LOST</option>
                                                        <option value="PENDING" {{ $deal->status == 'PENDING' ? 'selected' : '' }}>PENDING</option>
                                                        <option value="DRAFT" {{ $deal->status == 'DRAFT' ? 'selected' : '' }}>DRAFT</option>
                                                    </select>
                                                </div>

                                            </div>

                                            <!-- Right Column -->
                                            <div>
                                                <div class="dl-f">
                                                    <label class="dl-label">Owner name</label>
                                                    <input type="text" name="business_owner_name" value="{{ $deal->business_owner_name }}" placeholder="Owner Name" class="control">
                                                </div>
                                                <div class="dl-f">
                                                    <label class="dl-label">Business number</label>
                                                    <input type="text" name="business_number" value="{{ $deal->business_number }}" placeholder="Business No." class="control">
                                                </div>
                                                <div class="dl-f">
                                                    <label class="dl-label">Personal number</label>
                                                    <input type="text" name="personal_number" value="{{ $deal->personal_number }}" placeholder="Personal No." class="control">
                                                </div>
                                                <div class="dl-f">
                                                    <label class="dl-label">Business email</label>
                                                    <input type="email" name="business_email" value="{{ $deal->business_email }}" placeholder="business@email.com" class="control">
                                                </div>
                                                <div class="dl-f">
                                                    <label class="dl-label">Personal email</label>
                                                    <input type="email" name="personal_email" value="{{ $deal->personal_email }}" placeholder="personal@email.com" class="control">
                                                </div>
                                                <div class="dl-f">
                                                    <label class="dl-label">Business hours</label>
                                                    <input type="text" name="business_hours" value="{{ $deal->business_hours }}" placeholder="e.g. Mon-Fri 9AM-5PM" class="control">
                                                </div>
                                                <div class="dl-f">
                                                    <label class="dl-label">Yelp link</label>
                                                    <input type="url" name="yelp_link" value="{{ $deal->yelp_link }}" placeholder="https://yelp.com/..." class="control">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="dl-f">
                                            <label class="dl-label">Google maps link</label>
                                            <input type="url" name="google_link" value="{{ $deal->google_link }}" placeholder="https://google.com/maps/..." class="control">
                                        </div>

                                        <!-- Textarea Type Fields Section (Grouped at the bottom for correct alignment) -->
                                        <div class="dl-group">
                                            <div class="dl-f">
                                                <label class="dl-label">Address</label>
                                                <textarea name="business_address" placeholder="Address..." rows="2" class="control" style="resize: vertical; min-height: 0;">{{ $deal->business_address }}</textarea>
                                            </div>

                                            <div class="dl-two" style="margin-bottom: 1.3rem;">
                                                <div>
                                                    <label class="dl-label">Areas covered</label>
                                                    <textarea name="areas_covered" placeholder="Cities/Zipcodes..." rows="2" class="control" style="resize: vertical; min-height: 0;">{{ $deal->areas_covered }}</textarea>
                                                </div>
                                                <div>
                                                    <label class="dl-label">Services offered</label>
                                                    <textarea name="services_offered" placeholder="List of services..." rows="2" class="control" style="resize: vertical; min-height: 0;">{{ $deal->services_offered }}</textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Verification Documents Section -->
                                        <div class="dl-group">
                                            <div class="dl-group-title"><i class="fas fa-folder-open"></i> Disposal documents — all three for won deals</div>
                                            <div class="dl-three" style="margin-bottom: 1.3rem;">
                                                <div>
                                                    <label class="dl-label">Signed agreement</label>
                                                    <input type="file" name="docusign_file" class="control dl-file">
                                                    @if($deal->docusign_file)
                                                        <div class="dl-doc">
                                                            <a href="{{ asset('storage/' . $deal->docusign_file) }}" target="_blank" style="color: var(--primary);"><i class="fas fa-file-download"></i> View Document</a>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div>
                                                    <label class="dl-label">Proposal PDF</label>
                                                    <input type="file" name="proposal_pdf" class="control dl-file">
                                                    @if($deal->proposal_pdf)
                                                        <div class="dl-doc">
                                                            <a href="{{ asset('storage/' . $deal->proposal_pdf) }}" target="_blank" style="color: var(--primary);"><i class="fas fa-file-download"></i> View PDF</a>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div>
                                                    <label class="dl-label">Payment authorisation</label>
                                                    <input type="file" name="payment_auth_file" class="control dl-file">
                                                    @if($deal->payment_auth_file)
                                                        <div class="dl-doc">
                                                            <a href="{{ asset('storage/' . $deal->payment_auth_file) }}" target="_blank" style="color: var(--primary);"><i class="fas fa-file-download"></i> View Auth Proof</a>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>


                                        @if(auth()->user()->isAdmin() || auth()->user()->isCloser())
                                        <!-- Card Information (Admin/Superadmin/Closer only) -->
                                        <div class="dl-group">
                                            <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-main); margin-bottom: 1rem;">💳 Card Information (Confidential)</div>
                                            <div class="dl-two" style="margin-bottom: 1.3rem;">
                                                <div>
                                                    <div class="dl-f">
                                                        <input type="text" name="card_name" value="{{ $deal->card_name }}" placeholder="Name on Card" class="control">
                                                    </div>
                                                    <div class="dl-f">
                                                        <input type="text" name="card_number" value="{{ $deal->card_number }}" placeholder="16-digit Card Number" class="control">
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="dl-f">
                                                        <input type="text" name="card_expiry" value="{{ $deal->card_expiry }}" placeholder="MM/YY" class="control">
                                                    </div>
                                                    <div class="dl-f">
                                                        <input type="text" name="card_cvv" value="{{ $deal->card_cvv }}" placeholder="CVV" class="control">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="dl-f">
                                                <input type="text" name="card_address" value="{{ $deal->card_address }}" placeholder="Billing Address" class="control">
                                            </div>
                                        </div>
                                        @endif

                                        <div style="display: flex; gap: 10px;">
                                            <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Update Deal</button>
                                            <button type="button" class="btn btn-ghost" onclick="document.getElementById('editDealModal_{{ $deal->id }}').style.display='none'">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </td>
                        @endif
                    </tr>
                    @endforeach
@if($deals->isEmpty())
            <tr class="is-blank">
                <td colspan="7">
                    <div class="empty-state">
                        <i class="fas fa-file-signature"></i>
                        <h4>No deals yet</h4>
                        <p>Add your first deal, or wait for the floor to close one.</p>
                    </div>
                </td>
            </tr>
@endif

                </tbody>
            </table>
        </div>
        <div style="margin-top: 1.5rem;">
            {{ $deals->links() }}
        </div>
    </div>

    @if(!auth()->user()->isAgent())
    <!-- Log New Deal Side Card -->
    <div class="card" style="text-align: center; padding: 2rem;">
        <div style="width: 60px; height: 60px; background: rgba(249,157,78,0.1); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem auto; font-size: 1.5rem;">
            <i class="fas fa-handshake"></i>
        </div>
        <h3 style="font-size: 1.2rem; margin-bottom: 0.5rem; font-weight: 700; color: var(--text-main);">Manage Deals</h3>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem; line-height: 1.5;">Log new client projects, track values, assign closers, and save business details.</p>
        <button class="btn btn-primary" onclick="document.getElementById('addDealModal').style.display='flex'" style="width: 100%; justify-content: center; height: 45px; font-weight: 700;">
            <i class="fas fa-plus"></i> Log New Deal
        </button>
    </div>

    <!-- Add New Deal Modal -->
    <div id="addDealModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
        <div class="card" style="width: 680px; max-height: 90vh; overflow-y: auto; margin: 0; text-align: left;">
            <div class="card-header">
                <div class="card-title">Log New Deal</div>
                <button class="btn btn-ghost" onclick="document.getElementById('addDealModal').style.display='none'">&times;</button>
            </div>
            <form action="{{ route('admin.deals.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <!-- Normal Fields Grid -->
                <div class="dl-two" style="margin-bottom: 1.3rem;">
                    <!-- Left Column -->
                    <div>
                        <div class="dl-f">
                            <label class="dl-label">Client / project</label>
                            <input type="text" name="name" placeholder="Client Name" class="control">
                        </div>
                        <div class="dl-f">
                            <label class="dl-label">Total amount</label>
                            <input type="number" name="value" id="newDealValue" placeholder="Total Amount" class="control" oninput="calcNewRemaining()">
                        </div>
                        <div class="dl-f">
                            <label class="dl-label">Charged amount</label>
                            <input type="number" name="charge_payment" id="newDealCharge" placeholder="Charged Amount" class="control" oninput="calcNewRemaining()">
                        </div>
                        <div class="dl-f">
                            <label class="dl-label">Remaining amount</label>
                            <input type="number" name="remaining_payment" id="newDealRemaining" placeholder="Remaining Amount" readonly class="control" style="background: var(--bg-subtle); cursor: not-allowed;">
                        </div>
                        <div class="dl-f">
                            <label class="dl-label">Agent</label>
                            <select name="closed_by" class="control">
                                <option value="">Select Agent</option>
                                <option value="Self-Generated by Closer" style="font-weight: bold; color: var(--primary);">Self-Generated by Closer</option>
                                @foreach($performers->where('is_closer', false)->filter(fn($p) => $p->status !== 'INACTIVE') as $p)
                                    <option value="{{ $p->name }}">{{ $p->display_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="dl-f">
                            <label class="dl-label">Closer</label>
                            <select name="closer_by" class="control">
                                <option value="">Select Closer</option>
                                @foreach($performers->where('is_closer', true)->filter(fn($p) => $p->status !== 'INACTIVE') as $p)
                                    <option value="{{ $p->name }}">{{ $p->display_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="dl-f">
                            <label class="dl-label">Status</label>
                            <select name="status" class="control">
                                <option value="WON">WON</option>
                                <option value="LOST">LOST</option>
                                <option value="PENDING">PENDING</option>
                                <option value="DRAFT">DRAFT</option>
                            </select>
                        </div>

                    </div>

                    <!-- Right Column -->
                    <div>
                        <div class="dl-f">
                            <label class="dl-label">Owner name</label>
                            <input type="text" name="business_owner_name" placeholder="Owner Name" class="control">
                        </div>
                        <div class="dl-f">
                            <label class="dl-label">Business number</label>
                            <input type="text" name="business_number" placeholder="Business No." class="control">
                        </div>
                        <div class="dl-f">
                            <label class="dl-label">Personal number</label>
                            <input type="text" name="personal_number" placeholder="Personal No." class="control">
                        </div>
                        <div class="dl-f">
                            <label class="dl-label">Business email</label>
                            <input type="email" name="business_email" placeholder="business@email.com" class="control">
                        </div>
                        <div class="dl-f">
                            <label class="dl-label">Personal email</label>
                            <input type="email" name="personal_email" placeholder="personal@email.com" class="control">
                        </div>
                        <div class="dl-f">
                            <label class="dl-label">Business hours</label>
                            <input type="text" name="business_hours" placeholder="e.g. Mon-Fri 9AM-5PM" class="control">
                        </div>
                        <div class="dl-f">
                            <label class="dl-label">Yelp link</label>
                            <input type="url" name="yelp_link" placeholder="https://yelp.com/..." class="control">
                        </div>
                    </div>
                </div>
                <div class="dl-f">
                    <label class="dl-label">Google maps link</label>
                    <input type="url" name="google_link" placeholder="https://google.com/maps/..." class="control">
                </div>

                <!-- Textarea Type Fields Section (Grouped at the bottom for correct alignment) -->
                <div class="dl-group">
                    <div class="dl-f">
                        <label class="dl-label">Address</label>
                        <textarea name="business_address" placeholder="Address..." rows="2" class="control" style="resize: vertical; min-height: 0;"></textarea>
                    </div>

                    <div class="dl-two" style="margin-bottom: 1.3rem;">
                        <div>
                            <label class="dl-label">Areas covered</label>
                            <textarea name="areas_covered" placeholder="Cities/Zipcodes..." rows="2" class="control" style="resize: vertical; min-height: 0;"></textarea>
                        </div>
                        <div>
                            <label class="dl-label">Services offered</label>
                            <textarea name="services_offered" placeholder="List of services..." rows="2" class="control" style="resize: vertical; min-height: 0;"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Verification Documents Section -->
                <div class="dl-group">
                    <div class="dl-group-title"><i class="fas fa-folder-open"></i> Disposal documents — all three for won deals</div>
                    <div class="dl-three" style="margin-bottom: 1.3rem;">
                        <div>
                            <label class="dl-label">Signed agreement</label>
                            <input type="file" name="docusign_file" class="control dl-file">
                        </div>
                        <div>
                            <label class="dl-label">Proposal PDF</label>
                            <input type="file" name="proposal_pdf" class="control dl-file">
                        </div>
                        <div>
                            <label class="dl-label">Payment authorisation</label>
                            <input type="file" name="payment_auth_file" class="control dl-file">
                        </div>
                    </div>
                </div>


                @if(auth()->user()->isAdmin() || auth()->user()->isCloser())
                <!-- Card Information (Admin/Superadmin/Closer only) -->
                <div class="dl-group">
                    <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-main); margin-bottom: 1rem;">💳 Card Information (Confidential)</div>
                    <div class="dl-two" style="margin-bottom: 1.3rem;">
                        <div>
                            <div class="dl-f">
                                <input type="text" name="card_name" placeholder="Name on Card" class="control">
                            </div>
                            <div class="dl-f">
                                <input type="text" name="card_number" placeholder="16-digit Card Number" class="control">
                            </div>
                        </div>
                        <div>
                            <div class="dl-f">
                                <input type="text" name="card_expiry" placeholder="MM/YY" class="control">
                            </div>
                            <div class="dl-f">
                                <input type="text" name="card_cvv" placeholder="CVV" class="control">
                            </div>
                        </div>
                    </div>
                    <div class="dl-f">
                        <input type="text" name="card_address" placeholder="Billing Address" class="control">
                    </div>
                </div>
                @endif

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Add Deal Activity</button>
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('addDealModal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>

<!-- Email Modal -->
<div id="emailComposerModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 2000;">
    <div class="card" style="width: 800px; max-height: 90vh; overflow-y: auto; margin: 0; text-align: left;">
        <div class="card-header">
            <div class="card-title">Send Email / Proposal</div>
            <button class="btn btn-ghost" onclick="document.getElementById('emailComposerModal').style.display='none'">&times;</button>
        </div>
        <form action="{{ route('admin.emails.send') }}" method="POST">
            @csrf
            <input type="hidden" name="deal_id" id="email_deal_id">

            <div style="display: flex; gap: 10px; margin-bottom: 1rem;">
                <select id="templateSelector" class="form-control" style="flex: 1; padding: 0.7rem; border-radius: 8px; border: 1px solid var(--border);" onchange="loadTemplate(this.value)">
                    <option value="">-- Load a Template --</option>
                </select>
            </div>
            <div class="dl-f">
                <input type="email" name="to_address" id="email_to" class="form-control" style="width:100%; padding: 0.7rem; border-radius: 8px; border: 1px solid var(--border);" placeholder="To: email@client.com" required>
            </div>
            <div class="dl-f">
                <input type="text" name="subject" id="email_subject" class="form-control" style="width:100%; padding: 0.7rem; border-radius: 8px; border: 1px solid var(--border);" placeholder="Subject" required>
            </div>
            <div class="dl-f">
                <textarea name="body_html" id="email_body" class="form-control" style="width:100%; height: 300px;"></textarea>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">Send Email</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script src="@versioned('js/rich-editor.js')"></script>
<script>
    let templatesData = [];

    // Fetch templates when modal opens
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

    function openEmailModal(dealId, email, type) {
        document.getElementById('email_deal_id').value = dealId;
        document.getElementById('email_to').value = email;
        document.getElementById('email_subject').value = '';
        RichEditor.setContent('email_body', '');

        fetchTemplates();
        document.getElementById('emailComposerModal').style.display='flex';
    }

    RichEditor.attach('#email_body');
</script>
<script>
    function calcNewRemaining() {
        let total = parseFloat(document.getElementById('newDealValue').value) || 0;
        let charge = parseFloat(document.getElementById('newDealCharge').value) || 0;
        let remaining = total - charge;
        document.getElementById('newDealRemaining').value = remaining >= 0 ? remaining : 0;
    }

    function calcEditRemaining(id) {
        let total = parseFloat(document.getElementById('editDealValue_' + id).value) || 0;
        let charge = parseFloat(document.getElementById('editDealCharge_' + id).value) || 0;
        let remaining = total - charge;
        document.getElementById('editDealRemaining_' + id).value = remaining >= 0 ? remaining : 0;
    }
</script>
@endpush
@endsection

