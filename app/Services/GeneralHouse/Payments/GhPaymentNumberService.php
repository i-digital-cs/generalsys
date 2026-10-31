<?php

namespace App\Services\GeneralHouse\Payments;

use Illuminate\Support\Facades\DB;

class GhPaymentNumberService
{
    public const SEQUENCE = 'payments';

    public const PREFIX = 'GH-PAY-';

    public function nextReference(): string
    {
        return DB::transaction(function () {
            $row = DB::table('gh_sequences')
                ->where('name', self::SEQUENCE)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::table('gh_sequences')->insert([
                    'name' => self::SEQUENCE,
                    'next_value' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $value = 1;
            } else {
                $value = (int) $row->next_value;
                DB::table('gh_sequences')->where('name', self::SEQUENCE)->update([
                    'next_value' => $value + 1,
                    'updated_at' => now(),
                ]);
            }

            return self::PREFIX.str_pad((string) $value, 6, '0', STR_PAD_LEFT);
        });
    }
}
