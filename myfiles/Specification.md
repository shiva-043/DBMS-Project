# RailReserve — Interface, Application Behaviour & Coding-Agent Specification

**Purpose of this document:** This is the UI, page behaviour, application-flow, integration, and implementation specification for RailReserve.

> **Important:** The database design and database specifications have already been provided to the coding agent separately. Do not recreate, redesign, rename, normalize, or modify the database schema based on this document. Treat the previously supplied database specification as the source of truth. This document refers to database entities and values only where necessary to explain how interfaces and application features connect to the existing database.

---

## 1. Instructions to the coding agent

Build the RailReserve Railway Reservation Management System using this document together with the database specification already supplied.

### Mandatory technology choices

- HTML5
- CSS3
- Core PHP
- MySQL
- PHP sessions

### Do not use

-JS, React, Vue, Angular, Node.js, Laravel, or other application frameworks.
- Bootstrap, Tailwind, or other CSS frameworks.
- External UI libraries or icon libraries.
- External fonts or Google Fonts.
- Payment gateways, railway APIs, live tracking, waiting lists, or features outside the agreed project scope.

### Implementation style

- Use one global stylesheet: `css/style.css`.
- Keep PHP pages straightforward, independent, and understandable for a student.
- Do not create a complex reusable component/include architecture unless it is genuinely needed.
- Do not use inline CSS. Put styling in the global stylesheet.
- Use semantic HTML and meaningful class names.
- Keep code readable and consistently formatted.
- Use prepared statements for database queries involving request or user-provided values.
- Escape database values before rendering them into HTML, for example with `htmlspecialchars()`.
- Validate important inputs on the server. Browser-side validation is supplementary only.
- Use PHP sessions for authentication.
- Enforce authorization on every protected page and every protected action. Hiding a button is not access control.
- Use POST for state-changing operations.
- Use database transactions for operations that must succeed or fail as one unit.
- Do not invent additional functionality or change previously finalized requirements.
- If a required database field, relationship, or behaviour appears inconsistent with the separately supplied database specification, report the conflict and ask rather than silently changing the schema.

### Priority

Correct functionality, safe database interaction, understandable student-level code, and consistent interfaces are more important than visual complexity.

---

## 2. Project scope and user roles

RailReserve is a basic railway reservation application with two roles.

### User

A user can:

- Register and log in.
- Search trains using source station, destination station, and journey date.
- View train timings, fare, and available seats.
- Book one or more passengers on a train.
- Receive a unique PNR after a successful booking.
- View their own booking history.
- Filter booking history to see cancelled bookings.
- Cancel their own confirmed bookings.
- View and edit their basic profile details.

### Admin

An admin can:

- Log in using an account created manually in the database.
- Manage trains.
- Manage stations.
- View registered users.
- View bookings and their status.
- Search for a booking using its PNR.

An admin cannot cancel, edit, or delete bookings.

### Agreed scope limitations

Do not implement:

- Online payments or refunds.
- Waiting list or RAC.
- Seat or berth selection.
- Coach/class selection.
- Intermediate stops, connecting trains, or multi-leg journeys.
- Dynamic train routes or schedules.
- Live train tracking or external railway APIs.
- Ticket modification after booking.
- Public admin registration.
- Password change from the profile page.
- Passenger-detail expansion in booking-history or admin booking tables.
- Mobile-specific responsive design. The project is desktop-first, but it must adapt sensibly to different desktop monitor widths.

### Train and booking behaviour

- Trains have one fixed source and one fixed destination.
- Trains operate daily at fixed departure and arrival times.
- Availability is calculated separately for each train and journey date.
- There is no seat selection; only capacity-based availability is shown.
- A booking can contain multiple passengers.
- Only confirmed bookings consume capacity.
- Cancelled bookings remain in history and release their capacity.
- Fare shown for an existing booking must remain the fare recorded at the time of booking, even if the admin later changes the train's current fare.
- Every successful booking must have at least one passenger.
- Total fare is passenger count multiplied by the booking-time fare per passenger.

---

## 3. Global visual identity and design system

### Overall appearance

- Traditional railway website aesthetic.
- Light theme only.
- Navy blue and white as the main identity.
- Minimal design using mostly neutral colours.
- Spacious whitespace and generous, consistent padding/margins.
- System fonts only.
- No footer on any page.
- Subtle hover effects and smooth transitions.
- No special loading animations.
- No unnecessary icons or decorative illustrations.
- Desktop-first and flexible across different desktop monitor sizes.

