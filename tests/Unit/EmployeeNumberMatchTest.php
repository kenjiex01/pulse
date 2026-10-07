<?php

namespace Tests\Unit;

use App\Support\EmployeeNumberMatch;
use Tests\TestCase;

class EmployeeNumberMatchTest extends TestCase
{
    public function test_normalizes_and_compares_employee_numbers(): void
    {
        $this->assertTrue(EmployeeNumberMatch::same('19-SHS250299', '19shs250299'));
        $this->assertFalse(EmployeeNumberMatch::same('8670', '19-SHS250299'));
    }

    public function test_splits_section_tokens(): void
    {
        $this->assertSame(['LFCA111A014', 'LFCA311E023'], EmployeeNumberMatch::splitSectionTokens('LFCA111A014 / LFCA311E023'));
    }
}
