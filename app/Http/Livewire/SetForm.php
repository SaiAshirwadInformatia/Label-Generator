<?php

namespace App\Http\Livewire;

use App\Jobs\GeneratePDFJob;
use App\Models\Field;
use App\Models\Set;
use Livewire\Component;
use Log;

class SetForm extends Component
{
    public Set $set;
    public array $columns;

    protected $rules = [
        'set.name' => 'required',
        'set.type' => 'required',
        'set.columnName' => 'nullable'
    ];

    protected $listeners = [
        'fieldDeleted' => 'refresh'
    ];

    public function updated()
    {
        $this->set->save();
    }

    public function render()
    {
        $columns = ['NotSelected' => ''];
        foreach ($this->set->label->settings['columns'] ?? [] as $column) {
            $columns[] = $column;
        }
        $this->columns = $columns;

        return view('livewire.set-form');
    }

    public function generateExcel()
    {
        GeneratePDFJob::dispatch($this->set);
        $this->emit('showSuccess', 'PDF generation added to queue, you should receive email shortly!');
    }

    public function destroy()
    {
        $this->set->delete();
        $this->emit('setDeleted');
    }

    public function addField()
    {
        $field = new Field();
        $field->name = 'Field ' . $this->set->fields->count() + 1;
        $field->display_name = $field->name;
        $field->type = 'Text';
        $field->settings = [
            'font' => 'Roboto',
            'type' => 'Regular',
            'size' => '12'
        ];
        $this->set->fields()->save($field);
        $this->refresh();
    }

    public function refresh()
    {
        $this->set->refresh();
    }
}