### Branding

- Project name: **RailReserve**
- Logo: simple railway/train-related mark alongside the text `RailReserve`.
- Keep the logo clean and restrained.

### Suggested colour palette

Use these as the initial CSS variables:

```css
:root {
    --color-primary: #142D4E;
    --color-primary-hover: #23466D;
    --color-page: #F5F7FA;
    --color-surface: #FFFFFF;
    --color-text: #202B3C;
    --color-muted: #64748B;
    --color-border: #DFE5EC;
    --color-success: #16834A;
    --color-danger: #C43D42;

    --radius-sm: 6px;
    --radius-md: 10px;
    --radius-lg: 14px;

    --shadow-card: 0 2px 10px rgba(20, 45, 78, 0.06);
}
```

### Typography

Use system fonts only, for example:

```css
font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
```

Use a clear and restrained hierarchy for page headings, section headings, labels, body text, and helper text. Avoid oversized headings.

### Layout and CSS rules

- Use Flexbox and CSS Grid where appropriate.
- Use a centered main content container with a sensible maximum width.
- Let the layout adapt to different desktop widths.
- Maintain consistent spacing, borders, radii, and shadows.
- Keep all application styling in `css/style.css`.
- Use meaningful and reusable class names for common visual patterns.
- Provide clear primary, secondary, danger, disabled, and focus states.
- Include visible keyboard focus styles.
- Do not use inline CSS.

### Navigation bars

Use a horizontal top navbar.

**Public navbar:**
- RailReserve logo
- Home
- Login
- Register

**Logged-in user navbar:**
- RailReserve logo
- Home
- Search Trains
- My Bookings
- Profile
- Logout

**Admin navbar:**
- RailReserve logo
- Admin Dashboard
- Manage Trains
- Manage Stations
- Manage Bookings
- Manage Users
- Logout

The homepage must look the same for logged-in and logged-out visitors. Do not redirect a logged-in user away from the homepage.

### Forms and feedback

- Use visible labels; do not rely only on placeholders.
- Show inline validation errors below the relevant fields.
- Show inline success/error alert banners near the top of the relevant content.
- Preserve safe entered values after validation errors. Never repopulate password fields.
- Use clear confirmation prompts for destructive actions.
- Keep form layouts simple and consistent.

---

## 4. Page-by-page interface specifications

### 4.1 Public homepage — `index.php`

**Purpose:** Landing page and starting point for train search.

**Layout:**

- Public navbar.
- Hero section with RailReserve branding and a short railway reservation heading.
- Horizontal train-search form containing:
  - From station
  - To station
  - Journey date
  - Search Trains button
- Small informational section explaining the basic booking process.
- No footer.

**Integration and behaviour:**

- Populate station dropdowns from the existing stations data in the database.
- Display station names to users while submitting the corresponding station identifiers expected by the existing backend/database.
- Prevent the same station from being selected as both source and destination.
- Do not allow a journey date in the past.
- Validate the search on the server.
- On a successful search submission, navigate to `user/search-trains.php` with the selected source, destination, and journey date.
- The homepage remains the same for logged-in and logged-out users.
- If a logged-out visitor submits a valid search, preserve the search criteria, send them to login, and return them to the intended search after successful login. Do not silently discard their search.

### 4.2 Login — `login.php`

**Layout:**

- Public navbar or minimal brand header.
- Compact centered login card.
- Email field.
- Password field.
- Login button.
- Register link.
- Inline error area.

**Integration and behaviour:**

- Use one login page for both USER and ADMIN accounts.
- Authenticate against the existing users data.
- Verify the submitted password against the stored password hash using `password_verify()`.
- Regenerate the session ID after successful authentication.
- Store the authenticated user's identifier, display name, and role in the session.
- After login:
  - USER → `user/search-trains.php`
  - ADMIN → `admin/dashboard.php`
- If the user was redirected to login while trying to search, preserve and restore that intended search after successful login.
- Invalid credentials must show a clear inline error.
- Do not provide public admin registration.

### 4.3 Registration — `register.php`

**Layout:**

- Compact centered registration card.
- Full name.
- Email.
- Phone (optional).
- Password.
- Confirm password.
- Register button.
- Login link.

