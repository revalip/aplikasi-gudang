<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Location;
use App\Models\Rack;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('locations', 'name')],
        ], [
            'name.unique' => 'Warehouse location with this name already exists.',
            'name.required' => 'Warehouse location name is required.',
        ]);

        $location = Location::create([
            'name' => trim($data['name']),
        ]);

        ActivityLogger::log('create', "Created warehouse location {$location->name}");

        return back()->with('success', "Warehouse location '{$location->name}' added successfully.");
    }

    public function destroy(Location $location)
    {
        if (Item::where('location_id', $location->id)->exists()) {
            return back()->with('error', "Cannot delete warehouse '{$location->name}' because it contains active stock items.");
        }

        if (Rack::where('location_id', $location->id)->exists()) {
            return back()->with('error', "Cannot delete warehouse '{$location->name}' because it has racks assigned. Delete racks first.");
        }

        $name = $location->name;
        $location->delete();

        ActivityLogger::log('delete', "Deleted warehouse location {$name}");

        return back()->with('success', "Warehouse location '{$name}' deleted successfully.");
    }
}

