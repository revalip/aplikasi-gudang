<?php

namespace App\Models\Concerns;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for primary models: automatically records created / updated (before→after) / deleted in Activity Log.
 * Optional on model: protected string $activityName = 'Sales Order'; protected array $activityIgnore = ['field'];
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model) {
            ActivityLogger::log(
                'created',
                "Create {$model->activityName()} " . ActivityLogger::labelOf($model),
                $model,
                ['new' => $model->activityAttributes($model->getAttributes())],
            );
        });

        static::updated(function (Model $model) {
            $changes = $model->activityAttributes($model->getChanges());
            if (! $changes) {
                return;
            }

            $old = [];
            foreach (array_keys($changes) as $field) {
                $old[$field] = $model->getRawOriginal($field);
            }

            $label = ActivityLogger::labelOf($model);
            if (array_key_exists('status', $changes) && $old['status'] !== $changes['status']) {
                $event = 'status_changed';
                $desc = "Status {$model->activityName()} {$label}: {$old['status']} → {$changes['status']}";
            } else {
                $event = 'updated';
                $desc = "Update {$model->activityName()} {$label} (" . implode(', ', array_keys($changes)) . ')';
            }

            ActivityLogger::log($event, $desc, $model, ['old' => $old, 'new' => $changes]);
        });

        static::deleted(function (Model $model) {
            ActivityLogger::log(
                'deleted',
                "Delete {$model->activityName()} " . ActivityLogger::labelOf($model),
                $model,
                ['old' => $model->activityAttributes($model->getAttributes())],
            );
        });
    }

    public function activityName(): string
    {
        return $this->activityName ?? class_basename($this);
    }

    /** Exclude technical columns and model-specific ignored attributes. */
    public function activityAttributes(array $attributes): array
    {
        $ignore = array_merge(['created_at', 'updated_at', 'id'], $this->activityIgnore ?? [], ActivityLogger::MASKED);

        return array_diff_key($attributes, array_flip($ignore));
    }
}
