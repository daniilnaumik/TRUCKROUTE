<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DictionaryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DictionaryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $requested = $request->string('dictionary')->trim()->value();
        $dictionaries = $requested !== ''
            ? array_values(array_intersect(DictionaryItem::DICTIONARIES, [$requested]))
            : DictionaryItem::DICTIONARIES;

        $items = DictionaryItem::query()
            ->active()
            ->whereIn('dictionary', $dictionaries)
            ->ordered()
            ->get(['id', 'dictionary', 'value', 'label', 'description', 'sort_order']);

        $data = [];
        foreach ($dictionaries as $dictionary) {
            $data[$dictionary] = $items
                ->where('dictionary', $dictionary)
                ->values()
                ->map(fn (DictionaryItem $item) => [
                    'id' => $item->id,
                    'value' => $item->value,
                    'label' => $item->label,
                    'description' => $item->description,
                    'sort_order' => $item->sort_order,
                ]);
        }

        return response()->json(['data' => $data]);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $query = DictionaryItem::query()->ordered();

        if ($request->filled('dictionary')) {
            $request->validate([
                'dictionary' => ['string', Rule::in(DictionaryItem::DICTIONARIES)],
            ]);
            $query->where('dictionary', $request->string('dictionary')->value());
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge([
            'value' => trim((string) ($request->input('value') ?: $request->input('label'))),
        ]);
        $data = $this->validateItem($request);

        $item = DictionaryItem::create($data);

        return response()->json([
            'message' => 'Значение справочника добавлено.',
            'data' => $item,
        ], 201);
    }

    public function update(Request $request, DictionaryItem $dictionaryItem): JsonResponse
    {
        $data = $this->validateItem($request, $dictionaryItem);
        if (array_key_exists('value', $data)) {
            $data['value'] = trim($data['value']);
        }

        $dictionaryItem->update($data);

        return response()->json([
            'message' => 'Значение справочника обновлено.',
            'data' => $dictionaryItem->fresh(),
        ]);
    }

    public function destroy(DictionaryItem $dictionaryItem): JsonResponse
    {
        $dictionaryItem->delete();

        return response()->json([
            'message' => 'Значение удалено из справочника. Ранее созданные записи сохранены.',
        ]);
    }

    private function validateItem(Request $request, ?DictionaryItem $item = null): array
    {
        $dictionary = $request->input('dictionary', $item?->dictionary);

        return $request->validate([
            'dictionary' => [
                $item ? 'sometimes' : 'required',
                'string',
                Rule::in(DictionaryItem::DICTIONARIES),
            ],
            'label' => [$item ? 'sometimes' : 'required', 'string', 'min:1', 'max:100'],
            'value' => [
                'nullable',
                'string',
                'min:1',
                'max:100',
                Rule::unique('dictionary_items', 'value')
                    ->where(fn ($query) => $query->where('dictionary', $dictionary))
                    ->ignore($item?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
