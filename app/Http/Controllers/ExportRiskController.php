<?php

namespace App\Http\Controllers;

use App\Models\TrRiskHeader;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\MultiSheetRiskExport;
use Illuminate\Support\Facades\Auth;

class ExportRiskController extends Controller
{
    public function export(Request $request, $format)
    {
        // Hanya handle format excel untuk multi-sheet export
        if ($format !== 'excel') {
            return response()->json([
                'status' => 400,
                'success' => false,
                'message' => 'Format tidak didukung untuk multi-sheet export',
                'data' => 'Gunakan format excel untuk export multi-sheet'
            ], 400);
        }

        $filterYear  = $request->get('year');
        $filterMonth = $request->get('month');
        $filterDepartment = $request->get('department_id'); // Tambah filter department

        // Normalisasi filter bulan
        if (is_array($filterMonth) && isset($filterMonth['month'])) {
            $filterMonth = (int) $filterMonth['month'];
        } elseif (!is_null($filterMonth)) {
            $filterMonth = (int) $filterMonth;
        }

        // Normalisasi filter department
        if (!is_null($filterDepartment)) {
            $filterDepartment = (int) $filterDepartment;
        }

        // Ambil data header dengan semua field yang diperlukan untuk semua export
        $headers = TrRiskHeader::with([
            'riskCode:id,name',
            'department:id,name',
            'monthlyData' => function ($q) use ($filterYear, $filterMonth) {
                $q->select([
                    'id',
                    'header_id',
                    'target_quantitative',
                    'realization_quantitative',
                    'residual_risk_level_dampak',
                    'residual_risk_level_kemungkinan',
                    'residual_risk_posisi_risiko',
                    'residual_risk_level_risiko',
                    'realization_note',
                    'status_risiko',
                    'start_date',
                    'month'
                ]);

                if (!empty($filterYear)) {
                    $q->whereYear('start_date', $filterYear);
                }
                if (!empty($filterMonth)) {
                    $q->where('month', $filterMonth);
                }
            },
        ])
        ->select([
            'id',
            'risk_code',
            'jenis_risiko',
            'sasaran',
            'peristiwa_risiko',
            'penyebab_risiko',
            'dampak_risiko',
            'department_id', // Pastikan department_id di-select
            // Inherent risk fields
            'inherent_risk_level_dampak',
            'inherent_risk_level_kemungkinan',
            'inherent_risk_posisi_risiko',
            'inherent_risk_level_risiko',
            // Residual target risk fields
            'residual_target_level_dampak',
            'residual_target_level_kemungkinan',
            'residual_target_posisi_risiko',
            'residual_target_level_risiko',
            // Other fields
            'internal_control',
            'target_quantitative_satu_tahun',
            'biaya_perlakuan_risiko'
        ])
        // Filter berdasarkan department jika ada
        ->when(!is_null($filterDepartment), function ($query) use ($filterDepartment) {
            $query->where('department_id', $filterDepartment);
        })
        // Filter berdasarkan department user jika bukan admin (opsional)
        ->when($this->shouldFilterByUserDepartment(), function ($query) {
            $query->where('department_id', Auth::user()->department_id);
        })
        ->when(!empty($filterYear) || !empty($filterMonth), function ($query) use ($filterYear, $filterMonth) {
            $query->whereHas('monthlyData', function ($q) use ($filterYear, $filterMonth) {
                if (!empty($filterYear)) {
                    $q->whereYear('start_date', $filterYear);
                }
                if (!empty($filterMonth)) {
                    $q->where('month', $filterMonth);
                }
            });
        })
        ->orderBy('risk_code')
        ->get();

        // Kalau tidak ada data
        if ($headers->isEmpty()) {
            return response()->json([
                'status' => 404,
                'success' => false,
                'message' => 'Data Tidak Ditemukan',
                'data' => 'Data risiko untuk filter tersebut tidak ditemukan.'
            ], 404);
        }

        // Nama file dengan format yang sesuai
        $monthName = $this->getMonthName($filterMonth);

        // PERBAIKAN: Ambil department name dari data yang sudah di-load, bukan dari filter
        $departmentName = $this->getDepartmentNameFromHeaders($headers, $filterDepartment);

        $filename = "Risk_Report_Complete_{$departmentName}_{$monthName}_{$filterYear}_".time().".xlsx";

        // Export menggunakan MultiSheetRiskExport (3 sheets dalam 1 file)
        try {
            return Excel::download(
                new MultiSheetRiskExport($headers, $monthName, $filterYear ?? date('Y'), $departmentName),
                $filename
            );
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Gagal melakukan export',
                'data' => ['error' => $e->getMessage()]
            ], 500);
        }
    }

    private function getMonthName($month)
    {
        if (empty($month)) {
            return 'SEMUA_BULAN';
        }

        $monthNames = [
            1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET',
            4 => 'APRIL', 5 => 'MEI', 6 => 'JUNI',
            7 => 'JULI', 8 => 'AGUSTUS', 9 => 'SEPTEMBER',
            10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER'
        ];

        return $monthNames[$month] ?? 'BULAN_TIDAK_VALID';
    }

    private function getDepartmentName($departmentId)
    {
        if (empty($departmentId)) {
            return 'SEMUA_DEPT';
        }

        // Ambil nama department dari database atau cache
        $department = \App\Models\Department::find($departmentId);
        return $department ? strtoupper(str_replace(' ', '_', $department->name)) : 'DEPT_' . $departmentId;
    }

    /**
     * PERBAIKAN: Method baru untuk mengambil department name dari headers yang sudah di-load
     */
    private function getDepartmentNameFromHeaders($headers, $filterDepartment = null)
    {
        // Jika ada filter department spesifik, gunakan itu
        if (!is_null($filterDepartment)) {
            return $this->getDepartmentName($filterDepartment);
        }

        // Jika tidak ada filter, cek dari data headers
        if ($headers->isNotEmpty()) {
            // Ambil department dari record pertama
            $firstHeader = $headers->first();
            if ($firstHeader && $firstHeader->department) {
                return strtoupper(str_replace(' ', '_', $firstHeader->department->name));
            }
        }

        // Fallback jika tidak ada data department
        return 'SEMUA_DEPT';
    }

    /**
     * Cek apakah perlu filter berdasarkan department user
     * Misalnya jika user bukan admin, hanya bisa lihat data departmentnya
     */
    private function shouldFilterByUserDepartment()
    {
        $user = Auth::user();

        // Contoh logic: jika user bukan admin/super admin, filter by department
        return $user &&
               !in_array($user->role, ['admin', 'super_admin']) &&
               $user->department_id;
    }

    /**
     * Method untuk preview data sebelum export
     */
    public function preview(Request $request, $id)
    {
        $filterYear  = $request->get('year');
        $filterMonth = $request->get('month');
        $filterDepartment = $request->get('department_id'); // Tambah filter department

        // Normalisasi filter bulan
        if (is_array($filterMonth) && isset($filterMonth['month'])) {
            $filterMonth = (int) $filterMonth['month'];
        } elseif (!is_null($filterMonth)) {
            $filterMonth = (int) $filterMonth;
        }

        // Normalisasi filter department
        if (!is_null($filterDepartment)) {
            $filterDepartment = (int) $filterDepartment;
        }

        try {
            $headers = TrRiskHeader::with([
                'riskCode:id,name',
                'department:id,name',
                'monthlyData' => function ($q) use ($filterYear, $filterMonth) {
                    $q->select([
                        'id',
                        'header_id',
                        'target_quantitative',
                        'realization_quantitative',
                        'residual_risk_level_dampak',
                        'residual_risk_level_kemungkinan',
                        'residual_risk_posisi_risiko',
                        'residual_risk_level_risiko',
                        'start_date',
                        'month'
                    ]);

                    if (!empty($filterYear)) {
                        $q->whereYear('start_date', $filterYear);
                    }
                    if (!empty($filterMonth)) {
                        $q->where('month', $filterMonth);
                    }
                },
            ])
            ->select([
                'id',
                'risk_code',
                'jenis_risiko',
                'penyebab_risiko',
                'department_id', // Pastikan department_id di-select
                // Field untuk inherent risk
                'inherent_risk_level_dampak',
                'inherent_risk_level_kemungkinan',
                'inherent_risk_posisi_risiko',
                'inherent_risk_level_risiko',
                // Field untuk residual target risk
                'residual_target_level_dampak',
                'residual_target_level_kemungkinan',
                'residual_target_posisi_risiko',
                'residual_target_level_risiko',
                // Field tambahan
                'target_quantitative_satu_tahun',
                'biaya_perlakuan_risiko'
            ])
            // Filter berdasarkan department jika ada
            ->when(!is_null($filterDepartment), function ($query) use ($filterDepartment) {
                $query->where('department_id', $filterDepartment);
            })
            // Filter berdasarkan department user jika bukan admin (opsional)
            ->when($this->shouldFilterByUserDepartment(), function ($query) {
                $query->where('department_id', Auth::user()->department_id);
            })
            ->when(!empty($filterYear) || !empty($filterMonth), function ($query) use ($filterYear, $filterMonth) {
                $query->whereHas('monthlyData', function ($q) use ($filterYear, $filterMonth) {
                    if (!empty($filterYear)) {
                        $q->whereYear('start_date', $filterYear);
                    }
                    if (!empty($filterMonth)) {
                        $q->where('month', $filterMonth);
                    }
                });
            })
            // Jika ada ID specific, filter berdasarkan ID tersebut
            ->when($id, function ($query) use ($id) {
                if ($id > 0) {
                    $query->where('id', $id);
                }
            })
            ->limit(10)
            ->get();

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Preview data berhasil',
                'data' => [
                    'total_records' => TrRiskHeader::count(),
                    'filtered_records' => $headers->count(),
                    'preview_data' => $headers,
                    'sheets_info' => [
                        'risk_register' => 'Sheet 1: Data lengkap risk register',
                        'monitoring' => 'Sheet 2: Data monitoring risiko',
                        'peta_risiko' => 'Sheet 3: Peta risiko (heatmap)'
                    ],
                    'filters' => [
                        'id' => $id,
                        'year' => $filterYear,
                        'month' => $filterMonth,
                        'month_name' => $this->getMonthName($filterMonth),
                        'department_id' => $filterDepartment,
                        'department_name' => $this->getDepartmentNameFromHeaders($headers, $filterDepartment)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Gagal mengambil preview data',
                'data' => ['error' => $e->getMessage()]
            ], 500);
        }
    }
}
