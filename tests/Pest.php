<?php

declare(strict_types=1);

use GeorgianTax\Tests\TestCase;

/*
| Tax tests pick their own case class from tests/Support (each extends TestCase).
| The Laravel integration tests use the base TestCase directly.
*/
pest()->extend(TestCase::class)->in('Laravel');
