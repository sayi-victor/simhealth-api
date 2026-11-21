<?php

namespace App\Http\Resources;

use App\Models\FormDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FormDefinition */
class FormDefinitionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'version' => $this->version,
            'is_active' => $this->is_active,
            'fields' => $this->whenLoaded('fields', fn () => $this->fields->map(fn ($f) => [
                'id' => $f->id,
                'field_key' => $f->field_key,
                'label' => $f->label,
                'group_name' => $f->group_name,
                'field_type' => $f->field_type,
                'is_required' => $f->is_required,
                'sort_order' => $f->sort_order,
                'options' => $f->options,
                'default_value' => $f->default_value,
                'help_text' => $f->help_text,
            ])->values()->all()),
        ];
    }
}
