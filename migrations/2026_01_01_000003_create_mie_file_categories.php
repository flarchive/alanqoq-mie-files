<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('mie_file_categories', function (Blueprint $table) {
    $table->increments('id');
    $table->string('slug', 64)->unique();
    $table->string('name', 128);
    $table->string('permission_name', 64)->unique();
    $table->unsignedBigInteger('max_size');
    $table->string('storage_name', 64)->default('local');
    $table->string('insert_template', 32);
    $table->json('extensions');
    $table->json('mimes');
    $table->json('rules');
    $table->boolean('enabled')->default(true);
    $table->timestamps();
});
