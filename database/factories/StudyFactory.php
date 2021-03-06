<?php

use Faker\Generator as Faker;

$factory->define(\App\Models\Study::class, function (Faker $faker) {
    return [
        'study_id' => $faker->unique->randomNumber(5),
        'name' => $faker->unique->word
    ];
});
