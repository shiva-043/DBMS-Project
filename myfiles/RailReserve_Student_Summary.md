# RailReserve — Student Quick-Reference Summary

> **Purpose:** File-by-file list of PHP/SQL/JS concepts used. If a viva question asks *"Why did you use X here?"*, this sheet has your answer.

---

## `config/db.php` — Database Connection & Helpers

| Concept | What & Why |
|---|---|
| `session_start()` | Starts PHP session so we can store logged-in user info across pages |
| `new PDO(...)` | Connects PHP to MySQL database using PDO (safer than old `mysqli`) |
| `PDO::ATTR_ERRMODE => ERRMODE_EXCEPTION` | Makes DB errors throw exceptions so we can catch them |
| `PDO::ATTR_EMULATE_PREPARES => false` | Forces real prepared statements (actual security, not fake) |
| `$_SERVER['SCRIPT_NAME']` | Detects current file path to auto-calculate base URL for links |
| `$_SESSION` reads | Helper functions (`is_logged_in()`, `current_user()`) check session variables |
| `header("Location: ...")` + `exit` | PHP redirect — sends browser to another page, `exit` stops further code |
| `bin2hex(random_bytes(32))` | Generates a random CSRF token string for form security |
| `hash_equals()` | Timing-safe string comparison for CSRF token verification |
| `strtotime()` + arithmetic | Calculates journey duration; adds 86400 (seconds in a day) if arrival < departure (overnight train) |
| **Prepared statement** with `:param` | `$pdo->prepare("...WHERE train_id = :train_id")` → prevents SQL injection |
| `LEFT JOIN` in availability query | Joins trains→bookings→passengers; LEFT so trains with zero bookings still return results |
| `COALESCE(..., 0)` | Returns 0 instead of NULL when no passengers found (no bookings yet) |
| `random_int()` | Generates unique PNR number; loops until no duplicate found in DB |

---

## `index.php` — Homepage & Search Form

| Concept | What & Why |
|---|---|
| `$pdo->query(...)` | Simple SELECT (no user input) to fetch all stations for dropdowns |
| `$_POST` reading | Gets form values on submission (source, destination, date) |
| Server-side validation | Checks: fields not empty, source ≠ destination, date not in past |
| `$_SESSION['pending_search']` | Stores search criteria in session when visitor isn't logged in yet |
| `header("Location: login.php?redirect_to=search")` | Redirects to login, preserving intent to search |
| `http_build_query()` | Converts array to URL query string (`?source_station_id=1&...`) |
| `htmlspecialchars()` | Escapes output to prevent XSS (cross-site scripting) |
| `<select>` with DB data | Loops `$stations` array with `foreach` to build `<option>` tags |

---

## `login.php` — Authentication

| Concept | What & Why |
|---|---|
| `$pdo->prepare() + execute()` | Prepared statement to find user by email (prevents SQL injection) |
| `$user['password'] === $password` | Direct plain-text password comparison |
| `session_regenerate_id(true)` | Creates new session ID after login to prevent session fixation attack |
| `$_SESSION['user_id']`, `['role']`, etc. | Stores user info in session — used on every page to check login/role |
| Role-based redirect | `if ($user['role'] === 'ADMIN')` → dashboard, else → search page |
| `$_SESSION['pending_search']` check | Restores saved search after login, then `unset()` clears it |

---

## `register.php` — User Registration

| Concept | What & Why |
|---|---|
| `filter_var($email, FILTER_VALIDATE_EMAIL)` | Built-in PHP email format validation |
| Unique email check query | `SELECT` before INSERT to show friendly error if email already exists |
| `INSERT INTO users ... VALUES (... 'USER')` | Hardcodes role as `'USER'` — never accepts role from form (security) |
| Field-specific `$errors['field']` array | Shows inline error under the correct field, not just one generic error |
| Value preservation | `value="<?= htmlspecialchars($fullName) ?>"` — keeps typed values after validation failure |

---

## `logout.php` — Session Destroy

| Concept | What & Why |
|---|---|
| `$_SESSION = []` | Empties all session variables |
| `setcookie(session_name(), '', time()-42000)` | Expires the session cookie in the browser |
| `session_destroy()` | Destroys session file on server |

