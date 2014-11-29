AutoGitPull
==============================

This allows you to automatically pull your changes to your production when you push to a git repository.
Install this package on your web server and add a 'hook' on GitHub/Bitbucket which notifies (runs) this script when changes are pushed.

There are two important parts:
* A PHP script which Bitbucket or Github will automatically send a request to when you push. (`http://your-site.com/deploy.php` in the examples below)
* A shell script which does the actual pulling. (`git-pull.sh` in the examples below)

The reason for the separation is so you don't need to grant the web user write permission to your files. You just need to allow it to run the one script as a user that does have write permission.

Setup
-----

Install the latest version with `composer require tmd/auto-git-pull`

```
* Create a publicy accessible file which will be called by GitHub/Bitbucket and run the deployment (e.g. `http://your-site.com/deploy.php`) and edit the settings as appropriate. See `deploy.example.php` for an example.

* Allow the user to run the pull script as a user with write permissions:

```
sudo visudo

# Add the line:
# (Edit users and path as appropriate)
# www-data = User the PHP script runs as
# anthony = User the shell script needs to run as to write to the directory
# /sites/whodel/deploy/git-pull.sh = Path to shell script

www-data ALL=(anthony) NOPASSWD: /sites/whodel/deployer/git-pull.sh
```

* Make the shell script executable

```
chmod +x /sites/whodel/deploy/git-pull.sh
```

* Add your server's public key to git repo

Bitbucket: https://confluence.atlassian.com/pages/viewpage.action?pageId=270827678
Github: Haven't tried that yet, good luck

* Change your git 'remote' used to pull from an http url to an SSH one if required
```
cd /sites/whodel
git remote -v
```

If your output looks like this:
```
origin	https://bitbucket.org/antriver/who-deleted-me.git (fetch)
origin	https://bitbucket.org/antriver/who-deleted-me.git (push)
```

Change it, like this:
```
git remote set-url origin git@bitbucket.org:antriver/who-deleted-me.git
```

* Add the hook on Bitbucket:

![Add bitbucket deploy hook](http://img.ctrlv.in/img/53038a61539f9.png)

