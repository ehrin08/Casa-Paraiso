<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_error_reports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->char('fingerprint', 64)->unique();
            $table->unsignedSmallInteger('http_status')->index();
            $table->string('category', 40)->index();
            $table->string('exception_class')->nullable();
            $table->text('message')->nullable();
            $table->string('source_file', 1024)->nullable();
            $table->unsignedInteger('source_line')->nullable();
            $table->longText('stack_frames')->nullable();
            $table->unsignedBigInteger('occurrence_count')->default(0);
            $table->string('last_request_id', 36)->nullable()->index();
            $table->string('last_route_name')->nullable()->index();
            $table->string('last_request_method', 12)->nullable();
            $table->string('last_request_path', 2048)->nullable();
            $table->foreignId('last_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('first_seen_at')->index();
            $table->dateTime('last_seen_at')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable()->index();
            $table->timestamps();
            $table->index(['category', 'resolved_at', 'last_seen_at'], 'system_error_report_inbox_index');
        });

        Schema::create('system_error_occurrences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('system_error_report_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_id')->unique();
            $table->string('route_name')->nullable()->index();
            $table->string('request_method', 12);
            $table->string('request_path', 2048);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('occurred_at')->index();
            $table->timestamps();
            $table->index(['system_error_report_id', 'occurred_at'], 'system_error_occurrence_report_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_error_occurrences');
        Schema::dropIfExists('system_error_reports');
    }
};
