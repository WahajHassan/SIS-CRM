<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'disable_message',
        'logo',
        'dark_logo',
        'favicon',
        'theme_color',
        'number_of_breaks',
        'break_1_name',
        'break_1_start',
        'break_1_end',
        'break_2_name',
        'break_2_start',
        'break_2_end',
    ];

    /**
     * Same reason as on User: the database default is not reflected on a model that
     * has just been created, and an unset is_active reads as a disabled company.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function deals()
    {
        return $this->hasMany(Deal::class);
    }
}
