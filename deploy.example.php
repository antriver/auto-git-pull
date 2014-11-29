<?php

require 'vendor/autoload.php';

$deployer = new Tmd\AutoGitPull\Deployer(array(
	'deployUser' => 'anthony',
	'directory' => '/var/www/mysite/',
	'logDirectory' => __DIR__ . '/log/',
	'notifyEmails' => array(
		'me@gmail.com'
	)
));

$deployer->postDeployCallback = function () {
	echo 'Yay!';
};

$deployer->deploy();
