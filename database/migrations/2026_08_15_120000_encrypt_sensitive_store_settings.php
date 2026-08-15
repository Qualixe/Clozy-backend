<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Third-party credentials (bKash/Steadfast/Pathao/SMS/Anthropic) were being
 * stored in plaintext. Encrypts whatever's currently in those columns and
 * marks them `encrypted` on the model (see StoreSetting) so future reads/
 * writes go through Laravel's transparent encrypt/decrypt automatically.
 * Written against DB::table() rather than the Eloquent model, since the
 * model's new `encrypted` casts would otherwise try (and fail) to decrypt
 * the still-plaintext values the moment this runs.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'sms_api_key',
        'steadfast_api_key',
        'steadfast_secret_key',
        'pathao_client_secret',
        'pathao_password',
        'pathao_access_token',
        'pathao_refresh_token',
        'bkash_app_secret',
        'bkash_password',
        'bkash_id_token',
        'bkash_refresh_token',
        'anthropic_api_key',
    ];

    public function up(): void
    {
        $row = DB::table('store_settings')->first();
        if (! $row) {
            return;
        }

        $updates = [];
        foreach (self::COLUMNS as $column) {
            $value = $row->{$column} ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            // Already encrypted (e.g. migration re-run) — leave it alone.
            try {
                Crypt::decryptString($value);
                continue;
            } catch (\Throwable) {
                // Not decryptable, so it's plaintext — encrypt it below.
            }

            $updates[$column] = Crypt::encryptString($value);
        }

        if ($updates !== []) {
            DB::table('store_settings')->where('id', $row->id)->update($updates);
        }
    }

    public function down(): void
    {
        $row = DB::table('store_settings')->first();
        if (! $row) {
            return;
        }

        $updates = [];
        foreach (self::COLUMNS as $column) {
            $value = $row->{$column} ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            try {
                $updates[$column] = Crypt::decryptString($value);
            } catch (\Throwable) {
                // Already plaintext — nothing to revert.
            }
        }

        if ($updates !== []) {
            DB::table('store_settings')->where('id', $row->id)->update($updates);
        }
    }
};
