<?php

use Monolog\Logger;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Handler\FingersCrossed\ErrorLevelActivationStrategy;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\NativeMailerHandler;
use Monolog\Handler\StreamHandler;
use Tmd\AutoGitPull\Deployer;

require 'vendor/autoload.php';

$deployer = new Deployer([
        'directory' => '/var/www/mysite/'
    ]);

$logger = new Logger('deployment');

// Output log messages to screen
$logger->pushHandler(
    new StreamHandler("php://output")
);

// Write all log messages to a log file
$logger->pushHandler(
    new RotatingFileHandler('/var/log/mysite-deploy.log')
);

// Send an email if there's an error
$logger->pushHandler(
    new FingersCrossedHandler(
        new NativeMailerHandler('anthony@example.com', 'Deployment Failed', 'anthony@localhost', Logger::DEBUG),
        new ErrorLevelActivationStrategy(Logger::ERROR)
    )
);

$deployer->setLogger($logger);

$deployer->deploy();