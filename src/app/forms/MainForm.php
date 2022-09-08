<?php

namespace app\forms;

use php\jsoup\Jsoup;
use httpclient;
use std, gui, framework, app;
use php\time\Time;
use php\time\TimeZone;
use php\time\Timer;

class MainForm extends AbstractForm {

    public $uiState         = true,
           $currentVersion  = "1.0.1",
           $versionUrl      = "https://raw.githubusercontent.com/vsyakoedlyavw/VWFR/main/info.json",
           $createReportUrl = "https://forum.vimeworld.com/forum/41-%D0%B6%D0%B0%D0%BB%D0%BE%D0%B1%D1%8B-%D0%BD%D0%B0-%D0%B8%D0%B3%D1%80%D0%BE%D0%BA%D0%BE%D0%B2/?do=add",
           $userAgentUrl    = "https://whatsmyua.info",
           $imgurCids       = ["ec61be071b16841", "ad338f3eaae9baa", "9f3460e67f308f6", "65bfadb95e040a0", "2421109ee0e8d3d", "c37fc05199a05b7", "70ff50b8dfc3a53", "886730f5763b437", "6cf1cd6f95fe7c8", "4408bab9df4233c"];

    function checkUpdates($try = 0)
    {
        new Thread(function () {
            if (is_array($verInfo = json_decode(trim(file_get_contents($this->versionUrl)), true))) {
                if ($this->currentVersion != $verInfo["actualVersion"] && $verInfo["updateAdvice"]) {
                    uiLater(function () use ($verInfo) {
                        $dialog = new UXAlert("CONFIRMATION");
                        $dialog->title = $this->title . " | Обновление";
                        $dialog->headerText = "Доступно обновление!" . str_repeat(" ", 50); //костыль для расширения окна алерта, без этого текст первой кнопки почему-то не вмещается полностью
                        $dialog->contentText = "Текущая версия: " . $this->currentVersion . PHP_EOL .
                        "Новая версия: " . $verInfo["actualVersion"] . " [изменения: " . (!$verInfo["changelog"] ? "неизвестно" : $verInfo["changelog"]) . "]";
                        $dialog->setButtonTypes($buttons = ["Обновить автоматически", "Позже"]);

                        if ($dialog->showAndWait() == $buttons[0]) {
                            $taskkill = execute('taskkill /f /t /fi "WINDOWTITLE eq Updater" /im javaw.exe');
                            if (str::contains($taskkill->getInput()->readFully(), "SUCCESS")) wait("3s");
                            $exePath = substr($GLOBALS["argv"][0], 1);
                            $dirPath = explode("/", $exePath);
                            $exeName = array_pop($dirPath);
                            $copyPath = implode("/", $dirPath) . "/UpdaterCopy.exe";
                            fs::copy($exePath, $copyPath);

                            if (fs::exists(fs::abs("./UpdaterCopy.exe"))) {
                                $this->toast("Запуск апдейтера, подождите...");
                                execute('UpdaterCopy.exe runUpdater "' . $exeName . '" "' . $verInfo["actualVersion"] . '" "' . fs::hash($exePath) . '"');
                                app()->shutdown();
                            } else {
                                $this->toast("Не удалось создать копию программы для запуска апдейтера о.О" . PHP_EOL .
                                "Пожалуйста, скачайте обновление вручную по открывшейся ссылке или из темы программы.");
                                open($verInfo["downloadUrl"]);
                            }
                        }
                    });
                }
                if ($cids = $verInfo["imgurCids"]) $this->imgurCids = $cids;
             }
        })->start();
    }

