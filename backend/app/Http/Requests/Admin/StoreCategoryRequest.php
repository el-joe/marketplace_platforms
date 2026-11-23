<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesCategoryForm;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
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
        return $this->categoryFormRules(null);
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return $this->categoryFormAfter(null);
    }
}
