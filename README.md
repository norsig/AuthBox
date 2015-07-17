
# AuthBox

AuthBox is a standalone authentication PHP application.

## Installation

Clone the AuthBox repository into your public project folder:

    $ cd /your/project/public/dir
    $ git clone https://github.com/d4w33d/AuthBox.git auth

Visit the newly created directory:

    http://yourwebsite.tld/auth

Follow the instructions to setup AuthBox.

## Usage

In every page you'll need, import the AuthBox class, and use some
of the following methods, like this:

```php
// Import AuthBox class.
require 'path/to/AuthBox.php';

// If the user is not logged in, he is automatically redirected
// to the authentication page.
$user = AuthBox::assertLoggedIn();

// The assertLoggedIn() returns the user, like the getUser()
// method does it.
echo 'Your are logged in as ' . $user->email . "\n";
```

| AuthBox static method  | Description |
| ------------- | ------------- |
| `isLoggedIn()`                         | Returns `true` if the user is logged in. `false` elsewhere.  |
| `assertIsLoggedIn()`                   | Redirect to auth if the user is not logged in. Elsewhere, returns current user.  |
| `getUser()`                            | Returns the current user's object.  |
| `isRole($targetRole)`                  | Returns `true` if the current user's role is upper or equal to the `$targetRole`. `false` elsewhere. |
| `assertRole($targetRole)`              | Throw a `AuthBox\Lib\BadRoleException` if the user's role is lower than the `$targetRole`. |
| `getAuthUrl($afterUrl = null)`         | Returns the full URL (with hostname) of the authentication page (register and login on the same page).<br>The `$afterUrl` allows you to set a URL where the user will be redirected after the operation.  |
| `redirectToAuth($afterUrl = null)`     | If you want to redirect immediatly to this page. |
| `getRegisterUrl($afterUrl = null)`     | Returns the full URL (with hostname) of the registration page.<br>The `$afterUrl` allows you to set a URL where the user will be redirected after the operation. |
| `redirectToRegister($afterUrl = null)` | If you want to redirect immediatly to this page. |
| `getLoginUrl($afterUrl = null)`        | Returns the full URL (with hostname) of the login page.<br>The `$afterUrl` allows you to set a URL where the user will be redirected after the operation. |
| `redirectToLogin($afterUrl = null)`    | If you want to redirect immediatly to this page. |
| `getLogoutUrl($afterUrl = null)`       | Returns the full URL (with hostname) of the logout page.<br>The `$afterUrl` allows you to set a URL where the user will be redirected after the operation. |
| `redirectToLogout($afterUrl = null)`   | If you want to redirect immediatly to this page. |
| `getProfileUrl($afterUrl = null)`      | Returns the full URL (with hostname) of the profile page, allowing logged in user to update his informations and change his password. |
| `redirectToProfile($afterUrl = null)`  | If you want to redirect immediatly to this page. |