    function defineMyNickname()
    {
        return ($username = array_values(array_filter(file($_ENV["APPDATA"] . "\\.vimeworld\\config"), function ($value) {
            return str::startsWith($value, "username:");
        }))) ? explode(":", $username[0])[1] : "";
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
        if (!is_dir(($path = $_ENV["APPDATA"] . "\\.vimeworld\\minigames\\screenshots")))
            return UXDialog::show("Не могу найти папку со скриншотами по пути " . $path, "ERROR");
        foreach (scandir($path) as $file)
            if (pathinfo($finalPath = $path . "\\" . $file)["extension"] == "png") $files[$finalPath] = filemtime($finalPath);
        if (!$files)
            return UXDialog::show("В папке " . $path . " отсутствуют скриншоты", "ERROR");
        asort($files);
        return array_slice(array_keys($files), -$count);
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
                $this->setDate();
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
            if (!in_array($object->id, ["labelCookie", "cookie", "buttonCheckCookie", "labelAuthor"]) || $full) {
                 $object->enabled = $state;
                 if (str::contains($object->id, "button")) $object->opacity = ($state ? 1 : 0.5);
                 if (str::contains($object->id, "day") || str::contains($object->id, "separator")) $object->opacity = ($state ? 0.66 : 0.33);
            }
        }
        $this->uiState = $state;
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
        $response = $forumHttpClient->get($this->createReportUrl)->body();
        $jSoup = Jsoup::parseText($response);

        if (str::startsWith($jSoup->title(), "Please Wait") || str::startsWith($jSoup->title(), "Just a moment") || str::startsWith($jSoup->select(".ray-id")->html(), "Ray ID")) {
            $dialog = new UXAlert("WARNING");
            $dialog->title = $this->title . " | Что-то пошло не так...";
            $dialog->headerText = "Cloudflare не даёт доступ к форуму :C";
            $dialog->contentText = "Если вы используете в данный момент VPN (в виде программы или браузерного расширения), то просто отключите его для продолжения работы." . PHP_EOL .
            "В ином случае, " . ($userAgent ? "получите Cookie заново (после пройденной проверки Cloudflare) и " : "") . "укажите в поле ниже ваш User-Agent браузера:";
            $uaText = new UXTextField(); $uaLabel = new UXLabel(); $urlButton = new UXButton();
            if ($userAgent) {
                $uaText->text = $userAgent;
                $cookieText = new UXTextField(); $cookieLabel = new UXLabel();
                $cookieLabel->text = "Cookie:";
            }
            $uaLabel->text = "User-Agent:";
            $urlButton->text = "Как его узнать?";
            $urlButton->cursor = "HAND";
            $urlButton->textColor = "#4d66cc";
            $urlButton->on("click", function () {
                UXClipboard::setText($this->userAgentUrl);
                UXDialog::show("В буфер обмена помещена ссылка (" . $this->userAgentUrl . "), откройте её в браузере, в котором выполнен вход на форум и скопируйте User-Agent из текстового поля." . PHP_EOL .
                "Либо же просто загуглите: my user agent");
            });
            $root = new UXVBox($userAgent ? [$cookieLabel, $cookieText, $uaLabel, $uaText, $urlButton] : [$uaLabel, $uaText, $urlButton]);
            $root->spacing = 5;
            $root->padding = 10;
            $dialog->expanded = true;
            $dialog->expandableContent = $root;
            $dialog->setButtonTypes($buttons = ["Сохранить", "Закрыть"]);
            if ($dialog->showAndWait() == $buttons[1])
                return $this->toast("Вы отменили " . ($fromReport ? "публикацию жалобы" : "проверку"), 1500);
            $uaText->text = str_ireplace("user-agent: ", "", $uaText->text);
            $this->changeIni("useragent", $uaText->text);
            if ($userAgent) {
                $cookieText->text = str_ireplace("cookie: ", "", $cookieText->text);
                if (is_bool($missing = $this->checkCookieString($cookieText->text))) {
                    $this->cookie->text = $cookieText->text;
                    $this->changeIni("cookie", $cookieText->text);
                } else return UXDialog::show($missing, "ERROR");
            }
            return $this->toast("Сохранено, попробуйте " . ($fromReport ? "создать жалобу" : "выполнить проверку") . " ещё раз", 1500);
        }

        $csrfKey = $jSoup->select("input[name=csrfKey]")->attr("value");
        $plupload = $jSoup->select("input[name=plupload]")->attr("value");
        if ($jSoup->select("a[id=elUserSignIn]")->hasAttr("href"))
            return $this->toast("Cookie недействительны" . ($fromReport ? ", обновите их" : ". Возможно, вы не авторизованы на форуме?"));
        if (strlen($csrfKey) <> 32 || strlen($plupload) <> 32) {
            $this->toast("Не удалось получить нужные данные с форума о.О" . PHP_EOL . "Попробуйте обновить Cookie / проверить соединение с интернетом / повторить попытку.");
            return file_put_contents("debug.log", $response);
        }

