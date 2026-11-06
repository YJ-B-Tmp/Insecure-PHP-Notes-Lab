<?php /** @var string $message @var array $notes @var array|false $editing */ ?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Campus Notes</title>
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<style>body{font:16px sans-serif;max-width:750px;margin:2rem auto}label{display:block;margin:.5rem 0}article{border:1px solid #aaa;padding:1rem;margin:1rem 0}form.inline{display:inline}</style>
</head>
<body>
<h1>Campus Notes</h1>
<?php if ($message !== ''): ?><p><?= e($message) ?></p><?php endif; ?>

<?php if (current_user_id() === null): ?>
<h2>Register or sign in</h2>
<form method="post">
  <?= csrf_field() ?>
  <label>Email <input name="email" type="email" required></label>
  <label>Password <input name="password" type="password" required minlength="12"></label>
  <button name="action" value="register">Register</button>
  <button name="action" value="login">Sign in</button>
</form>
<?php else: ?>
<p>User ID: <?= e(current_user_id()) ?> | Role: <?= e($_SESSION['role'] ?? '') ?></p>
<form method="post" class="inline">
  <?= csrf_field() ?>
  <button name="action" value="logout">Log out</button>
</form>

<?php if ($editing): ?>
<h2>Edit note</h2>
<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= e($editing['id']) ?>">
  <label>Title <input name="title" value="<?= e($editing['title']) ?>" required maxlength="120"></label>
  <label>Body <textarea name="body" required><?= e($editing['body']) ?></textarea></label>
  <button name="action" value="update">Update</button>
  <a href="/">Cancel</a>
</form>
<?php endif; ?>

<h2>Create a note</h2>
<form method="post">
  <?= csrf_field() ?>
  <label>Title <input name="title" required maxlength="120"></label>
  <label>Body <textarea name="body" required></textarea></label>
  <button name="action" value="save">Save</button>
</form>

<h2>My notes</h2>
<?php foreach ($notes as $note): ?>
<article>
  <h3><?= e($note['title']) ?></h3>
  <p><?= nl2br(e($note['body'])) ?></p>
  <a href="?edit=<?= e($note['id']) ?>">Edit</a>
  <form method="post" class="inline">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e($note['id']) ?>">
    <button name="action" value="delete">Delete</button>
  </form>
</article>
<?php endforeach; ?>
<?php endif; ?>
</body></html>