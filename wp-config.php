<?php
define( 'WP_CACHE', true );
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'redcbltt_wp399' );

/** Database username */
define( 'DB_USER', 'redcbltt_wp399' );

/** Database password */
define( 'DB_PASSWORD', 'tpB]SS!28G()4Y[5' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'nz2lpum1psg97pu4lus4yd4x3teaplwlhxevgxuphhqvoo4ndbcobqdhg2vmft68' );
define( 'SECURE_AUTH_KEY',  'yqnzy9vpeejymgdkqd6yhpafoizgla0xsym3szycgm6wl7bxmpuaxbnaxyavttfp' );
define( 'LOGGED_IN_KEY',    'n8kgutxoj3ldada7sp73gq4ophqypvffauayszsmktzbvbvnqpyb1k1z8egdi2tf' );
define( 'NONCE_KEY',        'wq8emfuev7nl9ug3tekcdfs61pkhkiob9ix4auoqfvjl5hghqxwdcd8bar1g4xij' );
define( 'AUTH_SALT',        'h21uakdgvf48dgsuuw9l56ebctb8rfxojghezsjklrbvn4ckk8eqkhhm1230rhjy' );
define( 'SECURE_AUTH_SALT', 'ckk5dnfc83vao4aho5wkrqa1ho4qkgmoytvqjtnyjtrywlxczd5sviaz6fx5ow83' );
define( 'LOGGED_IN_SALT',   'kig1oycsgaqpgw16hawcdsquy3pibt3w8yfqmxsjkaje4jlzncsaaykm4puatvhl' );
define( 'NONCE_SALT',       'qr5zbd8fpvkf8o0u0v9870wgqho07bedvpwt7ki8djrxhswuetfki1x18dvh6txp' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wpxd_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
