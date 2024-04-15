<?php

namespace app\forms;

use php\io\IOException;
use system\DFFIStruct;
use system\DFFIReferenceValue;
use system\DFFIType;
use system\DFFI;
use gui\Ext4JphpWindows;
use php\jsoup\Jsoup;
use httpclient;
use std, gui, framework, app;
use php\time\Time;
use php\time\TimeZone;
use php\time\Timer;

class MainForm extends AbstractForm {

    public $uiState         = true,
           $currentVersion  = "1.2.2",
           $versionUrl      = "https://raw.githubusercontent.com/vsyakoedlyavw/VWFR/main/info.json",
           $userAgentUrl    = "https://whatsmyua.info",
           $imgurCids       = ["ec61be071b16841", "ad338f3eaae9baa", "9f3460e67f308f6", "65bfadb95e040a0", "2421109ee0e8d3d", "d297fd441566f99", "70ff50b8dfc3a53", "886730f5763b437", "6cf1cd6f95fe7c8", "4408bab9df4233c"],
           $serverCodes     = ["41-жалобы-на-игроков", "79-жалобы-на-игроков", "10-vime", "12-explore", "13-discover", "15-empire", "54-wurst", "11-flair", "66-hoden"],
           $noCheck         = [];

    function checkUpdates()
    {
        new Thread(function () {
            if (is_array($verInfo = json_decode(trim(file_get_contents($this->versionUrl)), true))) {
                if ($this->currentVersion != $verInfo["actualVersion"] && $verInfo["updateAdvice"]) {
                    uiLater(function () use ($verInfo) {
                        $df = $this->form("NeedUpdateForm");
                        $df->show();
                        $this->changeTheme($this->combobox->selectedIndex, "NeedUpdateForm", true);
                        $df->labelCurrV->text .= $this->currentVersion;
                        $df->labelNewV->text .= $verInfo["actualVersion"];
                        $df->changelogArea->text = $verInfo["changelog"];
                        $this->centerForm($df);
                        $df->buttonUpdate->on("click", function () use ($verInfo, $df) {
                            $taskkill = execute('taskkill /f /t /fi "WINDOWTITLE eq Updater" /im javaw.exe');
                            if (str::contains($taskkill->getInput()->readFully(), "SUCCESS")) wait("3s");
                            $exePath = substr($GLOBALS["argv"][0], 1);
                            $dirPath = explode("/", $exePath);
                            $exeName = array_pop($dirPath);
                            $copyPath = implode("/", $dirPath) . "/UpdaterCopy.exe";
                            fs::copy($exePath, $copyPath);
                            if (fs::exists(fs::abs("./UpdaterCopy.exe"))) {
                                $command = 'UpdaterCopy.exe runUpdater "' . $exeName . '" "' . $verInfo["actualVersion"] . '" "' . fs::hash($exePath) . '"';
                                $df->toast("Запуск апдейтера, подождите... (не закрывайте программу)", "1s");
                                waitAsync("1s", function () use ($df, $command, $verInfo) {
                                    try {
                                        execute($command);
                                        return app()->shutdown();
                                    } catch (IOException $e) {
                                        $df->toast("Пробуем другой способ запуска апдейтера..." . PHP_EOL .
                                        "(если он так и не запустится самостоятельно, сообщите об этом разработчику)", "3s");
                                        waitAsync("1s", function () use ($df, $command, $verInfo) {
                                            try {
                                                execute("cmd.exe /c " . $command);
                                                return app()->shutdown();
                                            } catch (IOException $e) {
                                                $this->showMessage("Невозможно запустить апдейтер, сообщите об этом разработчику." . PHP_EOL . PHP_EOL . $e->getMessage());
                                                return open($verInfo["downloadUrl"]);
                                            }
                                        });
                                    }
                                });
                            } else {
                                $df->toast("Не удалось создать копию программы для запуска апдейтера о.О" . PHP_EOL .
                                "Пожалуйста, скачайте обновление вручную по открывшейся ссылке или из темы программы.");
                                open($verInfo["downloadUrl"]);
                            }
                        });
                    });
                }
                if ($cids = $verInfo["imgurCids"]) $this->imgurCids = $cids;
            }
        })->start();
    }

    function showMessage($msg, $type = "info", $alwaysOnTop = true) {
        $mf = $this->form("MessageForm");
        if (!$alwaysOnTop) $mf->alwaysOnTop = false;
        if ($type == "info") $mf->title = $mf->labelProgTitle->text = "Сообщение";
        $this->changeTheme($this->combobox->selectedIndex, "MessageForm", true);
        $mf->imageInfo->image = new UXImage("res://.data/img/" . $type . ".png");
        $label = new UXLabel();
        $label->maxWidth = 387;
        $label->autoSize = true;
        $label->autoSizeType = "VERTICAL";
        $label->wrapText = true;
        $label->text = $msg;
        $vbox = new UXVBox();
        $vbox->add($label);
        $vbox->add(new UXLabel()); // добавляем фантомный label чтобы узнать в каком месте заканчивается первый. как вам?
        $mf->container->content = $vbox;
        $mf->show();
        $mf->container->height = $vbox->children[1]->y + 30;
        $mf->height = $mf->rect->height = 60 + 20 + $mf->container->content->children[1]->y;
        $mf->buttonOk->y = $mf->height - 32;
        $mf->buttonOk->toFront();
        $this->centerForm($mf);
    }

    function defineReportUrl()
    {
        return "https://forum.vimeworld.com/forum/" . urlencode($this->serverCodes[$this->comboboxServer->selectedIndex]) . "/?do=add";
    }

