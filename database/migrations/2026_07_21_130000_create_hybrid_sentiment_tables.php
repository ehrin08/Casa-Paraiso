<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback', function (Blueprint $table): void {
            $table->string('sentiment_source', 16)->default('rules')->after('sentiment_analysis_version');
            $table->decimal('sentiment_confidence', 5, 4)->nullable()->after('sentiment_source');
        });

        Schema::create('feedback_sentiment_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('feedback_id')->constrained('feedback')->cascadeOnDelete();
            $table->string('source', 16);
            $table->string('classifier_version', 64);
            $table->string('label', 16);
            $table->decimal('score', 5, 2)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('evidence')->nullable();
            $table->boolean('is_authoritative')->default(false);
            $table->timestamps();
            $table->index(['feedback_id', 'source']);
            $table->index(['classifier_version', 'is_authoritative'], 'sentiment_runs_classifier_auth_index');
        });

        Schema::create('feedback_annotations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('feedback_id')->constrained('feedback')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('label', 16);
            $table->string('language', 16);
            $table->json('topics')->nullable();
            $table->string('status', 16)->default('submitted');
            $table->text('notes')->nullable();
            $table->timestamp('adjudicated_at')->nullable();
            $table->timestamps();
            $table->unique(['feedback_id', 'reviewer_id']);
            $table->index(['status', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_annotations');
        Schema::dropIfExists('feedback_sentiment_runs');
        Schema::table('feedback', function (Blueprint $table): void {
            $table->dropColumn(['sentiment_source', 'sentiment_confidence']);
        });
    }
};
