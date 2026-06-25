<?php

namespace App\Support;

use App\Models\LuTemplate;
use App\Models\TimekeepingTemplate as TimekeepingTemplateModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TimekeepingTemplate
{
    public const LOG_TABLE = 'tbl_timekeeping_templates';

    public static function authorize(?User $user, string $permission): void
    {
        TimekeepingPolicy::authorize($user, $permission);
    }

    public static function routeName(string $action): string
    {
        return "timekeeping.templates.$action";
    }

    public static function listQuery(): Builder
    {
        return TimekeepingTemplateModel::query()
            ->with('templateType')
            ->orderByDesc('timekeeping_template_id');
    }

    public static function findOrFail(int $id): TimekeepingTemplateModel
    {
        return TimekeepingTemplateModel::query()->with('templateType')->findOrFail($id);
    }

    public static function recordLabel(TimekeepingTemplateModel $record): string
    {
        $typeLabel = $record->templateType?->template ?? 'Template #'.$record->template_name;

        return $typeLabel.' (ID '.$record->timekeeping_template_id.')';
    }

    /**
     * @return array<int, string>
     */
    public static function templateTypeOptions(): array
    {
        return LuTemplate::query()
            ->orderBy('template_id')
            ->pluck('template', 'template_id')
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function placeholdersForType(?int $templateTypeId): array
    {
        if ($templateTypeId === null) {
            return [];
        }

        return config('timekeeping_templates.placeholders.'.$templateTypeId, []);
    }

    public static function validationRules(?int $ignoreId = null): array
    {
        return [
            'template_name' => ['required', 'integer', Rule::exists('lu_template', 'template_id')],
            'content' => ['required', 'string', 'max:'.config('timekeeping_templates.content_max_length', 1000)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public static function validate(array $data, ?int $ignoreId = null): array
    {
        $validator = Validator::make($data, self::validationRules($ignoreId));

        $validator->after(function ($validator) use ($data, $ignoreId) {
            $content = (string) ($data['content'] ?? '');

            if ($content !== strip_tags($content)) {
                $validator->errors()->add('content', 'HTML tags are not allowed in the content field.');
            }

            $isActive = filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if (! $isActive) {
                return;
            }

            $templateName = (int) ($data['template_name'] ?? 0);

            $existingActive = TimekeepingTemplateModel::query()
                ->where('template_name', $templateName)
                ->where('is_active', true)
                ->when($ignoreId, fn (Builder $query) => $query->where('timekeeping_template_id', '!=', $ignoreId))
                ->exists();

            if ($existingActive) {
                $typeLabel = LuTemplate::query()->where('template_id', $templateName)->value('template') ?? 'this type';
                $validator->errors()->add('is_active', 'There is already an active template for: '.$typeLabel.'.');
            }
        });

        return $validator->validate();
    }

    public static function headerPayload(array $validated): array
    {
        return [
            'template_name' => (int) $validated['template_name'],
            'content' => trim($validated['content']),
            'is_active' => filter_var($validated['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    public static function isActive(TimekeepingTemplateModel $record): bool
    {
        return (bool) $record->is_active;
    }

    public static function activeConflictMessage(TimekeepingTemplateModel $record): ?string
    {
        $existingActive = TimekeepingTemplateModel::query()
            ->where('template_name', $record->template_name)
            ->where('is_active', true)
            ->where('timekeeping_template_id', '!=', $record->timekeeping_template_id)
            ->exists();

        if (! $existingActive) {
            return null;
        }

        $typeLabel = LuTemplate::query()->where('template_id', $record->template_name)->value('template') ?? 'this type';

        return 'There is already an active template for: '.$typeLabel.'.';
    }

    /**
     * Load active template body for a notification event type (paths-mvc fetch_template).
     */
    public static function fetchActiveContent(int $templateTypeId): ?string
    {
        $content = TimekeepingTemplateModel::query()
            ->where('template_name', $templateTypeId)
            ->where('is_active', true)
            ->value('content');

        return filled($content) ? (string) $content : null;
    }
}
