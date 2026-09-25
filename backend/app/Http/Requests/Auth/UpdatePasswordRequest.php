<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'A jelenlegi jelszó megadása kötelező.',
            'password.required' => 'Az új jelszó megadása kötelező.',
            'password.min' => 'A jelszónak legalább 8 karakternek kell lennie.',
            'password.confirmed' => 'A két jelszó nem egyezik.',
        ];
    }
}
