<?php

namespace App\Http\Requests\Cms;

use App\Enums\CmsPostStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCmsPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cms.manage') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:220'],
            'slug' => ['nullable', 'string', 'max:240'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(CmsPostStatus::class)],
            'content_type' => ['nullable', 'string', 'max:40'],
            'published_at' => ['nullable', 'date'],
            'scheduled_at' => ['nullable', 'date'],
            'category_id' => ['nullable', 'integer', 'exists:cms_categories,id'],
            'author_id' => ['nullable', 'integer', 'exists:cms_authors,id'],
            'reviewer_id' => ['nullable', 'integer', 'exists:cms_authors,id'],
            'featured_media_id' => ['nullable', 'integer', 'exists:cms_media,id'],
            'seo_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'canonical_url' => ['nullable', 'string', 'max:500'],
            'og_title' => ['nullable', 'string', 'max:180'],
            'og_description' => ['nullable', 'string', 'max:320'],
            'og_image_path' => ['nullable', 'string', 'max:255'],
            'robots' => ['nullable', 'string', 'max:80'],
            'primary_keyword' => ['nullable', 'string', 'max:120'],
            'secondary_keywords' => ['nullable'],
            'cta_label' => ['nullable', 'string', 'max:120'],
            'cta_url' => ['nullable', 'string', 'max:255'],
            'needs_clinical_review' => ['nullable', 'boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:cms_tags,id'],
            'auto_redirect' => ['nullable', 'boolean'],
            'faq_json' => ['nullable', 'string'],
        ];
    }
}
