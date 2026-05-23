<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Entity id 13 'Culinaria': BHGDIGITAL.CUSTOMER-CULIINARIA → BHGDIGITAL.CUSTOMER-CULINARIA
        DB::table('organization_entities')
            ->where('code', 'BHGDIGITAL.CUSTOMER-CULIINARIA')
            ->update(['code' => 'BHGDIGITAL.CUSTOMER-CULINARIA']);

        // Entity id 19 'EFP': BHFDIGITAL.CUSTOMER-EFP → BHGDIGITAL.CUSTOMER-EFP
        DB::table('organization_entities')
            ->where('code', 'BHFDIGITAL.CUSTOMER-EFP')
            ->update(['code' => 'BHGDIGITAL.CUSTOMER-EFP']);
    }

    public function down(): void
    {
        DB::table('organization_entities')
            ->where('code', 'BHGDIGITAL.CUSTOMER-CULINARIA')
            ->update(['code' => 'BHGDIGITAL.CUSTOMER-CULIINARIA']);

        DB::table('organization_entities')
            ->where('code', 'BHGDIGITAL.CUSTOMER-EFP')
            ->update(['code' => 'BHFDIGITAL.CUSTOMER-EFP']);
    }
};
