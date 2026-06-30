<?php
if(extension_loaded('yaml')) return;

require_once dirname(__DIR__) . '/test/lib/spyc.php';

if(!defined('YAML_UTF8_ENCODING')) define('YAML_UTF8_ENCODING', 1);

if(!function_exists('yaml_parse_file'))
{
    function yaml_parse_file($filename, $pos = 0, &$ndocs = null, $callbacks = array())
    {
        $ndocs = 1;
        return Spyc::YAMLLoad($filename);
    }
}

if(!function_exists('yaml_emit_file'))
{
    function yaml_emit_file($filename, $data, $encoding = YAML_UTF8_ENCODING, $linebreak = null, $callbacks = array())
    {
        return file_put_contents($filename, Spyc::YAMLDump($data, false, false, true)) !== false;
    }
}
