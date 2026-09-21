<?php
declare(strict_types=1);
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
$path=is_string($path)?rawurldecode($path):'/';
if(str_contains($path,"\0")||preg_match('~(^|/)(?:\.env(?:\.|$)|\.git(?:/|$)|storage(?:/|$)|tests(?:/|$)|project-settings\.key$)~i',$path)) { http_response_code(404); exit; }
$target=realpath(dirname(__DIR__).$path);
$root=realpath(dirname(__DIR__));
if($target!==false && $root!==false && ($target===$root||str_starts_with($target,$root.DIRECTORY_SEPARATOR)) && is_file($target)) return false;
http_response_code(404);
