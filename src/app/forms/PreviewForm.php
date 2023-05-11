<?php
namespace app\forms;

use gui\Ext4JphpWindows;
use std, gui, framework, app;


class PreviewForm extends AbstractForm
{

    /**
     * @event buttonLoad.action 
     */
    function doButtonLoadAction(UXEvent $e = null)
    {
        $this->form("MainForm")->loadTable($this->numberField->value, $this->sortType->selectedIndex);
    }

    /**
     * @event sortType.action 
     */
    function doSortTypeAction(UXEvent $e = null)
    {
        $this->form("MainForm")->loadTable($this->numberField->value, $this->sortType->selectedIndex);
    }

    /**
     * @event cbState.action 
     */
    function doCbStateAction(UXEvent $e = null)
    {
        foreach ($this->table->items as $item) $item["checkbox"]->children[0]->selected = $this->cbState->selectedIndex;
    }

}
