<?php

namespace App\ValueObjects\User;

use Illuminate\Support\Facades\Validator;
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
            throw new \InvalidArgumentException("Invalid email: {$this->email}");
        }

    }

    public function getValue(): string
    {
        return $this->email;
    }
}
