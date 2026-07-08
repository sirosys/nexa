<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_local_number_without_prefix_gets_country_code_prepended(): void
    {
        $this->assertSame('6281234567890', PhoneNumber::normalize('81234567890'));
    }

    public function test_leading_trunk_zero_is_replaced_with_country_code(): void
    {
        $this->assertSame('6281234567890', PhoneNumber::normalize('081234567890'));
    }

    public function test_already_normalized_number_is_unchanged(): void
    {
        $this->assertSame('6281234567890', PhoneNumber::normalize('6281234567890'));
    }

    public function test_non_digit_characters_are_stripped_before_normalizing(): void
    {
        $this->assertSame('6281234567890', PhoneNumber::normalize('+62 812-3456-7890'));
        $this->assertSame('6281234567890', PhoneNumber::normalize('0812 3456 7890'));
    }
}
