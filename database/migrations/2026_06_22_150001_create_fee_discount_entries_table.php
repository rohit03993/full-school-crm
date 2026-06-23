<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_discount_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('fee_structure_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('total_after', 12, 2);
            $table->string('reason')->nullable();
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['fee_structure_id', 'created_at']);
            $table->index(['admission_id', 'created_at']);
        });

        $now = now();

        $admissions = DB::table('admissions')
            ->where('discount_amount', '>', 0)
            ->get(['id', 'discount_amount', 'discount_set_by_user_id', 'created_at']);

        foreach ($admissions as $admission) {
            DB::table('fee_discount_entries')->insert([
                'admission_id' => $admission->id,
                'fee_structure_id' => null,
                'amount' => $admission->discount_amount,
                'total_after' => $admission->discount_amount,
                'reason' => 'Opening discount (migrated)',
                'granted_by_user_id' => $admission->discount_set_by_user_id,
                'created_at' => $admission->created_at ?? $now,
                'updated_at' => $admission->created_at ?? $now,
            ]);
        }

        $feeStructures = DB::table('fee_structures as fs')
            ->join('enrollments as e', 'e.id', '=', 'fs.enrollment_id')
            ->where('fs.discount_amount', '>', 0)
            ->select([
                'fs.id as fee_structure_id',
                'fs.discount_amount',
                'fs.discount_set_by_user_id',
                'fs.created_at',
                'e.admission_id',
            ])
            ->get();

        foreach ($feeStructures as $feeStructure) {
            if (
                $feeStructure->admission_id
                && DB::table('fee_discount_entries')->where('admission_id', $feeStructure->admission_id)->exists()
            ) {
                DB::table('fee_discount_entries')
                    ->where('admission_id', $feeStructure->admission_id)
                    ->update(['fee_structure_id' => $feeStructure->fee_structure_id]);

                continue;
            }

            DB::table('fee_discount_entries')->insert([
                'admission_id' => $feeStructure->admission_id,
                'fee_structure_id' => $feeStructure->fee_structure_id,
                'amount' => $feeStructure->discount_amount,
                'total_after' => $feeStructure->discount_amount,
                'reason' => 'Opening discount (migrated)',
                'granted_by_user_id' => $feeStructure->discount_set_by_user_id,
                'created_at' => $feeStructure->created_at ?? $now,
                'updated_at' => $feeStructure->created_at ?? $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_discount_entries');
    }
};
