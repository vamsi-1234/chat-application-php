<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Helper function to send JSON responses.
 *
 * @param Response $response The response object.
 * @param mixed $data The data to include in the response.
 * @param int $status HTTP status code (default is 200).
 * @return Response The response with JSON data.
 */
function jsonResponse(Response $response, $data, $status = 200) {
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
}

// Welcome route
$app->get('/', function (Request $request, Response $response) {
    return jsonResponse($response, ['message' => 'Welcome to the chat app!']);
});

// Route to handle favicon requests with no content
$app->get('/favicon.ico', function ($request, $response) {
    return $response->withStatus(204);
});

// Route to send a message
$app->post('/messages/send', function (Request $request, Response $response) {
    $db = $this->get('db');
    $data = json_decode($request->getBody()->getContents(), true);

    // Validate input for required fields
    if (empty($data['username']) || empty($data['message'])) {
        return jsonResponse($response, ['error' => 'Username and message are required'], 400);
    }

    $username = $data['username'];
    $message = $data['message'];

    // Check if the user exists
    $userStmt = $db->prepare("SELECT id FROM users WHERE username = :username");
    $userStmt->execute([':username' => $username]);
    $user = $userStmt->fetch();

    if (!$user) {
        return jsonResponse($response, ['error' => 'User does not exist, create a user first'], 404);
    }

    // Check if the user belongs to any group
    $groupStmt = $db->prepare("
        SELECT g.id FROM user_group ug
        JOIN chat_groups g ON ug.group_id = g.id
        WHERE ug.user_id = :user_id
    ");
    $groupStmt->execute([':user_id' => $user['id']]);
    $group = $groupStmt->fetch();

    if (!$group) {
        return jsonResponse($response, ['error' => 'User is not in any group'], 400);
    }

    // Insert the message into the user's group
    $insertStmt = $db->prepare("
        INSERT INTO messages (user_id, group_id, message)
        VALUES (:user_id, :group_id, :message)
    ");
    $insertStmt->execute([
        ':user_id' => $user['id'],
        ':group_id' => $group['id'],
        ':message' => $message
    ]);

    return jsonResponse($response, ['message' => 'Message sent successfully']);
});

// Route to retrieve messages for a specific group by group name
$app->get('/messages/group/{groupName}', function (Request $request, Response $response, $args) {
    $db = $this->get('db');
    $groupName = $args['groupName'];

    // Check if the group exists
    $groupStmt = $db->prepare("SELECT id FROM chat_groups WHERE name = :group_name");
    $groupStmt->execute([':group_name' => $groupName]);
    $group = $groupStmt->fetch();

    if (!$group) {
        return jsonResponse($response, ['error' => 'Group does not exist'], 404);
    }

    // Fetch messages associated with the group
    $messageStmt = $db->prepare("
        SELECT m.message, u.username, m.created_at
        FROM messages m
        JOIN users u ON m.user_id = u.id
        WHERE m.group_id = :group_id
        ORDER BY m.created_at ASC
    ");
    $messageStmt->execute([':group_id' => $group['id']]);
    $messages = $messageStmt->fetchAll(PDO::FETCH_ASSOC);

    return jsonResponse($response, $messages ?: ['message' => 'No messages found']);
});

// Route to list all available groups
$app->get('/groups', function (Request $request, Response $response) {
    $db = $this->get('db');
    $stmt = $db->query("SELECT name FROM chat_groups");
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return jsonResponse($response, ['groups' => $groups]);
});

// Route to retrieve all users grouped by their respective groups
$app->get('/groups/users', function (Request $request, Response $response) {
    $db = $this->get('db');
    $stmt = $db->prepare("
        SELECT g.name AS group_name, u.username
        FROM chat_groups g
        LEFT JOIN user_group ug ON g.id = ug.group_id
        LEFT JOIN users u ON ug.user_id = u.id
        ORDER BY g.name
    ");
    $stmt->execute();
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize users under their respective group names
    $groupedData = [];
    foreach ($groups as $row) {
        $groupedData[$row['group_name']][] = $row['username'];
    }

    return jsonResponse($response, $groupedData);
});

// Route to create or add a user to a group
$app->post('/users/group', function (Request $request, Response $response) {
    $db = $this->get('db');
    $data = json_decode($request->getBody()->getContents(), true);
    $username = $data['username'] ?? null;
    $groupName = $data['group_name'] ?? "Everyone";

    // Check if the group exists, create if it doesn't
    $groupStmt = $db->prepare("SELECT id FROM chat_groups WHERE name = :group_name");
    $groupStmt->execute([':group_name' => $groupName]);
    $group = $groupStmt->fetch();

    if ($group) {
        // If the group exists but no username is provided, return a message
        if (!$username) {
            return jsonResponse1($response, [
                "message" => "Group '$groupName' already exists."
            ], 400);
        }
        $groupId = $group['id'];
    } else {
        // Create group if it doesn't exist
        $createGroupStmt = $db->prepare("INSERT INTO chat_groups (name) VALUES (:group_name)");
        $createGroupStmt->execute([':group_name' => $groupName]);
        $groupId = $db->lastInsertId();
    } else {
        $groupId = $group['id'];
    }

    // Proceed with adding user if a username is provided
    if ($username) {
        // Check if the user exists, create if it doesn't
        $userStmt = $db->prepare("SELECT id FROM users WHERE username = :username");
        $userStmt->execute([':username' => $username]);
        $user = $userStmt->fetch();

        if (!$user) {
            // Create user if not exists
            $createUserStmt = $db->prepare("INSERT INTO users (username) VALUES (:username)");
            $createUserStmt->execute([':username' => $username]);
            $userId = $db->lastInsertId();
        } else {
            $userId = $user['id'];

            // Check if the user is already in a group
            $userGroupStmt = $db->prepare("
                SELECT g.name AS group_name
                FROM user_group ug
                JOIN chat_groups g ON ug.group_id = g.id
                WHERE ug.user_id = :user_id
            ");
            $userGroupStmt->execute([':user_id' => $userId]);
            $existingGroup = $userGroupStmt->fetch();

            if ($existingGroup) {
                // If the user is already in a group, return a message indicating they cannot be moved
                return jsonResponse($response, [
                    "message" => "User '$username' is already in group '{$existingGroup['group_name']}', cannot move the user to another group."
                ], 400);
            }
        }

        // Add the user to the specified group if not already assigned
        $addUserStmt = $db->prepare("INSERT INTO user_group (user_id, group_id) VALUES (:user_id, :group_id)");
        $addUserStmt->execute([':user_id' => $userId, ':group_id' => $groupId]);

        return jsonResponse($response, ["message" => "User '$username' added to group '$groupName'"]);
    }

    // If only the group was created, respond accordingly
    return jsonResponse($response, ["message" => "Group '$groupName' created without any users"]);
});

// $app->post('/users/group', function (Request $request, Response $response) {
//     $db = $this->get('db');
//     $data = json_decode($request->getBody()->getContents(), true);
//     $username = $data['username'] ?? null;
//     $groupName = $data['group_name'] ?? "Everyone";

//     // Check if the group exists, create if it doesn't
//     $groupStmt = $db->prepare("SELECT id FROM chat_groups WHERE name = :group_name");
//     $groupStmt->execute([':group_name' => $groupName]);
//     $group = $groupStmt->fetch();

//     if (!$group) {
//         // Create group if not exists
//         $createGroupStmt = $db->prepare("INSERT INTO chat_groups (name) VALUES (:group_name)");
//         $createGroupStmt->execute([':group_name' => $groupName]);
//         $groupId = $db->lastInsertId();
//         if (!$username) {
//             return jsonResponse($response, ["message" => "Group '$groupName' created without any users"]);
//         }
//     } else {
//         $groupId = $group['id'];
//     }

//     // Proceed with adding user if a username is provided
//     if ($username) {
//         // Check if the user exists, create if it doesn't
//         $userStmt = $db->prepare("SELECT id FROM users WHERE username = :username");
//         $userStmt->execute([':username' => $username]);
//         $user = $userStmt->fetch();

//         if (!$user) {
//             $createUserStmt = $db->prepare("INSERT INTO users (username) VALUES (:username)");
//             $createUserStmt->execute([':username' => $username]);
//             $userId = $db->lastInsertId();
//         } else {
//             $userId = $user['id'];
//         }

//         // Check if the user is already in the group
//         $userGroupStmt = $db->prepare("SELECT 1 FROM user_group WHERE user_id = :user_id AND group_id = :group_id");
//         $userGroupStmt->execute([':user_id' => $userId, ':group_id' => $groupId]);

//         if (!$userGroupStmt->fetch()) {
//             // Add the user to the group if not already a member
//             $addUserStmt = $db->prepare("INSERT INTO user_group (user_id, group_id) VALUES (:user_id, :group_id)");
//             $addUserStmt->execute([':user_id' => $userId, ':group_id' => $groupId]);
//             return jsonResponse($response, ["message" => "User '$username' added to group '$groupName'"]);
//         } else {
//             return jsonResponse($response, ["message" => "User '$username' is already in the group '$groupName'"]);
//         }
//     }

//     // If only the group was created, respond accordingly
//     return jsonResponse($response, ["message" => "Group '$groupName' already exists, no users added"]);
// });

// Route to get all messages from all groups
$app->get('/messages', function (Request $request, Response $response) {
    $db = $this->get('db');
    $stmt = $db->prepare("
        SELECT g.name AS group_name, u.username, m.message, m.created_at
        FROM messages m
        JOIN chat_groups g ON m.group_id = g.id
        JOIN users u ON m.user_id = u.id
        ORDER BY g.name, m.created_at
    ");
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return jsonResponse($response, $messages ?: ['message' => 'No messages found']);
});
$app->post('/move', function (Request $request, Response $response) {
    $db = $this->get('db');
    $data = json_decode($request->getBody()->getContents(), true);
    
    $username = $data['username'] ?? null;
    $groupName1 = $data['groupname1'] ?? null;
    $groupName2 = $data['groupname2'] ?? null;

    // Ensure groupName2 is provided
    if (!$groupName2) {
        return jsonResponse($response, ["error" => "Group name 2 is mandatory"], 400);
    }

    // Check if both username and group_name1 are provided
    if ($username && $groupName1) {
        return jsonResponse($response, ["error" => "Please be specific. Specify either 'username' or 'group_name1', not both."], 400);
    }

    // Check if groupName2 exists
    $groupStmt = $db->prepare("SELECT id FROM chat_groups WHERE name = :group_name");
    $groupStmt->execute([':group_name' => $groupName2]);
    $group2 = $groupStmt->fetch();

    if (!$group2) {
        return jsonResponse($response, ["error" => "Group '$groupName2' does not exist"], 404);
    }
    $groupId2 = $group2['id'];

    if ($username) {
        // Case 1: Move a specific user to groupName2, checking their current group automatically

        // Check if the user exists
        $userStmt = $db->prepare("SELECT id FROM users WHERE username = :username");
        $userStmt->execute([':username' => $username]);
        $user = $userStmt->fetch();

        if (!$user) {
            return jsonResponse($response, ["error" => "User '$username' does not exist"], 404);
        }
        $userId = $user['id'];

        // Find the current group of the user
        $userGroupStmt = $db->prepare("
            SELECT g.id, g.name FROM chat_groups g
            JOIN user_group ug ON g.id = ug.group_id
            WHERE ug.user_id = :user_id
        ");
        $userGroupStmt->execute([':user_id' => $userId]);
        $group1 = $userGroupStmt->fetch();

        if (!$group1) {
            return jsonResponse($response, ["error" => "User '$username' is not in any group"], 404);
        }

        // Prevent moving the user to the same group
        if ($group1['id'] == $groupId2) {
            return jsonResponse($response, ["error" => "User '$username' is already in group '$groupName2'"], 400);
        }

        // Move the user to groupName2
        $db->beginTransaction();
        try {
            // Remove user from their current group
            $deleteUserGroupStmt = $db->prepare("DELETE FROM user_group WHERE user_id = :user_id AND group_id = :group_id");
            $deleteUserGroupStmt->execute([':user_id' => $userId, ':group_id' => $group1['id']]);

            // Add user to groupName2
            $addUserToGroupStmt = $db->prepare("INSERT INTO user_group (user_id, group_id) VALUES (:user_id, :group_id)");
            $addUserToGroupStmt->execute([':user_id' => $userId, ':group_id' => $groupId2]);

            $db->commit();
            return jsonResponse($response, ["message" => "User '$username' moved from '{$group1['name']}' to '$groupName2'"]);
        } catch (Exception $e) {
            $db->rollBack();
            return jsonResponse($response, ["error" => "Failed to move user: " . $e->getMessage()], 500);
        }

    } elseif ($groupName1) {
        // Case 2: Move all users from groupName1 to groupName2

        // Check if groupName1 exists
        $groupStmt->execute([':group_name' => $groupName1]);
        $group1 = $groupStmt->fetch();

        if (!$group1) {
            return jsonResponse($response, ["error" => "Group '$groupName1' does not exist"], 404);
        }
        $groupId1 = $group1['id'];

        // Check if there are users in groupName1
        $userCountStmt = $db->prepare("SELECT COUNT(*) AS count FROM user_group WHERE group_id = :group_id");
        $userCountStmt->execute([':group_id' => $groupId1]);
        $userCount = $userCountStmt->fetchColumn();

        if ($userCount == 0) {
            return jsonResponse($response, ["error" => "No users are present in '$groupName1', cannot move"], 400);
        }

        // Move all users to groupName2
        $db->beginTransaction();
        try {
            // Update all users in groupName1 to point to groupName2
            $updateUsersStmt = $db->prepare("UPDATE user_group SET group_id = :group_id2 WHERE group_id = :group_id1");
            $updateUsersStmt->execute([':group_id2' => $groupId2, ':group_id1' => $groupId1]);

            // Delete groupName1 after users have been moved
            $deleteGroupStmt = $db->prepare("DELETE FROM chat_groups WHERE id = :group_id1");
            $deleteGroupStmt->execute([':group_id1' => $groupId1]);

            $db->commit();
            return jsonResponse($response, ["message" => "All users moved from '$groupName1' to '$groupName2', and '$groupName1' has been deleted"]);
        } catch (Exception $e) {
            $db->rollBack();
            return jsonResponse($response, ["error" => "Failed to move users: " . $e->getMessage()], 500);
        }

    } else {
        return jsonResponse($response, ["error" => "Either 'username' or 'group_name1' must be provided"], 400);
    }
});

