<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2014 Anthony Kuske <www.anthonykuske.com>
 *
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
*
* The above copyright notice and this permission notice shall be included in all
* copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
* SOFTWARE.
*/

/**
 * Automatic git deployment
 *
 * Use this code as a hook after pushing your code to a git repo
 * and it will automatically pull the new commits onto your server.
 * Built for use with BitBucket but it should be easily adaptable to work
 * with GitHub.
 *
 * TODO: Check which branch was pushed to (currently it pulls no
 * matter what branch was pushed to)
 *
 * Deployment script to be run from bitbucket.  This script runs a shell
 * script on the server to do deployment.    It should also run from github
 * with a change to the $_repositoryIp setting, and any other repository
 * that can call a URL on commit.
 *
 * Based on deployment script by Iain Gray igray@itgassociates.com
 * https://bitbucket.org/itjgray/bitbucket-php-deploy.git
 *
 * Based on deployment script by Brandon Summers
 * http://brandonsummers.name/blog/2012/02/10/using-bitbucket-for-automated-deployments/
 *
*/

namespace TMD\GitDeployer;

class GitDeployer
{

	//User options

	/**
	* A callback function to call after the deploy has finished.
	*
	* @var callback
	*/
	public $postDeployCallback;

	/**
	 * The name of the deploy script to run
	 * @var string
	 */
	private $deployScript;

	/**
	 * The username to run the deployment under
	 * @var string
	 */
	private $deployUser;

	/**
	* The name of the file that will be used for logging deployments. Set to
	* FALSE to disable logging.  You need to create the deploy directory if it doesn't exist already.
	* This is set automatically in __construct
	* @var string
	*/
	private $logDirectory = false;
	private $logFile = false;

	/**
	 * Which IPs can trigger the deployment?
	 * (PHP CLI is always allowed)
	 *
	 * Bitbucket IPs were found here:
	 * https://confluence.atlassian.com/display/BITBUCKET/POST+hook+management
	 * on Feb 18th 2014
	 *
	 * @var array of IP addresses
	 */
	private $allowedIPs = array(
		'131.103.20.165', //Bitbucket
		'131.103.20.166', //Bitbucket
	);

	/**
	* The timestamp format used for logging.
	*
	* @link    http://www.php.net/manual/en/function.date.php
	* @var     string
	*/
	private $dateFormat = 'Y-m-d H:i:s';

	/**
	 * Email addresses to send results to
	 * @var string
	 */
	private $notifyEmails = array();

	/**
	 * Directory to pull in
	 * @var string
	 */
	private $directory;

	/**
	 * Git branch to pull
	 * @var string
	 */
	private $branch = 'master';

	/**
	 * Git remote to pull form
	 * @var string
	 */
	private $remote = 'origin';

	//End of user options

	/**
	 * Are we going to send email notifications?
	 * @var boolean
	 */
	private $email = false;

	/**
	 * Holds messages that have been written to the log so we can email them at the end as well.
	 * @var array
	 */
	private $logBuffer = array();

	/**
	* Create instance
	*
	* @param  array   $options 	Array of options to set or override
	*/
	public function __construct($options = array())
	{
		ini_set('display_errors', true);

		$possibleOptions = array(
			'deployScript',
			'deployUser',
			'directory',
			'logDirectory',
			'branch',
			'dateFormat',
			'notifyEmails',
			'allowedIPs'
		);

		foreach ($options as $option => $value) {
			if (in_array($option, $possibleOptions)) {
				$this->{$option} = $value;
			}
		}

		if (!empty($this->logDirectory)) {
			$this->logFile = $options['logDirectory'] . time() . '.log';
		}

		//Should we send emails?
		$this->email = count($this->notifyEmails) > 0;
	}

	/**
	* Writes a message to the log file.
	*
	* @param  string  $message  The message to write
	* @param  string  $type     The type of log message (e.g. INFO, DEBUG, ERROR, etc.)
	*/
	public function log($message, $type = 'INFO')
	{
		$line = "[" . date($this->dateFormat) . "]\t{$type}\t{$message}" . PHP_EOL;

		if ($this->logFile) {
			if (!file_exists($this->logFile)) {
			   // Create the log file
				file_put_contents($this->logFile, '');

				// Allow anyone to write to log files
				chmod($this->logFile, 0666);
			}

			// Write the message into the log file
			file_put_contents($this->logFile, $line, FILE_APPEND);
		}

		if ($this->email) {
			$this->logBuffer[] = $line;
		}
	}

	private function logPostedData()
	{
		//Log POST data
		if (isset($_POST)) {

			if (isset($_POST['payload'])) {
				$_POST['payload'] = json_decode($_POST['payload']);
			}

			$this->log(print_r($_POST, true), 'POST');

		}
	}

	/**
	* Executes the necessary commands to deploy the website.
	*/
	public function deploy()
	{
		try {

			$this->log('Attempting deployment...');

			if (php_sapi_name() === 'cli') {

				$this->log("Running from PHP CLI");

			} else {

				$this->log("IP is {$_SERVER['REMOTE_ADDR']}");
				$this->logPostedData();

				if (!in_array($_SERVER['REMOTE_ADDR'], $this->allowedIPs)) {
					header('HTTP/1.1 403 Forbidden');
					throw new \Exception($_SERVER['REMOTE_ADDR'].' is not an authorised Remote IP Address');
				}

			}

			//Run the deploy script

			$script = $this->deployScript
			. " -b {$this->branch}"
			. " -d {$this->directory}"
			. " -r {$this->remote}";

			$cmd = "sudo -u {$this->deployUser} {$script} 2>&1";
			echo "\n" . $cmd;

			$this->log($cmd);
			exec($cmd, $output, $return);

			if ($return !== 0) {
				echo (implode("\n", $output));
				echo $return;
				throw new \Exception("Error $return executing shell script");
			} else {
				$this->log("Running deploy shell script...\n" . implode("\n", $output));
				unset ($output);
			}

			if (!empty($this->postDeployCallback)) {
				$callback = $this->postDeployCallback;
				$callback();
			}

			//Log and email
			$this->log('Deployment successful.');
			$this->sendEmails('Deployment successful');

		} catch (\Exception $e) {
			//Log and email
			$this->log($e, 'ERROR');
			$this->sendEmails('Deployment script failed');
		}
	}

	private function sendEmails($subject)
	{
		$message = implode('', $this->logBuffer);

		foreach ($this->notifyEmails as $email) {
			mail($email, $subject, $message);
		}
	}
}