**Integration and behaviour:**

- Create only normal USER accounts.
- Never accept the role from the browser form.
- Validate required fields, email format, password confirmation, and unique email.
- Hash passwords using `password_hash()`.
- On success, show a success message and direct the user to login.
- Never display or return a password hash.

### 4.4 User train search — `user/search-trains.php`

**Purpose:** Display direct trains matching a selected route and journey date.

**Layout:**

- User navbar.
- Page heading.
- Search summary showing source, destination, and journey date.
- Search modification form.
- Matching trains displayed as compact horizontal rows/cards.

**Each train result must display:**

- Train number.
- Train name.
- Source station and departure time.
- Destination station and arrival time.
- Journey duration.
- Fare per passenger.
- Available seats and total seats.
- Availability progress bar representing available seats divided by total seats.
- Book Now button.

**Integration and behaviour:**

- Query the existing train and station data for direct source-to-destination matches.
- Trains run daily; do not add a train-running calendar.
- Calculate duration correctly, including journeys arriving after midnight.
- Calculate availability for the exact selected journey date.
- Only confirmed bookings count against capacity. Cancelled bookings must not reduce availability.
- If availability is zero, do not allow booking.
- Book Now passes the selected train identifier and journey date to `user/book-ticket.php`.
- The booking page must recheck train, date, fare, and availability on the server; never trust values merely because they came from the search page.
- If no matching trains are found, display the simple text: **“No trains found”**. Do not add a complex illustration or repeat the search form in the empty state.

### 4.5 Book ticket — `user/book-ticket.php`

**Purpose:** Collect passenger information and confirm a booking.

**Layout:**

- User navbar.
- Selected train summary:
  - Train number and name.
  - Source and destination.
  - Departure and arrival times.
  - Journey date.
  - Fare per passenger.
  - Available seats.
- Passenger entry area.
- All passenger forms displayed together on one page; no multi-step wizard.
- Each passenger form contains:
  - Passenger name.
  - Age.
  - Gender.
- Booking summary:
  - Passenger count.
  - Fare per passenger.
  - Total fare.
- Confirm Booking button.

**Integration and behaviour:**

- Allow one or more passengers.
- At least one passenger is required.
- Passenger count cannot exceed current availability.
- Calculate total fare as passenger count multiplied by the current fare per passenger at booking time.
- Read fare and availability from the database on the server. Do not trust hidden form values.
- Use the existing database relationships to create one booking record and its passenger records.
- Generate a unique PNR.
- Perform the availability check and booking inserts in a transaction with a concurrency-safe locking strategy. A normal check followed by an insert is not sufficient to prevent two users from taking the last seats simultaneously.
- If any part fails, roll back the transaction and show a clear error.
- After success, redirect to `user/booking-confirmation.php` with a safe one-time confirmation context.

### 4.6 Booking confirmation — `user/booking-confirmation.php`

**Purpose:** Display the successful booking immediately after it is made.

**Layout:**

- User navbar.
- Minimal confirmation card.
- Success heading/message.
- Prominent PNR.
- Train number and name.
- Source and destination.
- Journey date.
- Departure and arrival times.
- Passenger count and passenger details for this immediate confirmation view.
- Fare per passenger.
- Total fare.
- Print Ticket button.
- My Bookings button.

**Integration and behaviour:**

- Display the confirmation only immediately after a successful booking.
- Use a safe one-time session confirmation context or equivalent mechanism.
- If the user refreshes the page, or the one-time context is missing, redirect to `user/my-bookings.php`.
- Do not create a second booking on refresh.
- Verify that the confirmation belongs to the currently logged-in user.
- Print Ticket uses the browser's print function only. No PDF generation is required.
- Add print-specific CSS to hide navigation and action buttons in the printed output.

### 4.7 My Bookings — `user/my-bookings.php`

**Purpose:** Let users view their own booking history and cancel their confirmed bookings.

**Layout:**

- User navbar.
- Page heading.
- Default view displays confirmed bookings.
- A simple filter/control allows the user to view cancelled bookings.
- Table columns:
  - PNR.
  - Train number/name.
  - Source.
  - Destination.
  - Journey date.
  - Passenger count.
  - Total fare.
  - Booking status.
  - Action.

**Integration and behaviour:**

