<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class PrepareDiscordLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'link_email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'link_password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'link_email.required' => 'Enter the email for the account you want to link to Discord.',
            'link_password.required' => 'Enter your password to continue.',
        ];
    }
}
