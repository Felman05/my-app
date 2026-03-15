<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MapDataController extends Controller
{
    private const DEFAULT_STATUS = 'approved';
    private const ALLOWED_STATUSES = ['approved', 'pending', 'rejected', 'draft'];

    public function points(Request $request): JsonResponse
    {
        abort_unless($this->tableExists('activities'), 404);

        $titleExpr = $this->activityTitleExpression();
        $latitudeColumn = Schema::hasColumn('activities', 'latitude') ? 'latitude' : (Schema::hasColumn('activities', 'lat') ? 'lat' : null);
        $longitudeColumn = Schema::hasColumn('activities', 'longitude') ? 'longitude' : (Schema::hasColumn('activities', 'lng') ? 'lng' : null);
        $hasMunicipalities = Schema::hasTable('municipalities');
        $hasProvinces = Schema::hasTable('provinces');
        $hasCategories = Schema::hasTable('categories');
        $hasActivityProvince = Schema::hasColumn('activities', 'province_id');
        $hasMunicipalityProvince = $hasMunicipalities && Schema::hasColumn('municipalities', 'province_id');

        $provinceId = $request->integer('province_id') ?: null;
        $categoryId = $request->integer('category_id') ?: null;
        $status = $this->normalizeStatus($request->string('status')->toString());

        if (! $latitudeColumn || ! $longitudeColumn) {
            return response()->json(['data' => [], 'meta' => ['reason' => 'missing_geo_columns']]);
        }

        $provinceIdExpr = match (true) {
            $hasMunicipalityProvince && $hasActivityProvince => 'COALESCE(m.province_id, a.province_id)',
            $hasMunicipalityProvince => 'm.province_id',
            $hasActivityProvince => 'a.province_id',
            default => 'NULL',
        };

        $categoryExpr = $hasCategories ? "COALESCE(c.name, 'Uncategorized')" : "'Uncategorized'";
        $municipalityExpr = $hasMunicipalities ? "COALESCE(m.name, '')" : "''";
        $provinceExpr = $hasProvinces ? "COALESCE(p.name, '')" : "''";

        $cacheKey = sprintf('map.points.%s.%s.%s', $provinceId ?? 'all', $categoryId ?? 'all', $status);

        try {
            $points = Cache::remember($cacheKey, now()->addMinutes(5), function () use (
                $provinceId,
                $categoryId,
                $status,
                $titleExpr,
                $latitudeColumn,
                $longitudeColumn,
                $hasMunicipalities,
                $hasProvinces,
                $hasCategories,
                $hasActivityProvince,
                $hasMunicipalityProvince,
                $provinceIdExpr,
                $categoryExpr,
                $municipalityExpr,
                $provinceExpr
            ) {
                $query = DB::table('activities as a');

                if ($hasMunicipalities) {
                    $query->leftJoin('municipalities as m', 'm.id', '=', 'a.municipality_id');
                }

                if ($hasProvinces && $hasMunicipalityProvince) {
                    $query->leftJoin('provinces as p', 'p.id', '=', 'm.province_id');
                }

                if ($hasCategories) {
                    $query->leftJoin('categories as c', 'c.id', '=', 'a.category_id');
                }

                return $query
                    ->select([
                        'a.id',
                        'a.category_id',
                        DB::raw($provinceIdExpr.' as province_id'),
                        DB::raw($titleExpr.' as title'),
                        DB::raw('a.'.$latitudeColumn.' as latitude'),
                        DB::raw('a.'.$longitudeColumn.' as longitude'),
                        'a.status',
                        DB::raw($categoryExpr.' as category'),
                        DB::raw($municipalityExpr.' as municipality'),
                        DB::raw($provinceExpr.' as province'),
                    ])
                    ->whereNotNull('a.'.$latitudeColumn)
                    ->whereNotNull('a.'.$longitudeColumn)
                    ->when($status, fn ($q) => $q->where('a.status', $status))
                    ->when($provinceId && $hasMunicipalityProvince, fn ($q) => $q->where('m.province_id', $provinceId))
                    ->when($provinceId && ! $hasMunicipalityProvince && $hasActivityProvince, fn ($q) => $q->where('a.province_id', $provinceId))
                    ->when($categoryId && Schema::hasColumn('activities', 'category_id'), fn ($q) => $q->where('a.category_id', $categoryId))
                    ->limit(500)
                    ->get();
            });

            return response()->json(['data' => $points]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'data' => [],
                'meta' => ['reason' => 'map_query_failed'],
            ]);
        }
    }

    private function normalizeStatus(string $requestedStatus): string
    {
        $status = $requestedStatus ?: self::DEFAULT_STATUS;

        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            return self::DEFAULT_STATUS;
        }

        return $status;
    }
}
