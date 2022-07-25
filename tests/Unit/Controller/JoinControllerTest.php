<?php

namespace OCA\BigBlueButton\Tests\Controller;

use OCA\BigBlueButton\BigBlueButton\API;
use OCA\BigBlueButton\Controller\JoinController;
use OCA\BigBlueButton\Db\Room;
use OCA\BigBlueButton\NotFoundException;
use OCA\BigBlueButton\Permission;
use OCA\BigBlueButton\Service\RoomService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\BackgroundJob\IJobList;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class JoinControllerTest extends TestCase {
	private $request;
	private $service;
	private $userSession;
	private $urlGenerator;
	private $controller;
	private $api;
	private $permission;
	private $room;
	private $jobList;

	public function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->service = $this->createMock(RoomService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->api = $this->createMock(API::class);
		$this->permission = $this->createMock(Permission::class);
		$this->jobList = $this->createMock(IJobList::class);

		$this->controller = new JoinController(
			'bbb',
			$this->request,
			$this->service,
			$this->urlGenerator,
			$this->userSession,
			$this->api,
			$this->permission,
			$this->jobList
		);

		$this->room = new Room();
		$this->room->id = 1;
		$this->room->uid = 'uid_foo';
		$this->room->userId = 'user_foo';
		$this->room->access = Room::ACCESS_PUBLIC;
		$this->room->name = 'name_foo';
		$this->room->password = 'password_foo';
	}

	public function testNonExistingRoom() {
		$this->controller->setToken('foobar');
		$this->expectException(NotFoundException::class);
		$this->service
			->expects($this->once())
			->method('findByUID')
			->willReturn(null);

		$this->controller->index(null);
	}

	public function testUserIsLoggedIn() {
		$this->controller->setToken($this->room->uid);
		$this->service
			->expects($this->once())
			->method('findByUID')
			->willReturn($this->room);

		$this->userSession
			->expects($this->once())
			->method('isLoggedIn')
			->willReturn(true);

		$user = $this->createMock(IUser::class);
		$user->method('getDisplayName')->willReturn('User Bar');
		$user->method('getUID')->willReturn('user_bar');

		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->api
			->expects($this->once())
			->method('createMeeting')
			->willReturn(12345);

		$url = 'https://foobar';
		$this->api
			->expects($this->once())
			->method('createJoinUrl')
			->with($this->room, 12345, 'User Bar', false, 'user_bar')
			->willReturn($url);

		$result = $this->controller->index(null);

		$this->assertInstanceOf(TemplateResponse::class, $result);
		$this->assertEquals('forward', $result->getTemplateName());
		$this->assertEquals($url, $result->getParams()['url']);
	}

	public function testUserNeedsToAuthenticateForInternal() {
		$this->room->setAccess(Room::ACCESS_INTERNAL);

		$this->controller->setToken($this->room->uid);
		$this->service
			->expects($this->once())
			->method('findByUID')
			->willReturn($this->room);

		$this->userSession
			->expects($this->once())
			->method('isLoggedIn')
			->willReturn(false);

		$this->urlGenerator
			->expects($this->exactly(2))
			->method('linkToRoute')
			->will($this->returnValueMap([
				['core.login.showLoginForm', ['redirect_url' => 'https://join'], 'https://login'],
				['bbb.join.index', ['token' => $this->room->uid], 'https://join'],
			]));

		$result = $this->controller->index(null);

		$this->assertInstanceOf(RedirectResponse::class, $result);
		$this->assertEquals(Http::STATUS_SEE_OTHER, $result->getStatus());
	}

	public function testUserNeedsToAuthenticateForInternalRestricted() {
		$this->room->setAccess(Room::ACCESS_INTERNAL_RESTRICTED);

		$this->controller->setToken($this->room->uid);
		$this->service
			->expects($this->once())
			->method('findByUID')
			->willReturn($this->room);

		$this->userSession
			->expects($this->once())
			->method('isLoggedIn')
			->willReturn(false);

		$this->urlGenerator
			->expects($this->exactly(2))
			->method('linkToRoute')
			->will($this->returnValueMap([
				['core.login.showLoginForm', ['redirect_url' => 'https://join'], 'https://login'],
				['bbb.join.index', ['token' => $this->room->uid], 'https://join'],
			]));

		$result = $this->controller->index(null);

		$this->assertInstanceOf(RedirectResponse::class, $result);
		$this->assertEquals(Http::STATUS_SEE_OTHER, $result->getStatus());
	}

	public function testDisplaynames() {
		$this->controller->setToken($this->room->uid);
		$this->service
			->expects($this->once())
			->method('findByUID')
			->willReturn($this->room);

		$this->api
			->expects($this->once())
			->method('createMeeting')
			->willReturn(12345);

		$url = 'https://foobar';
		$this->api
			->expects($this->once())
			->method('createJoinUrl')
			->with($this->room, 12345, 'Foo Bar', false, null)
			->willReturn($url);

		$this->invalidDisplayname('a');
		$this->invalidDisplayname('    a');
		$this->invalidDisplayname('aa');

		$response = $this->controller->index('Foo Bar');

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('forward', $response->getTemplateName());
		$this->assertEquals($url, $response->getParams()['url']);
	}

	private function invalidDisplayname($displayname) {
		$response = $this->controller->index($displayname);

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('join', $response->getTemplateName());
		$this->assertTrue($response->getParams()['wrongdisplayname']);
	}

	public function testPasswordRequired() {
		$this->room->setAccess(Room::ACCESS_PASSWORD);
		$this->room->setPassword('asdf');

		$this->controller->setToken($this->room->uid);
		$this->service
			->method('findByUID')
			->willReturn($this->room);

		$this->api
			->expects($this->once())
			->method('createMeeting')
			->willReturn(12345);

		$url = 'https://foobar';
		$this->api
			->expects($this->once())
			->method('createJoinUrl')
			->willReturn($url);

		$response = $this->controller->index('Foo Bar', '', '', 'qwert');

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('join', $response->getTemplateName());
		$this->assertTrue($response->getParams()['passwordRequired']);
		$this->assertTrue($response->getParams()['wrongPassword']);

		$response = $this->controller->index('Foo Bar', '', '', 'asdf');

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('forward', $response->getTemplateName());
		$this->assertEquals($url, $response->getParams()['url']);
	}
}
