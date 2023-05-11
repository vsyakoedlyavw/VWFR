<?php
namespace app\forms;

use std, gui, framework, app;


class MessageForm extends AbstractForm
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
     * @event buttonOk.action 
     */
    function doButtonOkAction(UXEvent $e = null)
    {    
        $this->free();
    }

    /**
     * @event keyUp-Esc 
     */
    function doKeyUpEsc(UXKeyEvent $e = null)
    {    
        $this->free();
    }

}
