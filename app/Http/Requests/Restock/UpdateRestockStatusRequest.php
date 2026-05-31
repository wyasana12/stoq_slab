<?php

namespace App\Http\Requests\Restock;

use App\Enums\RestockStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateRestockStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Sesuaikan dengan policy authorization jika diperlukan
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(RestockStatus::class)],
            'products' => ['sometimes', 'array', 'min:1'],
            'products.*.id' => ['required_with:products', 'exists:products,id'],
            'products.*.approved_quantity' => ['required_with:products', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status is required',
            'status.Illuminate\Validation\Rules\Enum' => 'Invalid status provided',
        ];
    }

    /**
     * Get the status as an Enum instance
     *
     * @return RestockStatus
     */
    public function getStatus(): RestockStatus
    {
        $validated = $this->validated();

        return RestockStatus::from($validated['status'] ?? '');
    }

    /**
     * Get validated product approval data for restock confirmation.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProducts(): array
    {
        $validated = $this->validated();

        return isset($validated['products']) && is_array($validated['products'])
            ? $validated['products']
            : [];
    }
}
