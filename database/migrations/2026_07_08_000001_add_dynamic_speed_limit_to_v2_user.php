<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('v2_user') && !Schema::hasColumn('v2_user', 'dynamic_speed_limit')) {
            Schema::table('v2_user', function (Blueprint $table) {
                $table->json('dynamic_speed_limit')->nullable()->after('device_limit');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('v2_user') && Schema::hasColumn('v2_user', 'dynamic_speed_limit')) {
            Schema::table('v2_user', function (Blueprint $table) {
                $table->dropColumn('dynamic_speed_limit');
            });
        }
    }
};
