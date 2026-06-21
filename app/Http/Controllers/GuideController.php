<?php

namespace App\Http\Controllers;

use App\Support\Guides;
use Illuminate\Contracts\View\View;

/**
 * Bahasa-Melayu content hub (/panduan) — long-tail SEO guides that funnel into
 * the product. Content is file-based (see App\Support\Guides + the content views).
 */
class GuideController extends Controller
{
    public function index(): View
    {
        return view('guides.index', ['guides' => Guides::all()]);
    }

    public function show(string $slug): View
    {
        $guide = Guides::find($slug);

        abort_if($guide === null || ! view()->exists('guides.content.'.$slug), 404);

        return view('guides.show', ['guide' => $guide]);
    }
}
