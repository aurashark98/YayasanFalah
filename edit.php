<?php
include 'config.php';

if(isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT * FROM users WHERE id=$id";
    $result = $conn->query($sql);
    $user = $result->fetch_assoc();
}

if(isset($_POST['update'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    // Update query
    $sql = "UPDATE users SET username='$username', email='$email' WHERE id=$id";
    $conn->query($sql);
    header("Location: admin_panel.php");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Pengguna</title>
</head>
<body>
    <h1>Edit Pengguna</h1>
    <form method="POST" action="">
        <input type="text" name="username" value="<?php echo $user['username']; ?>" required>
        <input type="email" name="email" value="<?php echo $user['email']; ?>" required>
        <input type="submit" name="update" value="Update">
    </form>
</body>
</html>