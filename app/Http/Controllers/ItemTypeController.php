<?php

namespace App\Http\Controllers;

use App\Models\ItemType;
use Illuminate\Http\Request;

class ItemTypeController extends Controller
{
    public function index()
    {
        $types = ItemType::withCount('items')->orderBy('name')->get();
        return view('item-types.index', compact('types'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:item_types,name',
        ]);

        ItemType::create($request->only('name'));

        return back()->with('success', 'New type added successfully.');
    }

    public function destroy(ItemType $itemType)
    {
        if ($itemType->items()->exists()) {
            return back()->with('error', 'This type is currently in use by items and cannot be deleted.');
        }

        $itemType->delete();

        return back()->with('success', 'Type deleted successfully.');
    }
}
