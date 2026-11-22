<?php

namespace Workdo\Chatter\Traits;

use Workdo\Chatter\Models\ChatterMessage;
use Workdo\Chatter\Models\ChatterActivity;
use Workdo\Chatter\Models\ChatterFollower;
use Illuminate\Support\Facades\Auth;

trait HasChatter
{
    public static function bootHasChatter(): void
    {
        static::updated(function ($model) {
            $companyId = $model->created_by ?? (isset($model->company_id) ? $model->company_id : (Auth::check() ? creatorId() : null));
            if (!$companyId) return;

            // Only track if tenant has Chatter active in their subscription plan
            if (function_exists('module_is_active') && !module_is_active('Chatter', $companyId)) {
                return;
            }

            // Get fields configured for automated audit diff tracking
            $tracked = property_exists($model, 'chatterTrackedFields')
                ? $model->chatterTrackedFields
                : ['status', 'total_amount', 'amount', 'subtotal', 'due_date', 'driver_name', 'vehicle_plate', 'priority', 'warehouse_id', 'stage_id'];

            $changes = [];
            $diffText = [];

            foreach ($tracked as $field) {
                if ($model->isDirty($field)) {
                    $old = $model->getOriginal($field);
                    $new = $model->getAttribute($field);

                    // Skip trivial changes (e.g. null to empty string)
                    if (trim((string) $old) === trim((string) $new)) {
                        continue;
                    }

                    $changes[$field] = [
                        'old' => $old,
                        'new' => $new,
                    ];

                    $label = ucwords(str_replace('_', ' ', $field));
                    $oldStr = $old !== null && $old !== '' ? $old : '—';
                    $newStr = $new !== null && $new !== '' ? $new : '—';
                    $diffText[] = "**{$label}**: `{$oldStr}` → `{$newStr}`";
                }
            }

            if (!empty($changes)) {
                $user = Auth::user();
                $authorName = $user ? $user->name : 'System';
                $modelType = class_basename(static::class);

                ChatterMessage::create([
                    'created_by' => $companyId,
                    'model_type' => $modelType,
                    'model_id'   => $model->id,
                    'user_id'    => $user ? $user->id : ($model->created_by ?? 1),
                    'message'    => "📝 **Field changes logged** by {$authorName}:\n" . implode("\n", $diffText),
                    'type'       => 'diff',
                    'metadata'   => ['changes' => $changes],
                ]);
            }
        });
    }

    public function chatterMessages()
    {
        return $this->hasMany(ChatterMessage::class, 'model_id')
            ->where('model_type', class_basename(static::class))
            ->orderBy('id', 'desc');
    }

    public function chatterActivities()
    {
        return $this->hasMany(ChatterActivity::class, 'model_id')
            ->where('model_type', class_basename(static::class))
            ->orderBy('due_date', 'asc');
    }

    public function chatterFollowers()
    {
        return $this->hasMany(ChatterFollower::class, 'model_id')
            ->where('model_type', class_basename(static::class));
    }

    public function isFollowedBy(int $userId): bool
    {
        return $this->chatterFollowers()->where('user_id', $userId)->exists();
    }

    public function addChatterFollower(int $userId): ChatterFollower
    {
        $companyId = $this->created_by ?? (Auth::check() ? creatorId() : 1);
        return ChatterFollower::firstOrCreate([
            'created_by' => $companyId,
            'model_type' => class_basename(static::class),
            'model_id'   => $this->id,
            'user_id'    => $userId,
        ]);
    }

    public function removeChatterFollower(int $userId): bool
    {
        return (bool) $this->chatterFollowers()->where('user_id', $userId)->delete();
    }
}