- Retrieve bookings only for the currently logged-in user. Never trust a user identifier supplied by the browser to determine ownership.
- Show confirmed bookings by default.
- Provide a filter to view cancelled bookings.
- Display passenger count only. Do not expand rows or show individual passenger details here.
- Calculate total fare using the booking-time fare per passenger multiplied by the number of passenger rows.
- Confirmed status should use a green status treatment.
- Cancelled status should use a red or muted grey status treatment.
- Show Cancel only for confirmed bookings eligible for cancellation.
- Ask for confirmation before submitting a cancellation.
- Submit cancellation using POST.
- On the server, verify ownership and current status before updating the booking.
- After successful cancellation, redirect to `user/cancellation-confirmation.php`.
- Do not allow a user to cancel another user's booking or cancel an already-cancelled booking.

### 4.8 Cancellation confirmation — `user/cancellation-confirmation.php`

**Purpose:** Confirm that the user's booking has been cancelled.

**Layout:**

- User navbar.
- Simple cancellation confirmation card.
- Clear cancellation-success message.
- PNR.
- Train and journey-date summary.
- Button/link to My Bookings.

**Integration and behaviour:**

- Display only after a successful cancellation.
- Use a one-time session confirmation context or equivalent safe mechanism.
- Verify that the cancellation belongs to the logged-in user.
- If the context is missing or invalid, redirect to My Bookings.
- Do not expose another user's booking data.

### 4.9 User profile — `user/profile.php`

**Purpose:** View and edit basic account details.

**Layout:**

- User navbar.
- Profile form containing:
  - Full name.
  - Email.
  - Phone.
  - Save Changes button.

**Integration and behaviour:**

- Load the currently logged-in user's details from the existing users data.
- Allow the user to update only their own name, email, and phone.
- Validate all fields on the server.
- Ensure email remains unique, excluding the current user's own record.
- Show inline success and validation messages.
- Do not include password-change functionality.
- Never show the password hash.

### 4.10 Admin dashboard — `admin/dashboard.php`

**Purpose:** Simple landing page for administrators.

**Layout:**

- Admin navbar.
- Welcome heading.
- Simple navigation cards or links to:
  - Manage Trains.
  - Manage Stations.
  - Manage Bookings.
  - Manage Users.

**Behaviour:**

- No charts or analytics.
- Keep the page minimal.
- Every link must lead to a working admin page.

### 4.11 Manage trains — `admin/manage-trains.php`

**Purpose:** Allow admins to manage train records.

**Layout:**

- Admin navbar.
- Page heading.
- Add Train button.
- Train table showing:
  - Train number.
  - Train name.
  - Source station.
  - Destination station.
  - Departure time.
  - Arrival time.
  - Total seats.
  - Fare.
  - Actions: Edit and Delete.
- Add/Edit form displayed in a modal on the same page.

**Modal fields:**

- Train number.
- Train name.
- Source station dropdown.
- Destination station dropdown.
- Departure time.
- Arrival time.
- Total seats.
- Fare.
- Save button.
- Cancel/Close button.

**Integration and behaviour:**

- Populate station dropdowns using existing station records.
- Display station names but submit the station identifiers expected by the existing database.
- Prevent source and destination from being the same.
- Validate all fields on the server.
- Enforce the existing database's unique train-number rule.
- Validate positive capacity and non-negative fare.
- Add and edit operations must update the existing trains data.
- Delete requires confirmation.
- If any booking references the train, prevent deletion and display a clear error. Do not delete booking history.
- Use the same page for adding and editing; do not create separate add/edit pages.
- Use plain HTML/CSS and minimal vanilla JavaScript only for opening/closing the modal and loading the selected record into the edit form. Do not use an external modal library.
- If server-side validation fails, return the user to the same page and show the error clearly.

### 4.12 Manage stations — `admin/manage-stations.php`

**Purpose:** Allow admins to manage station records.

**Layout:**

- Admin navbar.
- Page heading.
- Add Station button.
- Station table showing:
  - Station name.
  - Station code.
  - City.
  - Actions: Edit and Delete.
- Add/Edit form displayed in a modal on the same page.

**Modal fields:**

- Station name.
- Station code.
- City.
- Save button.
- Cancel/Close button.

**Integration and behaviour:**

