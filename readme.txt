=== Employee Attendance Manager ===
Contributors: hubator
Tags: attendance, employee, hr, timesheet, clock-in
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Employee attendance tracking with clock-in/out, photo capture, notes, calendars, and administrator reports.

== Description ==

Employee Attendance Manager adds an attendance dashboard to WordPress with the `[employee_attendance]` shortcode.

Employees can record a daily check-in and check-out with a required note and camera photo. Administrators can view employee attendance in a calendar and day-wise table from the Attendance admin menu.

== Features ==

* Frontend attendance dashboard through the `[employee_attendance]` shortcode.
* Employee check-in and check-out with required notes.
* Camera photo capture saved as WordPress media attachments.
* Monthly attendance calendar powered by bundled FullCalendar assets.
* Administrator employee filter and day-wise attendance table.

== Installation ==

1. Upload the `employee-attendance-manager` folder to `/wp-content/plugins/`.
2. Activate Employee Attendance Manager from the Plugins screen.
3. Add the `[employee_attendance]` shortcode to a page for employee access.
4. Administrators can also open the Attendance menu in wp-admin.

== Frequently Asked Questions ==

= Does this plugin require users to be logged in? =

Yes. Attendance actions and attendance records are available only to logged-in WordPress users.

= Are photos stored outside WordPress? =

No. Captured photos are stored as attachments in the WordPress media library.

= Does this version include shift scheduling or CSV export? =

No. This version focuses on clock-in/out attendance capture and administrator viewing.

== Third-Party Libraries ==

This plugin bundles FullCalendar 5.11.5 JavaScript and CSS in `assets/js/fullcalendar.min.js` and `assets/css/fullcalendar.min.css`.

FullCalendar is licensed under the MIT License. Source code and license details are available at https://github.com/fullcalendar/fullcalendar/releases/tag/v5.11.5.

== Changelog ==

= 1.0.0 =
* Initial release.
