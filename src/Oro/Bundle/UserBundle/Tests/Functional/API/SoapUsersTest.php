<?php

namespace Oro\Bundle\UserBundle\Tests\Functional\API;

use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Bundle\TestFrameworkBundle\Test\ToolsAPI;
use Oro\Bundle\TestFrameworkBundle\Test\Client;

/**
 * @outputBuffering enabled
 * @db_isolation
 */
class SoapUsersTest extends WebTestCase
{
    /** Default value for role label */
    const DEFAULT_VALUE = 'USER_LABEL';

    /** @var Client */
    protected $client;

    public function setUp()
    {
        $this->client = static::createClient(array(), ToolsAPI::generateWsseHeader());
        $this->client->soap(
            "http://localhost/api/soap",
            array(
                'location' => 'http://localhost/api/soap',
                'soap_version' => SOAP_1_2
            )
        );
    }

    /**
     * @param string $request
     * @param array  $response
     *
     * @dataProvider requestsApi
     */
    public function testCreateUser($request, $response)
    {
        $id = $this->client->getSoap()->createUser($request);
        $this->assertInternalType('int', $id);
        $this->assertGreaterThan(0, $id);
    }

    /**
     * @param string $request
     * @param array  $response
     *
     * @dataProvider requestsApi
     * @depends testCreateUser
     */
    public function testUpdateUser($request, $response)
    {
        //get user id
        $userId = $this->client
            ->getSoap()
            ->getUserBy(array('item' => array('key' =>'username', 'value' => $request['username'])));
        $userId = ToolsAPI::classToArray($userId);

        $request['username'] = 'Updated_' . $request['username'];
        $request['email'] = 'Updated_' . $request['email'];
        unset($request['plainPassword']);

        $result = $this->client->getSoap()->updateUser($userId['id'], $request);
        $result = ToolsAPI::classToArray($result);
        ToolsAPI::assertEqualsResponse($response, $result);

        $user = $this->client->getSoap()->getUser($userId['id']);
        $user = ToolsAPI::classToArray($user);
        $this->assertEquals($request['username'], $user['username']);
        $this->assertEquals($request['email'], $user['email']);
    }

    /**
     * @dataProvider requestsApi
     * @depends testUpdateUser
     */
    public function testGetUsers($request, $response)
    {
        $users = $this->client->getSoap()->getUsers(1, 1000);
        $users = ToolsAPI::classToArray($users);

        $user = array_filter(
            $users['item'],
            function ($a) use ($request) {
                return $a['username'] === 'Updated_' . $request['username'];
            }
        );

        $this->assertNotEmpty($user, 'Updated user is not in users list');

    }

    public function testGetUserRoles()
    {
        $roles = $this->client->getSoap()->getUserRoles(1);
        $roles = ToolsAPI::classToArray($roles);
        $this->assertEquals('Administrator', $roles['item']['label']);
    }

    public function testGetUserGroups()
    {
        $groups = $this->client->getSoap()->getUserGroups(1);
        $groups = ToolsAPI::classToArray($groups);
        $this->assertEquals('Administrators', $groups['item']['name']);
    }

    /**
     * @expectedException \SoapFault
     * @expectedExceptionMessage Empty filter data
     */
    public function testGetUserByException()
    {
        $this->client->getSoap()->getUserBy();
    }

    /**
     * @dataProvider requestsApi
     * @depends testGetUsers
     * @expectedException \SoapFault
     * @expectedExceptionMessage User cannot be found using specified filter
     */
    public function testDeleteUser($request)
    {
        //get user id
        $userId = $this->client->getSoap()->getUserBy(
            array(
                'item' => array(
                    'key' =>'username',
                    'value' =>'Updated_' . $request['username'])
            )
        );
        $userId = ToolsAPI::classToArray($userId);

        $result = $this->client->getSoap()->deleteUser($userId['id']);
        $this->assertTrue($result);

        $this->client->getSoap()->getUserBy(
            array(
                'item' => array(
                    'key' =>'username',
                    'value' =>'Updated_' . $request['username'])
            )
        );
    }

    /**
     * @expectedException \SoapFault
     * @expectedExceptionMessage An operation is forbidden. Reason: self delete
     */
    public function testSelfDeleteUser()
    {
        $this->client->getSoap()->deleteUser(1);
    }

    /**
     * Data provider for REST API tests
     *
     * @return array
     */
    public function requestsApi()
    {
        return ToolsAPI::requestsApi(__DIR__ . DIRECTORY_SEPARATOR . 'UserRequest');
    }
}
