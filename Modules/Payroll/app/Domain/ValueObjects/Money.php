<?php

namespace Modules\Payroll\Domain\ValueObjects;

use DomainException;

final readonly class Money
{
    public function __construct(public int $minor, public string $currency)
    {
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new DomainException('Currency code không hợp lệ.');
        }
    }

    public static function zero(string $currency): self
    {
        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        $this->same($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->same($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function multiplyBasisPoints(int $basisPoints): self
    {
        return new self(intdiv($this->minor * $basisPoints + 5000, 10000), $this->currency);
    }

    public function multiplyQuantity(int $quantityBasisPoints): self
    {
        return new self(intdiv($this->minor * $quantityBasisPoints + 5000, 10000), $this->currency);
    }

    /** @return array{amount_minor:int,currency_code:string} */
    public function toArray(): array
    {
        return ['amount_minor' => $this->minor, 'currency_code' => $this->currency];
    }

    private function same(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new DomainException('Không thể tính tiền khác currency.');
        }
    }
}
