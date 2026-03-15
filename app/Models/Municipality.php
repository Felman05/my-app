<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Municipality extends Model
{
    use HasFactory;

    protected $fillable = [
        'province_id',
        'name',
        'code',
    ];

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }
}
