
<!DOCTYPE html>
<html>
<head>
    <title>Rencana Investasi - Export PDF</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

    <style>
      @page { size: A4 landscape; margin: 14mm 12mm 16mm 12mm; }
      * { box-sizing: border-box; }
      body { font-family: DejaVu Sans, sans-serif; font-size: 9px; margin:0; color:#111; }
      thead { display: table-header-group; } tfoot { display: table-footer-group; }
      tr { page-break-inside: avoid; page-break-after: auto; }

      .header { text-align:center; margin-bottom:10px; }
      .title { font-size:15px; font-weight:700; margin-bottom:2px; }
      .subtitle { font-size:12px; font-weight:600; margin-bottom:2px; }
      .info { font-size:10px; font-weight:600; }

      table { width:100%; border-collapse: collapse; table-layout: fixed; margin-top:8px; }
      th, td { border:0.7px solid #000; padding:4px 5px; font-size:8px; line-height:1.25; }
      th { background:#e9f1da; font-weight:700; }
      tbody tr:nth-child(odd) td { background:#fafafa; }

      .text-left { text-align:left; } .text-center { text-align:center; } .text-right { text-align:right; }

      .w-id{width:4%} .w-erkap{width:6%} .w-dept{width:12%} .w-nama{width:18%}
      .w-kat{width:10%} .w-jenis{width:9%} .w-th{width:5%}
      .w-amt{width:9%} .w-tl{width:8%} .w-st{width:6%} .w-ket{width:14%}

      .badge { display:inline-block; border-radius:50%; width:12px; height:12px; font-size:8px; line-height:12px; text-align:center; color:#fff; margin-left:3px; }
      .chip { display:inline-block; padding:1px 4px; border-radius:3px; border:0.7px solid #000; font-size:7.5px; }

      .footer { position: fixed; left:0; right:0; bottom:-6mm; height:10mm; font-size:9px; color:#444; }
      .footer .page:after { content: counter(page); }
      .footer .pages:after{ content: counter(pages); }
    </style>
</head>
<body>
  <div class="header">
      <div class="title">RENCANA INVESTASI</div>
      <div class="subtitle">PT. KAWASAN BERIKAT NUSANTARA</div>
      <div class="info">UNIT KERJA : {{ $departmentName ?? 'TIDAK DIKETAHUI' }}</div>
      <div class="info">PERIODE : BULAN {{ strtoupper($monthName) }} {{ $year }}</div>
  </div>

  <table>
    <thead>
      <tr>
        <th class="w-id">NO</th>
        <th class="w-erkap">ERKAP ID</th>
        <th class="w-dept">DEPARTMENT</th>
        <th class="w-nama">NAMA INVESTASI</th>
        <th class="w-kat">KATEGORI</th>
        <th class="w-jenis">JENIS</th>
        <th class="w-th">TAHUN</th>
        <th class="w-amt">NILAI RKAP</th>
        <th class="w-amt">NILAI REVISI</th>
        <th class="w-amt">BUDGET TRANSFER</th>
        <th class="w-amt">REALISASI</th>
        <th class="w-tl">TARGET TL</th>
        <th class="w-tl">REALISASI TL</th>
        <th class="w-st">STATUS</th>
        <th class="w-ket">KETERANGAN</th>
      </tr>
    </thead>
    <tbody>
      @php
        $num = 1;
        $fmt = fn($v) => ($v === null || $v === '') ? '' : number_format((float)$v, 2, '.', ',');

        function tlCellArr($label, $color) {
            if (!$label && !$color) return '';
            $dot = $color ? "<span class=\"badge\" style=\"background: {$color}\"></span>" : '';
            $safe = e($label ?? '');
            return "{$safe} {$dot}";
        }
      @endphp

      @foreach($rows as $row)
        @php
          // $row is an associative array produced in controller ($exportRows)
          $dept           = $row['Department']            ?? '';
          $erkapId        = $row['ERKAP ID']              ?? '';
          $nama           = $row['Nama Investasi']        ?? '';
          $kategori       = $row['Kategori Investasi']    ?? '';
          $jenis          = $row['Jenis Investasi']       ?? '';
          $tahunRow       = $row['Tahun']                 ?? '';
          $nilaiRkap      = $row['Nilai RKAP']            ?? null;
          $nilaiRevisi    = $row['Nilai Revisi']          ?? null;
          $budgetTransfer = $row['Budget Transfer']       ?? null;
          $realisasi      = $row['Realisasi']             ?? null;

          $targetLabel    = $row['Target Timeline Label']    ?? null;
          $targetColor    = $row['Target Timeline Color']    ?? null;
          $realLabel      = $row['Realisasi Timeline Label'] ?? null;
          $realColor      = $row['Realisasi Timeline Color'] ?? null;

          $status         = $row['Status']               ?? '-';
          $keterangan     = $row['Keterangan']           ?? '';
        @endphp

        <tr>
          <td class="text-center">{{ $num++ }}</td>
          <td class="text-center">{{ $erkapId }}</td>
          <td class="text-left">{{ $dept }}</td>
          <td class="text-left">{{ $nama }}</td>
          <td class="text-left">{{ $kategori }}</td>
          <td class="text-left">{{ $jenis }}</td>
          <td class="text-center">{{ $tahunRow }}</td>
          <td class="text-right">{{ $fmt($nilaiRkap) }}</td>
          <td class="text-right">{{ $fmt($nilaiRevisi) }}</td>
          <td class="text-right">{{ $fmt($budgetTransfer) }}</td>
          <td class="text-right">{{ $fmt($realisasi) }}</td>
          <td class="text-left">{!! tlCellArr($targetLabel, $targetColor) !!}</td>
          <td class="text-left">{!! tlCellArr($realLabel, $realColor) !!}</td>
          <td class="text-center"><span class="chip">{{ $status }}</span></td>
          <td class="text-left">{{ $keterangan }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <div class="footer">
    <table style="width:100%; border-collapse:collapse;">
      <tr>
        <td class="text-left">Generated: {{ \Carbon\Carbon::now()->format('d M Y H:i') }}</td>
        <td class="text-center">PT. KBN — Rencana Investasi</td>
        <td class="text-right">Hal. <span class="page"></span> / <span class="pages"></span></td>
      </tr>
    </table>
  </div>
</body>
</html>
