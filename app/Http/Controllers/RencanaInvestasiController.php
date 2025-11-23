<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http; 
use App\Models\RencanaInvestasi;
use App\Models\TrRiskInvestasi;
use App\Models\RencanaInvestasiTimelineYear;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\RencanaInvestasi\MultiSheetRencanaInvestasiExport;

class RencanaInvestasiController extends Controller
{
    public function index(Request $request)
    {
        $perPage   = (int) $request->input('per_page', 10);
        $sortBy    = $request->input('sortBy');
        $sortOrder = strtolower($request->input('sortOrder', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortMap = [
            'tahun'                 => 'year',
            'nilai'                 => 'nilai_rkap',
            'nilai_erkap'           => 'nilai_rkap',
            'nilai_revisi'          => 'nilai_revisi',
            'nilai_budget_transfer' => 'nilai_budget_transfer',
        ];
        $sortColumn = $sortMap[$sortBy] ?? 'rencana_investasi.id';

        $now   = now();
        $tahun = (int) ($request->integer('tahun') ?: $now->year);
        $bulan = (int) ($request->integer('bulan') ?: $now->month);
        $week  = (int) ($request->integer('week')  ?: ceil($now->day / 7));

        $filterTahun = $request->filled('tahun') ? (int)$request->get('tahun') : null;
        $filterJenis = $request->filled('jenis_investasi') ? trim((string)$request->get('jenis_investasi')) : null;

        $unitParam = $request->get('unit')
            ?? $request->get('divisi')
            ?? $request->get('risk_owner')
            ?? $request->get('department_id')
            ?? $request->get('unit_kerja_id')
            ?? $request->get('department_name');

        $query = RencanaInvestasi::query()
            ->select('rencana_investasi.*', 'mst_email_unit_kerja.unit_kerja_nama as department_name_joined')
            ->leftJoin('mst_email_unit_kerja', 'rencana_investasi.unit_kerja_id', '=', 'mst_email_unit_kerja.unit_kerja_id')
            ->with([
                'createdBy:id,username',
                'updatedBy:id,username',
                'riskInvestasi:erkap_id,status,approved_by,approved_at,dampak_risiko_awal,kemungkinan_awal,eksposure_level_awal,eksposure_ltmh_awal,dampak_risiko_akhir,kemungkinan_akhir,eksposure_level_akhir,eksposure_ltmh_akhir,biaya_mitigasi_risiko',
                'riskInvestasi.approvedByUser:id,username,name',
                'periods' => function ($q) use ($tahun, $bulan, $week, $request) {
                    $q->where('year', $tahun)
                      ->where('month', $request->filled('bulan') ? (int)$request->bulan : $bulan)
                      ->where('week',  $request->filled('week')  ? (int)$request->week  : $week);
                },
            ])
            ->when(!is_null($filterTahun), fn($q) => $q->where('rencana_investasi.year', $filterTahun))
            ->when($filterJenis, fn($q) =>
                $q->where('rencana_investasi.jenis_investasi', 'like', '%'.$filterJenis.'%')
            )
            ->when(!is_null($unitParam), function ($q) use ($unitParam) {
                if (is_numeric($unitParam)) {
                    $id = (int)$unitParam;
                    $q->where(function ($qq) use ($id) {
                        $qq->where('rencana_investasi.department_id', $id)
                           ->orWhere('rencana_investasi.unit_kerja_id', $id);
                    });
                } else {
                    $name = trim((string)$unitParam);
                    if ($name !== '') {
                        $q->where(function ($qq) use ($name) {
                            $qq->where('rencana_investasi.department_name', 'like', '%'.$name.'%')
                               ->orWhere('mst_email_unit_kerja.unit_kerja_nama', 'like', '%'.$name.'%');
                        });
                    }
                }
            })
            ->when($request->filled('department_name'), function ($q) use ($request) {
                $name = trim($request->department_name);
                if ($name !== '') {
                    $q->where(function ($qq) use ($name) {
                        $qq->where('rencana_investasi.department_name', 'like', '%'.$name.'%')
                           ->orWhere('mst_email_unit_kerja.unit_kerja_nama', 'like', '%'.$name.'%');
                    });
                }
            })
            ->when($request->filled('erkap_id'), fn($q) => $q->where('rencana_investasi.erkap_id', (int)$request->erkap_id))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = trim((string)$request->search);
                if ($s !== '') {
                    $q->where(function ($qq) use ($s) {
                        $qq->where('rencana_investasi.nama_investasi', 'like', "%{$s}%")
                        ->orWhere('rencana_investasi.kategori_investasi', 'like', "%{$s}%")
                        ->orWhere('rencana_investasi.keterangan', 'like', "%{$s}%");
                    });
                }
            })
            ->when($request->filled('bulan') || $request->filled('week'), function ($q) use ($tahun, $bulan, $week, $request) {
                $q->whereHas('periods', function ($qq) use ($tahun, $bulan, $week, $request) {
                    $qq->where('year', $tahun)
                       ->where('month', $request->filled('bulan') ? (int)$request->bulan : $bulan)
                       ->where('week',  $request->filled('week')  ? (int)$request->week  : $week);
                });
            })
            ->orderBy($sortColumn, $sortOrder);

        $data = $query->paginate($perPage);

        if (empty($data->items())) {
            return json(404, false, 'Tidak Ada Data', 'Data rencana investasi tidak ditemukan.', null);
        }

        $resData = collect($data->items())->map(function ($it) use ($tahun, $bulan, $week) {
            $targetTimeline = null;
            $cache = \App\Models\RencanaInvestasiTimelineYear::where('erkap_id', $it->erkap_id)
                ->where('year', $tahun)
                ->first();

            if ($cache && is_array($cache->timeline_json)) {
                $bulanEntry = collect($cache->timeline_json)->firstWhere('bulan_id', (int)$bulan);
                if (is_array($bulanEntry)) {
                    $w = max(1, min((int)$week, 4));
                    $colorKey = "week{$w}_color";
                    $labelKey = "week{$w}_label";
                    $color = $bulanEntry[$colorKey] ?? null;
                    $label = $bulanEntry[$labelKey] ?? null;
                    if ($color || $label) {
                        $targetTimeline = ['color' => $color, 'label' => $label];
                    }
                }
            }

            $realisasiTimeline = !empty($it->realisasi_timeline)
                ? (is_string($it->realisasi_timeline)
                    ? $it->realisasi_timeline
                    : json_encode($it->realisasi_timeline))
                : null;

            $departmentName = $it->department_name_joined ?? $it->department_name;

            return [
                'id'                      => $it->id,
                'erkap_id'                => $it->erkap_id,
                'department_name'         => $departmentName,
                'nama_investasi'          => $it->nama_investasi,
                'kategori_investasi'      => $it->kategori_investasi,
                'jenis_investasi'         => $it->jenis_investasi,
                'year'                    => $it->year,
                'nilai_rkap'              => $it->nilai_rkap,
                'nilai_revisi'            => $it->nilai_revisi,
                'nilai_budget_transfer'   => $it->nilai_budget_transfer,
                'nilai_realisasi'         => $it->nilai_realisasi,
                'target_timeline'         => $targetTimeline,
                'realisasi_timeline'      => $realisasiTimeline,
                'ld_inherent'             => $it->ld_inherent,
                'dampak_inherent'         => $it->dampak_inherent,
                'ld_current'              => $it->ld_current,
                'lk_current'              => $it->lk_current,
                'level_current'           => $it->level_current,
                'dampak_current'          => $it->dampak_current,
                'level_residual'          => $it->level_residual,
                'dampak_residual'         => $it->dampak_residual,
                'keterangan'              => $it->keterangan,
                'status'                  => $it->status,
                'has_risk_profile'        => (bool) $it->riskInvestasi,
                'risk_investasi' => $it->riskInvestasi ? [
                    'erkap_id'               => $it->riskInvestasi->erkap_id,
                    'status'                 => $it->riskInvestasi->status,
                    'approved_by'            => $it->riskInvestasi->approved_by,
                    'approved_by_name'       => $it->riskInvestasi->approvedByUser ? get_decrypted_name($it->riskInvestasi->approvedByUser) : null,
                    'approved_at'            => optional($it->riskInvestasi->approved_at)->toISOString(),
                    'dampak_risiko_awal'     => $it->riskInvestasi->dampak_risiko_awal,
                    'kemungkinan_awal'       => $it->riskInvestasi->kemungkinan_awal,
                    'eksposure_level_awal'   => $it->riskInvestasi->eksposure_level_awal,
                    'eksposure_ltmh_awal'    => $it->riskInvestasi->eksposure_ltmh_awal,
                    'dampak_risiko_akhir'    => $it->riskInvestasi->dampak_risiko_akhir,
                    'kemungkinan_akhir'      => $it->riskInvestasi->kemungkinan_akhir,
                    'eksposure_level_akhir'  => $it->riskInvestasi->eksposure_level_akhir,
                    'eksposure_ltmh_akhir'   => $it->riskInvestasi->eksposure_ltmh_akhir,
                    'biaya_mitigasi_risiko'  => $it->riskInvestasi->biaya_mitigasi_risiko,
                ] : null,
                'created_at'              => optional($it->created_at)->toISOString(),
                'updated_at'              => optional($it->updated_at)->toISOString(),
                'created_by'              => $it->created_by,
                'created_by_name'         => get_decrypted_name($it->createdBy),
                'updated_by'              => $it->updated_by,
                'updated_by_name'         => $it->updatedBy ? get_decrypted_name($it->updatedBy) : null,
            ];
        });

        $cleanData = clean_recursive([
            'current_page' => $data->currentPage(),
            'per_page'     => $data->perPage(),
            'total'        => $data->total(),
            'last_page'    => $data->lastPage(),
            'from'         => $data->firstItem(),
            'to'           => $data->lastItem(),
            'data'         => $resData,
        ]);

        return json(200, true, 'Data Ditemukan', 'Data rencana investasi berhasil diambil.', $cleanData);
    }

