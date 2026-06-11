<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\NewsArticleResource;
use App\Models\DictionaryItem;
use App\Models\NewsArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class NewsController extends Controller
{
    /**
     * GET /api/v1/news — public list of published articles.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $articles = NewsArticle::query()
            ->where('status', '!=', 'archived')
            ->with('author:id,name')
            ->orderByDesc('created_at')
            ->paginate((int) $request->input('per_page', 20));

        return NewsArticleResource::collection($articles);
    }

    /**
     * GET /api/v1/news/{slug} — public single article.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $query = NewsArticle::query()->with('author:id,name');

        if ($request->user()?->role !== 'admin') {
            $query->where('status', '!=', 'archived');
        }

        $article = $query
            ->where(function ($q) use ($slug, $request) {
                $q->where('slug', $slug);

                if ($request->user()?->role === 'admin' && ctype_digit($slug)) {
                    $q->orWhere('id', (int) $slug);
                }
            })
            ->firstOrFail();

        return response()->json(['data' => new NewsArticleResource($article)]);
    }

    /**
     * POST /api/v1/news — admin creates article.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'        => ['required', 'string', 'max:255'],
            'excerpt'      => ['nullable', 'string'],
            'content'      => ['nullable', 'string'],
            'image'        => ['nullable', 'string'],
            'gallery'      => ['nullable', 'array'],
            'gallery.*'    => ['string'],
            'tags'         => ['nullable', 'array'],
            'tags.*'       => ['string'],
            'status'       => ['in:draft,published,archived'],
            'published_at' => ['nullable', 'date'],
        ]);

        if (($data['status'] ?? 'draft') === 'published' && empty($data['tags'])) {
            return response()->json([
                'message' => 'Для публикации статьи добавьте хотя бы один тег.',
            ], 422);
        }

        $data['author_id']   = $request->user()->id;
        $data['slug']        = Str::slug($data['title']) . '-' . Str::random(5);
        $data['published_at'] = $data['published_at'] ?? ($data['status'] === 'published' ? now() : null);

        $article = NewsArticle::create($data);
        $this->syncTags($article->tags ?? []);

        return (new NewsArticleResource($article->load('author')))->response()->setStatusCode(201);
    }

    /**
     * PUT /api/v1/news/{id} — admin updates article.
     */
    public function update(Request $request, NewsArticle $news): JsonResponse
    {
        $data = $request->validate([
            'title'        => ['sometimes', 'string', 'max:255'],
            'excerpt'      => ['nullable', 'string'],
            'content'      => ['nullable', 'string'],
            'image'        => ['nullable', 'string'],
            'gallery'      => ['nullable', 'array'],
            'gallery.*'    => ['string'],
            'tags'         => ['nullable', 'array'],
            'tags.*'       => ['string'],
            'status'       => ['in:draft,published,archived'],
            'published_at' => ['nullable', 'date'],
        ]);

        $nextStatus = $data['status'] ?? $news->status;
        $nextTags = array_key_exists('tags', $data) ? $data['tags'] : ($news->tags ?? []);

        if ($nextStatus === 'published' && empty($nextTags)) {
            return response()->json([
                'message' => 'Для публикации статьи добавьте хотя бы один тег.',
            ], 422);
        }

        if (isset($data['title'])) {
            $data['slug'] = Str::slug($data['title']) . '-' . Str::random(5);
        }
        if (isset($data['status']) && $data['status'] === 'published' && !$news->published_at) {
            $data['published_at'] = now();
        }

        $news->update($data);
        $this->syncTags($news->tags ?? []);

        return response()->json(['data' => new NewsArticleResource($news->fresh('author'))]);
    }

    /**
     * DELETE /api/v1/news/{id} — admin deletes article.
     */
    public function destroy(NewsArticle $news): JsonResponse
    {
        $news->delete();
        return response()->json(['message' => 'Статья удалена.']);
    }

    private function syncTags(array $tags): void
    {
        $nextOrder = (int) DictionaryItem::query()
            ->where('dictionary', 'tags')
            ->max('sort_order');

        foreach ($tags as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '') {
                continue;
            }

            $nextOrder += 10;
            DictionaryItem::firstOrCreate(
                ['dictionary' => 'tags', 'value' => $tag],
                [
                    'label' => $tag,
                    'description' => 'Тег редакционных материалов',
                    'sort_order' => $nextOrder,
                    'is_active' => true,
                ],
            );
        }
    }
}
