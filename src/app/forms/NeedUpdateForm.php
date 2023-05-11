<?php
namespace app\forms;

use gui\Ext4JphpWindows;
use std, gui, framework, app;


class NeedUpdateForm extends AbstractForm
{
    /**
     * @event buttonClose.action 
     */
    function doButtonCloseAction(UXEvent $e = null)
    {
        Animation::fadeOut($this, 200, function () {
            $this->free();
        });
    }

    /**
     * @event buttonRemind.action 
     */
    function doButtonRemindAction(UXEvent $e = null)
    {    
        Animation::fadeOut($this, 200, function () {
            $this->free();
        });
    }
}
