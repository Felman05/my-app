<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('doon:recommendations:generate', function () {
    if (! Schema::hasTable('recommendations') || ! Schema::hasTable('user_activity_logs')) {
        $this->warn('Missing required tables: recommendations or user_activity_logs.');

        return;
    }

    $topViewed = DB::table('user_activity_logs')
        ->select('activity_id', DB::raw('COUNT(*) as views'))
        ->where('action', 'view')
        ->groupBy('activity_id')
        ->orderByDesc('views')
        ->limit(20)
        ->get();

    $users = DB::table('users')->select('id')->get();

    foreach ($users as $user) {
        foreach ($topViewed as $rank => $activity) {
            DB::table('recommendations')->updateOrInsert(
                ['user_id' => $user->id, 'activity_id' => $activity->activity_id],
                [
                    'score' => max(0.1, 1 - ($rank * 0.03)),
                    'reason' => 'Popular in your region',
                    'is_clicked' => false,
                    'created_at' => now(),
                ]
            );
        }
    }

    $this->info('Recommendations generated successfully.');
})->purpose('Generate deterministic recommendations from activity logs');

Artisan::command('doon:analytics:daily', function () {
    if (! Schema::hasTable('daily_analytics_summaries') || ! Schema::hasTable('user_activity_logs')) {
        $this->warn('Missing required tables for daily analytics.');

        return;
    }

    $date = now()->toDateString();
    $provinceIds = Schema::hasTable('provinces') ? DB::table('provinces')->pluck('id') : collect([null]);

    foreach ($provinceIds as $provinceId) {
        $logs = DB::table('user_activity_logs as l')
            ->join('activities as a', 'a.id', '=', 'l.activity_id')
            ->join('municipalities as m', 'm.id', '=', 'a.municipality_id')
            ->when($provinceId, fn ($query) => $query->where('m.province_id', $provinceId));

        $totalViews = (clone $logs)->where('l.action', 'view')->count();
        $totalVisits = (clone $logs)->where('l.action', 'visit')->count();
        $uniqueUsers = (clone $logs)->distinct('l.user_id')->count('l.user_id');

        DB::table('daily_analytics_summaries')->updateOrInsert(
            ['summary_date' => $date, 'province_id' => $provinceId],
            [
                'total_views' => $totalViews,
                'total_visits' => $totalVisits,
                'unique_users' => $uniqueUsers,
                'top_categories' => json_encode([]),
                'top_activities' => json_encode([]),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    $this->info('Daily analytics summaries generated successfully.');
})->purpose('Generate daily tourism analytics summaries');

Schedule::command('doon:recommendations:generate')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('doon:analytics:daily')
    ->dailyAt('01:15')
    ->withoutOverlapping();
