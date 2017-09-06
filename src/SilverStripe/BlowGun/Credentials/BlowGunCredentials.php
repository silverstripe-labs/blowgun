<?php

namespace SilverStripe\BlowGun\Credentials;

use Aws\Common\Credentials\Credentials;

class BlowGunCredentials extends Credentials
{
    /**
     * Get the code of the default profile.
     *
     * @return string
     */
    public static function defaultProfile()
    {
        if (!self::checkForCredentialFile()) {
            return '';
        }

        return self::getEnvVar(self::ENV_PROFILE) ?: 'default';
    }

    /**
     * Get the default region from the profile.
     *
     * @param string $profile
     *
     * @throws \RuntimeException
     *
     * @return string Region id
     */
    public static function defaultRegion($profile = null)
    {
        $credentialFile = self::getHomeDir().'/.aws/credentials';
        $configFile = self::getHomeDir().'/.aws/config';
        $configData = [];
        $credentialData = [];

        // try with the default profile
        if (!$profile) {
            $profile = self::defaultProfile();
        }

        if (file_exists($credentialFile)) {
            if (!($credentialData = parse_ini_file($credentialFile, true))) {
                throw new \RuntimeException("Invalid AWS credentials file: {$credentialFile}.");
            }
        }

        if (file_exists($configFile)) {
            if (!($configData = parse_ini_file($configFile, true))) {
                throw new \RuntimeException("Invalid AWS credentials file: {$configFile}.");
            }
        }

        foreach ([$credentialData, $configData] as $data) {
            foreach ([$profile, "profile $profile"] as $section) {
                if (isset($data[$section]['region'])) {
                    return $data[$section]['region'];
                }
            }
        }

        return '';
    }

    /**
     * @return bool
     */
    protected static function checkForCredentialFile()
    {
        $credentialFile = self::getHomeDir().'/.aws/credentials';
        $configFile = self::getHomeDir().'/.aws/config';

        if (file_exists($credentialFile)) {
            return true;
        }

        if (file_exists($configFile)) {
            return true;
        }

        return false;
    }

    /**
     * @return mixed|null|string
     */
    private static function getHomeDir()
    {
        // On Linux/Unix-like systems, use the HOME environment variable
        if ($homeDir = self::getEnvVar('HOME')) {
            return $homeDir;
        }

        // Get the HOMEDRIVE and HOMEPATH values for Windows hosts
        $homeDrive = self::getEnvVar('HOMEDRIVE');
        $homePath = self::getEnvVar('HOMEPATH');

        return ($homeDrive && $homePath) ? $homeDrive.$homePath : null;
    }

    /**
     * Fetches the value of an environment variable by checking $_SERVER and getenv().
     *
     * @param string $var Name of the environment variable
     *
     * @return mixed|null
     */
    private static function getEnvVar($var)
    {
        return isset($_SERVER[$var]) ? $_SERVER[$var] : getenv($var);
    }
}
