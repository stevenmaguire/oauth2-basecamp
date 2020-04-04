<?php

namespace Stevenmaguire\OAuth2\Client\Test\Provider;

use Eloquent\Phony\Phpunit\Phony;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;
use Stevenmaguire\OAuth2\Client\Provider\Basecamp as BasecampProvider;

class BasecampTest extends TestCase
{
    /** @var BasecampProvider */
    protected $provider;

    protected function setUp()
    {
        $this->provider = new BasecampProvider([
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'none',
        ]);
    }

    public function testAuthorizationUrl()
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        parse_str($uri['query'], $query);

        $this->assertArrayHasKey('client_id', $query);
        $this->assertArrayHasKey('redirect_uri', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('type', $query);

        $this->assertAttributeNotEmpty('state', $this->provider);
    }

    // https://launchpad.37signals.com/authorization/new
    public function testBaseAuthorizationUrl()
    {
        $url = $this->provider->getBaseAuthorizationUrl([]);
        $uri = parse_url($url);

        $this->assertEquals('/authorization/new', $uri['path']);
    }

    // https://launchpad.37signals.com/authorization/token
    public function testBaseAccessTokenUrl()
    {
        $url = $this->provider->getBaseAccessTokenUrl([]);
        $uri = parse_url($url);
        parse_str($uri['query'], $query);

        $this->assertEquals('/authorization/token', $uri['path']);
        $this->assertArrayHasKey('type', $query);
    }

    public function testResourceOwnerDetailsUrl()
    {
        $token = $this->mockAccessToken();

        $url = $this->provider->getResourceOwnerDetailsUrl($token);

        $this->assertEquals('https://launchpad.37signals.com/authorization.json', $url);
    }

    public function testUserData()
    {
        // Mock
        $response = json_decode(file_get_contents(realpath(dirname(__FILE__).'/../../data/resource_owner.json')), true);

        $token = $this->mockAccessToken();

        $provider = Phony::partialMock(BasecampProvider::class);
        $provider->fetchResourceOwnerDetails->returns($response);
        $client = $provider->get();

        // Execute
        $user = $client->getResourceOwner($token);

        // Verify
        Phony::inOrder(
            $provider->fetchResourceOwnerDetails->called()
        );

        $this->assertInstanceOf('League\OAuth2\Client\Provider\ResourceOwnerInterface', $user);

        $this->assertEquals(9999999, $user->getId());
        $this->assertEquals('Jason Fried', $user->getName());
        $this->assertEquals('jason@basecamp.com', $user->getEmail());

        $user = $user->toArray();
    }

    public function testErrorResponse()
    {
        // Mock
        $error_json = '{"error": {"code": 400, "message": "I am an error"}}';

        $response = Phony::mock('GuzzleHttp\Psr7\Response');
        $response->getHeader->returns(['application/json']);
        $response->getBody->returns($error_json);

        $provider = Phony::partialMock(BasecampProvider::class);
        $provider->getResponse->returns($response);

        $client = $provider->get();

        $token = $this->mockAccessToken();

        // Expect
        $this->expectException(IdentityProviderException::class);

        // Execute
        $user = $client->getResourceOwner($token);

        // Verify
        Phony::inOrder(
            $provider->getResponse->calledWith($this->instanceOf('GuzzleHttp\Psr7\Request')),
            $response->getHeader->called(),
            $response->getBody->called()
        );
    }

    /**
     * @return AccessToken
     */
    private function mockAccessToken()
    {
        return new AccessToken(json_decode(file_get_contents(__DIR__.'/../../data/access_token.json'), true));
    }
}
