@extends('layouts.admin')

@section('content')
<div class="page-title" style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <a href="{{ url()->previous() }}" class="btn btn-ghost" style="margin-bottom: 10px; display: inline-block;"><i class="fas fa-arrow-left"></i> Back</a>
        <h1 style="font-size: 2rem; font-weight: 700; color: var(--text-main); margin-bottom: 5px;">Client Profile: {{ $client->name }}</h1>
        <p style="color: var(--text-muted);">View all details and contact information for this client.</p>
    </div>
    @if($client->supportTicket)
    <a href="{{ route('support.show', $client->supportTicket->id) }}" class="btn btn-primary" style="padding: 0.8rem 1.5rem; font-weight: 600; border-radius: 12px; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);">
        <i class="fas fa-ticket-alt"></i> View Support Ticket
    </a>
    @endif
</div>

<div class="profile-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem;">
    <!-- General Information -->
    <div class="sys-card" style="background: var(--bg-card); border-radius: 20px; padding: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid var(--border);">
        <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; color: var(--primary);">
            <i class="fas fa-briefcase"></i> Deal Information
        </h3>
        <div style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
            <div style="padding-bottom: 1rem; border-bottom: 1px solid var(--border);">
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">Deal Name</div>
                <div style="font-weight: 600; font-size: 1.1rem;">{{ $client->name }}</div>
            </div>
            <div style="padding-bottom: 1rem; border-bottom: 1px solid var(--border);">
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">Deal Value</div>
                <div style="font-weight: 600; font-size: 1.1rem; color: var(--success);">${{ number_format($client->value, 2) }}</div>
            </div>
            <div style="padding-bottom: 1rem; border-bottom: 1px solid var(--border);">
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">Closer</div>
                <div style="font-weight: 600; font-size: 1.1rem;">{{ $client->closed_by ?: 'N/A' }}</div>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">Date Created</div>
                <div style="font-weight: 600; font-size: 1.1rem;">{{ $client->created_at->format('F j, Y') }}</div>
            </div>
        </div>
    </div>

    <!-- Contact Information -->
    <div class="sys-card" style="background: var(--bg-card); border-radius: 20px; padding: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid var(--border);">
        <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; color: var(--info);">
            <i class="fas fa-address-card"></i> Contact Details
        </h3>
        <div style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
            <div style="padding-bottom: 1rem; border-bottom: 1px solid var(--border);">
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">Business Owner Name</div>
                <div style="font-weight: 600; font-size: 1.1rem;">{{ $client->business_owner_name ?: 'N/A' }}</div>
            </div>
            <div style="padding-bottom: 1rem; border-bottom: 1px solid var(--border);">
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">Business Phone</div>
                <div style="font-weight: 600; font-size: 1.1rem;">
                    @if($client->business_number)
                        <a href="tel:{{ $client->business_number }}" style="color: inherit; text-decoration: none;">{{ $client->business_number }}</a>
                    @else N/A @endif
                </div>
            </div>
            <div style="padding-bottom: 1rem; border-bottom: 1px solid var(--border);">
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">Business Email</div>
                <div style="font-weight: 600; font-size: 1.1rem;">
                    @if($client->business_email)
                        <a href="mailto:{{ $client->business_email }}" style="color: inherit; text-decoration: none;">{{ $client->business_email }}</a>
                    @else N/A @endif
                </div>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">Personal Phone</div>
                <div style="font-weight: 600; font-size: 1.1rem;">
                    @if($client->personal_number)
                        <a href="tel:{{ $client->personal_number }}" style="color: inherit; text-decoration: none;">{{ $client->personal_number }}</a>
                    @else N/A @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Business Details -->
    <div class="sys-card" style="background: var(--bg-card); border-radius: 20px; padding: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid var(--border);">
        <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; color: var(--warning);">
            <i class="fas fa-building"></i> Business Details
        </h3>
        <div style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
            <div style="padding-bottom: 1rem; border-bottom: 1px solid var(--border);">
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">Business Address</div>
                <div style="font-weight: 600; font-size: 1.1rem;">{{ $client->business_address ?: 'N/A' }}</div>
            </div>
            <div style="padding-bottom: 1rem; border-bottom: 1px solid var(--border);">
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">Areas Covered</div>
                <div style="font-weight: 600; font-size: 1.1rem;">{{ $client->areas_covered ?: 'N/A' }}</div>
            </div>
            <div style="padding-bottom: 1rem; border-bottom: 1px solid var(--border);">
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">Services Offered</div>
                <div style="font-weight: 600; font-size: 1.1rem;">{{ $client->services_offered ?: 'N/A' }}</div>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">Business Hours</div>
                <div style="font-weight: 600; font-size: 1.1rem;">{{ $client->business_hours ?: 'N/A' }}</div>
            </div>
        </div>
    </div>

    <!-- Status & Links -->
    <div class="sys-card" style="background: var(--bg-card); border-radius: 20px; padding: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid var(--border);">
        <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; color: #8b5cf6;">
            <i class="fas fa-link"></i> Status & Links
        </h3>
        <div style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
            <div style="padding-bottom: 1rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">Client Status</div>
                    <div style="font-weight: 600; font-size: 1.1rem; text-transform: capitalize;">{{ str_replace('-', ' ', $client->client_status ?: 'N/A') }}</div>
                </div>
            </div>
            <div style="padding-bottom: 1rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">Deal Status</div>
                    <div style="font-weight: 600; font-size: 1.1rem;">
                        <span class="badge" style="background: {{ $client->status == 'WON' ? 'var(--success)' : 'var(--info)' }}; color: white; padding: 0.3rem 0.8rem; border-radius: 6px;">
                            {{ $client->status }}
                        </span>
                    </div>
                </div>
            </div>
            <div style="padding-bottom: 1rem; border-bottom: 1px solid var(--border);">
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">Yelp Link</div>
                <div style="font-weight: 600; font-size: 1.1rem;">
                    @if($client->yelp_link)
                        <a href="{{ $client->yelp_link }}" target="_blank" style="color: var(--primary); text-decoration: none;"><i class="fas fa-external-link-alt"></i> View on Yelp</a>
                    @else N/A @endif
                </div>
            </div>
            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">Google Link</div>
                <div style="font-weight: 600; font-size: 1.1rem;">
                    @if($client->google_link)
                        <a href="{{ $client->google_link }}" target="_blank" style="color: var(--primary); text-decoration: none;"><i class="fas fa-external-link-alt"></i> View on Google</a>
                    @else N/A @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
