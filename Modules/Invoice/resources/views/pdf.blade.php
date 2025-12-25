<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
  <style>
    body {
      font-family: sans-serif;
      font-size: 12px;
      color: #333;
      line-height: 1.4;
    }

    .header {
      margin-bottom: 30px;
      border-bottom: 2px solid #333;
      padding-bottom: 20px;
    }

    .invoice-number {
      font-size: 28px;
      font-weight: bold;
      color: #1a1a1a;
      margin-bottom: 10px;
    }

    .meta-info {
      color: #666;
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
      background: #f9f9f9;
      border-radius: 4px;
    }

    .address-label {
      font-weight: bold;
      color: #666;
      text-transform: uppercase;
      font-size: 10px;
      margin-bottom: 8px;
    }

    .address-name {
      font-size: 14px;
      font-weight: bold;
      margin-bottom: 5px;
    }

    .items-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 30px;
    }

    .items-table th {
      background: #f5f5f5;
      padding: 12px 8px;
      text-align: left;
      font-weight: bold;
      border-bottom: 2px solid #ddd;
    }

    .items-table td {
      padding: 12px 8px;
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
      font-size: 16px;
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

    .status-badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: bold;
      text-transform: uppercase;
    }

    .status-draft {
      background: #e5e5e5;
      color: #666;
    }

    .status-sent {
      background: #dbeafe;
      color: #1d4ed8;
    }

    .status-paid {
      background: #dcfce7;
      color: #166534;
    }

    .status-overdue {
      background: #fee2e2;
      color: #dc2626;
    }

    .status-cancelled {
      background: #f3f4f6;
      color: #6b7280;
    }
  </style>
</head>
<body>
<div class="addresses">
  <table>
    <tr>
      <td style="padding-right: 20px;">
        <div class="address-block">
          <div class="address-label">From</div>
          <div class="address-name">{{ $invoice->from_name }}</div>
          @if($invoice->from_address)
            {!! nl2br(e($invoice->from_address)) !!}<br>
          @endif
          @if($invoice->from_email)
            {{ $invoice->from_email }}<br>
          @endif
          @if($invoice->from_phone)
            {{ $invoice->from_phone }}
          @endif
        </div>``
        <div class="address-block">
          <div class="address-label">Bill To</div>
          <div class="address-name">{{ $invoice->to_name }}</div>
          @if($invoice->to_address)
            {!! nl2br(e($invoice->to_address)) !!}<br>
          @endif
          @if($invoice->to_email)
            {{ $invoice->to_email }}
          @endif
        </div>
      </td>
      <td style="padding-left: 20px;">
        <div class="invoice-meta">
          <strong>{{ $invoice->invoice_number }}</strong><br>
          Date: {{ $invoice->invoice_date->format('M d, Y') }}<br>
          Due: {{ $invoice->due_date->format('M d, Y') }}<br>
        </div>
      </td>
    </tr>
  </table>
</div>

<table class="items-table">
  <thead>
  <tr>
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
    <tr>
      <td class="label">Tax ({{ $invoice->tax_rate * 100 }}%):</td>
      <td class="value">${{ number_format($invoice->tax_amount, 2) }}</td>
    </tr>
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
