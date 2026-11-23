<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Invoice {{ $invoice->invoice_number }}</title>
<style>
    @page { margin: 40pt 44pt 50pt 44pt; }
    body { font-family: Helvetica, sans-serif; font-size: 9.5pt; color: #1f2933; }
    table { border-collapse: collapse; width: 100%; }
    .header td { vertical-align: top; }
    .seal { width: 60pt; height: 60pt; }
    .issuer { font-size: 8.5pt; line-height: 12pt; color: #334155; }
    .issuer b { font-size: 10pt; color: #013366; }
    .doc-title { text-align: right; }
    .doc-title h1 { margin: 0; font-size: 22pt; letter-spacing: 1pt; color: #013366; }
    .doc-title p { margin: 2pt 0 0; font-size: 10pt; color: #334155; }
    .rule { border-top: 3pt solid #fcba19; margin: 12pt 0 14pt; }
    .meta td { vertical-align: top; width: 50%; }
    .label { font-size: 7.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5pt; color: #64748b; margin-bottom: 3pt; }
    .facts td { padding: 2pt 0; }
    .facts td.k { color: #64748b; width: 95pt; }
    .facts td.v { font-weight: bold; text-align: right; }
    .items { margin-top: 18pt; }
    .items th { background: #013366; color: #fff; font-size: 8.5pt; text-align: left; padding: 6pt; }
    .items th.num { text-align: right; }
    .items td { padding: 6pt; border-bottom: 0.5pt solid #cbd5e1; }
    .num { text-align: right; white-space: nowrap; }
    .totals { margin-top: 8pt; width: 45%; margin-left: 55%; }
    .totals td { padding: 3pt 6pt; }
    .totals tr.due td { border-top: 1pt solid #013366; font-weight: bold; font-size: 11pt; color: #013366; }
    .status { display: inline-block; padding: 2pt 8pt; border-radius: 3pt; font-weight: bold; font-size: 9pt; }
    .status-paid { background: #dcfce7; color: #166534; }
    .status-open { background: #fef3c7; color: #92400e; }
    .status-other { background: #e2e8f0; color: #334155; }
    .box { margin-top: 22pt; border: 0.5pt solid #cbd5e1; background: #f8fafc; padding: 10pt 12pt; font-size: 9pt; line-height: 13pt; }
    .box h2 { margin: 0 0 4pt; font-size: 10pt; color: #013366; }
    .box p { margin: 0 0 4pt; }
    .note { margin-top: 10pt; font-size: 8.5pt; color: #475569; white-space: pre-line; }
    .footer { position: fixed; bottom: -30pt; left: 0; right: 0; font-size: 7.5pt; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
<div class="footer">{!! $text['footer'] !!}</div>

<table class="header">
    <tr>
        <td style="width: 70pt;"><img class="seal" src="{{ $seal }}" alt="British Columbia EQA seal"></td>
        <td class="issuer">
            @foreach ($issuer as $i => $line)
                @if ($i === 0)<b>{{ $line }}</b>@else{{ $line }}@endif<br>
            @endforeach
            {{ $issuerEmail }}
        </td>
        <td class="doc-title">
            <h1>{!! $text['title'] !!}</h1>
            <p>{{ $title }}</p>
        </td>
    </tr>
</table>

<div class="rule"></div>

<table class="meta">
    <tr>
        <td>
            <div class="label">Bill to</div>
            @foreach ($billTo as $line)
                {{ $line }}<br>
            @endforeach
        </td>
        <td>
            <table class="facts">
                <tr><td class="k">Invoice number</td><td class="v">{{ $invoice->invoice_number ?: '—' }}</td></tr>
                <tr><td class="k">Invoice date</td><td class="v">{{ $date($invoice->invoice_date) }}</td></tr>
                @if (! empty($invoice->due_date))
                    <tr><td class="k">Due date</td><td class="v">{{ $date($invoice->due_date) }}</td></tr>
                @endif
                @if (! empty($invoice->application_reference))
                    <tr><td class="k">Application</td><td class="v">{{ $invoice->application_reference }}</td></tr>
                @endif
                <tr>
                    <td class="k">Status</td>
                    <td class="v">
                        <span class="status {{ $invoice->invoice_status === 'Paid' ? 'status-paid' : ($invoice->invoice_status === 'Unpaid' ? 'status-open' : 'status-other') }}">{{ $invoice->invoice_status ?: '—' }}</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th>Description</th>
            <th class="num" style="width: 45pt;">Qty</th>
            <th class="num" style="width: 75pt;">Unit price</th>
            <th class="num" style="width: 80pt;">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lines as $line)
            <tr>
                <td>{{ $line['label'] }}</td>
                <td class="num">{{ $line['quantity'] }}</td>
                <td class="num">{{ $money($line['unit_price']) }}</td>
                <td class="num">{{ $money($line['amount']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>Subtotal</td><td class="num">{{ $money($invoice->invoice_amount ?? $invoice->total_charges) }}</td></tr>
    <tr><td>Taxes</td><td class="num">{{ $money($invoice->taxes) }}</td></tr>
    <tr><td>Total</td><td class="num">{{ $money($invoice->total_charges) }}</td></tr>
    @if ($paid > 0)
        <tr><td>Payments received</td><td class="num">−{{ $money($paid) }}</td></tr>
    @endif
    <tr class="due"><td>Balance due (CAD)</td><td class="num">{{ $money($balance) }}</td></tr>
</table>

@if (empty($invoice->line_items) && ! empty($invoice->description))
    <p class="note">{{ $invoice->description }}</p>
@endif

@if ($invoice->invoice_status === 'Paid')
    <div class="box">
        <h2>{!! $text['paid_message'] !!}</h2>
        <p>
            Payment received{{ ! empty($invoice->payment_received_date) ? ' on '.$date($invoice->payment_received_date) : '' }}{{ ! empty($invoice->payment_method) ? ' by '.$invoice->payment_method : '' }}{{ ! empty($invoice->transaction_reference) ? ' (reference '.$invoice->transaction_reference.')' : '' }}.
        </p>
    </div>
@elseif ($balance > 0 && $invoice->invoice_status === 'Unpaid')
    <div class="box">
        <h2>How to pay</h2>
        <p>{!! $text['payment_online'] !!}</p>
        <p>{!! $text['payment_offline'] !!}</p>
        @if (! empty($invoice->due_date))
            <p><b>{!! $text['due_text'] !!}</b></p>
        @endif
    </div>
@endif

@if ($text['notes'] !== '')
    <p class="note">{!! $text['notes'] !!}</p>
@endif
</body>
</html>
