<?php

namespace Tests\Unit;

use App\ValueObjects\User\Email;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailTest extends TestCase
{
    #[Test]
    public function accepts_valid_email(): void
    {
        $value = 'user@gmail.com';
        $email = new Email($value);

        $this->assertSame($value, $email->getValue());
    }

    #[Test]
    public function accepts_valid_email_with_subdomain(): void
    {
        $value = 'user@mail.google.com';
        $email = new Email($value);

        $this->assertSame($value, $email->getValue());
    }

    #[Test]
    public function throws_on_invalid_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email');

        new Email('not-an-email');
    }

    #[Test]
    public function throws_on_empty_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email');

        new Email('');
    }

    #[Test]
    public function throws_on_missing_at(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email');

        new Email('userexample.com');
    }
}
