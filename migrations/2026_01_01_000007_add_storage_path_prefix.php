<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema): void {
        $schema->table('mie_file_storages', function (Blueprint $table): void {
            $table->string('path_prefix', 255)->nullable();
        });
        $schema->table('mie_file_storages', function (Blueprint $table): void {
            $table->dropColumn('region');
        });
    },

    'down' => function (Builder $schema): void {
        $schema->table('mie_file_storages', function (Blueprint $table): void {
            $table->string('region')->nullable();
        });
        $schema->table('mie_file_storages', function (Blueprint $table): void {
            $table->dropColumn('path_prefix');
        });
    },
];
