<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
define('THREAD_TYPE','PARSER');
require '_/_.php';

$db = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD);
$db->select_db(MYSQL_DATABASE);
if (isStoped()) exit;
define('THREAD_ID', $argv[1]);
query('INSERT INTO Thread(id) VALUES(' . THREAD_ID . ')');
setMySQLEncoding();
$query_success = $db->prepare(
        "UPDATE domains SET name = ?, email = ?, phone = ?, isParsed = 1, isWhois = 1, isNewParse = 1 WHERE id = ?"
        );
$query_success->bind_param('sssi', $gotData_name, $gotData_email, $gotData_phone, $domainId);
$query_noData = $db->prepare("UPDATE domains SET isParsed = 1, isNewParse = 1 WHERE id = ?");
$query_noData->bind_param('i', $domainId);
$Punycode = new Punycode();

$domainIds = explode(',', $argv[2]);
foreach ($domainIds as $domainId) {
    if (isStoped()) exit;
    if (isTimeOut()) exit;
    $domain = query("SELECT * FROM domains WHERE id = $domainId");
    if ($domain->num_rows == 0) {
        if (DEBUG) loger ('Не найден домен номер '.$domainId);
        continue;
    }
    $domain = $domain->fetch_object();
    if ($domain->isParsed == 1) {
        continue;
    }
    $domainName = $Punycode->encode($domain->domain);
    if ($domainName === false) {
        loger("Домен $domainId ({$domain->domain}) не может быть преобразован в Punycode и будет удален");
        query("DELETE FROM domains WHERE id = $domainId");
        setOption('deletedByFails', getOption('deletedByFails') + 1);
        continue;
    }
    
    $whoisServices = [
        'whois.domaintools.com',
        'whois.marcaria.com',
        'www.iana.org',
        'www.namecheap.com',
        'www.whois-search.com',
        //'https://who.is', //нужно читать мейл с картинки
    ];
    $whoisService_id = mt_rand(0, count($whoisServices) - 1);
    $br = new Browser();
    $br->setProxy();
    $gotData = false;
    include '_/parsers/'.$whoisServices[$whoisService_id];
    if ($gotData == 'FAIL') {
        if (DEBUG) loger ("FAIL {$whoisServices[$whoisService_id]} $domainName");
        query("UPDATE domains SET fails = fails + 1 WHERE id = $domainId");
        $fails = query("SELECT fails FROM domains WHERE id = $domainId")->fetch_array()[0];
        if ($fails > 5) {
            loger ("$domainName - не удалось получить whois более 5 раз. Удаляем");
            query("DELETE FROM domains WHERE id = $domainId");
            setOption('deletedByFails', getOption('deletedByFails') + 1);
        }
        continue;
    }
    else {
        if (is_array($gotData)) {
            $stoplist = getStopList();
            foreach ($stoplist as $word) if (strpos($gotData['email'], $word) !== false) {//
                if (DEBUG) loger("$domainName ({$gotData['email']}) - есть в стоплисте, удаляем");
                setOption('deletedByWords', getOption('deletedByWords') + 1);
                query("DELETE FROM domains WHERE id=$domainId");
                continue;
            }
            foreach ($gotData as $name => $value) {
                $name = 'gotData_'.$name;
                $$name = $value;
            }
            $result = $query_success->execute();
        }
        else {
            continue;
            //$result = $query_noData->execute();
        }
        if (!$result) {
            loger('MYSQL ERROR ('.$db->errno.'): '.$db->error);
            exit;
        }
    }
    
}