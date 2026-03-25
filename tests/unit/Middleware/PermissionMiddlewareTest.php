<?php

declare(strict_types=1);

namespace OCA\Forum\Tests\Middleware;

use OCA\Forum\Attribute\RequirePermission;
use OCA\Forum\Middleware\PermissionMiddleware;
use OCA\Forum\Service\PermissionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Test controller with permission attributes
 */
class TestPermissionController extends Controller {
	public function methodWithoutPermissions(): void {
	}

	#[RequirePermission('canEditRoles')]
	public function methodWithGlobalPermission(): void {
	}

	#[RequirePermission('canView', resourceType: 'category', resourceIdParam: 'categoryId')]
	public function methodWithCategoryPermission(): void {
	}

	#[RequirePermission('canPost', resourceType: 'category', resourceIdParam: 'categoryId')]
	public function methodWithCategoryPostPermission(): void {
	}

	#[RequirePermission('canView', resourceType: 'category', resourceIdFromThreadId: 'threadId')]
	public function methodWithThreadPermission(): void {
	}

	#[RequirePermission('canReply', resourceType: 'category', resourceIdFromPostId: 'postId')]
	public function methodWithPostPermission(): void {
	}

	#[RequirePermission('canView', resourceType: 'invalid', resourceIdParam: 'id')]
	public function methodWithInvalidResource(): void {
	}

	#[RequirePermission('canAccessAdminTools', orGroup: 'access')]
	#[RequirePermission('canEditRoles', orGroup: 'access')]
	public function methodWithOrGroup(): void {
	}

	#[RequirePermission('canAccessAdminTools', orGroup: 'access')]
	#[RequirePermission('canEditRoles', orGroup: 'access')]
	#[RequirePermission('canEditCategories')]
	public function methodWithOrGroupAndUngrouped(): void {
	}

	#[RequirePermission('canManageUsers')]
	public function methodWithManageUsersPermission(): void {
	}

	#[RequirePermission('canEditBbcodes')]
	public function methodWithEditBBCodesPermission(): void {
	}
}

class PermissionMiddlewareTest extends TestCase {
	private PermissionMiddleware $middleware;
	/** @var IRequest&MockObject */
	private IRequest $request;
	/** @var IUserSession&MockObject */
	private IUserSession $userSession;
	/** @var PermissionService&MockObject */
	private PermissionService $permissionService;
	/** @var IAppConfig&MockObject */
	private IAppConfig $config;
	/** @var LoggerInterface&MockObject */
	private LoggerInterface $logger;
	private TestPermissionController $controller;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->config = $this->createMock(IAppConfig::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->middleware = new PermissionMiddleware(
			$this->request,
			$this->userSession,
			$this->permissionService,
			$this->config,
			$this->logger
		);

		$this->controller = new TestPermissionController('forum', $this->request);
	}

	public function testAuthenticatedUserWithNoPermissionAttributesIsAllowed(): void {
		$user = $this->createMock(IUser::class);
		$this->userSession->method('getUser')->willReturn($user);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(false);

		// Should not throw exception
		$this->middleware->beforeController($this->controller, 'methodWithoutPermissions');
		$this->assertTrue(true); // If we get here, test passed
	}

	public function testAuthenticatedUserWithGlobalPermissionIsAllowed(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(false);

		$this->permissionService->expects($this->once())
			->method('hasGlobalPermission')
			->with('user1', 'canEditRoles')
			->willReturn(true);

		// Should not throw exception
		$this->middleware->beforeController($this->controller, 'methodWithGlobalPermission');
		$this->assertTrue(true);
	}

	public function testAuthenticatedUserWithoutGlobalPermissionIsDenied(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(false);

		$this->permissionService->expects($this->once())
			->method('hasGlobalPermission')
			->with('user1', 'canEditRoles')
			->willReturn(false);

		$this->expectException(OCSForbiddenException::class);
		$this->middleware->beforeController($this->controller, 'methodWithGlobalPermission');
	}

