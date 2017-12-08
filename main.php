<?php
define('THREAD_TYPE','MAIN');
require '_/_.php';

$db = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD);
$db->select_db(MYSQL_DATABASE);
setMySQLEncoding();
if (isRunning()) exit;
setRunning(true);
setOption('lastMainThreadRun', time());
$handlers = [];

while (true) {
    if (isStoped() AND IS_CONSOLE) {
        if (DEBUG) loger ('Остановлен');
        exit;
    }
    if (isTimeOut()) {
        if (DEBUG) loger ('Таймаут');
        exit;
    }
    $domains = query('SELECT id FROM domains WHERE isParsed = FALSE ORDER BY RAND() LIMIT 0, ' . getThreadMax() * 20);
    if ($domains->num_rows == 0) { //нет неотпарсеных - 
        // либо пора подводить итоги послднего парсинга, либо домены не добавлялись
        $rows = query('SELECT COUNT(id) FROM domains WHERE isNewParse = TRUE')->fetch_array()[0];
        if ($rows > 0) { //есть домены нового парсинга - то есть, таки был парсинг. Подводим итоги
            if (isTimeOut(true)) { //жесткая проверка таймаута, т.к. запросы к БД могут занять очень много времени
                if (DEBUG) loger ('Таймаут(строгая проверка)');
                exit; 
            }
            //удалить дубликаты по мейлам
            query('CREATE TEMPORARY TABLE t_email as ('
                    . 'SELECT min(id) as id FROM domains WHERE email IS NOT NULL GROUP BY email)');
            $total = query('SELECT COUNT(id) FROM domains WHERE email IS NOT NULL')->fetch_array()[0];
            $inTemp = query("SELECT COUNT(id) FROM t_email WHERE 1")->fetch_array()[0];
            $deletedByMails = $total - $inTemp;
            query('DELETE FROM domains WHERE domains.id NOT IN (SELECT id FROM t_email)');
            setOption('deletedByMails', $deletedByMails);
            
            $parsed = query('SELECT COUNT(id) FROM domains WHERE isWhois = TRUE')->fetch_array()[0];
            $nodata = query('SELECT COUNT(id) FROM domains WHERE isWhois = FALSE')->fetch_array()[0];
            sendMail(formatMessage($rows, $parsed, $nodata, 
                    getOption('deletedByMails'), getOption('deletedByWords'), getOption('deletedByFails')));
            
            query('UPDATE domains SET isNewParse = FALSE WHERE 1');
            setOption('deletedByMails', 0);
            setOption('deletedByWords', 0);
            setOption('deletedByFails', 0);
            loger('Парсинг завершен, письмо отправлено');
        }
        if (getOption('hasNewStoplist')) {
            if (isTimeOut(true)) { //жесткая проверка таймаута, т.к. запросы к БД могут занять очень много времени
                if (DEBUG) loger ('Таймаут(строгая проверка)');
                exit; 
            }
            $stoplist = getStoplist();
            foreach ($stoplist as $word) {
                do {
                    if (isTimeOut()) {
                        if (DEBUG) loger ('Таймаут');
                        exit; 
                    }
                    query("DELETE FROM domains WHERE email LIKE '%$word%' LIMIT 100");
                    $deletedByWord = $db->affected_rows;
                    setOption('deletedByWords', getOption('deletedByWords') + $deletedByWord);
                } while ($db->affected_rows > 0);
            }
            loger ('Домены по стоплисту удалены');
            sendMail("По обновленному стоп листу удалено " . getOption('deletedByWords') . " доменов");
            setOption('hasNewStoplist', 0);
            setOption('deletedByWords', 0);
        }
        break;
    }
    else { //неотпарсенные есть, парсим
        $threadMax = getThreadMax();
        if ($threadMax > $domains->num_rows) $threadMax = $domains->num_rows;
        $domainsPerThread = ceil($domains->num_rows / $threadMax);
        if ($domainsPerThread > 20) $domainsPerThread = 20;
        $thread_id = 1;
        while (getThreadsCount() < $threadMax) {
            if(isStoped() AND IS_CONSOLE) exit;
            for (;; $thread_id++) {
                if (query('SELECT SQL_NO_CACHE * FROM Thread WHERE id='.$thread_id)->num_rows == 0) break;
            }
            $ids = [];
            while (count($ids) < $domainsPerThread && $domain_id = $domains->fetch_array()) $ids[] = $domain_id[0];
            if (count($ids) == 0) break;
            $handler = popen(PHP_BASH."/parser.php -- $thread_id ".implode(',', $ids), 'r');
            sleep(1); //чтобы запускаемый поток успел установить флаг в базе
            if ($handler == false) {
                loger ("Не смог запустить поток");
                exit;
            }
            $handlers[] = $handler;
        }
    }
}