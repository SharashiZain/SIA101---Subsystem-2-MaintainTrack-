# MaintainTrack

MaintainTrack is a barangay maintenance and reporting system built with plain PHP, HTML, CSS, and JavaScript. It supports three user roles: Admin, Maintenance, and Regular User, each with a role-based dashboard and navigation experience.

## Project Overview

This project is designed for a barangay management workflow where:

- Admin users can manage requests, assignments, equipment, reports, notifications, and users.
- Maintenance staff can view assigned tasks and task updates.
- Regular users can submit maintenance concerns and monitor their request progress.

The app uses a modular structure with separate folders for each role and shared components.

## Features

- Role-based login and dashboard flow
- Separate UI for Admin, Maintenance, and Regular users
- Top navigation bars and profile menus
- Request, task, report, notification, and user management screens
- Responsive layout styling with custom CSS per section
- Local PHP session-based authentication

## Tech Stack

- PHP
- HTML
- CSS
- JavaScript
- XAMPP / Apache local environment

## Project Structure

```text
Sia/
├── Index.php                  # Login landing page
├── Login.php                  # Authentication logic
├── Components/                # Shared CSS, JS, and images
│   ├── css/
│   ├── js/
│   └── img/
├── Admin/
│   ├── Components/
│   ├── Pages/
│   └── ...
├── Maintenance/
│   ├── Components/
│   ├── Pages/
│   └── ...
├── Regular/
│   ├── Components/
│   ├── Pages/
│   └── ...
├── Config/
├── README.md
└── css-redesigned.zip
```

## Default Login Accounts

The system uses a simple in-memory user list defined in `Login.php`.

| Role         | Username | Password  |
| ------------ | -------- | --------- |
| Admin        | admin    | admin123  |
| Regular User | user     | user123   |
| Maintenance  | worker   | worker123 |

## How to Run

1. Place the project folder in your local web server root, such as:
   - `C:/xampp/htdocs/Sia`
2. Start Apache and MySQL using XAMPP.
3. Open the browser and go to:
   - `http://localhost/Sia/Index.php`
4. Log in using one of the default accounts above.

## Role Access Flow

When a user logs in, the app redirects them to the matching role dashboard:

- Admin → `Admin/Pages/Dashboard.php`
- Maintenance → `Maintenance/Pages/Dashboard.php`
- Regular → `Regular/Pages/Dashboard.php` or `Regular/Pages/reg_user_dashboard.php`

## Notes

- This project is a front-end-heavy PHP application and does not use a database for persistence.
- User credentials are hardcoded in `Login.php` for demo/testing purposes.
- CSS styling is separated by section and role, which makes it easy to customize the interface.

## Recommended Improvements

- Add a real database and authentication system
- Move user credentials to environment variables or a secure database table
- Add validation for requests and maintenance tasks
- Implement CRUD operations for reports, facilities, and user profiles
- Add file uploads and image handling for repair documentation

## License

This project is intended for academic / local project use and has no formal open-source license assigned.
