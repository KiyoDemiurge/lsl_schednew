
<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = 'localhost';
$dbname = 'lsc_faculty_system';
$dbuser = 'root';
$dbpass = '';

try {
    $conn = new mysqli($host, $dbuser, $dbpass, $dbname);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    exit('Database connection failed. Check database.sql and db.php.');
}

/*
 * Create sample accounts for a fresh local project.
 * Change these passwords before real deployment.
 */
function seedAccount($conn, $username, $password, $role, $facultyId = null) {
    $check = $conn->prepare("SELECT id FROM users WHERE username=?");
    $check->bind_param("s", $username);
    $check->execute();

    if ($check->get_result()->num_rows === 0) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            "INSERT INTO users (username,password,role,faculty_id)
             VALUES (?,?,?,?)"
        );
        $stmt->bind_param("sssi", $username, $hash, $role, $facultyId);
        $stmt->execute();
    }
}

$count = $conn->query("SELECT COUNT(*) AS total FROM faculty")->fetch_assoc();

if ((int)$count['total'] === 0) {
    $stmt = $conn->prepare(
        "INSERT INTO faculty
        (faculty_id,full_name,email,department_id,employment_type,specialization)
        VALUES (?,?,?,?,?,?)"
    );
    $fid = 'FAC-001';
    $name = 'Juan Dela Cruz';
    $email = 'faculty@example.com';
    $dept = 1;
    $type = 'Full-time';
    $spec = 'Web Development';
    $stmt->bind_param("sssiss", $fid, $name, $email, $dept, $type, $spec);
    $stmt->execute();
}

$facultyRow = $conn->query("SELECT id FROM faculty ORDER BY id LIMIT 1")->fetch_assoc();
$sampleFacultyId = $facultyRow ? (int)$facultyRow['id'] : null;

seedAccount($conn, 'admin', 'Admin123!', 'admin');
seedAccount($conn, 'faculty01', 'Faculty123!', 'faculty', $sampleFacultyId);
