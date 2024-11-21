<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\TargetGrowth;
use Illuminate\Http\Request;
use App\Imports\DataPsImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\DataResource;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Resources\DataPsResource;
use App\Models\DataPsAgustusKujangSql;

class DataPsController extends Controller
{
    const MONTHS = [
        'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];

    public function index()
    {
        $data = DataPsAgustusKujangSql::select('id', 'ORDER_ID', 'REGIONAL', 'WITEL', 'DATEL', 'STO')
            ->simplePaginate(10);

        return $this->successResponse(DataResource::collection($data));
    }

    public function show($id)
    {
        $item = DataPsAgustusKujangSql::find($id);

        if (!$item) {
            return $this->errorResponse('Data not found', 404);
        }

        return $this->successResponse(new DataPsResource($item));
    }

    public function analysisBySto(Request $request)
    {
        $selectedSto = $request->input('sto', 'all');
        $viewType = $request->input('view_type', 'table');

        $stoList = DataPsAgustusKujangSql::select('STO')->distinct()->orderBy('STO', 'asc')->get();

        $query = $this->buildStoAnalysisQuery($selectedSto);

        $stoAnalysis = $query->get();

        return $this->successResponse([
            'stoAnalysis' => $stoAnalysis,
            'stoList' => $stoList,
            'selectedSto' => $selectedSto,
            'viewType' => $viewType,
        ]);
    }

    private function buildStoAnalysisQuery($selectedSto)
    {
        $query = DataPsAgustusKujangSql::select('STO');

        foreach (self::MONTHS as $month) {
            $query->addSelect(DB::raw("SUM(CASE WHEN Bulan_PS = '{$month}' THEN 1 ELSE 0 END) AS total_{$month}"));
        }

        $query->addSelect(DB::raw('SUM(1) AS grand_total'))
            ->groupBy('STO');

        if ($selectedSto !== 'all') {
            $query->where('STO', $selectedSto);
        }

        return $query;
    }

    public function analysisByMonth(Request $request)
    {
        $monthAnalysis = DataPsAgustusKujangSql::select('Bulan_PS', 'STO', DB::raw('count(*) as total'))
            ->groupBy('Bulan_PS', 'STO')
            ->orderBy('Bulan_PS', 'asc')
            ->orderBy('STO', 'asc')
            ->get();

        return $this->successResponse(['month_analysis' => $monthAnalysis]);
    }

    public function analysisByCode(Request $request)
    {
        $bulanPsList = DataPsAgustusKujangSql::select('Bulan_PS')->distinct()->pluck('Bulan_PS');

        $codeAnalysis = $this->buildCodeAnalysisQuery();

        $organizedData = $this->organizeCodeAnalysisData($codeAnalysis);

        return $this->successResponse([
            'analysis_per_code' => array_values($organizedData),
            'bulan_list' => $bulanPsList,
        ]);
    }

    private function buildCodeAnalysisQuery()
    {
        return DataPsAgustusKujangSql::select(
            'data_ps_agustus_kujang_sql.Bulan_PS',
            'data_ps_agustus_kujang_sql.STO',
            'data_ps_agustus_kujang_sql.Kode_sales',
            'data_ps_agustus_kujang_sql.Nama_SA',
            DB::raw("
                CASE 
                    WHEN data_ps_agustus_kujang_sql.Bulan_PS = ```php
                    'Agustus' THEN sales_codes.kode_agen
                    WHEN data_ps_agustus_kujang_sql.Bulan_PS = 'September' THEN sales_codes.kode_baru
                    ELSE NULL
                END as kode_selected
            "),
            DB::raw("COUNT(DISTINCT data_ps_agustus_kujang_sql.id) as total")
        )
            ->leftJoin('sales_codes', function ($join) {
                $join->on('data_ps_agustus_kujang_sql.STO', '=', 'sales_codes.sto')
                    ->on(function ($query) {
                        $query->where('data_ps_agustus_kujang_sql.Bulan_PS', 'Agustus')
                            ->whereColumn('data_ps_agustus_kujang_sql.Kode_sales', 'sales_codes.kode_agen')
                            ->orWhere('data_ps_agustus_kujang_sql.Bulan_PS', 'September')
                            ->whereColumn('data_ps_agustus_kujang_sql.Kode_sales', 'sales_codes.kode_baru');
                    });
            })
            ->groupBy(
                'data_ps_agustus_kujang_sql.Bulan_PS',
                'data_ps_agustus_kujang_sql.STO',
                'data_ps_agustus_kujang_sql.Kode_sales',
                'data_ps_agustus_kujang_sql.Nama_SA',
                DB::raw('kode_selected')
            )
            ->orderBy('data_ps_agustus_kujang_sql.Bulan_PS', 'asc')
            ->orderBy('data_ps_agustus_kujang_sql.STO', 'asc')
            ->orderBy('kode_selected', 'asc')
            ->get();
    }

    private function organizeCodeAnalysisData($codeAnalysis)
    {
        $organizedData = [];
        foreach ($codeAnalysis as $item) {
            $key = $item->kode_selected ?? $item->Kode_sales;
            if (!isset($organizedData[$key])) {
                $organizedData[$key] = [
                    'kode' => $key,
                    'nama' => $item->Nama_SA,
                    'total' => 0
                ];
            }
            $organizedData[$key]['total'] += $item->total;
        }
        return $organizedData;
    }

    public function analysisByMitra(Request $request)
    {
        try {
            $bulanPsList = DataPsAgustusKujangSql::distinct()->pluck('Bulan_PS');
            $mitraList = DataPsAgustusKujangSql::distinct()->pluck('Mitra');
            $stoList = DataPsAgustusKujangSql::distinct()->pluck('STO')->sort();

            $mitraAnalysis = DataPsAgustusKujangSql::select(
                'data_ps_agustus_kujang_sql.Mitra',
                DB::raw('COUNT(DISTINCT data_ps_agustus_kujang_sql.id) as total')
            )
                ->groupBy('Mitra')
                ->orderBy('Mitra', 'asc')
                ->get();

            return $this->successResponse([
                'bulan_list' => $bulanPsList,
                'sto_list' => $stoList,
                'mitra_list' => $mitraList,
                'mitra_analysis' => $mitraAnalysis,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('An error occurred while retrieving data.', 500, $e->getMessage());
        }
    }

    public function stoChart(Request $request)
    {
        $bulanPs = $request->input('bulan_ps');
        $selectedMitra = $request->input('id_mitra');

        $data = DataPsAgustusKujangSql::select('STO', DB::raw('count(*) as total'))
            ->when($bulanPs, function ($query) use ($bulanPs) {
                return $query->where('Bulan_PS', $bulanPs);
            })
            ->when($selectedMitra, function ($query) use ($selectedMitra) {
                return $query->where('Mitra', $selectedMitra);
            })
            ->groupBy('STO')
            ->get();

        return $this->successResponse([
            'labels' => $data->pluck('STO'),
            'data' => $data->pluck('total'),
        ]);
    }

    public function stoPieChart(Request $request)
    {
        $bulanPs = $request->input('bulan_ps');
        $selectedMitra = $request->input('id_mitra');

        $data = DataPsAgustusKujangSql::select('STO', DB::raw('count(*) as total'))
            ->when($bulanPs, function ($query) use ($bulanPs) {
                return $query->where('Bulan_PS', $bulanPs);
            })
            ->when($selectedMitra, function ($query) use ($selectedMitra) {
                return $query->where('Mitra', $selectedMitra);
            })
            ->groupBy('STO')
            ->get();

        return $this->successResponse([
            'labels' => $data->pluck('STO'),
            'data' => $data->pluck('total'),
        ]);
    }

    public function mitraBarChartAnalysis(Request $request)
    {
        $selectedSto = $request->input('sto');
        $bulanPs = $request->input('bulan_ps');

        $stoList = DataPsAgustusKujangSql::distinct()->pluck('STO')->sort();

        $mitraAnalysis = DataPsAgustusKujangSql::select(
            'Mitra',
            DB::raw("COUNT(DISTINCT id) as total")
        )
            ->when($bulanPs, function ($query) use ($bulanPs) {
                return $query->where('Bulan_PS', $bulanPs);
            })
            ->when($selectedSto, function ($query) use ($selectedSto) {
                return $query->where('STO', $selectedSto);
            })
            ->groupBy('Mitra')
            ->get();

        return $this->successResponse([
            'stoList' => $stoList,
            'labels' => $mitraAnalysis->pluck('Mitra')->toArray(),
            'totals' => $mitraAnalysis->pluck('total')->toArray(),
        ]);
    }

    public function mitraPieChartAnalysis(Request $request)
    {
        $selectedSto = $request->input('sto');
        $bulanPs = $request->input('bulan_ps');

        $stoList = DataPsAgustusKujangSql::distinct()->pluck('STO')->sort();

        $mitraAnalysis = DataPsAgustusKujangSql::select(
            'Mitra',
            DB::raw("COUNT(DISTINCT id) as total")
        )
            ->when($bulanPs, function ($query) use ($bulanPs) {
                return $query->where('Bulan_PS', $bulanPs);
            })
            ->when($selectedSto, function ($query) use ($selectedSto) {
                return $query->where('STO', $selectedSto);
            })
            ->groupBy('Mitra')
            ->get();

        return $this->successResponse([
            'stoList' => $stoList,
            'mitraAnalysis' => $mitraAnalysis,
        ]);
    }

    public function dayAnalysis(Request $request)
    {
        $monthsList = $this->getMonthsList();
        $availableMonths = $this->getAvailableMonths();

        $bulan_ps = $request->input('bulan_ps');
        $query = DataPsAgustusKujangSql::query();

        if ($bulan_ps) {
            $carbonMonth = Carbon::parse($bulan_ps);
            $query->whereYear('TGL_PS', $carbonMonth->year)
                ->whereMonth('TGL_PS', $carbonMonth->month);
        }

        $dayAnalysis = $query->selectRaw('DATE(TGL_PS) as tanggal, COUNT(*) as totalPS')
            ->groupBy('tanggal')
            ->orderBy('tanggal', 'asc')
            ->get();

        return $this->successResponse([
            'dayAnalysis' => $dayAnalysis,
            'bulan_ps' => $bulan_ps,
            'availableMonths' => $availableMonths,
        ]);
    }

    private function getMonthsList()
    {
        return array_map(function ($month) {
            return Carbon::create(null, $month)->format('F Y');
        }, range(1, 12));
    }

    private function getAvailableMonths()
    {
        $allDates = DataPsAgustusKujangSql::selectRaw('DATE_FORMAT(TGL_PS, "%Y-%m") as month')
            ->distinct()
            ->pluck('month');

        return $allDates->map(function ($date) {
            return Carbon::parse($date)->format('F Y');
        })->toArray();
    }

    public function targetTrackingAndSalesChart(Request $request)
    {
        $selectedMonth = $request->input('bulan', now()->month);
        $selectedYear = $request->input('year', now()->year);

        $previousMonth = $selectedMonth == 1 ? 12 : $selectedMonth - 1;
        $previousMonthYear = $selectedMonth == 1 ? $selectedYear - 1 : $selectedYear;

        $currentMonthData = $this->getMonthlyData($selectedMonth, $selectedYear);
        $previousMonthData = $this->getMonthlyData($previousMonth, $selectedYear);

        $dataToDisplayCurrentMonth = $this->processMonthlyData($currentMonthData, $selectedMonth, $selectedYear);
        $dataToDisplayPreviousMonth = $this->processMonthlyData($previousMonthData, $previousMonth, $previousMonthYear);

        return $this->successResponse([
            'dataToDisplayCurrentMonth' => $dataToDisplayCurrentMonth,
            'dataToDisplayPreviousMonth' => $dataToDisplayPreviousMonth,
        ]);
    }

    private function getMonthlyData($month, $year)
    {
        return DataPsAgustusKujangSql::select(
            DB::raw('DATE(TGL_PS) as tgl'),
            DB::raw('DAY(TGL_PS) as day'),
            DB::raw('COUNT(*) as ps_harian')
        )
            ->whereMonth('TGL_PS', $month)
            ->whereYear('TGL_PS', $year)
            ->groupBy(DB::raw('DATE(TGL_PS), DAY(TGL_PS)'))
            ->orderBy('tgl')
            ->get();
    }

    private function processMonthlyData($monthlyData, $month, $year)
    {
        $dataToDisplay = [];
        $cumulativeTotal = 0;

        foreach (range(1, 31) as $day) {
            $dayData = $monthlyData->firstWhere('day', $day);
            $dailyCount = $dayData ? $dayData->ps_harian : 0;
            $cumulativeTotal += $dailyCount;

            $dataToDisplay[] = [
                'tgl' => $dayData ? $dayData->tgl : Carbon::createFromDate($year, $month, $day)->format('Y-m-d'),
                'ps_harian' => $dailyCount,
                'realisasi_mtd' => $cumulativeTotal,
                'gimmick' => $this->calculateGimmick($dailyCount, Carbon::createFromDate($year, $month, $day)->format('l'))
            ];
        }

        return $dataToDisplay;
    }

    private function calculateGimmick($ps_harian, $dayOfWeek)
    {
        $threshold = match ($dayOfWeek) {
            'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' => 7,
            'Saturday' => 6,
            'Sunday' => 5,
            default => 7,
        };

        return $ps_harian >= $threshold ? 'achieve' : 'not achieve';
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'ORDER_ID' => 'required|unique:data_ps_agustus_kujang_sql,ORDER_ID',
            'REGIONAL' => 'required|string|max:255',
            'WITEL' => 'nullable|string|max:100',
            'DATEL' => 'nullable|string|max:100',
            'STO' => 'nullable|string|max:10',
            'UNIT' => 'nullable|string|max:10',
            'JENISPSB' => 'nullable|string|max:50',
            'TYPE_TRANS' => 'nullable|string|max:50',
            'TYPE_LAYANAN' => 'nullable|string|max:50',
            'STATUS_RESUME' => 'nullable|string|max:255',
            'PROVIDER' => 'nullable|string|max:100',
            'ORDER_DATE' => 'nullable|date',
            'LAST_UPDATED_DATE' => 'nullable|date',
            'NCLI' => 'nullable|string|max:50',
            'POTS' => 'nullable|string|max:50',
            'SPEEDY' => 'nullable|string|max:50',
            'CUSTOMER_NAME' => 'nullable|string|max:255',
            'LOC_ID' => 'nullable|string|max:50',
            'WONUM' => 'nullable|string|max:50',
            'FLAG_DEPOSIT' => 'nullable|string|max:10',
            'CONTACT_HP' => 'nullable|string|max:20',
            'INS_ADDRESS' => 'nullable|string',
            'GPS_LONGITUDE' => 'nullable|string|max:50',
            'GPS_LATITUDE' => 'nullable|string|max:50',
            'KCONTACT' => 'nullable|string',
            'CHANNEL' => 'nullable|string|max:100',
            'STATUS_INET' => 'nullable|string|max:50',
            'STATUS_ONU' => 'nullable|string|max:50',
            'UPLOAD' => 'nullable|string|max:50',
            'DOWNLOAD' => 'nullable|string|max:50',
            'LAST_PROGRAM' => 'nullable|string|max:100',
            'STATUS_VOICE' => 'nullable|string|max:50',
            'CLID' => 'nullable|string|max:500',
            'LAST_START' => 'nullable|date',
            'TINDAK_LANJUT' => 'nullable|string',
            'ISI_COMMENT' => 'nullable|string',
            'USER_ID_TL' => 'nullable|string|max:50',
            'TGL_COMMENT' => 'nullable|date',
            'TANGGAL_MANJA' => 'nullable|date',
            'KELOMPOK_KENDALA' => 'nullable|string|max:100',
            'KELOMPOK_STATUS' => 'nullable|string|max:100',
            'HERO' => 'nullable|string|max:50',
            'ADDON' => 'nullable|string|max:50',
            'TGL_PS' => 'nullable|date',
            'STATUS_MESSAGE' => 'nullable|string|max:50',
            'PACKAGE_NAME' => 'nullable|string|max:100',
            'GROUP_PAKET' => 'nullable|string|max:100',
            'REASON_CANCEL' => 'nullable|string',
            'KETERANGAN_CANCEL' => 'nullable|string',
            'TGL_MANJA' => 'nullable|date',
            'DETAIL_MANJA' => 'nullable|string',
            'Bulan_PS' => 'nullable|string|max:50',
            'Kode_sales' => 'nullable|string|max:50',
            'Nama_SA' => 'nullable|string|max:255',
            'Mitra' => 'nullable|string|max:100',
            'Ekosistem' => 'nullable|string|max:100',
            // Include all other fields from your database table
        ]);

        // Mengecek apakah ORDER_ID sudah ada di database
        $existingOrder = DataPsAgustusKujangSql::where('ORDER_ID', $request->ORDER_ID)->first();
        if ($existingOrder) {
            return redirect()->back()->withErrors(['ORDER_ID' => 'ORDER_ID sudah digunakan.']);
        }

        // Simpan data ke database jika validasi lolos
        DataPsAgustusKujangSql::create($validatedData);

        // Redirect ke halaman index atau halaman lain setelah penyimpanan
        return redirect()->route('data-ps.index')->with('success', 'Data berhasil ditambahkan!');
    }

    public function edit($id)
    {
        $item = DataPsAgustusKujangSql::findOrFail($id);
        return view('data-ps.edit', compact('item'));
    }

    public function update(Request $request, $id)
    {
        $item = DataPsAgustusKujangSql::findOrFail($id);

        $validatedData = $request->validate([
            'ORDER_ID' => 'nullable|unique:data_ps_agustus_kujang_sql,ORDER_ID',
            'REGIONAL' => 'required|string|max:255',
            'WITEL' => 'nullable|string|max:100',
            'DATEL' => 'nullable|string|max:100',
            'STO' => 'nullable|string|max:10',
            'UNIT' => 'nullable|string|max:10',
            'JENISPSB' => 'nullable|string|max:50',
            'TYPE_TRANS' => 'nullable|string|max:50',
            'TYPE_LAYANAN' => 'nullable|string|max:50',
            'STATUS_RESUME' => 'nullable|string|max:255',
            'PROVIDER' => 'nullable|string|max:100',
            'ORDER_DATE' => 'nullable|date',
            'LAST_UPDATED_DATE' => 'nullable|date',
            'NCLI' => 'nullable|string|max:50',
            'POTS' => 'nullable|string|max:50',
            'SPEEDY' => 'nullable|string|max:50',
            'CUSTOMER_NAME' => 'nullable|string|max:255',
            'LOC_ID' => 'nullable|string|max:50',
            'WONUM' => 'nullable|string|max:50',
            'FLAG_DEPOSIT' => 'nullable|string|max:10',
            'CONTACT_HP' => 'nullable|string|max:20',
            'INS_ADDRESS' => 'nullable|string',
            'GPS_LONGITUDE' => 'nullable|string|max:50',
            'GPS_LATITUDE' => 'nullable|string|max:50',
            'KCONTACT' => 'nullable|string',
            'CHANNEL' => 'nullable|string|max:100',
            'STATUS_INET' => 'nullable|string|max:50',
            'STATUS_ONU' => 'nullable|string|max:50',
            'UPLOAD' => 'nullable|string|max:50',
            'DOWNLOAD' => 'nullable|string|max:50',
            'LAST_PROGRAM' => 'nullable|string|max:100',
            'STATUS_VOICE' => 'nullable|string|max:50',
            'CLID' => 'nullable|string|max:500',
            'LAST_START' => 'nullable|date',
            'TINDAK_LANJUT' => 'nullable|string',
            'ISI_COMMENT' => 'nullable|string',
            'USER_ID_TL' => 'nullable|string|max:50',
            'TGL_COMMENT' => 'nullable|date',
            'TANGGAL_MANJA' => 'nullable|date',
            'KELOMPOK_KENDALA' => 'nullable|string|max:100',
            'KELOMPOK_STATUS' => 'nullable|string|max:100',
            'HERO' => 'nullable|string|max:50',
            'ADDON' => 'nullable|string|max:50',
            'TGL_PS' => 'nullable|date',
            'STATUS_MESSAGE' => 'nullable|string|max:50',
            'PACKAGE_NAME' => 'nullable|string|max:100',
            'GROUP_PAKET' => 'nullable|string|max:100',
            'REASON_CANCEL' => 'nullable|string',
            'KETERANGAN_CANCEL' => 'nullable|string',
            'TGL_MANJA' => 'nullable|date',
            'DETAIL_MANJA' => 'nullable|string',
            'Bulan_PS' => 'nullable|string|max:50',
            'Kode_sales' => 'nullable|string|max:50',
            'Nama_SA' => 'nullable|string|max:255',
            'Mitra' => 'nullable|string|max:100',
            'Ekosistem' => 'nullable|string|max:100',
            // Include all other fields from your database table
        ]);
        $item->update($validatedData);
        return redirect()->route('data-ps.index')->with('success', 'Data PS updated successfully.');
    }

    public function destroy($id)
    {
        $item = DataPsAgustusKujangSql::findOrFail($id);
        $item->delete();
        return redirect()->route('data-ps.index')->with('success', 'Data PS deleted successfully.');
    }

    public function importExcel(Request $request)
    {
        Log::info('Starting file upload process...');

        if (!$request->hasFile('file')) {
            return $this->errorResponse('No file uploaded.', 422);
        }

        $file = $request->file('file');

        try {
            DB::beginTransaction();

            $import = new DataPsImport;
            Excel::import($import, $file);

            DB::commit();
            return $this->successResponse(['message' => "Data imported successfully! Rows imported: {$import->getRowCount()}"], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Error importing data: ' . $e->getMessage(), 500);
        }
    }

    private function successResponse($data, $status = 200)
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    private function errorResponse($message, $status = 400, $errors = null)
    {
        return response()->json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
    }
}
