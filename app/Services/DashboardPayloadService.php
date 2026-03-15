<?php

namespace App\Services;

use App\Models\User;
use App\Support\Concerns\ResolvesActivitySchema;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardPayloadService
{
    use ResolvesActivitySchema;

    public function buildLandingPayload(): array
    {
        // Guard joins so dashboards still work when optional tables are absent in older schemas.
        $provinces = collect();
        if ($this->tableExists('provinces') && $this->tableExists('municipalities') && $this->tableExists('activities')) {
            $provinces = DB::table('provinces as p')
                ->leftJoin('municipalities as m', 'm.province_id', '=', 'p.id')
                ->leftJoin('activities as a', 'a.municipality_id', '=', 'm.id')
                ->selectRaw('p.name, COUNT(a.id) as activity_count')
                ->groupBy('p.id', 'p.name')
                ->orderBy('p.name')
                ->limit(5)
                ->get()
                ->map(function ($row) {
                    return [
                        'name' => $row->name,
                        'count' => (int) $row->activity_count,
                    ];
                });
        }

        $featured = $this->buildLandingFeaturedDestination();

        $activeTourists = $this->countUsersByRole('tourist');
        $providerCount = $this->countUsersByRole('provider');
        $lguCount = $this->countUsersByRole('lgu');
        $adminCount = $this->countUsersByRole('admin');

        $listingCount = $this->tableExists('activities')
            ? (int) DB::table('activities')->where('status', 'approved')->count()
            : 0;

        $eventCount = $this->tableExists('events')
            ? (int) DB::table('events')->whereIn('status', ['upcoming', 'ongoing'])->count()
            : 0;

        $featuredCount = $this->tableExists('activities') && Schema::hasColumn('activities', 'is_featured')
            ? (int) DB::table('activities')->where('status', 'approved')->where('is_featured', 1)->count()
            : 0;

        $categories = collect();
        if ($this->tableExists('categories') && $this->tableExists('activities')) {
            $categories = DB::table('categories as c')
                ->leftJoin('activities as a', 'a.category_id', '=', 'c.id')
                ->selectRaw('c.name, COUNT(a.id) as activity_count')
                ->groupBy('c.id', 'c.name')
                ->orderByDesc('activity_count')
                ->limit(6)
                ->get()
                ->map(function ($row) {
                    return [
                        'name' => $row->name,
                        'count' => (int) $row->activity_count,
                    ];
                });
        }

        return [
            'landingStats' => [
                'activeTourists' => $activeTourists,
                'regionalListings' => $listingCount,
                'upcomingEvents' => $eventCount,
                'featuredSpots' => $featuredCount,
            ],
            'landingProvinces' => $provinces,
            'landingFeatured' => $featured,
            'landingCategories' => $categories,
            'landingRoles' => [
                ['name' => 'Tourist', 'count' => $activeTourists],
                ['name' => 'Provider', 'count' => $providerCount],
                ['name' => 'LGU Manager', 'count' => $lguCount],
                ['name' => 'Admin', 'count' => $adminCount],
            ],
        ];
    }

    public function buildTouristPayload(int $userId): array
    {
        $savedActivitiesCount = $this->tableExists('saved_activities')
            ? DB::table('saved_activities')->where('user_id', $userId)->count()
            : 0;

        $itineraryCount = $this->tableExists('itineraries')
            ? DB::table('itineraries')->where('user_id', $userId)->count()
            : 0;

        $recommendations = $this->tableExists('recommendations') && $this->tableExists('activities')
            ? DB::table('recommendations as r')
                ->join('activities as a', 'a.id', '=', 'r.activity_id')
                ->leftJoin('categories as c', 'c.id', '=', 'a.category_id')
                ->where('r.user_id', $userId)
                ->select([
                    'a.id',
                    DB::raw($this->activityTitleExpression().' as title'),
                    DB::raw("COALESCE(c.name, 'Uncategorized') as category"),
                    'r.score',
                    'r.reason',
                    'r.created_at',
                ])
                ->orderByDesc('r.score')
                ->limit(6)
                ->get()
            : collect();

        $weather = $this->tableExists('weather_cache') && $this->tableExists('municipalities')
            ? DB::table('weather_cache as w')
                ->join('municipalities as m', 'm.id', '=', 'w.municipality_id')
                ->select([
                    'm.name as municipality',
                    'w.temperature',
                    DB::raw('w.`condition` as weather_condition'),
                    'w.fetched_at',
                ])
                ->orderByDesc('w.fetched_at')
                ->limit(5)
                ->get()
            : collect();

        $recentLogs = $this->tableExists('user_activity_logs') && $this->tableExists('activities')
            ? DB::table('user_activity_logs as l')
                ->join('activities as a', 'a.id', '=', 'l.activity_id')
                ->where('l.user_id', $userId)
                ->select([
                    'l.action',
                    'l.created_at',
                    DB::raw($this->activityTitleExpression().' as activity_title'),
                ])
                ->orderByDesc('l.created_at')
                ->limit(8)
                ->get()
            : collect();

        $mapPoints = $this->activityMapQuery()
            ->whereNotNull('a.latitude')
            ->whereNotNull('a.longitude')
            ->limit(100)
            ->get();

        return [
            'stats' => [
                'savedActivities' => $savedActivitiesCount,
                'itineraries' => $itineraryCount,
                'recommendations' => $recommendations->count(),
                'recentActions' => $recentLogs->count(),
            ],
            'recommendations' => $recommendations,
            'weather' => $weather,
            'recentLogs' => $recentLogs,
            'mapPoints' => $mapPoints,
        ];
    }

    public function buildProviderPayload(int $providerId): array
    {
        $activityBaseQuery = $this->tableExists('activities')
            ? DB::table('activities')->where('provider_id', $providerId)
            : null;

        $totalListings = $activityBaseQuery ? (clone $activityBaseQuery)->count() : 0;
        $pendingListings = $activityBaseQuery ? (clone $activityBaseQuery)->where('status', 'pending')->count() : 0;
        $approvedListings = $activityBaseQuery ? (clone $activityBaseQuery)->where('status', 'approved')->count() : 0;

        $totalViews = $this->tableExists('user_activity_logs') && $this->tableExists('activities')
            ? DB::table('user_activity_logs as l')
                ->join('activities as a', 'a.id', '=', 'l.activity_id')
                ->where('a.provider_id', $providerId)
                ->where('l.action', 'view')
                ->count()
            : 0;

        $submissions = $this->activityMapQuery()
            ->where('a.provider_id', $providerId)
            ->orderByDesc('a.created_at')
            ->limit(8)
            ->get();

        $upcomingEvents = $this->tableExists('events')
            ? DB::table('events')
                ->where('provider_id', $providerId)
                ->whereIn('status', ['upcoming', 'ongoing'])
                ->orderBy('start_datetime')
                ->limit(6)
                ->get(['id', 'name', 'status', 'start_datetime'])
            : collect();

        $mapPoints = $this->activityMapQuery()
            ->where('a.provider_id', $providerId)
            ->whereNotNull('a.latitude')
            ->whereNotNull('a.longitude')
            ->limit(100)
            ->get();

        return [
            'stats' => [
                'totalListings' => $totalListings,
                'approvedListings' => $approvedListings,
                'pendingListings' => $pendingListings,
                'totalViews' => $totalViews,
            ],
            'submissions' => $submissions,
            'upcomingEvents' => $upcomingEvents,
            'mapPoints' => $mapPoints,
        ];
    }

    public function buildLguPayload(?int $provinceId): array
    {
        $visitorLogsQuery = ($provinceId && $this->tableExists('user_activity_logs') && $this->tableExists('activities') && $this->tableExists('municipalities'))
            ? DB::table('user_activity_logs as l')
                ->join('activities as a', 'a.id', '=', 'l.activity_id')
                ->join('municipalities as m', 'm.id', '=', 'a.municipality_id')
                ->where('m.province_id', $provinceId)
            : null;

        $monthlyVisitors = $visitorLogsQuery ? (clone $visitorLogsQuery)->count() : 0;
        $uniqueTourists = $visitorLogsQuery ? (clone $visitorLogsQuery)->distinct('l.user_id')->count('l.user_id') : 0;

        $pendingApprovals = ($provinceId && $this->tableExists('activities') && $this->tableExists('municipalities'))
            ? DB::table('activities as a')
                ->join('municipalities as m', 'm.id', '=', 'a.municipality_id')
                ->where('m.province_id', $provinceId)
                ->where('a.status', 'pending')
                ->count()
            : 0;

        $feedbackCount = ($provinceId && $this->tableExists('reviews') && $this->tableExists('activities') && $this->tableExists('municipalities'))
            ? DB::table('reviews as r')
                ->join('activities as a', 'a.id', '=', 'r.activity_id')
                ->join('municipalities as m', 'm.id', '=', 'a.municipality_id')
                ->where('m.province_id', $provinceId)
                ->count()
            : 0;

        $topSpots = ($provinceId && $this->tableExists('user_activity_logs') && $this->tableExists('activities') && $this->tableExists('municipalities'))
            ? DB::table('user_activity_logs as l')
                ->join('activities as a', 'a.id', '=', 'l.activity_id')
                ->join('municipalities as m', 'm.id', '=', 'a.municipality_id')
                ->where('m.province_id', $provinceId)
                ->selectRaw($this->activityTitleExpression().' as title, COUNT(*) as engagement')
                ->groupByRaw($this->activityTitleExpression())
                ->orderByDesc('engagement')
                ->limit(8)
                ->get()
            : collect();

        $mapPoints = ($provinceId)
            ? $this->activityMapQuery()
                ->where('m.province_id', $provinceId)
                ->whereNotNull('a.latitude')
                ->whereNotNull('a.longitude')
                ->limit(120)
                ->get()
            : collect();

        return [
            'stats' => [
                'monthlyVisitors' => $monthlyVisitors,
                'uniqueTourists' => $uniqueTourists,
                'pendingApprovals' => $pendingApprovals,
                'feedbackCount' => $feedbackCount,
            ],
            'topSpots' => $topSpots,
            'mapPoints' => $mapPoints,
        ];
    }

    public function buildAdminPayload(): array
    {
        $users = $this->tableExists('users') ? DB::table('users')->count() : 0;
        $providers = $this->tableExists('providers') ? DB::table('providers')->count() : 0;
        $activities = $this->tableExists('activities') ? DB::table('activities')->count() : 0;
        $events = $this->tableExists('events') ? DB::table('events')->count() : 0;

        $pendingQueue = collect();
        if ($this->tableExists('activities')) {
            $ownerExpression = $this->tableExists('providers')
                ? "COALESCE(p.business_name, 'Unknown Provider')"
                : "'Unknown Provider'";

            $pendingQueue = DB::table('activities as a')
                ->when($this->tableExists('providers'), function ($query) {
                    $query->leftJoin('providers as p', 'p.id', '=', 'a.provider_id');
                })
                ->where('a.status', 'pending')
                ->select([
                    'a.id',
                    DB::raw($this->activityTitleExpression().' as item_name'),
                    DB::raw("'activity' as item_type"),
                    DB::raw($ownerExpression.' as owner_name'),
                    'a.created_at',
                ])
                ->orderByDesc('a.created_at')
                ->limit(10)
                ->get();
        }

        $roleMix = ($this->tableExists('users') && $this->tableExists('roles'))
            ? DB::table('users as u')
                ->join('roles as r', 'r.id', '=', 'u.role_id')
                ->selectRaw('r.name as role, COUNT(*) as total')
                ->groupBy('r.name')
                ->orderByDesc('total')
                ->get()
            : collect();

        $mapPoints = $this->activityMapQuery()
            ->whereNotNull('a.latitude')
            ->whereNotNull('a.longitude')
            ->limit(160)
            ->get();

        return [
            'stats' => [
                'users' => $users,
                'providers' => $providers,
                'activities' => $activities,
                'events' => $events,
            ],
            'pendingQueue' => $pendingQueue,
            'roleMix' => $roleMix,
            'mapPoints' => $mapPoints,
        ];
    }

    public function resolveLguProvinceId(int $userId): ?int
    {
        if ($this->tableExists('lgu_managers')) {
            $provinceId = DB::table('lgu_managers')->where('user_id', $userId)->value('province_id');
            if ($provinceId) {
                return (int) $provinceId;
            }
        }

        if ($this->tableExists('provinces')) {
            $fallback = DB::table('provinces')->orderBy('id')->value('id');

            return $fallback ? (int) $fallback : null;
        }

        return null;
    }

    private function buildLandingFeaturedDestination(): mixed
    {
        if (! $this->tableExists('activities')) {
            return null;
        }

        // Title column varies across deployments (name/title), so compute expression dynamically.
        $activityTitle = $this->activityTitleExpression();
        $featuredQuery = DB::table('activities as a')
            ->leftJoin('municipalities as m', 'm.id', '=', 'a.municipality_id')
            ->leftJoin('provinces as p', 'p.id', '=', 'm.province_id')
            ->leftJoin('reviews as r', 'r.activity_id', '=', 'a.id')
            ->select([
                'a.id',
                DB::raw($activityTitle.' as title'),
                DB::raw("COALESCE(m.name, '') as municipality"),
                DB::raw("COALESCE(p.name, '') as province"),
                DB::raw('ROUND(AVG(r.rating), 1) as avg_rating'),
            ])
            ->where('a.status', 'approved')
            ->groupBy('a.id', 'm.name', 'p.name')
            ->groupByRaw($activityTitle);

        if (Schema::hasColumn('activities', 'is_featured')) {
            $featuredQuery->orderByDesc('a.is_featured');
        }

        return $featuredQuery->orderByDesc('a.created_at')->first();
    }

    private function activityMapQuery(): Builder
    {
        if (! $this->tableExists('activities')) {
            return DB::table('activities as a')->whereRaw('1=0');
        }

        // Shared base map query keeps all dashboards aligned on the same geo dataset shape.
        return DB::table('activities as a')
            ->leftJoin('municipalities as m', 'm.id', '=', 'a.municipality_id')
            ->leftJoin('provinces as p', 'p.id', '=', 'm.province_id')
            ->leftJoin('categories as c', 'c.id', '=', 'a.category_id')
            ->select([
                'a.id',
                DB::raw($this->activityTitleExpression().' as title'),
                'a.latitude',
                'a.longitude',
                'a.status',
                DB::raw("COALESCE(m.name, '') as municipality"),
                DB::raw("COALESCE(p.name, '') as province"),
                DB::raw("COALESCE(c.name, 'Uncategorized') as category"),
                'a.created_at',
            ]);
    }

    private function countUsersByRole(string $roleName): int
    {
        if (! $this->tableExists('users')) {
            return 0;
        }

        if ($this->tableExists('roles')) {
            return (int) DB::table('users as u')
                ->join('roles as r', 'r.id', '=', 'u.role_id')
                ->whereRaw('LOWER(r.name) = ?', [$roleName])
                ->count();
        }

        $roleMap = [
            'tourist' => User::ROLE_TOURIST,
            'provider' => User::ROLE_PROVIDER,
            'lgu' => User::ROLE_LGU,
            'admin' => User::ROLE_ADMIN,
        ];

        $roleValue = $roleMap[$roleName] ?? null;
        if ($roleValue === null) {
            return 0;
        }

        return (int) DB::table('users')->where('role', $roleValue)->count();
    }
}