    function defineMyNickname()
    {
        return json_decode(file_get_contents($_ENV["APPDATA"] . "\\.vimeworld\\launcher.json"), true)["last_account"] ?? "";
    }

    function cookieToArray($cookie)
    {
        $cookie = explode("; ", $cookie);
        array_walk($cookie, function ($value, $key) use (&$resCookie) {
            $resCookie[explode("=", $value)[0]] = explode("=", $value)[1];
        });
        return $resCookie;
    }

    function checkCookieString($cookie)
    {
        $cookieArr = $this->cookieToArray($cookie);
        return !count($missing = array_filter(["ips4_IPSSessionFront", "ips4_member_id"], function ($value) use ($cookieArr) {
            if (!array_key_exists($value, $cookieArr)) return $value;
        })) ? true : "Некорректно указаны Cookie (возможно, вы не выполнили вход на форуме или получили их не с той страницы), отсутствуют обязательные параметры: " . implode(", ", $missing) . PHP_EOL . PHP_EOL .
        "Подробную информацию о способе их получения вы можете найти в теме программы на форуме.";
    }

    function getLatestScr($count)
    {
        if (!is_dir(($path = $_ENV["APPDATA"] . "\\.vimeworld\\" . str::lower($this->comboboxServer->selected) . "\\screenshots")))
            return $this->showMessage("Не могу найти папку со скриншотами по пути " . $path . PHP_EOL . "Убедитесь, что вы выбрали нужный сервер вверху программы.", "err");
        foreach (scandir($path) as $file)
            if (pathinfo($finalPath = $path . "\\" . $file)["extension"] == "png") $files[$finalPath] = filemtime($finalPath);
        if (!$files)
            return $this->showMessage("В папке " . $path . " отсутствуют скриншоты" . PHP_EOL . "Убедитесь, что вы выбрали нужный сервер вверху программы.", "err");
        asort($files);
        return count($files) >= $count ? array_slice(array_keys($files), -$count) : array_keys($files);
    }

    function setDate($days = 0)
    {
        $this->date->text = Time::now(TimeZone::of("Europe/Moscow"))->add(["day" => $days])->toString("dd.MM.yyyy");
    }

    function setTimerUntilNewDay() //автосмена даты в текстовом поле с наступлением нового дня, для забывчивых как я
    {
        $msk = Time::now(TimeZone::of("Europe/Moscow"));
        $mskNext = $msk->add(["day" => 1])->toString("dd.MM.yyyy");
        $secUntil = ceil((new TimeFormat("dd.MM.yyyy")->parse($mskNext, TimeZone::of("Europe/Moscow"))->getTime() - $msk->getTime()) / 1000) + 10;
        if ($secUntil > 0 && $secUntil < 86420) {
            $secUntil .= "s";
            Timer::after($secUntil, function () {
                uiLater(function () { $this->setDate(); });
                $this->setTimerUntilNewDay();
            });
        }
    }

    function changeIni($param, $val, $doToast = false, $textToast = "Сохранено")
    {
        $this->ini->set($param, $val);
        $this->ini->save();
        if ($doToast) $this->toast($textToast);
    }

    function changeUiState($state = true, $full = false)
    {
        foreach ($this->children as $object) {
            if (!in_array($object->id, ["labelCookie", "cookie", "buttonCheckCookie", "labelAuthor", "panel"]) || $full) {
                 $object->enabled = $state;
                 if (str::contains($object->id, "button")) $object->opacity = ($state ? 1 : 0.5);
                 if (str::contains($object->id, "day") || str::contains($object->id, "separator")) $object->opacity = ($state ? 0.66 : 0.33);
            }
        }
        $this->uiState = $state;
    }

    function centerForm($form)
    {
        $form->x = $this->x + (($this->width - $form->width) / 2);
        $form->y = $this->y + (($this->height - $form->height) / 2);
    }

    function timerButton()
    {
        $sec = 30;
        $this->buttonCreateReport->enabled = false;
        Timer::every("1s", function (Timer $timer) use (&$sec) {
            $sec--;
            uiLater(function () use ($sec, &$timer) {
                $this->buttonCreateReport->text = "Создать жалобу (" . $sec . " с.)";
                if ($sec == 0) {
                    $this->buttonCreateReport->text = "Создать жалобу";
                    $this->buttonCreateReport->enabled = true;
                    $timer->cancel();
                }
            });
        });
    }

    function deleteAlbum() // чистим за собой если отменено создание жалобы
    {
        new Thread(function () {
            if (is_array($this->lastAlbum)) {
                $delHttpClient = new HttpClient();
                $delHttpClient->headers = ["Authorization" => "Client-ID " . $this->lastAlbum["clientId"]];
                foreach ($this->lastAlbum["deletehashes"] as $deletehash) {
                    $delHttpClient->delete("https://api.imgur.com/3/image/" . $deletehash);
                    wait("1s");
                }
                $delHttpClient->delete("https://api.imgur.com/3/album/" . $this->lastAlbum["deletehash"]);
            }
        })->start();
    }

