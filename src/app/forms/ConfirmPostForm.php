<?php
namespace app\forms;

use std, gui, framework, app;


class ConfirmPostForm extends AbstractForm
{

    /**
     * @event checkbox.click-Left 
     */
    function doCheckboxClickLeft(UXMouseEvent $e = null)
    {    
        $this->form("MainForm")->checkbox->selected = !$e->sender->selected;
    }

}
