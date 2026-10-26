<?php
require __DIR__.'/config.php';
$faker=Faker\Factory::create('en_US');$faker->seed(2026);
(new Lab\CustomerSeeder((new Lab\Database())->connect(),$faker))->seed();
echo "Customer generation complete; existing records preserved. Run php inspect.php for counts.\n";
