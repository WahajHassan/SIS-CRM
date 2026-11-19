<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A business worth calling.
 *
 * A lead is what an agent works from: who the business is, what trade it is in,
 * and where it is. When the call goes well it is handed on as a TransferredLead,
 * and from there it may become a Deal — so a lead is the first link in that
 * chain rather than a note that ends when the call does.
 */
class Lead extends Model
{
    use HasFactory, BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'business_name',
        'phone',
        'email',
        'business_category_id',
        'usa_state_id',
        'city_id',
        'address',
        'website',
        'source',
        'status',
        'notes',
        'created_by',
    ];

    public function category()
    {
        return $this->belongsTo(BusinessCategory::class, 'business_category_id');
    }

    public function state()
    {
        return $this->belongsTo(UsaState::class, 'usa_state_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /** Where this lead went once it was handed on. */
    public function transfers()
    {
        return $this->hasMany(TransferredLead::class);
    }

    /** What to show when a lead is named on screen. */
    public function displayName(): string
    {
        return $this->business_name ?: $this->name;
    }

    /** "Tucson, Arizona" — or whichever half of it is known. */
    public function place(): string
    {
        return collect([$this->city?->name, $this->state?->name])->filter()->implode(', ');
    }
}
