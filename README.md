This allows you to automatically deploy your changes into production when you push to a git repo. Install this package on your web server and add a 'hook' in your repo which POSTS to this script.

There are two parts
* A PHP script which Bitbucket or Github will automatically send a request to when you push. (`http://your-site.com/deploy.php` in the examples below)
* A shell script which does the actual pulling. The PHP script calls this script. (`/sites/whodel/deployer/git-pull.sh` in the examples below)

The reason for the separation is so you don't need to grant the web user write permission to your files. You juse need to allow it permission to run the one script as a user that does have write permission.

## Installation

In these examples

1. Install using composer

```json
{
	"repositories": [
		{
			"type": "git",
			"url":  "https://github.com/antriver/GitDeployer"
		}
	],
	"require": {
		"tmd/gitdeployer": "dev-master"
	}
}

```

2. Copy the `usage.php` to a publicly accessible location on your server (e.g. `http://your-site.com/deploy.php`) and edit the settings as appropriate.

3. Allow the user to run the pull script as a user with write permissions:

```
sudo visudo

# Add the line:
# (Edit users and path as appropriate)
# www-data = User the PHP script runs as
# anthony = User the shell script needs to run as to write to the directory
# /sites/whodel/deploy/git-pull.sh = Path to shell script

www-data ALL=(anthony) NOPASSWD: /sites/whodel/deployer/git-pull.sh
```

4. Make the shell script executable

```
chmod +x /sites/whodel/deploy/git-pull.sh
```

5. Add your server's public key to git repo

Bitbucket: https://confluence.atlassian.com/pages/viewpage.action?pageId=270827678
Github: Haven't tried that yet, good luck

6. Change your git 'remote' used to pull from an http url to an SSH one if required
``
cd /sites/whodel
git remote -v
``

If your output looks like this:
```
origin	https://bitbucket.org/antriver/who-deleted-me.git (fetch)
origin	https://bitbucket.org/antriver/who-deleted-me.git (push)
```

Change it, like this:
```
git remote set-url origin git@bitbucket.org:antriver/who-deleted-me.git
```


7. Add the hook on Bitbucket:

![Add bitbucket deploy hook](http://img.ctrlv.in/img/53038a61539f9.png)

=============

Hopefully these quick and dirty instructions get your started. If not, contact me at http://www.anthonykuske.com and I'll be happy to help.

