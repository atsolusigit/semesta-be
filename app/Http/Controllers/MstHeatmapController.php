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
        // Check authorization: only role 1, 2, 4 and 5 can store
        $result = check_role(auth()->user(), [1, 2, 4, 5]);
        if ($result !== true) {
            return $result;
        }

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
        // Check authorization: only role 1, 2, 4 and 5 can update
        $result = check_role(auth()->user(), [1, 2, 4, 5]);
        if ($result !== true) {
            return $result;
        }

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
        // Check authorization: only role 1 can delete
        $result = check_role(auth()->user(), [1]);
        if ($result !== true) {
            return $result;
        }

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
        'department_name' => 'nullable|string',
        'year' => 'nullable|integer',
        'risk_code' => 'array',
        'kode_risiko' => 'array',
        'month' => 'nullable|integer|min:1|max:12',
        'type' => 'nullable|string|in:inherent,residual,residual_current,residual_target,all'
    ]);

    $departmentId = $request->department_id;
    $departmentName = $request->department_name;
    $year = $request->year;
    $riskCodes = $request->risk_code ?? $request->kode_risiko ?? [];
    $month = $request->month;
    $type = $request->type ?? 'all';

    $user = auth()->user();
    $userRole = $user->role->id ?? $user->role_id ?? 1;
    $userDepartmentId = $user->department_id ?? null;

    if (in_array($userRole, [2, 3]) && $userDepartmentId) {
        $departmentId = $userDepartmentId;
        $departmentName = null;
    }

    $probabilitasLabels = [];
    $dampakLabels = [];
    $riskCategoriesArray = [];
    $riskCategoriesForTable = [];

    $kemungkinanData = \DB::table('mst_heatmap_kemungkinan')
        ->select('id', 'label')
        ->orderBy('id')
        ->get();

    foreach ($kemungkinanData as $item) {
        $probabilitasLabels[(string)$item->id] = $item->label;
    }

    $dampakData = \DB::table('mst_heatmap_dampak')
        ->select('id', 'label')
        ->orderBy('id')
        ->get();

    foreach ($dampakData as $item) {
        $dampakLabels[(string)$item->id] = $item->label;
    }

    $riskRangeData = \DB::table('mst_heatmap_risk_range')
        ->select('name', 'start', 'end', 'color')
        ->orderBy('start')
        ->get();

    foreach ($riskRangeData as $item) {
        $riskCategoriesArray[] = [
            'name' => $item->name,
            'min_score' => $item->start,
            'max_score' => $item->end,
            'color' => $item->color
        ];

        $riskCategoriesForTable[$item->name] = $item->color;
    }

    $query = TrRiskHeader::with([
        'monthlyData' => function($q) use ($month) {
            if ($month) {
                $q->where('month', $month);
            }
            $q->orderBy('month', 'desc');
        },
        'department'
    ]);

    if ($departmentId) {
        $query->where('department_id', $departmentId);
    }

    if ($departmentName) {
        $query->whereHas('department', function($q) use ($departmentName) {
            $q->where('name', 'like', '%' . $departmentName . '%');
        });
    }

    if ($year) {
        $query->where('year', $year);
    }

    if (!empty($riskCodes)) {
        $query->where(function($q) use ($riskCodes) {
            foreach ($riskCodes as $riskCode) {
                $q->orWhereRaw('FIND_IN_SET(?, risk_code)', [$riskCode]);
            }
        });
    }

    $riskHeaders = $query->get();

    $responseDepartmentName = null;
    if ($departmentName) {
        $responseDepartmentName = $departmentName;
    } elseif ($departmentId && $riskHeaders->count() > 0) {
        $firstRiskHeader = $riskHeaders->first();
        $responseDepartmentName = $firstRiskHeader->department ? $firstRiskHeader->department->name : null;
    }

    $inherentMatrixEvents = initialize_risk_event_matrix();
    $residualCurrentMatrixEvents = initialize_risk_event_matrix();
    $residualTargetMatrixEvents = initialize_risk_event_matrix();

    $inherentSummary = initialize_risk_summary();
    $residualCurrentSummary = initialize_risk_summary();
    $residualTargetSummary = initialize_risk_summary();

    $processedData = [
        'inherent_processed' => 0,
        'residual_current_processed' => 0,
        'residual_target_processed' => 0,
        'monthly_data_found' => 0,
        'headers_with_monthly' => 0
    ];

    $tableData = [];
    $tableSummary = initialize_risk_summary();

    // === Tambahan untuk Line Chart ===
    $months = range(1, 12);
    $inherentChart = array_fill(0, 12, 0);
    $residualCurrentChart = array_fill(0, 12, 0);
    $residualTargetChart = array_fill(0, 12, 0);
    $monthCounts = array_fill(0, 12, 0);

    foreach ($riskHeaders as $index => $header) {
        $order = $index + 1;

        // === INHERENT RISK ===
        if (in_array($type, ['inherent', 'all'])) {
            $impact = $header->inherent_risk_level_dampak ?? 0;
            $likelihood = $header->inherent_risk_level_kemungkinan ?? 0;

            if ($impact > 0 && $likelihood > 0) {
                $inherentMatrixEvents[$likelihood][$impact][] = [
                    'order' => $order,
                    'peristiwa_risiko' => $header->peristiwa_risiko ?? ''
                ];
                $score = $impact * $likelihood;
                $inherentSummary[get_risk_category_by_score($score)]++;
                $processedData['inherent_processed']++;
                $tableSummary[get_risk_category_by_score($score)]++;

                // Line chart (skor konstan tiap bulan)
                foreach ($months as $i => $m) {
                    $inherentChart[$i] += $score;
                    $monthCounts[$i]++;
                }
            }
        }

        // === RESIDUAL CURRENT RISK ===
        if (in_array($type, ['residual', 'residual_current', 'all'])) {
            if ($header->monthlyData->count() > 0) {
                $processedData['headers_with_monthly']++;
                foreach ($header->monthlyData as $monthlyData) {
                    $processedData['monthly_data_found']++;

                    $impact = $monthlyData->residual_risk_level_dampak ?? 0;
                    $likelihood = $monthlyData->residual_risk_level_kemungkinan ?? 0;
                    $monthIndex = ($monthlyData->month ?? 1) - 1;

                    if ($impact > 0 && $likelihood > 0 && $monthIndex >= 0 && $monthIndex < 12) {
                        $score = $impact * $likelihood;
                        $residualCurrentMatrixEvents[$likelihood][$impact][] = [
                            'order' => $order,
                            'peristiwa_risiko' => $header->peristiwa_risiko ?? ''
                        ];
                        $residualCurrentSummary[get_risk_category_by_score($score)]++;
                        $processedData['residual_current_processed']++;
                        $tableSummary[get_risk_category_by_score($score)]++;
                        $residualCurrentChart[$monthIndex] += $score;
                        $monthCounts[$monthIndex]++;
                    }
                }
            }
        }

        // === RESIDUAL TARGET RISK ===
        if (in_array($type, ['residual', 'residual_target', 'all'])) {
            $impact = $header->residual_target_level_dampak ?? 0;
            $likelihood = $header->residual_target_level_kemungkinan ?? 0;

            if ($impact > 0 && $likelihood > 0) {
                $score = $impact * $likelihood;
                $residualTargetMatrixEvents[$likelihood][$impact][] = [
                    'order' => $order,
                    'peristiwa_risiko' => $header->peristiwa_risiko ?? ''
                ];
                $residualTargetSummary[get_risk_category_by_score($score)]++;
                $processedData['residual_target_processed']++;
                $tableSummary[get_risk_category_by_score($score)]++;

                // Line chart (konstan tiap bulan)
                foreach ($months as $i => $m) {
                    $residualTargetChart[$i] += $score;
                }
            }
        }
    }

    // Hitung rata-rata per bulan agar lebih proporsional di chart
    foreach ($months as $i => $m) {
        $count = max($monthCounts[$i], 1);
        $inherentChart[$i] = round($inherentChart[$i] / $count, 2);
        $residualCurrentChart[$i] = round($residualCurrentChart[$i] / $count, 2);
        $residualTargetChart[$i] = round($residualTargetChart[$i] / $count, 2);
    }

    foreach ($riskCategoriesForTable as $category => $color) {
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
            'department_name' => $responseDepartmentName,
            'year' => $year,
            'month' => $month,
            'risk_codes' => $riskCodes,
            'kode_risiko' => $riskCodes,
            'type' => $type,
            'total_risks' => $riskHeaders->count(),
            'user_role' => $userRole,
            'access_restricted' => in_array($userRole, [2, 3])
        ],
        'processing_info' => $processedData,
        'heatmap' => [
            'inherent' => [
                'grid' => $inherentMatrixEvents,
                'summary' => $inherentSummary,
                'total' => array_sum($inherentSummary)
            ],
            'residual_current' => [
                'grid' => $residualCurrentMatrixEvents,
                'summary' => $residualCurrentSummary,
                'total' => array_sum($residualCurrentSummary)
            ],
            'residual_target' => [
                'grid' => $residualTargetMatrixEvents,
                'summary' => $residualTargetSummary,
                'total' => array_sum($residualTargetSummary)
            ]
        ],
        'table_data' => $tableData,
        'legend' => [
            'probabilitas_labels' => $probabilitasLabels,
            'dampak_labels' => $dampakLabels,
            'risk_categories' => $riskCategoriesArray
        ],
        'line_chart' => [
            'title' => 'DIVISI MANAJEMEN RISIKO, TATAKELOLA & KEPATUHAN',
            'data' => [
                'months' => $months,
                'labels' => ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'],
                'inherent_risk' => $inherentChart,
                'residual_current_risk' => $residualCurrentChart,
                'residual_target_risk' => $residualTargetChart
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
