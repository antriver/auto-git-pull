AutoGitPull
==============================

Automatically run a `git pull` when changes are pushed to a Git repository.

About
-----

There are two important parts:
* A PHP script which Bitbucket or Github will automatically send a request to when you push. (`http://mysite/deploy.php` in the examples below)
* A shell script which does the actual pulling. (`scripts/git-pull.sh`)

The reason for the separation is so you don't need to grant the web user write permission to your files. You just need to allow it to run the one script as a user that does have write permission.

Setup
-----

### Installation

* Install the latest version with `composer require tmd/auto-git-pull`

* Make the pull script executable (you need to do this if you update the package as well):
```bash
chmod +x vendor/tmd/auto-git-pull/scripts/git-pull.sh
```

* Create a publicy accessible URL on your site which will be called by Github/Bitbucket and run the deployment (e.g. `http://mysite.com/deploy.php`) and set the parameters as appropriate. See `deploy.example.php` for an example.

Example in Laravel:
```php
Route::post('deploy', function()
{
    $deployer = new \Tmd\AutoGitPull\Deployer(array(
        'directory' => '/var/www/mysite/',
        'logDirectory' => '/var/www/whodeletedme/app/storage/logs/deploy/',
        'notifyEmails' => array(
            'anthony@example.com'
        )
    ));
    $deployer->deploy();
});
```

* Add the hook on Bitbucket to run the script:

![Add bitbucket deploy hook](http://img.ctrlv.in/img/53038a61539f9.png)




#### If the web server user does not have write permissions on the directory

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



#### If your repository is private

You need to setup a deployment key so the pull can happen without a password being entered.

* Follow these instructions to add your public key to the git repository:

Bitbucket: https://confluence.atlassian.com/pages/viewpage.action?pageId=270827678
Github: https://developer.github.com/guides/managing-deploy-keys/#deploy-keys

* Change your git remote url from HTTP(S) to SSH if necessary:
```
cd /var/www/mysite
git remote -v
```

If your output looks like this, you're using HTTP(S):
```
origin	https://bitbucket.org/me/mysite.git (fetch)
origin	https://bitbucket.org/me/mysite.git (push)
```

Change it to use ssh, like this:
```
git remote set-url origin git@bitbucket.org:me/mysite.git
```
