<?php

namespace App\Services\GeneralHouse\Payments;

use Illuminate\Support\Facades\DB;

final class GhDriverCashNumberService
{
    public function nextCollectionReference(): string
    {
        return $this->next('driver_cash_collections', 'GH-DCC-');
    }

    public function nextSettlementReference(): string
    {
        return $this->next('driver_cash_settlements', 'GH-DCS-');
    }

    private function next(string $sequence, string $prefix): string
    {
        return DB::transaction(function () use ($sequence, $prefix) {
            $row = DB::table('gh_sequences')
                ->where('name', $sequence)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::table('gh_sequences')->insert([
                    'name' => $sequence,
                    'next_value' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $value = 1;
            } else {
                $value = (int) $row->next_value;
                DB::table('gh_sequences')->where('name', $sequence)->update([
                    'next_value' => $value + 1,
                    'updated_at' => now(),
                ]);
            }

            return $prefix.str_pad((string) $value, 6, '0', STR_PAD_LEFT);
        });
    }
}
