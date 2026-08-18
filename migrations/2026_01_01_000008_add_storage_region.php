<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema): void {
        if ($schema->hasColumn('mie_file_storages', 'region')) {
            return;
        }

        $schema->table('mie_file_storages', function (Blueprint $table): void {
            $table->string('region', 64)->nullable();
        });
    },

    'down' => function (Builder $schema): void {
        if (!$schema->hasColumn('mie_file_storages', 'region')) {
            return;
        }

        $schema->table('mie_file_storages', function (Blueprint $table): void {
            $table->dropColumn('region');
        });
    },
];
