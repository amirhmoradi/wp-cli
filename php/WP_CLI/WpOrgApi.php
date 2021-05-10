<?php

namespace WP_CLI;

use RuntimeException;

/**
 * Class WpOrgApi.
 *
 * This is an abstraction of the WordPress.org API.
 *
 * @see https://codex.wordpress.org/WordPress.org_API
 *
 * @package WP_CLI
 */
final class WpOrgApi {

	/**
	 * WordPress.org API root URL.
	 *
	 * @var string
	 */
	const API_ROOT = 'https://api.wordpress.org';

	/**
	 * WordPress.org API root URL.
	 *
	 * @var string
	 */
	const DOWNLOADS_ROOT = 'https://downloads.wordpress.org';

	/**
	 * Core checksums endpoint.
	 *
	 * @see https://codex.wordpress.org/WordPress.org_API#Checksum
	 *
	 * @var string
	 */
	const CORE_CHECKSUMS_ENDPOINT = self::API_ROOT . '/core/checksums/1.0/';

	/**
	 * Plugin checksums endpoint.
	 *
	 * @var string
	 */
	const PLUGIN_CHECKSUMS_ENDPOINT = self::DOWNLOADS_ROOT . '/plugin-checksums/';

	/**
	 * Salt endpoint.
	 *
	 * @see https://codex.wordpress.org/WordPress.org_API#Secret_Key
	 *
	 * @var string
	 */
	const SALT_ENDPOINT = self::API_ROOT . '/secret-key/1.1/salt/';

	/**
	 * Version check endpoint.
	 *
	 * @see https://codex.wordpress.org/WordPress.org_API#Version_Check
	 *
	 * @var string
	 */
	const VERSION_CHECK_ENDPOINT = self::API_ROOT . '/core/version-check/1.7/';

	/**
	 * Options to pass onto the Requests library for executing the remote calls.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * WpOrgApi constructor.
	 *
	 * @param array $options Associative array of options to pass to the API abstraction.
	 */
	public function __construct( $options = [] ) {
		$this->options = $options;
	}

	/**
	 * Gets the checksums for the given version of WordPress core.
	 *
	 * @param string $version Version string to query.
	 * @param string $locale  Locale to query.
	 * @return bool|array False on failure. An array of checksums on success.
	 * @throws RuntimeException If the remote request fails.
	 */
	public function get_core_checksums( $version, $locale = 'en_US' ) {
		$url = sprintf(
			'%s?%s',
			self::CORE_CHECKSUMS_ENDPOINT,
			http_build_query( compact( 'version', 'locale' ), null, '&' )
		);

		$response = $this->json_get_request( $url );

		if (
			! is_array( $response )
			|| ! isset( $response['checksums'] )
			|| ! is_array( $response['checksums'] )
		) {
			return false;
		}

		return $response['checksums'];
	}

	/**
	 * Gets a download offer
	 *
	 * @param string $locale   Locale to request an offer from.
	 * @return array|false False on failure. Associative array of the offer on success.
	 * @throws RuntimeException If the remote request failed.
	 */
	public function get_download_offer( $locale = 'en_US' ) {
		$url = sprintf(
			'%s?%s',
			self::VERSION_CHECK_ENDPOINT,
			http_build_query( compact( 'locale' ), null, '&' )
		);

		$response = $this->json_get_request( $url );

		if (
			! is_array( $response )
			|| ! isset( $response['offers'] )
			|| ! is_array( $response['offers'] )
		) {
			return false;
		}

		$offer = $response['offers'][0];

		if ( ! array_key_exists( 'locale', $offer ) || $locale !== $offer['locale'] ) {
			return false;
		}

		return $offer;
	}

	/**
	 * Gets the checksums for the given version of plugin.
	 *
	 * @param string $version Version string to query.
	 * @param string $plugin  Plugin string to query.
	 * @return bool|array False on failure. An array of checksums on success.
	 * @throws RuntimeException If the remote request fails.
	 */
	public function get_plugin_checksums( $plugin, $version ) {
		$url = sprintf(
			'%s%s/%s.json',
			self::PLUGIN_CHECKSUMS_ENDPOINT,
			$plugin,
			$version
		);

		$response = $this->json_get_request( $url );

		if (
			! is_array( $response )
			|| ! isset( $response['files'] )
			|| ! is_array( $response['files'] )
		) {
			return false;
		}

		return $response['files'];
	}

	/**
	 * Gets a set of salts in the format required by `wp-config.php`.
	 *
	 * @return bool|string False on failure. A string of PHP define() statements on success.
	 * @throws RuntimeException If the remote request fails.
	 */
	public function get_salts() {
		return $this->get_request( self::SALT_ENDPOINT );
	}

	/**
	 * Execute a remote GET request.
	 *
	 * @param string $url     URL to execute the GET request on.
	 * @param array  $headers Optional. Associative array of headers.
	 * @param array  $options Optional. Associative array of options.
	 * @return mixed|false False on failure. Decoded JSON on success.
	 * @throws RuntimeException If the JSON could not be decoded.
	 */
	private function json_get_request( $url, $headers = [], $options = [] ) {
		$headers = array_merge(
			[
				'Accept' => 'application/json',
			],
			$headers
		);

		$response = $this->get_request( $url, $headers, $options );

		if ( false === $response ) {
			return $response;
		}

		$data = json_decode( $response, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			throw new RuntimeException( 'Failed to decode JSON: ' . json_last_error_msg() );
		}

		return $data;
	}

	/**
	 * Execute a remote GET request.
	 *
	 * @param string $url     URL to execute the GET request on.
	 * @param array  $headers Optional. Associative array of headers.
	 * @param array  $options Optional. Associative array of options.
	 * @return string|false False on failure. Response body string on success.
	 * @throws RuntimeException If the remote request fails.
	 */
	private function get_request( $url, $headers = [], $options = [] ) {
		$options = array_merge(
			$this->options,
			[
				'halt_on_error' => false,
			],
			$options
		);

		$response = Utils\http_request( 'GET', $url, null, $headers, $options );

		if (
			! $response->success
			|| 200 > (int) $response->status_code
			|| 300 <= $response->status_code
		) {
			throw new RuntimeException(
				"Couldn't fetch response from {$url} (HTTP code {$response->status_code})."
			);
		}

		return trim( $response->body );
	}
}
