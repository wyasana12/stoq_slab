<?php

namespace App\Http\Requests\PurchaseOrder;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrderItem;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateStatusPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'status' => ['required', Rule::in(array_column(PurchaseOrderStatus::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:255'],
        ];

        if ($this->input('status') === PurchaseOrderStatus::APPROVED->value) {
            $rules['items'] = ['required', 'array', 'min:1'];
            $rules['items.*.id'] = ['required', 'string', 'exists:purchase_order_items,id'];

            $rules['items.*.quantity_approved'] = [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) {
                    $index = explode('.', $attribute)[1];
                    $itemId = $this->input("items.{$index}.id");

                    if ($itemId) {
                        $item = PurchaseOrderItem::with('purchase')->find($itemId);

                        if ($item) {
                            if ($value > $item->quantity_ordered) {
                                $fail("Kuantitas yang disetujui tidak boleh melebihi pesanan (Maks: {$item->quantity_ordered}).");
                            }

                            $supplierItem = DB::table('product_supplier_items')
                                ->where('supplier_id', $item->purchase->supplier_id)
                                ->where('product_id', $item->product_id)
                                ->first();

                            $moq = $supplierItem ? $supplierItem->min_order_quantity : 1;

                            if ($value < $moq) {
                                $fail("Kuantitas yang disetujui tidak boleh kurang dari Minimum Order Quantity (MOQ: {$moq}). Jika ingin membatalkan pemesanan produk ini, silakan tolak (reject) item tersebut.");
                            }
                        }
                    }
                }
            ];

            $rules['rejected_item_ids'] = ['nullable', 'array'];
            $rules['rejected_item_ids.*'] = ['string', 'exists:purchase_order_items,id'];
        }

        if (in_array($this->input('status'), [
            PurchaseOrderStatus::REJECTED->value,
            PurchaseOrderStatus::CANCELLED->value,
        ])) {
            $rules['notes'] = ['required', 'string'];
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'status.required' => 'Purchase order status is required',
            'notes.max' => 'Purchase order notes must be less than 250.',
        ];
    }
}
