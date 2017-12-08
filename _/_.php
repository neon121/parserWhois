<?php
define ('DEBUG', false);
define ('MYSQL_HOST', 'localhost');
define ('MYSQL_USER', 'root');
define ('MYSQL_PASSWORD', 'zQCV5322XRsJ');
define ('MYSQL_DATABASE', 'parserWhois');
define ('IS_CONSOLE', PHP_SAPI == 'cli' || !(isset($_SERVER['DOCUMENT_ROOT']) && isset($_SERVER['REQUEST_URI'])));
define ('LOG_FILE', 'log.txt');
define ('LOG_DATEFORMAT', 'd.m.Y H:i:s');
define ('PHP_PATH','/usr/bin/php7.0');
define ('PROJECT_PATH', '/var/www/parserWhois');
define ('PHP_BASH', PHP_PATH . ' -q -d max_execution_time=60 -f '.PROJECT_PATH);
define ('LOG_PATH', PROJECT_PATH . '/' . LOG_FILE);
define ('START_TIME', microtime(true));

IF (THREAD_TYPE != 'INDEX') {
    ob_start();
    register_shutdown_function('exitFunction');
}
spl_autoload_register(function ($class) {include PROJECT_PATH."/_/$class.php";});

function setMySQLEncoding() {
    global $db;
    $db->query("SET CHARACTER SET 'utf8'");
    $db->query("SET character_set_connection = 'utf8'");
    $db->query("set character_set_results =  'utf8'");
    $db->query("set character_set_server = 'utf8'");
}

function query($query) {
    global $db;
    $result = $db->query($query);
    if ($db->errno != 0) {
        loger('MYSQL ERROR ('.$db->errno.'): '.$db->error . " | QUERY: $query");
        exit;
    }
    else return $result;
}

function loger($msg) {
    if (THREAD_TYPE == 'PARSER') $msg = THREAD_TYPE . '('.THREAD_ID.')' . $msg;
    else $msg = THREAD_TYPE . ' ' . $msg;
    if (!IS_CONSOLE OR THREAD_TYPE == 'INDEX') echo $msg ."\n";
    $file = fopen(LOG_PATH, 'a+');
    chmod(LOG_PATH, 0777);
    $string = date(LOG_DATEFORMAT) . ": $msg\n";
    fwrite($file, $string);
    fclose($file);
}

function exitFunction($msg = 'Выход') {
    if (!defined('ALREADY_EXITED')) define ('ALREADY_EXITED', true);
    else exit;
    if (IS_CONSOLE AND ob_get_length() > 0) loger(ob_get_clean());
    if (DEBUG) loger($msg);
    if (THREAD_TYPE == 'MAIN') setRunning (false);
    elseif (THREAD_TYPE == 'PARSER' && defined('THREAD_ID')) query('DELETE FROM Thread WHERE id='.THREAD_ID);
    global $db;
    $db->close();
    exit;
}

function setRunning($status) {
    global $db;
    $db->query('UPDATE Options SET value = ' . ((int)$status) . ' WHERE name = \'isRunning\'');
}

function isRunning() {
    //проверяет, запущен ли ДРУГОЙ головной поток, чтобы не запустить 2 головных одновременно
    return getOption('isRunning');
}
function isStoped() {
    return getOption('isStoped');
}

function getThreadsCount() {
    return query('SELECT SQL_NO_CACHE COUNT(id) FROM Thread WHERE 1')->fetch_array()[0];
}

function getThreadMax() {
    return getOption('threadMax');
}

function getOption($name) {
    return query("SELECT value FROM Options WHERE name='$name'")->fetch_array()[0];
}
function setOption($name, $value) {
    query("UPDATE Options SET value = '$value' WHERE name='$name'");
}

function getParserOutput($handler) {
    echo "<PARSER>\n";
    while (!feof($handler)) echo fread($handler, 100);
    echo "</PARSER>\n";
}

function formatMessage($total, $parsed, $nodata, $deletedByMail, $deletedByWords, $deletedByFails) {
    $html = '';
    $html .= "<table>";
    $html .= "Парсинг завершен<br/>";
    $html .= "<tr><td>Всего проверено доменов<td>$total";
    $html .= "<tr><td>Удалены из-за одинакового емейла<td>$deletedByMail";
    $html .= "<tr><td>Удалены по стоп словам<td>$deletedByWords";
    $html .= "<tr><td>Удалены из-за вероятно неформатного домена<td>$deletedByFails";
    $html .= "<tr><td>Не удалось получить данные для<td>$nodata";
    $html .= "<tr><td>Данные сохранены для<td>$parsed";
    $html .= "</table>";
    return $html;
}

function sendMail($message) {
    $headers = [];
    $headers[] = 'From: whoisParser';
    $headers[] = 'Content-type: text/html; charset=utf8';
    mail(getOption('email'), 'Парсер Whois', $message, implode("\r\n", $headers) . "\r\n");
}

function isTimeOut($strict = false) {
    if ($strict) $max = 2;
    else $max = 55;
    return (microtime(true) - START_TIME > $max);
}

function deCFEmail($c){
   $k = hexdec(substr($c,0,2));
   for($i=2,$m='';$i<strlen($c)-1;$i+=2)$m.=chr(hexdec(substr($c,$i,2))^$k);
   return $m;
}

function getStoplist() {
    $result = query('SELECT * FROM Stoplist WHERE 1');
    $return = [];
    while ($arr = $result->fetch_array()) $return[] = $arr[0];
    return $return;
}