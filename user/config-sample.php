<?php

// =============================================================================

// If you changed your session name in your app (i.e. different than
// PHPSESSID), you can set your new session name here.
define('AUTHBOX_SESSION_NAME', null);

// The prefix of all session keys which will be set by AuthBox.
define('AUTHBOX_SESSION_PREFIX', '__authBox');

// =============================================================================

/**
 * All those parameters can also be set from session
 * through the "{AUTHBOX_SESSION_PREFIX}_config" key, like this:
 *
 * $_SESSION['__authBox_config'] = [
 *     'i18n' => [
 *         'defaultLanguage' => 'po',
 *     ],
 *     'security' => [
 *         'salt' => 'babar',
 *     ],
 * ];
 **/

return [

    // -------------------------------------------------------------------------
    // Basic app settings

    'app' => [

        // The name of your application
        'name' => MY__APP_NAME,

        // A sentence describing your app
        'baseline' => MY__APP_BASELINE,

        // Theme used by AuthBox (see user/themes)
        'theme' => 'default',

    ],

    // -------------------------------------------------------------------------
    // Database

    'database' => [

        // Database connection settings.
        // If you are using sqlite, set username and password to null
        // and fill the filepath.
        // Available DBMS:
        //   - mysql (standard port: 3306)
        //   - pgsql (standard port: 5432)
        //   - sqlite (file: absolute path to a sqlite database file)
        'dbms' => MY__DBMS,
        'host' => MY__DB_HOST,
        'port' => MY__DB_PORT,
        'dbname' => MY__DB_DBNAME,
        'username' => MY__DB_USERNAME,
        'password' => MY__DB_PASSWORD,
        'filepath' => MY__DB_FILEPATH,

        // The table names can be prefixed with that. Let's try "ab_"...
        'tables_prefix' => MY__DB_TABLES_PREFIX,

        // Table names
        'tables' => [
            'user' => [
                'name' => 'users',
            ],
            'token' => [
                'name' => 'tokens',
            ],
        ],

    ],

    // -------------------------------------------------------------------------
    // Security/crypting settings

    'security' => [

        // The salt is concatenated to password before hashing.
        'salt' => MY__SECURITY_SALT,

        // It is absolutely not recommended to store the plain password,
        // but sometimes...
        'storePlainPassword' => false,

    ],

    // -------------------------------------------------------------------------
    // Administration

    'admin' => [

        // Enable administration panel
        'enabled' => true,

        // Available password for administration panel
        'passwords' => [
            MY__ADMIN_PASSWORD,
        ],

    ],

    // -------------------------------------------------------------------------
    // Emails sent on events

    'emails' => [

        // SMTP settings
        'smtp' => [
            'enabled' => false, // Enable SMTP sending
            'host' => 'smtp.domain.tld', // SMTP server
            'encryption' => false,// Encryption type: false, "tls" or "ssl"
            'port' => 25, // SMTP port. Maybe 25, 587 or 465...
            'auth' => true, // Enabe SMTP authentication
            'username' => 'you@domain.tld', // Authentication username
            'password' => 'secret', // Authentication password
        ],

        // From address for emails
        'from' => [MY__EMAILS_FROM, MY__APP_NAME],

        // Reply to email address (if different of From)
        'replyTo' => null,

    ],

    // -------------------------------------------------------------------------
    // Cookie settings

    'cookies' => [

        'remember' => [

            // Name of the cookie
            'name' => 'AUTHBOX',

            // Base path of the cookie. Basically "/".
            'path' => '/',

            // Domain name for the cookie.
            // NULL correspond to the current domain naime.
            'domain' => null,

        ],

    ],

    // -------------------------------------------------------------------------
    // Internal URLs. You can modify URL rewriting rules to set your own
    // paths. Once it is done, set them here. Don't forget the
    // beginning slash "/" if the URL you want is on the server root.

    'routes' => [

        'index' => '',

        'register' => 'register',
        'success' => 'success',

        'login' => 'login',
        'forgotPassword' => 'forgot-password',
        'resetPassword' => 'reset-password',

        'logout' => 'logout',

        'profile' => 'profile',

        'admin' => 'admin',

    ],

    // -------------------------------------------------------------------------
    // Roles management for users

    'acl' => [

        // The order is important: upper are the less-right users.
        // Lower are something like gods (at least).
        'roles' => [
            'USER',
            'CONTRIBUTOR',
            'AUTHOR',
            'EDITOR',
            'ADMIN',
            'SUPER_ADMIN',
        ],

        // Default role for a registered user.
        'defaultRole' => 'USER',

    ],

    // -------------------------------------------------------------------------
    // Login/register and URLs behaviour

    'behaviour' => [

        // The regex wich will be applyed to the password for validation
        'passwordValidationRegex' => '/^.{6,}$/i',

        // Fields used for authentication (login field)
        'authFields' => ['email', 'username'],

        // If true, the registered user will be activated automatically.
        // If false, the onRegistration receivers will needs to accept the
        // registration from the email.
        'autoActivated' => MY__AUTO_ACTIVATED,

        // Be alerted on registration, to one or more predefined
        // email addresses
        'registrationAlert' => [
            'enabled' => false,
            'to' => [
                // ['you@domain.tld', 'AuthBox'],
            ],
        ],

        // Send a welcome email to the newly registered user
        'sendEmailToUserOnRegistration' => true,

        // Send an email to alert the new user his account has been activated
        'sendEmailToUserOnActivation' => true,

        // If autoActivated is false, you can set some tokens with which you'll
        // be able to give a private link to create an account automatically
        // enabled.
        // The private URL will be something like:
        // http://mywebsite.com/authbox/?register&t={oneOfYourTokens}
        'autoActivationTokens' => [
            MY__AUTO_ACTIVATION_TOKEN,
        ],

        // The duration of the session when the remember field is checked.
        'rememberDuration' => 365 * 24 * 3600,

        // Eg.: When the user click on "back" and there is no HTTP_REFERER
        // in the headers.
        'homepageUrl' => MY__HOMEPAGE_URL,

        // You can use some fields of your user model:
        // {user.id}, {user.email}, etc.
        // You can also set these informations by setting the query string
        // "after".
        'afterLoginUrl' => MY__HOMEPAGE_URL,
        'afterRegisterUrl' => MY__HOMEPAGE_URL,
        'afterLogoutUrl' => MY__HOMEPAGE_URL,

    ],

    // -------------------------------------------------------------------------
    // Internationalization

    'i18n' => [

        // Available languages are stored in user/i18n/*.xml
        'defaultLanguage' => MY__DEFAULT_LANGUAGE,

        // Display or not the languages switch in the pages
        'showLanguageSwitch' => true,

    ],

];