    public function store(Request $request)
    {
        $result = check_role(auth()->user(), [3]);
        if ($result !== true) return $result;

        $validator = Validator::make($request->all(), [
            'erkap_id'         => 'required|integer',
            'department_name'  => 'required|string',
            'nama_investasi'   => 'required|string',
            'kategori_investasi'=> 'required|string',
            'jenis_investasi'  => 'required|string',
            'year'             => 'required|numeric',
            'nilai_rkap'       => 'nullable|numeric',
            'nilai_revisi'     => 'nullable|numeric',
            'keterangan'       => 'required|string',
            'status'           => 'required|string',
            'nilai_budget_transfer' => 'nullable|integer',
            'nilai_realisasi'       => 'nullable|integer',
            'target_timeline'       => 'nullable|string',
            'realisasi_timeline'    => 'nullable|string',
            'ld_inherent'           => 'nullable|integer',
            'dampak_inherent'       => 'nullable|string',
            'ld_current'            => 'nullable|integer',
            'lk_current'            => 'nullable|integer',
            'level_current'         => 'nullable|integer',
            'dampak_current'        => 'nullable|string',
            'level_residual'        => 'nullable|string',
            'dampak_residual'       => 'nullable|string',
        ]);
        if ($validator->fails()) return json(400,false,'Validasi Gagal','Validasi gagal.',$validator->errors());

        $exists = RencanaInvestasi::where('erkap_id', $request->erkap_id)->exists();
        if ($exists) return json(403,false,'Sudah Ada','Data rencana investasi sudah ada',null);

        try {
            DB::beginTransaction();

            $currentUser = auth()->user();

            $data = $request->only([
                'erkap_id','department_name','nama_investasi','kategori_investasi','jenis_investasi',
                'year','nilai_rkap','nilai_revisi','keterangan','status',
                'nilai_budget_transfer','nilai_realisasi','target_timeline','realisasi_timeline',
                'ld_inherent','dampak_inherent','ld_current','lk_current','level_current',
                'dampak_current','level_residual','dampak_residual',
            ]);
            $data['created_by'] = auth()->id();
            $data['unit_kerja_id'] = $currentUser->role_id == 1 ? $request->input('unit_kerja_id') : $currentUser->department_id;

            $item = RencanaInvestasi::create($data);

            DB::commit();

            $item->load('createdBy:id,username');

            $resp = [
                'id' => $item->id,
                'erkap_id' => $item->erkap_id,
                'nama_investasi' => clean_string($item->nama_investasi),
                'kategori_investasi' => clean_string($item->kategori_investasi),
                'jenis_investasi'    => clean_string($item->jenis_investasi),
                'nilai_rkap'         => $item->nilai_rkap,
                'nilai_revisi'       => $item->nilai_revisi,
                'department_name'    => $item->department_name,
                'status'             => $item->status,
                'year'               => $item->year,
                'nilai_budget_transfer' => $item->nilai_budget_transfer,
                'nilai_realisasi'       => $item->nilai_realisasi,
                'target_timeline'       => $item->target_timeline,
                'realisasi_timeline'    => $item->realisasi_timeline,
                'ld_inherent'           => $item->ld_inherent,
                'dampak_inherent'       => clean_string($item->dampak_inherent),
                'ld_current'            => $item->ld_current,
                'lk_current'            => $item->lk_current,
                'level_current'         => $item->level_current,
                'dampak_current'        => clean_string($item->dampak_current),
                'level_residual'        => clean_string($item->level_residual),
                'dampak_residual'       => clean_string($item->dampak_residual),

                'created_at'         => $item->created_at,
                'created_by'         => $item->created_by,
                'created_by_name'    => get_decrypted_name($item->createdBy),
            ];

            return json(200,true,'Berhasil Disimpan','Rencana investasi berhasil disimpan.',$resp);

        } catch (\Throwable $th) {
            DB::rollBack();
            return json(500,false,'Gagal Disimpan','Terjadi kesalahan sistem.',$th->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        // 🔐 REVISI ROLE:
        // - update current risk investasi => manrisk (1,4,5,6)
        // - update realisasi timeline => user unit + manrisk (1,3,4,5,6)
        $result = check_role(auth()->user(), [1, 3, 4, 5, 6]);
        if ($result !== true) return $result;

        $item = RencanaInvestasi::find($id);
        if (!$item) {
            return json(404, false, 'Tidak Ditemukan', 'Rencana investasi tidak ditemukan.', null);
        }

        // $locked = TrRiskInvestasi::where('erkap_id', $item->erkap_id)->exists();
        // if ($locked) {
        //     return json(403, false, 'Terkunci', 'Risk Profile Investasi sudah dibuat. Rencana Investasi tidak dapat diupdate.', null);
        // }

        $validator = Validator::make($request->all(), [
            'ld_current'         => 'nullable|integer',
            'lk_current'         => 'nullable|integer',
            'level_current'      => 'nullable|integer',
            'dampak_residual'    => 'nullable|string',
            'realisasi_timeline' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return json(400, false, 'Validasi Gagal', 'Validasi gagal.', $validator->errors());
        }

        $currentUser = auth()->user();
        $roleId = $currentUser->role_id ?? null;

        // Role yang boleh update current risk investasi
        $riskRoles = [1, 4, 5, 6];
        // Role yang boleh update realisasi timeline
        $timelineRoles = [1, 3, 4, 5, 6];

        // Bangun payload sesuai role
        $payload = [];

        if (in_array($roleId, $riskRoles, true)) {
            $riskPayload = $request->only([
                'ld_current',
                'lk_current',
                'level_current',
                'dampak_residual',
            ]);
            $payload = array_filter(
                $riskPayload,
                fn($v) => !is_null($v)
            ) + $payload;
        }

        if (in_array($roleId, $timelineRoles, true)) {
            $timelinePayload = $request->only([
                'realisasi_timeline',
            ]);
            $payload = $payload + array_filter(
                $timelinePayload,
                fn($v) => !is_null($v)
            );
        }

        // Jika setelah filter role tidak ada field yang boleh diupdate
        if (empty($payload)) {
            return json(
                403,
                false,
                'Akses Ditolak',
                'Anda tidak memiliki hak untuk mengubah field yang diminta.',
                null
            );
        }

        try {
            DB::beginTransaction();

            $payload['updated_by'] = auth()->id();
            $item->update($payload);

            DB::commit();

            return json(200, true, 'Berhasil Diperbarui', 'Rencana investasi berhasil diupdate.', $item->only([
                'id',
                'erkap_id',
                'ld_current',
                'lk_current',
                'level_current',
                'dampak_residual',
                'realisasi_timeline',
                'updated_by',
                'updated_at',
            ]));
        } catch (\Throwable $th) {
            DB::rollBack();
            return json(500, false, 'Gagal Update', 'Terjadi kesalahan sistem.', $th->getMessage());
        }
    }

    public function export(Request $request, string $format)
    {
        if (!in_array($format, ['excel', 'pdf'])) {
            return response()->json([
                'status'  => 400,
                'success' => false,
                'message' => 'Format tidak didukung',
                'data'    => 'Format yang didukung: excel, pdf'
            ], 400);
        }

        $now              = now();
        $tahunForTimeline = (int) $now->year;
        $bulanForTimeline = (int) $now->month;
        $weekForTimeline  = (int) max(1, min((int)ceil($now->day / 7), 4));
        $monthName        = $this->getMonthName($bulanForTimeline);

        $rows = \App\Models\RencanaInvestasi::query()
            ->select('rencana_investasi.*', 'mst_email_unit_kerja.unit_kerja_nama as department_name_joined')
            ->leftJoin('mst_email_unit_kerja', 'rencana_investasi.unit_kerja_id', '=', 'mst_email_unit_kerja.unit_kerja_id')
            ->with([
                'createdBy:id,username',
                'updatedBy:id,username',
                'riskInvestasi:erkap_id,status,approved_by,approved_at,dampak_risiko_awal,kemungkinan_awal,eksposure_level_awal,eksposure_ltmh_awal,dampak_risiko_akhir,kemungkinan_akhir,eksposure_level_akhir,eksposure_ltmh_akhir,biaya_mitigasi_risiko',
                'riskInvestasi.approvedByUser:id,username,name',
                'periods' => function ($q) use ($tahunForTimeline, $bulanForTimeline, $weekForTimeline) {
                    $q->where('year',  $tahunForTimeline)
                    ->where('month', $bulanForTimeline)
                    ->where('week',  $weekForTimeline);
                },
            ])
            ->orderBy('rencana_investasi.id', 'asc')
            ->get();

        $departmentName = $this->guessDepartmentName($rows, null);

        $flatRows = $rows->map(function ($it) use ($tahunForTimeline, $bulanForTimeline, $weekForTimeline) {
            [$targetColor, $targetLabel] = $this->extractTargetTimeline($it);
            [$realColor, $realLabel]     = $this->extractRealisasiTimeline(
                (int)$it->erkap_id,
                $tahunForTimeline,
                $bulanForTimeline,
                $weekForTimeline
            );
            $deptName = $it->department_name_joined ?? $it->department_name;

            return [
                'ID'                        => $it->id,
                'ERKAP ID'                  => $it->erkap_id,
                'Department'                => $deptName,
                'Nama Investasi'            => $it->nama_investasi,
                'Kategori Investasi'        => $it->kategori_investasi,
                'Jenis Investasi'           => $it->jenis_investasi,
                'Tahun'                     => $it->year,
                'Nilai RKAP'                => (string)$it->nilai_rkap,
                'Nilai Revisi'              => (string)$it->nilai_revisi,
                'Budget Transfer'           => (string)$it->nilai_budget_transfer,
                'Realisasi'                 => (string)$it->nilai_realisasi,
                'Target Timeline Label'     => $targetLabel,
                'Target Timeline Color'     => $targetColor,
                'Realisasi Timeline Label'  => $realLabel,
                'Realisasi Timeline Color'  => $realColor,
                'Status'                    => $it->status,
                'Keterangan'                => $it->keterangan,
                'Created At'                => optional($it->created_at)->format('Y-m-d H:i:s'),
                'Updated At'                => optional($it->updated_at)->format('Y-m-d H:i:s'),
            ];
        })->values();

        try {
            if ($format === 'excel') {
                $filename = "RencanaInvestasi_{$departmentName}_{$monthName}_{$tahunForTimeline}_" . time() . ".xlsx";
                return \Maatwebsite\Excel\Facades\Excel::download(
                    new \App\Exports\RencanaInvestasi\MultiSheetRencanaInvestasiExport(
                        $flatRows,
                        $tahunForTimeline,
                        $bulanForTimeline,
                        $weekForTimeline,
                        $departmentName,
                        $monthName
                    ),
                    $filename
                );
            }

            // PDF
            $filename = "RencanaInvestasi_{$departmentName}_{$monthName}_{$tahunForTimeline}_" . time() . ".pdf";
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.rencana_investasi_pdf', [
                'rows'           => $flatRows,
                'monthName'      => $monthName,
                'year'           => $tahunForTimeline,
                'departmentName' => $departmentName,
            ])->setPaper('A4', 'landscape');

            return $pdf->download($filename);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 500,
                'success' => false,
                'message' => 'Gagal melakukan export',
                'data'    => ['error' => $e->getMessage()]
            ], 500);
        }
    }

    private function extractTargetTimeline($it): array
    {
        $period = $it->periods->first();
        if ($period && is_array($period->detail_json)) {
            $firstDetail = $period->detail_json[0] ?? null;
            if (is_array($firstDetail) && !empty($firstDetail['timeline_target'])) {
                $firstTl = $firstDetail['timeline_target'][0] ?? null;
                if (is_array($firstTl)) {
                    return [$firstTl['color'] ?? null, $firstTl['label'] ?? null];
                }
            }
        }
        return [null, null];
    }

    private function extractRealisasiTimeline(int $erkapId, int $tahun, int $bulan, int $week): array
    {
        $cache = RencanaInvestasiTimelineYear::where('erkap_id', $erkapId)->where('year', $tahun)->first();
        if ($cache && is_array($cache->timeline_json)) {
            $bulanEntry = collect($cache->timeline_json)->firstWhere('bulan_id', (int)$bulan);
            if (is_array($bulanEntry)) {
                $w = max(1, min((int)$week, 4));
                return [$bulanEntry["week{$w}_color"] ?? null, $bulanEntry["week{$w}_label"] ?? null];
            }
        }
        return [null, null];
    }

    private function normalizeMonth($month)
    {
        if (is_array($month) && isset($month['month'])) return (int)$month['month'];
        return !is_null($month) ? (int)$month : null;
    }

    private function getMonthName(int $month): string
    {
        $map = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
        return $map[$month] ?? (string)$month;
    }

    private function guessDepartmentName($rows, $filterDepartment)
    {
        if ($filterDepartment) {
            $hit = $rows->firstWhere('department_id', $filterDepartment);
            if ($hit) return $hit->department_name_joined ?? $hit->department_name ?? 'Department';
        }
        $hit = $rows->first();
        return $hit?->department_name_joined ?? $hit?->department_name ?? 'All-Dept';
    }

    private function shouldFilterByUserDepartment(): bool
    {

        return false;
    }

    public function timeline(Request $request)
    {
        $tahun   = (int) $request->query('tahun');
        $capexId = (int) $request->query('capex_id');

        if (!$tahun || !$capexId) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter tahun dan capex_id wajib diisi',
            ], 400);
        }

        $base = rtrim(config('services.erkap.base_url', env('ERKAP_BASE_URL', '')), '/');
        $auth = config('services.erkap.basic_auth', env('ERKAP_BASIC_AUTH', ''));

        if (!$base || !$auth) {
            return response()->json([
                'success' => false,
                'message' => 'ERKAP_BASE_URL / ERKAP_BASIC_AUTH belum diset di .env',
            ], 500);
        }

        try {
            $resp = Http::retry(3, 300)
                ->timeout(25)
                ->withHeaders([
                    'Authorization' => $auth,
                ])
                ->get($base . '/api/semesta/capex-timeline', [
                    'tahun'    => $tahun,
                    'capex_id' => $capexId,
                ])
                ->throw()
                ->json();

            return response()->json($resp, 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memanggil API ERKAP capex-timeline',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

}
