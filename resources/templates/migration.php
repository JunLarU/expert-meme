<?php

use Whis\Database\DB;
use Whis\Database\Migrations\Migration;

return new class () implements Migration {
    public function up()
    {
        DB::statement('$UP');
        
    }

    public function down()
    {
        DB::statement('$DOWN');
        
    }
};
