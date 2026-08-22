<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Search\SearchService;
use Illuminate\Contracts\View\View;

class SearchController extends Controller
{
    public function index(): View
    {
        return view('search.index');
    }

    public function show(SearchRequest $request, SearchService $search): View
    {
        $actor = $request->user();

        if ($actor === null) {
            abort(403);
        }

        $question = trim((string) $request->validated('q'));
        $attempt = $search->search($question, $actor);

        return view('search.results', [
            'attempt' => $attempt,
            'question' => $question,
        ]);
    }
}
