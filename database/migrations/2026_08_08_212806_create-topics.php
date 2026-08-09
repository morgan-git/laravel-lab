<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_sources', function (Blueprint $table) {
            $table->foreignId('topic_id')
                ->nullable()
                ->after('topic')
                ->constrained('topics')
                ->nullOnDelete();
        });

        // Backfill: for every distinct existing topic string, find or
        // create the matching Topic row, then point every feed_source
        // that had that string at the new topic_id. The old `topic`
        // column is left alone here — dropped in a later migration
        // only after this data has been confirmed correct.
        $distinctTopics = DB::table('feed_sources')
            ->whereNotNull('topic')
            ->distinct()
            ->pluck('topic');

        foreach ($distinctTopics as $topicName) {
            $topicId = DB::table('topics')->insertGetId([
                'name' => $topicName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('feed_sources')
                ->where('topic', $topicName)
                ->update(['topic_id' => $topicId]);
        }
    }

    public function down(): void
    {
        Schema::table('feed_sources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('topic_id');
        });
    }
};
