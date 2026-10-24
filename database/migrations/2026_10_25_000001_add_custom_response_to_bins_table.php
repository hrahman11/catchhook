<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bins', function (Blueprint $table) {
            $table->unsignedSmallInteger('response_status')->nullable()->after('name');
            $table->json('response_headers')->nullable()->after('response_status');
            $table->text('response_body')->nullable()->after('response_headers');
            $table->unsignedInteger('response_delay_ms')->default(0)->after('response_body');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bins', function (Blueprint $table) {
            $table->dropColumn(['response_status', 'response_headers', 'response_body', 'response_delay_ms']);
        });
    }
};
