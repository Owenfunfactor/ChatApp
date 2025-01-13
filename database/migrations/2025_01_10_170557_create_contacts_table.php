<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        DB::connection('mongodb')->collection('contacts')->create();
    }

    public function down()
    {
        DB::connection('mongodb')->collection('contacts')->drop();
    }
};
