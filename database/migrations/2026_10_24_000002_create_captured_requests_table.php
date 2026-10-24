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
        Schema::create('captured_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('bin_id', 16);
            $table->string('method', 10);
            $table->text('path');
            $table->text('query_string')->nullable();
            $table->json('headers');
            $table->longText('body')->nullable();
            $table->string('body_encoding', 8)->default('utf8');
            $table->string('content_type')->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('bin_id')->references('id')->on('bins')->cascadeOnDelete();
            $table->index(['bin_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('captured_requests');
    }
};
