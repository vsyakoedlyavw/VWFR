<?php
namespace app\forms;

use gui\Ext4JphpWindows;
use httpclient, std, gui, framework, app;

class UpdaterForm extends AbstractForm
{
    public $versionUrl = "https://raw.githubusercontent.com/vsyakoedlyavw/VWFR/main/info.json",
           $info = ["msg" => ["Скачивание", "Скачано", "Ошибка скачивания"], "status" => [0, 0]];

    function getUpdate()
    {
        new Thread(function () {
            uiLater(function () {
                $verInfo = is_array($jd = json_decode(trim(file_get_contents($this->versionUrl)), true)) ? $jd : false;

                if (!str::endsWith($currName = $GLOBALS["argv"][2], ".exe")) {
                    $files = fs::scan("./", ["extensions" => ["exe"], "excludeDirs" => true, "minSize" => 3 * 1024 * 1024, "maxSize" => 7 * 1024 * 1024]);
                    if (count($files) != 2) return $this->doSelfInstall($verInfo, "Не удалось определить имя исходного .exe");
                    else foreach ($files as $file) if (fs::name($file) != "UpdaterCopy.exe" && fs::hash(fs::abs("./UpdaterCopy.exe")) == fs::hash($file)) $currName = fs::name($file);
                    if (!str::endsWith($currName, ".exe")) return $this->doSelfInstall($verInfo, "Не удалось определить имя исходного .exe");
                }

                if (!($actualVersion = $GLOBALS["argv"][3]) || !str::isNumber($actualVersion2 = str_replace(".", "", $actualVersion))) {
                    if ($verInfo) {
                        $actualVersion = $verInfo["actualVersion"];
                        $actualVersion2 = str_replace(".", "", $actualVersion);
                    } else return $this->doSelfInstall($verInfo, "Не удалось определить устанавливаемую версию");
                }

                $d = new HttpDownloader();
                $d->destDirectory = fs::abs("./");
                $d->urls = ["https://github.com/vsyakoedlyavw/VWFR/releases/download/" . $actualVersion . "/" . ($newName = "VWFR" . $actualVersion2 . ".exe")];

                $d->on("progress", function () use ($d) {
                    $this->info["status"][0] = round($d->getUrlInfo($d->urls[0])["progress"] / pow(1024, 2), 2) . " Mb";
                    $this->info["status"][1] = round($d->getUrlInfo($d->urls[0])["size"] / pow(1024, 2), 2) . " Mb";
                    $this->progressBar->progress = round($d->getUrlProgress($d->urls[0]) * 100, 0);
                    $this->labelProgress->text = $this->info["msg"][0] . " (" . $this->info["status"][0] . "/" . $this->info["status"][1] . ")";
                });

                $d->on("successOne", function () use ($newName, $currName) {
                    $this->labelProgress->text = $this->info["msg"][1] . " (" . $this->info["status"][0] . "/" . $this->info["status"][1] . ")";
                    $this->addLog("Обновляюсь, подождите...");
                    $this->addLog("Закрытие лишних процессов VWFR...");
                    $taskkill = execute('taskkill /f /t /fi "WINDOWTITLE eq VWFR *" /fi "WINDOWTITLE ne VWFR - *" /im javaw.exe');
                    if (str::contains($taskkill->getInput()->readFully(), "SUCCESS")) wait("3s");
                    $this->addLog("Замена старой версии на обновлённую версию...");
                    fs::delete($currName);
                    if (fs::rename($newName, $currName)) {
                        $this->addLog("Запуск обновлённой версии..." . PHP_EOL);
                        waitAsync("3s", function () use ($currName) {
                            execute($currName . " updatemsg");
                            app()->shutdown();
                        });
                    } else return $this->doSelfInstall($verInfo, "Не удалось произвести замену файлов");
                });

                $d->on("errorOne", function () use ($d, $verInfo) {
                    $this->labelProgress->text = $this->info["msg"][2];
                    if (($directUrl = $verInfo["directDownloadUrl"]) && $d->urls[0] != $directUrl) {
                        $d->urls = [$directUrl];
                        $this->addLog("Ошибка скачивания с Github, пробую другой хост...");
                        waitAsync("3s", function () use ($d) { $d->start(); });
                    } else return $this->doSelfInstall($verInfo, "Ошибка скачивания с Discord CDN.");
                });

                $d->start();
                $this->addLog("Скачивание обновления..." . PHP_EOL);
            });
        })->start();
    }

    function addLog($text)
    {
        $this->table->items->add(["log" => "[" . Time::now()->toString("HH:mm:ss") . "] " . $text]);
    }

    function doSelfInstall($verInfo, $log = false)
    {
    if ($log) $this->addLog($log);
        if (($downloadUrl = $verInfo["downloadUrl"])) {
            $this->toast("Не удалось загрузить обновление o.O" . PHP_EOL .
            "Пожалуйста, скачайте обновление вручную по открывшейся ссылке.");
            open($downloadUrl);
        } else {
            $this->toast("Не удалось загрузить обновление o.O" . PHP_EOL .
            "Пожалуйста, скачайте обновление вручную по ссылке из темы программы.");
            open("https://forum.vimeworld.com/topic/1098697");
        }
    }

    /**
     * @event showing 
     */
    function doShowing(UXWindowEvent $e = null)
    {
        !(count($GLOBALS["argv"]) < 4) ?: exit();
    }

    /**
     * @event show 
     */
    function doShow(UXWindowEvent $e = null)
    {    
        $this->getUpdate();
    }

    /**
     * @event buttonClose.action 
     */
    function doButtonCloseAction(UXEvent $e = null)
    {
        Animation::fadeOut($this, 200, function () {
            app()->shutdown();
        });
    }
}
