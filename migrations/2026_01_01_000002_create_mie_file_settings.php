<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('mie_file_storages', function (Blueprint $table) {
    $table->increments('id');
    $table->string('name', 64)->unique();
    $table->string('driver', 32);
    $table->boolean('enabled')->default(true);
    $table->string('endpoint')->nullable();
    $table->string('bucket')->nullable();
    $table->string('region')->nullable();
    $table->text('access_key_ciphertext')->nullable();
    $table->text('secret_key_ciphertext')->nullable();
    $table->string('public_base_url')->nullable();
    $table->boolean('direct_delivery_confirmed')->default(false);
    $table->timestamps();
});
