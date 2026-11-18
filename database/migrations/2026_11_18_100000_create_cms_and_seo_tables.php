<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('seo_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('source_path', 255);
            $table->string('destination_url', 500);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hit_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('source_path');
            $table->index(['is_active', 'source_path']);
        });

        Schema::create('cms_authors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->string('role_title', 120)->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar_path', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cms_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->string('seo_title', 180)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cms_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        Schema::create('cms_media', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 40)->default('public');
            $table->string('path', 255);
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt', 255)->nullable();
            $table->string('caption', 255)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('path');
        });

        Schema::create('cms_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 220);
            $table->string('slug', 240)->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('body')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('content_type', 40)->default('article');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('cms_categories')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('cms_authors')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('cms_authors')->nullOnDelete();
            $table->foreignId('featured_media_id')->nullable()->constrained('cms_media')->nullOnDelete();
            $table->string('seo_title', 180)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->string('og_title', 180)->nullable();
            $table->string('og_description', 320)->nullable();
            $table->string('og_image_path', 255)->nullable();
            $table->string('robots', 80)->nullable();
            $table->string('primary_keyword', 120)->nullable();
            $table->json('secondary_keywords')->nullable();
            $table->json('faq')->nullable();
            $table->json('related_post_ids')->nullable();
            $table->string('cta_label', 120)->nullable();
            $table->string('cta_url', 255)->nullable();
            $table->boolean('needs_clinical_review')->default(false);
            $table->unsignedInteger('reading_time_minutes')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index('category_id');
            $table->index('author_id');
        });

        Schema::create('cms_post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained('cms_posts')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('cms_tags')->cascadeOnDelete();
            $table->primary(['post_id', 'tag_id']);
        });

        Schema::create('cms_post_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('cms_posts')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('summary', 255)->nullable();
            $table->string('status', 20)->nullable();
            $table->json('snapshot');
            $table->timestamps();

            $table->unique(['post_id', 'version']);
        });

        Schema::create('cms_content_plans', function (Blueprint $table) {
            $table->id();
            $table->string('title', 220);
            $table->string('target_keyword', 160)->nullable();
            $table->string('search_intent', 40)->nullable();
            $table->foreignId('category_id')->nullable()->constrained('cms_categories')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('target_publish_date')->nullable();
            $table->string('status', 30)->default('idea');
            $table->string('priority', 20)->default('normal');
            $table->text('outline')->nullable();
            $table->text('references')->nullable();
            $table->text('target_internal_links')->nullable();
            $table->text('editor_notes')->nullable();
            $table->foreignId('post_id')->nullable()->constrained('cms_posts')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'target_publish_date']);
        });

        Schema::create('cms_keywords', function (Blueprint $table) {
            $table->id();
            $table->string('keyword', 160);
            $table->string('slug', 180)->unique();
            $table->string('pillar', 80)->nullable();
            $table->string('intent', 40)->nullable();
            $table->string('priority', 20)->default('normal');
            $table->string('status', 30)->default('planned');
            $table->string('target_url', 255)->nullable();
            $table->foreignId('post_id')->nullable()->constrained('cms_posts')->nullOnDelete();
            $table->date('review_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['pillar', 'status']);
        });

        Schema::create('cms_analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('event', 60);
            $table->foreignId('post_id')->nullable()->constrained('cms_posts')->nullOnDelete();
            $table->string('path', 255)->nullable();
            $table->string('referrer', 255)->nullable();
            $table->string('meta', 255)->nullable();
            $table->string('session_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event', 'created_at']);
            $table->index('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_analytics_events');
        Schema::dropIfExists('cms_keywords');
        Schema::dropIfExists('cms_content_plans');
        Schema::dropIfExists('cms_post_revisions');
        Schema::dropIfExists('cms_post_tag');
        Schema::dropIfExists('cms_posts');
        Schema::dropIfExists('cms_media');
        Schema::dropIfExists('cms_tags');
        Schema::dropIfExists('cms_categories');
        Schema::dropIfExists('cms_authors');
        Schema::dropIfExists('seo_redirects');
        Schema::dropIfExists('seo_settings');
    }
};
