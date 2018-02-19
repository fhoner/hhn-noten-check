<?php

/**
 * Linda Notenchecker
 * sendet Nachricht in Slack Channel wenn die Notenliste sich verändert hat
 * Felix Honer, SEB, HHN
 * 2017-02-19
 */

$config = [
    'linda_username'    => 'fhoner',
    'linda_password'    => '53CUR3',
    'temp_filename'     => 'linda_last.html',
    'temp_lastrun'      => 'lastrun.txt',
    'slack_hook'        => 'https://hooks.slack.com/services/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',
    'slack_channel'     => '#noten',
    'slack_username'    => 'Linda'
];

/**
 * Parse http headers into array.
 * 
 * @param $headers Array from $http_response_header
 */
function parseHeaders($headers) {
    $head = array();
    foreach($headers as $k => $v) {
        $t = explode(':', $v, 2);
        if(isset($t[1]))
            $head[trim($t[0])] = trim($t[1]);
        else {
            $head[] = $v;
            if(preg_match("#HTTP/[0-9\.]+\s+([0-9]+)#", $v, $out))
                $head['reponse_code'] = intval($out[1]);
        }
    }
    return $head;
}

/**
 * Sends a message into a given slack channel.
 * 
 * @param hook Webhook-URL of your Slack instance.
 * @param channel Channelname where to send the message with leading #.
 * @param username The name which will be shown as sender.
 * @param msg Message to send.
 */
function sendSlackMessage(string $hook, string $channel, string $username, string $msg) {
    $json = json_encode([
        'channel'     => $channel,
        'username'    => $username,
        'text'        => $msg
    ]);
    $content = http_build_query(['payload' => $json]);
    $slackReq = array(
        'http'=>array(
          'method'  => 'POST',
          'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
          'content' => $content
        )
    );
    $context = stream_context_create($slackReq);
    $file = file_get_contents($hook, false, $context);
    echo $msg;
}

/**
 * Does the login at Linda to retrieve a valid JSESSIONID.
 * 
 * @return Array Array containing HTTP 302 redirect location and jsessionid.
 */
function login($username, $password) {
    $jsess = "";

    while(strlen($jsess) == 0) {
        $url = 'https://stud.zv.hs-heilbronn.de/qisstudent/rds?state=user&category=auth.login&type=1&startpage=portal.vm&breadCrumbSource=portal';
        $options['http'] = array(
            'method' => "POST",
            'header'    => 'Accept:text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8\r\n' .
                            'Accept-Encoding:gzip, deflate, br\r\n' .
                            'Accept-Language:en-US,en;q=0.9\r\n' .
                            'Cache-Control:max-age=0\r\n' .
                            'Connection:keep-alive\r\n' .
                            'Cookie:' . $jsess . '\r\n' .
                            'Host:stud.zv.hs-heilbronn.de\r\n' . 
                            'Referer:https://stud.zv.hs-heilbronn.de/qisstudent/rds?state=user&type=0\r\n' .
                            'Upgrade-Insecure-Requests:1\r\n' .
                            'Content-Type:application/x-www-form-urlencoded\r\n' . 
                            'User-Agent:Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/64.0.3282.167 Safari/537.36\r\n',
            'content'   => http_build_query([
                'asdf'      => $username,
                'fdsa'      => $password,
                'submit'    => 'Login'
            ])
        );
        $context = stream_context_create($options);
        $login = file_get_contents($url, false, $context);
        $headers = parseHeaders($http_response_header);
        $jsess = $headers['Set-Cookie'];
    }   
    return [
        "sessid" => $headers['Set-Cookie'],
        "location" => $headers['Location']
    ];
}

/**
 * Creates the required request headers for all http requests.
 * 
 * @param cookie Required cookies for the request.
 */
function createRequestHeaders($cookie) {
    return array(
        'http'=>array(
            'method' => "GET",
            'header' => "Accept-language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7,nb;q=0.6\r\n" .
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n" . 
            "Cookie: " . $cookie . "\r\n" .
            "Host: stud.zv.hs-heilbronn.de\r\n" .
            "Pragma: no-cache\r\n" . 
            "Connection: keep-alive\r\n" .
            "DNT: 1\r\n" .
            "Referer: https://stud.zv.hs-heilbronn.xn--dal&startpage=portal-bi53i.vm&chco=y/\r\n" .
            "Upgrade-Insecure-Requests:1\r\n" .
            "User-Agent:Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/64.0.3282.167 Safari/537.36"
        )
    );
}

/* DO THE LOGIN */
$login = login($config['linda_username'], $config['linda_password']);
$jsess = $login['sessid'];

/* GO TO 302 REDIRECT TO ACTIVATE SESSION (REQUIRED) */
$context = stream_context_create(createRequestHeaders($jsess));
$file = file_get_contents($login['location'], false, $context);
$file = preg_replace('<!--(.*?)-->', "", $file);

/* GO TO NOTENSPIEGEL PAGE */
$context = stream_context_create(createRequestHeaders($jsess));
$file = file_get_contents('https://stud.zv.hs-heilbronn.de/qisstudent/rds?state=change&type=1&moduleParameter=studyPOSMenu&nextdir=change&next=menu.vm&subdir=applications&xml=menu&purge=y&navigationPosition=functions%2CstudyPOSMenu&breadcrumb=studyPOSMenu&topitem=functions&subitem=studyPOSMenu', false, $context);
$file = preg_replace('<!--(.*?)-->', "", $file);
preg_match('/<a(.*)&amp;asi=(.*)"  (.*)>Notenspiegel<\/a>/', $file, $groups);
$asi = $groups[2];

/* SHOW CURRENT RESULTS */
$context = stream_context_create(createRequestHeaders($jsess));
$file = file_get_contents('https://stud.zv.hs-heilbronn.de/qisstudent/rds?state=notenspiegelStudent&next=list.vm&nextdir=qispos/notenspiegel/student&createInfos=Y&struct=auswahlBaum&nodeID=auswahlBaum%7Cabschluss%3Aabschl%3D84%2Cstgnr%3D1&expand=0&asi=' . $asi . '#auswahlBaum%7Cabschluss%3Aabschl%3D84%2Cstgnr%3D1', false, $context);
$file = preg_replace('<!--(.*?)-->', "", $file);
$file = str_replace([' ', $jsess, $asi, 'Node1', 'Node2'], "", $file);

/* CHECK IF CHANGES OCCURED */
$filename = $config['temp_filename'];
if (!file_exists($filename)) {      // first check
    file_put_contents($filename, $file);        
    endSlackMessage(
        $config['slack_hook'],
        $config['slack_channel'],
        $config['slack_username'],
        "Notenbot now active :robot_face:"
    );
} else if (strpos("Notenspiegel") !== false) {    // not in maintenance mode
    $last = file_get_contents($filename);
    if ($last != $file) {
        file_put_contents($filename, $file);
        sendSlackMessage(
            $config['slack_hook'],
            $config['slack_channel'],
            $config['slack_username'],
            "Neue Noten sind online :heinz: :winckler:"
        );
    }
}

file_put_contents($config['temp_lastrun'], (new DateTime())->format('Y-m-d H:i:s'));

?>