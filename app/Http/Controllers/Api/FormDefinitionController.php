<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FormDefinitionResource;
use App\Models\FormDefinition;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FormDefinitionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $definitions = FormDefinition::query()
            ->with('fields')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return FormDefinitionResource::collection($definitions);
    }

    public function show(FormDefinition $formDefinition): FormDefinitionResource
    {
        $formDefinition->load('fields');

        return new FormDefinitionResource($formDefinition);
    }
}
