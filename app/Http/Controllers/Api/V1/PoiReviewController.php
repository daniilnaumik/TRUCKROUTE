<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PoiReviewResource;
use App\Models\PoiReview;
use App\Models\ServiceObject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PoiReviewController extends Controller
{
    public function index(ServiceObject $poi): AnonymousResourceCollection
    {
        $reviews = $poi->reviews()
            ->with('user:id,name,avatar')
            ->paginate(20);

        return PoiReviewResource::collection($reviews);
    }

    public function store(Request $request, ServiceObject $poi): JsonResponse
    {
        $this->guardProviderReview($request, $poi);
        $data = $this->reviewData($request);

        $review = PoiReview::updateOrCreate(
            [
                'service_object_id' => $poi->id,
                'user_id' => $request->user()->id,
            ],
            $data,
        );

        $this->syncRating($poi);

        return (new PoiReviewResource($review->load('user:id,name,avatar')))
            ->response()
            ->setStatusCode($review->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request, ServiceObject $poi, PoiReview $review): JsonResponse
    {
        $this->guardReviewBelongsToPoi($poi, $review);
        abort_unless($review->user_id === $request->user()->id, 403, 'Можно изменять только собственный отзыв.');

        $review->update($this->reviewData($request));
        $this->syncRating($poi);

        return response()->json([
            'data' => new PoiReviewResource($review->fresh()->load('user:id,name,avatar')),
        ]);
    }

    public function destroy(Request $request, ServiceObject $poi, PoiReview $review): JsonResponse
    {
        $this->guardReviewBelongsToPoi($poi, $review);
        $user = $request->user();
        abort_unless(
            $review->user_id === $user->id || $user->role === 'admin',
            403,
            'Можно удалить только собственный отзыв.',
        );

        $review->delete();
        $this->syncRating($poi);

        return response()->json(['message' => 'Отзыв удалён.']);
    }

    public function reply(Request $request, ServiceObject $poi, PoiReview $review): JsonResponse
    {
        $this->guardReviewBelongsToPoi($poi, $review);
        $this->guardPoiOwner($request, $poi);

        $data = $request->validate([
            'reply' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $review->update([
            'owner_reply' => trim($data['reply']),
            'owner_replied_at' => now(),
            'owner_reply_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new PoiReviewResource($review->fresh()->load('user:id,name,avatar')),
        ]);
    }

    public function deleteReply(Request $request, ServiceObject $poi, PoiReview $review): JsonResponse
    {
        $this->guardReviewBelongsToPoi($poi, $review);
        $this->guardPoiOwner($request, $poi);

        $review->update([
            'owner_reply' => null,
            'owner_replied_at' => null,
            'owner_reply_user_id' => null,
        ]);

        return response()->json(['message' => 'Ответ владельца удалён.']);
    }

    private function reviewData(Request $request): array
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:3000'],
        ]);

        $data['body'] = trim((string) ($data['body'] ?? '')) ?: null;

        return $data;
    }

    private function guardProviderReview(Request $request, ServiceObject $poi): void
    {
        abort_if(
            $poi->provider_id && $poi->provider_id === $request->user()->id,
            422,
            'Владелец объекта не может оставлять отзыв на собственный объект.',
        );
    }

    private function guardPoiOwner(Request $request, ServiceObject $poi): void
    {
        $user = $request->user();
        abort_unless(
            $user->role === 'admin' || $poi->provider_id === $user->id,
            403,
            'Отвечать на отзывы может только владелец объекта.',
        );
    }

    private function guardReviewBelongsToPoi(ServiceObject $poi, PoiReview $review): void
    {
        abort_unless($review->service_object_id === $poi->id, 404);
    }

    private function syncRating(ServiceObject $poi): void
    {
        $average = $poi->reviews()->avg('rating');
        $poi->update(['rating' => $average !== null ? round((float) $average, 2) : 0]);
    }
}
