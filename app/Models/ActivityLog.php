<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use Throwable;

class ActivityLog extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'event',
        'module',
        'description',
        'subject_type',
        'subject_id',
        'properties',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function eventLabel(): string
    {
        return Str::headline(str_replace('_', ' ', $this->event));
    }

    public function eventBadgeClass(): string
    {
        return match ($this->event) {
            'created' => 'badge bg-success',
            'updated', 'status_changed' => 'badge bg-info',
            'deleted' => 'badge bg-danger',
            'login' => 'badge bg-primary',
            'logout' => 'badge bg-secondary',
            'login_failed' => 'badge bg-warning',
            'password_reset' => 'badge bg-warning',
            'impersonated', 'impersonation_stopped' => 'badge bg-dark',
            'email_tested' => 'badge badge-soft-info',
            default => 'badge bg-light text-dark',
        };
    }

    public function moduleLabel(): string
    {
        return Str::headline($this->module);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(
        string $event,
        mixed $subject = null,
        array $properties = [],
        ?string $description = null,
        ?string $module = null,
        ?User $causer = null,
    ): void {
        try {
            $causer ??= auth()->user();
            $model = $subject instanceof Model ? $subject : null;

            static::query()->create([
                'user_id' => $causer?->id,
                'event' => $event,
                'module' => $module ?? ($model ? Str::snake(class_basename($model)) : 'system'),
                'description' => $description ?: static::defaultDescription($event, $model, $causer),
                'subject_type' => $model ? $model::class : null,
                'subject_id' => $model?->getKey(),
                'properties' => static::sanitize($properties),
                'ip_address' => request()->ip(),
                'user_agent' => Str::limit((string) request()->userAgent(), 255, ''),
            ]);
        } catch (Throwable) {
            // Logging must never break the main action.
        }
    }

    protected static function defaultDescription(string $event, ?Model $subject, ?User $causer): string
    {
        $actor = $causer?->name ?? 'System';
        $target = $subject
            ? (string) ($subject->getAttribute('name')
                ?? $subject->getAttribute('title')
                ?? $subject->getAttribute('email')
                ?? class_basename($subject).'#'.$subject->getKey())
            : 'application';

        return $actor.' '.$event.' '.$target;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function sanitize(array $properties): array
    {
        $hidden = [
            'password',
            'password_confirmation',
            'remember_token',
            'mail_password',
            'token',
            'client_secret',
            'secret_key',
            'user_key',
            'cons_id',
            'credentials',
        ];

        foreach ($properties as $key => $value) {
            if (in_array(strtolower((string) $key), $hidden, true)) {
                $properties[$key] = '********';

                continue;
            }

            if (is_array($value)) {
                $properties[$key] = static::sanitize($value);
            }
        }

        return $properties;
    }
}
