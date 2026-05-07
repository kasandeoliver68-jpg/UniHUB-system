<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$page = $_GET['page'] ?? 'home';

try {
    $user = current_user();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();

        switch ($page) {
            case 'register':
                $name = trim($_POST['name'] ?? '');
                $email = strtolower(trim($_POST['email'] ?? ''));
                $password = $_POST['password'] ?? '';
                $university = find_university_by_email($email);

                if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
                    flash('error', 'Enter a valid name, university email, and password of at least 6 characters.');
                    redirect('register');
                }
                if (!$university) {
                    flash('error', 'That email domain is not registered for a UniHUB university.');
                    redirect('register');
                }

                $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    flash('error', 'An account with that email already exists.');
                    redirect('login');
                }

                $listingKey = bin2hex(random_bytes(8));
                $stmt = db()->prepare(
                    'INSERT INTO users (name, email, password_hash, university_id, listing_key, role)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $adminSuffix = '@admin.' . $university['domain'];
                $role = substr($email, -strlen($adminSuffix)) === $adminSuffix ? 'admin' : 'member';
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $university['id'], $listingKey, $role]);
                $id = (int) db()->lastInsertId();
                issue_otp($id, $email);
                $_SESSION['user_id'] = $id;
                flash('success', 'Account created. Use the verification code shown below for local testing.');
                redirect('verify');

            case 'login':
                $email = strtolower(trim($_POST['email'] ?? ''));
                $password = $_POST['password'] ?? '';
                $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
                $stmt->execute([$email]);
                $account = $stmt->fetch();

                if (!$account || !password_verify($password, $account['password_hash'])) {
                    flash('error', 'Invalid email or password.');
                    redirect('login');
                }

                $_SESSION['user_id'] = $account['id'];
                if (!$account['verified_at']) {
                    issue_otp((int) $account['id'], $account['email']);
                    flash('info', 'Verify your email to continue.');
                    redirect('verify');
                }
                flash('success', 'Welcome back.');
                redirect('dashboard');

            case 'verify':
                $user = current_user();
                if (!$user) {
                    redirect('login');
                }
                $otp = trim($_POST['otp'] ?? '');
                $expired = $user['otp_expires_at'] && strtotime($user['otp_expires_at']) < time();
                if ($expired || !$user['otp_code_hash'] || !password_verify($otp, $user['otp_code_hash'])) {
                    flash('error', 'Invalid or expired OTP.');
                    redirect('verify');
                }
                $stmt = db()->prepare('UPDATE users SET verified_at = NOW(), otp_code_hash = NULL, otp_expires_at = NULL WHERE id = ?');
                $stmt->execute([$user['id']]);
                unset($_SESSION['last_otp']);
                flash('success', 'Email verified. You are inside your university hub.');
                redirect('dashboard');

            case 'resend-otp':
                $user = current_user();
                if ($user) {
                    issue_otp((int) $user['id'], $user['email']);
                    flash('info', 'A new local testing OTP has been generated.');
                }
                redirect('verify');

            case 'event-save':
                $user = require_admin();
                $id = (int) ($_POST['id'] ?? 0);
                $eventDate = str_replace('T', ' ', trim($_POST['event_date'] ?? ''));
                $data = [
                    trim($_POST['title'] ?? ''),
                    trim($_POST['description'] ?? ''),
                    $eventDate,
                    trim($_POST['location'] ?? ''),
                    $user['university_id'],
                ];
                if ($data[0] === '' || $data[2] === '' || $data[3] === '') {
                    flash('error', 'Event title, date, and location are required.');
                    redirect('admin');
                }
                if ($id > 0) {
                    $stmt = db()->prepare('UPDATE events SET title = ?, description = ?, event_date = ?, location = ? WHERE id = ? AND university_id = ?');
                    $stmt->execute([$data[0], $data[1], $data[2], $data[3], $id, $user['university_id']]);
                } else {
                    $stmt = db()->prepare('INSERT INTO events (title, description, event_date, location, university_id, created_by) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$data[0], $data[1], $data[2], $data[3], $data[4], $user['id']]);
                }
                flash('success', 'Event saved.');
                redirect('admin');

            case 'event-delete':
                $user = require_admin();
                $stmt = db()->prepare('DELETE FROM events WHERE id = ? AND university_id = ?');
                $stmt->execute([(int) $_POST['id'], $user['university_id']]);
                flash('success', 'Event deleted.');
                redirect('admin');

            case 'rsvp':
                $user = require_auth();
                $eventId = (int) ($_POST['event_id'] ?? 0);
                $stmt = db()->prepare('SELECT id FROM rsvps WHERE user_id = ? AND event_id = ?');
                $stmt->execute([$user['id'], $eventId]);
                if ($stmt->fetch()) {
                    $stmt = db()->prepare('DELETE FROM rsvps WHERE user_id = ? AND event_id = ?');
                    $stmt->execute([$user['id'], $eventId]);
                    flash('info', 'RSVP removed.');
                } else {
                    $stmt = db()->prepare('INSERT INTO rsvps (user_id, event_id) VALUES (?, ?)');
                    $stmt->execute([$user['id'], $eventId]);
                    flash('success', 'RSVP confirmed.');
                }
                redirect('events');

            case 'listing-save':
                $user = require_auth();
                $id = (int) ($_POST['id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $price = (float) ($_POST['price'] ?? 0);
                $category = trim($_POST['category'] ?? '');
                $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
                $listingKey = trim($_POST['listing_key'] ?? '');

                if ($title === '' || $price <= 0 || $category === '' || $listingKey !== $user['listing_key']) {
                    flash('error', 'Listing details are incomplete or the seller security key is wrong.');
                    redirect('marketplace');
                }

                $images = listing_image_paths($_FILES['images'] ?? []);
                if ($id > 0) {
                    $stmt = db()->prepare('SELECT * FROM listings WHERE id = ? AND seller_id = ?');
                    $stmt->execute([$id, $user['id']]);
                    $listing = $stmt->fetch();
                    if (!$listing) {
                        flash('error', 'You can only edit your own listings.');
                        redirect('marketplace');
                    }
                    $mergedImages = $images ? json_encode($images) : $listing['image_paths'];
                    $stmt = db()->prepare(
                        'UPDATE listings SET title = ?, description = ?, price = ?, category = ?, quantity = ?, image_paths = ? WHERE id = ? AND seller_id = ?'
                    );
                    $stmt->execute([$title, $description, $price, $category, $quantity, $mergedImages, $id, $user['id']]);
                } else {
                    $stmt = db()->prepare(
                        'INSERT INTO listings (title, description, price, category, quantity, image_paths, seller_id, university_id)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$title, $description, $price, $category, $quantity, json_encode($images), $user['id'], $user['university_id']]);
                }
                flash('success', 'Listing saved.');
                redirect('marketplace');

            case 'listing-action':
                $user = require_auth();
                $id = (int) ($_POST['id'] ?? 0);
                $action = $_POST['action'] ?? '';
                if ($action === 'sold') {
                    $stmt = db()->prepare('UPDATE listings SET status = "sold" WHERE id = ? AND seller_id = ?');
                    $stmt->execute([$id, $user['id']]);
                    flash('success', 'Listing marked as sold.');
                } elseif ($action === 'delete') {
                    $stmt = db()->prepare('DELETE FROM listings WHERE id = ? AND seller_id = ?');
                    $stmt->execute([$id, $user['id']]);
                    flash('success', 'Listing deleted.');
                }
                redirect('marketplace');

            case 'cart-add':
                $user = require_auth();
                $listingId = (int) ($_POST['listing_id'] ?? 0);
                $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
                $stmt = db()->prepare('SELECT id FROM listings WHERE id = ? AND university_id = ? AND seller_id <> ? AND status = "available"');
                $stmt->execute([$listingId, $user['university_id'], $user['id']]);
                if (!$stmt->fetch()) {
                    flash('error', 'Only available listings from your hub can be added to cart.');
                    redirect('marketplace');
                }
                $stmt = db()->prepare('SELECT id FROM cart_items WHERE user_id = ? AND listing_id = ?');
                $stmt->execute([$user['id'], $listingId]);
                if ($stmt->fetch()) {
                    $stmt = db()->prepare('UPDATE cart_items SET quantity = quantity + ? WHERE user_id = ? AND listing_id = ?');
                    $stmt->execute([$quantity, $user['id'], $listingId]);
                } else {
                    $stmt = db()->prepare('INSERT INTO cart_items (user_id, listing_id, quantity) VALUES (?, ?, ?)');
                    $stmt->execute([$user['id'], $listingId, $quantity]);
                }
                flash('success', 'Item added to cart.');
                redirect('cart');

            case 'cart-update':
                $user = require_auth();
                foreach ($_POST['quantities'] ?? [] as $itemId => $qty) {
                    $qty = (int) $qty;
                    if ($qty <= 0) {
                        $stmt = db()->prepare('DELETE FROM cart_items WHERE id = ? AND user_id = ?');
                        $stmt->execute([(int) $itemId, $user['id']]);
                    } else {
                        $stmt = db()->prepare('UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?');
                        $stmt->execute([$qty, (int) $itemId, $user['id']]);
                    }
                }
                flash('success', 'Cart updated.');
                redirect('cart');

            case 'checkout':
                $user = require_auth();
                $phone = trim($_POST['phone'] ?? '');
                $stmt = db()->prepare(
                    'SELECT cart_items.*, listings.price, listings.quantity AS available
                     FROM cart_items JOIN listings ON listings.id = cart_items.listing_id
                     WHERE cart_items.user_id = ? AND listings.status = "available"'
                );
                $stmt->execute([$user['id']]);
                $items = $stmt->fetchAll();
                $total = 0;
                foreach ($items as $item) {
                    $total += min((int) $item['quantity'], (int) $item['available']) * (float) $item['price'];
                }
                if (!$items || $phone === '') {
                    flash('error', 'Add available items and a mobile money phone number before checkout.');
                    redirect('cart');
                }
                $stmt = db()->prepare('INSERT INTO mobile_payments (user_id, phone, amount, status) VALUES (?, ?, ?, "processed")');
                $stmt->execute([$user['id'], $phone, $total]);
                foreach ($items as $item) {
                    $purchased = min((int) $item['quantity'], (int) $item['available']);
                    $stmt = db()->prepare(
                        'UPDATE listings
                         SET quantity = GREATEST(quantity - ?, 0),
                             status = CASE WHEN GREATEST(quantity - ?, 0) = 0 THEN "sold" ELSE status END
                         WHERE id = ?'
                    );
                    $stmt->execute([$purchased, $purchased, $item['listing_id']]);
                }
                db()->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$user['id']]);
                flash('success', 'Mobile payment processed for ' . money($total) . '.');
                redirect('cart');

            case 'message-send':
                $user = require_auth();
                $listingId = (int) ($_POST['listing_id'] ?? 0);
                $body = trim($_POST['body'] ?? '');
                if ($body !== '') {
                    $stmt = db()->prepare('INSERT INTO messages (listing_id, sender_id, body) VALUES (?, ?, ?)');
                    $stmt->execute([$listingId, $user['id'], $body]);
                    flash('success', 'Message sent.');
                }
                redirect('listing', ['id' => $listingId]);

            case 'admin-user':
                $user = require_admin();
                $target = (int) ($_POST['id'] ?? 0);
                $role = $_POST['role'] === 'admin' ? 'admin' : 'member';
                if ($target !== (int) $user['id']) {
                    $stmt = db()->prepare('UPDATE users SET role = ? WHERE id = ? AND university_id = ?');
                    $stmt->execute([$role, $target, $user['university_id']]);
                    flash('success', 'User role updated.');
                }
                redirect('admin');

            case 'admin-listing-delete':
                $user = require_admin();
                $stmt = db()->prepare('DELETE FROM listings WHERE id = ? AND university_id = ?');
                $stmt->execute([(int) $_POST['id'], $user['university_id']]);
                flash('success', 'Listing removed from the hub.');
                redirect('admin');

            case 'admin-domain':
                require_admin();
                $name = trim($_POST['name'] ?? '');
                $domain = strtolower(trim($_POST['domain'] ?? ''));
                if ($name !== '' && $domain !== '') {
                    $stmt = db()->prepare('INSERT INTO universities (name, domain) VALUES (?, ?)');
                    $stmt->execute([$name, $domain]);
                    flash('success', 'University domain added.');
                }
                redirect('admin');
        }
    }
} catch (PDOException $exception) {
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>UniHUB setup</title><link rel="stylesheet" href="assets/styles.css">';
    echo '<main class="setup"><h1>UniHUB needs database setup</h1><p>Create a MySQL database named <strong>unihub</strong>, import <strong>database/schema.sql</strong>, then refresh this page.</p>';
    echo '<p class="muted">Database error: ' . e($exception->getMessage()) . '</p></main>';
    exit;
}

