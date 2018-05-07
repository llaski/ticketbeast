<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\Concert::class)->states('published')->create()->addTickets(10);

        factory(App\User::class)->create([
            'email' => 'larry.laski@gmail.com'
        ]);
    }
}
