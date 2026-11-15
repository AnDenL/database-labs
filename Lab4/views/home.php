<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Menu</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
<?php include 'menu.php'; ?>

<h2>Welcome!</h2>
<p>Число відвідувань: <?= (int)$counter ?></p>
<?php include 'footer.php'; ?>
</body>
</html>
