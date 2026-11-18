<?php
require_once 'db.php';
require_once 'auth.php';

$page = $_GET['page'] ?? 'dashboard';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'login') {
        verifyCsrf();

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $conn->prepare(
            "SELECT u.*, f.full_name FROM users u
             LEFT JOIN faculty f ON f.id=u.faculty_id
             WHERE u.username=? LIMIT 1"
        );
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
                'faculty_id' => $user['faculty_id'],
                'name' => $user['full_name'] ?: $user['username']
            ];
            logAction($conn, 'Login', 'Successful login');
            header('Location: index.php');
            exit;
        }

        $error = 'Invalid username or password.';
        $page = 'login';
    } elseif (($_POST['action'] ?? '') === 'logout') {
        verifyCsrf();
        $_SESSION = [];
        session_destroy();
        header('Location: index.php?page=login');
        exit;
    } else {
        requireLogin();
        verifyCsrf();

        $action = $_POST['action'] ?? '';
        $role = $_SESSION['user']['role'];

        try {
            if ($role === 'admin') {
                switch ($action) {
                    case 'save_faculty':
                        $id = (int)($_POST['id'] ?? 0);
                        $fid = trim($_POST['faculty_id'] ?? '');
                        $name = trim($_POST['full_name'] ?? '');
                        $email = trim($_POST['email'] ?? '');
                        $dept = (int)($_POST['department_id'] ?? 0);
                        $type = $_POST['employment_type'] ?? 'Full-time';
                        $spec = trim($_POST['specialization'] ?? '');
                        $status = $_POST['status'] ?? 'Active';

                        if ($fid === '' || $name === '') {
                            throw new Exception('Faculty ID and name are required.');
                        }
                        if (!in_array($type, ['Full-time','Part-time'], true) ||
                            !in_array($status, ['Active','Inactive'], true)) {
                            throw new Exception('Invalid faculty settings.');
                        }

                        if ($id) {
                            $stmt = $conn->prepare(
                                "UPDATE faculty SET faculty_id=?,full_name=?,email=?,
                                 department_id=NULLIF(?,0),employment_type=?,
                                 specialization=?,status=? WHERE id=?"
                            );
                            $stmt->bind_param(
                                "sssisssi", $fid,$name,$email,$dept,$type,$spec,$status,$id
                            );
                        } else {
                            $stmt = $conn->prepare(
                                "INSERT INTO faculty
                                (faculty_id,full_name,email,department_id,
                                 employment_type,specialization,status)
                                VALUES (?,?,?,NULLIF(?,0),?,?,?)"
                            );
                            $stmt->bind_param(
                                "sssisss", $fid,$name,$email,$dept,$type,$spec,$status
                            );
                        }
                        $stmt->execute();
                        logAction($conn, 'Save faculty', $fid);
                        flash('Faculty record saved.');
                        break;

                    case 'delete_faculty':
                        $id = (int)$_POST['id'];
                        $stmt = $conn->prepare("DELETE FROM faculty WHERE id=?");
                        $stmt->bind_param("i", $id);
                        $stmt->execute();
                        flash('Faculty record deleted.');
                        break;

                    case 'save_department':
                        $id = (int)($_POST['id'] ?? 0);
                        $code = trim($_POST['code'] ?? '');
                        $name = trim($_POST['name'] ?? '');
                        $head = trim($_POST['department_head'] ?? '');
                        if ($code === '' || $name === '') {
                            throw new Exception('Department code and name are required.');
                        }

                        if ($id) {
                            $stmt = $conn->prepare(
                                "UPDATE departments SET code=?,name=?,department_head=? WHERE id=?"
                            );
                            $stmt->bind_param("sssi", $code,$name,$head,$id);
                        } else {
                            $stmt = $conn->prepare(
                                "INSERT INTO departments (code,name,department_head) VALUES (?,?,?)"
                            );
                            $stmt->bind_param("sss", $code,$name,$head);
                        }
                        $stmt->execute();
                        flash('Department saved.');
                        break;

                    case 'save_subject':
                        $id = (int)($_POST['id'] ?? 0);
                        $code = trim($_POST['code'] ?? '');
                        $name = trim($_POST['name'] ?? '');
                        $units = (float)($_POST['units'] ?? 3);
                        $lecture = (float)($_POST['lecture_hours'] ?? 0);
                        $lab = (float)($_POST['lab_hours'] ?? 0);
                        $dept = (int)($_POST['department_id'] ?? 0);

                        if ($code === '' || $name === '' || $units < 0 ||
                            $lecture < 0 || $lab < 0) {
                            throw new Exception('Enter valid subject information.');
                        }

                        if ($id) {
                            $stmt = $conn->prepare(
                                "UPDATE subjects SET code=?,name=?,units=?,
                                 lecture_hours=?,lab_hours=?,department_id=NULLIF(?,0)
                                 WHERE id=?"
                            );
                            $stmt->bind_param(
                                "ssdddii", $code,$name,$units,$lecture,$lab,$dept,$id
                            );
                        } else {
                            $stmt = $conn->prepare(
                                "INSERT INTO subjects
                                (code,name,units,lecture_hours,lab_hours,department_id)
                                VALUES (?,?,?,?,?,NULLIF(?,0))"
                            );
                            $stmt->bind_param(
                                "ssdddi", $code,$name,$units,$lecture,$lab,$dept
                            );
                        }
                        $stmt->execute();
                        flash('Subject saved.');
                        break;

                    case 'save_section':
                        $id = (int)($_POST['id'] ?? 0);
                        $name = trim($_POST['name'] ?? '');
                        $program = trim($_POST['program'] ?? '');
                        $year = trim($_POST['year_level'] ?? '');
                        $ay = trim($_POST['academic_year'] ?? '');
                        $semester = trim($_POST['semester'] ?? '');

                        if (!$name || !$program || !$year || !$ay || !$semester) {
                            throw new Exception('Complete all section fields.');
                        }

                        if ($id) {
                            $stmt = $conn->prepare(
                                "UPDATE sections SET name=?,program=?,year_level=?,
                                 academic_year=?,semester=? WHERE id=?"
                            );
                            $stmt->bind_param("sssssi",$name,$program,$year,$ay,$semester,$id);
                        } else {
                            $stmt = $conn->prepare(
                                "INSERT INTO sections
                                (name,program,year_level,academic_year,semester)
                                VALUES (?,?,?,?,?)"
                            );
                            $stmt->bind_param("sssss",$name,$program,$year,$ay,$semester);
                        }
                        $stmt->execute();
                        flash('Section saved.');
                        break;

                    case 'save_room':
                        $id = (int)($_POST['id'] ?? 0);
                        $room = trim($_POST['room_number'] ?? '');
                        $building = trim($_POST['building'] ?? '');
                        $type = $_POST['room_type'] ?? 'Lecture';
                        $capacity = (int)($_POST['capacity'] ?? 40);
                        $equipment = trim($_POST['equipment'] ?? '');
                        $status = $_POST['status'] ?? 'Available';

                        if (!$room || $capacity < 1 ||
                            !in_array($type,['Lecture','Laboratory','Other'],true) ||
                            !in_array($status,['Available','Unavailable'],true)) {
                            throw new Exception('Enter valid classroom information.');
                        }

                        if ($id) {
                            $stmt = $conn->prepare(
                                "UPDATE classrooms SET room_number=?,building=?,room_type=?,
                                 capacity=?,equipment=?,status=? WHERE id=?"
                            );
                            $stmt->bind_param(
                                "sssissi",$room,$building,$type,$capacity,$equipment,$status,$id
                            );
                        } else {
                            $stmt = $conn->prepare(
                                "INSERT INTO classrooms
                                (room_number,building,room_type,capacity,equipment,status)
                                VALUES (?,?,?,?,?,?)"
                            );
                            $stmt->bind_param(
                                "sssiss",$room,$building,$type,$capacity,$equipment,$status
                            );
                        }
                        $stmt->execute();
                        flash('Classroom saved.');
                        break;

                    case 'assign_workload':
                        $faculty = (int)($_POST['faculty_id'] ?? 0);
                        $subject = (int)($_POST['subject_id'] ?? 0);
                        $section = (int)($_POST['section_id'] ?? 0);
                        $ay = trim($_POST['academic_year'] ?? '');
                        $semester = trim($_POST['semester'] ?? '');

                        $stmt = $conn->prepare(
                            "SELECT COUNT(*) n FROM faculty WHERE id=? AND status='Active'"
                        );
                        $stmt->bind_param("i",$faculty);
                        $stmt->execute();
                        if (!$stmt->get_result()->fetch_assoc()['n']) {
                            throw new Exception('Select an active faculty member.');
                        }

                        $stmt = $conn->prepare(
                            "INSERT INTO teaching_assignments
                            (faculty_id,subject_id,section_id,academic_year,semester)
                            VALUES (?,?,?,?,?)"
                        );
                        $stmt->bind_param("iiiss",$faculty,$subject,$section,$ay,$semester);
                        $stmt->execute();
                        logAction($conn,'Assign workload',"Faculty ID: $faculty");
                        flash('Teaching assignment created.');
                        break;

                    case 'save_schedule':
                        $assignment = (int)($_POST['assignment_id'] ?? 0);
                        $room = (int)($_POST['classroom_id'] ?? 0);
                        $day = $_POST['day_of_week'] ?? '';
                        $start = $_POST['start_time'] ?? '';
                        $end = $_POST['end_time'] ?? '';

                        $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                        if (!$assignment || !$room || !in_array($day,$days,true) ||
                            !preg_match('/^\d{2}:\d{2}$/',$start) ||
                            !preg_match('/^\d{2}:\d{2}$/',$end) || $start >= $end) {
                            throw new Exception('Enter a valid schedule and time range.');
                        }

                        $stmt = $conn->prepare(
                            "SELECT a.faculty_id,a.section_id,s.name AS subject_name,
                             s.code AS subject_code
                             FROM teaching_assignments a
                             JOIN subjects s ON s.id=a.subject_id
                             WHERE a.id=?"
                        );
                        $stmt->bind_param("i",$assignment);
                        $stmt->execute();
                        $a = $stmt->get_result()->fetch_assoc();
                        if (!$a) throw new Exception('Teaching assignment not found.');

                        $stmt = $conn->prepare(
                            "SELECT cs.id FROM class_schedules cs
                             JOIN teaching_assignments a ON a.id=cs.assignment_id
                             WHERE cs.day_of_week=?
                             AND cs.start_time < ? AND cs.end_time > ?
                             AND (a.faculty_id=? OR a.section_id=? OR cs.classroom_id=?)
                             LIMIT 1"
                        );
                        $stmt->bind_param(
                            "sssiii",$day,$end,$start,$a['faculty_id'],
                            $a['section_id'],$room
                        );
                        $stmt->execute();

                        if ($stmt->get_result()->num_rows) {
                            throw new Exception(
                                'Schedule conflict detected: faculty, section, or room is already booked.'
                            );
                        }

                        $stmt = $conn->prepare(
                            "INSERT INTO class_schedules
                            (assignment_id,classroom_id,day_of_week,start_time,end_time)
                            VALUES (?,?,?,?,?)"
                        );
                        $stmt->bind_param("iisss",$assignment,$room,$day,$start,$end);
                        $stmt->execute();
                        logAction($conn,'Create schedule',$a['subject_code']." $day $start-$end");
                        flash('Schedule created successfully.');
                        break;

                    case 'generate_auto_schedule':
                        $facultyId = (int)($_POST['faculty_id'] ?? 0);
                        $roomId = (int)($_POST['classroom_id'] ?? 0);

                        if ($facultyId < 1 || $roomId < 1) {
                            throw new Exception('Select a faculty member and an available classroom first.');
                        }

                        $facultyStmt = $conn->prepare(
                            "SELECT id, full_name FROM faculty WHERE id=? AND status='Active' LIMIT 1"
                        );
                        $facultyStmt->bind_param("i", $facultyId);
                        $facultyStmt->execute();
                        $selectedFaculty = $facultyStmt->get_result()->fetch_assoc();

                        if (!$selectedFaculty) {
                            throw new Exception('The selected faculty member is not active or no longer exists.');
                        }

                        $roomStmt = $conn->prepare(
                            "SELECT id, room_number FROM classrooms WHERE id=? AND status='Available' LIMIT 1"
                        );
                        $roomStmt->bind_param("i", $roomId);
                        $roomStmt->execute();
                        $selectedRoom = $roomStmt->get_result()->fetch_assoc();

                        if (!$selectedRoom) {
                            throw new Exception('The selected classroom is not available. Please select an available classroom.');
                        }

                        $assignmentStmt = $conn->prepare(
                            "SELECT a.id, a.faculty_id, a.section_id,
                                    s.code AS subject_code, s.name AS subject_name,
                                    sec.name AS section_name, f.full_name
                             FROM teaching_assignments a
                             JOIN faculty f ON f.id=a.faculty_id
                             JOIN subjects s ON s.id=a.subject_id
                             JOIN sections sec ON sec.id=a.section_id
                             WHERE a.faculty_id=?
                             ORDER BY s.code, sec.name, a.id"
                        );
                        $assignmentStmt->bind_param("i", $facultyId);
                        $assignmentStmt->execute();
                        $facultyAssignments = $assignmentStmt->get_result()->fetch_all(MYSQLI_ASSOC);

                        if (!$facultyAssignments) {
                            throw new Exception('This faculty member has no subjects assigned in Faculty Workload. Assign their subjects first.');
                        }

                        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                        // Three-hour slots from 7:00 AM through 7:00 PM.
                        $startMinutes = [420, 600, 780, 960];
                        $newEntries = [];

                        $conn->begin_transaction();
                        try {
                            // Lock and read existing schedules so generated times can be checked before any insert.
                            $existingStmt = $conn->prepare(
                                "SELECT cs.assignment_id, cs.classroom_id, cs.day_of_week,
                                        cs.start_time, cs.end_time, a.faculty_id, a.section_id
                                 FROM class_schedules cs
                                 JOIN teaching_assignments a ON a.id=cs.assignment_id
                                 FOR UPDATE"
                            );
                            $existingStmt->execute();
                            $existingSchedules = $existingStmt->get_result()->fetch_all(MYSQLI_ASSOC);

                            $alreadyScheduled = [];
                            $occupied = [];
                            foreach ($existingSchedules as $existing) {
                                $existingAssignmentId = (int)$existing['assignment_id'];
                                $alreadyScheduled[$existingAssignmentId] = true;
                                $occupied[] = [
                                    'assignment_id' => $existingAssignmentId,
                                    'classroom_id' => (int)$existing['classroom_id'],
                                    'day_of_week' => $existing['day_of_week'],
                                    'start_time' => $existing['start_time'],
                                    'end_time' => $existing['end_time'],
                                    'faculty_id' => (int)$existing['faculty_id'],
                                    'section_id' => (int)$existing['section_id']
                                ];
                            }

                            foreach ($facultyAssignments as $assignment) {
                                $assignmentId = (int)$assignment['id'];

                                // An assignment that already has a meeting is considered scheduled.
                                // Do not create a duplicate meeting for it.
                                if (isset($alreadyScheduled[$assignmentId])) {
                                    continue;
                                }

                                $placed = false;
                                foreach ($days as $day) {
                                    foreach ($startMinutes as $startMinute) {
                                        $endMinute = $startMinute + 180;
                                        $startTime = sprintf('%02d:%02d:00', intdiv($startMinute, 60), $startMinute % 60);
                                        $endTime = sprintf('%02d:%02d:00', intdiv($endMinute, 60), $endMinute % 60);
                                        $startCompare = substr($startTime, 0, 5);
                                        $endCompare = substr($endTime, 0, 5);
                                        $conflict = false;

                                        foreach ($occupied as $busy) {
                                            if ($busy['day_of_week'] !== $day) {
                                                continue;
                                            }

                                            $busyStart = substr((string)$busy['start_time'], 0, 5);
                                            $busyEnd = substr((string)$busy['end_time'], 0, 5);
                                            $overlaps = ($startCompare < $busyEnd && $endCompare > $busyStart);

                                            if ($overlaps && (
                                                (int)$busy['faculty_id'] === $facultyId ||
                                                (int)$busy['section_id'] === (int)$assignment['section_id'] ||
                                                (int)$busy['classroom_id'] === $roomId
                                            )) {
                                                $conflict = true;
                                                break;
                                            }
                                        }

                                        if ($conflict) {
                                            continue;
                                        }

                                        $newEntry = [
                                            'assignment_id' => $assignmentId,
                                            'classroom_id' => $roomId,
                                            'day_of_week' => $day,
                                            'start_time' => $startTime,
                                            'end_time' => $endTime,
                                            'faculty_id' => $facultyId,
                                            'section_id' => (int)$assignment['section_id'],
                                            'subject_code' => $assignment['subject_code'],
                                            'subject_name' => $assignment['subject_name'],
                                            'section_name' => $assignment['section_name']
                                        ];

                                        $newEntries[] = $newEntry;
                                        $occupied[] = $newEntry;
                                        $placed = true;
                                        break 2;
                                    }
                                }

                                if (!$placed) {
                                    throw new Exception(
                                        'Unable to schedule ' . $assignment['subject_code'] . ' — ' .
                                        $assignment['subject_name'] . ' for section ' .
                                        $assignment['section_name'] . ' within Monday–Saturday, 7:00 AM–7:00 PM. ' .
                                        'A faculty, section, or classroom conflict prevents a complete timetable. No new schedules were saved.'
                                    );
                                }
                            }

                            if (!$newEntries) {
                                $conn->commit();
                                flash('All assigned subjects for this faculty member already have schedules. No duplicate schedules were added.');
                                break;
                            }

                            $insertStmt = $conn->prepare(
                                "INSERT INTO class_schedules
                                 (assignment_id, classroom_id, day_of_week, start_time, end_time)
                                 VALUES (?, ?, ?, ?, ?)"
                            );

                            foreach ($newEntries as $entry) {
                                $entryAssignmentId = (int)$entry['assignment_id'];
                                $entryRoomId = (int)$entry['classroom_id'];
                                $entryDay = $entry['day_of_week'];
                                $entryStart = $entry['start_time'];
                                $entryEnd = $entry['end_time'];

                                $insertStmt->bind_param(
                                    "iisss",
                                    $entryAssignmentId,
                                    $entryRoomId,
                                    $entryDay,
                                    $entryStart,
                                    $entryEnd
                                );
                                $insertStmt->execute();
                            }

                            $conn->commit();
                        } catch (Throwable $scheduleError) {
                            $conn->rollback();
                            throw $scheduleError;
                        }

                        logAction(
                            $conn,
                            'Generate automatic schedule',
                            $selectedFaculty['full_name'] . ': ' . count($newEntries) .
                            ' three-hour class meeting(s) generated using classroom ' .
                            $selectedRoom['room_number']
                        );
                        flash(
                            'Successfully generated ' . count($newEntries) .
                            ' three-hour class meeting(s) for ' . $selectedFaculty['full_name'] .
                            '. Any assignment that already had a schedule was skipped to prevent duplicates.'
                        );
                        break;

                    case 'delete_schedule':
                        $id = (int)$_POST['id'];
                        $stmt = $conn->prepare("DELETE FROM class_schedules WHERE id=?");
                        $stmt->bind_param("i",$id);
                        $stmt->execute();
                        flash('Schedule deleted.');
                        break;

                    case 'request_decision':
                        $id = (int)($_POST['id'] ?? 0);
                        $status = $_POST['status'] ?? '';
                        $remarks = trim($_POST['remarks'] ?? '');

                        if (!in_array($status,['Approved','Rejected'],true)) {
                            throw new Exception('Invalid request status.');
                        }
                        $stmt = $conn->prepare(
                            "UPDATE schedule_requests SET status=?,remarks=? WHERE id=?"
                        );
                        $stmt->bind_param("ssi",$status,$remarks,$id);
                        $stmt->execute();
                        flash("Request $status.");
                        break;

                    case 'create_user':
                        $username = trim($_POST['username'] ?? '');
                        $password = $_POST['password'] ?? '';
                        $role = $_POST['role'] ?? '';
                        $fid = (int)($_POST['faculty_id'] ?? 0);

                        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/',$username) ||
                            strlen($password) < 8 ||
                            !in_array($role,['admin','faculty'],true)) {
                            throw new Exception('Enter a valid username, password (8+ characters), and role.');
                        }

                        $hash = password_hash($password,PASSWORD_DEFAULT);
                        if ($role === 'faculty' && $fid > 0) {
                            $stmt = $conn->prepare(
                                "INSERT INTO users (username,password,role,faculty_id) VALUES (?,?,?,?)"
                            );
                            $stmt->bind_param("sssi",$username,$hash,$role,$fid);
                        } else {
                            $stmt = $conn->prepare(
                                "INSERT INTO users (username,password,role) VALUES (?,?,?)"
                            );
                            $stmt->bind_param("sss",$username,$hash,$role);
                        }
                        $stmt->execute();
                        flash('User account created.');
                        break;

                    case 'update_user':
                        $id = (int)($_POST['id'] ?? 0);
                        $username = trim($_POST['username'] ?? '');
                        $newPassword = $_POST['password'] ?? '';
                        $role = $_POST['role'] ?? '';
                        $facultyId = (int)($_POST['faculty_id'] ?? 0);

                        if ($id < 1) {
                            throw new Exception('Invalid user account selected.');
                        }
                        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
                            throw new Exception('Username must be 3–50 characters and contain only letters, numbers, dots, underscores, or hyphens.');
                        }
                        if (!in_array($role, ['admin', 'faculty'], true)) {
                            throw new Exception('Select a valid account role.');
                        }
                        if ($newPassword !== '' && strlen($newPassword) < 8) {
                            throw new Exception('A new password must contain at least 8 characters. Leave it blank to keep the current password.');
                        }
                        if ($facultyId < 0) {
                            throw new Exception('Select a valid linked faculty member.');
                        }

                        $accountStmt = $conn->prepare("SELECT id FROM users WHERE id=? LIMIT 1");
                        $accountStmt->bind_param("i", $id);
                        $accountStmt->execute();
                        if (!$accountStmt->get_result()->fetch_assoc()) {
                            throw new Exception('The selected user account no longer exists.');
                        }

                        $duplicateStmt = $conn->prepare(
                            "SELECT id FROM users WHERE username=? AND id<>? LIMIT 1"
                        );
                        $duplicateStmt->bind_param("si", $username, $id);
                        $duplicateStmt->execute();
                        if ($duplicateStmt->get_result()->fetch_assoc()) {
                            throw new Exception('That username is already in use. Please choose another username.');
                        }

                        if ($facultyId > 0) {
                            $facultyStmt = $conn->prepare("SELECT id FROM faculty WHERE id=? LIMIT 1");
                            $facultyStmt->bind_param("i", $facultyId);
                            $facultyStmt->execute();
                            if (!$facultyStmt->get_result()->fetch_assoc()) {
                                throw new Exception('The selected faculty member does not exist.');
                            }
                        }

                        if ($newPassword !== '') {
                            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                            $updateStmt = $conn->prepare(
                                "UPDATE users SET username=?, password=?, role=?, faculty_id=NULLIF(?,0) WHERE id=?"
                            );
                            $updateStmt->bind_param(
                                "sssii", $username, $passwordHash, $role, $facultyId, $id
                            );
                        } else {
                            $updateStmt = $conn->prepare(
                                "UPDATE users SET username=?, role=?, faculty_id=NULLIF(?,0) WHERE id=?"
                            );
                            $updateStmt->bind_param("ssii", $username, $role, $facultyId, $id);
                        }

                        $updateStmt->execute();
                        logAction($conn, 'Update user account', 'User ID: ' . $id . ', username: ' . $username);
                        flash('User account updated successfully.');
                        break;

                    case 'delete_user':
                        $id = (int)($_POST['id'] ?? 0);
                        $currentUserId = (int)($_SESSION['user']['id'] ?? 0);

                        if ($id < 1) {
                            throw new Exception('Invalid user account selected.');
                        }
                        if ($id === $currentUserId) {
                            throw new Exception('You cannot delete the account you are currently logged in with.');
                        }

                        $accountStmt = $conn->prepare("SELECT id, username FROM users WHERE id=? LIMIT 1");
                        $accountStmt->bind_param("i", $id);
                        $accountStmt->execute();
                        $accountToDelete = $accountStmt->get_result()->fetch_assoc();
                        if (!$accountToDelete) {
                            throw new Exception('The selected user account no longer exists.');
                        }

                        $deleteStmt = $conn->prepare("DELETE FROM users WHERE id=?");
                        $deleteStmt->bind_param("i", $id);
                        try {
                            $deleteStmt->execute();
                        } catch (mysqli_sql_exception $deleteError) {
                            if ((int)$deleteError->getCode() === 1451) {
                                throw new Exception('This account cannot be deleted because it is referenced by existing records. No related faculty or schedule records were deleted.');
                            }
                            throw $deleteError;
                        }

                        if ($deleteStmt->affected_rows !== 1) {
                            throw new Exception('The user account was not deleted. Please refresh the page and try again.');
                        }
                        logAction($conn, 'Delete user account', 'Deleted username: ' . $accountToDelete['username']);
                        flash('User account deleted successfully.');
                        break;

                    default:
                        throw new Exception('Unknown action.');
                }
            } elseif ($role === 'faculty' && $action === 'submit_request') {
                $fid = (int)($_SESSION['user']['faculty_id'] ?? 0);
                $schedule = (int)($_POST['schedule_id'] ?? 0);
                $day = $_POST['requested_day'] ?? '';
                $start = $_POST['requested_start'] ?? '';
                $end = $_POST['requested_end'] ?? '';
                $reason = trim($_POST['reason'] ?? '');

                $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                if (!$fid || !in_array($day,$days,true) ||
                    !preg_match('/^\d{2}:\d{2}$/',$start) ||
                    !preg_match('/^\d{2}:\d{2}$/',$end) ||
                    $start >= $end || !$reason) {
                    throw new Exception('Complete the request form correctly.');
                }

                if ($schedule) {
                    $stmt = $conn->prepare(
                        "SELECT cs.id FROM class_schedules cs
                         JOIN teaching_assignments a ON a.id=cs.assignment_id
                         WHERE cs.id=? AND a.faculty_id=?"
                    );
                    $stmt->bind_param("ii",$schedule,$fid);
                    $stmt->execute();
                    if (!$stmt->get_result()->num_rows) {
                        throw new Exception('That schedule does not belong to you.');
                    }
                }

                $stmt = $conn->prepare(
                    "INSERT INTO schedule_requests
                    (faculty_id,schedule_id,requested_day,requested_start,
                     requested_end,reason) VALUES (?,NULLIF(?,0),?,?,?,?)"
                );
                $stmt->bind_param("iissss",$fid,$schedule,$day,$start,$end,$reason);
                $stmt->execute();
                flash('Schedule-change request submitted.');
            } else {
                throw new Exception('You do not have permission to perform this action.');
            }
        } catch (Throwable $ex) {
            flash($ex instanceof mysqli_sql_exception
                ? 'Unable to save. Check for duplicate values or records in use.'
                : $ex->getMessage(), 'error');
        }

        header('Location: index.php?page=' . urlencode($_POST['return_page'] ?? 'dashboard'));
        exit;
    }
}

