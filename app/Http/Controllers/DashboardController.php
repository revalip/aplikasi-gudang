<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Location;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    public function index(Request $request)
    {
        $allLocations = Location::orderBy('id')->get();
        // Header filter: limit count cards & recent items to 1 location (empty = all locations).
        $filterLocation = $allLocations->firstWhere('id', (int) $request->query('location'));

        $scoped = fn () => Item::query()->when($filterLocation, fn ($q) => $q->where('location_id', $filterLocation->id));

        $totalItems = Item::count();
        $perStatus = Item::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $scopedStatus = $scoped()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $locations = Location::orderBy('id')->get();
        $byLocation = Item::selectRaw('location_id, status, count(*) as total')
            ->groupBy('location_id', 'status')
            ->get()
            ->groupBy('location_id');

        $locationSummary = $locations->map(function (Location $loc) use ($byLocation) {
            $counts = $byLocation->get($loc->id, collect())->pluck('total', 'status');
            $inStock = ($counts['Available'] ?? 0) + ($counts['In Stock'] ?? 0);
            $used = ($counts['Rented'] ?? 0) + ($counts['Borrow'] ?? 0) + ($counts['Under Service'] ?? 0) + ($counts['Instalation'] ?? 0);
            $broken = ($counts['Damaged'] ?? 0) + ($counts['Broken'] ?? 0);
            $total = $counts->sum();

            return [
                'id' => $loc->id,
                'name' => $loc->name,
                'total' => $total,
                'in_stock' => $inStock,
                'used' => $used,
                'broken' => $broken,
                'other' => $total - $inStock - $used - $broken,
            ];
        });

        $damaged = $locationSummary->firstWhere('name', 'Rak Barang Rusak');

        // Card link: without location filter -> Part/Unit Search; with filter -> Warehouse for that location.
        $itemLink = fn (?string $status) => $filterLocation
            ? route('items.index', array_filter(['location' => $filterLocation->id, 'status' => $status]))
            : ($status ? route('items.part', ['status' => $status]) : route('items.index'));

        $availableCount = ($scopedStatus['Available'] ?? 0) + ($scopedStatus['In Stock'] ?? 0);
        $damagedCount = ($scopedStatus['Damaged'] ?? 0) + ($scopedStatus['Broken'] ?? 0);
        $serviceCount = ($scopedStatus['Under Service'] ?? 0) + ($scopedStatus['Instalation'] ?? 0) + ($scopedStatus['Has not been tested'] ?? 0);

        $stats = [
            ['label' => $filterLocation ? "Total at {$filterLocation->name}" : 'Total Items', 'value' => $scoped()->count(), 'icon' => 'package', 'bg' => '#E6F0F7', 'fg' => '#0066A0', 'url' => $itemLink(null)],
            ['label' => 'Available', 'value' => $availableCount, 'icon' => 'circle-check', 'bg' => '#E7F6EC', 'fg' => '#1E7A3C', 'url' => $itemLink('Available')],
            ['label' => 'Under Service', 'value' => $serviceCount, 'icon' => 'wrench', 'bg' => '#FFF4DB', 'fg' => '#8A5A00', 'url' => $itemLink('Under Service')],
            $filterLocation
                ? ['label' => 'Damaged', 'value' => $damagedCount, 'icon' => 'triangle-alert', 'bg' => '#FCE8EF', 'fg' => '#D4145A', 'url' => $itemLink('Damaged')]
                : ['label' => 'In Damaged Rack', 'value' => $damaged['total'] ?? 0, 'icon' => 'triangle-alert', 'bg' => '#FCE8EF', 'fg' => '#D4145A', 'url' => ($rack = $allLocations->firstWhere('name', 'Rak Barang Rusak')) ? route('items.index', ['location' => $rack->id]) : route('items.index')],
        ];

        $monthly = $this->monthlyFlow();

        $recentItems = $scoped()->with(['type', 'location'])->latest()->take(5)->get();

        return view('dashboard', [
            'today' => now()->locale('en')->isoFormat('dddd, D MMMM YYYY'),
            'stats' => $stats,
            'donuts' => $locationSummary->reject(fn ($l) => $l['name'] === 'Rak Barang Rusak')->values(),
            'share' => $locationSummary,
            'totalItems' => $totalItems,
            'monthly' => $monthly,
            'recentItems' => $recentItems,
            'allLocations' => $allLocations,
            'filterLocation' => $filterLocation,
            'locationIds' => $allLocations->pluck('id', 'name'),
            'actions' => $this->pendingActions($request),
        ]);
    }

    /**
     * "Action Required" cards: count of pending documents, click = redirect to pre-filtered list.
     */
    private function pendingActions(Request $request): array
    {
        $user = $request->user();

        $cards = [
            ['can' => 'purchasing.approve', 'label' => 'PO pending approval', 'hint' => 'Requires approval / rejection', 'icon' => 'file-clock', 'tone' => 'amber',
                'count' => PurchaseOrder::where('status', 'draft')->count(), 'url' => route('purchasing.index', ['status' => 'draft'])],
            ['can' => 'purchasing', 'label' => 'PO pending goods receipt', 'hint' => 'Ordered / partially received', 'icon' => 'package-open', 'tone' => 'brand',
                'count' => PurchaseOrder::whereIn('status', ['ordered', 'partial'])->count(), 'url' => route('purchasing.index', ['status' => 'ordered,partial'])],
        ];

        return array_values(array_filter($cards, fn ($c) => $user->can($c['can'])));
    }

    /** Barang masuk, keluar, dan total stok di akhir bulan untuk 6 bulan terakhir. */
    private function monthlyFlow(): array
    {
        $inDate = DB::raw('COALESCE(stuff_in, DATE(created_at))');
        $result = ['labels' => [], 'in' => [], 'out' => [], 'stock' => []];

        for ($i = 5; $i >= 0; $i--) {
            $start = now()->startOfMonth()->subMonths($i);
            $end = $start->copy()->endOfMonth();

            $result['labels'][] = self::MONTHS[$start->month - 1];
            $result['in'][] = Item::whereBetween($inDate, [$start->toDateString(), $end->toDateString()])->count();
            $result['out'][] = Item::whereBetween('stuff_out', [$start->toDateString(), $end->toDateString()])->count();
            $result['stock'][] = Item::where($inDate, '<=', $end->toDateString())
                ->where(fn ($q) => $q->whereNull('stuff_out')->orWhere('stuff_out', '>', $end->toDateString()))
                ->count();
        }

        return $result;
    }
}