- Validate all fields on the server.
- Enforce the existing database's unique station-code rule.
- Allow editing station details.
- If any train references a station, prevent deletion and display a clear error.
- Do not cascade-delete trains or bookings.
- Use the same page for adding and editing stations.
- Use only minimal vanilla JavaScript for modal interaction.

### 4.13 Manage bookings — `admin/manage-bookings.php`

**Purpose:** Allow admins to inspect booking records.

**Layout:**

- Admin navbar.
- Page heading.
- PNR search/lookup field.
- Booking table showing:
  - PNR.
  - User name or email.
  - Train number/name.
  - Source.
  - Destination.
  - Journey date.
  - Passenger count.
  - Total fare.
  - Booking date.
  - Booking status.

**Integration and behaviour:**

- Admin can view bookings and search by PNR.
- Display passenger count only. Do not show individual passenger details in this list.
- Admin cannot cancel, edit, or delete bookings.
- Do not provide booking modification controls.
- Escape all database values before rendering.

### 4.14 Manage users — `admin/manage-users.php`

**Purpose:** Allow admins to view registered users.

**Layout:**

- Admin navbar.
- Page heading.
- Table showing:
  - User ID.
  - Full name.
  - Email.
  - Phone.
  - Role.

**Integration and behaviour:**

- Admin can view users only.
- Never display password hashes.
- No user deletion or role-editing functionality is required.
- Do not allow role changes through this interface.

---

## 5. Application flow and page interconnection

The pages must work as one connected application. Do not build isolated static mockups.

### Main user flow

1. Visitor opens `index.php`.
2. Visitor selects source, destination, and journey date.
3. If not logged in, preserve the search criteria and redirect to `login.php`.
4. After successful USER login, redirect to the intended search results; for a normal login without a pending search, redirect to `user/search-trains.php`.
5. User views matching trains and selects Book Now.
6. User enters passenger details in `user/book-ticket.php`.
7. Server validates the request and checks availability safely.
8. Server creates the booking and passenger rows in one transaction.
9. User is redirected to `user/booking-confirmation.php`.
10. User can open `user/my-bookings.php`.
11. User can filter confirmed/cancelled bookings.
12. User can cancel their own confirmed booking.
13. Successful cancellation redirects to `user/cancellation-confirmation.php`.
14. User can view or edit their profile through `user/profile.php`.

### Main admin flow

1. Admin opens login and authenticates.
2. Successful ADMIN login redirects to `admin/dashboard.php`.
3. Admin can navigate to Manage Trains, Manage Stations, Manage Bookings, and Manage Users.
4. Train and station add/edit forms open as modals on their respective management pages.
5. Admin can view bookings and search by PNR.
6. Admin cannot cancel or modify bookings.

### Redirect summary

| Event | Destination |
|---|---|
| Open homepage | `index.php` |
| Successful normal USER login | `user/search-trains.php` |
| USER login with a pending search | Return to the intended search |
| Successful ADMIN login | `admin/dashboard.php` |
| Successful registration | `login.php` |
| Successful train search | `user/search-trains.php` |
| Select Book Now | `user/book-ticket.php` |
| Successful booking | `user/booking-confirmation.php` |
| Refresh confirmation / missing one-time context | `user/my-bookings.php` |
| Successful cancellation | `user/cancellation-confirmation.php` |
| Cancellation confirmation action | `user/my-bookings.php` |
| Logout | `index.php` |

---

## 6. Authentication, sessions, and authorization

### Registration and login

- Public registration always creates a normal USER account.
- Admin accounts are created manually in the database, as already specified in the database instructions.
- Use `password_hash()` when storing a new password.
- Use `password_verify()` during login.
- Use a shared login page for both roles.
- Regenerate the session ID after successful login.
- Store only the information needed for authentication and display in the session, such as user ID, full name, and role.
- Never store a plain-text password in the session.
- Logout must clear and destroy the session.

### Page authorization

- User pages must reject unauthenticated visitors and admin accounts.
- Admin pages must reject unauthenticated visitors and normal users.
- Every protected POST action must repeat the relevant role and ownership checks.
- Every user booking query must be scoped to the authenticated user's ID.
- Never trust a `user_id` supplied in a URL, hidden field, or form to determine booking ownership.
- Admin can view users and bookings but cannot cancel bookings.
- User cannot access admin management pages or actions.

### Request safety

