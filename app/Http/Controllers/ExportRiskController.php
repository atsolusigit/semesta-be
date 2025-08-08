<?php

namespace App\Http\Controllers;

use App\Models\TrRiskHeader;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\RiskExport;
use Maatwebsite\Excel\Facades\Excel;

class ExportRiskController extends Controller
{
    public function export($format, Request $request = null)
    {
        $request = $request ?? request();

        $allowedFormats = ['pdf', 'excel'];

        if (!in_array(strtolower($format), $allowedFormats)) {
            return response()->json([
                'status' => 400,
                'success' => false,
                'message' => 'Format Tidak Didukung',
                'data' => 'Format export yang diminta tidak tersedia.'
            ], 400);
        }

        // Ambil filter tahun & bulan dari request
        $filterYear  = $request->get('year');
        $filterMonth = $request->get('month');

        try {
            $headers = TrRiskHeader::with([
                'riskCode:id,name',
                'department:id,name',
                'optionTargetSatuTahun:id,name',
                'monthlyData' => function ($q) use ($filterYear, $filterMonth) {
                    $q->select('*')
                      ->orderBy('month', 'asc');

                    if (!empty($filterYear)) {
                        $q->whereYear('start_date', $filterYear);
                    }

                    if (!empty($filterMonth)) {
                        $q->where('month', $filterMonth);
                    }
                },
            ])
            // Filter header berdasarkan monthlyData yang ada atau tampilkan semua jika tidak ada filter
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

            if ($headers->isEmpty()) {
                return response()->json([
                    'status' => 404,
                    'success' => false,
                    'message' => 'Data Tidak Ditemukan',
                    'data' => 'Data risiko untuk filter tersebut tidak ditemukan.'
                ], 404);
            }

            $unixTime = time();

            // Untuk nama file
            $fileIdentifier = '';
            if (!empty($filterYear) && !empty($filterMonth)) {
                $monthNames = [
                    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
                    5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
                    9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
                ];
                $monthName = $monthNames[$filterMonth] ?? 'Unknown';
                $fileIdentifier = "{$monthName}_{$filterYear}";
            } elseif (!empty($filterYear)) {
                $fileIdentifier = "Tahun_{$filterYear}";
            } else {
                $fileIdentifier = 'Semua_Data';
            }

            if ($format === 'pdf') {
                $filename = "Risk_Register_{$fileIdentifier}_{$unixTime}.pdf";

                // Siapkan data untuk PDF view
                $data = [
                    'headers' => $headers,
                    'monthName' => $this->getMonthName($filterMonth),
                    'year' => $filterYear ?? date('Y'),
                    'filterMonth' => $filterMonth,
                    'filterYear' => $filterYear
                ];

                $pdf = Pdf::loadView('exports.risk_pdf', $data)
                    ->setPaper('a3', 'landscape') // Gunakan A3 untuk layout yang lebih luas
                    ->setOptions([
                        'isHtml5ParserEnabled' => true,
                        'isPhpEnabled' => true,
                        'defaultFont' => 'Arial'
                    ]);

                return $pdf->download($filename);
            }

            if ($format === 'excel') {
                $filename = "Risk_Register_{$fileIdentifier}_{$unixTime}.xlsx";

                // Ambil nama bulan untuk export
                $monthName = $this->getMonthName($filterMonth);

                return Excel::download(
                    new RiskExport($headers, $monthName, $filterYear ?? date('Y')),
                    $filename,
                    \Maatwebsite\Excel\Excel::XLSX,
                    [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                        'Cache-Control' => 'max-age=0',
                    ]
                );
            }

        } catch (\Exception $e) {
            \Log::error('Export Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'format' => $format,
                'filters' => ['year' => $filterYear, 'month' => $filterMonth]
            ]);

            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Export Gagal',
                'data' => [
                    'error' => config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan sistem.',
                    'line' => config('app.debug') ? $e->getLine() : null,
                    'file' => config('app.debug') ? $e->getFile() : null
                ]
            ], 500);
        }
    }

    private function getMonthName($month)
    {
        if (empty($month)) {
            return 'Semua Bulan';
        }

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
            4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        return $monthNames[$month] ?? 'Bulan Tidak Valid';
    }

    /**
     * Method untuk preview data sebelum export (opsional)
     */
    public function preview(Request $request)
    {
        $filterYear  = $request->get('year');
        $filterMonth = $request->get('month');

        try {
            $headers = TrRiskHeader::with([
                'riskCode:id,name',
                'department:id,name',
                'monthlyData' => function ($q) use ($filterYear, $filterMonth) {
                    if (!empty($filterYear)) {
                        $q->whereYear('start_date', $filterYear);
                    }
                    if (!empty($filterMonth)) {
                        $q->where('month', $filterMonth);
                    }
                },
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
            ->limit(5) // Preview hanya 5 data pertama
            ->get();

            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Preview data berhasil',
                'data' => [
                    'total_records' => $headers->count(),
                    'preview_data' => $headers,
                    'filters' => [
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