function render_header(string $title): void
{
    $user = current_user();
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> | UniHUB</title>
        <link rel="stylesheet" href="assets/styles.css">
    </head>
    <body>
    <header class="topbar">
        <a class="brand" href="<?= path($user ? 'dashboard' : 'home') ?>">UniHUB</a>
        <nav>
            <?php if ($user && $user['verified_at']): ?>
                <a href="<?= path('dashboard') ?>">Dashboard</a>
                <a href="<?= path('events') ?>">Events</a>
                <a href="<?= path('marketplace') ?>">Marketplace</a>
                <a href="<?= path('cart') ?>">Cart</a>
                <?php if ($user['role'] === 'admin'): ?><a href="<?= path('admin') ?>">Admin</a><?php endif; ?>
                <a href="<?= path('logout') ?>">Logout</a>
            <?php else: ?>
                <a href="<?= path('login') ?>">Login</a>
                <a class="button small" href="<?= path('register') ?>">Sign up</a>
            <?php endif; ?>
        </nav>
    </header>
    <main class="shell">
        <?php foreach (flashes() as $flash): ?>
            <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
    <?php
}

function render_footer(): void
{
    ?>
    </main>
    </body>
    </html>
    <?php
}

if ($page === 'logout') {
    session_destroy();
    session_start();
    flash('success', 'You have been logged out.');
    redirect('home');
}

