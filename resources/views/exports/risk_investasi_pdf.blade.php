<!DOCTYPE html>
<html>
<head>
    <title>Risk Profile Investasi - Export PDF</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

    <style>
      @page { size: A4 landscape; margin: 14mm 12mm 16mm 12mm; }
      * { box-sizing: border-box; }
      body { font-family: DejaVu Sans, sans-serif; font-size: 9px; margin:0; color:#111; }
      thead { display: table-header-group; }
      tfoot { display: table-footer-group; }
      tr { page-break-inside: avoid; page-break-after: auto; }

      .header { text-align:center; margin-bottom:10px; }
      .title { font-size:15px; font-weight:700; margin-bottom:2px; }
      .subtitle { font-size:12px; font-weight:600; margin-bottom:2px; }
      .info { font-size:10px; font-weight:600; }

      table { width:100%; border-collapse: collapse; table-layout: fixed; margin-top:8px; }
      th, td { border:0.7px solid #000; padding:4px 5px; font-size:8px; line-height:1.25; }
      th { background:#e9f1da; font-weight:700; }
      tbody tr:nth-child(odd) td { background:#fafafa; }

      .text-left { text-align:left; }
      .text-center { text-align:center; }
      .text-right { text-align:right; }

      .w-no      { width:3% }
      .w-erkap   { width:5% }
      .w-dept    { width:10% }
      .w-nama    { width:13% }
      .w-kat-inv { width:8% }
      .w-jenis   { width:7% }
      .w-th      { width:4% }
      .w-kat-ris { width:8% }
      .w-subkat  { width:8% }
      .w-sasaran { width:10% }
      .w-peris   { width:12% }
      .w-peny    { width:12% }
      .w-dampak  { width:7% }
      .w-kem     { width:5% }
      .w-exp     { width:5% }
      .w-intext  { width:5% }
      .w-mit     { width:10% }
      .w-biaya   { width:7% }
      .w-status  { width:5% }

      .chip { display:inline-block; padding:1px 4px; border-radius:3px; border:0.7px solid #000; font-size:7.5px; }

      .footer { position: fixed; left:0; right:0; bottom:-6mm; height:10mm; font-size:9px; color:#444; }
      .footer .page:after { content: counter(page); }
      .footer .pages:after{ content: counter(pages); }
    </style>
</head>
<body>
  @php
    // Helper untuk memastikan semua value yang di-print adalah string / null
    if (!function_exists('risk_txt')) {
        function risk_txt($v) {
            if (is_array($v)) {
                return json_encode($v, JSON_UNESCAPED_UNICODE);
            }
            return $v ?? '';
        }
    }

    $fmtNum = function ($v) {
        if ($v === null || $v === '') return '';
        if (is_array($v)) {
            // Kalau entah kenapa numeric datang sebagai array, ambil first atau encode
            $v = reset($v);
        }
        return number_format((float)$v, 2, '.', ',');
    };
  @endphp

  <div class="header">
      <div class="title">RISK PROFILE INVESTASI</div>
      <div class="subtitle">PT. KAWASAN BERIKAT NUSANTARA</div>
      <div class="info">
        UNIT KERJA : {{ risk_txt($departmentName ?? 'SEMUA UNIT') }}
      </div>
      <div class="info">
        TAHUN : {{ risk_txt($year ?? 'SEMUA') }}
      </div>
  </div>

  <table>
    <thead>
      <tr>
        <th class="w-no">NO</th>
        <th class="w-erkap">ERKAP ID</th>
        <th class="w-dept">DEPARTMENT</th>
        <th class="w-nama">NAMA INVESTASI</th>
        <th class="w-kat-inv">KAT. INVESTASI</th>
        <th class="w-jenis">JENIS</th>
        <th class="w-th">TH</th>
        <th class="w-kat-ris">KAT. RISIKO</th>
        <th class="w-subkat">SUB KAT.</th>
        <th class="w-sasaran">SASARAN</th>
        <th class="w-peris">PERISTIWA RISIKO</th>
        <th class="w-peny">PENYEBAB RISIKO</th>
        <th class="w-dampak">D. INHERENT</th>
        <th class="w-dampak">D. AWAL</th>
        <th class="w-kem">KEM. AWAL</th>
        <th class="w-exp">EXP LVL AWAL</th>
        <th class="w-intext">INT/EXT</th>
        <th class="w-mit">MITIGASI</th>
        <th class="w-dampak">D. RESIDUAL</th>
        <th class="w-dampak">D. AKHIR</th>
        <th class="w-kem">KEM. AKHIR</th>
        <th class="w-exp">EXP LVL AKHIR</th>
        <th class="w-biaya">BIAYA MITIGASI</th>
        <th class="w-status">STATUS</th>
      </tr>
    </thead>
    <tbody>
      @php $num = 1; @endphp

      @foreach($rows as $row)
        @php
          $erkapId            = $row['ERKAP ID']               ?? '';
          $namaInvestasi      = $row['Nama Investasi']         ?? '';
          $dept               = $row['Department']             ?? '';
          $jenisInvestasi     = $row['Jenis Investasi']        ?? '';
          $kategoriInvestasi  = $row['Kategori Investasi']     ?? '';
          $tahunRow           = $row['Tahun']                  ?? '';

          $kategoriRisiko     = $row['Kategori Risiko']        ?? '';
          $subKategoriRisiko  = $row['Sub Kategori Risiko']    ?? '';
          $sasaran            = $row['Sasaran']                ?? '';
          $peristiwaRisiko    = $row['Peristiwa Risiko']       ?? '';
          $penyebabRisiko     = $row['Penyebab Risiko']        ?? '';

          $dampakInherent     = $row['Dampak Inherent']        ?? '';
          $dampakRisikoAwal   = $row['Dampak Risiko Awal']     ?? '';
          $kemungkinanAwal    = $row['Kemungkinan Awal']       ?? '';
          $expLevelAwal       = $row['Eksposure Level Awal']   ?? '';
          $internalExternal   = $row['Internal / External']    ?? '';
          $mitigasiRisiko     = $row['Mitigasi Risiko']        ?? '';

          $dampakResidual     = $row['Dampak Residual']        ?? '';
          $dampakRisikoAkhir  = $row['Dampak Risiko Akhir']    ?? '';
          $kemungkinanAkhir   = $row['Kemungkinan Akhir']      ?? '';
          $expLevelAkhir      = $row['Eksposure Level Akhir']  ?? '';
          $biayaMitigasi      = $row['Biaya Mitigasi Risiko']  ?? null;

          $status             = $row['Status']                 ?? '-';
        @endphp

        <tr>
          <td class="text-center">{{ $num++ }}</td>
          <td class="text-center">{{ risk_txt($erkapId) }}</td>
          <td class="text-left">{{ risk_txt($dept) }}</td>
          <td class="text-left">{{ risk_txt($namaInvestasi) }}</td>
          <td class="text-left">{{ risk_txt($kategoriInvestasi) }}</td>
          <td class="text-left">{{ risk_txt($jenisInvestasi) }}</td>
          <td class="text-center">{{ risk_txt($tahunRow) }}</td>

          <td class="text-left">{{ risk_txt($kategoriRisiko) }}</td>
          <td class="text-left">{{ risk_txt($subKategoriRisiko) }}</td>
          <td class="text-left">{{ risk_txt($sasaran) }}</td>
          <td class="text-left">{{ risk_txt($peristiwaRisiko) }}</td>
          <td class="text-left">{{ risk_txt($penyebabRisiko) }}</td>

          <td class="text-left">{{ risk_txt($dampakInherent) }}</td>
          <td class="text-left">{{ risk_txt($dampakRisikoAwal) }}</td>
          <td class="text-right">{{ risk_txt($kemungkinanAwal) }}</td>
          <td class="text-right">{{ risk_txt($expLevelAwal) }}</td>
          <td class="text-center">{{ risk_txt($internalExternal) }}</td>
          <td class="text-left">{{ risk_txt($mitigasiRisiko) }}</td>

          <td class="text-left">{{ risk_txt($dampakResidual) }}</td>
          <td class="text-left">{{ risk_txt($dampakRisikoAkhir) }}</td>
          <td class="text-right">{{ risk_txt($kemungkinanAkhir) }}</td>
          <td class="text-right">{{ risk_txt($expLevelAkhir) }}</td>
          <td class="text-right">{{ $fmtNum($biayaMitigasi) }}</td>

          <td class="text-center">
            <span class="chip">{{ risk_txt($status) }}</span>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <div class="footer">
    <table style="width:100%; border-collapse:collapse;">
      <tr>
        <td class="text-left">
          Generated: {{ \Carbon\Carbon::now()->format('d M Y H:i') }}
        </td>
        <td class="text-center">
          PT. KBN — Risk Profile Investasi
        </td>
        <td class="text-right">
          Hal. <span class="page"></span> / <span class="pages"></span>
        </td>
      </tr>
    </table>
  </div>
</body>
</html>
