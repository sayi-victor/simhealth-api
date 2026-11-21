<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FacilityResource;
use App\Models\Facility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FacilityController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max((int) $request->integer('per_page', 25), 1), 100);
        $search = trim((string) $request->query('search', ''));
        $sortBy = in_array($request->query('sort'), ['name', 'code', 'created_at'], true)
            ? (string) $request->query('sort')
            : 'name';
        $sortDir = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $query = Facility::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return FacilityResource::collection(
            $query->orderBy($sortBy, $sortDir)->paginate($perPage)
        );
    }

    public function show(Facility $facility): FacilityResource
    {
        return new FacilityResource($facility);
    }
}
