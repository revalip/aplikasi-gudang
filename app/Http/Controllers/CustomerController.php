<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::withCount(['workOrders', 'deliveryOrders'])->orderBy('name')->get();

        return view('customers.index', compact('customers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:customers,name',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        Customer::create($validated);

        return back()->with('success', 'Customer added successfully.');
    }

    public function destroy(Customer $customer)
    {
        if ($customer->workOrders()->exists() || $customer->salesOrders()->exists()) {
            return back()->with('error', 'This customer has associated Work Order / delivery history and cannot be deleted.');
        }

        $customer->delete();

        return back()->with('success', 'Customer deleted successfully.');
    }
}
