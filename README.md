# MaintainTrack

MaintainTrack is a PHP demo for reporting and coordinating barangay maintenance work. It has three role areas: Admin, Maintenance, and Regular User. Shared layout, navigation, and styling helpers live in the root `Components/` folder.

## Run Locally

1. Put the project in the XAMPP web root, for example `C:\xampp\htdocs\Sia`.
2. Start Apache in XAMPP. MySQL is not required by the current code.
3. Open `http://localhost/Sia/Index.php`.
4. Sign in with one of the demo accounts:

| Role         | Username | Password    |
| ------------ | -------- | ----------- |
| Admin        | `admin`  | `admin123`  |
| Regular User | `user`   | `user123`   |
| Maintenance  | `worker` | `worker123` |

These accounts are hard-coded for local demonstration only; do not use these credentials in a deployed system.

## How a Request Flows

1. `Index.php` displays the login form and reads any login error from the PHP session.
2. The form posts to `Login.php`. Its `$users` array contains the demo credentials and role names.
3. On success, `Login.php` stores the username and role in `$_SESSION`, then redirects to that role's dashboard. Failed logins return to `Index.php` with an error message.
4. Each role page loads the shared helpers from `Components/layout.php`. `renderHead()` opens the document, loads the shared base CSS plus that page's CSS, and includes the correct role navigation. The page calls `renderPageHeader()` for its heading and `renderFoot()` to close the document.
5. Navigation items are defined once in `roleNavigationItems()` in `Components/layout.php`. Each role's sidebar is the default; users can switch to top navigation from the profile menu. `Components/js/RoleNavigation.js` handles active links, profile menus, and switching layouts.
6. The shared profile dropdown is rendered by `renderRoleProfileMenu()` and contains Edit Profile, Settings, and Logout. Settings opens `Components/Settings.php`; the authenticated session role determines its navigation, with the `role` query parameter used only when there is no valid session role. Role-specific `Pages/Setting.php` files have been removed. The page previews font and content-size changes immediately, then stores preferences in browser cookies. `RoleNavigation.js` reapplies them on page load. Logout clears the PHP session through `Logout.php` before returning to the login screen.

## Where Things Come From

| Change this                                                       | Edit here                                                                                             |
| ----------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| Demo usernames, passwords, role assignment, or dashboard redirect | `$users` and `$dashboardMap` in `Login.php`                                                           |
| Display name, role label, or initials                             | `roleUser()` in `Components/layout.php`                                                               |
| Menu labels, destinations, or icons                               | `roleNavigationItems()` in `Components/layout.php`                                                    |
| Shared page shell, escaping, role header, or status helpers       | `Components/layout.php`                                                                               |
| Role-specific dashboard, table, or notification sample values     | Arrays near the top of the relevant file in `Admin/Pages/`, `Maintenance/Pages/`, or `Regular/Pages/` |
| A page's colors and layout                                        | Its matching stylesheet under that role's `Components/css/` folder                                    |
| Shared base styles, sidebar styles, or settings styles            | `Components/css/base.css`, `Components/css/Sidebar.css`, or `Components/Setting.css`                  |
| Shared images or JavaScript                                       | `Components/img/` or `Components/js/`                                                                 |
| Navigation, font, content-size, sidebar-size, and background      | `Components/Settings.php`; cookies are scoped by role                                                 |
| Font scaling and content density tokens                           | `Components/css/base.css`; `--base-font-size`, `--font-scale`, `--content-scale`, and spacing tokens  |

Font size and content size each accept `small`, `medium`, or `large`. Sidebar size accepts `full` or `compact`; a separate Retractable sidebar checkbox collapses to the logo and an expand button, then reveals navigation on hover or button activation. On touch/mobile devices, use the expand button. Background choices are Default, Barangay building, Blurry cloud, Classic blue, Classic waves, and Terrain map; the existing radial background remains the default. Layout, font, content, sidebar-size, retractable, and background cookies are role-specific (`admin_*`, `regular_*`, `maintenance_*`), so changing Admin preferences does not alter Regular or Maintenance. Unset preferences default to Sidebar, Full sidebar, Medium font, Medium content, Retractable off, and Default background.

Dashboard and list values are currently demonstration data stored directly in page-level PHP arrays. For example, the Admin dashboard defines `$stats` and `$requests` in `Admin/Pages/Dashboard.php`; Regular and Maintenance pages define their own arrays. Editing those arrays changes the displayed sample values.

## Project Map

```text
Sia/
├── Index.php                  # Login screen
├── Login.php                  # Demo credential check and role redirect
├── Components/
│   ├── layout.php             # Shared PHP helpers and role navigation data
│   ├── Setting.css            # Shared settings styles
│   ├── css/                   # Shared base and sidebar styles
│   ├── img/                   # Shared images
│   └── js/RoleNavigation.js   # Shared role navigation behavior
├── Admin/
│   ├── Components/            # Admin top bar, sidebar, and CSS
│   └── Pages/                 # Admin dashboards and management screens
├── Maintenance/
│   ├── Components/            # Maintenance top bar, sidebar, and CSS
│   └── Pages/                 # Maintenance dashboards and work screens
├── Regular/
│   ├── Components/            # Regular-user top bar, sidebar, and CSS
│   └── Pages/                 # Regular-user dashboard and request screens
└── README.md
```

## Add a Page

1. Create the PHP page under the correct role's `Pages/` folder.
2. Require the shared helpers with `require_once '../../Components/layout.php';`.
3. Call `renderHead($title, $pageCss, $role)`, render the page content, then call `renderFoot()`.
4. Add its link to the correct role's list in `roleNavigationItems()` if it should appear in the navigation.
5. Add page-specific styling under that role's `Components/css/` folder.

## Demo Limitations

- There is no database connection or persistent request/task storage. Most forms and tables are interface demonstrations; page arrays provide their sample data.
- Login checks the hard-coded array and sets session values, but role pages do not currently enforce authenticated access or authorize actions. This is not production-ready authentication.
- `Config/` is currently empty.
- Use MySQL only if you later add database-backed persistence and configure the application to connect to it.
