<?php

namespace App\Entity;

use Symfony\Component\Validatior\Constraints as Assert;

class EmailCode {
    #[Assert\NotBlank]
    private ?string $code = null;

    public function getCode(): string {
        return $this->code;
    }

    public function setCode(string $code): static {
        $this->code = $code;
        return $this;
    }
}