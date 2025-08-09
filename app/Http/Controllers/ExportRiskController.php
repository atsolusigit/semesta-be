<?php

namespace App\Http\Controllers;

use App\Models\TrRiskHeader;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\MultiSheetRiskExport;

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

        // Normalisasi filter bulan
        if (is_array($filterMonth) && isset($filterMonth['month'])) {
            $filterMonth = (int) $filterMonth['month'];
        } elseif (!is_null($filterMonth)) {
            $filterMonth = (int) $filterMonth;
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
        $filename = "Risk_Report_Complete_{$monthName}_{$filterYear}_".time().".xlsx";

        // Export menggunakan MultiSheetRiskExport (3 sheets dalam 1 file)
        try {
            return Excel::download(
                new MultiSheetRiskExport($headers, $monthName, $filterYear ?? date('Y')),
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

    /**
     * Method untuk preview data sebelum export
     */
    public function preview(Request $request, $id)
    {
        $filterYear  = $request->get('year');
        $filterMonth = $request->get('month');

        // Normalisasi filter bulan
        if (is_array($filterMonth) && isset($filterMonth['month'])) {
            $filterMonth = (int) $filterMonth['month'];
        } elseif (!is_null($filterMonth)) {
            $filterMonth = (int) $filterMonth;
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
                        'month_name' => $this->getMonthName($filterMonth)
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
