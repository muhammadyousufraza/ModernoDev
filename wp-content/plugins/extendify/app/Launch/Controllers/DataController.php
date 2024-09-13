<?php
/**
 * Data Controller
 */

namespace Extendify\Launch\Controllers;

defined('ABSPATH') || die('No direct access.');

use Extendify\Http;

/**
 * The controller for handling general data
 */
class DataController
{
    /**
     * Get Goals information.
     *
     * @param \WP_REST_Request $request - The wp rest request.
     *
     * @return \WP_REST_Response
     */
    public static function getGoals($request)
    {
        $response = Http::get('/goals?site_type=' . $request->get_param('site_type'));

        return new \WP_REST_Response(
            $response,
            wp_remote_retrieve_response_code($response)
        );
    }

    /**
     * Get Goals information.
     *
     * @return \WP_REST_Response
     */
    public static function getSuggestedPlugins()
    {
        $response = Http::get('/suggested-plugins');
        return new \WP_REST_Response(
            $response,
            wp_remote_retrieve_response_code($response)
        );
    }

    /**
     * Just here to check for 200 (vs server rate limting)
     *
     * @return \WP_REST_Response
     */
    public static function ping()
    {
        return new \WP_REST_Response(true, 200);
    }
}
