@php
    $company = auth()->user()->company;
@endphp
@if($company)
@php
    $break1Name = $company->break_1_name ?? '1st Half Break';
    $break2Name = $company->break_2_name ?? '2nd Half Break';
    $numberOfBreaks = $company->number_of_breaks ?? 2;
@endphp
<div style="display: flex; gap: 15px; flex-wrap: wrap;">
    <!-- 1st Half -->
    <div style="display: flex; gap: 5px;">
        @php
            $half1Ended = \Illuminate\Support\Facades\Cache::get("auto_break_half_1_end_date_{$company->id}") === \App\Models\User::getOfficeDate();
            $half1State = \Illuminate\Support\Facades\Cache::get("global_break_state_half_1_{$company->id}");
        @endphp
        @if($half1Ended)
            <button type="button" disabled class="btn btn-muted" style="border-radius: var(--radius-sm) 0 0 var(--radius-sm);" title="Break Completed">
                <i class="fas fa-check-circle"></i> {{ $break1Name }} Completed
            </button>
            <form id="globalResetForm1" action="{{ route('admin.attendance.reset-global-break') }}" method="POST" style="margin: 0;">
                @csrf
                <input type="hidden" name="type" value="half_1">
                <button type="button" onclick="showGlobalBreakConfirm('Are you sure you want to RESET the {{ addslashes($break1Name) }}? This will re-enable the controls.', 'globalResetForm1')" class="btn btn-warning" style="border-radius: 0 var(--radius-sm) var(--radius-sm) 0;" title="Reset Break Controls">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </form>
        @elseif($half1State === 'started' || $half1State === 'resumed')
            <div style="display: flex; gap: 5px;">
                <form id="globalPauseForm1" action="{{ route('admin.attendance.pause-global-break') }}" method="POST" style="margin: 0;">
                    @csrf
                    <input type="hidden" name="type" value="half_1">
                    <button type="button" onclick="showGlobalBreakConfirm('Are you sure you want to PAUSE the {{ addslashes($break1Name) }}? This will lift the lock screen for everyone.', 'globalPauseForm1')" class="btn btn-warning" title="Pause Break (Lift Lock)">
                        <i class="fas fa-pause"></i> Pause {{ $break1Name }}
                    </button>
                </form>
                <form id="globalEndBreakForm1" action="{{ route('admin.attendance.end-global-break') }}" method="POST" style="margin: 0;">
                    @csrf
                    <input type="hidden" name="type" value="half_1">
                    <button type="button" onclick="showGlobalBreakConfirm('Are you sure you want to completely END the {{ addslashes($break1Name) }}?', 'globalEndBreakForm1')" class="btn btn-danger" title="End Break Forever">
                        <i class="fas fa-stop"></i> End {{ $break1Name }}
                    </button>
                </form>
            </div>
        @elseif($half1State === 'paused')
            <div style="display: flex; gap: 5px;">
                <form id="globalResumeForm1" action="{{ route('admin.attendance.resume-global-break') }}" method="POST" style="margin: 0;">
                    @csrf
                    <input type="hidden" name="type" value="half_1">
                    <button type="button" onclick="showGlobalBreakConfirm('Are you sure you want to RESUME the {{ addslashes($break1Name) }}? This will lock screens again.', 'globalResumeForm1')" class="btn btn-info" title="Resume Break (Lock Screens)">
                        <i class="fas fa-play"></i> Resume {{ $break1Name }}
                    </button>
                </form>
                <form id="globalEndBreakForm1" action="{{ route('admin.attendance.end-global-break') }}" method="POST" style="margin: 0;">
                    @csrf
                    <input type="hidden" name="type" value="half_1">
                    <button type="button" onclick="showGlobalBreakConfirm('Are you sure you want to completely END the {{ addslashes($break1Name) }}?', 'globalEndBreakForm1')" class="btn btn-danger" title="End Break Forever">
                        <i class="fas fa-stop"></i> End {{ $break1Name }}
                    </button>
                </form>
            </div>
        @else
            <form id="globalStartForm1" action="{{ route('admin.attendance.start-global-break') }}" method="POST" style="margin: 0;">
                @csrf
                <input type="hidden" name="type" value="half_1">
                <button type="button" onclick="showGlobalBreakConfirm('Are you sure you want to START the {{ addslashes($break1Name) }} early? This will lock screens for everyone immediately.', 'globalStartForm1')" class="btn btn-success" title="Start Break Early">
                    <i class="fas fa-play"></i> Start {{ $break1Name }}
                </button>
            </form>
        @endif
    </div>

    <!-- 2nd Half -->
    @if($numberOfBreaks >= 2)
    <div style="display: flex; gap: 5px;">
        @php
            $half2Ended = \Illuminate\Support\Facades\Cache::get("auto_break_half_2_end_date_{$company->id}") === \App\Models\User::getOfficeDate();
            $half2State = \Illuminate\Support\Facades\Cache::get("global_break_state_half_2_{$company->id}");
        @endphp
        @if($half2Ended)
            <button type="button" disabled class="btn btn-muted" style="border-radius: var(--radius-sm) 0 0 var(--radius-sm);" title="Break Completed">
                <i class="fas fa-check-circle"></i> {{ $break2Name }} Completed
            </button>
            <form id="globalResetForm2" action="{{ route('admin.attendance.reset-global-break') }}" method="POST" style="margin: 0;">
                @csrf
                <input type="hidden" name="type" value="half_2">
                <button type="button" onclick="showGlobalBreakConfirm('Are you sure you want to RESET the {{ addslashes($break2Name) }}? This will re-enable the controls.', 'globalResetForm2')" class="btn btn-warning" style="border-radius: 0 var(--radius-sm) var(--radius-sm) 0;" title="Reset Break Controls">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </form>
        @elseif($half2State === 'started' || $half2State === 'resumed')
            <div style="display: flex; gap: 5px;">
                <form id="globalPauseForm2" action="{{ route('admin.attendance.pause-global-break') }}" method="POST" style="margin: 0;">
                    @csrf
                    <input type="hidden" name="type" value="half_2">
                    <button type="button" onclick="showGlobalBreakConfirm('Are you sure you want to PAUSE the {{ addslashes($break2Name) }}? This will lift the lock screen for everyone.', 'globalPauseForm2')" class="btn btn-warning" title="Pause Break (Lift Lock)">
                        <i class="fas fa-pause"></i> Pause {{ $break2Name }}
                    </button>
                </form>
                <form id="globalEndBreakForm2" action="{{ route('admin.attendance.end-global-break') }}" method="POST" style="margin: 0;">
                    @csrf
                    <input type="hidden" name="type" value="half_2">
                    <button type="button" onclick="showGlobalBreakConfirm('Are you sure you want to completely END the {{ addslashes($break2Name) }}?', 'globalEndBreakForm2')" class="btn btn-danger" title="End Break Forever">
                        <i class="fas fa-stop"></i> End {{ $break2Name }}
                    </button>
                </form>
            </div>
        @elseif($half2State === 'paused')
            <div style="display: flex; gap: 5px;">
                <form id="globalResumeForm2" action="{{ route('admin.attendance.resume-global-break') }}" method="POST" style="margin: 0;">
                    @csrf
                    <input type="hidden" name="type" value="half_2">
                    <button type="button" onclick="showGlobalBreakConfirm('Are you sure you want to RESUME the {{ addslashes($break2Name) }}? This will lock screens again.', 'globalResumeForm2')" class="btn btn-info" title="Resume Break (Lock Screens)">
                        <i class="fas fa-play"></i> Resume {{ $break2Name }}
                    </button>
                </form>
                <form id="globalEndBreakForm2" action="{{ route('admin.attendance.end-global-break') }}" method="POST" style="margin: 0;">
                    @csrf
                    <input type="hidden" name="type" value="half_2">
                    <button type="button" onclick="showGlobalBreakConfirm('Are you sure you want to completely END the {{ addslashes($break2Name) }}?', 'globalEndBreakForm2')" class="btn btn-danger" title="End Break Forever">
                        <i class="fas fa-stop"></i> End {{ $break2Name }}
                    </button>
                </form>
            </div>
        @else
            <form id="globalStartForm2" action="{{ route('admin.attendance.start-global-break') }}" method="POST" style="margin: 0;">
                @csrf
                <input type="hidden" name="type" value="half_2">
                <button type="button" onclick="showGlobalBreakConfirm('Are you sure you want to START the {{ addslashes($break2Name) }} early? This will lock screens for everyone immediately.', 'globalStartForm2')" class="btn btn-success" title="Start Break Early">
                    <i class="fas fa-play"></i> Start {{ $break2Name }}
                </button>
            </form>
        @endif
    </div>
    @endif
</div>
@endif
