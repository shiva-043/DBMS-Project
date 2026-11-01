# RailReserve — Student Quick-Reference Summary (100% Pure PHP & HTML/CSS)

> **Purpose:** File-by-file list of PHP and SQL concepts used across the application. There is **zero JavaScript** in this project. If a viva examiner asks *"Why did you use X here?"* or *"How does this work without JavaScript?"*, this sheet gives you the exact answer.

---

## `config/db.php` — Database Connection & Helpers

| Concept | What & Why |
|---|---|
| `session_start()` | Starts PHP session so we can store logged-in user state across different pages |
| `new PDO(...)` | Connects PHP to MySQL using PDO (modern, safe, parameterized queries) |
| `PDO::ATTR_ERRMODE => ERRMODE_EXCEPTION` | Forces database errors to throw catchable exceptions |
| `PDO::ATTR_EMULATE_PREPARES => false` | Disables emulated prepared statements so MySQL does real server-side preparation (immune to SQL injection) |
| `$_SERVER['SCRIPT_NAME']` | Detects current file path to dynamically determine the `BASE_PATH` constant for relative URLs |
| `is_logged_in()`, `current_user()` | Reusable helper functions reading `$_SESSION` to identify user ID and role |
| `require_user()`, `require_admin()` | Authorization gates: checks `$_SESSION['role']` and calls `header("Location: ...")` + `exit` if unauthorized |
| `bin2hex(random_bytes(32))` | Generates a cryptographically secure random token for CSRF defense |
| `hash_equals()` | Constant-time string comparison preventing timing attacks when validating CSRF tokens |
| `strtotime()` + time math | Calculates duration; adds 86400 (seconds in 24 hours) if arrival time is numerically smaller than departure (overnight train) |
| `LEFT JOIN` in availability query | Connects trains to confirmed bookings and passengers; `LEFT JOIN` ensures trains with zero bookings still return full seat count |
| `COALESCE(..., 0)` | Replaces `NULL` with `0` when counting passengers on an empty train |
| `random_int()` in PNR generator | Generates unique 10-char PNR (`RR` + 8 digits) and queries DB in a `do-while` loop until uniqueness is guaranteed |

---

## `index.php` — Homepage & Search Form

| Concept | What & Why |
|---|---|
| `$pdo->query(...)` | Simple query fetching all stations for the `<select>` dropdowns |
| `$_POST` reading | Reads search parameters upon form submission |
| Server-side validation | Replaces client JS: validates stations are selected, source ≠ destination, and date ≥ today |
| `$_SESSION['pending_search']` | Stores search criteria in session if visitor is not logged in yet |
| Redirect to login | If guest searches, preserves search in session and redirects to `login.php?redirect_to=search` |
| `http_build_query()` | Converts array into a clean URL query string (`?source_station_id=1&...`) |
| `htmlspecialchars()` | Escapes output to prevent Cross-Site Scripting (XSS) attacks |
| `<select>` with DB loop | Uses PHP `foreach` to dynamically generate `<option>` elements with IDs and station names |

---

## `login.php` — Unified Authentication

| Concept | What & Why |
|---|---|
| Parameterized query | `$pdo->prepare("SELECT ... WHERE email = :email")` prevents SQL injection |
| Plain-text password match | Checks `$user['password'] === $password` directly (student-friendly plain text) |
| `session_regenerate_id(true)` | Generates a fresh session ID immediately on login to prevent Session Fixation attacks |
| Session population | Stores `user_id`, `full_name`, `email`, and `role` in `$_SESSION` |
| Role-based redirect | `ADMIN` goes to `admin/dashboard.php`; `USER` goes to intended search or `user/search-trains.php` |
| Restoring pending search | Checks `$_SESSION['pending_search']`, redirects user to results, and `unset()`s the temporary search data |

---

## `register.php` — User Registration

| Concept | What & Why |
|---|---|
| `filter_var($email, FILTER_VALIDATE_EMAIL)` | Built-in PHP validation for RFC-compliant email formatting |
| Unique email verification | Queries `users` table before insert to prevent duplicate accounts with friendly error feedback |
| Strict `'USER'` role assignment | `role` is hardcoded as `'USER'` in the SQL insert string, never accepted from user input |
| Plain-text password insert | Inserts raw password directly into the database as requested |
| Retaining entered input | Repopulates `$fullName`, `$email`, and `$phone` in form fields if validation fails so user doesn't retype |

---

## `logout.php` — Clean Session Termination

| Concept | What & Why |
|---|---|
| `$_SESSION = []` | Overwrites session array in memory |
| `setcookie(...)` | Expunges the session cookie from the client browser by setting expiration to the past |
| `session_destroy()` | Removes session storage file on the server, then redirects to `index.php` |

---

## `user/search-trains.php` — Train Search Results

| Concept | What & Why |
|---|---|
| `require_user()` | Restricts access to authenticated passengers only |
| Multi-table `JOIN` | Single query joins `trains` to `stations` twice (as `s1` for source, `s2` for destination) |
| Real-time capacity computation | Calls `get_available_seats()` for each train on the exact requested date |
| Duration calculation | `calculate_duration()` handles both same-day and past-midnight schedules |
| Capacity progress indicator | Calculates `($available / $total) * 100` and sets CSS width inline on `.seat-progress` |
| Conditional button rendering | If seats > 0, renders link to `book-ticket.php`; if 0, renders disabled "Sold Out" button |

---

## `user/book-ticket.php` — Booking with Pure PHP Multi-Passenger Management

