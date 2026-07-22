<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileDetail extends Model
{
    protected $primaryKey = 'profile_id';

    public $incrementing = false;

    protected $guarded = [];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function hairColor(): BelongsTo
    {
        return $this->belongsTo(TaxonomyOption::class, 'hair_color_option_id');
    }

    public function hairLength(): BelongsTo
    {
        return $this->belongsTo(TaxonomyOption::class, 'hair_length_option_id');
    }

    public function sexualOrientation(): BelongsTo
    {
        return $this->belongsTo(TaxonomyOption::class, 'sexual_orientation_option_id');
    }

    protected function casts(): array
    {
        return ['smoker' => 'boolean'];
    }
}
