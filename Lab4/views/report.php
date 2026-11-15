<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Зведений звіт (VIEW)</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
    <?php include 'menu.php'; ?>
    <h2>Зведений звіт по операціях (вибірка з VIEW)</h2>
    <table border="1" cellpadding="6" cellspacing="0">
        <thead>
            <tr>
                <th>ID</th>
                <th>Тип</th>
                <th>Дата</th>
                <th>Години</th>
                <th>Статус</th>
                <th>Поле</th>
                <th>Культура</th>
                <th>Склад / Покупець</th>
                <th>Тонни</th>
                <th>Сума, грн</th>
                <th>Працівники</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $r): ?>
            <tr>
                <td><?= (int)$r['op_id'] ?></td>
                <td><?= htmlspecialchars($r['op_type']) ?></td>
                <td><?= htmlspecialchars($r['op_date']) ?></td>
                <td><?= htmlspecialchars((string)$r['planned_hours']) ?></td>
                <td><?= htmlspecialchars($r['status']) ?></td>
                <td><?= htmlspecialchars($r['field_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($r['crop_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($r['warehouse_name'] ?? $r['buyer_name'] ?? '—') ?></td>
                <td><?= number_format((float)$r['tons'], 2, '.', ' ') ?></td>
                <td><?= number_format((float)$r['total_revenue'], 2, '.', ' ') ?></td>
                <td><?= htmlspecialchars($r['workers'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php include 'footer.php'; ?>
</body>
</html>