    function testCookie($cookie, $fromReport = true, $formUnlock = false)
    {
        $forumHttpClient = new HttpClient();
        $headers["Cookie"] = ($this->cookie->text = str_ireplace("cookie: ", "", $this->cookie->text));
        if ($userAgent = $this->ini->get("useragent")) $headers["User-Agent"] = $userAgent;
        $forumHttpClient->headers = $headers;
        $response = $forumHttpClient->get($this->defineReportUrl())->body();
        $jSoup = Jsoup::parseText($response);

        if (str::startsWith($jSoup->title(), "Please Wait") || str::startsWith($jSoup->title(), "Just a moment") || str::startsWith($jSoup->select(".ray-id")->html(), "Ray ID")) {
            $cf = $this->form("CloudflareForm");
            $cf->show();
            $this->changeTheme($this->combobox->selectedIndex, "CloudflareForm", true);
            if ($userAgent) {
                //var_dump($userAgent);
                $cf->useragent->text = $userAgent;
                $cf->labelCookie->visible = $cf->cookie->visible = true;
                $cf->height = $cf->rect->height = 353;
                $cf->buttonSave->position = [8, 321];
                $cf->buttonCancel->position = [192, 321];
            } else $cf->labelTitle->text = str_replace("получите Cookie заново (после пройденной проверки Cloudflare) и ", "", $cf->labelTitle->text);

            $this->centerForm($cf);

            $cf->buttonCancel->on("click", function () use ($cf, $fromReport) {
                $cf->free();
                return $this->toast("Вы отменили " . ($fromReport ? "публикацию жалобы" : "проверку"), "1.5s");
            });

            $cf->buttonSave->on("click", function () use ($cf, $userAgent, $fromReport) {
                $cf->useragent->text = str_ireplace("user-agent: ", "", $cf->useragent->text);
                $this->changeIni("useragent", $cf->useragent->text);
                if ($userAgent) {
                    $cf->cookie->text = str_ireplace("cookie: ", "", $cf->cookie->text);
                    if (is_bool($missing = $this->checkCookieString($cf->cookie->text))) {
                        $this->cookie->text = $cf->cookie->text;
                        $this->changeIni("cookie", $cf->cookie->text);
                    } else return $this->showMessage($missing, "err");
                }
                $cf->free();
                return $this->toast("Сохранено, попробуйте " . ($fromReport ? "создать жалобу" : "выполнить проверку") . " ещё раз", "1.5s");
            });
            return false;
        }

        $csrfKey = $jSoup->select("input[name=csrfKey]")->attr("value");
        $plupload = $jSoup->select("input[name=plupload]")->attr("value");
        $isBanned = $jSoup->select(".ipsType_huge.fa.fa-lock")->valid() ?? "";
        $username = $jSoup->select("a[title=Перейти в свой профиль]:not(:contains(Профиль))")->text() ?? "";
        $welcomeText = (Regex::match("^[a-zA-Z0-9_]{3,30}$", $username)) ? "Добро пожаловать, " . $username . "!" . PHP_EOL : "";
        if ($isBanned)
            return $this->toast("К сожалению, ваш форумный аккаунт заблокирован.");
        if ($jSoup->select("a[id=elUserSignIn]")->hasAttr("href"))
            return $this->toast("Cookie недействительны" . ($fromReport ? ", обновите их" : ". Возможно, вы не авторизованы на форуме или они устарели и их пора обновить?"));
        if (strlen($csrfKey) <> 32 || strlen($plupload) <> 32) {
            $this->toast("Не удалось получить нужные данные с форума о.О" . PHP_EOL . "Попробуйте обновить Cookie / проверить соединение с интернетом / повторить попытку.");
            return file_put_contents("debug.log", $response);
        }

        if ($this->ini->get("cookie") !== $this->cookie->text) $this->changeIni("cookie", $this->cookie->text);
        if ($formUnlock) $this->changeUiState();

        if ($fromReport) {
            if ($this->checkboxSelect->selected && !empty($this->scrSelected) && !$this->numberField->enabled)
                $latest = $this->scrSelected;
            elseif (!is_array($latest = $this->getLatestScr($this->numberField->value)))
                return false;

            $uploadImages = function () use ($latest) {
                if (!parse_url($imgUrl = $this->uploadImages($latest))["host"] || !$imgUrl)
                    return false;
                else return $imgUrl;    
            };
            $postReport = function ($imgUrl) use ($csrfKey, $plupload, $forumHttpClient) {
                if (!$imgUrl) return false;
                if ($this->checkbox->selected) {
                    $cf = $this->form("ConfirmPostForm");
                    $cf->show();
                    $this->centerForm($cf);
                    $this->changeTheme($this->combobox->selectedIndex, "ConfirmPostForm", true);
                    $cf->labelTitle->text .= $this->myTitle->text;
                    $cf->labelText->text = "[Текст жалобы:]" . PHP_EOL .
                    "  1. " . $this->nick->text . PHP_EOL .
                    "  2. " . $this->myNick->text .  PHP_EOL .
                    "  3. " . $this->date->text . PHP_EOL .
                    "  4. " . $imgUrl . PHP_EOL;
                    $cf->labelBrowser->x = $cf->labelText->font->calculateTextWidth("  4. " . $imgUrl) + 15;
                    $cf->labelBrowser->on("click", function () use ($imgUrl) { open($imgUrl); });
                    $cf->buttonCancel->on("click", function () use ($cf) {
                        $cf->free();
                        $this->deleteAlbum();
                        return $this->toast("Вы отменили публикацию жалобы");
                    });
                    $cf->buttonPost->on("click", function () use ($cf, $csrfKey, $plupload, $forumHttpClient, $imgUrl) {
                        $cf->free();
                        $this->postReport($csrfKey, $plupload, $forumHttpClient, $imgUrl);
                    });
                } else {
                    $this->postReport($csrfKey, $plupload, $forumHttpClient, $imgUrl);
                }
            };
            
            $this->aSync($uploadImages, $postReport);
        } else {
            $this->toast($welcomeText . "Cookie корректны и сохранены, программа готова к использованию", "2s");
        }
    }

