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
 * Based on deployment script by Iain Gray igray@itgassociates.com
 * https://bitbucket.org/itjgray/bitbucket-php-deploy.git
 *
 * Based on deployment script by Brandon Summers
 * http://brandonsummers.name/blog/2012/02/10/using-bitbucket-for-automated-deployments/
 *
*/

namespace Tmd\AutoGitPull;

use Exception;

class Deployer
{

    //User options

    /**
    * A callback function to call after the deploy has finished.
    *
    * @var closure
    */
    public $postDeployCallback;

    /**
     * The name of the deploy script to run
     * @var string
     */
    private $pullScriptPath;

    /**
     * The username to run the deployment under
     * @var string
     */
    private $deployUser;

    /**
    * Directory to store logs in, with a trailing slash.
    * Set to false to disable logging.
    * @var string
    */
    private $logDirectory = false;

    /**
     * Log file name in the log directory.
     * Populated in the constructor.
     * @var string
     */
    private $logFile = false;

    /**
     * Which IPs can trigger the deployment?
     * (PHP CLI is always allowed)
     *
     * Bitbucket IPs were found here:
     * https://confluence.atlassian.com/display/BITBUCKET/What+are+the+Bitbucket+IP+addresses+I+should+use+to+configure+my+corporate+firewall
     * on Feb 29th 2014
     *
     * @var array of IP addresses
     */
    private $allowedIpRanges = array(
        '131.103.20.160/27', // Bitbucket
        '165.254.145.0/26', // Bitbucket
        '104.192.143.0/24', // Bitbucket
        '192.30.252.0/22', // Github
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
    * @param  array   $options  Array of options to set or override
    */
    public function __construct($options = array())
    {
        $possibleOptions = array(
            'pullScriptPath',
            'deployUser',
            'directory',
            'logDirectory',
            'branch',
            'dateFormat',
            'notifyEmails',
            'allowedIpRanges'
        );

        foreach ($options as $option => $value) {
            if (in_array($option, $possibleOptions)) {
                $this->{$option} = $value;
            }
        }

        if (isset($options['additionalAllowedIpRanges'])) {
            $this->allowedIpRanges = array_merge($this->allowedIpRanges, $options['additionalAllowedIpRanges']);
        }

        // Set a log filename
        if (!empty($this->logDirectory)) {
            $this->logFile = $options['logDirectory'] . 'auto-git-pull-' . time() . '.log';
        }

        // Should we send emails?
        $this->email = count($this->notifyEmails) > 0;

        // Use the provided script by default
        if (empty($this->pullScriptPath)) {
            $this->pullScriptPath = dirname(__DIR__) . '/scripts/git-pull.sh';
        }
    }

    /**
    * Writes a message to the log file.
    * TODO: Use Monolog
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

    private function logHeaders()
    {
        if (empty($_SERVER)) {
            return false;
        }
        $headers = [];
        foreach ($_SERVER as $name => $value)
        {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[$name] = $value;
            }
        }
        $this->log(print_r($headers, true), 'INFO');
    }

    private function logPostedData()
    {
        // Log POST data
        if (isset($_POST)) {

            if (isset($_POST['payload'])) {
                $_POST['payload'] = json_decode($_POST['payload']);
            }

            $this->log(print_r($_POST, true), 'POST');
        }
    }

    protected function getIp()
    {
        $ip = null;

        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        if (!$ip) {
            return null;
        }

        // If there are multiple proxies, X_FORWARDED_FOR is a comma and space separated list of IPs
        $ip = explode(', ', $ip);

        return $ip[0];
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

                $ip = $this->getIp();
                $this->log("IP is {$ip}");
                $this->logHeaders();
                $this->logPostedData();

                if (!$this->isIpPermitted($ip)) {
                    header('HTTP/1.1 403 Forbidden');
                    throw new Exception($ip.' is not an authorised Remote IP Address');
                }

            }

            // Run the deploy script

            $script = escapeshellarg($this->pullScriptPath)
            . " -b {$this->branch}"
            . " -d {$this->directory}"
            . " -r {$this->remote}";


            $cmd = "{$script} 2>&1";

            if (!empty($this->deployUser)) {
                $cmd = "sudo -u {$this->deployUser} {$cmd}";
            }

            echo "\n" . $cmd;

            $this->log($cmd);
            exec($cmd, $output, $return);

            if ($return !== 0) {
                echo (implode("\n", $output));
                echo $return;
                throw new Exception("Error $return executing shell script");
            } else {
                $this->log("Running deploy shell script...\n" . implode("\n", $output));
                unset ($output);
            }

            if (!empty($this->postDeployCallback)) {
                $callback = $this->postDeployCallback;
                $callback();
            }

            // Log and email
            $this->log('Deployment successful.');
            $this->sendEmails('Deployment successful');

        } catch (Exception $e) {
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

    /**
     * Source: https://gist.github.com/jonavon/2028872
     * @param  [string]  $ip
     * @param  [string]  $range
     * @return boolean
     */
    private function isIpInRange($ip, $range) {
        if (strpos( $range, '/' ) == false) {
            $range .= '/32';
        }
        // $range is in IP/CIDR format eg 127.0.0.1/24
        list( $range, $netmask ) = explode( '/', $range, 2 );
        $range_decimal = ip2long( $range );
        $ip_decimal = ip2long( $ip );
        $wildcard_decimal = pow( 2, ( 32 - $netmask ) ) - 1;
        $netmask_decimal = ~ $wildcard_decimal;
        return ( ( $ip_decimal & $netmask_decimal ) == ( $range_decimal & $netmask_decimal ) );
    }

    private function isIpPermitted($ip) {
        foreach ($this->allowedIpRanges as $range) {
            if ($this->isIpInRange($ip, $range)) {
                return true;
            }
        }
        return false;
    }
}
