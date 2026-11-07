<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Location;
use App\Models\Rack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class RackController extends Controller
{
    public function index()
    {
        $hasRacksTable = Schema::hasTable('racks');
        $hasRackCol = Schema::hasColumn('items', 'rack');
        $hasShelfCol = Schema::hasColumn('items', 'shelf');

        $query = Location::orderBy('id');
        if ($hasRacksTable) {
            $query->with(['racks' => fn ($q) => $q->orderBy('name')]);
        }
        $locations = $query->withCount('items')->get();

        $counts = collect();
        if ($hasRackCol) {
            $counts = Item::whereNotNull('rack')->where('rack', '!=', '')
                ->selectRaw('location_id, rack, count(*) as total')->groupBy('location_id', 'rack')->get()
                ->mapWithKeys(fn ($r) => [$r->location_id . '|' . $r->rack => $r->total]);
        }

        $shelves = collect();
        if ($hasRackCol && $hasShelfCol) {
            $shelves = Item::whereNotNull('rack')->where('rack', '!=', '')
                ->whereNotNull('shelf')->where('shelf', '!=', '')
                ->selectRaw('location_id, rack, shelf, count(*) as count')
                ->groupBy('location_id', 'rack', 'shelf')
                ->get()
                ->groupBy(fn ($r) => $r->location_id . '|' . $r->rack);
        }

        return view('racks.index', compact('locations', 'counts', 'shelves'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'location_id' => 'required|exists:locations,id',
            'name' => ['required', 'string', 'max:255', Rule::unique('racks', 'name')->where('location_id', $request->location_id)],
        ], [
            'name.unique' => 'This rack already exists in the selected warehouse.',
        ], ['location_id' => 'Warehouse', 'name' => 'Rack name']);

        Rack::create(['location_id' => $data['location_id'], 'name' => trim($data['name'])]);

        return back()->with('success', 'Rack added successfully.');
    }

    public function destroy(Rack $rack)
    {
        if (Item::where('location_id', $rack->location_id)->where('rack', $rack->name)->exists()) {
            return back()->with('error', 'This rack is currently in use by items and cannot be deleted.');
        }

        $rack->delete();

        return back()->with('success', 'Rack deleted successfully.');
    }
}
