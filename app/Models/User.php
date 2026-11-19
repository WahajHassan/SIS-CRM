<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use App\Traits\BelongsToCompany;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, BelongsToCompany, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'permissions',
        'sales_performer_id',
        'company_id',
        'is_active',
        'disable_message',
        'approval_status',
        'monthly_salary',
        'avatar',
        'avatar_tone',
        'email_notifications',
        'verified_at',
        'verified_by',
        'verified_note',
    ];

    /**
     * The column default only applies inside the database, so a freshly created model
     * still reports a null is_active until it is reloaded. CheckAccountStatus reads
     * that attribute and would lock the account out, so mirror the default here.
     *
     * approval_status mirrors its own column default the same way: 'approved' unless
     * a creation path (currently only AdminController@storeUser, for the roles the
     * gate applies to) explicitly sets 'pending'.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'approval_status' => 'approved',
        // Same reason again: a user created in this request would otherwise
        // report a null here, and the notification would read that as "they
        // turned the emails off" on the very first thing it ever sent them.
        'email_notifications' => true,
    ];

    /**
     * Whether this account is still waiting on an admin to approve it. While true,
     * CheckAccountStatus locks the dashboard out, and a linked sales performer (if
     * any) stays off the public scoreboard — see SalesController@index.
     */
    public function isPendingApproval(): bool
    {
        return $this->approval_status === 'pending';
    }

    /**
     * The sections of the admin area this user may reach.
     *
     * A user with nothing saved falls back to the defaults for their role, so an
     * account created before the switches existed keeps working exactly as it did.
     * Once an admin saves the form the stored list is what counts.
     *
     * @return array<int, string>
     */
    public function allowedSections(): array
    {
        if ($this->isSuperAdmin()) {
            return array_keys(config('permissions.sections', []));
        }

        $saved = $this->permissions;

        if (is_array($saved)) {
            return array_values(array_intersect($saved, array_keys(config('permissions.sections', []))));
        }

        $defaults = config('permissions.role_defaults.' . $this->role, []);

        if ($defaults === ['*']) {
            return array_keys(config('permissions.sections', []));
        }

        return $defaults;
    }

    /**
     * Whether this user may reach a given section, e.g. canAccess('deals').
     */
    public function canAccess(string $section): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Owner level sections stay with the owner account no matter what is saved.
        if (in_array($section, config('permissions.superadmin_only', []), true)) {
            return false;
        }

        return in_array($section, $this->allowedSections(), true);
    }

    public function isAdmin()
    {
        return $this->role === 'admin' || $this->role === 'superadmin' || $this->role === 'floor_manager';
    }

    /** How recently a user must have made a request to count as online. */
    public const ONLINE_WINDOW_SECONDS = 120;

    /**
     * Online means a request within the last two minutes. The dashboard polls
     * every few seconds, so an open tab is comfortably inside that, and the
     * margin covers browsers slowing timers down in background tabs.
     *
     * Not read from the sessions table: the app uses the file session driver, so
     * that table is always empty and everyone looked offline.
     */
    public function isOnline()
    {
        return $this->last_seen_at
            && $this->last_seen_at->gte(now()->subSeconds(self::ONLINE_WINDOW_SECONDS));
    }

    public function isSuperAdmin()
    {
        return $this->role === 'superadmin';
    }

    public function isEditor()
    {
        return $this->role === 'editor';
    }

    public function isFloorManager()
    {
        return $this->role === 'floor_manager';
    }

    /**
     * Whether this person may step into somebody else's dashboard.
     *
     * The gate is the User Management section, not the role: anybody trusted
     * with the user list is trusted to look at what those users see. What the
     * role still decides is how far up they may reach — nobody steps into an
     * account that outranks their own, or into their own.
     *
     * The users page and the controller both ask this, so what the page offers
     * and what the route allows cannot drift apart.
     */
    public function mayImpersonate(self $target): bool
    {
        if (!$this->canAccess('users') || $target->id === $this->id) {
            return false;
        }

        // Reaching upwards: an owner account is only ever entered by an owner,
        // and an admin only by an owner or another admin.
        if ($target->isSuperAdmin()) {
            return $this->isSuperAdmin();
        }

        if ($target->role === 'admin') {
            return in_array($this->role, ['superadmin', 'admin'], true);
        }

        // A floor manager's reach is their floor, the same limit the user list
        // already puts on editing them.
        if ($this->isFloorManager()) {
            return in_array($target->role, ['agent', 'closer'], true);
        }

        return true;
    }

    public function isAgent()
    {
        return $this->role === 'agent';
    }

    public function isCloser()
    {
        return $this->role === 'closer';
    }

    public function isQA()
    {
        return $this->role === 'qa';
    }

    public function isSupportManager()
    {
        return $this->role === 'support_manager';
    }

    public function isSupportAgent()
    {
        return $this->role === 'support_agent';
    }

    public function isSupport()
    {
        return $this->role === 'support_manager' || $this->role === 'support_agent';
    }

    public function isAgentOrCloser()
    {
        return $this->role === 'agent' || $this->role === 'closer';
    }

    public function performer()
    {
        return $this->belongsTo(SalesPerformer::class, 'sales_performer_id')->withTrashed();
    }

    public function getDisplayNameAttribute()
    {
        return $this->performer ? $this->performer->display_name : $this->name;
    }

    /**
     * Their picture, or nothing.
     *
     * Returns a URL when one has been uploaded, and null when it has not, so
     * every place that shows a person can fall back to the initial without
     * asking twice.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar) {
            return $this->servedFrom($this->avatar);
        }

        // HR takes a photograph of everybody on their first day, and it was
        // sitting in the employee file doing nothing while the same person
        // showed up as a letter everywhere else. One picture per person,
        // whichever door it came in through. What they upload themselves wins,
        // because it is the more recent of the two by definition.
        $fromHr = $this->employeeProfile?->photo_path;

        return $fromHr ? $this->servedFrom($fromHr) : null;
    }

    /**
     * Where an uploaded file can actually be fetched from.
     *
     * Storage::url() returns a root-relative path — /storage/avatars/x.jpg —
     * which is right only when the CRM is the whole site. Installed in a
     * subfolder, as this one is, the browser resolves that against the domain
     * and asks for /storage/..., missing the /crm in the middle. The picture
     * 404s and every face falls back to a broken image icon.
     *
     * asset() builds it against wherever the application actually is, so it is
     * correct at the root and in a subfolder alike.
     */
    private function servedFrom(string $path): string
    {
        return asset('storage/' . ltrim($path, '/'));
    }

    /** The first letter, for when there is no picture. */
    public function getInitialAttribute(): string
    {
        $name = trim($this->display_name);

        return $name === '' ? '?' : mb_strtoupper(mb_substr($name, 0, 1));
    }

    /**
     * The colour behind that letter.
     *
     * Picked once from the name and kept, so somebody is the same colour in the
     * sidebar, the chat and the leaderboard. A different colour in each place
     * reads as a different person.
     */
    public function getToneAttribute(): string
    {
        if ($this->avatar_tone) {
            return $this->avatar_tone;
        }

        $tones = ['#F99D4E', '#6366F1', '#10B981', '#0EA5E9', '#EC4899', '#8B5CF6', '#14B8A6', '#F59E0B'];

        return $tones[crc32($this->display_name ?: (string) $this->id) % count($tones)];
    }

    /* ---- the tick -------------------------------------------------------- */

    /**
     * Has somebody with authority confirmed this account is who it says it is?
     *
     * Deliberately not the same question as "is their email confirmed" or "has
     * their account been approved". Those are about getting in. This is about
     * a name on a deal being a real, checked person — which is what anybody
     * reading the name actually wants to know.
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /** Who said so, for when somebody asks. */
    public function verifier()
    {
        return $this->belongsTo(self::class, 'verified_by');
    }

    /**
     * May this person put the tick on that one, or take it off?
     *
     * The same shape as stepping into somebody's dashboard: the gate is the
     * User Management section rather than the role, and the role decides how
     * far up the reach goes. Nobody verifies themselves — a tick you can award
     * yourself says nothing at all.
     */
    public function mayVerify(self $target): bool
    {
        return $this->mayImpersonate($target);
    }

    /** Put it on. */
    public function verify(self $target, ?string $note = null): void
    {
        $target->update([
            'verified_at' => now(),
            'verified_by' => $this->id,
            'verified_note' => $note,
        ]);
    }

    /** And take it off, leaving no trace of a claim that no longer holds. */
    public function unverify(self $target): void
    {
        $target->update([
            'verified_at' => null,
            'verified_by' => null,
            'verified_note' => null,
        ]);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    // ---- HR ----------------------------------------------------------------

    public function employeeProfile()
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function employeeDocuments()
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function jobDescription()
    {
        return $this->hasOne(JobDescription::class);
    }

    public function kpiTargets()
    {
        return $this->hasMany(KpiTarget::class);
    }

    /** HR staff and admins may open other people's personnel files. */
    public function isHr(): bool
    {
        return $this->role === 'hr';
    }

    /**
     * Who may manage the staff themselves — their accounts, their attendance and
     * their pay.
     *
     * HR was reaching these screens through the permission switches but being
     * turned away by every action on them, because the actions all asked whether
     * somebody was an admin. Hiring, approving and correcting a day's attendance
     * is HR's job; what stays admin-only is the company and the system around it,
     * and the guards against touching an admin's own account are further down
     * each of those actions.
     */
    public function managesStaff(): bool
    {
        return $this->isAdmin() || $this->isHr();
    }

    /** A team lead approves their own department's leave at the first step. */
    public function isTeamLead(): bool
    {
        return $this->role === 'team_lead' || $this->leadsDepartments()->exists();
    }

    public function shiftAssignments()
    {
        return $this->hasMany(EmployeeShift::class);
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leadsDepartments()
    {
        return $this->hasMany(Department::class, 'lead_user_id');
    }

    public function workingDayOverrides()
    {
        return $this->hasMany(WorkingDayOverride::class);
    }

    /** The department this person belongs to, through their personnel file. */
    public function department()
    {
        return $this->hasOneThrough(
            Department::class,
            EmployeeProfile::class,
            'user_id',        // employee_profiles.user_id
            'id',             // departments.id
            'id',             // users.id
            'department_id'   // employee_profiles.department_id
        );
    }

    /**
     * The shift in force for a date — the assignment covering it, falling back to
     * the only active shift when nobody has been assigned one yet.
     */
    public function shiftOn(string|\Carbon\Carbon|null $date = null): ?Shift
    {
        $date = $date ?: \Carbon\Carbon::today();

        $assignment = $this->shiftAssignments()
            ->with('shift')
            ->covering($date)
            ->orderByDesc('effective_from')
            ->first();

        if ($assignment?->shift) {
            return $assignment->shift;
        }

        $active = Shift::active()->get();

        return $active->count() === 1 ? $active->first() : null;
    }

    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_users')
                    ->withPivot('last_read_at')
                    ->withTimestamps();
    }

    public static function getOfficeDate($time = null)
    {
        $time = $time ? \Carbon\Carbon::parse($time) : \Carbon\Carbon::now('Asia/Karachi');
        if ($time->hour < 12) {
            return $time->subDay()->toDateString();
        }
        return $time->toDateString();
    }

    public static function getOfficeDayRange()
    {
        $now = \Carbon\Carbon::now('Asia/Karachi');
        if ($now->hour < 12) {
            $start = $now->copy()->subDay()->setTime(12, 0, 0);
            $end = $now->copy()->setTime(11, 59, 59);
        } else {
            $start = $now->copy()->setTime(12, 0, 0);
            $end = $now->copy()->addDay()->setTime(11, 59, 59);
        }
        return [$start, $end];
    }

    public function todayAttendance()
    {
        // Anyone who never clocked out is closed off first, so the figure this returns
        // is right even on a host where the scheduler was never wired up to run
        // attendance:auto-checkout. once() keeps it to a single sweep per request no
        // matter how many times the layout asks for today's attendance.
        once(fn () => Attendance::autoCheckoutExpired());

        return $this->attendances()->whereBetween('clock_in', self::getOfficeDayRange())->first();
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verified_at' => 'datetime',
            'email_notifications' => 'boolean',
            'last_seen_at' => 'datetime',
            'monthly_salary' => 'decimal:2',
            'password' => 'hashed',
            'permissions' => 'array',
        ];
    }

    public function loginLogs()
    {
        return $this->hasMany(LoginLog::class);
    }
}
