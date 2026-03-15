<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DashboardPayloadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardPayloadService $dashboardPayloadService)
    {
    }

    public function landing(): Response
    {
        $cached = Cache::remember('dashboard.landing', now()->addMinutes(2), function () {
            return $this->dashboardPayloadService->buildLandingPayload();
        });

        return Inertia::render('Landing', $cached);
    }

    public function index(Request $request): RedirectResponse
    {
        return match ($request->user()->role) {
            User::ROLE_TOURIST => redirect()->route('tourist.dashboard'),
            User::ROLE_PROVIDER => redirect()->route('provider.dashboard'),
            User::ROLE_LGU => redirect()->route('lgu.dashboard'),
            User::ROLE_ADMIN => redirect()->route('admin.dashboard'),
            default => redirect()->route('landing'),
        };
    }

    public function tourist(Request $request): Response
    {
        $userId = $request->user()->id;

        $cacheKey = 'dashboard.tourist.'.$userId;
        $cached = Cache::remember($cacheKey, now()->addMinutes(2), function () use ($userId) {
            return $this->dashboardPayloadService->buildTouristPayload($userId);
        });

        return Inertia::render('Tourist/Dashboard', $cached);
    }

    public function provider(Request $request): Response
    {
        $providerId = $this->resolveProviderId($request->user()->id);

        $cacheKey = 'dashboard.provider.'.$providerId;
        $cached = Cache::remember($cacheKey, now()->addMinutes(2), function () use ($providerId) {
            return $this->dashboardPayloadService->buildProviderPayload($providerId);
        });

        return Inertia::render('Provider/Dashboard', $cached);
    }

    public function lgu(Request $request): Response
    {
        $provinceId = $this->dashboardPayloadService->resolveLguProvinceId($request->user()->id);

        $cacheKey = 'dashboard.lgu.'.($provinceId ?? 'none');
        $cached = Cache::remember($cacheKey, now()->addMinutes(2), function () use ($provinceId) {
            return $this->dashboardPayloadService->buildLguPayload($provinceId);
        });

        return Inertia::render('Lgu/Dashboard', $cached);
    }

    public function admin(): Response
    {
        $cached = Cache::remember('dashboard.admin', now()->addMinutes(2), function () {
            return $this->dashboardPayloadService->buildAdminPayload();
        });

        return Inertia::render('Admin/Dashboard', $cached);
    }
}
