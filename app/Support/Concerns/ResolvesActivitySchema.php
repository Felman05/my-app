<?php

namespace App\Support\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait ResolvesActivitySchema
{
    protected function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    protected function resolveProviderId(int $userId): int
    {
        if (! $this->tableExists('providers')) {
            return $userId;
        }

        $providerId = DB::table('providers')->where('user_id', $userId)->value('id');

        if ($providerId) {
            return (int) $providerId;
        }

        return (int) DB::table('providers')->insertGetId([
            'user_id' => $userId,
            'business_name' => 'Doon Provider #'.$userId,
            'description' => 'Auto-created provider profile.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function activityTitleExpression(): string
    {
        $hasName = Schema::hasColumn('activities', 'name');
        $hasTitle = Schema::hasColumn('activities', 'title');

        if ($hasName) {
            return 'a.name';
        }

        if ($hasTitle) {
            return 'a.title';
        }

        return "'Untitled Activity'";
    }
}
