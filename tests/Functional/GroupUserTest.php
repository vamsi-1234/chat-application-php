<?php

namespace Tests\Functional;

use Slim\Psr7\Factory\ServerRequestFactory;
use PHPUnit\Framework\TestCase;
use Slim\App;

class GroupUserTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        // Initialize the Slim application here
        $this->app = require __DIR__ . '/../../public/index.php';
    }

    private function request($method, $uri, $data = null)
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri);
        if ($data) {
            $request->getBody()->write(json_encode($data));
            $request = $request->withHeader('Content-Type', 'application/json');
        }

        $response = $this->app->handle($request);
        $response->getBody()->rewind();
        return json_decode($response->getBody()->getContents(), true);
    }

    public function testDefaultGroup()
    {
        $response = $this->request('POST', '/users/group', ['group_name' => 'Everyone']);
        $expectedMessages = [
            'Group \'Everyone\' created without any users',
            'Group \'Everyone\' already exists.',
        ];
    
        $this->assertContains($response['message'], $expectedMessages, 'Unexpected response message: ' . $response['message']);
    }



}
