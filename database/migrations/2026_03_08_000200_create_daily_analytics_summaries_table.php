<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_analytics_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('summary_date');
            $table->unsignedBigInteger('province_id')->nullable();
            $table->unsignedInteger('total_views')->default(0);
            $table->unsignedInteger('total_visits')->default(0);
            $table->unsignedInteger('unique_users')->default(0);
            $table->json('top_categories')->nullable();
            $table->json('top_activities')->nullable();
            $table->timestamps();

            $table->unique(['summary_date', 'province_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_analytics_summaries');
    }
};
