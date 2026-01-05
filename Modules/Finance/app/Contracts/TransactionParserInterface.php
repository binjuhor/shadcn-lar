<?php

namespace Modules\Finance\Contracts;

use Illuminate\Http\UploadedFile;

interface TransactionParserInterface
{
    public function parseVoice(UploadedFile $audioFile, string $language = 'vi'): array;

    public function parseReceipt(UploadedFile $imageFile, string $language = 'vi'): array;

    public function parseText(string $text, string $language = 'vi'): array;

    public function matchCategory(string $hint, int $userId, string $type = 'expense'): ?array;

    public function matchAccount(string $hint, int $userId): ?array;
}
