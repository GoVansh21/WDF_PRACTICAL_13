<?php
require_once "db.php";

function respond($status, $data) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

try {
    $method = $_SERVER["REQUEST_METHOD"];

    // READ and SEARCH students
    if ($method === "GET") {
        $search = trim($_GET["search"] ?? "");

        if ($search !== "") {
            $stmt = $pdo->prepare(
                "SELECT id, name, email, course
                 FROM students
                 WHERE name LIKE ? OR email LIKE ? OR course LIKE ?
                 ORDER BY id DESC"
            );

            $term = "%" . $search . "%";
            $stmt->execute([$term, $term, $term]);
        } else {
            $stmt = $pdo->query(
                "SELECT id, name, email, course
                 FROM students
                 ORDER BY id DESC"
            );
        }

        respond(200, [
            "success" => true,
            "data" => $stmt->fetchAll()
        ]);
    }

    // Only GET and POST are supported
    if ($method !== "POST") {
        header("Allow: GET, POST");

        respond(405, [
            "success" => false,
            "message" => "Method not allowed."
        ]);
    }

    // Read JSON request body
    $input = json_decode(
        file_get_contents("php://input"),
        true
    );

    if (!is_array($input) ||
        json_last_error() !== JSON_ERROR_NONE) {
        respond(400, [
            "success" => false,
            "message" => "Invalid JSON request."
        ]);
    }

    $action = $input["action"] ?? "";

    // CREATE and UPDATE
    if ($action === "add" || $action === "update") {
        $name = trim($input["name"] ?? "");
        $email = trim($input["email"] ?? "");
        $course = trim($input["course"] ?? "");

        // Validate required fields
        if ($name === "" || $email === "" || $course === "") {
            respond(422, [
                "success" => false,
                "message" => "All fields are required."
            ]);
        }

        // Validate lengths and email
        if (
            strlen($name) > 100 ||
            strlen($email) > 150 ||
            strlen($course) > 100 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            respond(422, [
                "success" => false,
                "message" => "Enter valid details. Check field lengths and email."
            ]);
        }

        // CREATE student
        if ($action === "add") {
            $stmt = $pdo->prepare(
                "INSERT INTO students (name, email, course)
                 VALUES (?, ?, ?)"
            );

            $stmt->execute([$name, $email, $course]);

            respond(201, [
                "success" => true,
                "message" => "Student added successfully."
            ]);
        }

        // Validate ID for UPDATE
        $id = filter_var(
            $input["id"] ?? null,
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]]
        );

        if ($id === false || $id === null) {
            respond(422, [
                "success" => false,
                "message" => "Valid student ID is required."
            ]);
        }

        $stmt = $pdo->prepare(
            "UPDATE students
             SET name = ?, email = ?, course = ?
             WHERE id = ?"
        );

        $stmt->execute([$name, $email, $course, $id]);

        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare(
                "SELECT id FROM students WHERE id = ?"
            );
            $check->execute([$id]);

            if (!$check->fetch()) {
                respond(404, [
                    "success" => false,
                    "message" => "Student not found."
                ]);
            }
        }

        respond(200, [
            "success" => true,
            "message" => "Student updated successfully."
        ]);
    }

    // DELETE student
    if ($action === "delete") {
        $id = filter_var(
            $input["id"] ?? null,
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]]
        );

        if ($id === false || $id === null) {
            respond(422, [
                "success" => false,
                "message" => "Valid student ID is required."
            ]);
        }

        $stmt = $pdo->prepare(
            "DELETE FROM students WHERE id = ?"
        );

        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            respond(404, [
                "success" => false,
                "message" => "Student not found."
            ]);
        }

        respond(200, [
            "success" => true,
            "message" => "Student deleted successfully."
        ]);
    }

    respond(400, [
        "success" => false,
        "message" => "Invalid action."
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());

    if ($e->getCode() === "23000") {
        respond(409, [
            "success" => false,
            "message" => "This email address already exists."
        ]);
    }

    respond(500, [
        "success" => false,
        "message" => "A database error occurred."
    ]);

} catch (Throwable $e) {
    error_log($e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "An internal server error occurred."
    ]);
}
?>