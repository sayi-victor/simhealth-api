<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    protected $fillable = [
        'form_definition_id',
        'field_key',
        'label',
        'group_name',
        'field_type',
        'is_required',
        'sort_order',
        'options',
        'default_value',
        'help_text',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'options' => 'array',
        ];
    }

    /** @return BelongsTo<FormDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(FormDefinition::class, 'form_definition_id');
    }
}
