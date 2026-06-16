<?php
return [
    "connection"=>env("DB_CONNECTION", "mysql"),
    "host"=>env("DB_HOST", "localhost"),
    "port"=>env("DB_PORT", "3306"),
    "database"=>env("DB_DATABASE", "chomsky"),
    "username"=>env("DB_USERNAME", "root"),
    "password"=>env("DB_PASSWORD", "")
];