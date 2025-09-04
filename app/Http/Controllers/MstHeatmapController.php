<?php

namespace App\Http\Controllers;

use App\Models\MstHeatmap;
use App\Models\MstHeatmapDampak;
use App\Models\MstHeatmapKemungkinan;
use App\Models\MstHeatmapRiskRange;
use App\Models\TrRiskHeader;
use App\Models\TrRiskMonthly;
use App\Models\MstRiskCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class MstHeatmapController extends Controller
{
    public function index()
{
    $data = MstHeatmap::orderBy('dampak', 'asc')
        ->orderBy('kemungkinan', 'asc')
        ->get()
        ->map(function ($item) {
            return [
                'id' => $item->id,
                'dampak' => $item->dampak,
                'kemungkinan' => $item->kemungkinan,
                'result' => $item->result,
                'name' => $item->risk_range?->name ?? null,
                'color' => $item->risk_range?->color ?? null,
            ];
        });

    return json(200, true, 'Berhasil', 'List data heatmap', $data);
}

    public function show($id)
    {
        $item = MstHeatmap::with('riskRange')->find($id);

        if (!$item) {
            return json(404, false, 'Tidak Ditemukan', 'Data heatmap tidak ditemukan',null);
        }

        $data = [
            'dampak' => $item->dampak,
            'kemungkinan' => $item->kemungkinan,
            'result' => $item->result,
            'name' => $item->riskRange->name ?? null,
            'color' => $item->riskRange->color ?? null,
        ];

        return json(200, true, 'Berhasil', 'Detail data heatmap', $data);
    }

     public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'dampak' => 'required|exists:mst_heatmap_dampak,id',
            'kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
            'result' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Terdapat kesalahan input', $validator->errors());
        }

        $exist = MstHeatmap::where('dampak', $request->dampak)
            ->where('kemungkinan', $request->kemungkinan)
            ->first();

        if ($exist) {
            return json(409, false, 'Gagal', 'Data sudah ada dengan kombinasi dampak & kemungkinan ini', null);
        }

        $data = MstHeatmap::create($request->only(['dampak', 'kemungkinan', 'result', 'name']));

        return json(200, true, 'Berhasil', 'Data heatmap berhasil ditambahkan', $data);
    }

    public function update(Request $request, $id)
    {
        $heatmap = MstHeatmap::find($id);
        if (!$heatmap) {
            return json(404, false, 'Tidak Ditemukan', 'Data heatmap tidak ditemukan',null);
        }

        $validator = Validator::make($request->all(), [
            'dampak' => 'required|exists:mst_heatmap_dampak,id',
            'kemungkinan' => 'required|exists:mst_heatmap_kemungkinan,id',
            'result' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Terdapat kesalahan input', $validator->errors());
        }

        $heatmap->update($request->only(['dampak', 'kemungkinan', 'result', 'name']));

        return json(200, true, 'Berhasil', 'Data heatmap berhasil diperbarui', $heatmap);
    }

    public function destroy($id)
    {
        $data = MstHeatmap::find($id);

        if (!$data) {
            return json(404, false, 'Tidak Ditemukan', 'Data heatmap tidak ditemukan',null);
        }

        $data->delete();

        return json(200, true, 'Berhasil', 'Data heatmap berhasil dihapus',null);
    }

  public function getHeatmapData(Request $request)
{
    // Validasi input - semua parameter menjadi optional, tambah department_name
    $request->validate([
        'department_id' => 'nullable|integer',
        'department_name' => 'nullable|string', // NEW: Filter berdasarkan nama departemen
        'year' => 'nullable|integer',
        'risk_code' => 'array',
        'kode_risiko' => 'array',
        'month' => 'nullable|integer|min:1|max:12',
        'type' => 'nullable|string|in:inherent,residual,residual_current,residual_target,all'
    ]);

    // Get parameter values
    $departmentId = $request->department_id;
    $departmentName = $request->department_name; // NEW: Get department name filter
    $year = $request->year;
    $riskCodes = $request->risk_code ?? $request->kode_risiko ?? [];
    $month = $request->month;
    $type = $request->type ?? 'all'; // default tampilkan semua

    // NEW: Role-based access control - Role 2 dan 3 hanya bisa melihat data departemen mereka
    $user = auth()->user();
    $userRole = $user->role->id ?? $user->role_id ?? 1; // Ambil ID dari relasi role atau langsung role_id
    $userDepartmentId = $user->department_id ?? null;

    // Jika role 2 atau 3, override filter departement dengan departement user
    if (in_array($userRole, [2, 3]) && $userDepartmentId) {
        $departmentId = $userDepartmentId; // Force departement filter
        $departmentName = null; // Clear department name filter karena sudah di-override
    }

    // Pastikan nama model dan relasi benar
    $query = TrRiskHeader::with([
        'monthlyData' => function($q) use ($month) {
            if ($month) {
                $q->where('month', $month);
            }
            $q->orderBy('month', 'desc');
        },
        'department' // Include relasi department
    ]);

    // Filter berdasarkan department_id jika ada
    if ($departmentId) {
        $query->where('department_id', $departmentId);
    }

    // NEW: Filter berdasarkan department_name jika ada
    if ($departmentName) {
        $query->whereHas('department', function($q) use ($departmentName) {
            $q->where('name', 'like', '%' . $departmentName . '%');
        });
    }

    // Filter berdasarkan year jika ada
    if ($year) {
        $query->where('year', $year);
    }

    // Filter berdasarkan risk_code jika ada
    if (!empty($riskCodes)) {
        $query->where(function($q) use ($riskCodes) {
            foreach ($riskCodes as $riskCode) {
                $q->orWhereRaw('FIND_IN_SET(?, risk_code)', [$riskCode]);
            }
        });
    }

    $riskHeaders = $query->get();

    // Get department name untuk response - prioritaskan dari filter
    $responseDepartmentName = null;
    if ($departmentName) {
        $responseDepartmentName = $departmentName;
    } elseif ($departmentId && $riskHeaders->count() > 0) {
        $firstRiskHeader = $riskHeaders->first();
        $responseDepartmentName = $firstRiskHeader->department ? $firstRiskHeader->department->name : null;
    }

    // Inisialisasi matrix 5x5 untuk setiap jenis risiko menggunakan helper
    $inherentMatrix = initialize_risk_matrix();
    $residualCurrentMatrix = initialize_risk_matrix();
    $residualTargetMatrix = initialize_risk_matrix();

    // Summary berdasarkan kategori menggunakan helper
    $inherentSummary = initialize_risk_summary();
    $residualCurrentSummary = initialize_risk_summary();
    $residualTargetSummary = initialize_risk_summary();

    // Array untuk menyimpan detail data yang diproses
    $processedData = [
        'inherent_processed' => 0,
        'residual_current_processed' => 0,
        'residual_target_processed' => 0,
        'monthly_data_found' => 0,
        'headers_with_monthly' => 0
    ];

    // Array untuk menyimpan data tabel berdasarkan type yang dipilih
    $tableData = [];
    $tableSummary = initialize_risk_summary();

    foreach ($riskHeaders as $header) {
        // === INHERENT RISK === (dari tr_risk_header)
        if (in_array($type, ['inherent', 'all'])) {
            $inherentImpact = $header->inherent_risk_level_dampak ?? 0;
            $inherentLikelihood = $header->inherent_risk_level_kemungkinan ?? 0;

            if ($inherentImpact > 0 && $inherentLikelihood > 0 && $inherentImpact <= 5 && $inherentLikelihood <= 5) {
                $inherentMatrix[$inherentLikelihood][$inherentImpact]++;

                $inherentScore = $inherentImpact * $inherentLikelihood;
                $inherentCategory = get_risk_category_by_score($inherentScore);
                $inherentSummary[$inherentCategory]++;
                $processedData['inherent_processed']++;

                // Tambahkan ke table summary jika type sesuai
                if (in_array($type, ['inherent', 'all'])) {
                    $tableSummary[$inherentCategory]++;
                }
            }
        }

        // === RESIDUAL RISK === (dari tr_risk_monthly)
        if (in_array($type, ['residual', 'residual_current', 'all'])) {
            if ($header->monthlyData->count() > 0) {
                $processedData['headers_with_monthly']++;

                if ($month) {
                    // Jika ada filter month, ambil data bulan tertentu saja
                    $monthlyDataCollection = $header->monthlyData->where('month', $month);
                } else {
                    // Jika tidak ada filter month, ambil SEMUA data monthly
                    $monthlyDataCollection = $header->monthlyData;
                }

                // Loop semua data monthly yang sesuai filter
                foreach ($monthlyDataCollection as $monthlyData) {
                    $processedData['monthly_data_found']++;

                    // Residual Current (dari tr_risk_monthly)
                    $rcImpact = $monthlyData->residual_risk_level_dampak ?? 0;
                    $rcLikelihood = $monthlyData->residual_risk_level_kemungkinan ?? 0;

                    if ($rcImpact > 0 && $rcLikelihood > 0 && $rcImpact <= 5 && $rcLikelihood <= 5) {
                        $residualCurrentMatrix[$rcLikelihood][$rcImpact]++;

                        $rcScore = $rcImpact * $rcLikelihood;
                        $rcCategory = get_risk_category_by_score($rcScore);
                        $residualCurrentSummary[$rcCategory]++;
                        $processedData['residual_current_processed']++;

                        // Tambahkan ke table summary jika type sesuai
                        if (in_array($type, ['residual', 'residual_current', 'all'])) {
                            $tableSummary[$rcCategory]++;
                        }
                    }
                }
            }
        }

        // === RESIDUAL TARGET === (dari tr_risk_header)
        if (in_array($type, ['residual', 'residual_target', 'all'])) {
            $rtImpact = $header->residual_target_level_dampak ?? 0;
            $rtLikelihood = $header->residual_target_level_kemungkinan ?? 0;

            if ($rtImpact > 0 && $rtLikelihood > 0 && $rtImpact <= 5 && $rtLikelihood <= 5) {
                $residualTargetMatrix[$rtLikelihood][$rtImpact]++;

                $rtScore = $rtImpact * $rtLikelihood;
                $rtCategory = get_risk_category_by_score($rtScore);
                $residualTargetSummary[$rtCategory]++;
                $processedData['residual_target_processed']++;

                // Tambahkan ke table summary jika type sesuai
                if (in_array($type, ['residual', 'residual_target', 'all'])) {
                    $tableSummary[$rtCategory]++;
                }
            }
        }
    }

    // Convert table summary ke format array yang sesuai untuk frontend
    // Pastikan semua kategori muncul, bahkan yang kosong
    $riskCategories = [
        'Low' => '#00FF00',
        'Low to Moderate' => '#90EE90',
        'Moderate' => '#FFFF00',
        'Moderate to High' => '#FFA500',
        'High' => '#FF0000'
    ];

    foreach ($riskCategories as $category => $color) {
        $tableData[] = [
            'category' => $category,
            'color' => $color,
            'count' => $tableSummary[$category] ?? 0
        ];
    }

    return response()->json([
        'status' => true,
        'message' => 'Heatmap data retrieved successfully',
        'filters' => [
            'department_id' => $departmentId,
            'department_name' => $responseDepartmentName, // IMPROVED: Include filtered department name
            'year' => $year,
            'month' => $month,
            'risk_codes' => $riskCodes,
            'kode_risiko' => $riskCodes, // alias untuk risk_codes
            'type' => $type,
            'total_risks' => $riskHeaders->count(),
            'user_role' => $userRole, // NEW: Include user role untuk debugging
            'access_restricted' => in_array($userRole, [2, 3]) // NEW: Indicate if access is restricted
        ],
        'processing_info' => $processedData,
        'heatmap' => [
            'inherent' => [
                'grid' => $inherentMatrix,
                'summary' => $inherentSummary,
                'total' => array_sum($inherentSummary)
            ],
            'residual_current' => [
                'grid' => $residualCurrentMatrix,
                'summary' => $residualCurrentSummary,
                'total' => array_sum($residualCurrentSummary)
            ],
            'residual_target' => [
                'grid' => $residualTargetMatrix,
                'summary' => $residualTargetSummary,
                'total' => array_sum($residualTargetSummary)
            ]
        ],
        'table_data' => $tableData, // Data untuk tabel
        'legend' => [
            'probabilitas_labels' => [
                '1' => 'Sangat Jarang Terjadi',
                '2' => 'Jarang Terjadi',
                '3' => 'Bisa Terjadi',
                '4' => 'Sangat Mungkin Terjadi',
                '5' => 'Hampir Pasti Terjadi'
            ],
            'dampak_labels' => [
                '1' => 'Sangat Rendah',
                '2' => 'Rendah',
                '3' => 'Menengah',
                '4' => 'Tinggi',
                '5' => 'Sangat Tinggi'
            ],
            'risk_categories' => [
                ['name' => 'Low', 'min_score' => 1, 'max_score' => 5, 'color' => '#00FF00'],
                ['name' => 'Low to Moderate', 'min_score' => 6, 'max_score' => 10, 'color' => '#90EE90'],
                ['name' => 'Moderate', 'min_score' => 11, 'max_score' => 15, 'color' => '#FFFF00'],
                ['name' => 'Moderate to High', 'min_score' => 16, 'max_score' => 20, 'color' => '#FFA500'],
                ['name' => 'High', 'min_score' => 21, 'max_score' => 25, 'color' => '#FF0000']
            ]
        ]
    ]);
}

public function getHeatmapDetailData(Request $request)
{
    // Validasi input - untuk GET request, parameter dari query string
    $request->validate([
        'department_id' => 'nullable|integer',
        'department_name' => 'nullable|string',
        'year' => 'nullable|integer',
        'risk_code' => 'array',
        'kode_risiko' => 'array',
        'month' => 'nullable|integer|min:1|max:12',
        'type' => 'required|string|in:inherent,residual,residual_current,residual_target',
        'kemungkinan' => 'required|integer|min:1|max:5',
        'dampak' => 'required|integer|min:1|max:5',
        'per_page' => 'nullable|integer|min:1|max:100'
    ]);

    // Get parameter values
    $departmentId = $request->department_id;
    $departmentName = $request->department_name;
    $year = $request->year;
    $riskCodes = $request->risk_code ?? $request->kode_risiko ?? [];
    $month = $request->month;
    $type = $request->type;
    $kemungkinan = $request->kemungkinan;
    $dampak = $request->dampak;
    $perPage = $request->input('per_page', 10);

    // Get user untuk role access control
    $user = auth()->user();
    $userRole = $user->role->id ?? $user->role_id ?? 1;
    $userDepartmentId = $user->department_id ?? null;

    // Jika role 2 atau 3, override filter departement dengan departement user
    if (in_array($userRole, [2, 3]) && $userDepartmentId) {
        $departmentId = $userDepartmentId;
        $departmentName = null;
    }

    // Base query dengan role access control seperti di index function
    $query = TrRiskHeader::with([
        'irDampak:id,label',
        'irKemungkinan:id,label',
        'rrDampak:id,label',
        'rrKemungkinan:id,label',
        'department:id,name',
        'optionTargetSatuTahun:id,name,position',
        'uploads',
        'monthlyData' => function ($query) use ($month) {
            $query->orderBy('month', 'asc')->with('uploads');
            // Jika ada filter month untuk residual_current, aplikasikan di sini
            if ($month) {
                $query->where('month', $month);
            }
        },
        'headerEntry.monthlyEntryData.uploads',
        'headerEntry.irDampak:id,label',
        'headerEntry.irKemungkinan:id,label',
        'headerEntry.rrDampak:id,label',
        'headerEntry.rrKemungkinan:id,label',
        'headerEntry.department:id,name',
        'headerEntry.optionTargetSatuTahun:id,name,position',
        'createdBy:id,username,id',
        'updatedBy:id,username,id',
    ]);

    // Filter berdasarkan department_id jika ada
    if ($departmentId) {
        $query->where('department_id', $departmentId);
    }

    // Filter berdasarkan department_name jika ada
    if ($departmentName) {
        $query->whereHas('department', function($q) use ($departmentName) {
            $q->where('name', 'like', '%' . $departmentName . '%');
        });
    }

    // Filter berdasarkan year jika ada
    if ($year) {
        $query->where('year', $year);
    }

    // Filter berdasarkan risk_code jika ada
    if (!empty($riskCodes)) {
        $query->where(function($q) use ($riskCodes) {
            foreach ($riskCodes as $riskCode) {
                $q->orWhereRaw('FIND_IN_SET(?, risk_code)', [$riskCode]);
            }
        });
    }

    // Filter berdasarkan posisi heatmap yang diklik - menggunakan dampak dan kemungkinan
    switch ($type) {
        case 'inherent':
            $query->where('inherent_risk_level_dampak', $dampak)
                  ->where('inherent_risk_level_kemungkinan', $kemungkinan);
            break;

        case 'residual':
        case 'residual_current':
            // Filter header yang memiliki monthly data dengan nilai yang sesuai
            // PERBAIKAN: Sesuaikan dengan logika di getHeatmapData
            $query->whereHas('monthlyData', function($q) use ($dampak, $kemungkinan, $month) {
                $q->where('residual_risk_level_dampak', $dampak)
                  ->where('residual_risk_level_kemungkinan', $kemungkinan);
                // Aplikasikan filter month jika ada
                if ($month) {
                    $q->where('month', $month);
                }
            });
            break;

        case 'residual_target':
            $query->where('residual_target_level_dampak', $dampak)
                  ->where('residual_target_level_kemungkinan', $kemungkinan);
            break;
    }

    $query->orderBy('id', 'desc');

    // Get paginated data
    $data = $query->paginate($perPage);

    // DEBUG: Tambahkan informasi untuk debugging
    $debugInfo = [
        'query_filters' => [
            'department_id' => $departmentId,
            'department_name' => $departmentName,
            'year' => $year,
            'month' => $month,
            'risk_codes' => $riskCodes,
            'type' => $type,
            'dampak' => $dampak,
            'kemungkinan' => $kemungkinan
        ],
        'total_found' => $data->total(),
        // 'sql_query' => $query->toSql() // Untuk melihat query yang dijalankan
    ];

    // Mapping data pada halaman saat ini - HANYA TAMPILKAN DATA YANG RELEVAN
    $orderedData = collect($data->items())->map(function ($item) use ($type, $dampak, $kemungkinan, $month) {
        $inherentColor = get_color_by_position($item->inherent_risk_posisi_risiko);
        $residualTargetColor = get_color_by_position($item->residual_target_posisi_risiko);

        // Handle risk_code - berupa string yang dipisahkan koma
        $riskCodes = [];
        if (!empty($item->risk_code)) {
            $riskCodeIds = explode(',', $item->risk_code);
            $riskCodes = MstRiskCode::whereIn('id', $riskCodeIds)
                ->get(['id', 'name'])
                ->map(function ($riskCode) {
                    return [
                        'id' => $riskCode->id,
                        'name' => clean_string($riskCode->name)
                    ];
                })
                ->toArray();
        }

        // Base data yang selalu ada
        $baseData = [
            'id' => $item->id,
            'risk_code' => $riskCodes,
            'process_code' => $item->process_code ?? '',
            'jenis_risiko' => $item->jenis_risiko ?? '',
            'sasaran' => $item->sasaran ?? '',
            'peristiwa_risiko' => $item->peristiwa_risiko ?? '',
            'penyebab_risiko' => $item->penyebab_risiko ?? '',
            'dampak_risiko' => $item->dampak_risiko ?? '',
            'internal_control' => $item->internal_control ?? '',
            'department_id' => $item->department_id,
            'year' => $item->year,
            'created_at' => $item->created_at ? $item->created_at->toISOString() : null,
            'updated_at' => $item->updated_at ? $item->updated_at->toISOString() : null,
            'created_by' => $item->created_by ?? null,
            'created_by_name' => get_decrypted_username($item->createdBy),
            'updated_by' => $item->updated_by ?? null,
            'updated_by_name' => get_decrypted_username($item->updatedBy),
            'department' => $item->department ?? null,
        ];

        // Tambahkan data spesifik berdasarkan type
        switch ($type) {
            case 'inherent':
                $baseData = array_merge($baseData, [
                    'inherent_risk_level_dampak' => $item->inherent_risk_level_dampak ?? 0,
                    'inherent_risk_level_kemungkinan' => $item->inherent_risk_level_kemungkinan ?? 0,
                    'inherent_risk_posisi_risiko' => $item->inherent_risk_posisi_risiko ?? '',
                    'inherent_risk_level_risiko' => $item->inherent_risk_level_risiko ?? 0,
                    'inherent_risk_posisi_risiko_color' => $inherentColor,
                    'ir_dampak' => $item->irDampak ?? null,
                    'ir_kemungkinan' => $item->irKemungkinan ?? null,
                ]);
                break;

            case 'residual_target':
                $baseData = array_merge($baseData, [
                    'target_satu_tahun_option' => $item->target_satu_tahun_option ?? null,
                    'target_satu_tahun_option_name' => $item->optionTargetSatuTahun->name ?? '',
                    'target_satu_tahun_notes' => $item->target_satu_tahun_notes ?? '',
                    'target_satu_tahun_position' => $item->optionTargetSatuTahun->position ?? 0,
                    'target_quantitative_satu_tahun' => number_format($item->target_quantitative_satu_tahun, 0, ',', '.'),
                    'biaya_perlakuan_risiko' => number_format($item->biaya_perlakuan_risiko, 0, ',', '.'),
                    'residual_target_level_dampak' => $item->residual_target_level_dampak ?? 0,
                    'residual_target_level_kemungkinan' => $item->residual_target_level_kemungkinan ?? 0,
                    'residual_target_posisi_risiko' => $item->residual_target_posisi_risiko ?? '',
                    'residual_target_level_risiko' => $item->residual_target_level_risiko ?? 0,
                    'residual_target_posisi_risiko_color' => $residualTargetColor,
                    'rr_dampak' => $item->rrDampak ?? null,
                    'rr_kemungkinan' => $item->rrKemungkinan ?? null,
                ]);
                break;

            case 'residual':
            case 'residual_current':
                // Untuk residual_current, HANYA tampilkan monthly data yang sesuai filter
                $filteredMonthlyData = $item->monthlyData->filter(function($monthlyData) use ($dampak, $kemungkinan, $month) {
                    $matchesCriteria = $monthlyData->residual_risk_level_dampak == $dampak
                                    && $monthlyData->residual_risk_level_kemungkinan == $kemungkinan;

                    if ($month) {
                        return $matchesCriteria && $monthlyData->month == $month;
                    }

                    return $matchesCriteria;
                });

                $baseData['monthly_data'] = $filteredMonthlyData->map(function ($dataBulanan) {
                    $target = $dataBulanan->target_quantitative ?? 0;
                    $realization = $dataBulanan->realization_quantitative ?? 0;
                    $percentage = ($target > 0) ? round(($realization / $target) * 100, 2) : 0;

                    return [
                        'id' => $dataBulanan->id,
                        'header_id' => $dataBulanan->header_id,
                        'month' => $dataBulanan->month,
                        'risk_code' => $dataBulanan->risk_code,
                        'status_risiko' => $dataBulanan->status_risiko,
                        'process_code' => $dataBulanan->process_code,
                        'start_date' => $dataBulanan->start_date ? $dataBulanan->start_date->format('Y-m-d H:i:s') : null,
                        'expired_date' => $dataBulanan->expired_date ? $dataBulanan->expired_date->format('Y-m-d H:i:s') : null,
                        'realization_quantitative' => $realization,
                        'realization_note' => $dataBulanan->realization_note,
                        'target_quantitative' => $target,
                        'target_notes' => $dataBulanan->target_notes,
                        'residual_risk_level_dampak' => $dataBulanan->residual_risk_level_dampak,
                        'residual_risk_level_kemungkinan' => $dataBulanan->residual_risk_level_kemungkinan,
                        'residual_risk_posisi_risiko' => $dataBulanan->residual_risk_posisi_risiko,
                        'residual_risk_level_risiko' => $dataBulanan->residual_risk_level_risiko,
                        'residual_risk_level_risiko_color' => get_color_by_position($dataBulanan->residual_risk_posisi_risiko),
                        'realization_percentage' => $percentage . '%',
                        'is_finalize' => (bool) $dataBulanan->is_finalize,
                        'finalized_at' => $dataBulanan->finalized_at,
                        'finalized_by' => $dataBulanan->finalized_by,
                        'created_at' => $dataBulanan->created_at ? $dataBulanan->created_at->toISOString() : null,
                        'updated_at' => $dataBulanan->updated_at ? $dataBulanan->updated_at->toISOString() : null,
                        'uploads' => $dataBulanan->uploads->map(function ($upload) {
                            return [
                                'id' => $upload->id,
                                'filepath' => $upload->filepath,
                                'domain' => $upload->domain,
                            ];
                        }),
                    ];
                })->values(); // Reset index array

                // Tambahkan informasi summary untuk monthly
                $baseData['monthly_data_count'] = $filteredMonthlyData->count();
                break;
        }

        return $baseData;
    });;

    $cleanData = clean_recursive([
        'current_page' => $data->currentPage(),
        'per_page' => $data->perPage(),
        'total' => $data->total(),
        'last_page' => $data->lastPage(),
        'from' => $data->firstItem(),
        'to' => $data->lastItem(),
        'data' => $orderedData,
        'debug_info' => $debugInfo // Tambahkan debug info
    ]);

    // Return dalam format yang sama dengan index function
    return json(200, true, 'Data Ditemukan', 'Data risk header berhasil diambil.', $cleanData);
}

}
