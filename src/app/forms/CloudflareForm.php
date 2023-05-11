<?php
namespace app\forms;

use std, gui, framework, app;


class CloudflareForm extends AbstractForm
{
    /**
     * @event labelHelp.click-Left 
     */
    function doLabelHelpClickLeft(UXMouseEvent $e = null)
    {    
        UXClipboard::setText($this->form("MainForm")->userAgentUrl);
        $this->form("MainForm")->showMessage("В буфер обмена помещена ссылка (" . $this->form("MainForm")->userAgentUrl . "), откройте её в браузере, в котором выполнен вход на форум и скопируйте User-Agent из текстового поля." . PHP_EOL .
        "Либо же просто загуглите: my user agent");
    }

    /**
     * @event useragent.mouseDown-Left 
     */
    function doUseragentMouseDownLeft(UXMouseEvent $e = null)
    {    
        $e->sender->selectAll();
    }

    /**
     * @event cookie.mouseDown-Left 
     */
    function doCookieMouseDownLeft(UXMouseEvent $e = null)
    {    
        $e->sender->selectAll();
    }

}
