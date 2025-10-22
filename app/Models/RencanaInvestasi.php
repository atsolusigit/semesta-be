<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RencanaInvestasi extends Model
{
   protected $table = 'rencana_investasi';

    protected $fillable = [
        'erkap_id',
        'department_name',
        'nama_investasi',
        'kategori_investasi',
        'jenis_investasi',       
        'sasaran',  
        'year',
        'nilai_rkap',
        'nilai_revisi',
        'keterangan',
        'status',
        'catatan_svp_unit',      
        'catatan_svp_menrisk', 
        'unit_kerja_id',
        'updated_at',
        'created_at',
        'created_by',
        'updated_by'
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')
                    ->select(['id', 'name', 'username']);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function detail()
    {
        return $this->hasOne(RencanaInvestasiDetail::class, 'rencana_investasi_id');
    }

}
