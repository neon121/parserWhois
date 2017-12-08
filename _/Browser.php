<?php
class Browser {
    private $ch, $proxy = false, $header = [];
    
    public function __construct() {
        $this->ch = curl_init();
        curl_setopt_array($this->ch, [
            CURLOPT_AUTOREFERER => true,
            CURLOPT_COOKIESESSION => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_COOKIEFILE => '',
            CURLOPT_PROXYTYPE => CURLPROXY_HTTP,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FORBID_REUSE => 1,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_HEADEROPT =>  CURLHEADER_SEPARATE,
            CURLINFO_HEADER_OUT => true
        ]);
        $this->setHeader([
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            //'Accept-Encoding: gzip, deflate, sdch',
            'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.6,en;q=0.4',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Upgrade-Insecure-Requests: 1',
            'User-Agent: '.(UserAgent::random())    
        ]);
    }
    
    public function setHeader($header) {
        if (is_string($header)) $header = [$header];
        foreach ($header as $name => $value) {
            if (is_numeric($name)) {
                $arr = explode(': ', $value);
                $name = $arr[0];
                $value = $arr[1];
            }
            $this->header[$name] = $value;
        }
        $arr = [];
        foreach ($this->header as $name => $value) $arr[] = "$name: $value";
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $header);
    }
    
    public function getError() {
        if (curl_errno($this->ch) == 0) return false;
        else return (curl_errno($this->ch) . ' ' .curl_error($this->ch));
    }
    
    public function getHeaders() {
        return $this->header;
    }
    
    public function setProxy() {
        $this->proxy = query('SELECT ip FROM Proxy WHERE 1 ORDER BY lastUse ASC LIMIT 0,1')->fetch_array()[0];
        $this->setProxyUse();
        curl_setopt($this->ch, CURLOPT_PROXY, $this->proxy);
    }
    
    public function exec($url, $POST = null) {
        $this->setProxyUse();
        if (!is_null($POST)) {
            curl_setopt($this->ch, CURLOPT_POST, true);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $POST);
        }
        curl_setopt($this->ch, CURLOPT_URL, $url);
        $exec = curl_exec($this->ch);
        $poss = strpos($exec, "\r\n\r\n");
        if ($poss === false) return $exec;
        else {
            $headers = substr($exec, 0, $poss);
            $body = substr($exec, $poss+4);
            return ['headers' => $headers, 'body' => $body];
        }
    }
    
    public function getRequestHeaders() {
        return (curl_getinfo($this->ch, CURLINFO_HEADER_OUT));
    }
    
    protected function setProxyUse() {
        if ($this->proxy) query("UPDATE Proxy SET lastUse = CURRENT_TIMESTAMP WHERE ip = '{$this->proxy}'");
    }
    
    public function __destruct() {
        curl_close($this->ch);
    }
}