<?php

namespace Tests\Unit;

use App\Http\Requests\StockAdjustmentRequest;
use App\Http\Requests\StockEntryRequest;
use App\Http\Requests\StockExitRequest;
use App\Http\Requests\StoreMaterialRequest;
use App\Http\Requests\UpdateMaterialRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class InventoryQuantityValidationTest extends TestCase
{
    public function test_quantities_respect_database_precision_and_limits(): void
    {
        foreach ([
            [new StoreMaterialRequest, 'stock_actual', '0'],
            [new StoreMaterialRequest, 'stock_minimo', '0'],
            [new UpdateMaterialRequest, 'stock_minimo', '0'],
            [new StockEntryRequest, 'cantidad', '0.001'],
            [new StockExitRequest, 'cantidad', '0.001'],
            [new StockAdjustmentRequest, 'nuevo_stock', '0'],
        ] as [$request, $field, $minimum]) {
            $rules = [$field => $request->rules()[$field]];
            foreach ([$minimum, '12.345', '999999999.999'] as $value) {
                $this->assertTrue(Validator::make([$field => $value], $rules)->passes(), "$field: $value");
            }
            foreach (['-1', '0.0001', '1000000000', 'texto'] as $value) {
                $this->assertTrue(Validator::make([$field => $value], $rules)->fails(), "$field: $value");
            }
        }
    }
}
