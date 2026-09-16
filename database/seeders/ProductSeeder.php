<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        SaleItem::query()->delete();
        Sale::query()->delete();
        Product::query()->delete();
        Schema::enableForeignKeyConstraints();

        $medicines = $this->buildMedicineCatalog(2000);

        foreach (array_chunk($medicines, 250) as $chunk) {
            Product::query()->insert($chunk);
        }
    }

    /**
     * @return list<array{name: string, sku: string, price: float, stock: int, category: string, is_active: bool, created_at: \Carbon\Carbon, updated_at: \Carbon\Carbon}>
     */
    private function buildMedicineCatalog(int $targetCount): array
    {
        $now = now();

        $bases = [
            'Paracetamol', 'Ibuprofen', 'Aspirin', 'Diclofenac', 'Naproxen', 'Mefenamic Acid',
            'Amoxicillin', 'Augmentin', 'Azithromycin', 'Ciprofloxacin', 'Metronidazole', 'Cephalexin',
            'Doxycycline', 'Clarithromycin', 'Levofloxacin', 'Cefixime', 'Cefuroxime', 'Erythromycin',
            'Cetirizine', 'Loratadine', 'Fexofenadine', 'Desloratadine', 'Chlorpheniramine', 'Diphenhydramine',
            'Omeprazole', 'Esomeprazole', 'Pantoprazole', 'Rabeprazole', 'Ranitidine', 'Famotidine',
            'Metformin', 'Gliclazide', 'Glimepiride', 'Sitagliptin', 'Empagliflozin', 'Insulin Regular',
            'Amlodipine', 'Atenolol', 'Bisoprolol', 'Losartan', 'Valsartan', 'Ramipril',
            'Atorvastatin', 'Rosuvastatin', 'Simvastatin', 'Fenofibrate', 'Ezetimibe', 'Clopidogrel',
            'Aspirin Cardio', 'Warfarin', 'Rivaroxaban', 'Enoxaparin', 'Digoxin', 'Spironolactone',
            'Furosemide', 'Hydrochlorothiazide', 'Indapamide', 'Nifedipine', 'Diltiazem', 'Verapamil',
            'Salbutamol', 'Montelukast', 'Budesonide', 'Fluticasone', 'Theophylline', 'Ipratropium',
            'Prednisolone', 'Dexamethasone', 'Hydrocortisone', 'Betamethasone', 'Methylprednisolone', 'Triamcinolone',
            'Vitamin C', 'Vitamin D3', 'Vitamin B Complex', 'Folic Acid', 'Ferrous Sulfate', 'Calcium Carbonate',
            'Zinc Sulfate', 'Multivitamin', 'Omega-3', 'Magnesium', 'Biotin', 'Cod Liver Oil',
            'ORS', 'Loperamide', 'Domperidone', 'Metoclopramide', 'Hyoscine Butylbromide', 'Mebeverine',
            'Lactulose', 'Bisacodyl', 'Ispaghula Husk', 'Simethicone', 'Activated Charcoal', 'Oral Rehydration',
            'Miconazole', 'Clotrimazole', 'Terbinafine', 'Ketoconazole', 'Fluconazole', 'Nystatin',
            'Fusidic Acid', 'Mupirocin', 'Povidone Iodine', 'Chlorhexidine', 'Silver Sulfadiazine', 'Calamine',
            'Acyclovir', 'Valacyclovir', 'Oseltamivir', 'Albendazole', 'Mebendazole', 'Ivermectin',
            'Tramadol', 'Codeine', 'Morphine Sulfate', 'Gabapentin', 'Pregabalin', 'Carbamazepine',
            'Sodium Valproate', 'Levetiracetam', 'Phenytoin', 'Sertraline', 'Escitalopram', 'Fluoxetine',
            'Alprazolam', 'Diazepam', 'Clonazepam', 'Zolpidem', 'Quetiapine', 'Risperidone',
            'Levothyroxine', 'Carbimazole', 'Allopurinol', 'Colchicine', 'Methotrexate', 'Hydroxychloroquine',
            'Sildenafil', 'Tadalafil', 'Tamsulosin', 'Finasteride', 'Dutasteride', 'Sildenafil Citrate',
            'Eye Lubricant', 'Ciprofloxacin Eye', 'Tobramycin Eye', 'Chloramphenicol Eye', 'Ear Wax Softener', 'Nasal Spray',
            'Cough Suppressant', 'Expectorant Syrup', 'Antihistamine Syrup', 'Antipyretic Syrup', 'Antacid Suspension', 'Antiemetic Syrup',
            'Surgical Spirit', 'Hydrogen Peroxide', 'Normal Saline', 'Glucose Solution', 'Lidocaine Gel', 'Antiseptic Cream',
            'Cotton Wool', 'Gauze Pad', 'Crepe Bandage', 'Elastic Bandage', 'Adhesive Tape', 'Wound Dressing',
            'Disposable Syringe', 'Insulin Syringe', 'Face Mask', 'Examination Gloves', 'Alcohol Swabs', 'Thermometer Digital',
            'BP Monitor Cuff', 'Glucometer Strip', 'Pregnancy Test', 'Pulse Oximeter', 'Nebulizer Mask', 'Hot Water Bottle',
            'Panadol', 'Brufen', 'Risek', 'Flagyl', 'Amoxil', 'Augmentin Duo',
            'Ventolin', 'Clarityn', 'Gaviscon', 'Buscopan', 'Ponstan', 'Napa',
            'Disprin', 'Ascard', 'Concor', 'Glucophage', 'Diamicron', 'Softovac',
            'Betadine', 'Polyfax', 'Canesten', 'Daktarin', 'Strepsils', 'Sine-Cod',
            'Tixylix', 'Cofcol', 'Calpol', 'Septran', 'Ciproxin', 'Azomax',
        ];

        $forms = [
            'Tablets', 'Capsules', 'Syrup', 'Suspension', 'Injection', 'Cream',
            'Ointment', 'Gel', 'Drops', 'Inhaler', 'Sachets', 'Powder',
            'Spray', 'Suppository', 'Patch', 'Solution', 'Lotion', 'Elixir',
        ];

        $strengths = [
            '5mg', '10mg', '20mg', '25mg', '40mg', '50mg', '75mg', '100mg',
            '125mg', '200mg', '250mg', '400mg', '500mg', '625mg', '750mg', '1000mg',
            '1%', '2%', '5%', '10%', '120mg/5ml', '250mg/5ml', '100mcg', '200mcg',
        ];

        $packs = [
            '6s', '10s', '12s', '14s', '16s', '20s', '24s', '28s', '30s', '50s',
            '60ml', '100ml', '120ml', '150ml', '200ml', '15g', '20g', '30g', '50g', 'Box',
        ];

        $categories = [
            'Analgesics', 'Antibiotics', 'Antihistamines', 'Gastrointestinal', 'Cardiac',
            'Diabetes', 'Respiratory', 'Vitamins', 'Supplements', 'First Aid',
            'Dermatology', 'Eye Care', 'Ear Care', 'Cough & Cold', 'Syrups',
            'Surgical', 'Hygiene', 'Devices', 'Diagnostics', 'Hormonal',
            'Neurology', 'Psychiatric', 'Antifungal', 'Antiviral', 'Antiparasitic',
        ];

        $items = [];
        $usedNames = [];
        $index = 1;

        while (count($items) < $targetCount) {
            $base = $bases[($index - 1) % count($bases)];
            $form = $forms[($index * 3) % count($forms)];
            $strength = $strengths[($index * 7) % count($strengths)];
            $pack = $packs[($index * 11) % count($packs)];
            $category = $categories[($index * 5) % count($categories)];
            $variant = intdiv($index - 1, count($bases)) + 1;

            $name = sprintf('%s %s %s (%s)', $base, $strength, $form, $pack);
            if ($variant > 1) {
                $name .= ' V'.$variant;
            }

            // Ensure uniqueness if combinatorial collision occurs.
            if (isset($usedNames[$name])) {
                $name .= ' #'.$index;
            }
            $usedNames[$name] = true;

            $sku = sprintf('MED-%05d', $index);
            $price = round(25 + (($index * 37) % 4975) + (($index % 17) * 0.25), 2);
            $stock = 10 + (($index * 13) % 490);

            $items[] = [
                'name' => $name,
                'sku' => $sku,
                'price' => $price,
                'stock' => $stock,
                'category' => $category,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $index++;
        }

        return $items;
    }
}
