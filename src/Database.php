<?php

namespace App;

use Illuminate\Database\Capsule\Manager as Capsule;

class Database
{
    public static function setup()
    {
        $capsule = new Capsule;

        $capsule->addConnection([
            'driver' => env('DB_DRIVER'),
            'host' => env('DB_HOST'),
            'port' => env('DB_PORT'),
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'database' => env('DB_DATABASE'),
        ]);

        $capsule->setAsGlobal();

        $capsule->bootEloquent();
    }
}