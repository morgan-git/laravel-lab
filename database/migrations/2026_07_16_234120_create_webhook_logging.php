<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_requests', function (Blueprint $table) {
            $table->id();
            $table->string('provider');          // discord, slack, etc
            $table->string('requester_id');      // guild_id, workspace_id, etc
            $table->string('requester_type');    // guild, workspace, channel, etc
            $table->json('payload_in');          // raw incoming request
            $table->json('payload_out')->nullable(); // what we sent back
            $table->string('action');            // random_post, search, etc
            $table->string('status');            // success, throttled, failed, unauthorized
            $table->timestamps();

            $table->index(['provider', 'requester_id']); // fast lookup for dedup/throttle
        });

        Schema::create('webhook_sent_posts', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('requester_id');
            $table->foreignId('feed_post_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sent_at');

            $table->index(['provider', 'requester_id']); // fast lookup for dedup
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_sent_posts');
        Schema::dropIfExists('webhook_requests');
    }
};