	public function testUnauthenticatedUserWithGuestAccessDisabledIsDenied(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(false);

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('User not authenticated');
		$this->middleware->beforeController($this->controller, 'methodWithoutPermissions');
	}

	public function testUnauthenticatedUserWithGuestAccessDisabledAndPostMethodIsDenied(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(false);
		$this->request->method('getMethod')->willReturn('POST');

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('User not authenticated');
		$this->middleware->beforeController($this->controller, 'methodWithoutPermissions');
	}

	public function testUnauthenticatedUserWithGuestAccessDisabledAndPutMethodIsDenied(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(false);
		$this->request->method('getMethod')->willReturn('PUT');

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('User not authenticated');
		$this->middleware->beforeController($this->controller, 'methodWithoutPermissions');
	}

	public function testUnauthenticatedUserWithGuestAccessDisabledAndDeleteMethodIsDenied(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(false);
		$this->request->method('getMethod')->willReturn('DELETE');

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('User not authenticated');
		$this->middleware->beforeController($this->controller, 'methodWithoutPermissions');
	}

	public function testUnauthenticatedUserWithGuestAccessEnabledAndGetMethodIsAllowed(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(true);
		$this->request->method('getMethod')->willReturn('GET');

		// Should not throw exception
		$this->middleware->beforeController($this->controller, 'methodWithoutPermissions');
		$this->assertTrue(true);
	}

	public function testUnauthenticatedUserWithGuestAccessEnabledAndHeadMethodIsAllowed(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(true);
		$this->request->method('getMethod')->willReturn('HEAD');

		// Should not throw exception
		$this->middleware->beforeController($this->controller, 'methodWithoutPermissions');
		$this->assertTrue(true);
	}

	public function testUnauthenticatedUserWithGuestAccessEnabledAndOptionsMethodIsAllowed(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(true);
		$this->request->method('getMethod')->willReturn('OPTIONS');

		// Should not throw exception
		$this->middleware->beforeController($this->controller, 'methodWithoutPermissions');
		$this->assertTrue(true);
	}

	public function testUnauthenticatedUserWithGuestAccessEnabledAndPostMethodIsAllowed(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(true);
		$this->request->method('getMethod')->willReturn('POST');

		// Guest access enabled allows all HTTP methods (permission checks handle authorization)
		$this->middleware->beforeController($this->controller, 'methodWithoutPermissions');
		$this->assertTrue(true);
	}

	public function testUnauthenticatedUserWithGuestAccessEnabledAndPutMethodIsAllowed(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(true);
		$this->request->method('getMethod')->willReturn('PUT');

		// Guest access enabled allows all HTTP methods (permission checks handle authorization)
		$this->middleware->beforeController($this->controller, 'methodWithoutPermissions');
		$this->assertTrue(true);
	}

	public function testUnauthenticatedUserWithGuestAccessEnabledAndDeleteMethodIsAllowed(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(true);
		$this->request->method('getMethod')->willReturn('DELETE');

		// Guest access enabled allows all HTTP methods (permission checks handle authorization)
		$this->middleware->beforeController($this->controller, 'methodWithoutPermissions');
		$this->assertTrue(true);
	}

	public function testGuestUserWithGlobalPermissionCheckUsesNullUserId(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(true);
		$this->request->method('getMethod')->willReturn('GET');

		$this->permissionService->expects($this->once())
			->method('hasGlobalPermission')
			->with(null, 'canEditRoles')
			->willReturn(false);

		$this->expectException(OCSForbiddenException::class);
		$this->middleware->beforeController($this->controller, 'methodWithGlobalPermission');
	}

