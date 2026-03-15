<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyAnalyticsSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'summary_date',
        'province_id',
        'total_views',
        'total_visits',
        'unique_users',
        'top_categories',
        'top_activities',
    ];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'top_categories' => 'array',
            'top_activities' => 'array',
        ];
    }
}
