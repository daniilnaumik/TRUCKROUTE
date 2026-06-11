<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CargosController extends Controller
{
    /** GET /api/v1/cargos — saved cargo profiles for current user. */
    public function index(Request $request): JsonResponse
    {
        $cargos = $request->user()->cargos()->latest()->get();
        return response()->json(['data' => $cargos]);
    }

    /** POST /api/v1/cargos — save a reusable cargo profile. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'        => ['required', 'string', 'max:100'],
            'flag'         => ['nullable', 'string', 'max:50'],
            'weight_t'     => ['nullable', 'numeric', 'min:0', 'max:45'],
            'requirements' => ['nullable', 'string', 'max:255'],
        ]);

        $cargo = $request->user()->cargos()->create($data);
        return response()->json(['data' => $cargo], 201);
    }

    /** PATCH /api/v1/cargos/{cargo} */
    public function update(Request $request, Cargo $cargo): JsonResponse
    {
        abort_unless($cargo->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'title'        => ['sometimes', 'string', 'max:100'],
            'flag'         => ['sometimes', 'string', 'max:50'],
            'weight_t'     => ['sometimes', 'numeric', 'min:0', 'max:45'],
            'requirements' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $cargo->update($data);
        return response()->json(['data' => $cargo]);
    }

    /** DELETE /api/v1/cargos/{cargo} */
    public function destroy(Request $request, Cargo $cargo): JsonResponse
    {
        abort_unless($cargo->user_id === $request->user()->id, 403);
        $cargo->delete();
        return response()->json(['message' => 'Профиль груза удалён.']);
    }
}
