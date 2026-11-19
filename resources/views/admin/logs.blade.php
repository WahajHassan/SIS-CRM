@extends('layouts.admin')

@section('title', 'System Audit Logs')

@section('content')
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2 class="card-title">System Activity Logs</h2>
    </div>

    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border);">
                    <th style="padding: 1rem; color: var(--text-muted); font-weight: 600;">Time</th>
                    <th style="padding: 1rem; color: var(--text-muted); font-weight: 600;">User</th>
                    <th style="padding: 1rem; color: var(--text-muted); font-weight: 600;">Company</th>
                    <th style="padding: 1rem; color: var(--text-muted); font-weight: 600;">Action</th>
                    <th style="padding: 1rem; color: var(--text-muted); font-weight: 600;">Description</th>
                    <th style="padding: 1rem; color: var(--text-muted); font-weight: 600;">IP Address</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr style="border-bottom: 1px solid var(--border);">
                    <td style="padding: 1rem; font-size: 0.9rem;">{{ $log->created_at->format('M d, Y h:i A') }}</td>
                    <td style="padding: 1rem;">
                        @if($log->user)
                            {{ $log->user->name }}
                        @else
                            <span style="color: var(--text-muted);">System / Deleted User</span>
                        @endif
                    </td>
                    <td style="padding: 1rem;">
                        @if($log->company)
                            {{ $log->company->name }}
                        @else
                            <span style="color: var(--text-muted);">N/A</span>
                        @endif
                    </td>
                    <td style="padding: 1rem;">
                        <span class="badge" style="background: rgba(59,130,246,0.1); color: var(--info);">
                            {{ $log->action_type }}
                        </span>
                    </td>
                    <td style="padding: 1rem; color: var(--text-main);">
                        {{ $log->description }}
                    </td>
                    <td style="padding: 1rem; color: var(--text-muted); font-size: 0.85rem;">
                        {{ $log->ip_address }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="padding: 2rem; text-align: center; color: var(--text-muted);">
                        No logs found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <div style="padding: 1rem;">
        {{ $logs->links() }}
    </div>
</div>
@endsection
