# 1. Allow web user to run the pull script as a user with write permissions

sudo visudo

# Add the line:
# (Edit users and path as appropriate)
# www-data = user the PHP script runs as
# anthony = user the shell script needs to run as
# /sites/whodel/deploy/git-pull.sh = the shell script

www-data ALL=(anthony) NOPASSWD: /sites/whodel/deployer/git-pull.sh



# 2. Make the shell script executable

chmod +x /sites/whodel/deploy/git-pull.sh



# 3. Add ssh public key to git repo
# Bitbucket: https://confluence.atlassian.com/pages/viewpage.action?pageId=270827678
# Github: Haven't triedt that yet, good luck



# 4. If the git remote was an https:// URL, change it to an SSH URL

cd /sites/whodel
git remote -v

# Output:
# origin	https://bitbucket.org/antriver/who-deleted-me.git (fetch)
# origin	https://bitbucket.org/antriver/who-deleted-me.git (push)

git remote set-url origin git@bitbucket.org:antriver/who-deleted-me.git
