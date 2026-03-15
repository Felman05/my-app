<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'region',
        'image_url',
    ];

    public function municipalities()
    {
        return $this->hasMany(Municipality::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }
}
