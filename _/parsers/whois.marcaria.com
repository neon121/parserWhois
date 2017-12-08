<?php
$br->exec('http://whois.marcaria.com?q='.$domainName);
$br->setHeader([
    'Referer: http://whois.marcaria.com/?q='.$domainName,
    'Content-Type: application/json; charset=UTF-8'
]);
$result = $br->exec(
        'http://whois.marcaria.com/Custom/WebServices/WhoisWebService.asmx/PerformWhoisSearchAll', 
        json_encode(['query' => $domainName])
);
$raw = json_decode($result['body']);
if (isset($raw->d)) {
    $raw = explode('\r\n', $raw->d);
    $data = [];
    foreach ($raw as $str) {
        if (strpos($str, "Registrant Name: ") === 0) {
            $data['name'] = substr($str, strlen("Registrant Name: "));
        }
        elseif (strpos($str, "Registrant Phone: ") === 0) {
            $data['phone'] = substr($str, strlen("Registrant Phone: "));
        }
        elseif (strpos($str, "Registrant Email: ") === 0) {
            $data['email'] = substr($str, strlen("Registrant Email: "));
        }
    }
    if (count($data) == 3) $gotData = $data;
    else $gotData = "NO_DATA";
}
else $gotData = "FAIL";