    function uploadImages($filesToUpload)
    {
        $errorMsgs = ["Не удалось загрузить изображение на Imgur o.О" . PHP_EOL . "Попробуйте ещё раз (возможна также некорректная работа если вы используете VPN)", "Imgur возвратил ошибку "];
        $imgHttpClient = new HttpClient();
        $imgHttpClient->headers = ["Authorization" => "Client-ID " . ($cId = $this->imgurCids[array_rand($this->imgurCids)])];
        #$imgHttpClient->headers = ["Authorization" => "Client-ID " . ($cId = $this->imgurCids[5])];
        $imgHttpClient->requestType = "MULTIPART";
        $successUploads = "0";

        uiLater(function () use (&$pf, $successUploads, $filesToUpload) {
            $pf = $this->form("PreloaderForm");
            if (!$pf->visible) {
                Animation::fadeOut($pf, 200, function () use ($pf) {
                    $pf->show();
                    $this->centerForm($pf);
                    Animation::fadeIn($pf, 200);
                });
                #$pf->layout->opacity = 0.5;
                $this->changeTheme($this->combobox->selectedIndex, "PreloaderForm", true);
            }
            $pf->labelProgress->text = "Загружено изображений: " . $successUploads . "/" . count($filesToUpload);
        });

        foreach ($filesToUpload as $file) {
            $response = $imgHttpClient->post("https://api.imgur.com/3/image/", ["image" => new File($file), "description" => ($file == end($filesToUpload) ? "VWFR was here &#9925;" : "")])->body();
            $jsonRes = json_decode($response, true)["data"];
            if (!$dh = $jsonRes["deletehash"]) {
                file_put_contents("debug.log", PHP_EOL . PHP_EOL . $response, FILE_APPEND);
                if ($pf->visible)
                    Animation::fadeOut($pf, 200, function () use ($pf) {
                        $pf->free();
                    });
                return (!($eCode = $jsonRes["error"]["code"]) && !($eMessage = $jsonRes["error"]["message"]))
                ? uiLater(function () use ($errorMsgs) { $this->toast($errorMsgs[0]); }) : uiLater(function () use ($errorMsgs, $eCode, $eMessage) { $this->toast($errorMsgs[1] . $eCode . ":" . PHP_EOL . $eMessage); });
            }
            $deletehashes[] = $dh;
            $successUploads += 1;
            uiLater(function () use ($pf, $successUploads, $filesToUpload) {
                $pf->labelProgress->text = "Загружено изображений: " . $successUploads . "/" . count($filesToUpload);
            });
        }

        waitAsync(100, function () use ($pf) { // чтобы пользователь успевал видеть последнее значение в прогрессе до создания альбома :p
            uiLater(function () use ($pf) {
                if (!$pf->isFree() && $pf->labelProgress->visible) $pf->labelProgress->text = "Создание альбома...";
            });
        });
        $imgHttpClient->requestType = "URLENCODE";
        $response = $imgHttpClient->post("https://api.imgur.com/3/album/", ["deletehashes" => implode(",", $deletehashes)])->body();
        $this->lastAlbum = ["clientId" => $cId, "deletehash" => json_decode($response, true)["data"]["deletehash"], "deletehashes" => $deletehashes];
        if ($pf->visible)
            Animation::fadeOut($pf, 200, function () use ($pf) {
                $pf->free();
            });
        return ($id = json_decode($response, true)["data"]["id"]) ? "https://imgur.com/a/" . $id : uiLater(function () use ($errorMsgs) { $this->toast($errorMsgs[0]); });
    }

    function aSync($func, $callback) // выполняет функцию в потоке, ждёт результата и выполняет другую функцию
    {
        new Thread(function () use ($func, $callback) {
            $return = $func();
            uiLater(function () use ($callback, $return) {
                $callback($return);
            });
        })->start();
    }

    function uploadVideo($filesToUpload) // спойлер для внимательных и любопытных)
    {
        $errorMsgs = ["Не удалось загрузить видео на Imgur o.О" . PHP_EOL . "Попробуйте ещё раз", "Imgur возвратил ошибку "];
        $imgHttpClient = new HttpClient();
        $imgHttpClient->headers = ["Authorization" => "Client-ID " . ($cId = $this->imgurCids[array_rand($this->imgurCids)])];
        //$imgHttpClient->headers = ["Authorization" => "Client-ID " . ($cId = $this->imgurCids[9])];
        $imgHttpClient->requestType = "MULTIPART";
        $response = $imgHttpClient->post("https://api.imgur.com/3/upload", ["type" => "file", "disable_audio" => 1, "video" => new File($filesToUpload), "description" => "VWFR was here &#10024;"])->body();
        $jsonRes = json_decode($response, true)["data"];
        if (!$dh = $jsonRes["deletehash"]) {
            file_put_contents("debug.log", PHP_EOL . PHP_EOL . $response, FILE_APPEND);
            return (!($eCode = $jsonRes["error"]["code"]) && !($eMessage = $jsonRes["error"]["message"]))
            ? $this->toast($errorMsgs[0]) : $this->toast($errorMsgs[1] . $eCode . ":" . PHP_EOL . $eMessage);
        }
        $response = $imgHttpClient->post("https://api.imgur.com/3/album/", ["deletehashes" => $dh])->body();
        $this->lastAlbum = ["clientId" => $cId, "deletehash" => json_decode($response, true)["data"]["deletehash"], "deletehashes" => $dh];
        return ($id = json_decode($response, true)["data"]["id"]) ? "https://imgur.com/a/" . $id : $this->toast($errorMsgs[0]);
    }

