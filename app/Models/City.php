<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * A city inside a state.
 *
 * Leads are sorted and reported on by where the business is, so the city is a
 * row to pick rather than something typed differently every time.
 */
class City extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'usa_state_id', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function state()
    {
        return $this->belongsTo(UsaState::class, 'usa_state_id');
    }

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** The cities of one state, in the order a dropdown should show them. */
    public function scopeOfState($query, $stateId)
    {
        return $query->where('usa_state_id', $stateId)->orderBy('name');
    }
}
