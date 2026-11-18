<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsAnalyticsEvent extends Model
{
    public $timestamps = false;

    protected $table = 'cms_analytics_events';

    protected $fillable = [
        'event',
        'post_id',
        'path',
        'referrer',
        'meta',
        'session_hash',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CmsPost::class, 'post_id');
    }
}