    function postReport($csrfKey, $plupload, $forumHttpClient, $imgUrl)
    {
        $data = ["form_submitted" => 1,
                "csrfKey" => $csrfKey,
                "MAX_FILE_SIZE" => 9437184, 
                "plupload" => $plupload, 
                "topic_title" => $this->myTitle->text, 
                "topic_content" =>
                    "<p>" . $this->nick->text .
                    "<br>" . $this->myNick->text .
                    "<br>" . $this->date->text . 
                    '<br><a href="' . $imgUrl . '" ipsnoembed="true">' . $imgUrl . "</a>" . 
                    ($this->checkboxAddInfo->selected && ($add = $this->additionalInfo->text) ? "<br>" . $add : "") .
                    "</p>",
                "topic_auto_follow" => 0,
                "topic_auto_follow_checkbox" => ($this->checkboxFollow->selected) ? 1 : 0];

        $forumHttpClient->followRedirects = false;
        $createRequest = $forumHttpClient->post($this->defineReportUrl(), $data);
        if (str::startsWith($location = $createRequest->header("Location"), "https://forum.vimeworld.com/topic/")) {
            $labelSuccess = new UXLabel();
            $labelSuccess->text = "Жалоба опубликована и доступна по ссылке:";
            $labelLink = new UXLabel();
            $labelLink->text = explode("-", $location)[0];
            $labelLink->underline = true;
            $labelLink->cursor = "HAND";
            $labelLink->on("click", function() use ($labelLink) {
                open($labelLink->text);
            });
            $toast = new UXTooltip();
            $toast->graphic = new UXVBox([$labelSuccess, $labelLink]);
            $toast->opacity = 0;
            $toast->show($this, $this->x + $this->width / 2 - $toast->font->calculateTextWidth($labelSuccess->text) / 2, $this->y + $this->height / 2 - $toast->font->lineHeight * 2 / 2);
            Animation::fadeIn($toast, 500, function () use ($toast) {
                waitAsync("2s", function () use ($toast) {
                    Animation::fadeOut($toast, 500);
                });
            });
            // ДА. я очень. хотел. кликабельную. ссылку. было так:
            // $this->toast("Жалоба опубликована и доступна по ссылке:" . PHP_EOL . explode("-", $location)[0] . "-", "2s");
            if ($this->checkboxAddInfo->selected) {
                $this->checkboxAddInfo->selected = false;
                $this->checkboxAddInfo->text = "Указать дополнительную информацию к жалобе (6-й пункт)";
                $this->additionalInfo->text = "";
                $this->additionalInfo->visible = 
                $this->additionalInfo->enabled = false;
            }
            $this->numberField->value = 1;
            $this->panel->requestFocus(); //на намберфилд выше при смене значения переводит фокус, а он нам не нужен, поэтому меняем фокус на что-то нефокусируемое
            if (!empty($this->scrSelected)) {
                unset($this->scrSelected);
                $this->checkboxSelect->selected = false;
                $this->labelCountScreenshots->opacity = 1.0;
                $this->numberField->enabled = true;
            }
            $this->timerButton();
        } elseif (str::contains($postRequest->body(), "Установлен лимит на отправку нескольких сообщений за определённое время"))
            $this->showMessage("Не прошло 30 секунд с момента публикации последней жалобы или ответа", "err");
        else $this->showMessage("По неизвестной причине не удалось опубликовать жалобу :C", "err");
    }

    /**
     * @event author.click 
     */
    function doAuthorClick(UXMouseEvent $e = null)
    {
        open("https://vimetop.ru/player/Hocico");
    }

    /**
     * @event buttonCheckCookie.action 
     */
    function doButtonCheckCookieAction(UXEvent $e = null)
    {
        if (is_bool($missing = $this->checkCookieString($this->cookie->text))) {
            !$this->uiState ? $this->testCookie($this->cookie->text, false, true) : $this->testCookie($this->cookie->text, false, false);
            $e->sender->enabled = false;
            $e->sender->opacity = 0.5;
            waitAsync("2.5s", function () use ($e) {
                $e->sender->enabled = true;
                $e->sender->opacity = 1;
            });
        } else $this->showMessage($missing, "warning");
    }

    /**
     * @event buttonSaveTitle.action 
     */
    function doButtonSaveTitleAction(UXEvent $e = null)
    {
        !$this->myTitle->text ? $this->toast("Введите заголовок жалобы для сохранения") : $this->changeIni("title", $this->myTitle->text, true);
    }

    /**
     * @event buttonDefineMyNickname.action 
     */
    function doButtonDefineMyNicknameAction(UXEvent $e = null)
    {
        (!$myNick = $this->defineMyNickname()) ? $this->toast("Не удалось определить ваш ник, скорее всего, вы не авторизованы в лаунчере VimeWorld. Заполните поле вручную.") : $this->myNick->text = $myNick;
    }

    /**
     * @event buttonCreateReport.action 
     */
    function doButtonCreateReportAction(UXEvent $e = null)
    {
        !$this->cookie->text ? $ls[] = "- Не указаны Cookie;" : (is_bool($missing = $this->checkCookieString($this->cookie->text)) ?: $ls[] = "- " . $missing . ";");
        !$this->myTitle->text ? $ls[] = "- Не указан заголовок жалобы (например: «асоц. поведение», «некорректная постройка», или же придумайте какой-нибудь универсальный для всех жалоб);" : (Regex::match("^.{1,100}$", $this->myTitle->text) ?: $ls[] = "- Заголовок жалобы заполнен некорректно, min - 1 символ, max - 100;");
        !$this->nick->text ? $ls[] = "- Не указан(ы) ник(и) нарушителя(ей);" : (Regex::match("^.{3,100}$", $this->nick->text) ?: $ls[] = "- Ник(и) нарушителя(ей) заполнен(ы) некорректно, min - 3 символа, max - 100;");
        !$this->myNick->text ? $ls[] = "- Не указан ник отправителя;" : (Regex::match("^.{3,25}$", $this->myNick->text) ?: $ls[] = "- Ник отправителя заполнен некорректно, min - 3 символа, max - 25;");
         $this->date->text ?: $ls[] = "- Не указана(ы) дата(ы) нарушения (по МСК времени);";

        if (is_array($ls)) return $this->showMessage("Вы ошиблись в заполнении некоторых полей:" . str_repeat(PHP_EOL, 2) . implode(PHP_EOL, $ls), "warning");

        $this->testCookie($this->cookie->text);
    }

