<?php

use App\Models\Set;
use Illuminate\Database\Migrations\Migration;

class FixExistingSequenceFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $sets = Set::all();
        foreach ($sets as $set) {
            $sequence = 1;
            foreach ($set->fields as $field) {
                $field->sequence = $sequence++;
                $field->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
