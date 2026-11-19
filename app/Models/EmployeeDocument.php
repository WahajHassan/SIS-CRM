<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** A degree, certificate or any other paper attached to an employee. */
class EmployeeDocument extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'user_id', 'company_id', 'category', 'title', 'path', 'mime', 'size', 'uploaded_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function sizeText(): string
    {
        $bytes = (int) $this->size;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024) . ' KB';
        }

        return $bytes . ' B';
    }
}