    /**
     * @event cookie.mouseDown-Left 
     */
    function doCookieMouseDownLeft(UXMouseEvent $e = null)
    {    
        $e->sender->selectAll();
    }

    /**
     * @event nick.mouseDown-Left 
     */
    function doNickMouseDownLeft(UXMouseEvent $e = null)
    {
        $e->sender->selectAll();
    }

    /**
     * @event myNick.mouseDown-Left 
     */
    function doMyNickMouseDownLeft(UXMouseEvent $e = null)
    {
        $e->sender->selectAll();
    }

    /**
     * @event labelSetToday.click 
     */
    function doLabelSetTodayClick(UXMouseEvent $e = null)
    {
        $this->setDate();
    }

    /**
     * @event labelSetYesterday.click 
     */
    function doLabelSetYesterdayClick(UXMouseEvent $e = null)
    {
        $this->setDate(-1);
    }

    function copyObserver($do) {
        if ($do) {
            $this->obs = $this->observer("focused")->addListener(function ($state) {
                $clipText = trim(UXClipboard::getText());
                if (!$state && Regex::match("^[a-zA-Z0-9_]{3,16}$", $clipText) && $clipText !== $this->nick->text && $clipText !== $this->myNick->text && !in_array($clipText, $this->noCheck)) {
                    if (fs::exists($path = $_ENV["APPDATA"] . "\\.vimeworld\\1.8.8\\assets\\skins\\skins\\" . substr($clipText, 0, 2) . "\\" . $clipText . ".png")) {
                        if ((time() - fileatime($path)) < 100000) {
                            $this->nick->text = $clipText;
                            $this->noCheck[] = $clipText;
                        }
                    } else {
                        new Thread(function () use ($clipText) {
                            $checkHttpClient = new HttpClient();
                            $clipPlayerInfo = json_decode($checkHttpClient->get("https://api.vimeworld.com/user/name/" . $clipText)->body(), true);
                            if ($clipPlayerInfo[0]["lastSeen"]) if ((time() - $clipPlayerInfo[0]["lastSeen"]) < 100000)
                                return uiLater(function () use ($clipText, $clipPlayerInfo) { $this->nick->text = ($nick = $clipPlayerInfo[0]["username"]) ? $nick : $clipText; });
                            $this->noCheck[] = $clipText;
                        })->start();
                    }
                }
            });
        } elseif (is_object($this->obs)) $this->observer("focused")->removeListener($this->obs);
    }

    function changeTheme($value, $anotherForm = false, $replaceIcon = true) {
        $form = (!$anotherForm) ? $this : $this->form($anotherForm);
        foreach ($this->combobox->items as $index => $item)
            if ($form->hasStylesheet(".theme/" . $index . "-theme.fx.css")) $form->removeStylesheet(".theme/" . $index . "-theme.fx.css");
        $form->addStylesheet(".theme/" . $value . "-theme.fx.css");
        if ($replaceIcon) {
            $form->icons->clear();
            $form->icons->add(new UXImage("res://.data/img/vime_" . $value . ".png"));
            (!is_object($form->imageIcon)) ?: $form->imageIcon->image = new UXImage("res://.data/img/vime_" . $value . ".png");
        }
    }

    function loadTable($count = 10, $notReverse = false) {
        $latest = !$notReverse ? array_values(array_reverse($this->getLatestScr($count))) : array_values($this->getLatestScr($count));
        $pf = $this->form("PreviewForm");
        $tf = $this->transpForm;
        $pf->table->items->clear();
        $pf->cbState->selectedIndex = 0;
        foreach ($latest as $key => $screen) {
            $imgArea = new UXImageArea();
            $imgArea->size = [369, 205];
            $imgArea->image = new UXImage($screen, 369, 205);
            $imgArea->cursor = "CROSSHAIR";
            $cb = new UXCheckbox();
            $cb->stylesheets->add(".theme/big-checkbox.fx.css");
            $vbox = new UXVBox([$cb]);
            $vbox->alignment = "CENTER";
            $vbox->cursor = "HAND";
            $vbox->on("click", function () use ($vbox) {
                $vbox->children[0]->selected = !$vbox->children[0]->selected;
            });
            $pf->table->items->add(["checkbox" => $vbox, "screen" => substr(strrchr($screen, "\\"), 1), "preview" => $imgArea]);
            $pf->table->items[$key]["preview"]->on("mouseDown", function () use ($pf, $screen, $tf) {
                $tf->fullScreen = true;
                $tf->show();
                $tf->toFront();
                $img = new UXImage($screen);
                $imgArea = new UXImageArea();
                $imgArea->backgroundColor = "transparent";
                $imgArea->image = $img;
                $imgArea->size = $tf->size;
                $imgArea->centered = true;
                $tf->size = [$imgArea->width, $imgArea->height];
                $tf->children[0] = $imgArea;
            });
            $pf->table->items[$key]["preview"]->on("mouseUp", function () use ($pf, $screen, $tf) {
                $tf->children[0]->image = null;
                $tf->fullScreen = false;
                $tf->hide();
            });
        }
        $pf->toast("Показано скриншотов: " . count($pf->table->items) . PHP_EOL . PHP_EOL .
        "Удерживайте ЛКМ/ПКМ на скриншоте для открытия в полном масштабе.", "1.5s");
    }

