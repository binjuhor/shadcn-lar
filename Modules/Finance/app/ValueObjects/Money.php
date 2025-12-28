<?php

namespace Modules\Finance\ValueObjects;

use Brick\Money\Money as BrickMoney;

class Money
{
    private BrickMoney $money;

    public function __construct(int $amountInCents, string $currencyCode)
    {
        $this->money = BrickMoney::ofMinor($amountInCents, $currencyCode);
    }

    public static function fromDecimal(float|string $amount, string $currencyCode): self
    {
        $brickMoney = BrickMoney::of($amount, $currencyCode);

        return new self($brickMoney->getMinorAmount()->toInt(), $currencyCode);
    }

    public function getAmount(): int
    {
        return $this->money->getMinorAmount()->toInt();
    }

    public function getAmountDecimal(): string
    {
        return $this->money->getAmount()->__toString();
    }

    public function getCurrency(): string
    {
        return $this->money->getCurrency()->getCurrencyCode();
    }

    public function add(Money $other): self
    {
        $result = $this->money->plus($other->money);

        return new self($result->getMinorAmount()->toInt(), $this->getCurrency());
    }

    public function subtract(Money $other): self
    {
        $result = $this->money->minus($other->money);

        return new self($result->getMinorAmount()->toInt(), $this->getCurrency());
    }

    public function format(): string
    {
        return $this->money->formatTo('en_US');
    }
}