if ($page === 'logout') {
    if (!empty($_SESSION['user'])) {
        $_SESSION = [];
        session_destroy();
    }
    header('Location: index.php?page=login');
    exit;
}

if (empty($_SESSION['user'])) $page = 'login';
$user = $_SESSION['user'] ?? null;

if ($user && $user['role'] === 'faculty' &&
    in_array($page,['faculty','departments','subjects','sections','rooms',
    'workload','scheduling','users','reports','requests'],true)) {
    $page = 'dashboard';
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function rows($conn, $sql) {
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

function countRows($conn, $table) {
    $allowed = ['faculty','subjects','sections','class_schedules',
        'teaching_assignments','schedule_requests','departments','classrooms'];
    if (!in_array($table,$allowed,true)) return 0;
    return (int)$conn->query("SELECT COUNT(*) n FROM `$table`")->fetch_assoc()['n'];
}

function postForm($action, $page, $fields = '') {
    echo '<form method="post" class="inline-form" onsubmit="return confirmAction(this)">';
    echo '<input type="hidden" name="csrf" value="'.e(csrfToken()).'">';
    echo '<input type="hidden" name="action" value="'.e($action).'">';
    echo '<input type="hidden" name="return_page" value="'.e($page).'">';
    echo $fields;
    echo '</form>';
}

$navAdmin = [
    'dashboard'=>'Dashboard','faculty'=>'Faculty Management',
    'departments'=>'Departments','subjects'=>'Subjects','sections'=>'Sections',
    'rooms'=>'Classrooms','workload'=>'Faculty Workload',
    'scheduling'=>'Academic Scheduling','requests'=>'Schedule Change Requests',
    'reports'=>'Reports','users'=>'User Accounts'
];
$navFaculty = [
    'dashboard'=>'Dashboard','profile'=>'My Profile','workload'=>'My Workload',
    'schedule'=>'My Schedule','myrequests'=>'Schedule Change Requests'
];

if ($page !== 'login') requireLogin();
$nav = ($user['role'] ?? '') === 'admin' ? $navAdmin : $navFaculty;
if (!isset($nav[$page]) && $page !== 'login') $page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>LSC Faculty Workload & Scheduling</title>
<link rel="stylesheet" href="style.css">
<script defer src="script.js"></script>
</head>
<body>
<?php if ($page === 'login'): ?>
<div class="login-layout">
    <div class="login-art">
        <div class="brand-mark">LSC</div>
        <h1>Academic Management System</h1>
        <p>Faculty Workload & Academic Scheduling</p>
        <div class="art-shapes">✦</div>
    </div>
    <div class="login-panel">
        <div class="brand-mobile">LSC · Faculty Portal</div>
        <span class="eyebrow">WELCOME BACK</span>
        <h2>Sign in to your account</h2>
        <p class="muted">Enter your credentials to continue.</p>
        <?php if ($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?>
        <form method="post" class="stack-form">
            <input type="hidden" name="csrf" value="<?=e(csrfToken())?>">
            <input type="hidden" name="action" value="login">
            <label>Username<input name="username" required autocomplete="username"></label>
            <label>Password><div class="password-wrap">
                <input type="password" id="password" name="password" required autocomplete="current-password">
                <button type="button" class="show-password" onclick="togglePassword()">Show</button>
            </div></label>
            <button class="btn primary full" type="submit">Sign In <span>→</span></button>
        </form>
        <p class="login-foot">Lyceum of Southern Luzon College</p>
    </div>
</div>
<?php else: ?>
<div class="app-shell">
<aside class="sidebar" id="sidebar">
    <div class="brand">
        <div class="brand-mark small">LSC</div>
        <div><strong>LSC Academic</strong><small>Workload & Scheduling</small></div>
    </div>
    <div class="nav-label">MAIN MENU</div>
    <nav>
        <?php foreach ($nav as $key=>$label): ?>
        <a class="nav-link <?= $page===$key?'active':'' ?>"
           href="index.php?page=<?=e($key)?>">
            <span class="nav-icon"><?=e([
                'dashboard'=>'▦','faculty'=>'♙','departments'=>'▤','subjects'=>'▣',
                'sections'=>'▥','rooms'=>'⌂','workload'=>'☷','scheduling'=>'▦',
                'requests'=>'◷','reports'=>'▥','users'=>'♙','profile'=>'♙',
                'schedule'=>'▦','myrequests'=>'◷'
            ][$key] ?? '•')?></span><?=e($label)?>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-bottom">
        <div class="user-mini"><div class="avatar"><?=e(strtoupper(substr($user['name'] ?? 'U',0,1)))?></div>
            <div><strong><?=e($user['name'] ?? 'User')?></strong>
                <small><?=e(ucfirst($user['role']))?></small></div>
        </div>

        <!-- UPDATED LOGOUT FORM: only this logout block was changed -->
        <form method="post" action="index.php" class="inline-form" style="margin:0;">
            <input type="hidden" name="csrf" value="<?=e(csrfToken())?>">
            <input type="hidden" name="action" value="logout">
            <input type="hidden" name="return_page" value="login">
            <button class="nav-link logout" type="submit" style="width:100%;cursor:pointer;">↪ &nbsp; Log out</button>
        </form>
    </div>
</aside>

<main class="main-area">
<header class="topbar">
    <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Toggle navigation">☰</button>
    <div class="breadcrumbs">LSC <span>/</span> <?=e($nav[$page] ?? 'Dashboard')?></div>
    <div class="topbar-right"><span class="term-chip">AY 2026–2027</span>
        <div class="avatar"><?=e(strtoupper(substr($user['name'] ?? 'U',0,1)))?></div>
    </div>
</header>
<div class="content">
<?php if ($flash): ?>
<div class="alert <?=e($flash['type'])?>"><?=e($flash['message'])?></div>
<?php endif; ?>

<?php
switch ($page) {
case 'dashboard':
    if ($user['role'] === 'admin') {
        $stats = [
            ['Total Faculty',countRows($conn,'faculty'),'♙'],
            ['Total Subjects',countRows($conn,'subjects'),'▣'],
            ['Total Sections',countRows($conn,'sections'),'▤'],
            ['Class Schedules',countRows($conn,'class_schedules'),'▦'],
            ['Pending Workloads',(int)$conn->query("SELECT COUNT(*) n FROM teaching_assignments WHERE status='Pending'")->fetch_assoc()['n'],'◷'],
            ['Pending Requests',(int)$conn->query("SELECT COUNT(*) n FROM schedule_requests WHERE status='Pending'")->fetch_assoc()['n'],'!']
        ];
    } else {
        $fid = (int)($user['faculty_id'] ?? 0);
        $stmt = $conn->prepare("SELECT COUNT(*) n FROM teaching_assignments WHERE faculty_id=?");
        $stmt->bind_param("i",$fid); $stmt->execute();
        $assigned = (int)$stmt->get_result()->fetch_assoc()['n'];
        $stmt = $conn->prepare(
            "SELECT COALESCE(SUM(s.lecture_hours+s.lab_hours),0) total
             FROM teaching_assignments a JOIN subjects s ON s.id=a.subject_id
             WHERE a.faculty_id=?"
        );
        $stmt->bind_param("i",$fid); $stmt->execute();
        $hours = $stmt->get_result()->fetch_assoc()['total'];
        $stmt = $conn->prepare("SELECT COUNT(*) n FROM schedule_requests WHERE faculty_id=? AND status='Pending'");
        $stmt->bind_param("i",$fid); $stmt->execute();
        $pending = $stmt->get_result()->fetch_assoc()['n'];
        $stats = [
            ['My Teaching Hours',$hours,'◷'],
            ['My Subjects',$assigned,'▣'],
            ['My Sections',$assigned,'▤'],
            ['Pending Requests',$pending,'◷']
        ];
    }
?>
<div class="page-heading"><div><span class="eyebrow">OVERVIEW</span>
<h1><?= $user['role']==='admin'?'Dashboard':'My Dashboard' ?></h1>
<p>Here is your academic workload overview.</p></div>
<?php if($user['role']==='admin'): ?>
<a class="btn primary" href="index.php?page=scheduling">＋ Create Schedule</a>
<?php endif; ?></div>
<div class="stats-grid">
<?php foreach($stats as $i=>$s): ?>
<div class="stat-card"><div class="stat-top"><span><?=e($s[0])?></span>
<div class="stat-icon icon-<?=$i%4?>"><?=e($s[2])?></div></div>
<strong class="stat-value"><?=e($s[1])?></strong>
<small><?= $i===0 && $user['role']==='admin'?'Registered faculty':'Current records' ?></small></div>
<?php endforeach; ?>
</div>
<div class="two-column">
<div class="panel">
<div class="panel-head"><div><h3>Upcoming Class Schedule</h3>
<p>Scheduled classes for the current term</p></div>
<a href="index.php?page=<?=$user['role']==='admin'?'scheduling':'schedule'?>" class="text-link">View all →</a></div>
<?php
$sql = "SELECT cs.*,s.code,s.name subject_name,f.full_name,c.room_number,a.section_id
FROM class_schedules cs JOIN teaching_assignments a ON a.id=cs.assignment_id
JOIN subjects s ON s.id=a.subject_id JOIN faculty f ON f.id=a.faculty_id
JOIN classrooms c ON c.id=cs.classroom_id";
if ($user['role'] === 'faculty') $sql .= " WHERE a.faculty_id=".(int)$user['faculty_id'];
$sql .= " ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),start_time LIMIT 6";
$schedules = rows($conn,$sql);
if (!$schedules) echo '<div class="empty-state">No schedules have been created yet.</div>';
else foreach($schedules as $s): ?>
<div class="schedule-row"><div class="day-box"><?=e(substr($s['day_of_week'],0,3))?>
<strong><?=e(date('g:i',strtotime($s['start_time'])))?></strong></div>
<div class="schedule-info"><strong><?=e($s['code'])?> · <?=e($s['subject_name'])?></strong>
<small><?=e($s['full_name'])?> · <?=e($s['room_number'])?></small></div>
<span class="status approved"><?=e(date('g:i A',strtotime($s['start_time'])))?></span></div>
<?php endforeach; ?>
</div>
<div class="panel">
<div class="panel-head"><div><h3><?= $user['role']==='admin'?'Recent Activity':'My Workload' ?></h3>
<p><?= $user['role']==='admin'?'Latest system records':'Assigned teaching subjects' ?></p></div></div>
<?php
if ($user['role']==='admin') {
    $activity = rows($conn,"SELECT l.*,u.username FROM audit_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.id DESC LIMIT 6");
    if (!$activity) echo '<div class="empty-state">No recent activity.</div>';
    foreach($activity as $a) echo '<div class="activity-row"><span class="activity-dot"></span><div><strong>'.e($a['action']).'</strong><small>'.e($a['username'] ?? 'System').' · '.e($a['details']).'</small></div></div>';
} else {
    $fid = (int)$user['faculty_id'];
    $stmt=$conn->prepare("SELECT s.code,s.name,s.lecture_hours,s.lab_hours,sec.name section_name
      FROM teaching_assignments a JOIN subjects s ON s.id=a.subject_id
      JOIN sections sec ON sec.id=a.section_id WHERE a.faculty_id=?");
    $stmt->bind_param("i",$fid);$stmt->execute();
    foreach($stmt->get_result() as $a) echo '<div class="activity-row"><span class="activity-dot"></span><div><strong>'.e($a['code']).' · '.e($a['name']).'</strong><small>'.e($a['section_name']).' · '.e($a['lecture_hours']+$a['lab_hours']).' hours/week</small></div></div>';
}
?>
</div></div>
<?php
break;

case 'faculty':
    $search = trim($_GET['q'] ?? '');
    $stmt = $conn->prepare(
        "SELECT f.*,d.name department,
         COALESCE((SELECT SUM(s.lecture_hours+s.lab_hours)
          FROM teaching_assignments a JOIN subjects s ON s.id=a.subject_id
          WHERE a.faculty_id=f.id),0) hours
         FROM faculty f LEFT JOIN departments d ON d.id=f.department_id
         WHERE f.full_name LIKE CONCAT('%',?,'%') OR f.faculty_id LIKE CONCAT('%',?,'%')
         ORDER BY f.full_name"
    );
    $stmt->bind_param("ss",$search,$search);$stmt->execute();
    $list=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $departments=rows($conn,"SELECT * FROM departments ORDER BY name");
?>
<div class="page-heading"><div><span class="eyebrow">PEOPLE</span><h1>Faculty Management</h1>
<p>Manage faculty records and employment information.</p></div>
<button class="btn primary" onclick="openModal('faculty-modal')">＋ Add Faculty</button></div>
<div class="panel">
<form class="toolbar" method="get"><input type="hidden" name="page" value="faculty">
<input name="q" value="<?=e($search)?>" placeholder="Search faculty name or ID...">
<button class="btn secondary">Search</button></form>
<div class="table-wrap"><table><thead><tr><th>Faculty ID</th><th>Full Name</th><th>Department</th><th>Employment</th><th>Hours/Week</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($list as $r): ?>
<tr><td><?=e($r['faculty_id'])?></td><td><strong><?=e($r['full_name'])?></strong><small><?=e($r['email'])?></small></td><td><?=e($r['department'] ?? '—')?></td><td><?=e($r['employment_type'])?></td><td><?=e($r['hours'])?></td><td><span class="status <?=strtolower(e($r['status']))?>"><?=e($r['status'])?></span></td>
<td class="actions"><button class="btn tiny secondary" onclick='editFaculty(<?=json_encode($r,JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP)?>)'>Edit</button>
<?php postForm('delete_faculty','faculty','<input type="hidden" name="id" value="'.(int)$r['id'].'"><button class="btn tiny danger">Delete</button>'); ?></td></tr>
<?php endforeach; ?>
<?php if(!$list) echo '<tr><td colspan="7" class="empty-cell">No faculty records found.</td></tr>'; ?>
</tbody></table></div></div>
<div class="modal-backdrop" id="faculty-modal"><div class="modal">
<div class="modal-head"><h2 id="faculty-modal-title">Add Faculty</h2><button class="close-btn" onclick="closeModal('faculty-modal')">×</button></div>
<form method="post" class="stack-form">
<input type="hidden" name="csrf" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="save_faculty"><input type="hidden" name="return_page" value="faculty"><input type="hidden" name="id" id="f-id">
<div class="form-grid"><label>Faculty ID<input name="faculty_id" id="f-faculty_id" required></label>
<label>Full Name<input name="full_name" id="f-full_name" required></label>
<label>Email<input type="email" name="email" id="f-email"></label>
<label>Department<select name="department_id" id="f-department_id"><option value="0">Unassigned</option><?php foreach($departments as $d): ?><option value="<?=$d['id']?>"><?=e($d['name'])?></option><?php endforeach; ?></select></label>
<label>Employment Type<select name="employment_type" id="f-employment_type"><option>Full-time</option><option>Part-time</option></select></label>
<label>Specialization<input name="specialization" id="f-specialization"></label>
<label>Status<select name="status" id="f-status"><option>Active</option><option>Inactive</option></select></label></div>
<div class="modal-actions"><button type="button" class="btn secondary" onclick="closeModal('faculty-modal')">Cancel</button><button class="btn primary">Save Faculty</button></div>
</form></div></div>
<?php
break;

case 'departments':
    $list=rows($conn,"SELECT d.*,(SELECT COUNT(*) FROM faculty f WHERE f.department_id=d.id) faculty_count FROM departments d ORDER BY d.name");
?>
<div class="page-heading"><div><span class="eyebrow">ACADEMIC SETUP</span><h1>Departments</h1><p>Manage academic departments.</p></div></div>
<div class="panel"><h3>Add Department</h3><form method="post" class="inline-create">
<input type="hidden" name="csrf" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="save_department"><input type="hidden" name="return_page" value="departments">
<input name="code" placeholder="Department code" required><input name="name" placeholder="Department name" required><input name="department_head" placeholder="Department head"><button class="btn primary">Add Department</button></form>
<div class="table-wrap"><table><thead><tr><th>Code</th><th>Department</th><th>Department Head</th><th>Faculty Count</th></tr></thead><tbody><?php foreach($list as $r): ?><tr><td><?=e($r['code'])?></td><td><?=e($r['name'])?></td><td><?=e($r['department_head'])?></td><td><?=e($r['faculty_count'])?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php
break;

case 'subjects':
    $list=rows($conn,"SELECT s.*,d.name department FROM subjects s LEFT JOIN departments d ON d.id=s.department_id ORDER BY s.code");
    $departments=rows($conn,"SELECT * FROM departments ORDER BY name");
?>
<div class="page-heading"><div><span class="eyebrow">ACADEMIC SETUP</span><h1>Subjects</h1><p>Manage subject codes, units, and contact hours.</p></div></div>
<div class="panel"><h3>Add Subject</h3><form method="post" class="inline-create">
<input type="hidden" name="csrf" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="save_subject"><input type="hidden" name="return_page" value="subjects">
<input name="code" placeholder="Subject code" required><input name="name" placeholder="Subject name" required>
<input type="number" step=".5" min="0" name="units" value="3" placeholder="Units" required>
<input type="number" step=".5" min="0" name="lecture_hours" value="3" placeholder="Lecture hours" required>
<input type="number" step=".5" min="0" name="lab_hours" value="0" placeholder="Lab hours" required>
<select name="department_id"><option value="0">Department</option><?php foreach($departments as $d): ?><option value="<?=$d['id']?>"><?=e($d['name'])?></option><?php endforeach; ?></select>
<button class="btn primary">Add Subject</button></form>
<div class="table-wrap"><table><thead><tr><th>Code</th><th>Subject</th><th>Units</th><th>Lecture</th><th>Lab</th><th>Department</th></tr></thead><tbody><?php foreach($list as $r): ?><tr><td><?=e($r['code'])?></td><td><?=e($r['name'])?></td><td><?=e($r['units'])?></td><td><?=e($r['lecture_hours'])?></td><td><?=e($r['lab_hours'])?></td><td><?=e($r['department']??'—')?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php
break;

case 'sections':
    $list=rows($conn,"SELECT * FROM sections ORDER BY academic_year,semester,name");
?>
<div class="page-heading"><div><span class="eyebrow">ACADEMIC SETUP</span><h1>Sections</h1><p>Manage programs and class sections.</p></div></div>
<div class="panel"><h3>Add Section</h3><form method="post" class="inline-create">
<input type="hidden" name="csrf" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="save_section"><input type="hidden" name="return_page" value="sections">
<input name="name" placeholder="Section name" required><input name="program" placeholder="Program" required><input name="year_level" placeholder="Year level" required><input name="academic_year" value="2026-2027" required><select name="semester"><option>1st Semester</option><option>2nd Semester</option><option>Summer</option></select><button class="btn primary">Add Section</button></form>
<div class="table-wrap"><table><thead><tr><th>Section</th><th>Program</th><th>Year Level</th><th>Academic Year</th><th>Semester</th></tr></thead><tbody><?php foreach($list as $r): ?><tr><td><?=e($r['name'])?></td><td><?=e($r['program'])?></td><td><?=e($r['year_level'])?></td><td><?=e($r['academic_year'])?></td><td><?=e($r['semester'])?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php
break;

case 'rooms':
    $list=rows($conn,"SELECT * FROM classrooms ORDER BY room_number");
?>
<div class="page-heading"><div><span class="eyebrow">RESOURCES</span><h1>Classrooms</h1><p>Manage room capacity and availability.</p></div></div>
<div class="panel"><h3>Add Classroom</h3><form method="post" class="inline-create">
<input type="hidden" name="csrf" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="save_room"><input type="hidden" name="return_page" value="rooms">
<input name="room_number" placeholder="Room number" required><input name="building" placeholder="Building">
<select name="room_type"><option>Lecture</option><option>Laboratory</option><option>Other</option></select>
<input type="number" name="capacity" min="1" value="40" required><input name="equipment" placeholder="Equipment">
<select name="status"><option>Available</option><option>Unavailable</option></select><button class="btn primary">Add Room</button></form>
<div class="table-wrap"><table><thead><tr><th>Room</th><th>Building</th><th>Type</th><th>Capacity</th><th>Equipment</th><th>Status</th></tr></thead><tbody><?php foreach($list as $r): ?><tr><td><?=e($r['room_number'])?></td><td><?=e($r['building'])?></td><td><?=e($r['room_type'])?></td><td><?=e($r['capacity'])?></td><td><?=e($r['equipment'])?></td><td><span class="status"><?=e($r['status'])?></span></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php
break;

case 'workload':
    $faculties=rows($conn,"SELECT id,faculty_id,full_name FROM faculty WHERE status='Active' ORDER BY full_name");
    $subjects=rows($conn,"SELECT * FROM subjects ORDER BY code");
    $sections=rows($conn,"SELECT * FROM sections ORDER BY name");
    $sql="SELECT a.*,f.full_name,f.faculty_id,s.code,s.name subject_name,
      s.lecture_hours,s.lab_hours,sec.name section_name
      FROM teaching_assignments a JOIN faculty f ON f.id=a.faculty_id
      JOIN subjects s ON s.id=a.subject_id JOIN sections sec ON sec.id=a.section_id";
    if ($user['role']==='faculty') $sql.=" WHERE a.faculty_id=".(int)$user['faculty_id'];
    $sql.=" ORDER BY f.full_name,s.code";
    $list=rows($conn,$sql);
?>
<div class="page-heading"><div><span class="eyebrow">FACULTY ASSIGNMENTS</span><h1>Faculty Workload</h1><p>Assign subjects and track weekly teaching hours.</p></div></div>
<?php if($user['role']==='admin'): ?><div class="panel"><h3>Assign Teaching Load</h3>
<form method="post" class="inline-create"><input type="hidden" name="csrf" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="assign_workload"><input type="hidden" name="return_page" value="workload">
<select name="faculty_id" required><option value="">Select faculty</option><?php foreach($faculties as $f): ?><option value="<?=$f['id']?>"><?=e($f['faculty_id'].' — '.$f['full_name'])?></option><?php endforeach; ?></select>
<select name="subject_id" required><option value="">Select subject</option><?php foreach($subjects as $s): ?><option value="<?=$s['id']?>"><?=e($s['code'].' — '.$s['name'])?></option><?php endforeach; ?></select>
<select name="section_id" required><option value="">Select section</option><?php foreach($sections as $s): ?><option value="<?=$s['id']?>"><?=e($s['name'])?></option><?php endforeach; ?></select>
<input name="academic_year" value="2026-2027" required><select name="semester"><option>1st Semester</option><option>2nd Semester</option><option>Summer</option></select><button class="btn primary">Assign Workload</button></form></div><?php endif; ?>
<div class="panel"><div class="panel-head"><div><h3>Workload Assignments</h3><p>Weekly hours are calculated from subject contact hours.</p></div><button class="btn secondary" onclick="window.print()">Print Report</button></div>
<div class="table-wrap"><table><thead><tr><th>Faculty</th><th>Subject</th><th>Section</th><th>Lecture</th><th>Lab</th><th>Total Hours</th><th>Status</th></tr></thead><tbody>
<?php $total=0; foreach($list as $r): $h=(float)$r['lecture_hours']+(float)$r['lab_hours']; $total+=$h; ?>
<tr><td><?=e($r['full_name'])?></td><td><?=e($r['code'].' — '.$r['subject_name'])?></td><td><?=e($r['section_name'])?></td><td><?=e($r['lecture_hours'])?></td><td><?=e($r['lab_hours'])?></td><td><strong><?=e($h)?></strong></td><td><span class="status <?=strtolower(e($r['status']))?>"><?=e($r['status'])?></span></td></tr>
<?php endforeach; if(!$list) echo '<tr><td colspan="7" class="empty-cell">No workload assignments yet.</td></tr>'; ?>
</tbody></table></div><div class="report-total">Displayed assignment hours: <strong><?=e($total)?> hours</strong></div></div>
<?php
break;

case 'scheduling':
case 'schedule':
    $isFaculty = $user['role'] === 'faculty';
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    $assignSql = "SELECT a.id, a.faculty_id, f.full_name, f.faculty_id AS faculty_code,
                         s.code, s.name AS subject_name, sec.name AS section_name,
                         a.academic_year, a.semester
                  FROM teaching_assignments a
                  JOIN faculty f ON f.id=a.faculty_id
                  JOIN subjects s ON s.id=a.subject_id
                  JOIN sections sec ON sec.id=a.section_id";
    if ($isFaculty) {
        $assignSql .= " WHERE a.faculty_id=" . (int)$user['faculty_id'];
    }
    $allAssignments = rows($conn, $assignSql . " ORDER BY f.full_name, s.code, sec.name");

    $faculties = $isFaculty ? [] : rows(
        $conn,
        "SELECT id, faculty_id, full_name
         FROM faculty
         WHERE status='Active'
         ORDER BY full_name"
    );
    $rooms = $isFaculty ? [] : rows(
        $conn,
        "SELECT id, room_number, room_type
         FROM classrooms
         WHERE status='Available'
         ORDER BY room_number"
    );

    $scheduledIds = rows($conn, "SELECT DISTINCT assignment_id FROM class_schedules");
    $scheduledAssignmentLookup = [];
    foreach ($scheduledIds as $scheduledId) {
        $scheduledAssignmentLookup[(int)$scheduledId['assignment_id']] = true;
    }

    $sql = "SELECT cs.*, s.code, s.name AS subject_name,
                   f.full_name, sec.name AS section_name, c.room_number,
                   a.faculty_id, a.section_id
            FROM class_schedules cs
            JOIN teaching_assignments a ON a.id=cs.assignment_id
            JOIN subjects s ON s.id=a.subject_id
            JOIN faculty f ON f.id=a.faculty_id
            JOIN sections sec ON sec.id=a.section_id
            JOIN classrooms c ON c.id=cs.classroom_id";
    if ($isFaculty) {
        $sql .= " WHERE a.faculty_id=" . (int)$user['faculty_id'];
    }
    $sql .= " ORDER BY FIELD(cs.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),
                       cs.start_time, f.full_name, s.code";
    $list = rows($conn, $sql);
?>
<div class="page-heading">
    <div>
        <span class="eyebrow">TIMETABLE</span>
        <h1><?=$isFaculty ? 'My Schedule' : 'Academic Scheduling'?></h1>
        <p><?=$isFaculty
            ? 'View your weekly teaching schedule.'
            : 'Select a faculty member and available classroom to automatically schedule assigned subjects.'?></p>
    </div>
    <button class="btn secondary" onclick="window.print()">Print Timetable</button>
</div>

<?php if (!$isFaculty): ?>
<div class="panel">
    <h3>Generate Automatic Schedule</h3>
    <p>Each assigned subject is scheduled once for a three-hour meeting. The system chooses the day and time between 7:00 AM and 7:00 PM, Monday through Saturday. Existing assignments that already have a schedule will not be duplicated.</p>

    <form method="post" class="stack-form" id="auto-schedule-form">
        <input type="hidden" name="csrf" value="<?=e(csrfToken())?>">
        <input type="hidden" name="action" value="generate_auto_schedule">
        <input type="hidden" name="return_page" value="scheduling">

        <div class="form-grid">
            <label for="auto-schedule-faculty">Faculty Name
                <select name="faculty_id" id="auto-schedule-faculty" required>
                    <option value="">Select faculty member</option>
                    <?php foreach ($faculties as $faculty): ?>
                    <option value="<?=$faculty['id']?>">
                        <?=e($faculty['full_name'] . ' (' . $faculty['faculty_id'] . ')')?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label for="auto-schedule-room">Available Classroom
                <select name="classroom_id" id="auto-schedule-room" required>
                    <option value="">Select available classroom</option>
                    <?php foreach ($rooms as $room): ?>
                    <option value="<?=$room['id']?>">
                        <?=e($room['room_number'] . ' (' . $room['room_type'] . ')')?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="panel" style="margin-top:8px;">
            <div class="panel-head">
                <div>
                    <h3>Assigned Subjects</h3>
                    <p>These assignments come directly from Faculty Workload.</p>
                </div>
                <span class="term-chip" id="auto-assignment-count">Select faculty</span>
            </div>

            <div class="table-wrap">
                <table class="timetable">
                    <thead>
                    <tr>
                        <th>Subject Code</th>
                        <th>Subject Name</th>
                        <th>Section</th>
                        <th>Academic Year</th>
                        <th>Semester</th>
                        <th>Scheduling Status</th>
                    </tr>
                    </thead>
                    <tbody id="auto-assignment-preview">
                    <?php foreach ($allAssignments as $assignment): ?>
                    <tr class="auto-assignment-row"
                        data-faculty-id="<?=$assignment['faculty_id']?>"
                        data-assignment-id="<?=$assignment['id']?>"
                        hidden>
                        <td><?=e($assignment['code'])?></td>
                        <td><?=e($assignment['subject_name'])?></td>
                        <td><?=e($assignment['section_name'])?></td>
                        <td><?=e($assignment['academic_year'] ?? '—')?></td>
                        <td><?=e($assignment['semester'] ?? '—')?></td>
                        <td>
                            <?php if (isset($scheduledAssignmentLookup[(int)$assignment['id']])): ?>
                                <span class="status approved">Already scheduled</span>
                            <?php else: ?>
                                <span class="status pending">Needs schedule</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr id="auto-assignment-message-row">
                        <td colspan="6" class="empty-cell">Select a faculty member to display their assigned subjects.</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <button class="btn primary" type="submit">Generate Schedule</button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const facultySelect = document.getElementById('auto-schedule-faculty');
    const rows = Array.from(document.querySelectorAll('.auto-assignment-row'));
    const messageRow = document.getElementById('auto-assignment-message-row');
    const countLabel = document.getElementById('auto-assignment-count');

    function updateAssignedSubjects() {
        const facultyId = facultySelect.value;
        let visibleCount = 0;

        rows.forEach(function (row) {
            const matches = facultyId !== '' && row.dataset.facultyId === facultyId;
            row.hidden = !matches;
            if (matches) visibleCount++;
        });

        messageRow.hidden = visibleCount > 0;
        if (visibleCount === 0) {
            messageRow.querySelector('td').textContent = facultyId
                ? 'This faculty member has no subjects assigned in Faculty Workload.'
                : 'Select a faculty member to display their assigned subjects.';
        }

        countLabel.textContent = facultyId
            ? visibleCount + (visibleCount === 1 ? ' subject assignment' : ' subject assignments')
            : 'Select faculty';
    }

    facultySelect.addEventListener('change', updateAssignedSubjects);
    updateAssignedSubjects();
});
</script>
<?php endif; ?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h3>Weekly Timetable</h3>
            <p>All scheduled class meetings, organized by day and time.</p>
        </div>
        <span class="term-chip">Weekly View</span>
    </div>

    <div class="table-wrap">
        <table class="timetable">
            <thead>
            <tr>
                <th>Day</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Faculty Name</th>
                <th>Subject</th>
                <th>Section</th>
                <th>Classroom</th>
                <th>Total Hours</th>
                <?php if (!$isFaculty): ?><th>Action</th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($list as $schedule): ?>
            <tr>
                <td><strong><?=e($schedule['day_of_week'])?></strong></td>
                <td><?=e(date('g:i A', strtotime($schedule['start_time'])))?></td>
                <td><?=e(date('g:i A', strtotime($schedule['end_time'])))?></td>
                <td><?=e($schedule['full_name'])?></td>
                <td>
                    <strong><?=e($schedule['code'])?></strong>
                    <small><?=e($schedule['subject_name'])?></small>
                </td>
                <td><?=e($schedule['section_name'])?></td>
                <td><?=e($schedule['room_number'])?></td>
                <td><?=e(number_format((strtotime($schedule['end_time']) - strtotime($schedule['start_time'])) / 3600, 1))?> hours</td>
                <?php if (!$isFaculty): ?>
                <td>
                    <?php
                    postForm(
                        'delete_schedule',
                        'scheduling',
                        '<input type="hidden" name="id" value="' . (int)$schedule['id'] .
                        '"><button class="btn tiny danger">Delete</button>'
                    );
                    ?>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>

            <?php if (!$list): ?>
            <tr>
                <td colspan="<?=$isFaculty ? 8 : 9?>" class="empty-cell">No classes scheduled yet.</td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
break;

case 'requests':
case 'myrequests':
    $isFaculty=$user['role']==='faculty';
    $requestsSql="SELECT r.*,f.full_name FROM schedule_requests r JOIN faculty f ON f.id=r.faculty_id";
    if($isFaculty) $requestsSql.=" WHERE r.faculty_id=".(int)$user['faculty_id'];
    $requests=rows($conn,$requestsSql." ORDER BY r.created_at DESC");
    $ownSchedules=[];
    if($isFaculty) {
        $fid=(int)$user['faculty_id'];
        $ownSchedules=rows($conn,"SELECT cs.id,cs.day_of_week,cs.start_time,cs.end_time,s.code
          FROM class_schedules cs JOIN teaching_assignments a ON a.id=cs.assignment_id
          JOIN subjects s ON s.id=a.subject_id WHERE a.faculty_id=$fid");
    }
?>
<div class="page-heading"><div><span class="eyebrow">REQUESTS</span><h1>Schedule Change Requests</h1><p>Submit and review timetable change requests.</p></div></div>
<?php if($isFaculty): ?><div class="panel"><h3>Submit a Request</h3><form method="post" class="inline-create">
<input type="hidden" name="csrf" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="submit_request"><input type="hidden" name="return_page" value="myrequests">
<select name="schedule_id"><option value="0">Select current schedule (optional)</option><?php foreach($ownSchedules as $s): ?><option value="<?=$s['id']?>"><?=e($s['code'].' · '.$s['day_of_week'].' · '.date('g:i A',strtotime($s['start_time'])) )?></option><?php endforeach; ?></select>
<select name="requested_day"><?php foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $d): ?><option><?=e($d)?></option><?php endforeach; ?></select>
<label>Requested start<input type="time" name="requested_start" required></label><label>Requested end<input type="time" name="requested_end" required></label>
<input name="reason" placeholder="Reason for request" required><button class="btn primary">Submit Request</button></form></div><?php endif; ?>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Request</th><?php if(!$isFaculty): ?><th>Faculty</th><?php endif; ?><th>Requested Schedule</th><th>Reason</th><th>Date</th><th>Status</th><?php if(!$isFaculty): ?><th>Decision</th><?php endif; ?></tr></thead><tbody>
<?php foreach($requests as $r): ?><tr><td>#<?=e($r['id'])?></td><?php if(!$isFaculty): ?><td><?=e($r['full_name'])?></td><?php endif; ?><td><?=e($r['requested_day'])?><small><?=e(date('g:i A',strtotime($r['requested_start'])).' – '.date('g:i A',strtotime($r['requested_end'])))?></small></td><td><?=e($r['reason'])?><small><?=e($r['remarks'])?></small></td><td><?=e(date('M j, Y',strtotime($r['created_at'])))?></td><td><span class="status <?=strtolower(e($r['status']))?>"><?=e($r['status'])?></span></td>
<?php if(!$isFaculty): ?><td><?php if($r['status']==='Pending'): ?><form method="post" class="decision-form"><input type="hidden" name="csrf" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="request_decision"><input type="hidden" name="return_page" value="requests"><input type="hidden" name="id" value="<?=$r['id']?>"><input name="remarks" placeholder="Remarks"><button name="status" value="Approved" class="btn tiny primary">Approve</button><button name="status" value="Rejected" class="btn tiny danger">Reject</button></form><?php else: ?>—<?php endif; ?></td><?php endif; ?></tr><?php endforeach; ?>
<?php if(!$requests) echo '<tr><td colspan="7" class="empty-cell">No requests submitted.</td></tr>'; ?>
</tbody></table></div></div>
<?php
break;

case 'profile':
    $fid=(int)($user['faculty_id']??0);
    $stmt=$conn->prepare("SELECT f.*,d.name department FROM faculty f LEFT JOIN departments d ON d.id=f.department_id WHERE f.id=?");
    $stmt->bind_param("i",$fid);$stmt->execute();$r=$stmt->get_result()->fetch_assoc();
?>
<div class="page-heading"><div><span class="eyebrow">ACCOUNT</span><h1>My Profile</h1><p>Your faculty information.</p></div></div>
<div class="panel profile-panel"><?php if($r): ?><div class="profile-avatar"><?=e(strtoupper(substr($r['full_name'],0,1)))?></div><h2><?=e($r['full_name'])?></h2><p class="muted"><?=e($r['faculty_id'])?></p><div class="profile-grid"><?php foreach(['email'=>'Email','department'=>'Department','employment_type'=>'Employment Type','specialization'=>'Specialization','status'=>'Status'] as $key=>$label): ?><div><small><?=e($label)?></small><strong><?=e($r[$key]??'—')?></strong></div><?php endforeach; ?></div><?php else: ?><div class="empty-state">No faculty profile is linked to this account. Ask the admin to link one.</div><?php endif; ?></div>
<?php
break;

case 'users':
    $list=rows($conn,"SELECT u.id,u.username,u.role,u.faculty_id,u.created_at,
      f.full_name,f.faculty_id AS linked_faculty_code
      FROM users u LEFT JOIN faculty f ON f.id=u.faculty_id ORDER BY u.id DESC");
    $faculties=rows($conn,"SELECT id,faculty_id,full_name FROM faculty ORDER BY full_name");
?>
<div class="page-heading"><div><span class="eyebrow">ACCESS CONTROL</span><h1>User Accounts</h1><p>Create login accounts and assign roles.</p></div></div>
<div class="panel"><h3>Create Account</h3><form method="post" class="inline-create"><input type="hidden" name="csrf" value="<?=e(csrfToken())?>"><input type="hidden" name="action" value="create_user"><input type="hidden" name="return_page" value="users"><input name="username" placeholder="Username" required pattern="[A-Za-z0-9_.-]{3,50}"><input type="password" name="password" placeholder="Password (8+ characters)" minlength="8" required><select name="role"><option value="faculty">Faculty</option><option value="admin">Admin</option></select><select name="faculty_id"><option value="0">Link faculty (optional)</option><?php foreach($faculties as $f): ?><option value="<?=$f['id']?>"><?=e($f['faculty_id'].' — '.$f['full_name'])?></option><?php endforeach; ?></select><button class="btn primary">Create Account</button></form>
<div class="table-wrap"><table><thead><tr><th>Username</th><th>Linked Faculty</th><th>Role</th><th>Created</th><th>Actions</th></tr></thead><tbody>
<?php foreach($list as $r): ?>
<tr>
    <td><strong><?=e($r['username'])?></strong></td>
    <td><?=e($r['full_name']??'—')?><?php if(!empty($r['linked_faculty_code'])): ?><small><?=e($r['linked_faculty_code'])?></small><?php endif; ?></td>
    <td><?=e(ucfirst($r['role']))?></td>
    <td><?=e($r['created_at'])?></td>
    <td class="actions">
        <button type="button" class="btn tiny secondary" onclick='openUserEditModal(<?=json_encode([
            'id'=>(int)$r['id'],
            'username'=>$r['username'],
            'role'=>$r['role'],
            'faculty_id'=>(int)($r['faculty_id']??0)
        ],JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP)?>)'>Edit</button>
        <form method="post" class="inline-form" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this user account? This action cannot be undone.');">
            <input type="hidden" name="csrf" value="<?=e(csrfToken())?>">
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="return_page" value="users">
            <input type="hidden" name="id" value="<?=(int)$r['id']?>">
            <button type="submit" class="btn tiny danger">Delete</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if(!$list): ?><tr><td colspan="5" class="empty-cell">No user accounts found.</td></tr><?php endif; ?>
</tbody></table></div></div>

<div class="modal-backdrop" id="user-edit-modal">
    <div class="modal">
        <div class="modal-head">
            <h2 id="user-edit-modal-title">Edit User Account</h2>
            <button type="button" class="close-btn" onclick="closeModal('user-edit-modal')">×</button>
        </div>
        <form method="post" class="stack-form" id="user-edit-form">
            <input type="hidden" name="csrf" value="<?=e(csrfToken())?>">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="return_page" value="users">
            <input type="hidden" name="id" id="user-edit-id">
            <div class="form-grid">
                <label for="user-edit-username">Username
                    <input type="text" name="username" id="user-edit-username" required minlength="3" maxlength="50" pattern="[A-Za-z0-9_.-]{3,50}" autocomplete="off">
                </label>
                <label for="user-edit-role">Account Role
                    <select name="role" id="user-edit-role" required>
                        <option value="faculty">Faculty</option>
                        <option value="admin">Admin</option>
                    </select>
                </label>
                <label for="user-edit-faculty">Linked Faculty
                    <select name="faculty_id" id="user-edit-faculty">
                        <option value="0">No linked faculty</option>
                        <?php foreach($faculties as $f): ?>
                        <option value="<?=$f['id']?>"><?=e($f['faculty_id'].' — '.$f['full_name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label for="user-edit-password">New Password (optional)
                    <input type="password" name="password" id="user-edit-password" minlength="8" autocomplete="new-password" placeholder="Leave blank to keep current password">
                </label>
            </div>
            <p class="muted">Leave the password field blank to keep the current password. A new password must be at least 8 characters.</p>
            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closeModal('user-edit-modal')">Cancel</button>
                <button type="submit" class="btn primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<script>
function openUserEditModal(account) {
    document.getElementById('user-edit-id').value = account.id;
    document.getElementById('user-edit-username').value = account.username || '';
    document.getElementById('user-edit-role').value = account.role || 'faculty';
    document.getElementById('user-edit-faculty').value = account.faculty_id || '0';
    document.getElementById('user-edit-password').value = '';
    document.getElementById('user-edit-modal-title').textContent = 'Edit User Account';
    openModal('user-edit-modal');
}
</script>
<?php
break;

case 'reports':
    $report=rows($conn,"SELECT f.faculty_id,f.full_name,d.name department,
      COALESCE(SUM(s.lecture_hours+s.lab_hours),0) hours,
      COUNT(a.id) assignments FROM faculty f LEFT JOIN departments d ON d.id=f.department_id
      LEFT JOIN teaching_assignments a ON a.faculty_id=f.id
      LEFT JOIN subjects s ON s.id=a.subject_id GROUP BY f.id ORDER BY f.full_name");
?>
<div class="page-heading"><div><span class="eyebrow">ANALYTICS</span><h1>Reports</h1><p>Workload summaries and academic scheduling reports.</p></div><button class="btn primary" onclick="window.print()">Print / Save PDF</button></div>
<div class="stats-grid"><div class="stat-card"><span>Faculty</span><strong class="stat-value"><?=countRows($conn,'faculty')?></strong></div><div class="stat-card"><span>Subjects</span><strong class="stat-value"><?=countRows($conn,'subjects')?></strong></div><div class="stat-card"><span>Schedules</span><strong class="stat-value"><?=countRows($conn,'class_schedules')?></strong></div><div class="stat-card"><span>Rooms</span><strong class="stat-value"><?=countRows($conn,'classrooms')?></strong></div></div>
<div class="panel"><div class="panel-head"><div><h3>Faculty Workload Summary</h3><p>Calculated from assigned subject contact hours.</p></div></div><div class="table-wrap"><table><thead><tr><th>Faculty ID</th><th>Name</th><th>Department</th><th>Assignments</th><th>Hours/Week</th></tr></thead><tbody><?php foreach($report as $r): ?><tr><td><?=e($r['faculty_id'])?></td><td><?=e($r['full_name'])?></td><td><?=e($r['department']??'—')?></td><td><?=e($r['assignments'])?></td><td><strong><?=e($r['hours'])?></strong></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php
break;
}
?>
<footer class="footer">Lyceum of Southern Luzon College · Faculty Workload & Academic Scheduling System</footer>
</div>
</main>
</div>
<?php endif; ?>
</body>
</html>