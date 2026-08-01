<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        return ReviewResource::collection(
            Review::where('user_id', $request->user()->id)->latest()->get()
        );
    }

    public function store(StoreReviewRequest $request)
    {
        $this->authorize('create', Review::class);

        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        return response()->json(new ReviewResource(Review::create($data)), 201);
    }

    public function show($id, Request $request)
    {
        $review = Review::findOrFail($id);

        $this->authorize('view', $review);

        return new ReviewResource($review);
    }

    public function update(UpdateReviewRequest $request, $id)
    {
        $review = Review::findOrFail($id);

        $this->authorize('update', $review);

        $review->update($request->validated());

        return new ReviewResource($review->refresh());
    }

    public function destroy($id, Request $request)
    {
        $review = Review::findOrFail($id);

        $this->authorize('delete', $review);

        $review->delete();

        return response()->json(['message' => 'Avis supprimé avec succès']);
    }
}
