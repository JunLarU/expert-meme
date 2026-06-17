<?php

use Whis\Database\DB;
use Whis\Database\Migrations\Migration;

return new class () implements Migration {
    public function up()
    {
        DB::statement('CREATE TABLE novedadess (id INT AUTO_INCREMENT PRIMARY KEY)');
        
    }

    public function down()
    {
        DB::statement('DROP TABLE novedadess');
        
    }
};
