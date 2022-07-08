<?php

namespace Pterodactyl\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorized(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user' => 'required_unless:token|string',
            'password' => 'required_unless:token|string',
        ];
    }
}
