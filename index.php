<?php
define('THREAD_TYPE','INDEX');
require '_/_.php';
if (isset($_GET['getlog'])) {
    echo "<pre style='font-family: monospace;'>".htmlentities(@file_get_contents(LOG_PATH))."</pre>";
    exit;
}

$inf = [];
$db = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD);
$db->select_db(MYSQL_DATABASE);
if ($db->errno != 0) $inf['mysql'] = "Ошибка(".$db->errno."): ".$db->error;
else $inf['mysql'] = 'OK';
if (!defined('PHP_VERSION_ID') OR PHP_VERSION_ID >= 70000) $inf['php'] = 'ОК';
else $inf['php'] = 'Версия '. phpversion(). ' недостаточна!';
if (function_exists('curl_init')) $inf['curl'] = 'ОК';
else $inf['curl'] = 'не установлен, парсер не будет работать';
$q = (new TesseractOCR('_/_image.png'))->run();
if ($q != "8055") $inf['tesseract'] = 'возможно, не установлен';
else $inf['tesseract'] = 'OK';
$a = exec(PHP_PATH . ' -v');
if (strlen($a) == 0) $inf['path'] = 'путь до PHP указан неверно';
else $inf['path'] = 'OK';
$h = @fopen(LOG_PATH , 'a+');
@chmod(LOG_PATH, 0777);
if ($h) fclose($h);
if (!$h) $inf['log'] = 'Нет прав на запись, логи не будут записаны';
else $inf['log'] = 'OK';
$h = @fopen(PROJECT_PATH . '/tmp/1' , 'a+');
if ($h) {
    fclose($h);
    unlink (PROJECT_PATH . '/tmp/1');
}
if (!$h) $inf['tmp'] = 'Нет прав на запись в папке tmp, не удастся скачать изображения для распознавания';
else $inf['tmp'] = 'OK';