| Concept | What & Why |
|---|---|
| **No JavaScript Dynamic Rows** | Adding/removing passengers submits the form back to PHP with `name="add_passenger"` or `name="remove_passenger"` |
| `formnovalidate` on row buttons | HTML5 attribute on Add/Remove buttons so the browser doesn't block form submission on unfilled fields |
| Input array preservation | Keeps `$savedNames`, `$savedAges`, and `$savedGenders` in PHP arrays so inputs persist across Add/Remove submits |
| **`$pdo->beginTransaction()`** | Encloses the booking and passenger inserts in a single atomic database transaction |
| **`SELECT ... FOR UPDATE`** | **Row-level lock** on the train record; stops race conditions and prevents overbooking under high traffic |
| Recalculating under lock | Queries confirmed passenger count while holding the lock before accepting the new booking |
| `$pdo->lastInsertId()` | Retrieves auto-incremented `booking_id` from the booking insert to bind child passenger rows |
| Batch passenger inserts | Inserts passenger records in a loop referencing `booking_id` |
| **`$pdo->commit()` & `rollBack()`** | Commits changes only if all inserts succeed; rolls back on any error, leaving DB clean |
| One-time confirmation token | Sets `$_SESSION['booking_confirmation'] = ['booking_id' => ...]` and redirects |

---

## `user/booking-confirmation.php` — One-Time Ticket Receipt

| Concept | What & Why |
|---|---|
| One-time session token check | Checks `$_SESSION['booking_confirmation']`; if absent, redirects to `my-bookings.php` (prevents duplicate views on refresh) |
| Ownership verification | `WHERE booking_id = :id AND user_id = :uid` ensures users only see their own tickets |
| Immediate token destruction | `unset($_SESSION['booking_confirmation'])` immediately after reading makes the receipt strictly single-use |
| **No-JS Print Support** | Explains browser keyboard shortcuts (`Ctrl+P` / `Cmd+P`) and uses `@media print` CSS to hide menus |

---

## `user/my-bookings.php` — Booking History & Cancellation

| Concept | What & Why |
|---|---|
| `$_GET['status']` filter | Toggles between `CONFIRMED` and `CANCELLED` bookings via clean tabs |
| `COUNT(p.passenger_id)` + `GROUP BY` | Computes passenger count per booking dynamically without fetching individual rows |
| Dynamic total fare | Calculated as `passenger_count * fare_per_passenger` (preserves historical fare) |
| Pure PHP POST cancel action | Form submittal with `name="action" value="cancel"` and CSRF token (no JS prompt required) |
| Status update transaction | Runs `UPDATE bookings SET booking_status = 'CANCELLED' WHERE booking_id = :id AND user_id = :uid` |
| Capacity release | By changing status to `'CANCELLED'`, seats are immediately freed up in the availability formula |

---

## `user/cancellation-confirmation.php` — One-Time Cancellation Receipt

| Concept | What & Why |
|---|---|
| One-time token pattern | Checks `$_SESSION['cancellation_confirmation']`, renders receipt, and `unset()`s the session variable |

---

## `user/profile.php` — Account Details Management

| Concept | What & Why |
|---|---|
| Self-exclusion in unique check | `WHERE email = :email AND user_id != :uid` allows users to retain their current email |
| Scoped profile update | `UPDATE users ... WHERE user_id = :uid` strictly scopes updates to session user ID |
| Live session synchronization | Updates `$_SESSION['full_name']` and `$_SESSION['email']` so the navbar reflects changes immediately |

---

## `admin/dashboard.php` — Administrator Overview

| Concept | What & Why |
|---|---|
| `require_admin()` | Protects admin pages from unauthorized users or guests |
| Aggregate metrics | Runs fast `SELECT COUNT(*)` queries across trains, stations, bookings, and users |

---

## `admin/manage-trains.php` — Pure PHP Inline Add/Edit/Delete

| Concept | What & Why |
|---|---|
| **No-JS Form Toggling** | Displays an inline form card when `?action=add` or `?action=edit&train_id=X` is in the URL |
| Pre-filling edit data | Queries train data based on `$_GET['train_id']` and pre-populates form inputs in PHP |
| Safe deletion check | Queries `SELECT COUNT(*) FROM bookings WHERE train_id = :id`; rejects delete if any booking references the train |
| Duplicate number detection | On edit, verifies uniqueness with `WHERE train_number = :num AND train_id != :id` |

---

## `admin/manage-stations.php` — Pure PHP Inline Add/Edit/Delete

| Concept | What & Why |
|---|---|
| Same inline pattern | Uses `?action=add` or `?action=edit&station_id=X` to show inline form without JavaScript modals |
| Referential integrity check | Verifies no trains reference station (`source_station_id` or `destination_station_id`) before deleting |
| `strtoupper()` | Sanitizes station codes into uppercase standard formatting |

---

## `admin/manage-bookings.php` — System-Wide Booking Inspector

| Concept | What & Why |
|---|---|
| `LIKE` search filter | `WHERE b.pnr LIKE :pnr` allows admins to search bookings by full or partial PNR code |
| View-only design | Admins can inspect details, but cannot cancel, edit, or delete passenger bookings |

---

## `admin/manage-users.php` — Account Directory

| Concept | What & Why |
|---|---|
| Selective projection | Explicitly omits the `password` column from the `SELECT` query so plain passwords never touch the UI |

---

## `css/style.css` — Pure CSS Highlights

| Technique | Purpose |
|---|---|
| CSS Variables (`:root`) | Centralized color palette (Navy Blue `#142D4E`, Page `#F5F7FA`) and spacing rules |
| CSS Grid & Flexbox | Modern desktop-responsive card and form layouts without third-party frameworks |
| `@media print` | Automatically hides navigation bar, action buttons, and borders when printing a ticket |
| Accessible Focus States | Visible keyboard focus outlines on all interactive inputs and buttons |
