<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2015 Anthony Kuske <www.anthonykuske.com>
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

use Closure;
use Exception;
use Monolog\Logger;

class Deployer
{
    // User options...

    /**
     * Which IPs can trigger the deployment?
     * (PHP CLI is always allowed)
     *
     * Bitbucket IPs were found here on Oct 26th 2015:
     * https://confluence.atlassian.com/bitbucket/manage-webhooks-735643732.html
     * and here on Dec 11th 2015:
     * https://blog.bitbucket.org/2015/12/03/making-bitbuckets-network-better-faster-and-ready-to-grow/
     *
     * GitHub IPs where found here on Oct 26th 2015:
     * https://help.github.com/articles/what-ip-addresses-does-github-use-that-i-should-whitelist/
     *
     * @var array
     */
    public $allowedIpRanges = array(
        '131.103.20.160/27', // Bitbucket
        '165.254.145.0/26', // Bitbucket
        '104.192.143.0/24', // Bitbucket
        '104.192.143.192/28', // Bitbucket (Dec 2015)
        '104.192.143.208/28', // Bitbucket (Dec 2015)
        '192.30.252.0/22', // GitHub
    );

    /**
     * Git branch to pull
     *
     * @var string
     */
    public $branch = 'master';

    /**
     * The username to run the deployment under
     *
     * @var string
     */
    public $deployUser;

    /**
     * Directory to pull in
     *
     * @var string
     */
    public $directory;

    /**
     * A callback function to call after the deploy has finished.
     *
     * @var Closure
     */
    public $postDeployCallback;

    /**
     * The name of the deploy script to run
     *
     * @var string
     */
    public $pullScriptPath;

    /**
     * Git remote to pull form
     *
     * @var string
     */
    public $remote = 'origin';

    /**
     * Monolog instance for logging.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Create instance
     *
     * @param array $options Array of options to set or override
     *
     * @throws Exception
     */
    public function __construct($options = array())
    {
        $possibleOptions = array(
            'allowedIpRanges',
            'branch',
            'directory',
            'pullScriptPath',
            'deployUser',
        );

        foreach ($options as $option => $value) {
            if (in_array($option, $possibleOptions)) {
                $this->{$option} = $value;
            }
        }

        if (empty($this->directory)) {
            throw new Exception("A directory must be supplied");
        }


        if (isset($options['additionalAllowedIpRanges'])) {
            $this->allowedIpRanges = array_merge($this->allowedIpRanges, $options['additionalAllowedIpRanges']);
        }

        // Use the provided script by default
        if (empty($this->pullScriptPath)) {
            $this->pullScriptPath = dirname(__DIR__) . '/scripts/git-pull.sh';
        }
    }

    /**
     * Set a Monolog instance to use for logging
     *
     * @param Logger $logger
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Sends a message to Monolog.
     *
     * @param string $message The message to write
     * @param int $level One of the levels defined by Monolog (e.g. INFO, DEBUG, ERROR, etc.)
     * @param array $context
     */
    private function log($message, $level = Logger::DEBUG, $context = array())
    {
        if ($this->logger instanceof Logger) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * Write all the HTTP values from $_SERVER to the log file.
     *
     * @return bool
     */
    private function logHeaders()
    {
        if (empty($_SERVER)) {
            return false;
        }
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[$name] = $value;
            }
        }
        $this->log('HTTP Headers', Logger::DEBUG, $headers);
        return true;
    }

    /**
     * Write all the input from $_POST to the log file.
     *
     * @return bool
     */
    private function logPostedData()
    {
        if (isset($_POST['payload'])) {
            $_POST['payload'] = json_decode($_POST['payload']);
        }

        $this->log('POST Data', Logger::DEBUG, $_POST);
        return true;
    }

    /**
     * Return the IP the request is from.
     * (Might be from a proxy or via CloudFlare
     *
     * @return string|null
     */
    protected function getIp()
    {
        $ipAddress = null;

        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ipAddress = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        }

        if (!$ipAddress) {
            return null;
        }

        // If there are multiple proxies, X_FORWARDED_FOR is a comma and space separated list of IPs
        $ipAddress = explode(', ', $ipAddress);

        // Use the first IP
        return $ipAddress[0];
    }

    /**
     * Executes the necessary commands to do the pull.
     *
     * @throws Exception
     */
    public function deploy()
    {
        $this->log('Attempting deployment...');

        if (php_sapi_name() === 'cli') {
            $this->log("Running from PHP CLI");
        } else {
            $ipAddress = $this->getIp();
            $this->log("IP is {$ipAddress}");
            $this->logHeaders();
            $this->logPostedData();

            if (!$this->isIpPermitted($ipAddress)) {
                $this->log($ipAddress . ' is not an authorised Remote IP Address', Logger::WARNING);

                header('HTTP/1.1 403 Forbidden');
                throw new Exception($ipAddress . ' is not an authorised Remote IP Address');
            }
        }

        // Run the deploy script

        $script = escapeshellarg($this->pullScriptPath)
            . " -b {$this->branch}"
            . " -d " . escapeshellarg($this->directory)
            . " -r {$this->remote}";

        $cmd = "{$script} 2>&1";

        if (!empty($this->deployUser)) {
            $cmd = "sudo -u {$this->deployUser} {$cmd}";
        }

        $this->log($cmd, Logger::DEBUG);

        $output = [];
        exec($cmd, $output, $return);

        $this->log("Output from script", Logger::DEBUG, $output);

        if ($return !== 0) {
            $this->log("Deploy script exited with code $return", Logger::ERROR);
            throw new Exception("Deploy script exited with code $return");
        }

        $this->log('Deployment successful.', Logger::NOTICE);

        if (!empty($this->postDeployCallback)) {
            $callback = $this->postDeployCallback;
            $callback();
        }
    }


    /**
     * Check if an IP address is within the given range.
     * Source: https://gist.github.com/jonavon/2028872
     *
     * @param string $ip IPv4 address
     * @param string $range IPv4 range in CIDR notation
     *
     * @return bool
     */
    private function isIpInRange($ip, $range)
    {
        if (strpos($range, '/') == false) {
            $range .= '/32';
        }
        // $range is in IP/CIDR format eg 127.0.0.1/24
        list($range, $netmask) = explode('/', $range, 2);
        $rangeDecimal = ip2long($range);
        $ipDecimal = ip2long($ip);
        $wildcardDecimal = pow(2, (32 - $netmask)) - 1;
        $netmaskDecimal = ~$wildcardDecimal;
        return (($ipDecimal & $netmaskDecimal) == ($rangeDecimal & $netmaskDecimal));
    }

    /**
     * Check if the given IP address is allowed to trigger the pull.
     *
     * @param string $ip IPv4 address
     *
     * @return bool
     */
    private function isIpPermitted($ip)
    {
        foreach ($this->allowedIpRanges as $range) {
            if ($this->isIpInRange($ip, $range)) {
                return true;
            }
        }
        return false;
    }
}
