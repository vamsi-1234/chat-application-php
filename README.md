# chat-application-php

## Prerequisites
Before setting up and running the application, ensure that you have the following installed:

- **PHP** used version is 8.3.13
- **Composer** (dependency manager for PHP) used version is 2.8.2

You can verify the installations by running the following commands:
php -v
composer -v

Clone the repository
Go to cloned folder Install dependencies using Composer:


**composer require slim/slim slim/psr7 monolog/monolog php-di/php-di**


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

**curl -X POST -d "{\\"username\\": \\"user1\\", \\"group_name\\": \\"GroupA\\"}" -H "Content-Type: application/json" http://localhost:8080/users/group**


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

**curl -X POST -d "{\\"username\\": \\"user1\\", \\"message\\": \\"Hello, GroupA!\\"}" -H "Content-Type: application/json" http://localhost:8080/messages/send**


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

6. Move a user or group to another group


Endpoint: POST /move

Description: Move a user or group to another group.

Request Example:

**curl -X POST -d "{\\"username\\": \\"user1\\", \\"groupname2\\": \\"GroupB\\"}" -H "Content-Type: application/json" http://localhost:8080/move**


**curl -X POST -d "{\\"groupname1\\": \\"GroupA\\", \\"groupname2\\": \\"GroupB\\"}" -H "Content-Type: application/json" http://localhost:8080/move**

Error Handling and Edge Cases:

If the username is not provided in the POST /users/group or POST /messages/send requests, the API will respond with a message.

If a message is not provided in POST /messages, the API will respond with a message.

If the username does not exist when attempting to send a message, the API will return a message suggesting to create the user first.

If both username and groupname1 are provided in the request, the endpoint should return an message indicating that only one of these should be specified.

If groupname2 is missing, the endpoint should return an message indicating that groupname2 is mandatory.

If the user is already in group_name2, it returns an error message to avoid unnecessary database operations.

If the user is not in any group, an error is returned saying that the user isn’t in a group and cannot be moved.

**For Testing you can use the above curl commands in cmd  or Do install {composer require --dev phpunit/phpunit} and run vendor/bin/phpunit**

