<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class IdeHelper extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (app()->isLocal()) {
            \Illuminate\Support\Facades\Artisan::call('ide-helper:generate', [
                '-n',
            ]);

            \Illuminate\Support\Facades\Artisan::call(
                '
            ide-helper:models -n
                --dir=app/Models
                --dir=Modules/News/Models
                --dir=Modules/Calendar/Models
                --dir=Modules/EmpCovidVac/Models
            '
            );
        }
    }
}
