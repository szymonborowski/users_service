<?php

namespace App\ValueObjects\User;

use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\AssertionFailedError;

final class Email
{
    /**
     * @param string $email
     */
    public function __construct(private readonly string $email) {

        $validator = Validator::make(
            ['email' => $this->email],
            ['email' => 'required|email:rfc,dns']
        );

        if ($validator->fails()) {
            throw new AssertionFailedError('Invalid email');
        }

    }

    public function getValue(): string
    {
        return $this->email;
    }
}
