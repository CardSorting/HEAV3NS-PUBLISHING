<?php

namespace App\Http\Controllers;

use App\Model\PublicPage;
use Illuminate\Http\Request;

class PublicPagesController extends Controller
{
    /**
     * Renders public ( admin-created ) pages.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function getPage(Request $request)
    {
        $slug = $request->route('slug');
        $page = PublicPage::where('slug', $slug)->first();

        if (!$page) {
            abort(404);
        }
        if ($page->isExternal()) {
            return redirect()->away($page->getUrl());
        }

        return view('pages.public-page', ['page'=>$page]);
    }
}
