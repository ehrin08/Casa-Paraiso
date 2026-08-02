<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback', function (Blueprint $table): void {
            $table->string('sentiment_analysis_version', 32)->nullable()->after('sentiment_score');
            $table->json('sentiment_evidence')->nullable()->after('sentiment_analysis_version');
        });

        Schema::create('feedback_topics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('feedback_id')->constrained('feedback')->cascadeOnDelete();
            $table->string('topic_key', 64);
            $table->string('polarity', 16);
            $table->json('matched_terms')->nullable();
            $table->timestamps();
            $table->unique(['feedback_id', 'topic_key', 'polarity']);
            $table->index(['topic_key', 'polarity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_topics');
        Schema::table('feedback', function (Blueprint $table): void {
            $table->dropColumn(['sentiment_analysis_version', 'sentiment_evidence']);
        });
    }
};
