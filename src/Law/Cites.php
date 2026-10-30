<?php

declare(strict_types=1);

namespace GeorgianTax\Law;

/** For classes carrying an #[Article] attribute. */
trait Cites
{
    public function citation(): Citation
    {
        return Citation::of($this);
    }
}
