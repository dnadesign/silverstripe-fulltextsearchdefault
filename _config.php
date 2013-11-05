<?php

if(!defined('SOLR_PATH')) {
	define('SOLR_PATH', '/data/solr');
}

if(!defined('SOLR_BASE_URL')) {
	define('SOLR_BASE_URL', 'http://localhost:8983/solr/FulltextSearchDefaultIndex');
}

if(!defined('SOLR_EXTRAS_PATH')) {
	define('SOLR_EXTRAS_PATH', dirname(__FILE__) .'/assets/');
}

Solr::configure_server(isset($solr_config) ? $solr_config : array(
	'host' => 'localhost',
	'indexstore' => array(
		'mode' => 'file',
		'path' => defined('SOLR_PATH') ? SOLR_PATH : BASE_PATH . '/.solr'
	),
	'extraspath' => SOLR_EXTRAS_PATH,
));
