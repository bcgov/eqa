<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Registry of BC EQA-Designated Institutions</title>
<style>
    @page { margin: 99pt 41pt 40pt 38pt; }
    body { font-family: Helvetica, sans-serif; font-size: 8pt; color: #000; }
    .page-header { position: fixed; top: -74pt; left: 0; right: 0; height: 60pt; }
    .page-header img { position: absolute; left: 0; top: 0; width: 56pt; height: 56pt; }
    .page-header .title { position: absolute; left: 93pt; top: 21pt; font-size: 15pt; }
    table { width: 533pt; border-collapse: collapse; }
    th, td { border: 0.5pt solid #000; padding: 4pt 5pt; text-align: left; }
    th { background: #dfdfdf; font-weight: bold; vertical-align: top; height: 17pt; line-height: 8pt; padding-top: 6pt; padding-bottom: 2pt; }
    td { vertical-align: middle; line-height: 9pt; }
    tr { page-break-inside: avoid; }
    .num { padding-left: 2pt; padding-right: 2pt; }
    .website { vertical-align: top; }
    .address { line-height: 8pt; }
    .website { line-height: 12pt; }
    .website a { color: #0000ff; text-decoration: none; }
    .date { text-align: center; }
    .section { page-break-before: always; }
    .section h2 { font-size: 12pt; font-weight: normal; margin: 0 0 8pt 0; }
    .empty { text-align: center; color: #555; padding: 10pt; }
</style>
</head>
<body>
<div class="page-header">
    <img src="{{ $seal }}" alt="British Columbia EQA seal">
    <div class="title">Registry of BC EQA-Designated Institutions</div>
</div>

@include('reports.partials.eqa-registry-table', ['rows' => $designated, 'dateLabel' => 'Designation Date'])

<div class="section">
    <h2>Institutions No Longer EQA-Designated</h2>
    @include('reports.partials.eqa-registry-table', ['rows' => $noLongerDesignated, 'dateLabel' => 'De-Designation Date'])
</div>
</body>
</html>
