<?php

namespace App\Models;

use \PDO;




class DB
{

    

    public function connect()
    {
        $envPath =dirname(__DIR__, 2);
  $dotenv = (\Dotenv\Dotenv::createImmutable($envPath))->load();
   // const $dotenv->load();
     $host = $_ENV['DB_HOST'];
     $user = $_ENV['DB_USER'];
     $pass = $_ENV['DB_PASSWORD'];
     $dbname = $_ENV['DB_NAME'];
        $conn_str = "mysql:host=$host;dbname=$dbname";
        $conn = new PDO($conn_str, $user, $pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $conn;
    }
}
