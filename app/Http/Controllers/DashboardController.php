<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use App\Models\DataPsAgustusKujangSql;
use App\Models\SalesCodes;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function dashboard(): JsonResponse
    {
        // Fetching counts
        $salesData = $this->getSalesData();

        // Fetching recent records
        $recentData = $this->getRecentData();

        // Return a JSON response
        return response()->json(array_merge($salesData, $recentData), 200);
    }

    private function getSalesData(): array
    {
        return [
            'totalSalesCodes' => SalesCodes::count(),
            'totalOrders' => DataPsAgustusKujangSql::count(),
            'completedOrders' => DataPsAgustusKujangSql::where('STATUS_MESSAGE', 'completed')->count(),
            'pendingOrders' => DataPsAgustusKujangSql::where('STATUS_MESSAGE', 'pending')->count(),
        ];
    }

    private function getRecentData(): array
    {
        return [
            'recentSalesCodes' => SalesCodes::latest()->take(5)->get(),
            'recentOrders' => DataPsAgustusKujangSql::orderBy('ORDER_ID', 'desc')->take(5)->get(),
        ];
    }
}