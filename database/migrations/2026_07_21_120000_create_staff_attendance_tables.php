<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained()->restrictOnDelete();
            $table->date('attendance_date')->index();
            $table->dateTime('time_in_at')->nullable()->index();
            $table->dateTime('time_out_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['staff_profile_id', 'attendance_date']);
        });

        Schema::create('staff_attendance_scan_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained()->restrictOnDelete();
            $table->date('attendance_date')->index();
            $table->string('qr_bucket', 20);
            $table->dateTime('scanned_at')->index();
            $table->dateTime('expires_at')->index();
            $table->string('status', 20)->default('pending')->index();
            $table->string('resolution', 20)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['staff_profile_id', 'qr_bucket']);
            $table->index(['status', 'attendance_date', 'scanned_at'], 'attendance_scan_status_date_index');
        });

        Schema::create('staff_attendance_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_attendance_id')->constrained('staff_attendances')->restrictOnDelete();
            $table->foreignId('staff_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('scan_request_id')->nullable()->constrained('staff_attendance_scan_requests')->nullOnDelete();
            $table->string('event_type', 30)->index();
            $table->string('source', 30)->index();
            $table->dateTime('occurred_at')->index();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['staff_profile_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_attendance_events');
        Schema::dropIfExists('staff_attendance_scan_requests');
        Schema::dropIfExists('staff_attendances');
    }
};
