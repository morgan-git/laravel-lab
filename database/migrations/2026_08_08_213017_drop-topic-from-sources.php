<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_sources', function (Blueprint $table) {
            $table->dropColumn('topic');
        });
    }

    public function down(): void
    {
        Schema::table('feed_sources', function (Blueprint $table) {
            $table->string('topic')->nullable()->after('handle');
        });
    }
};