---

## `user/search-trains.php` — Train Search Results

| Concept | What & Why |
|---|---|
| `require_user()` | Auth guard — redirects if not logged in or if admin |
| `$_GET` parameters | Reads search criteria from URL query string |
| `JOIN` query (3 tables) | `trains JOIN stations s1 JOIN stations s2` — gets source & destination names in one query |
| `get_available_seats()` | Calls helper that counts passengers in CONFIRMED bookings and subtracts from total |
| `calculate_duration()` | Handles overnight journeys (arrival < departure) |
| `number_format($fare, 2)` | Formats fare as `1,850.00` |
| Percentage calculation | `($available / $total) * 100` for the seat availability progress bar width |
| Conditional button | Shows "Book Now" link or disabled "Sold Out" based on availability |

---

## `user/book-ticket.php` — Booking with Transaction

| Concept | What & Why |
|---|---|
| `verify_csrf()` | Validates the CSRF token from the form matches the session token |
| Array form inputs | `name="passenger_name[]"` — PHP receives as `$_POST['passenger_name']` array |
| **`$pdo->beginTransaction()`** | Starts atomic DB operation — either ALL succeed or NONE |
| **`SELECT ... FOR UPDATE`** | **Row-level lock** — prevents two users booking the last seat simultaneously |
| Recalculate under lock | Re-counts booked seats AFTER acquiring lock (not from cached search page values) |
| `$pdo->lastInsertId()` | Gets the auto-increment `booking_id` just inserted |
| Loop INSERT for passengers | One prepared statement executed multiple times in a loop |
| **`$pdo->commit()`** | Saves all changes; `rollBack()` in catch block undoes everything on failure |
| `$_SESSION['booking_confirmation']` | One-time token — stores booking_id so confirmation page can display it once |

---

## `user/booking-confirmation.php` — One-Time Receipt

| Concept | What & Why |
|---|---|
| Session one-time check | If `$_SESSION['booking_confirmation']` missing → redirect to My Bookings (prevents re-display on refresh) |
| Ownership verification | `WHERE booking_id = :id AND user_id = :uid` — can't view another user's ticket |
| `unset($_SESSION['booking_confirmation'])` | Clears token immediately after reading — makes it truly one-time |
| `window.print()` | JS triggers browser's native print dialog |
| `@media print` CSS | Hides navbar and buttons in printed output |

---

## `user/my-bookings.php` — Booking History & Cancel

| Concept | What & Why |
|---|---|
| `$_GET['status']` filter | Switches between CONFIRMED and CANCELLED views |
| `COUNT(p.passenger_id)` + `GROUP BY` | Counts passengers per booking without fetching individual details |
| Calculated total fare | `passenger_count × fare_per_passenger` (not stored — derived) |
| Cancel = `POST` action | State-changing operation uses POST, not a GET link |
| Ownership + status check before cancel | `WHERE booking_id = :id AND user_id = :uid` + checks status is CONFIRMED |
| `UPDATE ... SET booking_status = 'CANCELLED'` | Changes status only — never deletes booking or passenger rows |
| `confirm()` JS | Browser confirmation dialog before cancel submission |

---

## `user/cancellation-confirmation.php` — Cancel Receipt

| Concept | What & Why |
|---|---|
| Same one-time session pattern | `$_SESSION['cancellation_confirmation']` — checked, read, then immediately `unset()` |

---

## `user/profile.php` — Edit Profile

| Concept | What & Why |
|---|---|
| Unique email excluding self | `WHERE email = :email AND user_id != :user_id` — allows keeping your own email |
| `UPDATE users SET ... WHERE user_id = :id` | Only updates own record using session's user_id (never from form) |
| Session variable update | After DB update, also updates `$_SESSION['full_name']` so navbar reflects changes immediately |

---

## `admin/dashboard.php` — Admin Landing

| Concept | What & Why |
|---|---|
| `require_admin()` | Auth guard — only ADMIN role can access |
| `SELECT COUNT(*)` queries | Simple aggregate counts for summary cards |

---

## `admin/manage-trains.php` — CRUD with Modal

