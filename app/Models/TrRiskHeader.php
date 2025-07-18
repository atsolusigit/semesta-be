<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class TrRiskHeader extends Model
    {
        protected $table = 'tr_risk_header';

        protected $fillable = [
            'risk_code',
            'process_code',
            'prefix_risiko',
            'sasaran',
            'permasalahan_risiko',
            'dampak',
            'dampak_risiko',
            'ir_level_dampak',
            'ir_level_kemungkinan',
            'ir_posisi_risiko',
            'ir_level_risiko',
            'internal_control',
            'target_satu_tahun',
            'target_satu_tahun_option',
            'target_satu_tahun_other',
            'target_satu_tahun_notes',
            'target_satu_tahun_position',
            'biaya_pertolongan_risiko',
            'rr_level_dampak',
            'rr_level_kemungkinan',
            'rr_posisi_risiko',
            'rr_level_risiko',
            'department_id',
            'year',
        ];

        public function riskCode()
        {
            return $this->belongsTo(\App\Models\MstRiskCode::class, 'risk_code', 'id');
        }

        public function irDampak()
        {
            return $this->belongsTo(\App\Models\MstHeatmapDampak::class, 'ir_level_dampak');
        }

        public function irKemungkinan()
        {
            return $this->belongsTo(\App\Models\MstHeatmapKemungkinan::class, 'ir_level_kemungkinan');
        }

        public function rrDampak()
        {
            return $this->belongsTo(\App\Models\MstHeatmapDampak::class, 'rr_level_dampak');
        }

        public function rrKemungkinan()
        {
            return $this->belongsTo(\App\Models\MstHeatmapKemungkinan::class, 'rr_level_kemungkinan');
        }

        public function department()
        {
            return $this->belongsTo(\App\Models\MstDepartment::class, 'department_id');
        }

        public function optionWaktuSelesai()
        {
            return $this->belongsTo(\App\Models\MstOption::class, 'target_waktu_selesai_option', 'name');
        }

        public function optionWaktuSelesaiPosition()
        {
            return $this->belongsTo(\App\Models\MstOption::class, 'target_waktu_selesai_position', 'name');
        }
        public function riskRange()
        {
            return $this->belongsTo(MstHeatmapRiskRange::class, 'risk_range_id');
        }

    }