        $this->changeIni("cookie", $this->cookie->text);
        if ($formUnlock) $this->changeUiState();

        ($fromReport)
        ? $this->postReport($csrfKey, $plupload, $forumHttpClient)
        : $this->toast("Cookie корректны и сохранены, программа готова к использованию", 2000);
    }

    function uploadImages($filesToUpload)
    {
        $errorMsgs = ["Не удалось загрузить изображение на Imgur o.О" . PHP_EOL . "Попробуйте ещё раз", "Imgur возвратил ошибку "];
        $imgHttpClient = new HttpClient();
        $imgHttpClient->headers = ["Authorization" => "Client-ID " . ($cId = $this->imgurCids[array_rand($this->imgurCids)])];
        //$imgHttpClient->headers = ["Authorization" => "Client-ID " . ($cId = $this->imgurCids[9])];
        $imgHttpClient->requestType = "MULTIPART";
        foreach ($filesToUpload as $file) {
            $response = $imgHttpClient->post("https://api.imgur.com/3/image/", ["image" => new File($file), "description" => ($file == end($filesToUpload) ? "VWFR was here &#10024;" : "")])->body();
            $jsonRes = json_decode($response, true)["data"];
            if (!$dh = $jsonRes["deletehash"]) {
                file_put_contents("debug.log", PHP_EOL . PHP_EOL . $response, FILE_APPEND);
                return (!($eCode = $jsonRes["error"]["code"]) && !($eMessage = $jsonRes["error"]["message"]))
                ? $this->toast($errorMsgs[0]) : $this->toast($errorMsgs[1] . $eCode . ":" . PHP_EOL . $eMessage);
            }
            $deletehashes[] = $dh;
        }
        $imgHttpClient->requestType = "URLENCODE";

        $response = $imgHttpClient->post("https://api.imgur.com/3/album/",
        ["deletehashes" => implode(",", $deletehashes)])->body();

        $this->lastAlbum = ["clientId" => $cId, "deletehash" => json_decode($response, true)["data"]["deletehash"], "deletehashes" => $deletehashes];
        return ($id = json_decode($response, true)["data"]["id"]) ? "https://imgur.com/a/" . $id : $this->toast($errorMsgs[0]);
    }

    function postReport($csrfKey, $plupload, $forumHttpClient)
    {
        if (!is_array($latest = $this->getLatestScr($this->numberField->value)))
            return false;
        if (!parse_url($imgUrl = $this->uploadImages($latest))["host"] || !$imgUrl)
            return false;

        $data = ["form_submitted" => 1,
                "csrfKey" => $csrfKey,
                "MAX_FILE_SIZE" => 9437184, 
                "plupload" => $plupload, 
                "topic_title" => $this->myTitle->text, 
                "topic_content" =>
                    "<p>" . $this->nick->text .
                    "<br>" . $this->myNick->text .
                    "<br>" . $this->date->text . 
                    '<br><a href="' . $imgUrl . '" ipsnoembed="true">' . $imgUrl . "</a></p>",
                "topic_auto_follow" => 0];

        if ($this->checkbox->selected) {
            $dialog = new UXAlert("CONFIRMATION");
            $dialog->title = $this->title . " | Подтверждение";
            $dialog->headerText = "Почти готово, публиковать на форум?";
            $dialog->contentText = "[Заголовок:]" . PHP_EOL . " " . $this->myTitle->text . PHP_EOL . PHP_EOL . "[Жалоба:]" . PHP_EOL . 
            " 1. " . $this->nick->text . PHP_EOL . " 2. " . $this->myNick->text .  PHP_EOL . " 3. " . $this->date->text . PHP_EOL . " 4. " . $imgUrl . PHP_EOL;
            $urlButton = new UXButton();
            $urlButton->text = "Открыть ссылку на скриншот(ы) в браузере";
            $urlButton->cursor = "HAND";
            $urlButton->textColor = "#4d66cc";
            $urlButton->on("click", function () use ($imgUrl) {
                open($imgUrl);
            });
            $root = new UXVBox([$urlButton]);
            $root->paddingLeft = 10;
            $dialog->expanded = true;
            $dialog->expandableContent = $root;
            $dialog->setButtonTypes($buttons = ["Да", "Отменить"]);
            if ($dialog->showAndWait() != $buttons[0]) {
                $this->deleteAlbum();
                return $this->toast("Вы отменили публикацию жалобы");
            }
        }
        $this->changeIni("checkbox", $this->checkbox->selected);

        $forumHttpClient->followRedirects = false;
        $createRequest = $forumHttpClient->post($this->createReportUrl, $data);
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
            // $this->toast("Жалоба опубликована и доступна по ссылке:" . PHP_EOL . explode("-", $location)[0] . "-", 2000);
            $this->timerButton();
        } elseif (str::contains($postRequest->body(), "Установлен лимит на отправку нескольких сообщений за определённое время"))
            UXDialog::show("Не прошло 30 секунд с момента публикации последней жалобы или ответа", "ERROR");
        else UXDialog::show("По неизвестной причине не удалось опубликовать жалобу :C", "ERROR");
    }

    /**
     * @event labelAuthor.click 
     */
    function doLabelAuthorClick(UXMouseEvent $e = null)
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
            waitAsync("1.5s", function () use ($e) {
                $e->sender->enabled = true;
                $e->sender->opacity = 1;
            });
        } else UXDialog::show($missing, "WARNING");
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
        (!$myNick = $this->defineMyNickname()) ? $this->toast("Не удалось определить ваш ник, скорее всего, вы не авторизованы в лаунчере VimeWorld") : $this->myNick->text = $myNick;
    }

    /**
     * @event buttonCreateReport.action 
     */
    function doButtonCreateReportAction(UXEvent $e = null)
    {
        !$this->cookie->text ? $ls[] = "- Не указаны Cookie;" : (is_bool($missing = $this->checkCookieString($this->cookie->text)) ?: $ls[] = "- " . $missing . ";");
        !$this->myTitle->text ? $ls[] = "- Не указан заголовок жалобы (например: «асоц. поведение», «некорректная постройка», или же придумайте какой-нибудь универсальный для всех жалоб);" : (Regex::match("^.{1,100}$", $this->myTitle->text) ?: $ls[] = "- Заголовок жалобы заполнен некорректно, min - 1 символ, max - 100;");
        !$this->nick->text ? $ls[] = "- Не указан(ы) ник(и) нарушителя(ей);" : (Regex::match("^.{3,100}$", $this->nick->text) ?: $ls[] = "- Ник(и) нарушителя(ей) заполнен(ы) некорректно, min - 3 символа, max - 100;");
        !$this->myNick->text ? $ls[] = "- Не указан ник отправителя;" : (Regex::match("^.{3,32}$", $this->myNick->text) ?: $ls[] = "- Ник отправителя заполнен некорректно, min - 3 символа, max - 32;");
         $this->date->text ?: $ls[] = "- Не указана(ы) дата(ы) нарушения;";

        if (is_array($ls)) return UXDialog::show("Вы ошиблись в заполнении некоторых полей:" . str_repeat(PHP_EOL, 2) . implode(PHP_EOL, $ls), "WARNING");

        $this->testCookie($this->cookie->text);
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

    /**
     * @event show 
     */
    function doShow(UXWindowEvent $e = null)
    {
        if (in_array("runUpdater", $GLOBALS["argv"])) return $this->loadForm("UpdaterForm");
        (!in_array("updatemsg", $GLOBALS["argv"])) ?: $this->toast("Обновление установлено!" . PHP_EOL . "Пожалуйста, сообщайте в тему программы о пожеланиях/багах/недоработках.");

        (!fs::exists("debug.log")) ?: fs::delete("debug.log");
        (!fs::exists("UpdaterCopy.exe")) ?: waitAsync("3s", function () { fs::delete("UpdaterCopy.exe"); });

        $this->myNick->text = $this->defineMyNickname();
        $this->setDate();
        if ($cookie = $this->ini->get("cookie")) {
            $this->cookie->text = $cookie;
        } else {
            $this->changeUiState(false);
            $this->cookie->requestFocus();
        }
        $this->myTitle->text = ($title = $this->ini->get("title")) ? $title : "";
        $this->checkUpdates();
        $this->setTimerUntilNewDay();
    }
}