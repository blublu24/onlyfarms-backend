<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\UnitConversion;

class UnitConversionDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        UnitConversion::truncate();

        $unitConversions = [
            // Kamatis (Tomato) → Sack, Small Sack, Kilograms
            ['vegetable_slug' => 'kamatis', 'unit' => 'sack', 'standard_weight_kg' => 15.0], // 11-20kg average
            ['vegetable_slug' => 'kamatis', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0], // Fixed 10kg
            ['vegetable_slug' => 'kamatis', 'unit' => 'kg', 'standard_weight_kg' => 1.0], // Variable 1-9kg
            ['vegetable_slug' => 'kamatis', 'unit' => 'piece', 'standard_weight_kg' => 0.125], // Individual piece

            // Sibuyas (Onion) → Sack, Small Sack, Kilograms, Packet
            ['vegetable_slug' => 'sibuyas', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'sibuyas', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'sibuyas', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'sibuyas', 'unit' => 'packet', 'standard_weight_kg' => 0.5], // Packet varies
            ['vegetable_slug' => 'sibuyas', 'unit' => 'piece', 'standard_weight_kg' => 0.110],

            // Bawang (Garlic) → Sack, Small Sack, Kilograms, Packet
            ['vegetable_slug' => 'bawang', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'bawang', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'bawang', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'bawang', 'unit' => 'packet', 'standard_weight_kg' => 0.250],

            // Kalabasa (Squash) → Sack, Small Sack, Kilograms, Pieces
            ['vegetable_slug' => 'kalabasa', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'kalabasa', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'kalabasa', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'kalabasa', 'unit' => 'piece', 'standard_weight_kg' => 2.500],

            // Ampalaya (Bitter gourd) → Sack, Small Sack, Kilograms, Pieces
            ['vegetable_slug' => 'ampalaya', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'ampalaya', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'ampalaya', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'ampalaya', 'unit' => 'piece', 'standard_weight_kg' => 0.250],

            // Talong (Eggplant) → Sack, Small Sack, Kilograms, Tali
            ['vegetable_slug' => 'talong', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'talong', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'talong', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'talong', 'unit' => 'tali', 'standard_weight_kg' => 0.300],

            // Okra → Sack, Small Sack, Kilograms, Tali
            ['vegetable_slug' => 'okra', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'okra', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'okra', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'okra', 'unit' => 'tali', 'standard_weight_kg' => 0.250],

            // Sitaw (String beans) → Sack, Small Sack, Kilograms, Tali
            ['vegetable_slug' => 'sitaw', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'sitaw', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'sitaw', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'sitaw', 'unit' => 'tali', 'standard_weight_kg' => 0.250],

            // Kangkong (Water spinach) → Sack, Small Sack, Kilograms, Tali
            ['vegetable_slug' => 'kangkong', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'kangkong', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'kangkong', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'kangkong', 'unit' => 'tali', 'standard_weight_kg' => 0.300],

            // Pechay → Sack, Small Sack, Kilograms, Tali
            ['vegetable_slug' => 'pechay', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'pechay', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'pechay', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'pechay', 'unit' => 'tali', 'standard_weight_kg' => 0.250],

            // Repolyo (Cabbage) → Sack, Small Sack, Kilograms, Pieces
            ['vegetable_slug' => 'repolyo', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'repolyo', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'repolyo', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'repolyo', 'unit' => 'piece', 'standard_weight_kg' => 1.000],

            // Carrots → Sack, Small Sack, Kilograms, Pieces
            ['vegetable_slug' => 'carrots', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'carrots', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'carrots', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'carrots', 'unit' => 'piece', 'standard_weight_kg' => 0.060], // Estimated

            // Sayote → Sack, Small Sack, Kilograms, Pieces
            ['vegetable_slug' => 'sayote', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'sayote', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'sayote', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'sayote', 'unit' => 'piece', 'standard_weight_kg' => 0.300],

            // Patatas (Potato) → Sack, Small Sack, Kilograms, Pieces
            ['vegetable_slug' => 'patatas', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'patatas', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'patatas', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'patatas', 'unit' => 'piece', 'standard_weight_kg' => 0.213],

            // Labanos (Radish) → Sack, Small Sack, Kilograms, Pieces
            ['vegetable_slug' => 'labanos', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'labanos', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'labanos', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'labanos', 'unit' => 'piece', 'standard_weight_kg' => 0.200],

            // Upo (Bottle gourd) → Sack, Small Sack, Kilograms, Pieces
            ['vegetable_slug' => 'upo', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'upo', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'upo', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'upo', 'unit' => 'piece', 'standard_weight_kg' => 1.500],

            // Luya (Ginger) → Sack, Small Sack, Kilograms, Packet
            ['vegetable_slug' => 'luya', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'luya', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'luya', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'luya', 'unit' => 'packet', 'standard_weight_kg' => 0.250],

            // Siling Green (Green chili) → Sack, Small Sack, Kilograms, Pieces
            ['vegetable_slug' => 'siling_green', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'siling_green', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'siling_green', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'siling_green', 'unit' => 'packet', 'standard_weight_kg' => 0.250],

            // Siling Red (Red chili) → Sack, Small Sack, Kilograms, Pieces
            ['vegetable_slug' => 'siling_red', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'siling_red', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'siling_red', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'siling_red', 'unit' => 'packet', 'standard_weight_kg' => 0.250],

            // Bell pepper → Sack, Small Sack, Kilograms, Pieces
            ['vegetable_slug' => 'bell_pepper', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'bell_pepper', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'bell_pepper', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'bell_pepper', 'unit' => 'piece', 'standard_weight_kg' => 0.120],

            // Lettuce → Sack, Small Sack, Kilograms, Tali
            ['vegetable_slug' => 'lettuce', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'lettuce', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'lettuce', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'lettuce', 'unit' => 'tali', 'standard_weight_kg' => 0.400],

            // Cucumber → Sack, Small Sack, Kilograms, Pieces
            ['vegetable_slug' => 'cucumber', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'cucumber', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'cucumber', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'cucumber', 'unit' => 'piece', 'standard_weight_kg' => 0.180],

            // Broccoli → Sack, Small Sack, Kilograms, Pieces
            ['vegetable_slug' => 'broccoli', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'broccoli', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'broccoli', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'broccoli', 'unit' => 'piece', 'standard_weight_kg' => 0.250],

            // Gabi (Taro) → Sack, Small Sack, Kilograms
            ['vegetable_slug' => 'gabi', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'gabi', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'gabi', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'gabi', 'unit' => 'piece', 'standard_weight_kg' => 0.400],

            // Talbos ng Kamote (Sweet potato tops) → Sack, Small Sack, Kilograms, Tali
            ['vegetable_slug' => 'talbos_ng_kamote', 'unit' => 'sack', 'standard_weight_kg' => 15.0],
            ['vegetable_slug' => 'talbos_ng_kamote', 'unit' => 'small_sack', 'standard_weight_kg' => 10.0],
            ['vegetable_slug' => 'talbos_ng_kamote', 'unit' => 'kg', 'standard_weight_kg' => 1.0],
            ['vegetable_slug' => 'talbos_ng_kamote', 'unit' => 'tali', 'standard_weight_kg' => 0.250],
        ];

        foreach ($unitConversions as $conversion) {
            UnitConversion::create($conversion);
        }

        $this->command->info('Unit conversion data seeded successfully!');
    }
}