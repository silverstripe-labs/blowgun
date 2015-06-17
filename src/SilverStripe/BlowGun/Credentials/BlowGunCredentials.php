<?php namespace SilverStripe\BlowGun\Credentials;

use Aws\Common\Credentials\Credentials;

class BlowGunCredentials extends Credentials {

	/**
	 * Get the code of the default profile
	 *
	 * @return string
	 */
	public static function defaultProfile() {
		return self::getEnvVar(self::ENV_PROFILE) ?: 'default';
	}

	/**
	 * Get the default region from the profile
	 *
	 * @param string $profile
	 * @return string Region id
	 * @throws \RuntimeException
	 */
	public static function defaultRegion($profile = null) {
		$credentialFile = self::getHomeDir() . '/.aws/credentials';
		$configFile = self::getHomeDir() . '/.aws/config';
		$configData = array();
		$credentialData = array();

		if (!$profile) {
			$profile = self::defaultProfile();
		}

		if(!file_exists($credentialFile) && !file_exists($configFile)) {
			throw new \RuntimeException("Invalid AWS credentials file(s).");
		}

		if(file_exists($credentialFile)) {
			if(!($credentialData = parse_ini_file($credentialFile, true))) {
				throw new \RuntimeException("Invalid AWS credentials file: {$credentialFile}.");
			}
		}

		if(file_exists($configFile)) {
			if(!($configData = parse_ini_file($configFile, true))) {
				throw new \RuntimeException("Invalid AWS credentials file: {$configFile}.");
			}
		}

		foreach(array($credentialData, $configData) as $data) {
			foreach(array($profile, "profile $profile") as $section) {
				if(isset($data[$section]['region'])) return $data[$section]['region'];
			}
		}

		throw new \RuntimeException("Invalid region set for profile: {$profile}.");
	}

	/**
	 * @return mixed|null|string
	 */
	private static function getHomeDir() {
		// On Linux/Unix-like systems, use the HOME environment variable
		if ($homeDir = self::getEnvVar('HOME')) {
			return $homeDir;
		}

		// Get the HOMEDRIVE and HOMEPATH values for Windows hosts
		$homeDrive = self::getEnvVar('HOMEDRIVE');
		$homePath = self::getEnvVar('HOMEPATH');

		return ($homeDrive && $homePath) ? $homeDrive . $homePath : null;
	}

	/**
	 * Fetches the value of an environment variable by checking $_SERVER and getenv().
	 *
	 * @param string $var Name of the environment variable
	 *
	 * @return mixed|null
	 */
	private static function getEnvVar($var) {
		return isset($_SERVER[$var]) ? $_SERVER[$var] : getenv($var);
	}
}