<?php

namespace Tests\Unit;

use App\Support\EmailAddressList;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailAddressListTest extends TestCase
{
    #[Test]
    public function it_parses_comma_separated_addresses(): void
    {
        $this->assertSame([
            'hr@company.com',
            'manager@company.com',
        ], EmailAddressList::parse(' hr@company.com , manager@company.com '));
    }

    #[Test]
    public function it_rejects_invalid_addresses(): void
    {
        $this->expectException(ValidationException::class);

        EmailAddressList::parseValidated('good@company.com, bad-address');
    }
}
