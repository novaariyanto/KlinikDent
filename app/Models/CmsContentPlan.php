<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsContentPlan extends Model
{
    protected $table = 'cms_content_plans';

    protected $fillable = [
        'title',
        'target_keyword',
        'search_intent',
        'category_id',
        'assignee_id',
        'target_publish_date',
        'status',
        'priority',
        'outline',
        'references',
        'target_internal_links',
        'editor_notes',
        'post_id',
    ];

    protected function casts(): array
    {
        return [
            'target_publish_date' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CmsCategory::class, 'category_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CmsPost::class, 'post_id');
    }
}