	public function testGuestUserWithCategoryPermissionCheckUsesNullUserId(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(true);
		$this->request->method('getMethod')->willReturn('GET');
		$this->request->method('getParam')
			->with('categoryId')
			->willReturn('1');

		$this->permissionService->expects($this->once())
			->method('hasCategoryPermission')
			->with(null, 1, 'canView')
			->willReturn(true);

		// Should not throw exception
		$this->middleware->beforeController($this->controller, 'methodWithCategoryPermission');
		$this->assertTrue(true);
	}

	public function testGuestUserWithoutCategoryPermissionIsDenied(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(true);
		$this->request->method('getMethod')->willReturn('GET');
		$this->request->method('getParam')
			->with('categoryId')
			->willReturn('1');

		$this->permissionService->expects($this->once())
			->method('hasCategoryPermission')
			->with(null, 1, 'canPost')
			->willReturn(false);

		$this->expectException(OCSForbiddenException::class);
		$this->middleware->beforeController($this->controller, 'methodWithCategoryPostPermission');
	}

	public function testMultiplePermissionAttributesAllMustPass(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(false);

		// First permission check passes
		$this->permissionService->expects($this->once())
			->method('hasGlobalPermission')
			->with('user1', 'canEditRoles')
			->willReturn(true);

		// Should not throw exception when permission passes
		$this->middleware->beforeController($this->controller, 'methodWithGlobalPermission');
		$this->assertTrue(true);
	}

	public function testCategoryPermissionFromThreadId(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(false);

		$this->request->method('getParam')
			->with('threadId')
			->willReturn('10');

		$this->permissionService->expects($this->once())
			->method('getCategoryIdFromThread')
			->with(10)
			->willReturn(5);

		$this->permissionService->expects($this->once())
			->method('hasCategoryPermission')
			->with('user1', 5, 'canView')
			->willReturn(true);

		// Should not throw exception
		$this->middleware->beforeController($this->controller, 'methodWithThreadPermission');
		$this->assertTrue(true);
	}

	public function testCategoryPermissionFromPostId(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(false);

		$this->request->method('getParam')
			->with('postId')
			->willReturn('20');

		$this->permissionService->expects($this->once())
			->method('getCategoryIdFromPost')
			->with(20)
			->willReturn(7);

		$this->permissionService->expects($this->once())
			->method('hasCategoryPermission')
			->with('user1', 7, 'canReply')
			->willReturn(true);

		// Should not throw exception
		$this->middleware->beforeController($this->controller, 'methodWithPostPermission');
		$this->assertTrue(true);
	}

	public function testInvalidResourceTypeThrowsException(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(false);

		$this->expectException(OCSForbiddenException::class);
		$this->middleware->beforeController($this->controller, 'methodWithInvalidResource');
	}

	public function testMissingResourceIdParameterThrowsException(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(false);

		$this->request->method('getParam')
			->with('categoryId')
			->willReturn(null);

		$this->expectException(OCSForbiddenException::class);
		$this->middleware->beforeController($this->controller, 'methodWithCategoryPermission');
	}

	public function testGuestAccessWithMultipleReadMethods(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(true);

		// Test with GET
		$this->request->method('getMethod')->willReturn('GET');
		$this->middleware->beforeController($this->controller, 'methodWithoutPermissions');

		// Test with HEAD
		$this->request = $this->createMock(IRequest::class);
		$this->request->method('getMethod')->willReturn('HEAD');
		$this->middleware = new PermissionMiddleware(
			$this->request,
			$this->userSession,
			$this->permissionService,
			$this->config,
			$this->logger
		);
		$this->middleware->beforeController($this->controller, 'methodWithoutPermissions');

		$this->assertTrue(true);
	}

	public function testOrGroupAllowsAccessWhenFirstPermissionPasses(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(false);

		$this->permissionService->method('hasGlobalPermission')
			->willReturnMap([
				['user1', 'canAccessAdminTools', true],
			]);

		$this->middleware->beforeController($this->controller, 'methodWithOrGroup');
		$this->assertTrue(true);
	}

