<?php
$result = $br->exec('https://www.namecheap.com/domains/whois/results.aspx?domain='.$domainName);
preg_match('/var url = "([^"]+)"/', $result['body'], $matches);
if (count($matches)) {
    $br->setHeader('Referer: https://www.namecheap.com/domains/whois/results.aspx?domain='.$domainName);
    $result = $br->exec('https://www.namecheap.com' . $matches[1]);
    if (!$br->getError()) {
        $raw = $result['body'];
        $data = [];
        preg_match('/Registrant Name: ([^\n]+)/', $raw, $matches);
        if (isset($matches[1])) $data['name'] = $matches[1];
        preg_match('/Registrant Phone: ([^\n]+)/', $raw, $matches);
        if (isset($matches[1])) $data['phone'] = $matches[1];
        preg_match('/Registrant Email: ([^\n]+)/', $raw, $matches);
        if (isset($matches[1])) $data['email'] = $matches[1];
        if (count($data) == 3) $gotData = $data;
        else $gotData = "NO_DATA";
    }
    else $gotData = "FAIL";
}
else $gotData = "FAIL";