| Concept | What & Why |
|---|---|
| Same page for Add/Edit/Delete | `$_POST['action']` distinguishes between `'save'` and `'delete'` |
| Delete protection | `SELECT COUNT(*) FROM bookings WHERE train_id = :id` — blocks delete if bookings exist |
| Unique train_number check | On edit: `WHERE train_number = :num AND train_id != :id` (excludes self) |
| `json_encode($tr)` | Converts PHP row to JSON, passed to JS `openEditTrainModal()` function |
| Modal open/close | Pure vanilla JS — toggles `.active` CSS class on `.modal-backdrop` |
| JS populates form fields | `document.getElementById('...').value = tr.train_number` fills modal inputs for editing |

---

## `admin/manage-stations.php` — Same CRUD Pattern

| Concept | What & Why |
|---|---|
| Delete protection | Checks if any train references the station before allowing delete |
| `strtoupper()` | Auto-uppercases station code input |
| Same modal pattern as trains | Add/Edit via JS modal, Delete via POST with confirm |

---

## `admin/manage-bookings.php` — View Only

| Concept | What & Why |
|---|---|
| `LIKE` search | `WHERE b.pnr LIKE :pnr` with `'%' . $query . '%'` for partial PNR matching |
| No cancel/edit/delete buttons | Admin is view-only for bookings (by design) |

---

## `admin/manage-users.php` — View Only

| Concept | What & Why |
|---|---|
| Never shows `password` column | SELECT explicitly lists columns, excludes password |
| No role-edit controls | View-only table — no forms or buttons to change roles |

---

## `css/style.css` — Key CSS Concepts Used

| Concept | Where |
|---|---|
| CSS Variables (`:root`) | Color palette, border-radius, shadows — change once, applies everywhere |
| Flexbox | Navbar, form rows, train cards, button groups |
| CSS Grid | Homepage features grid, ticket details grid |
| `position: sticky` | Navbar stays visible on scroll |
| `@media print` | Hides navbar/buttons when printing ticket |
| `@media (max-width: 860px)` | Train cards stack vertically on narrower screens |
| `@keyframes` | Modal slide-in animation |
| Transition properties | Smooth hover effects on buttons, cards, links |

---

## `includes/navbar.php` — Shared Navigation

| Concept | What & Why |
|---|---|
| `require_once` | Included in every page — avoids duplicating navbar HTML |
| Role-based `if/elseif/else` | Shows different nav links for Public, User, and Admin |
| `basename($_SERVER['SCRIPT_NAME'])` | Detects current page to highlight the active nav link |

---

## Core SQL Patterns Cheat Sheet

```text
Simple read         →  $pdo->query("SELECT ...")
Parameterized read  →  $pdo->prepare("... WHERE id = :id")  →  execute(['id' => $val])
Insert              →  $pdo->prepare("INSERT INTO t (...) VALUES (:a, :b)")
Update              →  $pdo->prepare("UPDATE t SET col = :v WHERE id = :id")
Delete              →  $pdo->prepare("DELETE FROM t WHERE id = :id")
Last inserted ID    →  $pdo->lastInsertId()
Transaction         →  beginTransaction() → queries → commit() / rollBack()
Row lock            →  SELECT ... FOR UPDATE  (inside transaction)
Aggregate count     →  SELECT COUNT(*) FROM t WHERE ...
Join 2+ tables      →  SELECT ... FROM a JOIN b ON a.id = b.a_id
```

---

## Core PHP Patterns Cheat Sheet

```text
Session variable    →  $_SESSION['key'] = value;    read: $_SESSION['key']
GET parameter       →  $_GET['key']                 (from URL ?key=value)
POST parameter      →  $_POST['key']                (from form submission)
Redirect            →  header("Location: url"); exit;
Include file once   →  require_once 'file.php';
Escape HTML output  →  htmlspecialchars($var)
Format number       →  number_format($num, 2)
Format date         →  date('d M Y', strtotime($dbDate))
Ternary             →  $x > 0 ? 'yes' : 'no'
Array loop          →  foreach ($arr as $item) { ... }
Array loop indexed  →  foreach ($arr as $idx => $item) { ... }
```