- Use POST for booking, cancellation, profile updates, train/station changes, and logout.
- Do not perform destructive operations through GET links.
- Include CSRF tokens in authenticated state-changing forms.
- Validate CSRF tokens server-side.
- Escape output rendered into HTML.
- Use prepared statements for request-derived values.
- Avoid displaying raw SQL errors or stack traces to end users. Log or report useful development errors separately.

---

## 7. Important backend integration rules

The database specification has already been supplied separately. Follow it exactly. The following are application-level rules that must be respected when connecting the interfaces to that schema.

### Data source consistency

- Use the exact existing database, table, and column names from the supplied database specification.
- Do not create duplicate tables or alternate column names.
- Do not add redundant columns to make a page easier to implement.
- Derive passenger count from the passenger records.
- Derive total fare from passenger count and the booking-time fare-per-passenger value.
- Use station identifiers for train source/destination relationships; show station names in the UI.
- Use the existing booking status values consistently throughout the application.
- Keep the booking-time fare snapshot for historical booking calculations.

### Availability

For a selected train and journey date:

`available seats = train capacity - passenger rows in confirmed bookings for that train and date`

- Count only passengers attached to confirmed bookings.
- Do not count cancelled bookings.
- Ensure a train with no bookings returns its full capacity.
- Availability must be recalculated on the server during booking.

### Concurrency-safe booking

A simple availability SELECT followed by an INSERT can cause overbooking if two users book simultaneously.

- Use a transaction.
- Lock a stable row for the selected train, such as the train row with `SELECT ... FOR UPDATE`, before calculating availability.
- Recalculate availability while holding the lock.
- Insert the booking and all passenger rows in the same transaction.
- Commit only if all inserts succeed.
- Roll back if capacity is insufficient or any insert fails.
- Ensure the tables use a transactional engine such as InnoDB.

### Cancellation

- Only the owner of a confirmed booking can cancel it.
- Admin cannot cancel bookings.
- Update the booking status; do not delete booking or passenger records.
- Cancelled bookings must stop consuming capacity.
- Repeated cancellation requests must be handled safely and must not change the booking again.

### One-time result pages

- Booking confirmation and cancellation confirmation are result pages, not permanent public pages.
- Use a session-based one-time result context or another safe method.
- Verify ownership before showing booking information.
- Clear the one-time context after it has been consumed.
- Refreshing or revisiting without valid context redirects to My Bookings.

---

## 8. Suggested project folder structure

Use this structure unless a small additional file is necessary for a clearly justified purpose.

```text
railway-reservation/
│
├── index.php
├── login.php
├── register.php
│
├── user/
│   ├── search-trains.php
│   ├── book-ticket.php
│   ├── booking-confirmation.php
│   ├── my-bookings.php
│   ├── cancellation-confirmation.php
│   └── profile.php
│
├── admin/
│   ├── dashboard.php
│   ├── manage-trains.php
│   ├── manage-stations.php
│   ├── manage-bookings.php
│   └── manage-users.php
│
├── config/
│   └── db.php
│
└── css/
    └── style.css
```

The SQL/database files are managed according to the separate database specification already provided. Do not regenerate or replace them as part of this interface task unless explicitly requested.

---

## 9. Local setup and connection expectations

The project is intended to run locally using XAMPP.

Expected project location:

`htdocs/railway-reservation/`

Expected local URL:

`http://localhost/railway-reservation/`

Create `config/db.php` to connect to the already-created database. Use the database name and credentials from the existing project/database instructions. Do not guess a different database name or silently create a second database.

The coding agent should provide concise setup notes covering:

- Where to place the project folder in XAMPP.
- How to start Apache and MySQL.
- How to configure the connection file using the already supplied database details.
- Which URL to open in the browser.
- How to log in with the admin account that has already been or will be manually inserted according to the database instructions.

---

## 10. Functional and integration testing checklist

### Public pages and authentication

- [ ] Homepage loads and station dropdowns are populated from the database.
- [ ] Homepage is visually the same for logged-in and logged-out visitors.
- [ ] Same source and destination are rejected.
- [ ] Past journey date is rejected.
- [ ] Registration creates only USER accounts.
- [ ] Duplicate email is rejected.
- [ ] Password is stored as a hash.
- [ ] Invalid login shows an inline error.
- [ ] USER login redirects to Train Search.
- [ ] ADMIN login redirects to Admin Dashboard.
- [ ] Pending search criteria survive the login redirect.
- [ ] Public visitors cannot access protected pages.
- [ ] USER cannot access admin pages or actions.
- [ ] ADMIN cannot use user booking actions.
- [ ] Logout destroys the session.

