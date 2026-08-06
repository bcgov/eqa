<?php

namespace Modules\Web\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManageUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'users' => ['nullable', 'array'],
            'users.*.id' => ['nullable', 'integer'],
            'users.*.name' => ['nullable', 'string', 'max:255'],
            'users.*.email' => ['nullable', 'email', 'max:255'],
            'users.*.role' => ['nullable', 'string', 'max:255'],
            'selected_user_id' => ['nullable', 'integer'],
        ];
    }
}