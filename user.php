<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRUD User</title>
    <link rel="stylesheet" href="styleuser.css">
</head>
<body>
    <div class="container">
        <h1>CRUD User</h1>
        <form action="create.php" method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Tambah User</button>
        </form>

        <h2>Daftar Pengguna</h2>
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                include 'configuser.php';
                $sql = "SELECT * FROM users";
                $result = $conn->query($sql);

                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo "<tr>
                                <td>{$row['username']}</td>
                                <td>{$row['email']}</td>
                                <td><a href='delete.php?id={$row['id']}'>Hapus</a></td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='4'>Tidak ada pengguna</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</body>
</html>