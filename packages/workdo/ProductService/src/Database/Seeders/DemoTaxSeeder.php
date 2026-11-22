<?php

namespace Workdo\ProductService\Database\Seeders;

use Workdo\ProductService\Models\ProductServiceTax;
use Illuminate\Database\Seeder;

class DemoTaxSeeder extends Seeder
{
    public function run($userId): void
    {
        if (!empty($userId)) {
            $taxes = [
                ['tax_name' => 'VAT 15% (የተጨማሪ እሴት ታክስ)', 'rate' => 15.00],
                ['tax_name' => 'TOT Goods 2% (ተርን ኦቨር ታክስ - ዕቃዎች)', 'rate' => 2.00],
                ['tax_name' => 'TOT Services 10% (ተርን ኦቨር ታክስ - አገልግሎቶች)', 'rate' => 10.00],
                ['tax_name' => 'Withholding Tax 2% (የቀነሳ ታክስ)', 'rate' => 2.00],
                ['tax_name' => 'Withholding Tax 30% (ያለ TIN የቀነሳ ታክስ)', 'rate' => 30.00],
            ];

            foreach ($taxes as $tax) {
                ProductServiceTax::create([
                    'tax_name' => $tax['tax_name'],
                    'rate' => $tax['rate'],
                    'creator_id' => $userId,
                    'created_by' => $userId,
                ]);
            }
        }
    }
}
