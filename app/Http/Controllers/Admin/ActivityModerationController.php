<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class ActivityModerationController extends Controller
{
    private const ALLOWED_STATUSES = ['pending', 'rejected', 'approved'];

    public function index(Request $request): Response
    {
        abort_unless($this->tableExists('activities'), 404);

        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());

        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            $status = 'pending';
        }

        $titleExpr = $this->activityTitleExpression();
        $hasProviders = $this->tableExists('providers');
        $hasUserName = Schema::hasColumn('users', 'name');
        $hasUserFirstName = Schema::hasColumn('users', 'first_name');
        $hasUserLastName = Schema::hasColumn('users', 'last_name');
        $hasUserEmail = Schema::hasColumn('users', 'email');

        $providerExpr = $this->buildProviderExpression($hasProviders, $hasUserName, $hasUserFirstName, $hasUserLastName);

        $queueQuery = DB::table('activities as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.provider_id')
            ->leftJoin('municipalities as m', 'm.id', '=', 'a.municipality_id')
            ->select([
                'a.id',
                DB::raw($titleExpr.' as title'),
                'a.status',
                DB::raw($providerExpr.' as provider_name'),
                DB::raw("COALESCE(m.name, '-') as municipality"),
                'a.created_at',
            ])
            ->where('a.status', $status)
            ->when($search !== '', function ($query) use ($titleExpr, $search, $hasUserName, $hasUserFirstName, $hasUserLastName, $hasUserEmail) {
                $query->where(function ($inner) use ($titleExpr, $search, $hasUserName, $hasUserFirstName, $hasUserLastName, $hasUserEmail) {
                    $inner->whereRaw($titleExpr.' like ?', ['%'.$search.'%'])
                        ->orWhere('m.name', 'like', '%'.$search.'%');

                    if ($hasUserName) {
                        $inner->orWhere('u.name', 'like', '%'.$search.'%');
                    }

                    if ($hasUserFirstName) {
                        $inner->orWhere('u.first_name', 'like', '%'.$search.'%');
                    }

                    if ($hasUserLastName) {
                        $inner->orWhere('u.last_name', 'like', '%'.$search.'%');
                    }

                    if ($hasUserEmail) {
                        $inner->orWhere('u.email', 'like', '%'.$search.'%');
                    }
                });
            })
            ->orderByDesc('a.created_at');

        $queue = $queueQuery->paginate(20)->withQueryString();

        return Inertia::render('Admin/Moderation/Activities', [
            'queue' => $queue,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    public function approve(Request $request, int $activityId): RedirectResponse
    {
        $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        $current = $this->getActivityOrAbort($activityId);

        $this->updateActivityStatus($activityId, 'approved', $request->user()->id);
        $this->insertApprovalLog($activityId, $request->user()->id, $current->status, 'approved', $request->string('notes')->toString());

        AuditLogger::log('activity.approve', 'activity', $activityId, ['from' => $current->status]);

        return back()->with('success', 'Activity approved.');
    }

    public function reject(Request $request, int $activityId): RedirectResponse
    {
        $request->validate(['notes' => ['required', 'string', 'max:500']]);

        $current = $this->getActivityOrAbort($activityId);

        $this->updateActivityStatus($activityId, 'rejected', $request->user()->id);
        $this->insertApprovalLog($activityId, $request->user()->id, $current->status, 'rejected', $request->string('notes')->toString());

        AuditLogger::log('activity.reject', 'activity', $activityId, ['from' => $current->status]);

        return back()->with('success', 'Activity rejected.');
    }

    private function getActivityOrAbort(int $activityId): object
    {
        $activity = DB::table('activities')->where('id', $activityId)->first();
        abort_if(! $activity, 404);

        return $activity;
    }

    private function buildProviderExpression(bool $hasProviders, bool $hasUserName, bool $hasUserFirstName, bool $hasUserLastName): string
    {
        $userDisplayExpr = "'Unknown Provider'";
        if ($hasUserName) {
            $userDisplayExpr = 'u.name';
        } elseif ($hasUserFirstName && $hasUserLastName) {
            $userDisplayExpr = "TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')))";
        } elseif ($hasUserFirstName) {
            $userDisplayExpr = 'u.first_name';
        } elseif ($hasUserLastName) {
            $userDisplayExpr = 'u.last_name';
        }

        return $hasProviders
            ? "COALESCE(p.business_name, {$userDisplayExpr}, 'Unknown Provider')"
            : "COALESCE({$userDisplayExpr}, 'Unknown Provider')";
    }

    private function updateActivityStatus(int $activityId, string $targetStatus, int $actorUserId): void
    {
        $update = [
            'status' => $targetStatus,
            'updated_at' => now(),
        ];

        if ($targetStatus === 'approved') {
            if (Schema::hasColumn('activities', 'approved_by')) {
                $update['approved_by'] = $actorUserId;
            }

            if (Schema::hasColumn('activities', 'approved_at')) {
                $update['approved_at'] = now();
            }
        }

        DB::table('activities')->where('id', $activityId)->update($update);
    }

    private function insertApprovalLog(int $activityId, int $actorUserId, string $fromStatus, string $toStatus, string $notes): void
    {
        if (! $this->tableExists('approval_logs')) {
            return;
        }

        DB::table('approval_logs')->insert([
            'activity_id' => $activityId,
            'acted_by' => $actorUserId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'notes' => $notes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