### Train search

- [ ] Search by source, destination, and journey date works.
- [ ] Only direct route matches are displayed.
- [ ] Duration handles an arrival after midnight.
- [ ] Availability is specific to the selected date.
- [ ] Cancelled bookings do not reduce availability.
- [ ] A train with no bookings shows full capacity.
- [ ] Zero availability prevents booking.
- [ ] No-match result displays “No trains found”.
- [ ] Search results display the correct fare and timings.

### Booking

- [ ] User can book one passenger.
- [ ] User can book multiple passengers.
- [ ] Empty passenger list is rejected.
- [ ] Invalid passenger fields are rejected.
- [ ] More passengers than available seats is rejected.
- [ ] Total fare is calculated correctly.
- [ ] PNR is unique.
- [ ] Booking and all passenger rows are saved together.
- [ ] Failed booking does not leave partial records.
- [ ] Concurrent booking attempts cannot exceed capacity.
- [ ] Confirmation displays the correct PNR and booking details.
- [ ] Refreshing confirmation redirects to My Bookings.
- [ ] Print Ticket invokes browser print and hides navigation/action controls.

### Booking history and cancellation

- [ ] User sees only their own bookings.
- [ ] Confirmed bookings are shown by default.
- [ ] Cancelled filter shows cancelled records.
- [ ] Passenger count is shown without individual passenger details.
- [ ] Total fare uses the booking-time fare.
- [ ] User can cancel their own confirmed booking.
- [ ] User cannot cancel another user's booking.
- [ ] User cannot cancel an already-cancelled booking.
- [ ] Cancellation retains booking and passenger history.
- [ ] Cancellation releases capacity.
- [ ] Successful cancellation opens the cancellation confirmation page.
- [ ] Admin has no cancellation action.

### Profile

- [ ] Profile displays the logged-in user's details.
- [ ] User can update name, email, and phone.
- [ ] Duplicate email is rejected.
- [ ] User cannot update another user's profile.
- [ ] No password-change feature is displayed.

### Admin

- [ ] Admin can add and edit trains using a modal.
- [ ] Invalid train data is rejected.
- [ ] Duplicate train number is rejected.
- [ ] Admin cannot delete a train with bookings.
- [ ] Admin can add and edit stations using a modal.
- [ ] Duplicate station code is rejected.
- [ ] Admin cannot delete a station referenced by a train.
- [ ] Admin can search and view bookings by PNR.
- [ ] Admin booking list shows passenger count, not individual passenger details.
- [ ] Admin can view users.
- [ ] Admin cannot cancel or modify bookings.

### Visual acceptance

- [ ] Navy-and-white railway identity is consistent.
- [ ] Light neutral page background and white content surfaces are used.
- [ ] Typography and spacing are consistent.
- [ ] Horizontal navigation works on all relevant pages.
- [ ] Forms, buttons, tables, alerts, and status indicators are visually consistent.
- [ ] Confirmed and cancelled statuses are visually distinguishable.
- [ ] No footer appears.
- [ ] No unnecessary icons, charts, animations, illustrations, or external fonts are used.
- [ ] Layout adapts sensibly to different desktop monitor widths.

---

## 11. Completion requirements

Before declaring the implementation complete, the coding agent must:

1. Read this document and the previously supplied database specification together.
2. Treat the previously supplied database specification as authoritative.
3. Generate the PHP and CSS pages listed in the folder structure.
4. Connect every interface to the existing database schema.
5. Ensure all navigation links and form actions lead to working implementations.
6. Ensure table and column names match the supplied schema exactly.
7. Implement authentication, role authorization, validation, and safe database operations.
8. Test the full user journey from login/search through booking, confirmation, history, and cancellation.
9. Test the admin journey for train, station, booking, and user management.
10. Fix PHP errors, SQL errors, broken redirects, and authorization problems.
11. Avoid placeholder buttons or non-functional interface elements.
12. Do not report a feature as complete unless its server-side behaviour and database interaction work.

**End of interface and application specification.**
