<?php

require 'vendor/autoload.php';

$deployer = new TMD\GitDeployer\GitDeployer(array(
	'deployUser' => 'anthony',
	'deployScript' => '/sites/whodel/deployer/git-pull.sh',
	'directory' => '/sites/whodel/',
	'logDirectory' => '/sites/whodel/deployer/log/',
	'notifyEmails' => array(
		'anthonykuske@gmail.com'
	)
));

$deployer->postDeployCallback = function () use ($deployer) {
	echo 'Yay!';
};

$deployer->deploy();