    function makeMinimizable($isAnimated) { // возвращает сворачивание/разворачивание при клике на иконку в панели задач, целый день убил на эту херню
        $u32 = new DFFI("user32");
        $u32->bind("GetWindowLongA", DFFIType::LONG, [DFFIType::INT, DFFIType::INT]);
        $u32->bind("SetWindowLongA", DFFIType::LONG, [DFFIType::INT, DFFIType::INT, DFFIType::LONG]);
        $u32->bind("GetClassLongA", DFFIType::LONG, [DFFIType::INT, DFFIType::INT]);
        $u32->bind("SetClassLongA", DFFIType::LONG, [DFFIType::INT, DFFIType::INT, DFFIType::LONG]);
        $hwnd = DFFI::getJFXHandle($this);
        $oldStyle = DFFI::GetWindowLongA($hwnd, -16);
        $oldStyleClass = DFFI::GetClassLongA($hwnd, -26);
        DFFI::SetClassLongA($hwnd, -26, 0x00020000);
        DFFI::SetWindowLongA($hwnd, -16, $oldStyle | 0x00020000 | (!$isAnimated ?: 0x00C00000));
        if ($isAnimated) {
            $this->observer("iconified")->addListener(function ($min) use ($hwnd, $oldStyle) {
                DFFI::SetWindowLongA($hwnd, -16, $oldStyle | 0x00020000 | (!$min ?: 0x00C00000));
            });
        }
    }

    /**
     * @event show 
     */
    function doShow(UXWindowEvent $e = null)
    {
        $this->combobox->selectedIndex = (str::isNumber($index = $this->ini->get("defaultTheme")) && $index <= count($this->combobox->items)) ? $index : 0;
        $this->comboboxServer->selectedIndex = (str::isNumber($index = $this->ini->get("defaultServer")) && $index <= count($this->comboboxServer->items)) ? $index : 0;
        $this->checkboxAddInfo->stylesheets->add(".theme/little-checkbox.fx.css");

        if (in_array("runUpdater", $GLOBALS["argv"])) {
            $this->loadForm("UpdaterForm");
            return $this->changeTheme($this->combobox->selectedIndex, "UpdaterForm", true);
        }
        (!in_array("updatemsg", $GLOBALS["argv"])) ?: $this->toast("Обновление установлено!" . PHP_EOL .
        "Пожалуйста, сообщайте в тему программы на форуме о пожеланиях и возникших багах/недоработках (если таковые вдруг имеются :P).", "3s");
        #(!in_array("updatemsg", $GLOBALS["argv"])) ?: $this->showMessage("Обновление установлено!" . PHP_EOL .
        #"В новой версии совершена кардинальная работа над дизайном, также было переработано и добавлено множество других вещей." . PHP_EOL .
        #"Поэтому важна обратная связь: сообщайте в тему программы на форуме (или мне там же в ЛС) о возникших багах/недоработках или разного рода странностей в интерфейсе, если таковые вдруг обнаружатся. Благодарю за понимание <3", "info", false);

        (!fs::exists("debug.log")) ?: fs::delete("debug.log");
        (!fs::exists("UpdaterCopy.exe")) ?: waitAsync("3s", function () { fs::delete("UpdaterCopy.exe"); });

        if ($cookie = $this->ini->get("cookie")) {
            $this->cookie->text = $cookie;
        } else {
            $this->changeUiState(false);
            $this->cookie->requestFocus();
        }

        #new Ext4JphpWindows()->addShadow($this);
        $this->myTitle->text = ($title = $this->ini->get("title")) ? $title : "";
        $this->myNick->text = $this->defineMyNickname();
        $this->setDate();
        $this->setTimerUntilNewDay();
        $this->checkbox->selected = $this->ini->get("checkbox");
        $this->checkboxObs->selected = $this->ini->get("checkboxObs");
        $this->checkboxFollow->selected = $this->ini->get("checkboxFollow");
        $this->copyObserver($this->checkboxObs->selected);
        $cellRender = function (UXListCell $cell, $item) {
            $title = new UXLabel($item);
            if ($item == "Тёмная") $title->style = "-fx-text-fill: black; -fx-font-weight: bold;";
            $cell->graphic = $title;
        };
        $this->combobox->onCellRender($cellRender);

        $count = count($exp = explode("\\", fs::abs("./")));
        if ($count >= 2 || $count >= 3)
            if ($exp[count($exp) - 2] == "Temp" || $exp[count($exp) - 3] == "Temp")
                $this->showMessage("Предположительно, вы запустили программу прямиком из архива, не распаковав её (она находится в характерной для этого папке Temp)." . PHP_EOL. 
                "Настоятельно рекомендую распаковать для удобного использования и сохранения всех настроек при последующих запусках.", "warning");

        $this->checkUpdates();
        $this->makeMinimizable(false);
    }

