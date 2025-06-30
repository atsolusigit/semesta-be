<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MstPage;

class PageController extends Controller
{
    public function index()
    {
        $pages = MstPage::select('id', 'name')->get();
        return response()->json($pages);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:mst_page,name',
        ]);

        $page = MstPage::create([
            'name' => $request->name,
        ]);

        return response()->json(['status' => true, 'data' => $page]);
    }
}
