<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreActivityRequest;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Municipality;
use App\Models\Notification;
use App\Models\Province;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    public function touristIndex(Request $request): Response
    {
        $this->authorize('viewAny', Activity::class);

        $activities = $this->buildActivityListingQuery()
            ->where('a.status', 'approved')
            ->latest('a.created_at')
            ->paginate(12)
            ->withQueryString();

        $this->hydrateActivityRelations($activities);

        return Inertia::render('Tourist/Activities/Index', [
            'activities' => $activities,
        ]);
    }

    public function providerIndex(Request $request): Response
    {
        $this->authorize('viewAny', Activity::class);

        $providerId = $this->resolveProviderId($request->user()->id);

        $activities = $this->buildActivityListingQuery()
            ->where('a.provider_id', $providerId)
            ->latest('a.created_at')
            ->paginate(12)
            ->withQueryString();

        $this->hydrateActivityRelations($activities);

        return Inertia::render('Provider/Activities/Index', [
            'activities' => $activities,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Activity::class);

        $categoriesQuery = Category::query();

        if (Schema::hasColumn('categories', 'is_active')) {
            $categoriesQuery->where('is_active', true);
        }

        return Inertia::render('Provider/Activities/Create', [
            'categories' => $categoriesQuery->orderBy('name')->get(['id', 'name']),
            'provinces' => Province::query()->orderBy('name')->get(['id', 'name']),
            'municipalities' => Municipality::query()->orderBy('name')->get(['id', 'province_id', 'name']),
        ]);
    }

    public function store(StoreActivityRequest $request): RedirectResponse
    {
        $this->authorize('create', Activity::class);

        $validatedData = $request->validated();

        $providerId = $this->resolveProviderId($request->user()->id);

        $payload = $this->buildCreatePayload($validatedData, $providerId, $request->user()->travel_style);
        $filteredPayload = $this->filterToActivityColumns($payload);

        $created = Activity::query()->create($filteredPayload);

        AuditLogger::log('activity.submit', 'activity', $created->id, [
            'status' => 'pending',
            'provider_id' => $providerId,
        ]);

        $this->createSubmissionNotification($request->user()->id, $created->id);

        return redirect()->route('provider.activities.index')->with('success', 'Activity submitted for review.');
    }

    private function buildActivityListingQuery()
    {
        return DB::table('activities as a')
            ->leftJoin('categories as c', 'c.id', '=', 'a.category_id')
            ->leftJoin('municipalities as m', 'm.id', '=', 'a.municipality_id')
            ->leftJoin('provinces as p', 'p.id', '=', 'm.province_id')
            ->select([
                'a.id',
                DB::raw($this->activityTitleExpression().' as title'),
                'a.description',
                'a.status',
                DB::raw("COALESCE(c.name, 'Uncategorized') as category_name"),
                DB::raw("COALESCE(m.name, '') as municipality_name"),
                DB::raw("COALESCE(p.name, '') as province_name"),
            ]);
    }

    private function hydrateActivityRelations($activities): void
    {
        $activities->getCollection()->transform(function ($item) {
            $item->category = ['name' => $item->category_name ?: 'Uncategorized'];
            $item->municipality = ['name' => $item->municipality_name ?: null];
            $item->province = ['name' => $item->province_name ?: null];
            unset($item->category_name, $item->municipality_name, $item->province_name);

            return $item;
        });
    }

    private function buildCreatePayload(array $validatedData, int $providerId, ?string $travelStyle): array
    {
        // Start with cross-schema safe base fields that every activity submission should carry.
        $payload = [
            'provider_id' => $providerId,
            'category_id' => $validatedData['category_id'],
            'municipality_id' => $validatedData['municipality_id'],
            'slug' => $this->generateUniqueSlug($validatedData['title']),
            'description' => $validatedData['description'] ?? null,
            'address' => $validatedData['address'] ?? null,
            'latitude' => $validatedData['latitude'] ?? null,
            'longitude' => $validatedData['longitude'] ?? null,
            'status' => 'pending',
            'is_featured' => (bool) ($validatedData['is_featured'] ?? false),
        ];

        if (Schema::hasColumn('activities', 'province_id')) {
            $payload['province_id'] = $this->resolveProvinceId($validatedData);
        }

        if (Schema::hasColumn('activities', 'title')) {
            $payload['title'] = $validatedData['title'];
        }

        if (Schema::hasColumn('activities', 'name')) {
            $payload['name'] = $validatedData['title'];
        }

        if (Schema::hasColumn('activities', 'short_description')) {
            $payload['short_description'] = Str::limit((string) ($validatedData['description'] ?? ''), 180);
        }

        if (Schema::hasColumn('activities', 'price')) {
            $payload['price'] = $validatedData['price'] ?? null;
        }

        if (Schema::hasColumn('activities', 'price_min')) {
            $payload['price_min'] = $validatedData['price'] ?? 0;
        }

        if (Schema::hasColumn('activities', 'price_max')) {
            $payload['price_max'] = $validatedData['price'] ?? null;
        }

        if (Schema::hasColumn('activities', 'starts_at')) {
            $payload['starts_at'] = $validatedData['starts_at'] ?? null;
        }

        if (Schema::hasColumn('activities', 'ends_at')) {
            $payload['ends_at'] = $validatedData['ends_at'] ?? null;
        }

        if (Schema::hasColumn('activities', 'travel_style') && $travelStyle) {
            $payload['travel_style'] = json_encode([$travelStyle]);
        }

        return $payload;
    }

    private function generateUniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title);
        $slug = $baseSlug;
        $counter = 1;

        while (Activity::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function resolveProvinceId(array $validatedData): ?int
    {
        $provinceId = $validatedData['province_id'] ?? null;

        if (! $provinceId && ! empty($validatedData['municipality_id']) && Schema::hasTable('municipalities')) {
            $provinceId = DB::table('municipalities')
                ->where('id', $validatedData['municipality_id'])
                ->value('province_id');
        }

        return $provinceId ? (int) $provinceId : null;
    }

    private function filterToActivityColumns(array $payload): array
    {
        // Keep payload schema-aware to prevent insert failures when columns differ by environment.
        $allowedColumns = Schema::getColumnListing('activities');

        return array_intersect_key($payload, array_flip($allowedColumns));
    }

    private function createSubmissionNotification(int $userId, int $activityId): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Notification::query()->create([
            'user_id' => $userId,
            'title' => 'Listing Submitted',
            'body' => 'Your activity was submitted and is awaiting approval.',
            'type' => 'approval',
            'reference_id' => $activityId,
            'reference_type' => 'activity',
            'is_read' => false,
            'sent_at' => now(),
        ]);
    }

}
