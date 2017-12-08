<?php
$result = $br->exec('https://www.whois-search.com/whois/'.$domainName);
if (!$br->getError()) {
    $raw = $result['body'];
    $data = [];
    preg_match('/Registrant Name: ([^\n]+)/', $raw, $matches);
    if (isset($matches[1])) $data['name'] = $matches[1];
    preg_match('/Registrant Phone: ([^\n]+)/', $raw, $matches);
    if (isset($matches[1])) $data['phone'] = $matches[1];
    preg_match('/Registrant Email: ([^\n]+)/', $raw, $matches);
    if (isset($matches[1])) {
        $raw = $matches[1];
        preg_match('/data-cfemail="([^"]+)"/', $raw, $matches);
        if (isset($matches[1])) {
            $email = deCFEmail($matches[1]);
            if (strpos($email, '@') !== false) $data['email'] = $email;
            else $gotData = 'FAIL';
        }
        else $gotData = 'FAIL';
        
    }
    if (count($data) == 3) $gotData = $data;
    else $gotData = "NO_DATA";
}
else $gotData = 'FAIL';