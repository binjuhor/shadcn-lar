<?php

namespace Modules\Finance\Traits;

use Modules\Finance\Models\Category;
use Modules\Finance\Services\AccountDetectionService;

trait ParsesTransactionEntities
{
    protected function normalizeTransactionAmount(mixed $amount): float
    {
        if (is_numeric($amount)) {
            return round((float) $amount, 2);
        }

        $value = mb_strtolower(trim((string) $amount));

        if (preg_match('/([0-9]+(?:[.,][0-9]+)?)\s*(k|nghìn|nghin|tr|triệu|trieu|tỷ|ty)/iu', $value, $matches)) {
            $number = (float) str_replace(',', '.', $matches[1]);
            $multiplier = match ($matches[2]) {
                'k', 'nghìn', 'nghin' => 1_000,
                'tr', 'triệu', 'trieu' => 1_000_000,
                'tỷ', 'ty' => 1_000_000_000,
            };

            return round($number * $multiplier, 2);
        }

        $value = preg_replace('/[^0-9.,-]/', '', $value) ?? '';
        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
            $thousandsSeparator = $decimalSeparator === ',' ? '.' : ',';
            $value = str_replace($thousandsSeparator, '', $value);
            $value = str_replace($decimalSeparator, '.', $value);

            return round((float) $value, 2);
        }

        $separator = $lastComma !== false ? ',' : ($lastDot !== false ? '.' : null);
        if ($separator === null) {
            return round((float) $value, 2);
        }

        [$whole, $fraction] = array_pad(explode($separator, $value, 2), 2, '');
        $isThousandsSeparator = strlen($fraction) === 3 && (int) $whole !== 0;
        $value = $isThousandsSeparator
            ? $whole.$fraction
            : $whole.'.'.$fraction;

