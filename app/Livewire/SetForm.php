<?php

namespace App\Livewire;

use App\Jobs\GeneratePDFJob;
use App\Models\Field;
use App\Models\Set;
use Livewire\Component;

class SetForm extends Component
{
    public Set $set;

    public array $columns;

    public string $previewLink;

    protected $rules = [
        'set.name'                   => 'required',
        'set.type'                   => 'required',
        'set.columnName'             => 'nullable',
        'set.limit'                  => 'nullable|numeric',
        'set.incremental'            => 'nullable|numeric',
        'set.header_width'           => 'nullable|numeric',
        'set.header_font'            => 'nullable|numeric',
        'set.settings.columns'       => 'nullable',
        'set.settings.differentPage' => 'nullable',
        'set.settings.fragile'       => 'nullable|boolean',
        'set.settings.filter'        => 'nullable',
    ];

    protected $listeners = [
        'refreshSet'  => 'refresh',
        'hidePreview' => 'hide',
    ];

    public function updated($name, $value)
    {
        if ($name == 'set.limit' && empty($value)) {
            $this->set->limit = null;
        }
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

    public function generatePDF()
    {
        GeneratePDFJob::dispatch($this->set);
        $this->dispatch('showSuccess', 'PDF generation added to queue, you should receive email shortly!');
    }

    public function hide()
    {
        $this->previewLink = false;
    }

    public function previewPDF()
    {
        // $this->previewLink = route('labels.preview', ['set' => $this->set->id]);
        $this->dispatch('openLink', route('labels.preview', ['set' => $this->set->id]));
    }

    public function openWebPage()
    {
        $this->dispatch('openLink', route('labels.generate', ['set' => $this->set->id]));
    }

    public function destroy()
    {
        $this->set->delete();
        $this->dispatch('setDeleted');
    }

    public function addField()
    {
        $field = new Field();
        $field->name = 'Field '.$this->set->fields->count() + 1;
        $field->display_name = $field->name;
        $field->type = 'Text';
        $field->settings = [
            'font' => 'Roboto',
            'type' => 'Regular',
            'size' => '15',
        ];
        $field->sequence = $this->set->fields()->count() + 1;
        $this->set->fields()->save($field);
        $this->refresh();
    }

    public function refresh()
    {
        $this->set->refresh();
    }
}
