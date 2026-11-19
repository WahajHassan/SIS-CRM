<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class KpiTarget extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'user_id', 'company_id', 'period', 'metric', 'unit', 'source',
        'target_value', 'achieved_value', 'notes', 'updated_by',
    ];

    protected $casts = [
        'period' => 'date',
        'target_value' => 'float',
        'achieved_value' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** How far along this target is, capped at 100 for the progress bar. */
    public function progress(): int
    {
        if ($this->target_value <= 0) {
            return 0;
        }

        return (int) min(100, round($this->achieved_value / $this->target_value * 100));
    }

    /** Whether this figure keeps itself up to date from the CRM. */
    public function isAuto(): bool
    {
        return \App\Services\PerformanceService::isAuto($this->source);
    }

    public function sourceLabel(): string
    {
        return \App\Services\PerformanceService::label($this->source);
    }

    public function valueText(float $value): string
    {
        return match ($this->unit) {
            'amount' => '$' . number_format($value, 0),
            'percent' => rtrim(rtrim(number_format($value, 1), '0'), '.') . '%',
            default => rtrim(rtrim(number_format($value, 1), '0'), '.'),
        };
    }
}