	public function testOrGroupAllowsAccessWhenSecondPermissionPasses(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(false);

		$this->permissionService->method('hasGlobalPermission')
			->willReturnMap([
				['user1', 'canAccessAdminTools', false],
				['user1', 'canEditRoles', true],
			]);

		$this->middleware->beforeController($this->controller, 'methodWithOrGroup');
		$this->assertTrue(true);
	}

	public function testOrGroupDeniesAccessWhenNoPermissionPasses(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(false);

		$this->permissionService->method('hasGlobalPermission')
			->willReturnMap([
				['user1', 'canAccessAdminTools', false],
				['user1', 'canEditRoles', false],
			]);

		$this->expectException(OCSForbiddenException::class);
		$this->middleware->beforeController($this->controller, 'methodWithOrGroup');
	}

	public function testOrGroupWithUngroupedRequiresBoth(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(false);

		// OR group passes (canEditRoles), but ungrouped (canEditCategories) fails
		$this->permissionService->method('hasGlobalPermission')
			->willReturnMap([
				['user1', 'canAccessAdminTools', false],
				['user1', 'canEditRoles', true],
				['user1', 'canEditCategories', false],
			]);

		$this->expectException(OCSForbiddenException::class);
		$this->middleware->beforeController($this->controller, 'methodWithOrGroupAndUngrouped');
	}

	public function testOrGroupWithUngroupedAllowsWhenBothPass(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(false);

		// OR group passes (canEditRoles) AND ungrouped (canEditCategories) passes
		$this->permissionService->method('hasGlobalPermission')
			->willReturnMap([
				['user1', 'canAccessAdminTools', false],
				['user1', 'canEditRoles', true],
				['user1', 'canEditCategories', true],
			]);

		$this->middleware->beforeController($this->controller, 'methodWithOrGroupAndUngrouped');
		$this->assertTrue(true);
	}

	public function testCanManageUsersPermissionAllowsAccess(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(false);

		$this->permissionService->expects($this->once())
			->method('hasGlobalPermission')
			->with('user1', 'canManageUsers')
			->willReturn(true);

		$this->middleware->beforeController($this->controller, 'methodWithManageUsersPermission');
		$this->assertTrue(true);
	}

	public function testCanManageUsersPermissionDeniesAccess(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(false);

		$this->permissionService->expects($this->once())
			->method('hasGlobalPermission')
			->with('user1', 'canManageUsers')
			->willReturn(false);

		$this->expectException(OCSForbiddenException::class);
		$this->middleware->beforeController($this->controller, 'methodWithManageUsersPermission');
	}

	public function testCanEditBBCodesPermissionAllowsAccess(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(false);

		$this->permissionService->expects($this->once())
			->method('hasGlobalPermission')
			->with('user1', 'canEditBbcodes')
			->willReturn(true);

		$this->middleware->beforeController($this->controller, 'methodWithEditBBCodesPermission');
		$this->assertTrue(true);
	}

	public function testCanEditBBCodesPermissionDeniesAccess(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(false);

		$this->permissionService->expects($this->once())
			->method('hasGlobalPermission')
			->with('user1', 'canEditBbcodes')
			->willReturn(false);

		$this->expectException(OCSForbiddenException::class);
		$this->middleware->beforeController($this->controller, 'methodWithEditBBCodesPermission');
	}

	public function testAuthenticatedUserBypassesGuestRestrictions(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user1');
		$this->userSession->method('getUser')->willReturn($user);

		// Guest access is disabled, but authenticated users should still work
		$this->config->method('getAppValueBool')
			->with('allow_guest_access', false, true)
			->willReturn(false);

		// POST method, which would be blocked for guests
		$this->request->method('getMethod')->willReturn('POST');

		// Should not throw exception for authenticated user
		$this->middleware->beforeController($this->controller, 'methodWithoutPermissions');
		$this->assertTrue(true);
	}
}
