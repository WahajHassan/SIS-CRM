@extends('layouts.admin')

@section('title', 'Recycle Bin')

@section('content')
{{-- No heading of its own: the layout already prints the screen's name, and
     two of them one above the other reads as a mistake. --}}
<p class="field-hint" style="max-width: 70ch; margin: 0 0 1.3rem;">
    Nothing deleted in the CRM is destroyed any more — it is set aside here, with
    everything attached to it left untouched. Put it back and the person, the lead
    or the deal returns exactly as it was.
</p>

@php $anything = collect($bins)->sum(fn ($bin) => $bin['rows']->count()); @endphp

@if(!$anything)
    <div class="card">
        <div class="empty-state">
            <i class="fas fa-trash-arrow-up"></i>
            <h4>The bin is empty</h4>
            <p>Nothing has been deleted. When something is, it waits here instead of going away.</p>
        </div>
    </div>
@else
    @foreach($bins as $key => $bin)
        @continue($bin['rows']->isEmpty())

        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-{{ $bin['icon'] }}"></i> {{ $bin['label'] }}
                </div>
                <span class="chip">{{ $bin['rows']->count() }}</span>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>What it was</th>
                            <th>Deleted</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bin['rows'] as $row)
                        <tr>
                            <td>
                                <div style="font-weight: 600;">{{ $row['name'] }}</div>
                                @if($row['detail'])
                                    <div class="tl-note">{{ $row['detail'] }}</div>
                                @endif
                            </td>
                            <td style="white-space: nowrap;">
                                {{ $row['when']?->diffForHumans() }}
                                <div class="tl-note">{{ $row['when']?->format('j M Y, g:i a') }}</div>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <form action="{{ route('admin.bin.restore', [$key, $row['id']]) }}" method="POST" style="display: inline-flex; margin: 0;">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-rotate-left"></i> Put it back
                                    </button>
                                </form>

                                @if(auth()->user()->isSuperAdmin())
                                    {{-- The only place in the CRM that really destroys
                                         something, so it says so. --}}
                                    <form action="{{ route('admin.bin.purge', [$key, $row['id']]) }}" method="POST"
                                          style="display: inline-flex; margin: 0 0 0 6px;"
                                          onsubmit="return confirm('Destroy {{ addslashes($row['name']) }} for good?\n\nThis cannot be undone, and everything attached to it goes too.');">
                                        @csrf
                                        <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--danger);"
                                                title="Destroy for good — this cannot be undone">
                                            <i class="fas fa-fire"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
@endif
@endsection
