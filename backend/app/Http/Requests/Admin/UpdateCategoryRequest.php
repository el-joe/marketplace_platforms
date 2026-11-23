<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesCategoryForm;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    use ValidatesCategoryForm;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->categoryFormRules((string) $this->route('category'));
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return $this->categoryFormAfter((string) $this->route('category'));
    }
}