    /**
     * @event checkboxAddInfo.click-Left 
     */
    function doCheckboxAddInfoClickLeft(UXMouseEvent $e = null)
    {
        $e->sender->text = ($e->sender->selected) ? "Доп. инфа (6 пункт):" : "Указать дополнительную информацию к жалобе (6-й пункт)";
        $this->additionalInfo->visible = 
        $this->additionalInfo->enabled = $e->sender->selected;
        (!$e->sender->selected) ?: $this->additionalInfo->requestFocus();
        if ($this->ini->get("notifiedBr") != "1") {
            $this->showMessage('Для переноса строки используйте тег <br>, пример: "Строка<br>Новая строка".' . PHP_EOL .
            "Это сообщение не будет показываться в дальнейшем!");
            $this->changeIni("notifiedBr", "1");
        }
    }

    /**
     * @event checkbox.click-Left 
     */
    function doCheckboxClickLeft(UXMouseEvent $e = null)
    {
        $this->changeIni("checkbox", $e->sender->selected);
    }

    /**
     * @event checkboxFollow.click-Left 
     */
    function doCheckboxFollowClickLeft(UXMouseEvent $e = null)
    {    
        $this->changeIni("checkboxFollow", $e->sender->selected);
    }

    /**
     * @event checkboxObs.click-Left 
     */
    function doCheckboxObsClickLeft(UXMouseEvent $e = null)
    {
        $this->changeIni("checkboxObs", $e->sender->selected);
        $this->copyObserver($e->sender->selected);
    }

    /**
     * @event checkboxSelect.click-Left 
     */
    function doCheckboxSelectClickLeft(UXMouseEvent $e = null)
    {
        if (!$e->sender->selected) {
            $this->labelCountScreenshots->opacity = 1.0;
            $this->numberField->enabled = true;
            if (!empty($this->scrSelected)) {
                unset($this->scrSelected);
                $this->toast("Вы отменили ручной выбор скриншотов." . PHP_EOL . PHP_EOL .
                "Теперь будущая жалоба вновь будет состоять из последнего(их) скриншота(ов).", "1s");
                $this->setDate();
            }
            return true;
        }

        if (!is_array($res = $this->getLatestScr(1)))
            return $e->sender->selected = false;

        $pf = $this->form("PreviewForm");
        $pf->show();
        $pf->centerOnScreen();
        $this->changeTheme($this->combobox->selectedIndex, "PreviewForm", true);
        $this->transpForm = new UXForm();
        $tf = $this->transpForm;
        $tf->style = "TRANSPARENT";
        $tf->layout->backgroundColor = "transparent";
        $tf->transparent = true;
        $tf->resizable = false;
        $this->loadTable();

        $pf->buttonSave->on("click", function() use ($pf) {
            $this->scrSelected = [];
            foreach ($pf->table->items as $item)
                if ($item["checkbox"]->children[0]->selected) $this->scrSelected[] = $_ENV["APPDATA"] . "\\.vimeworld\\" . str::lower($this->comboboxServer->selected) . "\\screenshots\\" . $item["screen"];
            if (empty($this->scrSelected)) {
                return $pf->toast("Невозможно сохранить результат: не выбрано ни одного скриншота.");
            } else {
                if ($pf->sortType->selectedIndex == 0) $this->scrSelected = array_reverse($this->scrSelected);
                Animation::fadeOut($pf, 500, function () use ($pf) {
                    $pf->free();
                });
                $this->toast("Готово. Теперь будущая жалоба будет состоять из только что выбранных вами скриншотов." . PHP_EOL . PHP_EOL .
                "Если передумали - просто снимите галочку, если хотите выбрать заново - нажмите её снова." . PHP_EOL . 
                "Также не забудьте удостовериться, что ваш ник на выбранных скринах совпадает с текущим значением.", "3s");
                $this->labelCountScreenshots->opacity = 0.5;
                $this->numberField->enabled = false;
                foreach ($this->scrSelected as $screen) {
                    $dates[] = new Time(fs::time($screen), TimeZone::of("Europe/Moscow"))->toString("dd.MM.yyyy");
                    $dates = array_unique($dates);
                    $this->date->text = implode(", ", $dates);
                    #$this->date->maxLength = 25;
                }
            }
        });

        $pf->buttonClose->on("click", function() use ($pf) {
            $pf->free();
            $this->toast("Вы вышли из окна ручного выбора скриншотов, выбор не был сохранён." . PHP_EOL . PHP_EOL .
            "Для сохранения результата используйте предназначенную кнопку.", "3s");
            $this->checkboxSelect->selected = false;
        });
    }

    /**
     * @event combobox.action 
     */
    function doComboboxAction(UXEvent $e = null)
    {
        if ($e->sender->popupVisible) {
            $e->sender->hidePopup();
            $this->changeIni("defaultTheme", $e->sender->selectedIndex);
            Animation::fadeOut($this, 350, function () {
                $this->changeTheme($this->combobox->selectedIndex);
                Animation::fadeIn($this, 350);
            });
        } else $this->changeTheme($e->sender->selectedIndex);
        $this->panel->requestFocus();
    }

    /**
     * @event comboboxServer.action 
     */
    function doComboboxServerAction(UXEvent $e = null)
    {
        if ($e->sender->popupVisible) $this->changeIni("defaultServer", $e->sender->selectedIndex);
        $this->defaultServer = $e->sender->selected;
        $this->panel->requestFocus();
    }

    /**
     * @event comboboxServer.click-Left 
     */
    function doComboboxServerClickLeft(UXMouseEvent $e = null)
    {    
        if ($this->checkboxSelect->selected) {
            if ($e->sender->popupVisible) $e->sender->hidePopup();
            $this->toast("Вы не можете менять сервер при активном ручном выборе скриншотов!", "1s");
        }
    }

    /**
     * @event buttonHide.action 
     */
    function doButtonHideAction(UXEvent $e = null)
    {
        app()->minimizeForm("MainForm");
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
