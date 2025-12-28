<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
  <style>
    body {
      font-family: sans-serif;
      font-size: 14px;
      color: #333;
      line-height: 1.4;
    }

    strong {
      color: #999;
    }

    .addresses {
      margin-bottom: 30px;
    }

    .addresses table {
      width: 100%;
      border: none;
    }

    .addresses td {
      width: 50%;
      vertical-align: top;
      padding: 0;
      border: none;
    }

    .address-block {
      padding: 15px;
      border-radius: 4px;
    }

    .address-label {
      color: #666;
      margin-bottom: 8px;
      margin-top: 20px;
    }

    .address-name {
      font-weight: bold;
    }

    .items-table,
    .meta-table {
      width: 100%;
      border-collapse: collapse;
      border-spacing: 0;
      margin-bottom: 30px;
    }

    .items-table thead th {
      background: #151313;
      border-color: #151313;
    }

    .items-table thead th:first-child{
      border-radius: 4px 0 0 4px;
    }

    .items-table thead th:last-child{
      border-radius: 0 4px 4px 0 ;
    }

    .items-table th {
      padding: 5px 20px;
      text-align: left;
      color: #fff;
      font-weight: normal;
    }

    .items-table td {
      padding: 5px 20px;
      border-bottom: 1px solid #eee;
    }

    .items-table .text-right {
      text-align: right;
    }

    .totals {
      width: 300px;
      margin-left: auto;
    }

    .totals table {
      width: 100%;
      border: none;
    }

    .totals td {
      padding: 8px 0;
      border: none;
    }

    .totals .label {
      text-align: right;
      padding-right: 20px;
      color: #666;
    }

    .totals .value {
      text-align: right;
      font-weight: bold;
    }

    .totals .total-row {
      border-top: 2px solid #333;
    }

    .totals .total-row td {
      padding-top: 12px;
    }

    .notes {
      margin-top: 40px;
      padding: 15px;
      background: #f9f9f9;
      border-radius: 4px;
    }

    .notes-label {
      font-weight: bold;
      margin-bottom: 8px;
      color: #666;
    }

    .meta-table {
      text-align: right;
    }

    .meta-table td {
      padding: 5px 15px;
    }

    .meta-table tr:last-child {
      font-weight: bold;
      background: #f5f5f5;
      border-color: #f5f5f5;
      padding: 5px 15px;
      border-radius: 4px;
    }
    .meta-table tr:last-child td:first-child {
      border-radius: 4px 0 0 4px;
    }

    .meta-table tr:last-child td:last-child {
      border-radius: 0 4px 4px 0;
    }
  </style>
</head>
<body>
<div class="addresses">
  <table>
    <tr>
      <td style="width: 60%;">
        <div class="address-block">
          <div class="address-name">Full name: {{ $invoice->from_name }}</div>
          @if($invoice->from_address)
            Address: {!! nl2br(e($invoice->from_address)) !!}<br>
          @endif
          @if($invoice->from_email)
            {{ $invoice->from_email }}<br>
          @endif
          @if($invoice->from_phone)
            {{ $invoice->from_phone }}
          @endif
          <div class="address-label">Bill To</div>
          <div class="address-name">{{ $invoice->to_name }}</div>
          @if($invoice->to_address)
            {!! nl2br(e($invoice->to_address)) !!}<br>
          @endif
          @if($invoice->to_email)
            Email: {{ $invoice->to_email }}<br>
          @endif
        </div>
      </td>
      <td style="padding-left: 20px; text-align: right; line-height: 1.2">
        <div style="margin-bottom: 50px;">
          <h1 style="font-size: 40px; font-weight: normal; line-height: 1;">INVOICE</h1>
          <strong style="color: #5c5c5c">{{ $invoice->invoice_number }}</strong><br>
        </div>
        <table class="meta-table">
          <tr>
            <td>Date: </td>
            <td>{{ $invoice->invoice_date->format('M d, Y') }}</td>
          </tr>
          <tr>
            <td>Due Date: </td>
            <td>{{ $invoice->due_date->format('M d, Y') }}</td>
          </tr>
          <tr>
            <td>Balance Due:</td>
            <td>${{ number_format($invoice->total, 2) }}</td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</div>

<table class="items-table">
  <thead>
    <tr style="color: #fff">
      <th style="width: 50%;">Description</th>
      <th style="width: 15%;" class="text-right">Qty</th>
      <th style="width: 15%;" class="text-right">Unit Price</th>
      <th style="width: 20%;" class="text-right">Amount</th>
    </tr>
  </thead>
  <tbody>
  @foreach($invoice->items as $item)
    <tr>
      <td>{{ $item->description }}</td>
      <td class="text-right">{{ number_format($item->quantity, 2) }}</td>
      <td class="text-right">${{ number_format($item->unit_price, 2) }}</td>
      <td class="text-right">${{ number_format($item->amount, 2) }}</td>
    </tr>
  @endforeach
  </tbody>
</table>

<div class="totals">
  <table>
    <tr>
      <td class="label">Subtotal:</td>
      <td class="value">${{ number_format($invoice->subtotal, 2) }}</td>
    </tr>
    @if($invoice->tax_rate > 0)
    <tr>
      <td class="label">Tax ({{ $invoice->tax_rate * 100 }}%):</td>
      <td class="value">${{ number_format($invoice->tax_amount, 2) }}</td>
    </tr>
    @endif
    <tr class="total-row">
      <td class="label">Total:</td>
      <td class="value">${{ number_format($invoice->total, 2) }}</td>
    </tr>
  </table>
</div>

@if($invoice->notes)
  <div class="notes">
    <div class="notes-label">Notes</div>
    {!! nl2br(e($invoice->notes)) !!}
  </div>
@endif
</body>
</html>
