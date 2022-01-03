<?php

namespace App\Http\Livewire;

use App\Events\LabelCreated;
use App\Models\Label;
use Livewire\Component;
use Livewire\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 *
 * @property TemporaryUploadedFile $path
 */
class LabelCreate extends Component
{
    use WithFileUploads;

    /**
     * @var mixed
     */
    public $name;
    /**
     * @var mixed
     */
    public $path;

    public $size, $orientation, $numbers, $column_nos;

    /**
     * @var array
     */
    protected $rules = [
        'name' => 'required',
        'path' => 'mimes:csv,xls,xlsx',
        'size' => 'required',
        'orientation' => 'required',
        'numbers' => 'required',
        'column_nos' => 'required'
    ];

    public function updatedPath()
    {
        $this->validate([
            'path' => 'mimes:csv,xls,xlsx',
        ]);
        $this->name = $this->path->getClientOriginalName();
    }

    public function submitForm()
    {
        $this->validate();

        $label           = new Label();
        $label->name     = $this->name;
        $label->path     = $this->path->store('/uploads', 'public');
        $label->settings = [
            'size' => $this->size,
            'orientation' => $this->orientation,
            'numbers' => $this->numbers,
            'column_nos' => $this->column_nos
        ];
        auth()->user()->labels()->save($label);

        event(new LabelCreated($label));

        return redirect()->route('labels.configure', [
            'label' => $label->id,
        ]);
    }

    public function render()
    {
        return view('livewire.label-create', [
            'pageOptions'      => array_merge(config('sai.pageOptions')),
            'pageOrientations' => array_merge(config('sai.pageOrientations')),
        ]);
    }
}
