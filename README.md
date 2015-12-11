# Auto git pull

Automatically pull when changes are pushed to a Git repository.
(Actually it does a `git fetch` followed by `git reset` but that wasn't as catchy.)

## About

There are two important parts:
* A PHP script which Bitbucket or GitHub will automatically send a request to when you push. (`http://mysite/deploy.php` in the examples below)
* A shell script which does the actual pulling. ([`scripts/git-pull.sh`](scripts/git-pull.sh))

The reason for the separation is so you don't need to grant the web user write permission to your files. You just need to allow it to run the one script as a user that does have write permission.

## Setup

* Install the latest version with `composer require tmd/auto-git-pull`

* Make the pull script executable (you need to do this if you update the package as well):
```bash
chmod +x vendor/tmd/auto-git-pull/scripts/git-pull.sh
```
You can have this automatically happen by adding this to your `composer.json`:
```
"scripts": {
    "post-install-cmd": [
        "chmod +x vendor/tmd/auto-git-pull/scripts/git-pull.sh"
    ]
}
```

* Create a publicly accessible URL on your site which will be called by GitHub/Bitbucket and run the deployment (e.g. `http://mysite.com/deploy.php`) and set the parameters as appropriate.

Example showing all the options that can be given and their default values:
The only required option is `directory`
```php
use Tmd\AutoGitPull\Deployer;

require 'vendor/autoload.php';

$deployer = new Deployer([
    // IP addresses that are allowed to trigger the pull
    // (CLI is always allowed)
    'allowedIpRanges' => [
        '131.103.20.160/27', // Bitbucket
        '165.254.145.0/26', // Bitbucket
        '104.192.143.0/24', // Bitbucket
        '104.192.143.192/28', // Bitbucket (Dec 2015)
        '104.192.143.208/28', // Bitbucket (Dec 2015)
        '192.30.252.0/22', // GitHub
    ],

    // These are added to the allowedIpRanges array
    // to avoid having to define the Bitbucket/GitHub IPs in your own code
    'additionalAllowedIpRanges' => [
        '192.168.0.2/24'
    ],

    // Git branch to reset to
    'branch' => 'master',

    // User to run the script as
    'deployUser' => 'anthony',

    // Directory of the repository
    'directory' => '/var/www/mysite/',

    // Path to the pull script
    // (You can provide your own script instead)
    'pullScriptPath' => __DIR__ . '/scripts/git-pull.sh',

    // Git remote to fetch from
    'remote' => 'origin'
]);

$deployer->postDeployCallback = function () {
    echo 'Yay!';
};

$deployer->deploy();
```

Example in Laravel showing minimal options:
```php
Route::post('deploy', function()
{
    $deployer = new \Tmd\AutoGitPull\Deployer(array(
        'directory' => '/var/www/mysite/'
    ));
    $deployer->deploy();
});
```

Example with logging:
```php
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
```

* Add the hook on Bitbucket/GitHub to run the script:

![Add bitbucket deploy hook](http://img.ctrlv.in/img/53038a61539f9.png)


### If the web server user does not have write permissions on the directory

If your webserver runs as a different user than the owner of the files (as is best practise) you need to allow the webserver to do the pull.

* Allow the web server user to run the pull script as a user with write permissions:

```bash
sudo visudo

# Add the line:
# (Edit users and path as appropriate)
# www-data = User the PHP script runs as
# anthony = User the shell script needs to run as to write to the directory
# /var/www/mysite/vendor/tmd/auto-git-pull/scripts/git-pull.sh = Path to shell script

www-data ALL=(anthony) NOPASSWD: /var/www/mysite/vendor/tmd/auto-git-pull/scripts/git-pull.sh
```

* Set the user to run the pull as in the parameters:
```php
$deployer = new \Tmd\AutoGitPull\Deployer(array(
    'deployUser' => 'anthony',
    // ...
));
```


### If your repository is private

You need to setup a deployment key so the pull can happen without a password being entered.

* Generate an sshkey using `ssh-keygen` *for the user that will run the pull script (e.g. `www-data`).

* Follow these instructions to add a deployment key to the git repository:

Bitbucket: https://confluence.atlassian.com/bitbucket/use-deployment-keys-294486051.html
GitHub: https://developer.github.com/guides/managing-deploy-keys/#deploy-keys

* Change your git remote url from HTTPS to SSH if necessary:
```
cd /var/www/mysite
git remote -v
```

If your output looks like this, you're using HTTPS:
```
origin	https://bitbucket.org/me/mysite.git (fetch)
origin	https://bitbucket.org/me/mysite.git (push)
```

Change it to use ssh, like this:
```
git remote set-url origin git@bitbucket.org:me/mysite.git
```
