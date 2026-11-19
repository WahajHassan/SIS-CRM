<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * The HR record behind a login: the details a CRM account does not need but a
 * personnel file does.
 */
class EmployeeProfile extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'user_id', 'company_id', 'father_name', 'personal_email', 'contact_no',
        'designation', 'department', 'department_id', 'date_of_birth', 'emergency_contact_name',
        'emergency_contact_no', 'family_contact_no', 'id_card_no', 'address',
        'employment_status', 'joining_date', 'probation_end_date',
        'cnic_path', 'photo_path', 'cv_path', 'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'joining_date' => 'date',
        'probation_end_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function departmentRecord()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /** The department's name, whichever way it was set. */
    public function departmentName(): ?string
    {
        return $this->departmentRecord?->name ?: ($this->department ?: null);
    }

    /** Filled-in fields out of the ones HR asks for, as a percentage. */
    public function completeness(): int
    {
        $wanted = [
            'father_name', 'contact_no', 'designation', 'department', 'date_of_birth',
            'emergency_contact_no', 'family_contact_no', 'id_card_no', 'joining_date',
            'cnic_path', 'photo_path', 'cv_path',
        ];

        $filled = collect($wanted)->filter(fn ($f) => filled($this->{$f}))->count();

        return (int) round($filled / count($wanted) * 100);
    }

    public function isProbation(): bool
    {
        return $this->employment_status !== 'permanent';
    }

    /** Their birthday this year, for the upcoming-birthdays list. */
    public function nextBirthday(): ?\Carbon\Carbon
    {
        if (!$this->date_of_birth) {
            return null;
        }

        $next = $this->date_of_birth->copy()->year(now()->year);

        return $next->lt(now()->startOfDay()) ? $next->addYear() : $next;
    }
}
