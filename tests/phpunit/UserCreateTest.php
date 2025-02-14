<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../lib/bootstrap.php');

class UserCreateTest extends TestCase {
    protected Gazelle\User $user;

    public function tearDown(): void {
        $this->user->remove();
    }

    public function testCreate(): void {
        $_SERVER['REMOTE_ADDR']     = '127.0.0.100';
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';

        $name         = 'create.' . randomString(6);
        $email        = "$name@example.com";
        $password     = randomString(40);
        $adminComment = 'Created by tests/phpunit/UserCreateTest.php';

        $this->user = (new \Gazelle\UserCreator)
            ->setUsername($name)
            ->setEmail($email)
            ->setPassword($password)
            ->setIpaddr($_SERVER['REMOTE_ADDR'])
            ->setAdminComment($adminComment)
            ->create();

        $this->assertEquals($name, $this->user->username(), 'user-create-username');
        $this->assertEquals($email, $this->user->email(), 'user-create-email');
        $this->assertStringContainsString($adminComment, $this->user->staffNotes(), 'user-create-staff-notes');
        $this->assertTrue($this->user->isUnconfirmed(), 'user-create-unconfirmed');
        $this->assertStringStartsWith(
            'static/styles/apollostage/style.css?v=',
            (new Gazelle\User\Stylesheet($this->user))->cssUrl(),
            'user-create-stylesheet'
        );

        $location = "user.php?id={$this->user->id()}";
        $this->assertEquals($location, $this->user->location(), 'user-location');
        $this->assertEquals(SITE_URL . "/$location", $this->user->publicLocation(), 'user-public-location');
        $this->assertEquals($location, $this->user->url(), 'user-url');
        $this->assertEquals(SITE_URL . "/$location", $this->user->publicUrl(), 'user-public-url');

        $login = new Gazelle\Login;
        $watch = new Gazelle\LoginWatch($_SERVER['REMOTE_ADDR']);
        $watch->clearAttempts();

        $result = $login->login($this->user->username(), 'not-the-password!', $watch);
        $this->assertNull($result, 'user-create-login-bad-pw-null');
        $this->assertEquals(\Gazelle\Login::ERR_CREDENTIALS, $login->error(), 'user-create-login-bad-pw-error');

        $result = $login->login($this->user->username(), $password, $watch);
        $this->assertNull($result, 'user-create-login-unconfirmed-null');
        $this->assertEquals(\Gazelle\Login::ERR_UNCONFIRMED, $login->error(), 'user-create-login-unconfirmed-error');

        $this->assertEquals(2, $watch->nrAttempts(), 'user-create-two-login-attempts');
        $this->user->setField('Enabled', '2')->modify();

        $enabledUser = $login->login($this->user->username(), $password, $watch);
        $this->assertInstanceOf(\Gazelle\User::class, $enabledUser, 'user-create-login-success');
        $this->assertEquals(0, $watch->nrAttempts(), 'user-create-two-login-cleared');
        $this->assertEquals(0, $watch->nrBans(), 'user-create-two-login-banned');
    }
}
