<?php

namespace App\Http\Requests\Contact;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'A név megadása kötelező.',
            'email.required' => 'Az email megadása kötelező.',
            'email.email' => 'Érvényes email címet adj meg.',
            'message.required' => 'Az üzenet megadása kötelező.',
            'message.min' => 'Az üzenet legalább 10 karakter legyen.',
            'message.max' => 'Az üzenet legfeljebb 5000 karakter lehet.',
        ];
    }
}
