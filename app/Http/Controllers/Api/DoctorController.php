<?php

namespace App\Http\Controllers\Api;

use App\Enums\Specialty;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\DoctorResource;
use App\Services\DoctorCacheService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DoctorController extends Controller
{
    public function index(
        Request $request,
        DoctorCacheService $doctorCacheService
    ) {
        $validated = $request->validate([
            'branch_id' => [
                'required',
                'integer',
                'exists:branches,id',
            ],
            'specialty' => [
                'nullable',
                Rule::enum(Specialty::class),
            ],
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
            ],
        ]);

        $doctors = $doctorCacheService->getDoctors(
            (int) $validated['branch_id'],
            $validated['specialty'] ?? null,
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 15)
        );

        return ApiResponse::success([
            'items' => DoctorResource::collection(
                $doctors->getCollection()
            )->resolve($request),

            'pagination' => [
                'current_page' => $doctors->currentPage(),
                'last_page' => $doctors->lastPage(),
                'per_page' => $doctors->perPage(),
                'total' => $doctors->total(),
            ],
        ], 'Doctors retrieved successfully.');
    }
}