$status = '';
if ($inf['mysql'] == 'OK') {
    setMySQLEncoding();
    if (count($_POST)) {
        if (isset($_POST['threadMax'])) setOption ('threadMax', $_POST['threadMax']);
        elseif (isset($_POST['resetFlag'])) {
            setOption ('isRunning', 0);
            query('TRUNCATE Thread');
        }
        elseif (isset($_POST['changeStopFlag'])) setOption ('isStoped', getOption('isStoped') == 1 ? 0 : 1);
        elseif (isset($_POST['email'])) setOption('email', $_POST['email']);
        elseif (isset($_POST['checkEmail'])) sendMail('Тестовое сообщение. Если вы можете его прочитать, отправка сообщений парсера работает');
        elseif (isset($_POST['stoplist'])) {
            $db->query('TRUNCATE Stoplist');
            $stoplist = explode("\r\n", $_POST['stoplist']);
            foreach ($stoplist as $word) $db->query("INSERT INTO Stoplist VALUES('$word')");
            setOption('hasNewStoplist', 1);
        }
        elseif (isset($_POST['clearLogs'])) unlink (LOG_PATH);
        elseif (isset($_POST['refreshProxies'])) {
            if ($_FILES['proxies']['error'] == 0) {
                $raw = file_get_contents($_FILES['proxies']['tmp_name']);
                $proxies = explode("\r\n", $raw);
                if (count($proxies) == 0) $status = 'Прокси не обнаружены. Неверный формат файла?';
                else {
                    query('TRUNCATE Proxy');
                    foreach ($proxies as $proxy) if (strlen($proxy) > 0) {
                        query("INSERT INTO Proxy(ip) VALUES('{$proxy}')");
                    }
                    $status = 'Прокси обновлены. Загружено ' . (count($proxies) - 1) . ' ИП-адресов';
                }
                
            }
            else $status = "Ошибка загрузки файла " . $_FILES['proxies']['error'];
        }
        if (isset($_POST['checkEmail'])) $status = 'Письмо отправлено';
        elseif (isset($_POST['clearLogs'])) $status = 'Лог удален';
        elseif ($status == "") $status = 'Изменения внесены';
    }
    $inf['cron'] = (time() - getOption('lastMainThreadRun') < 120) ? 'OK' : 'Последний запуск был давно';
    $inf['d_total'] = $db->query('SELECT COUNT(id) FROM domains WHERE 1')->fetch_array()[0];
    $inf['d_parsed'] = $db->query('SELECT COUNT(id) FROM domains WHERE isParsed=1')->fetch_array()[0];
    $inf['d_whois'] = $db->query('SELECT COUNT(id) FROM domains WHERE isWhois=1')->fetch_array()[0];
    $inf['d_new'] = $db->query('SELECT COUNT(id) FROM domains WHERE isNewParse=1')->fetch_array()[0];
    $inf['threads'] = $db->query('SELECT COUNT(id) FROM domains WHERE isNewParse=1')->fetch_array()[0];
    
    $inf['stoplist'] = array();
    $inf['isRunning'] = getOption('isRunning') == "1" ? 'Да' : 'Нет';
    $stoplist = $db->query('SELECT word FROM Stoplist WHERE 1');
    while ($word = $stoplist->fetch_array()) $inf['stoplist'][] = $word[0];
    $inf['stoplist'] = implode("\n", $inf['stoplist']);
}
?><!DOCTYPE HTML>
<html>
    <head>
        <title>Парсер Whois</title>
        <style>
            * {
                font-family: Calibri;
                font-size: 14px;
            }
            form {
                font-size:10px;
                display:inline;
                line-height: 10px;
            }
            table {
                table-layout: fixed;
                border-collapse: collapse;
                margin-bottom:20px;
                margin-right:10px;
                display:inline-table;
                vertical-align: top;
            }
            table tr td {
                border-bottom: 1px solid #dedede;
                vertical-align: top;
            }
            table tr td:first-child {
                padding-right:5px;
                width: 170px;
            }
            #stoplist td, #log td {border: 0;}
            textarea {
                width:280px;
                height: 251px;
                border-color: #dedede;
            }
            .noborder td {
                border-bottom: 0;
                font-size:15px;
            }
            #options tr td {
                padding-bottom:8px;
            }
            #log {width: 99%}
            pre {
                font-family: monospace;
                width:100%;
                border-top:1px solid #dedede;
            }
        </style>
    </head>
    <body>
        <div id="reload">
            <form method="GET" action=""><input type="submit" value="Обновить"/></form>
            <?=$status?>
        </div>
        <table id="diagnostic">
            <tr><td>Соединение с БД<td><?=$inf['mysql']?>
            <tr><td>Путь до интерпретатора PHP <br/>(<?=PHP_PATH?>)<td><?=$inf['php']?>
            <tr><td>Версия PHP <br/>(<?=phpversion()?>)<td><?=$inf['path']?>
            <tr><td>php_curl<td><?=$inf['curl']?>
            <tr><td>Tesseract<td><?=$inf['tesseract']?>
            <tr><td>Права на запись логов <br/><?=LOG_FILE?><td><?=$inf['log']?>
            <tr><td>Права на запись в папке tmp<td><?=$inf['tmp']?>
            <? if ($inf['mysql'] == 'OK') {?>
            <tr><td>Cron<td><?=$inf['cron']?></tr>
            <tr class="noborder"><td><td>&nbsp;
            <tr><td>Всего мейлов в базе<td><?=$inf['d_total']?>
            <tr><td>Whois проверен для<td><?=$inf['d_parsed']?>
            <tr><td>из них успешно<td><?=$inf['d_whois']?>
            <tr><td>Новые данные за последнее время<td><?=$inf['d_new']?>
            <? } ?>
        </table>
        <? if ($inf['mysql'] == 'OK') {?>
        <table id="options">
            <tr><td>Потоков в работе сейчас<td><?=getThreadsCount()?>
            <tr><td>Максимум потоков<td>
                <form method="POST" action="">
                    <input type="text" name="threadMax" value="<?=getThreadMax()?>"/>
                    <input type="submit" name="change_threadMax" value="Изменить"/><br/>
                    Чем больше потоков, тем выше скорость, но слишком большое количество <br/>
                    может привести к недостатку памяти сервера, а так же к многочисленным банам прокси. 
                </form>
            <tr><td>Парсер работает сейчас<td><?=getOption('isRunning') == 1 ? 'Да' : 'Нет'?>
                <form method="POST" action="">
                    <input type="submit" name="resetFlag" value="Сброс флага работы"/><br/>
                    Если сервер перезагружался во время работы парсера, либо скрипт переносился на другой сервер, <br/>
                    возможно зависание флага работы скрипта, из-за чего он никогда не начнет работу. <br/>
                    Если это случилось, сброс флага может помочь.
                </form>
            <tr><td>Парсер включен<td><?=getOption('isStoped') == 1 ? 'Нет' : 'Да'?>
                <form method="POST" action="">
                    <input type="submit" name="changeStopFlag" value="переключить"/><br/>
                    Перед внесением изменений в работу парсера, сменой прокси листа или переносом к другому хостеру<br/>
                    рекомендуется выключить и подождать несколько секунд.
                </form>
            <tr><td>Емейл для отчетов<td>
                <form method="POST" action="">
                    <input type="text" name="email" style="width:300px" value="<?=getOption('email')?>"/>
                    <input type="submit" name="changeEmail" value="Изменить"/>
                </form>
            <tr><td><td colspan="2"><form method="POST" action="">
                <input type="submit" name="checkEmail" value="Тестовая отправка письма"/><br/>
                Скорее всего, письма будут попадать в папку Спам. У скрипта нет возможности проверить,
                <br/>совершена ли отправка в действительности.
            </form>
        </table>
        <table id="stoplist">
            <tr><td colspan="2">Стоп-слова<br/>
                <form method="POST" action="">
                    <textarea name="stoplist"><?=$inf['stoplist']?></textarea><br/>
                    <input type="submit" name="refreshStoplist" value="Обновить">
                </form>
            <tr>
                <td colspan="2">
                    Обновление прокси-листа<br/>
                    <form action="" method="POST" enctype="multipart/form-data">
                        <input name="proxies" type="file" required="required" title="Прокси" />
                        <input type="submit" name="refreshProxies" value="OK"/>
                    </form>
                </td>
            </tr>
        </table>
        <div></div>
        <table id="log">
            <tr><td>Логи парсера
                <form method="POST" action="">
                    <input type="submit" name="clearLogs" value="Очистить"/>
                </form>
            <tr><td>
                <?php if (@filesize(LOG_PATH) > 100 * 1024) {?>
                    Файл логов больше 100кб. 
                    <a href="?getlog" target="_blank">Открыть в новом окне</a>
                <?php } else { ?>
                    <pre><?= htmlentities(@file_get_contents(LOG_PATH))?></pre>
                <?php } ?>
        </table>
        <? } ?>
    </body>
</html>