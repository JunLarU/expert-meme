<?php

use Whis\Database\DB;
use Whis\Database\Migrations\Migration;

return new class () implements Migration {
    public function up()
    {
        try {
            DB::statement('CREATE TABLE users (id INT(11) AUTO_INCREMENT PRIMARY KEY,name VARCHAR(255),email VARCHAR(255) UNIQUE,password VARCHAR(255),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)');
        } catch (\PDOException $th) {
            exit;
        }
        
    }

    public function down()
    {
        try {
            DB::statement('DROP TABLE users');
        } catch (\PDOException $th) {
            exit;
        }
        
    }
};