switch ($page) {
    case 'home':
        render_header('Campus marketplace and events');
        ?>
        <section class="hero">
            <div>
                <p class="eyebrow">Verified university communities</p>
                <h1>UniHUB</h1>
                <p>Join your university hub, discover campus events, buy and sell safely, and message students from the same verified institution.</p>
                <div class="actions">
                    <a class="button" href="<?= path('register') ?>">Create account</a>
                    <a class="button secondary" href="<?= path('login') ?>">Login</a>
                </div>
            </div>
            <div class="hero-panel">
                <strong>Fast path</strong>
                <span>Sign up</span>
                <span>Verify OTP</span>
                <span>Browse events and listings</span>
            </div>
        </section>
        <?php
        render_footer();
        break;

    case 'register':
    case 'login':
        render_header($page === 'register' ? 'Sign up' : 'Login');
        ?>
        <section class="auth-grid">
            <form class="panel" method="post">
                <?= csrf_field() ?>
                <h1><?= $page === 'register' ? 'Create your account' : 'Welcome back' ?></h1>
                <?php if ($page === 'register'): ?>
                    <label>Name<input required name="name" autocomplete="name"></label>
                <?php endif; ?>
                <label>University email<input required type="email" name="email" autocomplete="email" placeholder="name@std.must.ac.ug"></label>
                <label>Password<input required type="password" name="password" minlength="6" autocomplete="<?= $page === 'register' ? 'new-password' : 'current-password' ?>"></label>
                <button class="button" type="submit"><?= $page === 'register' ? 'Sign up' : 'Login' ?></button>
                <p class="muted"><?= $page === 'register' ? 'Already registered?' : 'New to UniHUB?' ?>
                    <a href="<?= path($page === 'register' ? 'login' : 'register') ?>"><?= $page === 'register' ? 'Login' : 'Create account' ?></a>
                </p>
            </form>
            <aside class="panel quiet">
                <h2>Allowed starting domain</h2>
                <p>Seed data includes Mbarara University of Science and Technology.</p>
                <code>@std.must.ac.ug</code>
            </aside>
        </section>
        <?php
        render_footer();
        break;

    case 'verify':
        $user = current_user();
        if (!$user) {
            redirect('login');
        }
        render_header('Verify email');
        ?>
        <form class="panel narrow" method="post">
            <?= csrf_field() ?>
            <h1>Email verification</h1>
            <p class="muted">Enter the six-digit OTP for <?= e($user['email']) ?>.</p>
            <?php if (!empty($_SESSION['last_otp'])): ?>
                <p class="local-otp">Local testing OTP: <strong><?= e($_SESSION['last_otp']) ?></strong></p>
            <?php endif; ?>
            <label>OTP<input name="otp" inputmode="numeric" pattern="[0-9]{6}" required></label>
            <button class="button" type="submit">Verify email</button>
        </form>
        <form class="inline-form center" method="post" action="<?= path('resend-otp') ?>">
            <?= csrf_field() ?>
            <button class="link-button" type="submit">Generate a new OTP</button>
        </form>
        <?php
        render_footer();
        break;

    case 'dashboard':
        $user = require_auth();
        $stmt = db()->prepare('SELECT * FROM events WHERE university_id = ? ORDER BY event_date ASC LIMIT 3');
        $stmt->execute([$user['university_id']]);
        $events = $stmt->fetchAll();
        $stmt = db()->prepare('SELECT * FROM listings WHERE university_id = ? AND status = "available" ORDER BY created_at DESC LIMIT 4');
        $stmt->execute([$user['university_id']]);
        $listings = $stmt->fetchAll();
        render_header('Dashboard');
        ?>
        <section class="page-head">
            <div>
                <p class="eyebrow"><?= e($user['university_name']) ?></p>
                <h1>Hello, <?= e($user['name']) ?></h1>
            </div>
            <span class="badge"><?= e($user['role']) ?></span>
        </section>
        <section class="stats">
            <div><strong><?= table_count('users', (int) $user['university_id']) ?></strong><span>Users</span></div>
            <div><strong><?= table_count('events', (int) $user['university_id']) ?></strong><span>Events</span></div>
            <div><strong><?= table_count('listings', (int) $user['university_id']) ?></strong><span>Listings</span></div>
            <div><strong><?= e($user['listing_key']) ?></strong><span>Seller key</span></div>
        </section>
        <div class="two-col">
            <section>
                <h2>Upcoming events</h2>
                <?php foreach ($events as $event): ?>
                    <article class="row-card">
                        <strong><?= e($event['title']) ?></strong>
                        <span><?= e(date('M j, Y H:i', strtotime($event['event_date']))) ?> at <?= e($event['location']) ?></span>
                    </article>
                <?php endforeach; ?>
            </section>
            <section>
                <h2>Fresh listings</h2>
                <?php foreach ($listings as $listing): ?>
                    <article class="row-card">
                        <strong><?= e($listing['title']) ?></strong>
                        <span><?= money($listing['price']) ?> · <?= e($listing['category']) ?></span>
                    </article>
                <?php endforeach; ?>
            </section>
        </div>
        <?php
        render_footer();
        break;

    case 'events':
        $user = require_auth();
        $stmt = db()->prepare(
            'SELECT events.*, COUNT(rsvps.user_id) AS rsvp_count,
             SUM(CASE WHEN rsvps.user_id = ? THEN 1 ELSE 0 END) AS mine
             FROM events LEFT JOIN rsvps ON rsvps.event_id = events.id
             WHERE events.university_id = ?
             GROUP BY events.id ORDER BY events.event_date ASC'
        );
        $stmt->execute([$user['id'], $user['university_id']]);
        $events = $stmt->fetchAll();
        render_header('Events');
        ?>
        <section class="page-head"><h1>Campus events</h1><a class="button secondary" href="<?= path('dashboard') ?>">Back</a></section>
        <section class="cards">
            <?php foreach ($events as $event): ?>
                <article class="card">
                    <p class="eyebrow"><?= e(date('M j, Y H:i', strtotime($event['event_date']))) ?></p>
                    <h2><?= e($event['title']) ?></h2>
                    <p><?= e($event['description']) ?></p>
                    <p class="muted"><?= e($event['location']) ?> · <?= (int) $event['rsvp_count'] ?> attending</p>
                    <form method="post" action="<?= path('rsvp') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                        <button class="button small" type="submit"><?= $event['mine'] ? 'Cancel RSVP' : 'RSVP' ?></button>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>
        <?php
        render_footer();
        break;

    case 'marketplace':
        $user = require_auth();
        $query = trim($_GET['q'] ?? '');
        $params = [$user['university_id']];
        $sql = 'SELECT listings.*, users.name AS seller_name FROM listings JOIN users ON users.id = listings.seller_id WHERE listings.university_id = ?';
        if ($query !== '') {
            $sql .= ' AND (listings.title LIKE ? OR listings.category LIKE ? OR listings.description LIKE ?)';
            $like = '%' . $query . '%';
            array_push($params, $like, $like, $like);
        }
        $sql .= ' ORDER BY listings.created_at DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $listings = $stmt->fetchAll();
        render_header('Marketplace');
        ?>
        <section class="page-head">
            <h1>Marketplace</h1>
            <form class="search" method="get">
                <input type="hidden" name="page" value="marketplace">
                <input name="q" value="<?= e($query) ?>" placeholder="Search listings">
                <button class="button small" type="submit">Search</button>
            </form>
        </section>
        <details class="panel">
            <summary>Create a listing</summary>
            <form class="grid-form" method="post" enctype="multipart/form-data" action="<?= path('listing-save') ?>">
                <?= csrf_field() ?>
                <label>Title<input name="title" required></label>
                <label>Category<input name="category" required placeholder="Books, electronics, furniture"></label>
                <label>Price<input name="price" required type="number" min="1" step="100"></label>
                <label>Quantity<input name="quantity" type="number" min="1" value="1"></label>
                <label>Seller key<input name="listing_key" required placeholder="<?= e($user['listing_key']) ?>"></label>
                <label class="full">Images<input name="images[]" type="file" accept="image/*" multiple></label>
                <label class="full">Description<textarea name="description" rows="3"></textarea></label>
                <button class="button" type="submit">Publish listing</button>
            </form>
        </details>
        <section class="listing-grid">
            <?php foreach ($listings as $listing): $images = json_decode($listing['image_paths'] ?: '[]', true) ?: []; ?>
                <article class="listing">
                    <a href="<?= path('listing', ['id' => $listing['id']]) ?>">
                        <?php if ($images): ?><img src="<?= e($images[0]) ?>" alt=""><?php else: ?><div class="image-fallback">UniHUB</div><?php endif; ?>
                    </a>
                    <div>
                        <span class="badge"><?= e($listing['status']) ?></span>
                        <h2><a href="<?= path('listing', ['id' => $listing['id']]) ?>"><?= e($listing['title']) ?></a></h2>
                        <p><?= money($listing['price']) ?> · <?= e($listing['category']) ?></p>
                        <p class="muted">Seller: <?= e($listing['seller_name']) ?></p>
                    </div>
                    <?php if ($listing['seller_id'] == $user['id']): ?>
                        <form class="inline-form" method="post" action="<?= path('listing-action') ?>">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $listing['id'] ?>">
                            <button class="button small secondary" name="action" value="sold">Sold</button>
                            <button class="button small danger" name="action" value="delete">Delete</button>
                        </form>
                    <?php elseif ($listing['status'] === 'available'): ?>
                        <form class="inline-form" method="post" action="<?= path('cart-add') ?>">
                            <?= csrf_field() ?><input type="hidden" name="listing_id" value="<?= (int) $listing['id'] ?>">
                            <input class="qty" name="quantity" type="number" min="1" max="<?= (int) $listing['quantity'] ?>" value="1">
                            <button class="button small">Add</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
        <?php
        render_footer();
        break;

    case 'listing':
        $user = require_auth();
        $id = (int) ($_GET['id'] ?? 0);
        $stmt = db()->prepare('SELECT listings.*, users.name AS seller_name FROM listings JOIN users ON users.id = listings.seller_id WHERE listings.id = ? AND listings.university_id = ?');
        $stmt->execute([$id, $user['university_id']]);
        $listing = $stmt->fetch();
        if (!$listing) {
            flash('error', 'Listing not found in your hub.');
            redirect('marketplace');
        }
        $stmt = db()->prepare('SELECT messages.*, users.name FROM messages JOIN users ON users.id = messages.sender_id WHERE listing_id = ? ORDER BY messages.created_at ASC');
        $stmt->execute([$id]);
        $messages = $stmt->fetchAll();
        $images = json_decode($listing['image_paths'] ?: '[]', true) ?: [];
        render_header($listing['title']);
        ?>
        <article class="detail">
            <div class="gallery">
                <?php foreach ($images as $image): ?><img src="<?= e($image) ?>" alt=""><?php endforeach; ?>
                <?php if (!$images): ?><div class="image-fallback large">UniHUB</div><?php endif; ?>
            </div>
            <div>
                <span class="badge"><?= e($listing['status']) ?></span>
                <h1><?= e($listing['title']) ?></h1>
                <p class="price"><?= money($listing['price']) ?></p>
                <p><?= e($listing['description']) ?></p>
                <p class="muted">Seller: <?= e($listing['seller_name']) ?> · <?= e($listing['category']) ?></p>
            </div>
        </article>
        <?php if ($listing['seller_id'] == $user['id']): ?>
            <details class="panel">
                <summary>Edit listing</summary>
                <form class="grid-form" method="post" enctype="multipart/form-data" action="<?= path('listing-save') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $listing['id'] ?>">
                    <label>Title<input name="title" value="<?= e($listing['title']) ?>" required></label>
                    <label>Category<input name="category" value="<?= e($listing['category']) ?>" required></label>
                    <label>Price<input name="price" value="<?= e((string) $listing['price']) ?>" required type="number" min="1"></label>
                    <label>Quantity<input name="quantity" value="<?= (int) $listing['quantity'] ?>" type="number" min="1"></label>
                    <label>Seller key<input name="listing_key" required></label>
                    <label class="full">Replace images<input name="images[]" type="file" accept="image/*" multiple></label>
                    <label class="full">Description<textarea name="description" rows="3"><?= e($listing['description']) ?></textarea></label>
                    <button class="button" type="submit">Save changes</button>
                </form>
            </details>
        <?php endif; ?>
        <section class="panel">
            <h2>Messages</h2>
            <div class="messages">
                <?php foreach ($messages as $message): ?>
                    <p><strong><?= e($message['name']) ?>:</strong> <?= e($message['body']) ?></p>
                <?php endforeach; ?>
            </div>
            <form method="post" action="<?= path('message-send') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="listing_id" value="<?= (int) $listing['id'] ?>">
                <label>Message<textarea name="body" rows="3" required></textarea></label>
                <button class="button small" type="submit">Send</button>
            </form>
        </section>
        <?php
        render_footer();
        break;

    case 'cart':
        $user = require_auth();
        $stmt = db()->prepare(
            'SELECT cart_items.id AS cart_id, cart_items.quantity AS cart_quantity, listings.*
             FROM cart_items JOIN listings ON listings.id = cart_items.listing_id
             WHERE cart_items.user_id = ? ORDER BY cart_items.created_at DESC'
        );
        $stmt->execute([$user['id']]);
        $items = $stmt->fetchAll();
        $total = array_reduce($items, fn($sum, $item) => $sum + ((int) $item['cart_quantity'] * (float) $item['price']), 0.0);
        render_header('Cart');
        ?>
        <section class="page-head"><h1>Your cart</h1><strong><?= money($total) ?></strong></section>
        <form class="panel" method="post" action="<?= path('cart-update') ?>">
            <?= csrf_field() ?>
            <?php foreach ($items as $item): ?>
                <div class="cart-line">
                    <span><?= e($item['title']) ?></span>
                    <span><?= money($item['price']) ?></span>
                    <input name="quantities[<?= (int) $item['cart_id'] ?>]" type="number" min="0" max="<?= (int) $item['quantity'] ?>" value="<?= (int) $item['cart_quantity'] ?>">
                </div>
            <?php endforeach; ?>
            <button class="button small secondary" type="submit">Update cart</button>
        </form>
        <form class="panel narrow" method="post" action="<?= path('checkout') ?>">
            <?= csrf_field() ?>
            <h2>Mobile payment</h2>
            <label>Phone number<input name="phone" placeholder="+256..." required></label>
            <button class="button" type="submit">Pay <?= money($total) ?></button>
        </form>
        <?php
        render_footer();
        break;

    case 'admin':
        $user = require_admin();
        $stmt = db()->prepare('SELECT * FROM users WHERE university_id = ? ORDER BY created_at DESC');
        $stmt->execute([$user['university_id']]);
        $users = $stmt->fetchAll();
        $stmt = db()->prepare('SELECT * FROM events WHERE university_id = ? ORDER BY event_date DESC');
        $stmt->execute([$user['university_id']]);
        $events = $stmt->fetchAll();
        $stmt = db()->prepare('SELECT listings.*, users.name AS seller_name FROM listings JOIN users ON users.id = listings.seller_id WHERE listings.university_id = ? ORDER BY listings.created_at DESC');
        $stmt->execute([$user['university_id']]);
        $listings = $stmt->fetchAll();
        $stmt = db()->query('SELECT * FROM universities ORDER BY name');
        $universities = $stmt->fetchAll();
        render_header('Admin');
        ?>
        <section class="page-head"><h1>Admin panel</h1><span class="badge"><?= e($user['university_name']) ?></span></section>
        <section class="stats">
            <div><strong><?= count($users) ?></strong><span>Total users</span></div>
            <div><strong><?= count($events) ?></strong><span>Events</span></div>
            <div><strong><?= count($listings) ?></strong><span>Listings</span></div>
        </section>
        <div class="two-col">
            <section class="panel">
                <h2>Create event</h2>
                <form class="stack" method="post" action="<?= path('event-save') ?>">
                    <?= csrf_field() ?>
                    <label>Title<input name="title" required></label>
                    <label>Date<input name="event_date" type="datetime-local" required></label>
                    <label>Location<input name="location" required></label>
                    <label>Description<textarea name="description"></textarea></label>
                    <button class="button small" type="submit">Save event</button>
                </form>
            </section>
            <section class="panel">
                <h2>Add university domain</h2>
                <form class="stack" method="post" action="<?= path('admin-domain') ?>">
                    <?= csrf_field() ?>
                    <label>Name<input name="name" required></label>
                    <label>Domain<input name="domain" required placeholder="std.example.ac.ug"></label>
                    <button class="button small" type="submit">Add domain</button>
                </form>
                <p class="muted"><?= count($universities) ?> registered universities</p>
            </section>
        </div>
        <section class="panel">
            <h2>Manage users</h2>
            <?php foreach ($users as $member): ?>
                <form class="admin-line" method="post" action="<?= path('admin-user') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $member['id'] ?>">
                    <span><?= e($member['name']) ?><small><?= e($member['email']) ?></small></span>
                    <select name="role">
                        <option value="member" <?= $member['role'] === 'member' ? 'selected' : '' ?>>Member</option>
                        <option value="admin" <?= $member['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                    <button class="button small secondary">Update</button>
                </form>
            <?php endforeach; ?>
        </section>
        <section class="panel">
            <h2>Manage events</h2>
            <?php foreach ($events as $event): ?>
                <form class="admin-edit" method="post" action="<?= path('event-save') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
                    <input name="title" value="<?= e($event['title']) ?>" required>
                    <input name="event_date" type="datetime-local" value="<?= e(date('Y-m-d\TH:i', strtotime($event['event_date']))) ?>" required>
                    <input name="location" value="<?= e($event['location']) ?>" required>
                    <textarea name="description" rows="2"><?= e($event['description']) ?></textarea>
                    <button class="button small secondary">Save</button>
                </form>
                <form class="compact-delete" method="post" action="<?= path('event-delete') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
                    <button class="button small danger">Delete <?= e($event['title']) ?></button>
                </form>
            <?php endforeach; ?>
        </section>
        <section class="panel">
            <h2>Moderate listings</h2>
            <?php foreach ($listings as $listing): ?>
                <form class="admin-line" method="post" action="<?= path('admin-listing-delete') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $listing['id'] ?>">
                    <span><?= e($listing['title']) ?><small><?= e($listing['seller_name']) ?> · <?= e($listing['status']) ?></small></span>
                    <button class="button small danger">Delete</button>
                </form>
            <?php endforeach; ?>
        </section>
        <?php
        render_footer();
        break;

    default:
        http_response_code(404);
        render_header('Not found');
        echo '<section class="panel narrow"><h1>Page not found</h1><a class="button" href="' . path('home') . '">Go home</a></section>';
        render_footer();
}
