<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'wptest');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', 'root');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'F^{R0}6wWq-Yw:R,}d4UMUd7l|F>6e9hn@2b{7z)-&!R{2U=4cZc-N5YxHhW>470');
define('SECURE_AUTH_KEY',  '#5hV%Rrs#>YPU*_>NKqpRXeDTE_&7^.nt-%E>;R[@X VwUjJ=?1b7t(iY{,6n`t8');
define('LOGGED_IN_KEY',    '/8W.a@f? rx*P}X~^P.fqg->,w7Tcv:ImF/X+ L_BVC%~3Qr7ox`+$]TAHsxbs%q');
define('NONCE_KEY',        'DOw3S6e%uO#r7aFRrMrRZWXMK:&+01@/e.l&]2Z2!7Is&Z,y7aA^z1K2spBz*}x7');
define('AUTH_SALT',        '&&/$H5=CX$=~J|_XH`ZnJE^5Gs!kiaH0r:XGnOuz]@8A[;Bo~%/?DTO!%/v&[TBr');
define('SECURE_AUTH_SALT', ',z)>+sFc]-M?o](!)RL5{b}}G/5c}/OVA}Js*tv*VF0&r8%3~%v`edJVUVDkqsrM');
define('LOGGED_IN_SALT',   '~e- p@9E97]DxJ0^_D;TtDM?6P!*Fb*xTCtsJ9*5iL<ymO^AKNF^0%zy!WRtE0`7');
define('NONCE_SALT',       'jyI_ew|Nt2O2=Nfji:%tkpZw ^g><]=9],u|^v:j@-)u)o0 =P~ObLHo.A?=e0Z!');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', true);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
