<?php
$result = $br->exec('http://whois.domaintools.com/'.$domainName);
preg_match('/<div class=\'raw well well-sm\'>([\w\W]+?)<\/div>/', $result['body'], $matches);
if (count($matches)) {
    $raw = $matches[1];
    $raw = str_replace("&nbsp;", " ", $raw);
    $raw = html_entity_decode($raw);
    $raw = explode("<br/>", $raw);
    $data = [];
    foreach ($raw as $str) {
        if (strpos($str, "Registrant Name:") === 0) {
            $data['name'] = substr($str, strlen("Registrant Name: "));
        }
        elseif (strpos($str, "Registrant Phone: ") === 0) {
            $data['phone'] = substr($str, strlen("Registrant Phone: "));
        }
        elseif (strpos($str, "Registrant Email: ") === 0) {
            preg_match('/<img src="([^"]+)"/', $str, $matches);
            $url = str_replace(
                    ['size=9',  'color=0000FF', '&format[]=transparent'], 
                    ['size=15', 'color=000000', ''], 
                    $matches[1]);
            if (!$url) {
                $data['email'] = 'CANT_DOWNLOAD';
                break;
            }
            $exec = $br->exec($url);
            if (!$exec['body'] || $br->getError() != 0) {
                $data['email'] = 'CANT_DOWNLOAD';
                break;
            }
            $png = $exec['body'];
            $filename = PROJECT_PATH.'/tmp/'.mt_rand(10000000, 99999999) . '.png';
            $file = fopen($filename, 'a+');
            fwrite($file, $png);
            fclose($file);
            $email = (new TesseractOCR($filename))->psm(7)->run();
            unlink($filename);
            preg_match('/md5=([^&]+)&/', $url, $matches);
            if (md5($email) != $matches[1]) {
                $data['email'] = 'CANT_READ';
                break;
            }
            else $data['email'] = $email;
        }
    }
    if      ($data['email'] == 'CANT_READ')     $gotData = 'NO_DATA';
    elseif  ($data['email'] == 'CANT_DOWNLOAD') $gotData = 'FAIL';
    elseif  (count($data) == 3)                 $gotData = $data;
    else                                        $gotData = "NO_DATA";
}
else $gotData = "FAIL";