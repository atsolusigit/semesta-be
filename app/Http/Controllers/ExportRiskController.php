<?php

namespace App\Http\Controllers;

use App\Models\TrRiskHeader;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\RiskExport;
use Maatwebsite\Excel\Facades\Excel;

class ExportRiskController extends Controller
{
    public function export($id, $format)
    {
        $allowedFormats = ['pdf', 'excel'];
        if (!in_array($format, $allowedFormats)) {
            return json(400, false, 'Format Tidak Didukung', 'Format export yang diminta tidak tersedia.', null);
        }

        try {
            $header = TrRiskHeader::with([
                'riskCode:id,name',
                'department:id,name',
                'optionTargetSatuTahun:id,name',
                'monthlyData' => function ($q) {
                    $q->orderBy('month', 'asc');
                },
            ])->find($id);

            if (!$header) {
                return json(404, false, 'Data Tidak Ditemukan', 'Header risiko dengan ID tersebut tidak ditemukan.', null);
            }

            $unixTime = time();

            $rawProjectName = $header->riskCode->name ?? 'project';
            $projectName = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $rawProjectName));

            if ($format === 'pdf') {
                $filename = "Risk_Report_{$projectName}_{$unixTime}.pdf";
                $pdf = Pdf::loadView('exports.risk_pdf', compact('header'))->setPaper('a4', 'landscape');
                return $pdf->download($filename);
            }

            if ($format === 'excel') {
                $filename = "Risk_Report_{$projectName}_{$unixTime}.xlsx";
                $export = new RiskExport($header);
                return Excel::download($export, $filename);
            }

        } catch (\Exception $e) {
            return json(500, false, 'Export Gagal', 'Terjadi kesalahan saat proses export file.', [
                'error' => $e->getMessage()
            ]);
        }
    }

    public function preview($id)
    {
        try {
            $header = TrRiskHeader::with([
                'riskCode:id,name',
                'department:id,name',
                'monthlyData' => function ($q) {
                    $q->orderBy('month', 'asc');
                }
            ])->find($id);

            if (!$header) {
                return json(404, false, 'Data Tidak Ditemukan', 'Header risiko tidak ditemukan.', null);
            }

            return json(200, true, 'Sukses', 'Data ditemukan.', [
                'header' => $header,
                'monthly_count' => $header->monthlyData->count()
            ]);
        } catch (\Exception $e) {
            return json(500, false, 'Preview Gagal', 'Terjadi kesalahan saat preview data.', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
