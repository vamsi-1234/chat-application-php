<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write("Welcome to the chat app!");
    return $response;
});
$app->get('/favicon.ico', function ($request, $response) {
    return $response->withStatus(204); // No Content
});
$app->post('/messages/send', function (Request $request, Response $response) {
    $db = $this->get('db');
    
    $rawData = $request->getBody()->getContents();
    $data = json_decode($rawData, true);

    // Validate input
    if (empty($data['username'])) {
        $response->getBody()->write(json_encode(['error' => 'Username not provided']));
    }

    if (empty($data['message'])) {
        $response->getBody()->write(json_encode(['error' => 'Message is mandatory']));
    }

    $username = $data['username'];
    $message = $data['message'];

    // Check if the user exists
    $userStmt = $db->prepare("SELECT * FROM users WHERE username = :username");
    $userStmt->execute([':username' => $username]);
    $user = $userStmt->fetch();

    if (!$user) {
        $response->getBody()->write(json_encode(['error' => 'User does not exist, create a user first']));
    }
    if($user){
    // Check if user is in any group
    $userGroupStmt = $db->prepare("
        SELECT g.id 
        FROM user_group ug
        JOIN chat_groups g ON ug.group_id = g.id 
        WHERE ug.user_id = :user_id
    ");
    $userGroupStmt->execute([':user_id' => $user['id']]);
    $group = $userGroupStmt->fetch();

    // Insert the message
    $insertMessageStmt = $db->prepare("
        INSERT INTO messages (user_id, group_id, message) 
        VALUES (:user_id, :group_id, :message)
    ");
    $insertMessageStmt->execute([
        ':user_id' => $user['id'],
        ':group_id' => $group['id'],
        ':message' => $message
    ]);

    $response->getBody()->write(json_encode(['message' => 'Message sent successfully']));
}
    return $response->withHeader('Content-Type', 'application/json');
});
$app->get('/messages/group/{groupName}', function (Request $request, Response $response, $args) {
    $db = $this->get('db');
    $groupName = $args['groupName'];

    // Check if the group exists
    $groupStmt = $db->prepare("SELECT * FROM chat_groups WHERE name = :group_name");
    $groupStmt->execute([':group_name' => $groupName]);
    $group = $groupStmt->fetch();

    if (!$group) {
        $response->getBody()->write(json_encode(['error' => 'Group does not exist']));
    }
    if($group){
    // Retrieve messages for the group
    $messageStmt = $db->prepare("
        SELECT m.message, u.username, m.created_at 
        FROM messages m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.group_id = :group_id 
        ORDER BY m.created_at ASC
    ");
    $messageStmt->execute([':group_id' => $group['id']]);
    $messages = $messageStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response->getBody()->write(json_encode($messages));
    }
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/groups', function (Request $request, Response $response) {
    $db = $this->get('db');

    // Retrieve all groups
    $groupsStmt = $db->query("SELECT name FROM chat_groups");
    $groups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the list of groups
    $response->getBody()->write(json_encode(['groups' => $groups]));
    return $response->withHeader('Content-Type', 'application/json');
});
$app->get('/groups/users', function (Request $request, Response $response) {
    $db = $this->get('db');

    // Query to get all groups with their corresponding users
    $stmt = $db->prepare("
        SELECT g.name AS group_name, u.username
        FROM chat_groups g
        LEFT JOIN user_group ug ON g.id = ug.group_id
        LEFT JOIN users u ON ug.user_id = u.id
        ORDER BY g.name
    ");
    $stmt->execute();
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Structure the response to group users under their respective group names
    $groupedData = [];
    foreach ($groups as $row) {
        $groupName = $row['group_name'];
        if (!isset($groupedData[$groupName])) {
            $groupedData[$groupName] = [];
        }
        if ($row['username'] !== null) {
            $groupedData[$groupName][] = $row['username'];
        }
    }

    // Return JSON response
    $response->getBody()->write(json_encode($groupedData));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/users/group', function (Request $request, Response $response) {
    $db = $this->get('db');
    // error_log(print_r($request->getBody(), true)); 
    $rawData = $request->getBody()->getContents();
    $data = json_decode($rawData, true);
    error_log(print_r($rawData, true)); 
    // $data = $request->getParsedBody();
    error_log(print_r($data, true)); 
    // Retrieve username and group name from the request body
    $username = !empty($data['username']) ? $data['username'] : null; 
    $groupName = !empty($data['group_name']) ? $data['group_name'] : "Everyone";// Default to 'Everyone' if no group name is provided

    // Check if the group exists, create if it doesn't
    $groupStmt = $db->prepare("SELECT * FROM chat_groups WHERE name = :group_name");
    $groupStmt->execute([':group_name' => $groupName]);
    $group = $groupStmt->fetch();

    if (!$group) {
        // Create group if it doesn't exist
        $createGroupStmt = $db->prepare("INSERT INTO chat_groups (name) VALUES (:group_name)");
        $createGroupStmt->execute([':group_name' => $groupName]);
        $groupId = $db->lastInsertId();
        if(!$username){
        $response->getBody()->write(json_encode(['message' => "Group '$groupName' created without any users"]));
        }
        else{
            $response->getBody()->write(json_encode(['message' => "Group '$groupName' created"]));
        
        }
        return $response->withHeader('Content-Type', 'application/json');
    }

    // If username is provided, proceed with adding the user
    if ($username) {
        // Check if the user exists
        $userStmt = $db->prepare("SELECT * FROM users WHERE username = :username");
        $userStmt->execute([':username' => $username]);
        $user = $userStmt->fetch();

        // Create user if they don't exist
        if (!$user) {
            $createUserStmt = $db->prepare("INSERT INTO users (username) VALUES (:username)");
            $createUserStmt->execute([':username' => $username]);
            $userId = $db->lastInsertId();
        } else {
            $userId = $user['id'];
        }

        // Check if user is already in the specified group
        $userGroupStmt = $db->prepare("SELECT * FROM user_group WHERE user_id = :user_id AND group_id = :group_id");
        $userGroupStmt->execute([':user_id' => $userId, ':group_id' => $group['id']]);

        if ($userGroupStmt->fetch()) {
            // User is already in the specified group
            $response->getBody()->write(json_encode(['message' => "User '$username' is already in the group '$groupName'"]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // Check if user is in any other group
        $userOtherGroupStmt = $db->prepare("SELECT g.name FROM user_group ug JOIN chat_groups g ON ug.group_id = g.id WHERE ug.user_id = :user_id AND g.id != :group_id");
        $userOtherGroupStmt->execute([':user_id' => $userId, ':group_id' => $group['id']]);
        $otherGroup = $userOtherGroupStmt->fetch();

        if ($otherGroup) {
            // Inform user of membership in another group
            $response->getBody()->write(json_encode(['message' => "User is already in another group '{$otherGroup['name']}'"]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        // Add user to the specified or default group
        $addUserToGroupStmt = $db->prepare("INSERT INTO user_group (user_id, group_id) VALUES (:user_id, :group_id)");
        $addUserToGroupStmt->execute([':user_id' => $userId, ':group_id' => $group['id']]);

        $response->getBody()->write(json_encode(['message' => "User '$username'added to group '$groupName'"]));
    } else {
        // Only group creation occurred, no user to add
        $response->getBody()->write(json_encode(['message' => "Group '$groupName' already exists, no users added"]));
    }
    
    return $response->withHeader('Content-Type', 'application/json');
});
$app->get('/messages', function (Request $request, Response $response) {
    $db = $this->get('db');

    // Query to get all messages from all groups
    $stmt = $db->prepare("
        SELECT g.name AS group_name, u.username, m.message, m.created_at
        FROM messages m
        JOIN chat_groups g ON m.group_id = g.id
        JOIN users u ON m.user_id = u.id
        ORDER BY g.name, m.created_at
    ");
    
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare the response
    if ($messages) {
        $response->getBody()->write(json_encode($messages));
    } else {
        $response->getBody()->write(json_encode(['message' => 'No messages found']));
    }
    
    return $response->withHeader('Content-Type', 'application/json');
});
$app->post('/users', function (Request $request, Response $response) {
    $db = $this->get('db');
    $data = $request->getParsedBody();
    $username = $data['username'];

    $stmt = $db->prepare("INSERT INTO users (username) VALUES (:username)");
    $stmt->execute([':username' => $username]);

    $response->getBody()->write(json_encode(['user_id' => $db->lastInsertId()]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/groups', function (Request $request, Response $response) {
    $db = $this->get('db');
    $data = $request->getParsedBody();
    $name = $data['name'];

    $stmt = $db->prepare("INSERT INTO chat_groups (name) VALUES (:name)");
    $stmt->execute([':name' => $name]);

    $response->getBody()->write(json_encode(['group_id' => $db->lastInsertId()]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/groups/{group_id}/messages', function (Request $request, Response $response, $args) {
    $db = $this->get('db');
    $data = $request->getParsedBody();
    $groupId = $args['group_id'];
    $userId = $data['user_id'];
    $content = $data['content'];

    $stmt = $db->prepare("INSERT INTO messages (user_id, group_id, content) VALUES (:user_id, :group_id, :content)");
    $stmt->execute([':user_id' => $userId, ':group_id' => $groupId, ':content' => $content]);

    $response->getBody()->write(json_encode(['message_id' => $db->lastInsertId()]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/groups/{group_id}/messages', function (Request $request, Response $response, $args) {
    $db = $this->get('db');
    $groupId = $args['group_id'];

    $stmt = $db->prepare("
        SELECT messages.content, users.username, messages.timestamp
        FROM messages
        JOIN users ON messages.user_id = users.id
        WHERE messages.group_id = :group_id
        ORDER BY messages.timestamp ASC
    ");
    $stmt->execute([':group_id' => $groupId]);

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $response->getBody()->write(json_encode($messages));
    return $response->withHeader('Content-Type', 'application/json');
});