        return round((float) $value, 2);
    }

    public function matchCategory(string $hint, int $userId, string $type = 'expense'): ?array
    {
        $categories = Category::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhereNull('user_id');
        })
            ->where('is_active', true)
            ->where(function ($q) use ($type) {
                $q->where('type', $type)->orWhere('type', 'both');
            })
            ->get();

        $hintLower = mb_strtolower($hint);

        foreach ($categories as $category) {
            if (mb_strtolower($category->name) === $hintLower) {
                return ['id' => $category->id, 'name' => $category->name];
            }
        }

        foreach ($categories as $category) {
            if (str_contains(mb_strtolower($category->name), $hintLower) ||
                str_contains($hintLower, mb_strtolower($category->name))) {
                return ['id' => $category->id, 'name' => $category->name];
            }
        }

        $mappings = [
            'ăn' => ['food', 'ăn uống', 'thực phẩm'],
            'cafe' => ['food', 'ăn uống', 'coffee'],
            'xăng' => ['transport', 'di chuyển', 'transportation'],
            'điện' => ['utilities', 'tiện ích', 'điện nước'],
            'nước' => ['utilities', 'tiện ích', 'điện nước'],
            'lương' => ['salary', 'thu nhập', 'income'],
            'thuê' => ['rent', 'nhà ở', 'housing'],
            'mua sắm' => ['shopping', 'mua sắm'],
            'giải trí' => ['entertainment', 'giải trí'],
        ];

        foreach ($mappings as $keyword => $relatedTerms) {
            if (str_contains($hintLower, $keyword)) {
                foreach ($categories as $category) {
                    $catLower = mb_strtolower($category->name);
                    foreach ($relatedTerms as $term) {
                        if (str_contains($catLower, $term)) {
                            return ['id' => $category->id, 'name' => $category->name];
                        }
                    }
                }
            }
        }

        return null;
    }

    public function matchAccount(string $hint, int $userId): ?array
    {
        $match = app(AccountDetectionService::class)->matchHint($hint, $userId);

        return $match ? ['id' => $match['id'], 'name' => $match['name']] : null;
    }

    protected function getParsePrompt(string $language): string
    {
        $langInstructions = $language === 'vi'
            ? 'Input is in Vietnamese. Handle Vietnamese number shortcuts: k/nghìn=×1000, tr/triệu=×1000000.'
            : 'Input is in English. Preserve decimal amounts exactly.';

        return <<<PROMPT
You are a financial transaction parser. Extract transaction details from text input.

{$langInstructions}

Extract:
- type: "income", "expense", or "transfer" (default: expense). Use "transfer" when money moves between accounts (chuyển khoản, chuyển tiền, transfer, gửi tiền, nạp tiền vào tài khoản)
- amount: number in the currency written by the user; preserve decimals exactly (for example, $0.67 must be 0.67). Convert Vietnamese shortcuts to full numbers
- description: brief description of transaction
- category_hint: suggested category (food, transport, utilities, salary, shopping, entertainment, etc.) — omit for transfers
- account_hint: source account if mentioned — can be account name, bank name (Vietcombank, Techcombank, MoMo), account type (cash, card, bank), or last digits of account number (e.g. "1234" from "TK *1234" or "...1234")
- transfer_account_hint: destination account/bank if mentioned for transfers (e.g. "Vietcombank", "MoMo", "tiết kiệm", "savings", or last 4 digits)
- date_hint: relative date if mentioned (hôm nay, hôm qua, today, yesterday)
- confidence: 0.0 to 1.0 based on clarity of input

Vietnamese examples:
- "Ăn sáng 35 nghìn" → expense, 35000, breakfast, food
- "Lương tháng 15 triệu" → income, 15000000, monthly salary, salary
- "Đổ xăng 200k hôm qua" → expense, 200000, gas, transport, yesterday
- "Cafe 50k" → expense, 50000, coffee, food
- "Chuyển 5tr sang Vietcombank" → transfer, 5000000, transfer to Vietcombank, transfer_account_hint: "Vietcombank"
- "Chuyển khoản 2tr MoMo" → transfer, 2000000, transfer to MoMo, transfer_account_hint: "MoMo"
- "TK *1234 thanh toán 500k" → expense, 500000, payment, account_hint: "1234"
- "Thẻ Vietcombank ...5678 chi 200k" → expense, 200000, payment, account_hint: "5678"

Return ONLY valid JSON, no explanation:
{"type":"expense","amount":50000,"description":"Coffee","category_hint":"food","date_hint":"today","confidence":0.95}
PROMPT;
    }

    protected function getReceiptPrompt(string $language): string
    {
        $langInstructions = $language === 'vi'
            ? 'Receipt is likely in Vietnamese. Look for: Tổng cộng, Thành tiền, Total for amount. Ngày for date.'
            : 'Receipt is in English. Look for Total, Amount, Date fields.';

        return <<<PROMPT
You are a receipt/bill OCR parser. Extract transaction details from the image.

{$langInstructions}

Extract:
- type: "expense" (receipts are typically expenses)
- amount: total amount in the local currency
- description: store/vendor name or main item
- category_hint: category based on vendor type (restaurant=food, gas station=transport, etc.)
- account_hint: payment method if visible — card last 4 digits, bank name, or "cash"
- date_hint: transaction date if visible
- confidence: 0.0 to 1.0 based on image clarity and extraction certainty
- raw_text: key text extracted from receipt

Return ONLY valid JSON, no explanation:
{"type":"expense","amount":150000,"description":"Highland Coffee","category_hint":"food","date_hint":"2026-01-04","confidence":0.92,"raw_text":"Highland Coffee - Total: 150,000 VND"}
PROMPT;
    }

    protected function getTextWithImagePrompt(string $language, string $userText): string
    {
        $langInstructions = $language === 'vi'
            ? 'Input is in Vietnamese. Handle Vietnamese number shortcuts: k/nghìn=×1000, tr/triệu=×1000000. Receipt may be in Vietnamese.'
            : 'Input is in English.';

        return <<<PROMPT
You are a financial transaction parser. The user provides text context AND an image (receipt/bill). Use both to extract transaction details.

User says: "{$userText}"

{$langInstructions}

Extract:
- type: "income" or "expense" (default: expense)
- amount: number in local currency (convert shortcuts to full numbers)
- description: brief description combining text and image context
- category_hint: suggested category (food, transport, utilities, salary, shopping, entertainment, etc.)
- account_hint: payment method if mentioned or visible — account name, bank name, card last 4 digits, or type (cash, card, bank)
- date_hint: date if visible or mentioned
- confidence: 0.0 to 1.0 based on clarity of input
- raw_text: key text extracted from the image

Return ONLY valid JSON, no explanation:
{"type":"expense","amount":150000,"description":"Highland Coffee","category_hint":"food","date_hint":"2026-01-04","confidence":0.92,"raw_text":"Highland Coffee - Total: 150,000 VND"}
PROMPT;
    }
}
