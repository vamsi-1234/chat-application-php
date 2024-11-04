# chat-application-php



Go to cloned folder Install dependencies using Composer:

**composer install**


Configure PHP settings:

Open your php.ini file

Ensure the following setting is enabled to allow for PDO and SQLite support:

**extension=pdo_sqlite**

Run Database

**php setup.php**

Running the Application

To start the application, run the following command:

**php -S localhost:8080 -t public**


1. Create or Add a User to a Group

Endpoint: POST /users/group

Description: Adds a user to a specified group. If the group name is omitted, the user is added to the default group "Everyone".

Request Example:

**curl -X POST -d "{\\"username\\": \\"user1\\"}" -H "Content-Type: application/json" http://localhost:8080/users/group**

With specific group:

**curl -X POST -d "{\"username\": \"user1\", \"group_name\": \"GroupA\"}" -H "Content-Type: application/json" http://localhost:8080/users/group**


2. Retrieve All Groups and Users in Each Group

Endpoint: GET /groups

Description: Retrieves all groups with users in each group.

Request Example:

**curl http://localhost:8080/groups/users**

3. Retrieve All Groups

Endpoint: GET /groups

Description: Retrieves all groups.

Request Example:

**curl http://localhost:8080/groups**

3. Send a Message to a Group

   
Endpoint: POST /messages/send

Description: Sends a message to a group if the user belongs to that group.

Request Example:

**curl -X POST -d "{\"username\": \"user1\", \"message\": \"Hello, GroupA!\"}" -H "Content-Type: application/json" http://localhost:8080/messages**


4. Retrieve All Messages in a Specific Group

   
Endpoint: GET /messages/{group_name}

Description: Retrieves all messages for a specific group.

Request Example:

**curl http://localhost:8080/messages/GroupA**


5. Retrieve All Messages from All Groups
Endpoint: GET /messages

Description: Retrieves all messages from all groups.

Request Example:

**curl http://localhost:8080/messages**


Error Handling and Edge Cases:

If the username is not provided in the POST /users/group or POST /messages requests, the API will respond with a message.

If a message is not provided in POST /messages, the API will respond with a message.

If the username does not exist when attempting to send a message, the API will return a message suggesting to create the user first.


