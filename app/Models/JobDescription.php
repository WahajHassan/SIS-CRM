<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class JobDescription extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'user_id', 'company_id', 'title', 'reports_to', 'purpose',
        'responsibilities', 'requirements', 'tools', 'updated_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    /** A textarea holds one item per line; the view wants them as a list. */
    public function lines(string $field): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $this->{$field}))
            ->map(fn ($l) => trim(ltrim($l, "-•* \t")))
            ->filter()
            ->values()
            ->all();
    }
}
